<?php

namespace Phpx\Jsx;

if (!defined('TAG_EXTENSION_IN')) {
    define('TAG_EXTENSION_IN', 'tag');
}

if (!defined('TAG_EXTENSION_OUT')) {
    define('TAG_EXTENSION_OUT', 'php');
}

class Walker {
    private static $started = null;

    private function __construct() {}

    private static function removeBase(string $file): string {
        return str_replace(getcwd(), '', $file);
    }

    public static function compile(string $in) {
        $out = str_replace(TAG_EXTENSION_IN.'@@@_END_$$$', TAG_EXTENSION_OUT, $in.'@@@_END_$$$');

        if (file_exists($out) && filemtime($out) > filemtime($in) && static::$started < filemtime($out)) {
            return;
        }

        $phpx = file_get_contents($in);

        if (is_string($phpx)) {
            $exception = null;

            try {
                $compiled = trim($phpx) === '' ? null : Transpiler::transpile($phpx);
            } catch (\Throwable $e) {
                $compiled = null;
                $exception = $e;
            }

            if ($compiled === null) {
                touch($out);
                Log::warn(static::removeBase($in)." 🚧 ".static::removeBase($out));
            } elseif (file_put_contents($out, $compiled)) {
                Log::success(static::removeBase($in)." ✨ ".static::removeBase($out));
            } else {
                Log::error("Failed to write to file");
                exit;
            }

            if ($exception) {
                Log::error("{$e->getMessage()}\n> file: {$in}");
                print_r($exception);
            }
        } else {
            Log::error("Unable to read input file");
            exit;
        }
    }

    public static function walk(string $dir) {
        if (!isset(static::$started)) {
            static::$started = time();
        }

        $extension = TAG_EXTENSION_IN;
        $dirs = glob("${dir}/*", GLOB_ONLYDIR);
        $files = glob("${dir}/*.${extension}");

        if (sizeof($dirs) > 0) {
            foreach ($dirs as $_dir) {
                self::walk($_dir);
            }
        }

        if (sizeof($files) > 0) {
            foreach ($files as $file) {
                self::compile($file);
            }
        }
    }
}
