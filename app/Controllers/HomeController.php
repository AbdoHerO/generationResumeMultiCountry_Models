<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Template;
use App\Services\Countries;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $templates = Template::all();
        $featured = [];
        foreach (['fr-classic', 'us-technical', 'ma-bilingual', 'cn-classic', 'ca-modern', 'fr-creative'] as $slug) {
            foreach ($templates as $t) {
                if ($t['slug'] === $slug) {
                    $featured[] = $t;
                }
            }
        }
        $countries = Countries::all(true);
        $counts = [];
        foreach ($templates as $t) {
            $counts[$t['country_code']] = ($counts[$t['country_code']] ?? 0) + 1;
        }
        return $this->view('pages/landing', [
            'title' => 'Professional CVs for France, Morocco, the US, Canada and China',
            'featured' => $featured,
            'countries' => $countries,
            'counts' => $counts,
            'templateCount' => count($templates),
        ], 'layouts/public');
    }

    public function privacy(Request $request): Response
    {
        return $this->view('pages/privacy', ['title' => 'Privacy & security'], 'layouts/public');
    }

    /** Health check for the hosting platform: 200 when PHP and MySQL answer. */
    public function health(Request $request): Response
    {
        try {
            $ok = (int) Database::value('SELECT 1') === 1;
        } catch (\Throwable) {
            $ok = false;
        }
        return Response::json(['status' => $ok ? 'ok' : 'degraded', 'database' => $ok], $ok ? 200 : 503);
    }
}
