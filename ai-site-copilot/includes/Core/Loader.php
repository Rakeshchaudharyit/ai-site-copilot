<?php
namespace AISC\Core;

if (!defined('ABSPATH')) exit;

final class Loader {
    public static function register(): void {
        spl_autoload_register(function ($class) {
            $prefix = 'AISC\\';
            $base_dir = AISC_PATH . 'includes/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) return;

            $relative = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative) . '.php';

            if (file_exists($file)) require_once $file;
        });
    }
}

\AISC\Core\Loader::register();