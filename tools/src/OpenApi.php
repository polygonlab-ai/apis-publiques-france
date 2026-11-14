<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Reconnaissance d'une description OpenAPI (ou Swagger) dans le début d'une réponse : document JSON ou YAML dont
 * la racine porte la clé « openapi » ou « swagger ».
 *
 * Seul le début du corps est lu (64 Ko par défaut) : le document peut donc être coupé. La recherche se fait sans
 * décoder le document entier ; une clé de racine placée après la coupe n'est pas vue.
 */
final class OpenApi
{
    private const KEYS = ['openapi', 'swagger'];

    public static function detect(string $body): bool
    {
        $text = ltrim((string) preg_replace('/\A\xEF\xBB\xBF/', '', $body));
        if ($text === '' || $text[0] === '<') {
            return false;
        }
        if ($text[0] === '{') {
            return self::jsonRootHasKey($text);
        }

        return preg_match('/^["\']?(?:openapi|swagger)["\']?[ \t]*:[ \t]*\S/mi', $text) === 1;
    }

    /**
     * Parcourt le texte JSON, coupé ou non, et cherche une clé attendue au premier niveau de l'objet racine.
     */
    private static function jsonRootHasKey(string $text): bool
    {
        $length = \strlen($text);
        $depth = 0;
        $i = 0;
        while ($i < $length) {
            $character = $text[$i];
            if ($character === '"') {
                $start = ++$i;
                while ($i < $length && $text[$i] !== '"') {
                    $i += $text[$i] === '\\' ? 2 : 1;
                }
                if ($i >= $length) {
                    return false;
                }
                $string = substr($text, $start, $i - $start);
                $i++;
                if ($depth === 1 && \in_array(strtolower($string), self::KEYS, true) && preg_match('/\G\s*:/', $text, offset: $i) === 1) {
                    return true;
                }
                continue;
            }
            if ($character === '{' || $character === '[') {
                $depth++;
            } elseif ($character === '}' || $character === ']') {
                $depth--;
            }
            $i++;
        }

        return false;
    }
}
