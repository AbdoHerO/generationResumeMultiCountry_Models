<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\Cv;
use App\Models\User;
use App\Services\Auth;
use App\Services\PhotoService;

final class AccountController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->view('account/show', ['title' => 'Account', 'cvCount' => Cv::countForUser(Auth::id())]);
    }

    public function updateProfile(Request $request): Response
    {
        $v = new Validator($request->post);
        $v->required('name', 'Your name')->max('name', 120, 'Your name');
        if ($v->fails()) {
            return $this->backWithErrors('/account', $v->errors(), ['name' => $request->string('name')]);
        }
        User::updateProfile(Auth::id(), $request->string('name'));
        Session::flash('success', 'Profile updated.');
        return $this->redirect('/account');
    }

    public function updatePassword(Request $request): Response
    {
        $result = $this->changePassword($request);
        if ($result !== []) {
            return $this->backWithErrors('/account', $result);
        }
        Session::flash('success', 'Password changed. Other sessions have been signed out.');
        return $this->redirect('/account');
    }

    public function showForcedChange(Request $request): Response
    {
        $user = $this->user();
        if ((int) $user['must_change_password'] !== 1) {
            return $this->redirect('/dashboard');
        }
        return $this->view('auth/change-password', ['title' => 'Choose a new password'], 'layouts/auth');
    }

    public function forcedChange(Request $request): Response
    {
        $result = $this->changePassword($request);
        if ($result !== []) {
            return $this->backWithErrors('/account/change-password', $result);
        }
        Session::flash('success', 'Your new password is saved. Welcome!');
        return $this->redirect($this->user()['role'] === 'admin' ? '/admin' : '/dashboard');
    }

    /** @return array<string, string> errors, empty on success */
    private function changePassword(Request $request): array
    {
        $user = $this->user();
        $hash = (string) User::passwordHash((int) $user['id']);
        $current = (string) $request->input('current_password', '');
        $new = (string) $request->input('password', '');
        $v = new Validator($request->post);
        if (!password_verify($current, $hash)) {
            $v->add('current_password', 'Your current password is incorrect.');
        }
        $v->password('password')->confirmed('password', 'password_confirmation');
        if (!$v->fails() && password_verify($new, $hash)) {
            $v->add('password', 'Choose a password different from the current one.');
        }
        if (!$v->fails() && strtolower($new) === strtolower((string) $user['email'])) {
            $v->add('password', 'Your password must not be your e-mail address.');
        }
        if ($v->fails()) {
            return $v->errors();
        }
        User::setPassword((int) $user['id'], $new);
        // Re-sign this session against the new hash; other sessions become invalid.
        Auth::login((int) $user['id']);
        AuditLog::record((int) $user['id'], 'auth.password_changed', 'user', (int) $user['id'], [], $request->ip());
        return [];
    }

    /** Deletes the account, every CV and every photo. */
    public function destroy(Request $request): Response
    {
        $user = $this->user();
        if (!password_verify((string) $request->input('password', ''), (string) User::passwordHash((int) $user['id']))) {
            return $this->backWithErrors('/account', ['delete_password' => 'Your password is incorrect.']);
        }
        if ($user['role'] === 'admin' && User::countActiveAdmins() <= 1) {
            Session::flash('error', 'You are the only administrator. Promote another administrator before deleting this account.');
            return $this->redirect('/account');
        }
        $photos = Cv::photosOfUser((int) $user['id']);
        Database::transaction(function () use ($user, $request): void {
            AuditLog::record(null, 'account.deleted', 'user', (int) $user['id'], [], $request->ip());
            User::delete((int) $user['id']);
        });
        foreach ($photos as $photo) {
            PhotoService::delete($photo);
        }
        Auth::logout();
        Session::start($request);
        Session::flash('success', 'Your account and all your CVs have been deleted.');
        return $this->redirect('/');
    }
}
