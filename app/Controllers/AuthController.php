<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Models\Setting;
use App\Models\User;
use App\Services\Auth;
use App\Services\Mailer;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login', ['title' => 'Sign in', 'mailEnabled' => Mailer::enabled()], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        $email = $request->string('email');
        $password = (string) $request->input('password', '');
        if ($email === '' || $password === '') {
            return $this->backWithErrors('/login', ['email' => 'Enter your e-mail address and password.'], ['email' => $email]);
        }
        $result = Auth::attempt($email, $password, $request);
        if (!$result['ok']) {
            return $this->backWithErrors('/login', ['email' => (string) $result['error']], ['email' => $email]);
        }
        $user = $result['user'];
        if ((int) $user['must_change_password'] === 1) {
            Session::flash('info', 'For your security, please choose a new password before continuing.');
            return $this->redirect('/account/change-password');
        }
        $intended = (string) Session::get('intended', '');
        Session::forget('intended');
        if ($intended !== '' && str_starts_with($intended, '/') && !str_starts_with($intended, '//') && !str_starts_with($intended, '/api/')) {
            return Response::redirect(url($intended));
        }
        return $this->redirect($user['role'] === 'admin' ? '/admin' : '/dashboard');
    }

    public function showRegister(Request $request): Response
    {
        return $this->view('auth/register', [
            'title' => 'Create your account',
            'open' => (bool) Setting::get('registration_open', true),
        ], 'layouts/auth');
    }

    public function register(Request $request): Response
    {
        if (!Setting::get('registration_open', true)) {
            Session::flash('error', 'Registration is currently closed.');
            return $this->redirect('/register');
        }
        $input = ['name' => $request->string('name'), 'email' => $request->string('email')];
        $ip = $request->ip();
        if (LoginAttempt::tooMany($ip, 'register', (int) config('app.limits.register_per_hour', 10), 60)) {
            return $this->backWithErrors('/register', ['email' => 'Too many accounts were created from this connection. Please try again later.'], $input);
        }
        $v = new Validator($request->post);
        $v->required('name', 'Your name')->max('name', 120, 'Your name')
            ->required('email', 'E-mail address')->email('email')
            ->password('password')->confirmed('password', 'password_confirmation');
        if (!$request->input('terms')) {
            $v->add('terms', 'Please accept the privacy terms to continue.');
        }
        if (!$v->fails() && User::emailExists($input['email'])) {
            $v->add('email', 'An account with this e-mail address already exists. Try signing in.');
        }
        if ($v->fails()) {
            return $this->backWithErrors('/register', $v->errors(), $input);
        }
        try {
            $id = User::create($input['name'], $input['email'], (string) $request->input('password'));
        } catch (\PDOException $e) {
            // Unique index: a concurrent registration with the same address.
            return $this->backWithErrors('/register', ['email' => 'An account with this e-mail address already exists. Try signing in.'], $input);
        }
        LoginAttempt::record($input['email'], $ip, true, 'register');
        Auth::login($id);
        User::touchLogin($id);
        Session::flash('success', 'Welcome! Your account is ready. Let’s create your first CV.');
        return $this->redirect('/dashboard');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        Session::start($request);
        return $this->redirect('/');
    }

    public function showForgot(Request $request): Response
    {
        return $this->view('auth/forgot', ['title' => 'Reset your password', 'mailEnabled' => Mailer::enabled()], 'layouts/auth');
    }

    public function forgot(Request $request): Response
    {
        $email = $request->string('email');
        $ip = $request->ip();
        if (!Mailer::enabled()) {
            Session::flash('error', 'Password reset by e-mail is not available on this installation. Please contact the administrator.');
            return $this->redirect('/forgot-password');
        }
        if (LoginAttempt::tooMany($ip, 'reset', 5, 60)) {
            Session::flash('error', 'Too many reset requests. Please try again in an hour.');
            return $this->redirect('/forgot-password');
        }
        LoginAttempt::record($email, $ip, true, 'reset');
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? User::findForAuth($email) : null;
        if ($user && $user['status'] === 'active') {
            $token = PasswordReset::create((int) $user['id']);
            $link = $this->absoluteUrl($request, '/reset-password/' . $token);
            Mailer::send(
                (string) $user['email'],
                'Reset your ' . app_name() . ' password',
                "Hello {$user['name']},\n\nUse this link within 60 minutes to choose a new password:\n$link\n\n"
                . "If you did not ask for this, you can ignore this e-mail; your password stays the same.\n"
            );
            AuditLog::record((int) $user['id'], 'auth.reset_requested', 'user', (int) $user['id'], [], $ip);
        }
        // Same answer whether or not the account exists.
        Session::flash('success', 'If an account exists for this address, we have sent a link to reset the password.');
        return $this->redirect('/login');
    }

    public function showReset(Request $request): Response
    {
        $token = $request->param('token');
        if (!PasswordReset::findValid($token)) {
            Session::flash('error', 'This reset link is invalid or has expired. Please ask for a new one.');
            return $this->redirect('/forgot-password');
        }
        return $this->view('auth/reset', ['title' => 'Choose a new password', 'token' => $token], 'layouts/auth');
    }

    public function reset(Request $request): Response
    {
        $token = $request->string('token');
        $reset = PasswordReset::findValid($token);
        if (!$reset) {
            Session::flash('error', 'This reset link is invalid or has expired. Please ask for a new one.');
            return $this->redirect('/forgot-password');
        }
        $v = new Validator($request->post);
        $v->password('password')->confirmed('password', 'password_confirmation');
        if ($v->fails()) {
            return $this->backWithErrors('/reset-password/' . $token, $v->errors());
        }
        User::setPassword((int) $reset['user_id'], (string) $request->input('password'));
        PasswordReset::consume((int) $reset['id']);
        AuditLog::record((int) $reset['user_id'], 'auth.password_reset', 'user', (int) $reset['user_id'], [], $request->ip());
        Session::flash('success', 'Your password has been changed. You can sign in now.');
        return $this->redirect('/login');
    }

    private function absoluteUrl(Request $request, string $path): string
    {
        // The host comes from APP_URL, never from the request's Host header (which a client controls),
        // so a reset link can only point at this site. The scheme follows the request when it is HTTPS.
        $configured = (string) config('app.url', '');
        $host = (string) (parse_url($configured, PHP_URL_HOST) ?: ($request->server['SERVER_NAME'] ?? 'localhost'));
        $port = parse_url($configured, PHP_URL_PORT);
        $scheme = $request->isSecure() ? 'https' : ((string) (parse_url($configured, PHP_URL_SCHEME) ?: 'http'));
        return $scheme . '://' . $host . ($port ? ':' . $port : '') . url($path);
    }
}
