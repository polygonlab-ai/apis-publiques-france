<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Tests;

use PHPUnit\Framework\TestCase;
use PolygonLab\ApisPubliquesFrance\Outils\Api;
use PolygonLab\ApisPubliquesFrance\Outils\Catalogue;
use PolygonLab\ApisPubliquesFrance\Outils\ReplayClient;
use PolygonLab\ApisPubliquesFrance\Outils\SourceError;
use PolygonLab\ApisPubliquesFrance\Outils\Sources;
use PolygonLab\ApisPubliquesFrance\Outils\Text;
use PolygonLab\ApisPubliquesFrance\Tests\Fixtures\Workspace;

/**
 * La lecture du catalogue, sur de fausses pages (aucun appel réseau) : pagination, champs nuls, types d'accès,
 * entrées écartées, doublons, adresses e-mail retirées, champs obligatoires, plafond de pages.
 *
 * @phpstan-import-type Reading from Catalogue
 */
final class CatalogueTest extends TestCase
{
    private Workspace $workspace;

    protected function setUp(): void
    {
        $this->workspace = new Workspace();
    }

    protected function tearDown(): void
    {
        $this->workspace->remove();
    }

    public function testEveryPageIsReadUntilNoNextPage(): void
    {
        $client = new ReplayClient(Workspace::PAGES);

        $reading = (new Catalogue($client, Sources::load(), static function (int $seconds): void {}))->read();

        self::assertSame([
            'https://www.data.gouv.fr/api/1/dataservices/?page_size=100&page=1',
            'https://www.data.gouv.fr/api/1/dataservices/?page_size=100&page=2',
        ], $client->requests);
        self::assertSame(2, $reading['pages']);
        self::assertSame(19, $reading['read']);
        self::assertSame(3, $reading['skipped'], 'entrées archivée, privée et supprimée');
        $ids = array_map(static fn(Api $api): string => $api->id, $reading['apis']);
        self::assertCount(15, $ids, 'seize entrées publiables, dont une en double');
        $sorted = $ids;
        sort($sorted, SORT_STRING);
        self::assertSame($sorted, $ids, 'API triées par identifiant');
        foreach (['a011', 'a012', 'a013'] as $suffix) {
            self::assertNotContains('aaaa0000000000000000' . $suffix, $ids);
        }
    }

    public function testOnePauseBetweenTwoPages(): void
    {
        $pauses = [];
        $sleep = static function (int $seconds) use (&$pauses): void {
            $pauses[] = $seconds;
        };

        (new Catalogue(new ReplayClient(Workspace::PAGES), Sources::load(), $sleep))->read();

        self::assertSame([1], $pauses);
    }

    public function testAnEntryIsTakenWithItsPublishedFacts(): void
    {
        $api = $this->api('a001');

        self::assertSame('API Adresse d\'essai', $api->title);
        self::assertSame('Institut d\'essai géographique', $api->organisation);
        self::assertSame('ouvert', $api->access);
        self::assertSame('https://api.example.com/adresse', $api->address);
        self::assertSame('api.example.com', $api->host());
        self::assertSame('REST', $api->format);
        self::assertSame('lov2', $api->licence);
        self::assertSame('https://docs.example.com/adresse', $api->documentation);
        self::assertSame('https://api.example.com/adresse/openapi.json', $api->machineDocumentation);
        self::assertSame(99.9, $api->declaredAvailability);
        self::assertSame('50 appels par seconde et par adresse.', $api->limits);
        self::assertSame(['adresse', 'geocodage'], $api->keywords, 'mots-clés triés, sans doublon');
        self::assertSame('https://www.data.gouv.fr/dataservices/essai-a001/', $api->page);
        self::assertSame('2026-01-16', $api->createdOn, 'jour à l\'heure de Paris');
        self::assertSame('2026-10-20', $api->modifiedOn);
    }

    public function testDescriptionIsPlainText(): void
    {
        self::assertSame('Présentation Recherche d\'adresses en France : guide. géocodage géocodage inverse', $this->api('a001')->description);
    }

