<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Captcha;
use App\FeedbackValidator;
use App\RateLimiter;
use App\Session;

// Initialize cookies/session before the first test writes any output.
Session::start();

$passed = 0;
$failed = 0;
$skipped = 0;

function test(string $description, callable $callback): void
{
    global $passed, $failed;
    try {
        $callback();
        $passed++;
        echo "[PASS] {$description}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "[FAIL] {$description}: {$exception->getMessage()}\n";
    }
}

function expect(bool $condition, string $description): void
{
    if (!$condition) {
        throw new RuntimeException($description);
    }
}

function data(array $overrides = []): array
{
    return array_replace([
        'topic' => 'general',
        'full_name' => 'Иванов Иван Иванович',
        'phone' => '+7 (999) 123-45-67',
        'email' => 'hello@example.com',
        'message' => 'Здравствуйте! Это тестовое обращение.',
        'captcha' => 'A2B3C4',
        'consent' => '1',
    ], $overrides);
}

$validator = new FeedbackValidator();

test('Valid feedback is accepted and normalized', static function () use ($validator): void {
    $result = $validator->validate(data());
    expect($result['errors'] === [], 'Valid data rejected: ' . json_encode($result['errors'], JSON_UNESCAPED_UNICODE));
    expect($result['values']['phone'] === '+79991234567', 'Phone not normalized');
    expect($result['values']['topic'] === 'Общий вопрос', 'Topic is not mapped');
});

test('Alternative phone number starting with 8 is accepted', static function () use ($validator): void {
    $result = $validator->validate(data(['phone' => '89991234567']));
    expect($result['errors'] === [], 'Phone starting with 8 rejected');
    expect($result['values']['phone'] === '+79991234567', 'Phone normalization wrong');
});

test('All empty mandatory fields produce errors', static function () use ($validator): void {
    $result = $validator->validate(data(array_fill_keys(
        ['topic', 'full_name', 'phone', 'email', 'message', 'captcha', 'consent'], ''
    )));
    foreach (['topic', 'full_name', 'phone', 'email', 'message', 'captcha', 'consent'] as $key) {
        expect(isset($result['errors'][$key]), "Missing error: {$key}");
    }
});

test('Unknown select value is rejected', static function () use ($validator): void {
    $result = $validator->validate(data(['topic' => 'admin']));
    expect(isset($result['errors']['topic']), 'Arbitrary option accepted');
});

test('Malformed phones and emails are rejected', static function () use ($validator): void {
    foreach (['+7 (999) 111-22', '123', '+7999abcdefg', '+1 (999) 111-22-33'] as $phone) {
        expect(isset($validator->validate(data(['phone' => $phone]))['errors']['phone']), "Phone accepted: {$phone}");
    }
    foreach (['not-an-email', 'a@', "a@example.com\nBcc:test@evil.test"] as $email) {
        expect(isset($validator->validate(data(['email' => $email]))['errors']['email']), "Email accepted: {$email}");
    }
});

test('Text limits use Unicode characters, not bytes', static function () use ($validator): void {
    expect($validator->validate(data(['full_name' => str_repeat('Ж', 255)]))['errors'] === [], '255 Unicode symbols rejected');
    expect(isset($validator->validate(data(['full_name' => str_repeat('Ж', 256)]))['errors']['full_name']), '256 Unicode symbols accepted');
    expect($validator->validate(data(['message' => str_repeat('я', 4096)]))['errors'] === [], '4096 Unicode symbols rejected');
    expect(isset($validator->validate(data(['message' => str_repeat('я', 4097)]))['errors']['message']), '4097 Unicode symbols accepted');
});

test('Invalid UTF-8 and array-instead-of-string values are rejected', static function () use ($validator): void {
    expect(isset($validator->validate(data(['full_name' => "\xC3\x28"]))['errors']['full_name']), 'Invalid UTF-8 accepted');
    expect(isset($validator->validate(data(['email' => ['test@example.org']]))['errors']['email']), 'Non-string email accepted');
    expect(isset($validator->validate(data(['captcha' => []]))['errors']['captcha']), 'Non-string captcha accepted');
});

test('CAPTCHA syntax and missing consent are rejected', static function () use ($validator): void {
    expect(isset($validator->validate(data(['captcha' => '123']))['errors']['captcha']), 'Short captcha accepted');
    expect(isset($validator->validate(data(['consent' => 'on']))['errors']['consent']), 'Unexpected consent accepted');
});

test('CSRF token is stable within session and wrong tokens are rejected', static function (): void {
    expect(Session::csrfToken() === Session::csrfToken(), 'Token changed in session');
    expect(Session::verifyCsrf(Session::csrfToken()), 'Valid CSRF rejected');
    expect(!Session::verifyCsrf('invalid'), 'Invalid CSRF accepted');
    expect(!Session::verifyCsrf([]), 'Invalid token type accepted');
});

test('CAPTCHA answer is case-insensitive and one-time', static function (): void {
    Session::start();
    $_SESSION['captcha'] = ['hash' => hash('sha256', 'A2B3C4'), 'created_at' => time()];
    $captcha = new Captcha();
    expect($captcha->verifyAndConsume('a2b3c4'), 'Correct captcha rejected');
    expect(!$captcha->verifyAndConsume('A2B3C4'), 'CAPTCHA response was reused');
});

test('CAPTCHA expires and a wrong response consumes it', static function (): void {
    $_SESSION['captcha'] = ['hash' => hash('sha256', 'A2B3C4'), 'created_at' => time() - 301];
    $captcha = new Captcha();
    expect(!$captcha->verifyAndConsume('A2B3C4'), 'Expired captcha accepted');
    $_SESSION['captcha'] = ['hash' => hash('sha256', 'A2B3C4'), 'created_at' => time()];
    expect(!$captcha->verifyAndConsume('AAAAAA'), 'Incorrect answer accepted');
    expect(!$captcha->verifyAndConsume('A2B3C4'), 'Consumed challenge reused');
});

if (extension_loaded('pdo_sqlite')) {
    test('Rate limiter accepts up to limit and resets for other identities', static function (): void {
        $db = tempnam(sys_get_temp_dir(), 'rate-limit-test-');
        if ($db === false) throw new RuntimeException('Cannot create temp database');
        try {
            $limiter = new RateLimiter($db);
            expect($limiter->allow('client A', 2, 60), 'First request blocked');
            expect($limiter->allow('client A', 2, 60), 'Second request blocked');
            expect(!$limiter->allow('client A', 2, 60), 'Third request not limited');
            expect($limiter->allow('client B', 2, 60), 'Different client blocked');
        } finally {
            @unlink($db);
            @unlink($db . '-wal');
            @unlink($db . '-shm');
        }
    });
} else {
    $skipped++;
    echo "[SKIP] SQLite rate limiter: install pdo_sqlite (included in Docker image)\n";
}

if (!extension_loaded('gd')) {
    $skipped++;
    echo "[SKIP] CAPTCHA PNG rendering: install gd (included in Docker image)\n";
} else {
    test('GD extension needed for CAPTCHA generation is loaded', static function (): void {
        expect(function_exists('imagecreatetruecolor'), 'GD image functions unavailable');
    });
}

echo "\nResult: {$passed} passed, {$failed} failed, {$skipped} skipped.\n";
exit($failed > 0 ? 1 : 0);
