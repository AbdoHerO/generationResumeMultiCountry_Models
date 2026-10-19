<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Profile;
use App\Models\ProfileImport;
use App\Services\Ai\GroqClient;
use App\Services\Auth;
use App\Services\Profile\ProfileService;

/** The global profile, the PDF import page and the review of an import. */
final class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $profile = ProfileService::load(Auth::id());
        $lang = in_array($request->string('lang'), ProfileService::LANGUAGES, true) ? $request->string('lang') : 'fr';
        return $this->view('profile/editor', [
            'title' => 'My profile',
            'mode' => 'profile',
            'filled' => $profile['filled'],
            'aiAvailable' => GroqClient::available(),
            'state' => ProfileService::editorState('profile', 'profile-' . Auth::id(), $profile['data'], $profile['version'], $lang, $profile['exists']),
        ], 'layouts/editor');
    }

    public function import(Request $request): Response
    {
        ProfileImport::purgeOld();
        return $this->view('profile/import', [
            'title' => 'Import a CV from PDF',
            'aiAvailable' => GroqClient::available(),
            'hasProfile' => Profile::find(Auth::id()) !== null,
            'recent' => ProfileImport::recentForUser(Auth::id()),
            'maxMb' => (int) round(config('app.ai.import_max_bytes') / 1048576),
        ]);
    }

    public function review(Request $request): Response
    {
        $import = ProfileImport::findOwned((int) $request->param('id'), Auth::id());
        if (!$import) {
            throw new HttpException(404);
        }
        if ($import['status'] !== 'ready') {
            return $this->redirect('/profile/import');
        }
        $data = ProfileService::sanitizeData($import['data']);
        $filled = array_map(fn ($c) => ProfileService::hasContent($c), $data);
        $lang = in_array($import['source_language'], ProfileService::LANGUAGES, true) ? (string) $import['source_language'] : 'fr';
        return $this->view('profile/editor', [
            'title' => 'Review your imported CV',
            'mode' => 'import',
            'import' => $import,
            'filled' => $filled,
            'aiAvailable' => true,
            'state' => ProfileService::editorState('import', 'import-' . $import['id'], $data, $import['version'], $lang, Profile::find(Auth::id()) !== null),
        ], 'layouts/editor');
    }
}
