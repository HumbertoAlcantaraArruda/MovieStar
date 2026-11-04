<?php

declare(strict_types=1);

namespace MovieStar\Tests\Support;

/**
 * Procura, pelos tokens do PHP, construções que não existem no PHP 7.4 (o projeto precisa rodar nele,
 * mas só há PHP 8.x no ambiente): match, nullsafe, atributos, enums, readonly, promoção de propriedades
 * no construtor, union/mixed/never/static como tipo, argumentos nomeados, vírgula final em lista de
 * parâmetros/use, catch sem variável e funções novas do PHP 8.
 *
 * Não substitui rodar no 7.4, mas pega os casos comuns. Só funciona rodando em PHP 8+ (o tokenizer
 * do 7.4 nem reconhece essas construções; lá, o próprio "php -l" já as acusa).
 */
final class Php74CompatibilityScanner
{
    private const PHP8_FUNCTIONS = [
        'str_contains', 'str_starts_with', 'str_ends_with', 'get_debug_type', 'get_resource_id', 'fdiv',
        'preg_last_error_msg', 'array_is_list', 'enum_exists', 'json_validate', 'mb_str_pad', 'array_find',
        'array_find_key', 'array_any', 'array_all', 'mb_trim', 'mb_ltrim', 'mb_rtrim', 'mb_ucfirst',
        'mb_lcfirst', 'str_increment', 'str_decrement',
    ];

    private const PHP8_ONLY_TYPES = ['mixed', 'never', 'static', 'false', 'null', 'true'];

    private function __construct()
    {
    }

    /**
     * @return string[] problemas encontrados ("arquivo:linha descrição")
     */
    public static function scanFile(string $file): array
    {
        return self::scanCode((string) file_get_contents($file), $file);
    }

    /**
     * @return string[]
     */
    public static function scanCode(string $code, string $label = 'código'): array
    {
        $tokens = array_values(array_filter(token_get_all($code), static function ($token): bool {
            return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
        }));

        $problems = [];
        $count    = count($tokens);
        $line     = 1;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                $line = $token[2];
            }

            $id = is_array($token) ? $token[0] : $token;

            foreach (['T_MATCH' => 'match', 'T_NULLSAFE_OBJECT_OPERATOR' => 'operador nullsafe ?->', 'T_ATTRIBUTE' => 'atributo #[...]',
                      'T_ENUM' => 'enum', 'T_READONLY' => 'readonly'] as $name => $what) {
                if (defined($name) && $id === constant($name)) {
                    $problems[] = $label . ':' . $line . ' ' . $what;
                }
            }

            // Chamada de função nova do PHP 8 (não método, não declaração).
            if (self::isName($token) && self::text($tokens, $i + 1) === '('
                && in_array(strtolower(ltrim($token[1], '\\')), self::PHP8_FUNCTIONS, true)
                && !in_array(self::id($tokens, $i - 1), [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
                $problems[] = $label . ':' . $line . ' função do PHP 8: ' . $token[1] . '()';
            }

            // Argumento nomeado: "(" ou "," seguido de identificador e ":" (não "::").
            if (in_array(self::text($tokens, $i - 1), ['(', ','], true) && self::isName($token)
                && self::text($tokens, $i + 1) === ':' && self::isCallArgument($tokens, $i)) {
                $problems[] = $label . ':' . $line . ' argumento nomeado: ' . $token[1] . ':';
            }

            // catch sem variável.
            if ($id === T_CATCH) {
                $j = $i + 2;

                while ($j < $count && self::text($tokens, $j) !== ')' && self::id($tokens, $j) !== T_VARIABLE) {
                    $j++;
                }

                if (self::text($tokens, $j) === ')') {
                    $problems[] = $label . ':' . $line . ' catch sem variável';
                }
            }

            if ($id === T_FUNCTION || $id === T_FN) {
                $problems = array_merge($problems, self::scanSignature($tokens, $i, $label));
            }

            // Vírgula final na lista "use (...)" de closure.
            if ($id === T_USE && self::text($tokens, $i + 1) === '(') {
                $j = $i + 2;

                while ($j < $count && self::text($tokens, $j) !== ')') {
                    $j++;
                }

                if (self::text($tokens, $j - 1) === ',') {
                    $problems[] = $label . ':' . $line . ' vírgula final em use (...)';
                }
            }
        }

        return $problems;
    }

