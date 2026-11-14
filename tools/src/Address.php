<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Adresse d'une API ou d'une documentation, telle qu'elle peut être testée : protocole http ou https, nom d'hôte
 * public (ni adresse IP, ni nom local), sans identifiant dans l'adresse. Tout port est admis.
 *
 * Address::parse() rend l'adresse, ou la raison (texte) pour laquelle elle n'est pas testée.
 */
final class Address
{
    private const MAX_LENGTH = 2000;

    /** Suffixes de noms réservés à un usage local. */
    private const LOCAL_SUFFIXES = ['.localhost', '.local', '.localdomain', '.internal', '.intranet', '.lan', '.home', '.corp', '.home.arpa'];

    /** Écritures numériques d'une adresse IPv4 (« 2130706433 », « 0x7f.1 »). */
    private const NUMERIC_HOST = '/\A(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*\z/';

    private const HOSTNAME = '/\A(?:[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]\z/';

    private function __construct(
        public readonly string $url,
        public readonly string $scheme,
        public readonly string $host,
        public readonly int $port,
    ) {}

    /**
     * @return self|string l'adresse à tester, ou la raison pour laquelle elle ne l'est pas
     */
    public static function parse(string $url): self|string
    {
        if (\strlen($url) > self::MAX_LENGTH) {
            return 'adresse trop longue';
        }
        if ($url === '' || preg_match('/[\s\x00-\x1F\x7F]/', $url) === 1) {
            return 'adresse invalide';
        }
        $parts = parse_url($url);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return 'adresse invalide';
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return 'protocole autre que http ou https';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'adresse avec identifiant';
        }
        $host = rtrim(strtolower($parts['host']), '.');
        if ($host === '') {
            return 'adresse invalide';
        }
        if (IpAddress::isLiteral($host) || preg_match(self::NUMERIC_HOST, $host) === 1) {
            return 'adresse IP';
        }
        if ($host === 'localhost' || !str_contains($host, '.')) {
            return 'hôte local';
        }
        foreach (self::LOCAL_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return 'hôte local';
            }
        }
        if (\strlen($host) > 253 || preg_match(self::HOSTNAME, $host) !== 1) {
            return 'nom d\'hôte invalide';
        }

        return new self($url, $scheme, $host, $parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    }

    /**
     * Hôte (en minuscules) d'une adresse http ou https, ou null si elle n'en a pas.
     */
    public static function hostOf(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $parts = parse_url($url);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host']) || !\in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }
        $host = rtrim(strtolower($parts['host']), '.');

        return preg_match('/\A[a-z0-9._\[\]:-]+\z/', $host) === 1 ? $host : null;
    }

    /**
     * Vrai, faux, ou null si l'adresse n'est ni en http ni en https.
     */
    public static function isHttps(?string $url): ?bool
    {
        if ($url === null || self::hostOf($url) === null) {
            return null;
        }

        return str_starts_with(strtolower($url), 'https://');
    }
}
