<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PolygonLab\ApisPubliquesFrance\Outils\Address;
use PolygonLab\ApisPubliquesFrance\Outils\IpAddress;
use PolygonLab\ApisPubliquesFrance\Outils\OpenApi;

/**
 * Adresses testables, adresses IP publiques ou non, reconnaissance d'une description OpenAPI.
 */
final class AddressTest extends TestCase
{
    public function testATestableAddressGivesItsHostAndPort(): void
    {
        $address = Address::parse('https://API.Example.com/v1?page=2');
        self::assertInstanceOf(Address::class, $address);
        self::assertSame('api.example.com', $address->host);
        self::assertSame(443, $address->port);
        self::assertSame('https', $address->scheme);

        $address = Address::parse('http://api.example.com:8080/v1');
        self::assertInstanceOf(Address::class, $address);
        self::assertSame(8080, $address->port, 'tout port est admis');

        $address = Address::parse('http://api.example.com/v1');
        self::assertInstanceOf(Address::class, $address);
        self::assertSame(80, $address->port);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function refused(): array
    {
        return [
            'protocole' => ['ftp://api.example.com/', 'protocole autre que http ou https'],
            'adresse IPv4' => ['https://' . implode('.', [10, 0, 0, 1]) . '/', 'adresse IP'],
            'adresse IPv6' => ['https://[::1]/', 'adresse IP'],
            'adresse IPv4 hexadécimale' => ['http://0x7f.1/', 'adresse IP'],
            'boucle locale' => ['http://localhost/', 'hôte local'],
            'sous-domaine de localhost' => ['http://api.localhost/', 'hôte local'],
            'nom local' => ['https://nas.local/', 'hôte local'],
            'sans protocole' => ['api.example.com', 'adresse invalide'],
            'nom invalide' => ['https://api_.exa$mple.com/', 'nom d\'hôte invalide'],
        ];
    }

    #[DataProvider('refused')]
    public function testARefusedAddressGivesItsReason(string $url, string $reason): void
    {
        self::assertSame($reason, Address::parse($url));
    }

    public function testHostAndProtocolOfAnAddress(): void
    {
        self::assertSame('api.example.com', Address::hostOf('https://API.example.com:8443/v1'));
        self::assertNull(Address::hostOf('mailto:contact@example.com'));
        self::assertNull(Address::hostOf(null));
        self::assertNull(Address::hostOf('pas une adresse'));
        self::assertTrue(Address::isHttps('https://api.example.com/v1'));
        self::assertFalse(Address::isHttps('http://api.example.com/v1'));
        self::assertNull(Address::isHttps(null));
        self::assertNull(Address::isHttps('ftp://api.example.com/v1'));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function ipAddresses(): array
    {
        return [
            'publique' => ['198.51.100.20', true],
            'documentation' => ['192.0.2.1', true],
            'privée 10' => [implode('.', [10, 255, 0, 1]), false],
            'privée 172.16' => [implode('.', [172, 16, 0, 1]), false],
            'privée 172.31' => [implode('.', [172, 31, 255, 254]), false],
            'publique 172.32' => [implode('.', [172, 32, 0, 1]), true],
            'privée 192.168' => [implode('.', [192, 168, 0, 1]), false],
            'lien local' => [implode('.', [169, 254, 0, 1]), false],
            'boucle locale' => ['127.0.0.1', false],
            'boucle locale 127.x' => [implode('.', [127, 8, 9, 10]), false],
            'adresse nulle' => ['0.0.0.0', false],
            'opérateurs' => [implode('.', [100, 127, 255, 254]), false],
            'diffusion groupée' => [implode('.', [224, 0, 0, 251]), false],
            'diffusion générale' => [implode('.', [255, 255, 255, 255]), false],
            'IPv6 publique' => ['2001:db8::1', true],
            'IPv6 boucle locale' => ['::1', false],
            'IPv6 nulle' => ['::', false],
            'IPv6 privée fc' => ['fc00::1', false],
            'IPv6 privée fd' => ['fd12:3456::1', false],
            'IPv6 lien local' => ['fe80::abcd', false],
            'IPv6 diffusion groupée' => ['ff02::1', false],
            'IPv4 privée enveloppée' => ['::ffff:' . implode('.', [192, 168, 1, 1]), false],
            'IPv4 publique enveloppée' => ['::ffff:198.51.100.20', true],
            'illisible' => ['pas-une-adresse', false],
        ];
    }

    #[DataProvider('ipAddresses')]
    public function testOnlyPublicIpAddressesAreAccepted(string $ip, bool $public): void
    {
        self::assertSame($public, IpAddress::isPublic($ip));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function bodies(): array
    {
        return [
            'JSON OpenAPI 3' => ['{"openapi": "3.0.3", "info": {}}', true],
            'JSON Swagger 2, clé plus loin' => ['{"info": {"title": "a \" b"}, "swagger": "2.0", "paths": {}}', true],
            'JSON coupé après la clé' => ['{"openapi":"3.1.0","paths":{"/a":{"get":{"summary":"coup', true],
            'JSON, clé seulement dans un sous-objet' => ['{"info": {"openapi": "non"}, "paths": {}}', false],
            'JSON, mot dans une valeur' => ['{"title": "openapi"}', false],
            'JSON, liste à la racine' => ['[{"openapi": "3.0.0"}]', false],
            'YAML OpenAPI' => ["openapi: 3.0.0\ninfo:\n  title: Essai\n", true],
            'YAML Swagger entre guillemets' => ["\"swagger\": \"2.0\"\n", true],
            'YAML, clé indentée' => ["info:\n  openapi: non\n", false],
            'page HTML' => ['<!DOCTYPE html><html><body>swagger: ui</body></html>', false],
            'texte' => ['Bienvenue sur notre API.', false],
            'vide' => ['', false],
        ];
    }

    #[DataProvider('bodies')]
    public function testOpenApiDescriptionIsRecognised(string $body, bool $expected): void
    {
        self::assertSame($expected, OpenApi::detect($body));
    }
}
