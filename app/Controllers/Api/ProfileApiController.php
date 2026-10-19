<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\ProfileImport;
use App\Services\Ai\AiException;
use App\Services\Ai\GroqClient;
use App\Services\Auth;
use App\Services\Profile\ProfileService;
use App\Services\Profile\ResumeImporter;

/**
 * Profile and PDF import endpoints. Every import is loaded with the user's id: another user's
 * import answers 404. The import runs as several short requests (extract, then one translation
 * per language) so each stays well under the web server's 60-second limit.
 */
final class ProfileApiController extends Controller
{
    public function show(Request $request): Response
    {
        $p = ProfileService::load(Auth::id());
        return Response::ok(['version' => $p['version'], 'filled' => $p['filled'], 'data' => $p['data']]);
    }

    public function save(Request $request): Response
    {
        $in = $request->json();
        $data = ProfileService::sanitizeData($in['data'] ?? null);
        $current = Profile::find(Auth::id());
        $expected = $current === null ? null : (int) ($in['version'] ?? 0);
        $version = Profile::save(Auth::id(), $data, $expected);
        if ($version === null) {
            throw new HttpException(409, 'Your profile was changed in another tab or window. Reload to get the latest version.', [], 'version_conflict');
        }
        return Response::ok(['version' => $version, 'savedAt' => gmdate('c')], 'Profile saved');
    }

