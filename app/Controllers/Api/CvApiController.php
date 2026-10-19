<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Models\Cv;
use App\Models\Template;
use App\Services\Auth;
use App\Services\Countries;
use App\Services\Cv\CvRenderer;
use App\Services\Cv\CvSchema;
use App\Services\Cv\CvService;
use App\Services\Cv\Design;
use App\Services\Cv\PdfService;
use App\Services\PhotoService;

/**
 * JSON endpoints. Every response uses the envelope {ok, message, data|errors, code}.
 * Every action loads the CV through ownedCv(): another user's ID answers 404.
 */
final class CvApiController extends Controller
{
    public function show(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        return Response::ok(CvService::editorState($cv)['cv']);
    }

    /** Autosave and manual save (optimistic concurrency on `version`). */
    public function save(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        $result = CvService::save($cv, $request->json());
        return Response::ok([
            'version' => $result['version'],
            'status' => $result['status'],
            'completeness' => $result['completeness'],
            'title' => $result['title'],
            'savedAt' => gmdate('c'),
        ], 'All changes saved');
    }

    /** Renders unsaved editor state to HTML for the live preview (nothing is stored). */
    public function preview(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        $in = $request->json();
        $country = strtoupper((string) ($in['country'] ?? $cv['country_code']));
        $templateId = (int) ($in['templateId'] ?? $cv['template_id']);
        $template = Template::find($templateId);
        if (!$template || $template['country_code'] !== $country || (!$template['is_active'] && $templateId !== (int) $cv['template_id'])) {
            throw new HttpException(422, 'This template is not available for the selected country.');
        }
        $lang = Countries::resolveLanguage($country, (string) ($in['language'] ?? $cv['language_code']));
        $data = [
            'content' => CvSchema::sanitize($in['content'] ?? null),
            'design' => Design::sanitize($in['design'] ?? [], $template, $country),
            'language_code' => $lang,
            'country_code' => $country,
        ];
        $doc = (new CvRenderer())->render($data, $template, 'preview', CvService::photoSources($cv));
        return Response::ok(['html' => $doc['html'], 'dir' => $doc['dir']]);
    }

    /** Exact page count from a real PDF render of the current editor state. */
    public function pages(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        $in = $request->json();
        $template = Template::find((int) ($in['templateId'] ?? $cv['template_id']));
        if (!$template || $template['country_code'] !== strtoupper((string) ($in['country'] ?? $cv['country_code']))) {
            throw new HttpException(422, 'This template is not available for the selected country.');
        }
        $data = $cv;
        $data['content'] = CvSchema::sanitize($in['content'] ?? $cv['content']);
        $data['design'] = Design::sanitize($in['design'] ?? $cv['design'], $template, (string) $template['country_code']);
        $data['language_code'] = Countries::resolveLanguage((string) $template['country_code'], (string) ($in['language'] ?? $cv['language_code']));
        $pdf = (new PdfService())->generate($data, $template, CvService::photoSources($cv));
        return Response::ok(['pages' => $pdf['pages']]);
    }

    public function fromProfile(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        $in = $request->json();
        $lang = (string) ($in['language'] ?? $cv['language_code']);
        $template = Template::find((int) ($in['templateId'] ?? $cv['template_id']));
        $country = strtoupper((string) ($in['country'] ?? $cv['country_code']));
        if (!$template || $template['country_code'] !== $country) {
            throw new HttpException(422, 'This template is not available for the selected country.');
        }
        $names = \App\Services\Profile\ProfileService::LANGUAGE_NAMES;
        if (!isset($names[$lang])) {
            throw new HttpException(422, 'Your profile exists in French, English and Chinese. Switch this CV to one of these languages to fill it from your profile.', [], 'profile_language');
        }
        $order = $template['config']['section_order'] ?? (Countries::get($country)['section_order'] ?? []);
        $content = \App\Services\Profile\ProfileService::contentForCv(Auth::id(), $lang, $country, $order);
        if ($content === null) {
            throw new HttpException(422, "Your profile has no {$names[$lang]} version yet. Fill it in or import a PDF from My profile.", [], 'profile_empty');
        }
        return Response::ok(['content' => $content, 'language' => $lang, 'languageName' => $names[$lang]]);
    }

    public function rename(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        $title = CvSchema::clean($request->input('title', ''), 120);
        if ($title === '') {
            throw new HttpException(422, 'Please enter a name for this CV.', ['title' => 'required']);
        }
        Cv::rename((int) $cv['id'], Auth::id(), $title);
        $fresh = Cv::findOwned((int) $cv['id'], Auth::id());
        return Response::ok(['title' => $title, 'version' => $fresh['version'] ?? null], 'CV renamed');
    }

    public function duplicate(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        $copy = CvService::duplicate($cv, Auth::id());
        return Response::ok(['id' => $copy['id'], 'title' => $copy['title'], 'editUrl' => url('/cvs/' . $copy['id'] . '/edit')], 'CV duplicated');
    }

    public function delete(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        CvService::delete($cv);
        return Response::ok(['id' => $cv['id']], 'CV deleted');
    }

    public function uploadPhoto(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        $file = $request->file('photo');
        if (!$file) {
            throw new HttpException(422, 'Choose an image to upload.', ['photo' => 'required']);
        }
        $id = PhotoService::store($file);
        $old = $cv['photo_path'] ?? null;
        Cv::setPhoto((int) $cv['id'], Auth::id(), $id);
        if ($old && !Cv::photoInUse((string) $old)) {
            PhotoService::delete((string) $old);
        }
        Logger::info('Photo uploaded', ['cv' => $cv['id']]);
        $cv['photo_path'] = $id;
        return Response::ok(['photo' => CvService::photoSources($cv)['preview_square'] ?? null], 'Photo updated');
    }

    public function deletePhoto(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        if ($cv['photo_path']) {
            Cv::setPhoto((int) $cv['id'], Auth::id(), null);
            if (!Cv::photoInUse((string) $cv['photo_path'])) {
                PhotoService::delete((string) $cv['photo_path']);
            }
        }
        return Response::ok([], 'Photo removed');
    }

    /** Lets the editor check its session before retrying a save. */
    public function session(Request $request): Response
    {
        return Response::ok(['user' => Auth::id(), 'csrf' => csrf_token()]);
    }
}