    public function testTheThreeAccessTypesAndAnUnknownOne(): void
    {
        self::assertSame('ouvert', $this->api('a001')->access);
        self::assertSame('restreint', $this->api('a002')->access);
        self::assertSame('ouvert_avec_compte', $this->api('a003')->access);
        self::assertNull($this->api('a018')->access, 'type d\'accès inconnu : non repris');
        self::assertSame(Api::ACCESS_UNKNOWN, $this->api('a018')->accessKey());
    }

    public function testNullFieldsStayNull(): void
    {
        $withoutAddress = $this->api('a004');
        self::assertNull($withoutAddress->address);
        self::assertNull($withoutAddress->host());
        self::assertNull($withoutAddress->format);
        self::assertNull($withoutAddress->licence);
        self::assertNull($withoutAddress->machineDocumentation);
        self::assertNull($withoutAddress->declaredAvailability);
        self::assertNull($withoutAddress->limits);
        self::assertSame([], $withoutAddress->keywords);

        self::assertNull($this->api('a003')->organisation, 'entrée sans organisation : le propriétaire n\'est jamais repris');
    }

    public function testUnexpectedFieldsDoNotStopTheReading(): void
    {
        $api = $this->api('a018');

        self::assertSame('API aux champs inattendus', $api->title);
        self::assertSame([], $api->keywords, 'mots-clés qui ne sont pas une liste : ignorés');
        self::assertNull($api->declaredAvailability, 'disponibilité qui n\'est pas un nombre : ignorée');
        self::assertNull($api->licence, 'licence qui n\'est pas un texte : ignorée');
        self::assertNull($api->createdOn, 'date illisible : ignorée');
        self::assertNull($api->modifiedOn);
    }

    public function testADuplicatedIdentifierKeepsTheMostRecentEntry(): void
    {
        $api = $this->api('a001');

        self::assertSame('API Adresse d\'essai', $api->title);
        self::assertSame('https://api.example.com/adresse', $api->address);
    }

    public function testEmailAddressesAreNeverKept(): void
    {
        $reading = $this->read(Workspace::PAGES);
        $api = $this->api('a003');

        self::assertSame('Annuaire des services. Écrire à ' . Text::EMAIL_REPLACEMENT . ' pour obtenir une clé.', $api->description);
        self::assertSame('Sur demande à ' . Text::EMAIL_REPLACEMENT . '.', $api->limits);
        self::assertNull($api->accessRequest, 'lien qui contient une adresse e-mail : retiré');
        self::assertSame(['aaaa0000000000000000a003' => ['description', 'demande_acces', 'limites']], $reading['emails']);
        $published = (string) json_encode(array_map(static fn(Api $api): array => $api->normalized(), $reading['apis']), JSON_UNESCAPED_UNICODE);
        self::assertDoesNotMatchRegularExpression(Text::EMAIL, $published);
    }

    public function testContactsOwnersAndMetricsAreNeverKept(): void
    {
        $reading = $this->read(Workspace::PAGES);
        $published = (string) json_encode(array_map(static fn(Api $api): array => $api->normalized() + ['cree_le' => $api->createdOn], $reading['apis']), JSON_UNESCAPED_UNICODE);

        foreach (['Équipe d\'essai', 'equipe-', 'Camille', 'Exemple', 'followers', 'is_franceconnect', 'permissions', 'quality', 'cccc0000', 'dddd0000'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $published);
        }
    }

    public function testLongTextsAreCutCleanly(): void
    {
        $pages = $this->workspace->pages();
        $long = str_repeat('Une phrase assez longue pour dépasser la limite. ', 20);
        $this->workspace->edit($pages . '/page-0001.json', static function (array $page) use ($long): array {
            self::assertIsArray($page['data']);
            self::assertIsArray($page['data'][0]);
            $page['data'][0]['description'] = $long;
            $page['data'][0]['rate_limiting'] = $long;

            return $page;
        });

        $api = $this->api('a001', $pages);

        self::assertNotNull($api->description);
        self::assertNotNull($api->limits);
        self::assertLessThanOrEqual(Api::DESCRIPTION_LENGTH, mb_strlen($api->description));
        self::assertGreaterThan(Api::DESCRIPTION_LENGTH - 20, mb_strlen($api->description));
        self::assertLessThanOrEqual(Api::LIMITS_LENGTH, mb_strlen($api->limits));
        self::assertStringEndsWith(Text::ELLIPSIS, $api->description);
        self::assertStringEndsWith(Text::ELLIPSIS, $api->limits);
        $kept = mb_substr($api->description, 0, -1);
        self::assertStringStartsWith($kept . ' ', $long, 'la coupe se fait à la fin d\'un mot');
    }

