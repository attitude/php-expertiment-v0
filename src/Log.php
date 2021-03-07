<?php

namespace Phpx\Jsx;

final class Log {
    private static $debug = false;

    public static function start() {
        static::$debug = true;
    }

    public static function stop() {
        static::$debug = false;
    }

    public static function calledBy() {
        // TODO: Get class

        $backtrace = array_column(
            array_filter(
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
                fn(array $trace) => $trace['class'] === __NAMESPACE__.'\Transpiler',
            ),
            'function'
        );

        $backtrace = array_filter($backtrace, fn(string $function) => match($function) {
            'calledBy', 'warn', 'error', 'info' => false,
            default => true,
        });

        return implode(' > ', array_reverse($backtrace));
    }

    public static function debug(string $message, string $prefix = null) {
        $prefix = $prefix ? "{$prefix} " : '';
        echo $prefix.static::calledBy().': '.trim($message)."\n";
    }

    public static function info(string $message, bool $force = false) {
        if (static::$debug || $force) {
            static::debug($message, 'ℹ️');
        }
    }

    public static function success(string $message) {
        echo "✅ {$message}\n";
    }

    public static function warn(string $message) {
        static::debug($message, '⚠️');
    }

    public static function error(string $message) {
        static::debug($message, '❗');
    }
}
