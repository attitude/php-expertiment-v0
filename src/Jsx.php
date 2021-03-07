<?php

namespace Phpx\Jsx;

require_once 'vendor/attitude/php/src/Functions/array/array_flatten.php';

class Jsx {
    const EMPTY_ELEMENTS = [
        'area', 'base',  'br',     'col',    'embed',
        'hr',   'img',   'input',  'keygen', 'link',
        'meta', 'param', 'source', 'track',  'wbr',
    ];
    const REGEX_HTML_ATTRIBUTE = '/^(?:[a-zA-Z]+|[a-zA-Z]+[-:][a-zA-Z]+|[a-zA-Z][a-zA-Z-]+[a-zA-Z][:-][a-zA-Z]|[a-zA-Z][:-][a-zA-Z][a-zA-Z-]+[a-zA-Z]|[a-zA-Z][a-zA-Z-]+[a-zA-Z][:-][a-zA-Z][a-zA-Z-]+[a-zA-Z])$/';

    private static $tags = [];
    private static $lookupPaths = [];

    private static function resolveFilePath(string $file) {
        if ($realpath = realpath($file)) {
            return $realpath;
        }

        if ($includepath = stream_resolve_include_path($file)) {
            return $includepath;
        }

        return $file;
    }

    public static function register (string $file, ?string $name = null): void {
        $name = $name ?? pathinfo($file, PATHINFO_FILENAME);
        $file = static::resolveFilePath($file);

        if (file_exists($file)) {
            static::$tags[$name] = $file;

            return;
        }

        throw new \Exception("File does not exist: {$name}.php", 404);
    }

    public static function registerPath(string $path) {
        $dir = static::resolveFilePath($path);

        if (!is_dir($dir)) {
            throw new \Exception("Directory does not exist: {$path}", 404);
        }

        static::$lookupPaths[] = $dir;
    }

    public static function list() {
        return static::$tags;
    }

    private static function exists(string $tag) {
        if (isset(static::$tags[$tag])) {
            return true;
        }

        if (ucfirst($tag) === $tag) {
            foreach (static::$lookupPaths as $path) {
                try {
                    static::register("{$path}/{$tag}.php", $tag);
                    return true;
                } catch (\Throwable $th) {}
            }
        }

        return isset(static::$tags[$tag]);
    }

    private static function isEmptyTag(string $name) {
        return in_array($name, self::EMPTY_ELEMENTS);
    }

    public static function indent(string $code) {
        $rows = explode("\n", $code);
        $rows = array_map(fn($row) => '  '.$row, $rows);

        return implode("\n", $rows);
    }

    public static function render(string $name, array $props) {
        // echo '<pre>render('.print_r([$name => $props], true).')</pre>';

        $isEmpty = self::isEmptyTag($name);

        if ($isEmpty && isset($props['children'])) {
            throw new \Exception("Empty tags must not have children, tag: {$name}", 400);
        }

        $props = array_filter($props, fn($v) => isset($v));

        $attributes = array_map(function($key, $value) {
            if ($key === 'children') {
                return;
            }

            if (!preg_match(self::REGEX_HTML_ATTRIBUTE, $key)) {
                throw new \TypeError("Atrribute must consist of [a-zA-Z], ':' and '-', '{$key}' given", 1);
            }

            if (is_array($value)) {
                $_flatValue = \array_flatten($value);

                $value = match($key) {
                    'style' => implode(';', array_filter(
                        array_map(
                            fn(int|string $key, int|string|null $value) => is_numeric($key)
                                ? $value
                                : (isset($value)
                                  ? "{$key}:{$value}"
                                  : null),
                            array_keys($_flatValue),
                            $_flatValue
                        ),
                        fn(string|null $cssRule) => isset($cssRule),
                    )),
                    default => implode(' ', $_flatValue),
                };
            }

            if ($value === true) {
                return $key;
            }

            if ($value === false || $value === null) {
                return;
            }

            return "{$key}=\"{$value}\"";
        }, array_keys($props), array_values($props));

        $attributes = ' '.implode(" ", array_filter($attributes));
        $attributes = empty(trim($attributes)) ? '' : $attributes;

        if ($isEmpty) {
            return in_array($name, static::EMPTY_ELEMENTS)
                ? "<{$name}{$attributes}>"
                : "<{$name}{$attributes}/>"
            ;
        }

        if (isset($props['children'])) {
            $props['children'] = (array) $props['children'];
            $props['children'] = array_values(array_filter(
                array_flatten($props['children']),
                fn($value) => is_numeric($value) || (is_string($value) && strlen(trim($value)) > 0)
            ));

            if (empty($props['children'])) {
                unset($props['children']);
            }
        }

        if (isset($props['children'])) {
            return "<{$name}{$attributes}>".(
                count($props['children']) === 1 && !strstr($props['children'][0], "\n")
                    ? $props['children'][0]
                    : "\n".static::indent(implode("\n", $props['children']))."\n"
            )."</{$name}>";
        }

        return "<{$name}{$attributes}></{$name}>";
    }

    private static function renderCustom(string $__name, array $props) {
        extract($props);

        return require(static::$tags[$__name]);
    }

    private static function isJsxTag (string $name) {
        if (strstr($name, '-')) {
            return false;
        }

        if (strtolower($name) === $name) {
            return false;
        }

        return true;
    }

    public static function jsx (string $name, array $props = []) {
        if (static::exists($name)) {
            return static::renderCustom($name, $props);
        }

        if (static::isJsxTag($name)) {
            throw new \Exception("JSX tag '{$name}' does not exist", 400);
        }

        return static::render($name, $props);
    }

    public static function path (array|object $value, string $path) {
        if (empty($path)) {
            throw new \TypeError('Argument #2 ($path) must be a non-empty string, empty string given');
        }

        list($key, $rest) = explode('.', $path.'.', 2);
        $rest = trim($rest, '.');

        $_value = is_array($value) ? $value[$key] ?? null : $value->{$key} ?? null;

        return isset($_value)
            ? (empty($rest)
                ? $_value
                : static::path($_value, $rest)
            ) : null
        ;
    }

    public static function jsAnd (...$arguments) {
        foreach ($arguments as $index => $argument) {
            if (!$argument()) {
                break;
            }
        }

        return $arguments[$index]();
    }

    public static function jsOr (...$arguments) {
        while (count($arguments)) {
            $result = array_shift($arguments)();

            if ($result) {
                return $result;
            }
        }

        return $result;
    }
}
