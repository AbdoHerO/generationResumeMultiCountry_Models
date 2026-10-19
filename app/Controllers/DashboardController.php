<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Cv;
use App\Models\Template;
use App\Services\Auth;
use App\Services\Countries;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => mb_substr($request->string('q'), 0, 80),
            'country' => strtoupper($request->string('country')),
            'template' => $request->string('template'),
            'status' => $request->string('status'),
            'sort' => $request->string('sort', 'updated'),
        ];
        $userId = Auth::id();
        $cvs = Cv::forUser($userId, $filters);
        $all = Cv::forUser($userId);
        $filtered = array_filter([$filters['q'], $filters['country'], $filters['template'], $filters['status']]) !== [];
        return $this->view('pages/dashboard', [
            'title' => 'My CVs',
            'cvs' => $cvs,
            'total' => count($all),
            'completed' => count(array_filter($all, fn ($c) => $c['status'] === 'completed')),
            'recent' => array_slice($all, 0, 3),
            'filters' => $filters,
            'filtered' => $filtered,
            'countries' => Countries::all(),
            'templates' => Template::all(false),
        ]);
    }
}
