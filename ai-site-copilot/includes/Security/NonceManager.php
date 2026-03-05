<?php
namespace AISC\Security;

if (!defined('ABSPATH')) exit;

class NonceManager {
    const ACTION = 'aisc_admin_nonce';

    public static function create(): string {
        return wp_create_nonce(self::ACTION);
    }

    public static function verify(string $nonce): bool {
        return (bool) wp_verify_nonce($nonce, self::ACTION);
    }
}