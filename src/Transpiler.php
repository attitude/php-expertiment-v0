<?php

namespace Phpx\Jsx;

class Transpiler {
    const BRACKETS = [
        '[' => ']',
        '{' => '}',
        '(' => ')',
    ];

    const VOID_ELEMENTS = [
        'area', 'base', 'basefont', 'bgsound', 'br', 'col', 'command',
        'embed', 'frame', 'hr', 'image', 'img', 'input', 'isindex',
        'keygen', 'link', 'menuitem', 'meta', 'nextid', 'param', 'source',
        'track', 'wbr'
    ];

    const STATIC_VARIABLE_PREFIX_REGEX = '/^(?:\\\\\w+){1,}::$/';

    private static function _isJSXAttribute(string $token) {
        return match($token) {
            '>', '/>' => false,
            default => true,
        };
    }

    private static function _nextClosingTagName(Tokens $tokens): string {
        $index = $tokens->index() + 1;
        $name = '';

        while($tokens->at($index) !== '>') {
            $name.= $tokens->at($index);
            $index++;
        }

        return $name;
    }

    private static function _generateTagPhp(string $name, array $attributes) {
        $start = "Jsx::jsx('{$name}', [";
        $end = "])";

        $code = [];

        foreach ($attributes as $attribute => $value) {
            if (is_array($value)) {
                $code[] = Jsx::indent("'{$attribute}' => [");
                foreach($value as $row) {
                    $code[] = Jsx::indent(Jsx::indent("{$row},"));
                }
                $code[] = Jsx::indent("],");
            } else {
                $code[] = Jsx::indent("'{$attribute}' => {$value},");
            }
        }

        if (empty($code)) {
            return $start.$end;
        }

        return "{$start}\n".implode("\n", $code)."\n{$end}";
    }

    private static function _isStaticVariablePrefix(string $token) {
        return preg_match(static::STATIC_VARIABLE_PREFIX_REGEX, $token);
    }

    public static function compileUntil(Tokens $tokens, string $until): string {
        Log::info('`'.$until.'`');

        $result = '';

        while ($tokens->next() !== $until) {
            Log::info('NEXT #'.($tokens->index() + 1).': `'.$tokens->next().'`');
            $result.= $tokens->advance();
        }

        return $result;
    }

    public static function compileUntilRegex(Tokens $tokens, string $untilRegex): string {
        Log::info('`'.$untilRegex.'`');

        $result = $tokens->advance();

        while (preg_match($untilRegex, $tokens->next()) === false) {
            Log::info('NEXT #'.($tokens->index() + 1).': `'.$tokens->next().'`');
            $result.= $tokens->advance();
        }

        return $result;
    }

    private static function compileTextNode(Tokens $tokens) {
        $content = '';

        while (
            $tokens->next(Tokens::INCLUDE_EMPTY) !== '<'
            &&
            $tokens->next(Tokens::INCLUDE_EMPTY) !== '</'
            &&
            $tokens->next(Tokens::INCLUDE_EMPTY) !== '<>'
            &&
            $tokens->next(Tokens::INCLUDE_EMPTY) !== '{'
        ) {
            $content.= $tokens->advance(Tokens::INCLUDE_EMPTY);

            if (strpos($tokens->next(Tokens::INCLUDE_EMPTY), "\n") !== false) {
                break;
            }
        }

        $content = trim($content);

        return $content ? Strings::escape($content) : null;
    }

    private static function compileQuotedString(Tokens $tokens) {
        Log::info('#'.($tokens->index() + 1).': `'.$tokens->next().'`');

        $quote = $tokens->advance();
        $string = static::compileUntil($tokens, $quote);
        $tokens->advance();

        return "{$quote}{$string}{$quote}";
    }

