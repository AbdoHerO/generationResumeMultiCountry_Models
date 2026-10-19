<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Template;
use App\Services\Auth;
use App\Services\Countries;
use App\Services\Cv\CvRenderer;
use App\Services\Cv\CvService;
use App\Services\Cv\PdfService;
use App\Services\PhotoService;

final class CvController extends Controller
{
    /** Creation wizard: country and language, then template. */
    public function create(Request $request): Response
    {
        $country = strtoupper($request->string('country'));
        $template = $request->string('template');
        $preselected = $template !== '' ? Template::findBySlug($template) : null;
        if ($preselected && $preselected['is_active'] && Countries::exists($preselected['country_code'])) {
            $country = $preselected['country_code'];
        } else {
            $preselected = null;
        }
        return $this->view('cvs/new', [
            'title' => 'Create a new CV',
            'countries' => Countries::all(true),
            'templates' => Template::all(),
            'country' => Countries::exists($country) ? $country : '',
            'preselected' => $preselected,
            'profileLanguages' => array_keys(array_filter(\App\Services\Profile\ProfileService::filledLanguages(\App\Services\Auth::id()))),
        ]);
    }

    public function store(Request $request): Response
    {
        try {
            $cv = CvService::create(
                Auth::id(),
                $request->string('country'),
                $request->string('language'),
                $request->string('template') ?: null,
                $request->string('title'),
                $request->string('from_profile') === '1'
            );
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/cvs/new');
        }
        Session::flash('success', 'Your CV is ready. Start with your personal details — everything saves automatically.');
        return $this->redirect('/cvs/' . $cv['id'] . '/edit');
    }

    public function edit(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        return $this->view('cvs/editor', [
            'title' => $cv['title'],
            'cv' => $cv,
            'state' => CvService::editorState($cv),
            'bodyClass' => 'editor-page',
        ], 'layouts/editor');
    }

    /** A4 PDF download (or inline view with ?inline=1). */
    public function download(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        $template = Template::find((int) $cv['template_id']);
        if (!$template) {
            throw new HttpException(404);
        }
        try {
            $pdf = (new PdfService())->generate($cv, $template, CvService::photoSources($cv));
        } catch (\Throwable $e) {
            Logger::error('PDF generation failed', ['cv' => $cv['id'], 'error' => $e->getMessage()]);
            throw new HttpException(500, 'The PDF could not be generated. Please try again in a moment.');
        }
        return Response::file($pdf['bytes'], 'application/pdf', PdfService::filename($cv), $request->string('inline') === '1')
            ->withHeader('X-Page-Count', (string) $pdf['pages']);
    }

    /** Saved CV as an HTML page (dashboard thumbnails). */
    public function render(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        $template = Template::find((int) $cv['template_id']);
        if (!$template) {
            throw new HttpException(404);
        }
        $html = (new CvRenderer())->render($cv, $template, 'preview', CvService::photoSources($cv))['html'];
        return Response::html($html);
    }

    /** The CV's photo, only for its owner. */
    public function photo(Request $request): Response
    {
        $cv = $this->ownedCv($request);
        $shape = $request->string('shape') === 'circle' ? 'circle' : 'square';
        $file = $cv['photo_path'] ? PhotoService::path((string) $cv['photo_path'], $shape) : null;
        if (!$file) {
            throw new HttpException(404);
        }
        return (new Response((string) file_get_contents($file), 200, [
            'Content-Type' => $shape === 'circle' ? 'image/png' : 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }
}