    /** Step 1: upload the PDF and extract its text (no AI call here: it returns at once). */
    public function import(Request $request): Response
    {
        $userId = Auth::id();
        if (!GroqClient::available()) {
            throw new HttpException(503, 'The CV import is not available yet: an administrator must configure the AI service.', [], 'ai_unavailable');
        }
        if (ProfileImport::countRecent($userId, 60) >= (int) config('app.ai.imports_per_hour', 10)) {
            throw new HttpException(429, 'You have imported many CVs in the last hour. Please try again later.', [], 'too_many_imports');
        }
        $file = $request->file('pdf');
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new HttpException(422, 'Choose a PDF file to import.', ['pdf' => 'required']);
        }
        $error = (int) $file['error'];
        $max = (int) config('app.ai.import_max_bytes');
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || (int) ($file['size'] ?? 0) > $max) {
            throw new HttpException(422, 'This PDF is larger than ' . round($max / 1048576) . ' MB.', ['pdf' => 'too_large'], 'pdf_too_large');
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new HttpException(422, 'The file could not be uploaded. Please try again.', ['pdf' => 'upload']);
        }
        $tmp = (string) $file['tmp_name'];
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $head = (string) file_get_contents($tmp, false, null, 0, 5);
        if ($mime !== 'application/pdf' || $head !== '%PDF-') {
            throw new HttpException(422, 'Please choose a PDF file.', ['pdf' => 'type'], 'pdf_type');
        }
        $text = ResumeImporter::extractText($tmp);
        @unlink($tmp); // the PDF is never kept

        $name = (string) preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', basename((string) ($file['name'] ?? 'cv.pdf')));
        $id = ProfileImport::create($userId, $name);
        $data = ResumeImporter::start($text);
        ProfileImport::store($id, $userId, $data, 'processing');
        Logger::info('CV import started', ['import' => $id, 'chars' => mb_strlen($text), 'parts' => count($data['work']['parts'])]);
        return Response::ok(['id' => $id, 'progress' => ResumeImporter::progress($data)], 'PDF read');
    }

    /**
     * Step 2, called repeatedly by the page: structures and translates as much as fits in one
     * request, saving the progress after the work done. When the AI quota is reached it answers
     * 429 with the seconds to wait, and the page calls again.
     */
    public function continue(Request $request): Response
    {
        $budget = (int) config('app.ai.step_budget', 48);
        @set_time_limit($budget + 10);
        $import = $this->ownedImport($request);
        if ($import['status'] === 'ready') {
            return Response::ok(['progress' => ResumeImporter::progress($import['data']), 'ready' => true, 'reviewUrl' => url('/profile/imports/' . $import['id'])]);
        }
        if (!in_array($import['status'], ['processing', 'failed'], true) || !isset($import['data']['work'])) {
            throw new HttpException(409, 'This import cannot be continued. Start a new import.', [], 'import_state');
        }
        $result = (new ResumeImporter())->advance($import['data'], microtime(true) + $budget);
        $data = $result['data'];
        $ready = !isset($data['work']);
        ProfileImport::store($import['id'], Auth::id(), $data, $ready ? 'ready' : 'processing', $data['sourceLanguage'] ?? null);
        if ($result['error'] !== null) {
            if ($result['error']->reason !== 'rate_limited') {
                Logger::warning('CV import step failed', ['import' => $import['id'], 'reason' => $result['error']->reason]);
            }
            throw self::aiError($result['error']);
        }
        if ($ready) {
            Logger::info('CV import ready', ['import' => $import['id'], 'language' => $data['sourceLanguage'] ?? '']);
        }
        return Response::ok(['progress' => ResumeImporter::progress($data), 'ready' => $ready, 'reviewUrl' => url('/profile/imports/' . $import['id'])]);
    }

    /** Current version of an import (the editor checks it after a conflict). */
    public function showImport(Request $request): Response
    {
        $import = $this->ownedImport($request);
        return Response::ok(['version' => $import['version'], 'status' => $import['status']]);
    }

    /** Edits made while reviewing (autosave). */
    public function saveImport(Request $request): Response
    {
        $import = $this->ownedImport($request);
        $in = $request->json();
        $data = ProfileService::sanitizeData($in['data'] ?? null);
        $keep = ['source' => $import['data']['source'] ?? null, 'sourceLanguage' => $import['data']['sourceLanguage'] ?? ''];
        $version = ProfileImport::saveEdits($import['id'], Auth::id(), $data + $keep, (int) ($in['version'] ?? 0));
        if ($version === null) {
            throw new HttpException(409, 'This import was changed in another tab or window. Reload to continue.', [], 'version_conflict');
        }
        return Response::ok(['version' => $version, 'savedAt' => gmdate('c')], 'Changes saved');
    }

    /** Step 3: the reviewed versions become the profile. */
    public function apply(Request $request): Response
    {
        $import = $this->ownedImport($request);
        if ($import['status'] !== 'ready') {
            throw new HttpException(409, 'This import is not ready to be saved.', [], 'import_state');
        }
        $data = ProfileService::sanitizeData($import['data']);
        Profile::save(Auth::id(), $data, null);
        ProfileImport::setStatus($import['id'], Auth::id(), 'applied');
        AuditLog::record(Auth::id(), 'account.profile_imported', 'profile', Auth::id(), ['import' => $import['id']], $request->ip());
        return Response::ok(['profileUrl' => url('/profile')], 'Your profile has been updated');
    }

    public function deleteImport(Request $request): Response
    {
        $import = $this->ownedImport($request);
        ProfileImport::delete($import['id'], Auth::id());
        return Response::ok([], 'Import discarded');
    }

    /** @return array<string, mixed> */
    private function ownedImport(Request $request): array
    {
        $import = ProfileImport::findOwned((int) $request->param('id'), Auth::id());
        if (!$import) {
            throw new HttpException(404, 'This import does not exist.');
        }
        return $import;
    }

    /** AI failures as API errors; a rate limit carries the seconds to wait, so the page can retry by itself. */
    private static function aiError(AiException $e): HttpException
    {
        return match ($e->reason) {
            'not_configured' => new HttpException(503, $e->getMessage(), [], 'ai_not_configured'),
            'rate_limited' => new HttpException(429, $e->getMessage(), ['retryAfter' => $e->retryAfter], 'ai_rate_limited'),
            default => new HttpException(502, $e->getMessage(), [], 'ai_' . $e->reason),
        };
    }
}