    /**
     * Parâmetros e tipo de retorno de uma função a partir do token "function"/"fn".
     *
     * @param array<int, mixed> $tokens
     * @return string[]
     */
    private static function scanSignature(array $tokens, int $start, string $label): array
    {
        $count = count($tokens);
        $i     = $start + 1;

        while ($i < $count && self::text($tokens, $i) !== '(') {
            $i++;
        }

        $problems  = [];
        $depth     = 0;
        $inDefault = false;
        $line      = is_array($tokens[$start]) ? $tokens[$start][2] : 0;
        $isCtor    = strtolower(self::text($tokens, $start + 1)) === '__construct';

        for (; $i < $count; $i++) {
            $text = self::text($tokens, $i);

            if ($text === '(' || $text === '[') {
                $depth++;
                continue;
            }

            if ($text === ')' || $text === ']') {
                $depth--;

                if ($depth === 0) {
                    break;
                }

                continue;
            }

            if ($depth !== 1) {
                continue;
            }

            if ($text === ',') {
                $inDefault = false;

                if (self::text($tokens, $i + 1) === ')') {
                    $problems[] = $label . ':' . $line . ' vírgula final na lista de parâmetros';
                }

                continue;
            }

            if ($text === '=') {
                $inDefault = true;
                continue;
            }

            if ($inDefault) {
                if (self::id($tokens, $i) === T_NEW) {
                    $problems[] = $label . ':' . $line . ' "new" em valor padrão de parâmetro';
                }

                continue;
            }

            if ($isCtor && in_array(self::id($tokens, $i), [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                $problems[] = $label . ':' . $line . ' promoção de propriedade no construtor';
            }

            if ($text === '|') {
                $problems[] = $label . ':' . $line . ' union type em parâmetro';
            }

            if (self::isName($tokens[$i]) && in_array(strtolower($text), ['mixed'], true)
                && self::id($tokens, $i + 1) === T_VARIABLE) {
                $problems[] = $label . ':' . $line . ' tipo mixed em parâmetro';
            }
        }

        // Tipo de retorno: ": tipo" até "{", ";" ou "=>".
        if (self::text($tokens, $i + 1) === ':') {
            for ($j = $i + 2; $j < $count && !in_array(self::text($tokens, $j), ['{', ';', '=>'], true); $j++) {
                $text = strtolower(self::text($tokens, $j));

                if ($text === '|') {
                    $problems[] = $label . ':' . $line . ' union type no retorno';
                } elseif (in_array($text, self::PHP8_ONLY_TYPES, true)) {
                    $problems[] = $label . ':' . $line . ' tipo de retorno ' . $text;
                }
            }
        }

        return $problems;
    }

    /**
     * Distingue "f(nome: valor)" de "[chave => ...]" e de ternários: o "(" que abre a lista
     * deve ser de uma chamada (precedido de nome, variável, ")" ou "]").
     *
     * @param array<int, mixed> $tokens
     */
    private static function isCallArgument(array $tokens, int $i): bool
    {
        $depth = 0;

        for ($j = $i - 1; $j >= 0; $j--) {
            $text = self::text($tokens, $j);

            if ($text === ')' || $text === ']') {
                $depth++;
            } elseif ($text === '[') {
                if ($depth === 0) {
                    return false;
                }

                $depth--;
            } elseif ($text === '(') {
                if ($depth === 0) {
                    $prev = $tokens[$j - 1] ?? null;

                    return $prev !== null && (self::isName($prev) || self::id($tokens, $j - 1) === T_VARIABLE
                        || in_array(self::text($tokens, $j - 1), [')', ']'], true));
                }

                $depth--;
            } elseif ($depth === 0 && in_array($text, ['?', ';', '{', '}'], true)) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param mixed $token
     */
    private static function isName($token): bool
    {
        if (!is_array($token)) {
            return false;
        }

        $names = [T_STRING];

        foreach (['T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED', 'T_NAME_RELATIVE'] as $name) {
            if (defined($name)) {
                $names[] = constant($name);
            }
        }

        return in_array($token[0], $names, true);
    }

    /**
     * @param array<int, mixed> $tokens
     * @return int|string|null
     */
    private static function id(array $tokens, int $i)
    {
        if (!isset($tokens[$i])) {
            return null;
        }

        return is_array($tokens[$i]) ? $tokens[$i][0] : $tokens[$i];
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private static function text(array $tokens, int $i): string
    {
        if (!isset($tokens[$i])) {
            return '';
        }

        return is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
    }
}
