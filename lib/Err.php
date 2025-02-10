<?php
namespace Lib;

class Err {
    private static $codes;

    private static function load() {
        if (self::$codes === null) {
            $jsonfile = file_get_contents(AppPaths('lib') . '/errCodes.json');
            if (!$jsonfile) {
                throw new \Exception("Could not load error codes file.");
            }
            self::$codes = json_decode($jsonfile, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Could not decode error codes file.");
            }
        }
    }

    public static function get($key) {
        self::load();
        return ['errno' => self::code($key), 'error' => self::msg($key)];
    }

    public static function code($key) {
        self::load();
        return self::$codes[$key]['code'] ?? null;
    }

    public static function msg($key) {
        self::load();
        return self::$codes[$key]['message'] ?? 'Unknown error.';
    }
}