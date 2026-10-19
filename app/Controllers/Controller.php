<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Cv;
use App\Services\Auth;

abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $view, array $data = [], string $layout = 'layouts/app', int $status = 200): Response
    {
        return Response::html(View::render($view, $data, $layout), $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(url($path));
    }

    /** @param array<string, string> $errors @param array<string, mixed> $input */
    protected function backWithErrors(string $path, array $errors, array $input = [], string $message = ''): Response
    {
        Session::flashErrors($errors);
        Session::flashInput($input);
        if ($message !== '') {
            Session::flash('error', $message);
        }
        return $this->redirect($path);
    }

    /** A CV of the signed-in user, or 404 — never another account's CV. @return array<string, mixed> */
    protected function ownedCv(Request $request): array
    {
        $cv = Cv::findOwned((int) $request->param('id'), Auth::id());
        if (!$cv) {
            throw new HttpException(404, 'This CV does not exist or does not belong to your account.', [], 'not_found');
        }
        return $cv;
    }

    /** @return array<string, mixed> */
    protected function user(): array
    {
        return (array) Auth::user();
    }
}
