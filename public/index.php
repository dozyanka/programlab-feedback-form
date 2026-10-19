<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\FeedbackValidator;
use App\Session;

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$csrfToken = Session::csrfToken();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f8fc">
    <meta name="description" content="Форма обратной связи.">
    <title>Форма обратной связи</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/styles.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<div class="page-shell">
    <header class="site-header" aria-label="Шапка сайта">
        <a class="brand" href="/" aria-label="На главную">
            <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
            <span class="brand-name">FORMLAB<span class="brand-dot">.</span></span>
        </a>
    </header>

    <main class="main-content">
        <section class="form-panel" aria-labelledby="form-heading">
            <div class="form-panel-heading">
                <h1 id="form-heading">Форма обратной связи</h1>
                <p class="form-caption">Все поля обязательны для заполнения.</p>
            </div>

            <div class="notice" id="form-notice" role="status" aria-live="polite" hidden></div>
            <form id="feedback-form" action="/api/submit.php" method="post" novalidate>
                <input type="hidden" name="action" value="message">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <div class="website-trap" aria-hidden="true">
                    <label for="website">Ваш сайт</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-field" data-field="topic">
                    <label for="topic">Тема обращения <span class="required-mark">*</span></label>
                    <div class="select-wrap">
                        <select id="topic" name="topic" required aria-describedby="topic-error">
                            <option value="" selected disabled>Выберите подходящую тему</option>
                            <?php foreach (FeedbackValidator::TOPICS as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="field-error" id="topic-error" hidden></p>
                </div>

                <div class="form-field" data-field="full_name">
                    <label for="full_name">Ваше ФИО <span class="required-mark">*</span></label>
                    <input id="full_name" name="full_name" type="text" placeholder="Иванов Иван Иванович" autocomplete="name" maxlength="255" required aria-describedby="full_name-error">
                    <p class="field-error" id="full_name-error" hidden></p>
                </div>

                <div class="two-columns">
                    <div class="form-field" data-field="phone">
                        <label for="phone">Номер телефона <span class="required-mark">*</span></label>
                        <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+7 (999) 123-45-67" maxlength="255" required aria-describedby="phone-error">
                        <p class="field-error" id="phone-error" hidden></p>
                    </div>
                    <div class="form-field" data-field="email">
                        <label for="email">Электронная почта <span class="required-mark">*</span></label>
                        <input id="email" name="email" type="email" inputmode="email" autocomplete="email" placeholder="hello@example.ru" maxlength="255" required aria-describedby="email-error">
                        <p class="field-error" id="email-error" hidden></p>
                    </div>
                </div>

                <div class="form-field" data-field="message">
                    <div class="field-heading"><label for="message">Ваше сообщение <span class="required-mark">*</span></label><span class="char-count" id="message-counter">0 / 4096</span></div>
                    <textarea id="message" name="message" rows="5" maxlength="4096" placeholder="Введите сообщение" required aria-describedby="message-error message-counter"></textarea>
                    <p class="field-error" id="message-error" hidden></p>
                </div>

                <div class="captcha-block form-field" data-field="captcha">
                    <div class="captcha-title-row"><label for="captcha">Код подтверждения <span class="required-mark">*</span></label></div>
                    <div class="captcha-controls">
                        <img id="captcha-image" class="captcha-image" src="/api/captcha.php" width="242" height="82" alt="Код подтверждения из шести букв и цифр">
                        <button id="captcha-refresh" class="refresh-button" type="button" aria-label="Получить новый код подтверждения" title="Обновить код">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7v5h-5"/><path d="M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 7l2 5M4 12l2 5a7 7 0 0 0 12.4-2"/></svg>
                        </button>
                        <input id="captcha" name="captcha" type="text" placeholder="Введите код" maxlength="6" autocomplete="off" autocapitalize="characters" spellcheck="false" required aria-describedby="captcha-error">
                    </div>
                    <p class="captcha-help">Введите символы с картинки. Регистр не имеет значения.</p>
                    <p class="field-error" id="captcha-error" hidden></p>
                </div>

                <div class="form-field consent-field" data-field="consent">
                    <label class="check-label" for="consent">
                        <input type="checkbox" id="consent" name="consent" value="1" required aria-describedby="consent-error">
                        <span class="custom-check" aria-hidden="true">✓</span>
                        <span>Я даю согласие на обработку персональных данных, указанных в форме, в целях обработки моего обращения <span class="required-mark">*</span></span>
                    </label>
                    <p class="field-error" id="consent-error" hidden></p>
                </div>

                <button type="submit" id="submit-button" class="submit-button">
                    <span class="submit-label">Отправить сообщение</span>
                </button>
            </form>
        </section>
    </main>
    <footer class="site-footer">© <?= date('Y') ?> FORMLAB / DEMO PROJECT</footer>
</div>
</body>
</html>
