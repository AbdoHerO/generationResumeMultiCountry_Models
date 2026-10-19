<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\Admin\AdminController;
use App\Controllers\Admin\AdminTemplateController;
use App\Controllers\Admin\AdminUserController;
use App\Controllers\Api\CvApiController;
use App\Controllers\Api\ProfileApiController;
use App\Controllers\AuthController;
use App\Controllers\CvController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\ProfileController;
use App\Controllers\TemplateController;
use App\Core\Router;

/*
 * Every POST route is CSRF-protected automatically (App\Core\App).
 * Ownership of CVs is enforced in the controllers through App\Models\Cv::findOwned().
 */
return static function (Router $r): void {
    // Public
    $r->get('/', [HomeController::class, 'index']);
    $r->get('/privacy', [HomeController::class, 'privacy']);
    $r->get('/health', [HomeController::class, 'health']);
    $r->get('/templates', [TemplateController::class, 'gallery']);
    $r->get('/templates/{slug:[a-z0-9-]+}/preview', [TemplateController::class, 'preview']);

    // Visitors only
    $r->group(['guest'], function (Router $r): void {
        $r->get('/login', [AuthController::class, 'showLogin']);
        $r->post('/login', [AuthController::class, 'login']);
        $r->get('/register', [AuthController::class, 'showRegister']);
        $r->post('/register', [AuthController::class, 'register']);
        $r->get('/forgot-password', [AuthController::class, 'showForgot']);
        $r->post('/forgot-password', [AuthController::class, 'forgot']);
        $r->get('/reset-password/{token:[a-f0-9]+}', [AuthController::class, 'showReset']);
        $r->post('/reset-password', [AuthController::class, 'reset']);
    });

    // Signed in (password change may be pending)
    $r->group(['auth'], function (Router $r): void {
        $r->post('/logout', [AuthController::class, 'logout']);
        $r->get('/account/change-password', [AccountController::class, 'showForcedChange']);
        $r->post('/account/change-password', [AccountController::class, 'forcedChange']);
    });

    // Signed in, password up to date
    $r->group(['auth', 'password'], function (Router $r): void {
        $r->get('/dashboard', [DashboardController::class, 'index']);
        $r->get('/cvs/new', [CvController::class, 'create']);
        $r->post('/cvs', [CvController::class, 'store']);
        $r->get('/cvs/{id}/edit', [CvController::class, 'edit']);
        $r->get('/cvs/{id}/download', [CvController::class, 'download']);
        $r->get('/cvs/{id}/render', [CvController::class, 'render']);
        $r->get('/cvs/{id}/photo', [CvController::class, 'photo']);

        $r->get('/account', [AccountController::class, 'show']);
        $r->post('/account/profile', [AccountController::class, 'updateProfile']);
        $r->post('/account/password', [AccountController::class, 'updatePassword']);
        $r->post('/account/delete', [AccountController::class, 'destroy']);

        // JSON API for the editor and the dashboard
        $r->get('/api/cvs/{id}', [CvApiController::class, 'show']);
        $r->post('/api/cvs/{id}/save', [CvApiController::class, 'save']);
        $r->post('/api/cvs/{id}/preview', [CvApiController::class, 'preview']);
        $r->post('/api/cvs/{id}/pages', [CvApiController::class, 'pages']);
        $r->post('/api/cvs/{id}/rename', [CvApiController::class, 'rename']);
        $r->post('/api/cvs/{id}/from-profile', [CvApiController::class, 'fromProfile']);
        $r->get('/profile', [ProfileController::class, 'show']);
        $r->get('/profile/import', [ProfileController::class, 'import']);
        $r->get('/profile/imports/{id}', [ProfileController::class, 'review']);
        $r->get('/api/profile', [ProfileApiController::class, 'show']);
        $r->post('/api/profile/save', [ProfileApiController::class, 'save']);
        $r->post('/api/profile/import', [ProfileApiController::class, 'import']);
        $r->get('/api/profile/imports/{id}', [ProfileApiController::class, 'showImport']);
        $r->post('/api/profile/imports/{id}/continue', [ProfileApiController::class, 'continue']);
        $r->post('/api/profile/imports/{id}/save', [ProfileApiController::class, 'saveImport']);
        $r->post('/api/profile/imports/{id}/apply', [ProfileApiController::class, 'apply']);
        $r->post('/api/profile/imports/{id}/delete', [ProfileApiController::class, 'deleteImport']);
        $r->post('/api/cvs/{id}/duplicate', [CvApiController::class, 'duplicate']);
        $r->post('/api/cvs/{id}/delete', [CvApiController::class, 'delete']);
        $r->post('/api/cvs/{id}/photo', [CvApiController::class, 'uploadPhoto']);
        $r->post('/api/cvs/{id}/photo/delete', [CvApiController::class, 'deletePhoto']);
        $r->get('/api/session', [CvApiController::class, 'session']);
    });

    // Administration
    $r->group(['auth', 'password', 'admin'], function (Router $r): void {
        $r->get('/admin', [AdminController::class, 'dashboard']);
        $r->get('/admin/users', [AdminUserController::class, 'index']);
        $r->get('/admin/users/new', [AdminUserController::class, 'create']);
        $r->post('/admin/users', [AdminUserController::class, 'store']);
        $r->get('/admin/users/{id}', [AdminUserController::class, 'show']);
        $r->post('/admin/users/{id}', [AdminUserController::class, 'update']);
        $r->post('/admin/users/{id}/password', [AdminUserController::class, 'resetPassword']);
        $r->post('/admin/users/{id}/delete', [AdminUserController::class, 'destroy']);
        $r->post('/admin/users/{id}/status', [AdminUserController::class, 'status']);
        $r->post('/admin/users/{id}/role', [AdminUserController::class, 'role']);
        $r->get('/admin/templates', [AdminTemplateController::class, 'index']);
        $r->post('/admin/templates/{id}', [AdminTemplateController::class, 'update']);
        $r->post('/admin/templates/{id}/toggle', [AdminTemplateController::class, 'toggle']);
        $r->get('/admin/countries', [AdminController::class, 'countries']);
        $r->post('/admin/countries', [AdminController::class, 'saveCountries']);
        $r->get('/admin/settings', [AdminController::class, 'settings']);
        $r->post('/admin/settings', [AdminController::class, 'saveSettings']);
        $r->post('/admin/settings/ai', [AdminController::class, 'saveAi']);
        $r->post('/admin/settings/ai/test', [AdminController::class, 'testAi']);
        $r->get('/admin/audit', [AdminController::class, 'audit']);
    });
};
