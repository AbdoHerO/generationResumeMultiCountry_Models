<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Auth;

final class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $q = mb_substr($request->string('q'), 0, 120);
        $status = $request->string('status');
        return $this->view('admin/users', [
            'title' => 'Users',
            'result' => User::paginate($q, $status, $request->int('page', 1)),
            'q' => $q,
            'status' => $status,
        ], 'layouts/admin');
    }

    public function status(Request $request): Response
    {
        $user = $this->target($request);
        $status = $request->string('status') === 'inactive' ? 'inactive' : 'active';
        if ((int) $user['id'] === Auth::id()) {
            Session::flash('error', 'You cannot deactivate your own account.');
            return $this->back($request);
        }
        if ($status === 'inactive' && $user['role'] === 'admin' && User::countActiveAdmins() <= 1) {
            Session::flash('error', 'At least one active administrator must remain.');
            return $this->back($request);
        }
        User::setStatus((int) $user['id'], $status);
        AuditLog::record(Auth::id(), $status === 'inactive' ? 'admin.user_deactivated' : 'admin.user_activated', 'user', (int) $user['id'], ['email' => $user['email']], $request->ip());
        Session::flash('success', $status === 'inactive' ? "{$user['email']} has been deactivated and signed out." : "{$user['email']} has been reactivated.");
        return $this->back($request);
    }

    public function role(Request $request): Response
    {
        $user = $this->target($request);
        $role = $request->string('role') === 'admin' ? 'admin' : 'user';
        if ((int) $user['id'] === Auth::id()) {
            Session::flash('error', 'You cannot change your own role.');
            return $this->back($request);
        }
        if ($role === 'user' && $user['role'] === 'admin' && User::countActiveAdmins() <= 1) {
            Session::flash('error', 'At least one active administrator must remain.');
            return $this->back($request);
        }
        User::setRole((int) $user['id'], $role);
        AuditLog::record(Auth::id(), $role === 'admin' ? 'admin.user_promoted' : 'admin.user_demoted', 'user', (int) $user['id'], ['email' => $user['email']], $request->ip());
        Session::flash('success', "{$user['email']} is now " . ($role === 'admin' ? 'an administrator.' : 'a regular user.'));
        return $this->back($request);
    }

    /** @return array<string, mixed> */
    private function target(Request $request): array
    {
        $user = User::find((int) $request->param('id'));
        if (!$user) {
            throw new HttpException(404);
        }
        return $user;
    }

    private function back(Request $request): Response
    {
        $query = array_filter(['q' => $request->string('q'), 'status' => $request->string('filter_status'), 'page' => $request->string('page')]);
        return $this->redirect('/admin/users' . ($query ? '?' . http_build_query($query) : ''));
    }
}
