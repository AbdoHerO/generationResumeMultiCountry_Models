<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Storage;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Outgoing e-mail for password resets.
 *   MAIL_MAILER=smtp      send through the configured SMTP server (PHPMailer)
 *   MAIL_MAILER=log       write the message to storage/logs/mail.log (development only)
 *   MAIL_MAILER=disabled  password reset by e-mail is not offered
 */
final class Mailer
{
    public static function enabled(): bool
    {
        $mailer = (string) config('app.mail.mailer', 'disabled');
        return $mailer === 'log' || ($mailer === 'smtp' && (string) config('app.mail.host') !== '');
    }

    public static function send(string $to, string $subject, string $text): bool
    {
        $mailer = (string) config('app.mail.mailer', 'disabled');
        if ($mailer === 'log') {
            $entry = sprintf("[%s] To: %s\nSubject: %s\n\n%s\n\n", gmdate('c'), $to, $subject, $text);
            return (bool) @file_put_contents(Storage::path('logs') . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
        }
        if ($mailer !== 'smtp') {
            return false;
        }
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string) config('app.mail.host');
            $mail->Port = (int) config('app.mail.port', 587);
            $user = (string) config('app.mail.username', '');
            if ($user !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $user;
                $mail->Password = (string) config('app.mail.password', '');
            }
            $enc = (string) config('app.mail.encryption', 'tls');
            $mail->SMTPSecure = $enc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : ($enc === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '');
            $mail->CharSet = 'UTF-8';
            $mail->setFrom((string) config('app.mail.from_address'), (string) config('app.mail.from_name'));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $text;
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            Logger::error('Mail sending failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
