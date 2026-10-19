<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\Cv;
use App\Models\User;
use App\Services\Auth;
use App\Services\PhotoService;

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

    public function create(Request $request): Response
    {
        return $this->view('admin/user-new', ['title' => 'New user'], 'layouts/admin');
    }

    /** Creates an account with a temporary password, shown once to the administrator. */
    public function store(Request $request): Response
    {
        $input = ['name' => $request->string('name'), 'email' => $request->string('email'), 'role' => $request->string('role')];
        $v = $this->validateProfile($request, null);
        if ($v->fails()) {
            return $this->backWithErrors('/admin/users/new', $v->errors(), $input);
        }
        $role = $input['role'] === 'admin' ? 'admin' : 'user';
        [$id, $password] = User::createWithTemporaryPassword($input['name'], $input['email'], $role);
        AuditLog::record(Auth::id(), 'admin.user_created', 'user', $id, ['email' => User::normalizeEmail($input['email']), 'role' => $role], $request->ip());
        $this->revealOnce($id, $password, 'created');
        return $this->redirect('/admin/users/' . $id);
    }

    /** One user: profile, activity, CV count and the management actions. */
    public function show(Request $request): Response
    {
        $user = $this->target($request);
        $reveal = Session::get('admin_temp_password');
        Session::forget('admin_temp_password');
        if (!is_array($reveal) || (int) ($reveal['id'] ?? 0) !== (int) $user['id']) {
            $reveal = null;
        }
        return $this->view('admin/user-edit', [
            'title' => $user['name'],
            'u' => $user,
            'cvCount' => Cv::countForUser((int) $user['id']),
            'isMe' => (int) $user['id'] === Auth::id(),
            'reveal' => $reveal,
        ], 'layouts/admin');
    }

    public function update(Request $request): Response
    {
        $user = $this->target($request);
        $path = '/admin/users/' . $user['id'];
        $input = ['name' => $request->string('name'), 'email' => $request->string('email')];
        $v = $this->validateProfile($request, (int) $user['id']);
        if ($v->fails()) {
            return $this->backWithErrors($path, $v->errors(), $input);
        }
        User::updateByAdmin((int) $user['id'], $input['name'], $input['email']);
        $changes = [];
        if ($input['name'] !== $user['name']) {
            $changes['name'] = 'changed';
        }
        if (User::normalizeEmail($input['email']) !== $user['email']) {
            $changes['email'] = $user['email'] . ' → ' . User::normalizeEmail($input['email']);
        }
        AuditLog::record(Auth::id(), 'admin.user_updated', 'user', (int) $user['id'], $changes, $request->ip());
        Session::flash('success', 'Account updated.');
        return $this->redirect($path);
    }

    /** Replaces a user's password with a temporary one; the new hash signs them out everywhere. */
    public function resetPassword(Request $request): Response
    {
        $user = $this->target($request);
        if ((int) $user['id'] === Auth::id()) {
            Session::flash('error', 'Change your own password from your account page.');
            return $this->redirect('/admin/users/' . $user['id']);
        }
        $password = User::setTemporaryPassword((int) $user['id']);
        AuditLog::record(Auth::id(), 'admin.user_password_reset', 'user', (int) $user['id'], ['email' => $user['email']], $request->ip());
        $this->revealOnce((int) $user['id'], $password, 'reset');
        return $this->redirect('/admin/users/' . $user['id']);
    }

    /** Deletes an account with its CVs and photos. The e-mail address must be typed to confirm. */
    public function destroy(Request $request): Response
    {
        $user = $this->target($request);
        $path = '/admin/users/' . $user['id'];
        if ((int) $user['id'] === Auth::id()) {
            Session::flash('error', 'You cannot delete your own account from here.');
            return $this->redirect($path);
        }
        if ($user['role'] === 'admin' && $user['status'] === 'active' && User::countActiveAdmins() <= 1) {
            Session::flash('error', 'At least one active administrator must remain.');
            return $this->redirect($path);
        }
        if (User::normalizeEmail($request->string('confirm_email')) !== $user['email']) {
            return $this->backWithErrors($path, ['confirm_email' => 'Type the e-mail address exactly to confirm.']);
        }
        $cvs = Cv::countForUser((int) $user['id']);
        $photos = Cv::photosOfUser((int) $user['id']);
        Database::transaction(function () use ($user, $request, $cvs): void {
            AuditLog::record(Auth::id(), 'admin.user_deleted', 'user', (int) $user['id'], ['email' => $user['email'], 'cvs' => $cvs], $request->ip());
            User::delete((int) $user['id']);
        });
        foreach ($photos as $photo) {
            if (!Cv::photoInUse($photo)) {
                PhotoService::delete($photo);
            }
        }
        Session::flash('success', "{$user['email']} and their {$cvs} CV(s) have been deleted.");
        return $this->redirect('/admin/users');
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

    private function validateProfile(Request $request, ?int $id): Validator
    {
        $v = new Validator($request->post);
        $v->required('name', 'The name')->max('name', 120, 'The name')
            ->required('email', 'The e-mail address')->email('email')->max('email', 190, 'The e-mail address');
        $email = $request->string('email');
        if (!$v->fails() && ($id === null ? User::emailExists($email) : User::emailTakenByOther($email, $id))) {
            $v->add('email', 'Another account already uses this e-mail address.');
        }
        return $v;
    }

    /** The temporary password is kept in the session for the next page only; never logged or stored. */
    private function revealOnce(int $id, string $password, string $reason): void
    {
        Session::put('admin_temp_password', ['id' => $id, 'password' => $password, 'reason' => $reason]);
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
        if ($request->string('return') === 'user') {
            return $this->redirect('/admin/users/' . (int) $request->param('id'));
        }
        $query = array_filter(['q' => $request->string('q'), 'status' => $request->string('filter_status'), 'page' => $request->string('page')]);
        return $this->redirect('/admin/users' . ($query ? '?' . http_build_query($query) : ''));
    }
}
