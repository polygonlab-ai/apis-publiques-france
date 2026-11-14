<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Tests\Fixtures;

use PolygonLab\ApisPubliquesFrance\Outils\Api;
use PolygonLab\ApisPubliquesFrance\Outils\Sources;
use PolygonLab\ApisPubliquesFrance\Outils\TestClass;
use PolygonLab\ApisPubliquesFrance\Outils\Updater;

/**
 * API d'essai et collectes d'essai, pour les tests qui n'ont pas besoin de lire des pages du catalogue.
 *
 * @phpstan-import-type Collected from Updater
 */
final class Apis
{
    /**
     * API d'essai : identifiant « essai-<numéro> », adresse sur l'hôte donné, organisation et résultats au choix.
     */
    public static function make(
        int $number,
        string $test = TestClass::RESPONDS,
        ?string $access = 'ouvert',
        ?string $organisation = 'Organisation d\'essai',
        string $host = 'api.example.com',
        ?string $title = null,
        ?string $description = null,
        ?string $modifiedOn = '2026-10-20',
        ?int $httpCode = 200,
    ): Api {
        return new Api(
            sprintf('essai-%04d', $number),
            $title ?? sprintf('API d\'essai n° %d', $number),
            $description,
            $organisation,
            $access,
            sprintf('https://%s/v1/service-%d', $host, $number),
            'REST',
            null,
            null,
            null,
            null,
            null,
            null,
            [],
            sprintf('https://www.data.gouv.fr/dataservices/essai-%04d/', $number),
            $test,
            $test === TestClass::UNTESTED || $test === TestClass::UNREACHABLE ? null : $httpCode,
            TestClass::DOC_ABSENT,
            '2026-03-02',
            $modifiedOn,
        );
    }

    /**
     * Plusieurs API d'essai, numérotées de $from à $to, toutes de la même classe de test.
     *
     * @return list<Api>
     */
    public static function range(int $from, int $to, string $test = TestClass::RESPONDS, string $host = 'api.example.com'): array
    {
        $apis = [];
        for ($number = $from; $number <= $to; $number++) {
            $apis[] = self::make($number, $test, host: $host);
        }

        return $apis;
    }

    /**
     * Collecte d'essai portant ces API.
     *
     * @param list<Api> $apis
     * @param array<string, list<string>> $emails
     *
     * @return Collected
     */
    public static function collected(array $apis, array $emails = []): array
    {
        return [
            'apis' => $apis,
            'sha256' => hash('sha256', serialize($apis)),
            'size' => 1000,
            'pages' => 1,
            'read' => \count($apis),
            'skipped' => 0,
            'tested' => \count($apis),
            'requests' => \count($apis),
            'duration' => 12,
            'emails' => $emails,
            'url' => Sources::load()->listUrl(),
            'path' => '/collecte/catalogue.json',
        ];
    }
}