    public static function compileTag(Tokens $tokens): string {
        Log::info('#'.($tokens->index() + 1).': `'.$tokens->next().'`');

        if ($tokens->next(offset: 2) === '!') {
            return Strings::escape(static::compileUntil($tokens, '>').$tokens->advance());
        }

        if (trim($tokens->next(offset: 2)) ===  '') {
            return $tokens->advance();
        }

        $tokens->advance();
        $name = trim(static::compileUntilRegex($tokens, '/^(?:\s|>)$/'));

        $attributes = [];

        while (static::_isJSXAttribute($tokens->next(Tokens::SKIP_EMPTY))) {
            $attribute = $tokens->advance(Tokens::SKIP_EMPTY);

            if (!preg_match('/^[\w-]+$/', $attribute)) {
                throw new \Exception("Unexpected attribute: {$attribute}", 500);
            }

            $value = 'true';

            if ($tokens->next(Tokens::SKIP_EMPTY) === '=') {
                $tokens->advance(Tokens::SKIP_EMPTY);
                $value = match($tokens->next()) {
                    '"', "'" => static::compileQuotedString($tokens),
                    "{" => static::compileExpression($tokens),
                };
            }

            $attributes[$attribute] = $value;
        }

        $children = [];

        if ($tokens->advance(Tokens::SKIP_EMPTY) === '>' && !in_array($name, static::VOID_ELEMENTS)) {
            try {
                if ($name === 'script' || $name === 'style') {
                    $content = '';

                    while (
                        !($tokens->next(offset: 1) === '</'
                            &&
                            (
                                $tokens->next(offset: 2) === 'script'
                                ||
                                $tokens->next(offset: 2) === 'style'
                            )
                        )
                    ) {
                        $content.= $tokens->advance(Tokens::INCLUDE_EMPTY);
                    }

                    if (trim($content) !== '') {
                        $content = Strings::removeCommonIndentation($content);
                        $children = array_map(fn(string $row) => Strings::escape($row), explode("\n", $content));
                    }
                } else {
                    while ($tokens->next() !== '</>' && $tokens->next() !== '</' && static::_nextClosingTagName($tokens) !== $name) {
                        Log::info('NEXT #'.($tokens->index() + 1).': `'.$tokens->next().'`');

                        $children[] = match($tokens->next()) {
                            '<' => static::compileTag($tokens),
                            '<>' => static::compileFragment($tokens),
                            '{' => static::compileExpression($tokens),
                            default => static::compileTextNode($tokens),
                        };
                    }
                }
            } catch (\Throwable $e) {
                Log::info('Caught: '.$e->getMessage());

                if ($e->getCode() !== 404) {
                    throw $e;
                }
            }

            $children = array_filter($children);

            $tokens->advance();
            $tokens->advance();

            if ($tokens->next() !== '>') {
                throw new \ParseError("Mising closing `>`", 500);
            }

            $tokens->advance();
        }

        if (!empty($children)) {
            $attributes['children'] = $children;
        }

        Log::info("ATTRIBUTES: ".print_r($attributes, true));

        return static::_generateTagPhp($name, $attributes);
    }

