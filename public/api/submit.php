<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Captcha;
use App\FeedbackMailer;
use App\FeedbackValidator;
use App\RateLimiter;
use App\Session;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

/** @param array<string, string> $errors */
function respond(int $status, bool $success, string $message, array $errors = []): never
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'errors' => (object) $errors,
        'captcha_refresh' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Метод запроса не поддерживается.');
}

try {
    Session::start();

    if (!Session::verifyCsrf($_POST['csrf_token'] ?? null)) {
        respond(403, false, 'Сессия устарела. Обновите страницу и попробуйте снова.');
    }

    if (($_POST['action'] ?? null) !== 'message') {
        respond(400, false, 'Некорректное действие формы.');
    }

    // Hidden honeypot: bots filling all inputs get no SMTP delivery.
    if (!empty($_POST['website'])) {
        respond(200, true, 'Сообщение отправлено.');
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    // Never trust arbitrary X-Forwarded-For headers supplied by the client.
    $rateLimiter = new RateLimiter(dirname(__DIR__, 2) . '/storage/rate-limits.sqlite');
    if (!$rateLimiter->allow('post:' . $ip, 15, 900)) {
        header('Retry-After: 900');
        respond(429, false, 'Слишком много попыток. Попробуйте через 15 минут.');
    }

    $validation = (new FeedbackValidator())->validate($_POST);
    $errors = $validation['errors'];
    $answer = $validation['values']['captcha'] ?? '';

    // Always consume the challenge to prevent reusing a CAPTCHA response.
    if (!(new Captcha())->verifyAndConsume($answer)) {
        $errors['captcha'] = 'Неверный или устаревший код. Введите новый код с изображения.';
    }

    if ($errors !== []) {
        respond(422, false, 'Проверьте выделенные поля и отправьте форму ещё раз.', $errors);
    }

    if (!$rateLimiter->allow('mail:' . $ip, 3, 3600)) {
        header('Retry-After: 3600');
        respond(429, false, 'Лимит отправленных обращений исчерпан. Попробуйте позже.');
    }

    // Unlock the session while SMTP delivery takes place.
    session_write_close();
    (new FeedbackMailer())->send($validation['values']);
    respond(200, true, 'Готово! Копия обращения отправлена на указанную почту.');
} catch (Throwable $exception) {
    // Never expose SMTP credentials, stack traces, or connection details to users.
    error_log('Feedback submission error: ' . $exception->getMessage());
    respond(503, false, 'Сервис временно недоступен. Попробуйте позже.');
}
