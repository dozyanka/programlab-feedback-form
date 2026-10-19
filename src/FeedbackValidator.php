<?php

declare(strict_types=1);

namespace App;

final class FeedbackValidator
{
    public const TOPICS = [
        'general' => 'Общий вопрос',
        'partnership' => 'Сотрудничество',
        'support' => 'Техническая поддержка',
        'other' => 'Другое',
    ];

    /**
     * @param array<string, mixed> $data
     * @return array{errors: array<string, string>, values: array<string, string>}
     */
    public function validate(array $data): array
    {
        $errors = [];
        $values = [];

        $topic = $this->string($data, 'topic');
        if ($topic === '') {
            $errors['topic'] = 'Выберите тему обращения.';
        } elseif (!array_key_exists($topic, self::TOPICS)) {
            $errors['topic'] = 'Выбрана неизвестная тема обращения.';
        } else {
            $values['topic'] = self::TOPICS[$topic];
        }

        $name = $this->string($data, 'full_name');
        if ($name === '') {
            $errors['full_name'] = 'Укажите ваше ФИО.';
        } elseif (!$this->isValidLength($name, 255)) {
            $errors['full_name'] = 'ФИО должно содержать не более 255 символов.';
        } else {
            $values['full_name'] = $name;
        }

        $phone = $this->string($data, 'phone');
        if ($phone === '') {
            $errors['phone'] = 'Укажите номер телефона.';
        } elseif (!$this->isValidLength($phone, 255)) {
            $errors['phone'] = 'Номер телефона слишком длинный.';
        } else {
            // Both +7 (999) 123-45-67 and 89991234567 are accepted.
            $compacted = preg_replace('/[\s()\-]+/u', '', $phone);
            if (!is_string($compacted) || !preg_match('/^(?:\+7|8)\d{10}$/D', $compacted)) {
                $errors['phone'] = 'Введите российский номер в формате +7 (999) 123-45-67.';
            } else {
                $digits = preg_replace('/\D/', '', $compacted);
                $values['phone'] = '+7' . substr((string) $digits, 1);
            }
        }

        $email = $this->string($data, 'email');
        if ($email === '') {
            $errors['email'] = 'Укажите электронную почту.';
        } elseif (!$this->isValidLength($email, 255)) {
            $errors['email'] = 'Адрес почты должен содержать не более 255 символов.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $email)) {
            $errors['email'] = 'Укажите корректный адрес электронной почты.';
        } else {
            $values['email'] = $email;
        }

        $message = str_replace(["\r\n", "\r"], "\n", $this->string($data, 'message'));
        if ($message === '') {
            $errors['message'] = 'Напишите сообщение.';
        } elseif (!$this->isValidLength($message, 4096)) {
            $errors['message'] = 'Сообщение должно содержать не более 4096 символов.';
        } else {
            $values['message'] = $message;
        }

        if (($data['consent'] ?? null) !== '1') {
            $errors['consent'] = 'Необходимо согласие на обработку персональных данных.';
        }

        $captcha = $this->string($data, 'captcha');
        if ($captcha === '') {
            $errors['captcha'] = 'Введите код с изображения.';
        } elseif (!preg_match('/^[A-Za-z2-9]{6}$/D', $captcha)) {
            $errors['captcha'] = 'Код должен состоять из шести символов.';
        } else {
            $values['captcha'] = strtoupper($captcha);
        }

        return ['errors' => $errors, 'values' => $values];
    }

    /** @param array<string, mixed> $data */
    private function string(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
    }

    private function isValidLength(string $value, int $limit): bool
    {
        // preg_match_all counts Unicode code points rather than UTF-8 bytes;
        // return false for malformed UTF-8. No mbstring dependency is needed.
        $count = preg_match_all('/./us', $value);
        return $count !== false && $count <= $limit;
    }
}
