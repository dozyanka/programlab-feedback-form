<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

try {
    (new App\Captcha())->renderPng();
} catch (Throwable $exception) {
    error_log('CAPTCHA error: ' . $exception->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo 'CAPTCHA недоступна. Попробуйте позже.';
}
