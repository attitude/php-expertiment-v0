<?php

namespace Phpx\Jsx;

final class Strings {
    public static function escape(string $string) {
        return '"'.addcslashes($string, '"').'"';
    }

    public static function removeCommonIndentation(string $rows): string {
        $rows = explode("\n", $rows);

        while (!empty($rows) && trim($rows[0]) === '') {
            array_shift($rows);
        }

        while (!empty($rows) && trim($rows[count($rows) - 1]) === '') {
            array_pop($rows);
        }

        $rows = array_map(fn(string $row) => str_replace("\t", '    ', $row), $rows);

        if (count($rows) === 0) {
            return "\n";
        }

        if (count($rows) === 1) {
            return implode("\n", $rows);
        }

        $result = [];

        foreach ($rows as $row) {
            if ($row === '') {
                $result[] = '';
                continue;
            }

            if ($row[0] === ' ') {
                $result[] = substr($row, 1);
            } else {
                break;
            }
        }

        if (count($result) !== count($rows)) {
            return implode("\n", $rows);
        }

        return static::removeCommonIndentation(implode("\n", $result));
    }
}
