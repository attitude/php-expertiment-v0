<?php

namespace Phpx\Jsx;

final class Operators {
    private static function prepare(array $tokens) {
        $regrouped = [[]];
        $index = 0;

        while (count($tokens) > 0) {
            $token = array_shift($tokens);

            if (
                $token === '?'
                ||
                $token === ':'
                ||
                $token === '??'
                ||
                $token === '||'
                ||
                $token === '&&'
                ||
                $token === ';'
            ) {
                $index++;
                $regrouped[$index] = [$token];
                $index++;

                continue;
            }

            if (!isset($regrouped[$index])) {
                $regrouped[$index] = [];
            }

            $regrouped[$index][] = $token;
        }

        if (!isset($regrouped[0])) {
            throw new \Exception("Parse error: First token must not be any of `?`, `:`, `??`, `||`, `&&`", 400);
        }

        return array_map(fn(array $tokens) => trim(implode('', $tokens)), $regrouped);
    }

    private static function compileLogicalAndGroups(array $groups) {
        return count($groups) === 1
            ? $groups[0]
            : 'Jsx::jsAnd(fn() => '.implode(', fn() => ', $groups).')';
    }

    private static function compileLogicalOrGroups(array $groups) {
        return count($groups) === 1
            ? static::compileLogicalAndGroups($groups[0])
            : 'Jsx::jsOr(fn() => '.implode(', fn() => ', array_map(fn(array $ands) => static::compileLogicalAndGroups($ands), $groups)).')';
    }

    public static function compileOrs(array $conditions) {
        $groups = [
            0 => [],
        ];

        $index = 0;

        foreach ($conditions as $condition) {
            if ($condition === '||') {
                $groups[] = [];
                $index++;
            } elseif ($condition !== '&&') {
                $groups[$index][] = $condition;
            }
        }

        return static::compileLogicalOrGroups($groups);
    }

    public static function compileNullish(array $conditions) {
        $index = array_search('??', $conditions);

        if ($index === 0) {
            throw new \Exception('Unexpected `??` at the begining of expression', 400);
        }

        if ($index > 0) {
            return static::compileOrs(
                array_slice($conditions, 0, $index)
            ).' ?? '.static::compileNullish(
                array_slice($conditions, $index + 1)
            );
        }

        return static::compileOrs($conditions);
    }

    public static function compile(array $conditions) {
        $conditions = static::prepare($conditions);

        $end = '';

        if ($conditions[count($conditions) - 1] === ';') {
            $end = array_pop($conditions);
        }

        $questionmarkIndex = array_search('?', $conditions);
        $colonIndex = array_search(':', $conditions);

        if ($questionmarkIndex === 0) {
            throw new \Exception("Unexpected `?` at the beginning of the condition", 400);
        }

        if ($questionmarkIndex > 0) {
            if ($colonIndex === false) {
                throw new \Exception("Missing `:` in the ternary operator", 400);
            }

            if ($colonIndex < $questionmarkIndex) {
                throw new \Exception("Unexpected `:` that came sooner than `?` in the condition", 400);
            }

            $if = static::compileNullish(array_slice($conditions, 0, $questionmarkIndex));
            $then = static::compileNullish(array_slice($conditions, $questionmarkIndex + 1, $colonIndex - ($questionmarkIndex + 1)));
            $else = static::compile(array_slice($conditions, $colonIndex + 1));

            $valueCounts = array_count_values($conditions);

            if ($valueCounts['?'] !== $valueCounts[':']) {
                throw new \Exception("Uneven counts of `?` and `:` in ternary operators", 400);
            }

            return $valueCounts[':'] > 1
                ? "{$if} ? {$then} : ({$else}){$end}"
                : "{$if} ? {$then} : {$else}{$end}";
        }

        return static::compileNullish($conditions).$end;
    }
}
