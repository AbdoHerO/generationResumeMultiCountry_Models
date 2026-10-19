<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Template;
use App\Services\Countries;
use App\Services\Cv\CvRenderer;
use App\Services\Cv\Design;
use App\Services\Cv\SampleContent;

final class TemplateController extends Controller
{
    public function gallery(Request $request): Response
    {
        $country = strtoupper($request->string('country'));
        return $this->view('pages/gallery', [
            'title' => 'CV templates',
            'templates' => array_values(array_filter(Template::all(), fn ($t) => Countries::exists($t['country_code']))),
            'countries' => Countries::all(true),
            'selectedCountry' => Countries::exists($country) ? $country : '',
            'categories' => ['classic', 'modern', 'executive', 'creative', 'compact', 'academic', 'bilingual', 'technical', 'entry-level', 'career'],
        ], 'layouts/public');
    }

    /** The real template rendered with sample content (used by gallery and wizard previews). */
    public function preview(Request $request): Response
    {
        $template = Template::findBySlug($request->param('slug'));
        // Hidden templates stay previewable for administrators (template management page).
        if (!$template || (!$template['is_active'] && !is_admin())) {
            throw new HttpException(404);
        }
        $lang = $request->string('lang');
        if (!in_array($lang, $template['languages'], true)) {
            $lang = Countries::get($template['country_code'])['default_language'] ?? $template['languages'][0];
            if (!in_array($lang, $template['languages'], true)) {
                $lang = $template['languages'][0];
            }
        }
        $design = Design::defaults($template, $template['country_code']);
        $accent = strtoupper($request->string('accent'));
        if ($accent !== '' && array_key_exists($accent, config('design.palette'))) {
            $design['accent'] = $accent;
        }
        $cv = [
            'content' => SampleContent::for($template['country_code'], $lang),
            'design' => $design,
            'language_code' => $lang,
            'country_code' => $template['country_code'],
        ];
        $html = (new CvRenderer())->render($cv, $template, 'preview')['html'];
        return Response::html($html)->withHeader('Cache-Control', $template['is_active'] ? 'public, max-age=600' : 'private, no-store');
    }
}