    public function testAnEntryWithoutTitleStopsTheReading(): void
    {
        $pages = $this->workspace->pages();
        $this->workspace->edit($pages . '/page-0002.json', static function (array $page): array {
            self::assertIsArray($page['data']);
            self::assertIsArray($page['data'][0]);
            $page['data'][0]['title'] = '  ';

            return $page;
        });

        $this->expectException(SourceError::class);
        $this->expectExceptionMessage('Page 2, entrée n° 1 : champ obligatoire « title » absent ou vide ; entrée : aaaa0000000000000000a009');
        $this->read($pages);
    }

    public function testAnEntryWithoutIdentifierStopsTheReadingWithoutShowingAnEmail(): void
    {
        $pages = $this->workspace->pages();
        $this->workspace->edit($pages . '/page-0001.json', static function (array $page): array {
            self::assertIsArray($page['data']);
            $page['data'][2] = ['title' => 'Sans identifiant', 'description' => 'Écrire à contact@example.com.'];

            return $page;
        });

        try {
            $this->read($pages);
            self::fail('lecture non arrêtée');
        } catch (SourceError $error) {
            self::assertStringContainsString('Page 1, entrée n° 3 : champ obligatoire « id » absent ou vide', $error->getMessage());
            self::assertStringContainsString('Sans identifiant', $error->getMessage());
            self::assertDoesNotMatchRegularExpression(Text::EMAIL, $error->getMessage());
        }
    }

    public function testThePageCapStopsTheReading(): void
    {
        $this->expectException(SourceError::class);
        $this->expectExceptionMessage('Plafond de 1 pages atteint');
        (new Catalogue(new ReplayClient(Workspace::PAGES), $this->workspace->sources(['pages_max' => 1]), static function (int $seconds): void {}))->read();
    }

    public function testAnUnreadablePageStopsTheReading(): void
    {
        $pages = $this->workspace->pages();
        file_put_contents($pages . '/page-0002.json', '<html>Erreur</html>');

        $this->expectException(SourceError::class);
        $this->expectExceptionMessage('Page 2 : réponse illisible');
        $this->read($pages);
    }

    public function testAPageWithoutItsListStopsTheReading(): void
    {
        $pages = $this->workspace->pages();
        file_put_contents($pages . '/page-0001.json', '{"message": "erreur"}');

        $this->expectException(SourceError::class);
        $this->expectExceptionMessage('Page 1 : liste « data » absente');
        $this->read($pages);
    }

    public function testAMissingPageStopsTheReading(): void
    {
        $pages = $this->workspace->pages();
        unlink($pages . '/page-0002.json');

        $this->expectException(SourceError::class);
        $this->expectExceptionMessage('Page enregistrée absente : page-0002.json');
        $this->read($pages);
    }

    public function testADayIsGivenInParisTime(): void
    {
        self::assertSame('2026-11-15', Catalogue::day('2026-11-14T23:30:00+00:00'));
        self::assertSame('2026-07-01', Catalogue::day('2026-06-30T22:00:00.000000+00:00'));
        self::assertNull(Catalogue::day('hier'));
        self::assertNull(Catalogue::day(null));
    }

    /**
     * @return Reading
     */
    private function read(string $pages): array
    {
        return (new Catalogue(new ReplayClient($pages), Sources::load(), static function (int $seconds): void {}))->read();
    }

    private function api(string $suffix, string $pages = Workspace::PAGES): Api
    {
        foreach ($this->read($pages)['apis'] as $api) {
            if ($api->id === 'aaaa0000000000000000' . $suffix) {
                return $api;
            }
        }

        self::fail('API absente de la lecture : ' . $suffix);
    }
}
