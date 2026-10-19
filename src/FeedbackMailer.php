<?php

declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\PHPMailer;

final class FeedbackMailer
{
    /** @param array<string, string> $values validated data only */
    public function send(array $values): void
    {
        if (!class_exists(PHPMailer::class)) {
            throw new \RuntimeException('Install Composer dependencies before sending mail.');
        }

        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = Config::get('SMTP_HOST', 'mailpit');
        $mailer->Port = (int) Config::get('SMTP_PORT', '1025');
        $mailer->Timeout = 10;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;

        $username = Config::get('SMTP_USERNAME');
        if ($username !== '') {
            $mailer->SMTPAuth = true;
            $mailer->Username = $username;
            $mailer->Password = Config::get('SMTP_PASSWORD');
        }

        $encryption = Config::get('SMTP_ENCRYPTION');
        if ($encryption === 'tls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption !== '') {
            throw new \RuntimeException('Unsupported SMTP_ENCRYPTION setting.');
        } else {
            // Do not automatically negotiate STARTTLS for the plaintext local Mailpit instance.
            $mailer->SMTPAutoTLS = false;
        }

        $mailer->setFrom(Config::get('MAIL_FROM_ADDRESS', 'noreply@example.test'),
            Config::get('MAIL_FROM_NAME', 'Форма обратной связи'));
        // The spec explicitly requires sending notification TO the submitted email.
        $mailer->addAddress($values['email'], $values['full_name']);
        $mailer->isHTML(false);
        $mailer->Subject = 'Копия обращения: ' . $values['topic'];
        $mailer->Body = implode("\n", [
            'Здравствуйте!',
            '',
            'Ниже приведена копия вашего обращения.',
            '',
            'Тема: ' . $values['topic'],
            'ФИО: ' . $values['full_name'],
            'Телефон: ' . $values['phone'],
            'E-mail: ' . $values['email'],
            '',
            'Сообщение:',
            $values['message'],
            '',
            'Это автоматическое письмо. Не отвечайте на него.',
        ]);
        $mailer->send();
    }
}
