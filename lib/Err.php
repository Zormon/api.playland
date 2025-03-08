<?php
namespace Lib;

class Err {
    private static $codes;

    private static function load() {
        if (self::$codes === null) {
            $jsonfile = file_get_contents(AppPaths('lib') . '/errCodes.json');
            if (!$jsonfile) {
                throw new \Exception("Could not load error file.");
            }
            self::$codes = json_decode($jsonfile, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Could not decode error file.");
            }
        }
    }

    public static function get($key) {
        self::load();
        return self::$codes[$key] ?? null;
    }
}