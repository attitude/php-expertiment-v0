<?php

namespace Phpx\Jsx;

final class Tokens {
    private $tokens;
    private $index = -1;

    const SKIP_EMPTY = true;
    const INCLUDE_EMPTY = false;

    public function __construct(array $tokens) {
        $this->tokens = $tokens;
    }

    public static function tokenize(string $content) {
        return preg_split('/('.implode('|', [
            // SPECIAL DOC TAG
            '<!doctype html>',
            // COMMENTS
            '<!--', '-->', '\/\/', '\/\*', '\*\/',
            // ESCAPED QUOTES
            '\\\"', // \"
            '\\\\\'', // \'
            // QUOTES
            '"', '\'',
            // COMPARISON OPERATORS
            '>=', '<=', '=>', '<=>', '!==', '===', '!=',
            // ASSIGNMENT OPERATORS
            '=', '-=', '\+=', '\.=', '\*=', '\/=', '\*\*=', '&=', '\|=', '\^=', '<<=', '>>=',
            // NULLISH COALESCING
            '\?\?',
            // NEGATION
            '!',
            // VARIABLES
            '\\$',
            // OBJECT OPERATOR
            '->',
            // FRAGMENT
            '<>', '<\/>',
            // TAGS
            '<\/', '\/>', '<', '>',
            // BRACKETS
            '\{', '\}', '\(', '\)', '\[', '\]',
            // PUNCTUATION
            ';', ',', '\.', '\?',
            // SPACES
            "\n", '\s+',
        ]).')/', $content, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);
    }

    public function index(int $index = null) {
        if (isset($index)) {
            $this->index = $index;
        }

        return $this->index;
    }

    public function at(int $index) {
        $token = $this->tokens[$index] ?? null;

        if (isset($token)) {
            return $token;
        }

        throw new \Exception('No current token', 404);
    }

    public function rewind(bool $skipEmpty = self::INCLUDE_EMPTY): string {
        $this->index = $this->index - 1;

        if ($skipEmpty) {
            try {
                $current = $this->current();

                if (trim($current) === '') {
                    $this->rewind($skipEmpty);
                }
            } catch(\Throwable $e) {}
        }

        return $this->current();
    }

    public function advance(bool $skipEmpty = self::INCLUDE_EMPTY): string {
        $this->index = $this->index + 1;

        if ($skipEmpty) {
            try {
                $current = $this->current();
                if (trim($current) === '') {
                    $this->advance($skipEmpty);
                }
            } catch(\Throwable $e) {}
        }

        return $this->current();
    }

    public function current(): string {
        $token = $this->tokens[$this->index] ?? null;

        if (isset($token)) {
            return $token;
        }

        throw new \Exception('No current token', 404);
    }

    public function next(bool $skipEmpty = self::INCLUDE_EMPTY, int $offset = 1): string {
        if ($offset < 1) {
            throw new \Exception("Offset must be larger than 0", 400);
        }

        $token = $this->tokens[$this->index + $offset] ?? null;

        if ($skipEmpty && isset($token) && trim($token) === '') {
            $token = $this->next($skipEmpty, $offset + 1);
        }

        if (isset($token)) {
            return $token;
        }

        throw new \Exception('No next token', 404);
    }

    public function previous(bool $skipEmpty = self::INCLUDE_EMPTY, int $offset = -1): string {
        if ($offset > -1) {
            throw new \Exception("Offset must be less than 0", 400);
        }

        $token = $this->tokens[$this->index + $offset] ?? null;

        if ($skipEmpty && isset($token) && trim($token) === '') {
            $token = $this->previous($skipEmpty, $offset - 1);
        }

        if (isset($token)) {
            return $token;
        }

        throw new \Exception('No previous token', 404);
    }

    public function prev(): string {
        return $this->previous();
    }
}