    public static function compileFragment(Tokens $tokens): string {
        Log::info('#'.($tokens->index() + 1).': `'.$tokens->next().'`');

        $tokens->advance();
        $children = [];

        try {
            while ($tokens->next(Tokens::SKIP_EMPTY) !== '</>') {
                Log::info('NEXT #'.($tokens->index() + 1).': `'.$tokens->next().'`');

                $children[] = match($tokens->next()) {
                    '<' => static::compileTag($tokens),
                    '<>' => static::compileFragment($tokens),
                    '{' => static::compileExpression($tokens),
                    default => static::compileTextNode($tokens),
                };
            }

            $tokens->advance(Tokens::SKIP_EMPTY);
        } catch (\Throwable $e) {
            Log::info('Caught: '.$e->getMessage());

            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        $children = array_filter($children, 'trim');

        if (empty($children)) {
            return 'implode("\n", [])';
        }

        return 'implode("\n", ['."\n".Jsx::indent(
            implode(",\n", $children)
        ).",\n])";
    }

    public static function compileVariable(Tokens $tokens): string {
        Log::info('#'.($tokens->index() + 1).': `'.$tokens->next().'`');

        $variable = '';

        if (static::_isStaticVariablePrefix($tokens->next())) {
            $variable.= $tokens->advance();
        }

        $variable.= $tokens->advance();

        if (trim($tokens->next()) === '') {
            throw new \Exception('Unexpected space after `$`', 400);
        }

        while (
            preg_match('/^\w+$/', $tokens->next())
            ||
            $tokens->next() === '('
            ||
            $tokens->next() === '{'
            ||
            $tokens->next() === '['
            ||
            $tokens->next() === '->'
            ||
            ($tokens->next() === '?' && $tokens->next(offset: 2) === '->')
        ) {
            if ($tokens->next() === '->' && $tokens->current() !== '?') {
                $variable.='?';
            }

            $variable.= match($tokens->next()) {
                '[', '(', '{' => static::compileBlock($tokens),
                default => $tokens->advance(),
            };
        }

        return $variable;
    }

    public static function compile(Tokens $tokens): string {
        Log::info('#'.($tokens->index() + 1).': `'.$tokens->next().'`');

        $next = $tokens->next();

        switch ($next) {
            case '<>':
                return static::compileFragment($tokens);
            case '<':
                return static::compileTag($tokens);
            case '"':
            case "'":
                return static::compileQuotedString($tokens);
            case '(':
            case '{':
            case '[':
                return static::compileBlock($tokens);
            case '$':
                return static::compileVariable($tokens);
        }

        if (static::_isStaticVariablePrefix($tokens->next())) {
            return static::compileVariable($tokens);
        }

        return $tokens->advance();
    }

    public static function compileStatement(Tokens $tokens): string {
        Log::info('#'.($tokens->index() + 1).': `'.$tokens->next().'`');

        if ($tokens->next() === "\n") {
            return $tokens->advance();
        }

        $indentation = '';

        if (preg_match('/^[ \t]+$/', $tokens->next())) {
            while (preg_match('/^\s+$/', $tokens->next())) {
                $indentation.= $tokens->advance();
            }

            if ($tokens->next() === "\n") {
                return $indentation;
            }
        }

        $indentation = $indentation ? '  ' : '';

        if ($tokens->next() === '//') {
            return $indentation.static::compileUntil($tokens, "\n");
        }

        if ($tokens->next() === '/*') {
            return $indentation.static::compileUntil($tokens, '*/').$tokens->advance();
        }

        $result = [];

        try {
            while (
                $tokens->next() !== ';'
                &&
                $tokens->next() !== '</>'
                &&
                !in_array($tokens->next(), array_values(static::BRACKETS))
            ) {
                Log::info('NEXT #'.($tokens->index() + 1).': `'.$tokens->next().'`');
                $result[] = static::compile($tokens);

                if (
                    $tokens->next(Tokens::SKIP_EMPTY) === '</>'
                    ||
                    in_array($tokens->next(Tokens::SKIP_EMPTY), array_values(static::BRACKETS))
                ) {
                    break;
                }
            }

            if ($tokens->next() === ';') {
                $result[] = $tokens->advance();
            }
        } catch (\Throwable $e) {
            Log::info('Caught: '.$e->getMessage());

            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        Log::info('STATEMENT TOKENS: '.print_r($result, true));

        $left = '';

        if (isset($result[0]) && $result[0] === 'return') {
            $left = array_shift($result);
        }

        if ($index = array_search('=', $result)) {
            $left.= implode('', array_slice($result, 0, $index + 1));
            $result = array_slice($result, $index + 1);
        }

        $result = ($left ? "{$left} " : '').Operators::compile($result);

        return $indentation ? Jsx::indent($result) : $result;
    }

    public static function compileBlockAsStatementsList(Tokens $tokens): array {
        $start = null;
        $end = null;

        if (in_array($tokens->next(), array_keys(static::BRACKETS))) {
            $start = $tokens->advance();
            $end = static::BRACKETS[$start];
        }

        Log::info("START: {$start}");
        Log::info("END: {$end}");

        $statements = [];

        try {
            while (
                $tokens->next() !== $end
                &&
                $tokens->next() !== '</>'
                &&
                !in_array($tokens->next(), array_values(static::BRACKETS))
            ) {
                Log::info('NEXT #'.($tokens->index() + 1).': `'.$tokens->next().'`');
                $statements[] = static::compileStatement($tokens);
            }

            $tokens->advance();
        } catch (\Throwable $e) {
            Log::info('Caught: '.$e->getMessage());

            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        // Removes last spaces before the bracket
        while (!empty($statements) && trim($statements[count($statements) - 1], ' ') === '') {
            array_pop($statements);
        }

        if ($start) {
            array_unshift($statements, $start);
        }

        if ($end) {
            $statements[] = $end;
        }

        Log::info('STATEMENTS: '.print_r($statements, true));

        return $statements;
    }

    public static function compileBlock(Tokens $tokens): string {
        return implode('', static::compileBlockAsStatementsList($tokens));
    }

    public static function compileExpression(Tokens $tokens): string {
        Log::info('#'.($tokens->index() + 1).': `'.$tokens->next().'`');

        try {
            $result = static::compileBlockAsStatementsList($tokens);
        } catch (\Throwable $e) {
            Log::info('Caught: '.$e->getMessage());

            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        array_shift($result);
        array_pop($result);

        $result = implode("\n", $result);
        $result = Strings::removeCommonIndentation($result);

        return $result;
    }

    public static function transpile(string $template) {
        Log::info('Init...');

        $tokens = Tokens::tokenize($template);

        if ($tokens === false) {
            return;
        }

        Log::info('TOKENS: '.print_r($tokens, true));

        $tokens = new Tokens($tokens);
        $expressions = static::compileBlockAsStatementsList($tokens);

        $namespace = __NAMESPACE__;

        return "<?php\n".
            "\nnamespace {$namespace};\n".
            "\n".
            implode("", $expressions);
    }
}
