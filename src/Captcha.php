<?php

declare(strict_types=1);

namespace App;

final class Captcha
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const LIFETIME_SECONDS = 300;

    public function verifyAndConsume(string $answer): bool
    {
        Session::start();
        $stored = $_SESSION['captcha'] ?? null;
        unset($_SESSION['captcha']); // One attempt per challenge prevents replay.

        if (!is_array($stored) || !is_string($stored['hash'] ?? null)
            || !is_int($stored['created_at'] ?? null)) {
            return false;
        }

        if (time() - $stored['created_at'] > self::LIFETIME_SECONDS) {
            return false;
        }

        return hash_equals($stored['hash'], hash('sha256', strtoupper(trim($answer))));
    }

    public function renderPng(): void
    {
        Session::start();
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('PHP GD extension is required.');
        }

        $code = '';
        for ($i = 0; $i < 6; ++$i) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        $_SESSION['captcha'] = [
            'hash' => hash('sha256', $code),
            'created_at' => time(),
        ];

        $image = imagecreatetruecolor(242, 82);
        if ($image === false) {
            throw new \RuntimeException('Could not create CAPTCHA image.');
        }

        $background = imagecolorallocate($image, 246, 249, 254);
        $line = imagecolorallocate($image, 203, 214, 235);
        $dot = imagecolorallocate($image, 221, 229, 244);
        $ink = [
            imagecolorallocate($image, 34, 46, 89),
            imagecolorallocate($image, 63, 69, 149),
            imagecolorallocate($image, 47, 83, 125),
        ];
        imagefilledrectangle($image, 0, 0, 241, 81, $background);

        for ($i = 0; $i < 135; ++$i) {
            imagesetpixel($image, random_int(1, 240), random_int(1, 80), $dot);
        }
        for ($i = 0; $i < 4; ++$i) {
            imageline($image, random_int(0, 60), random_int(4, 78),
                random_int(180, 241), random_int(4, 78), $line);
        }

        for ($i = 0; $i < 6; ++$i) {
            $tile = imagecreatetruecolor(13, 19);
            imagefilledrectangle($tile, 0, 0, 12, 18, $background);
            imagestring($tile, 5, 2, 1, $code[$i], $ink[random_int(0, 2)]);

            $scaled = imagecreatetruecolor(27, 43);
            imagefilledrectangle($scaled, 0, 0, 26, 42, $background);
            imagecopyresized($scaled, $tile, 0, 0, 0, 0, 27, 43, 13, 19);
            $rotated = imagerotate($scaled, random_int(-12, 12), $background);
            imagecolortransparent($rotated, $background);
            imagecopy($image, $rotated, 20 + $i * 35, 14 + random_int(-4, 4),
                0, 0, imagesx($rotated), imagesy($rotated));
            imagedestroy($rotated);
            imagedestroy($scaled);
            imagedestroy($tile);
        }

        for ($i = 0; $i < 2; ++$i) {
            imagearc($image, random_int(75, 170), random_int(28, 60),
                random_int(160, 240), random_int(20, 45), 0, 180, $line);
        }

        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        imagepng($image);
        imagedestroy($image);
    }
}
