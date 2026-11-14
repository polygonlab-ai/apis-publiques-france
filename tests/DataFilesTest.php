<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Tests;

use PHPUnit\Framework\TestCase;
use PolygonLab\ApisPubliquesFrance\Outils\Api;
use PolygonLab\ApisPubliquesFrance\Outils\DataFiles;
use PolygonLab\ApisPubliquesFrance\Outils\JsonFile;
use PolygonLab\ApisPubliquesFrance\Outils\ListPage;
use PolygonLab\ApisPubliquesFrance\Outils\Readme;
use PolygonLab\ApisPubliquesFrance\Outils\SourceError;
use PolygonLab\ApisPubliquesFrance\Outils\Sources;
use PolygonLab\ApisPubliquesFrance\Outils\TestClass;
use PolygonLab\ApisPubliquesFrance\Tests\Fixtures\Apis;

/**
 * Les fichiers de data/ et les pages générées : rendu des API et des hôtes, passages et disponibilité observée,
 * historique, pages du README et de LISTE.md.
 */
final class DataFilesTest extends TestCase
{
    public function testAnApiIsPublishedWithItsTest(): void
    {
        $list = JsonFile::decode(DataFiles::apisJson(Sources::load(), '2026-11-18', [Apis::make(1, TestClass::DENIED, 'restreint', httpCode: 401)], ['essai-0001' => 0.88]), 'apis.json');

        self::assertSame(1, $list['nombre']);
        self::assertSame(1, $list['nombre_hotes']);
        self::assertSame('2026-11-18', $list['date_source']);
        self::assertIsArray($list['nombres']);
        self::assertSame(['ouvert' => 0, 'ouvert_avec_compte' => 0, 'restreint' => 1, 'non_precise' => 0], $list['nombres']['acces']);
        $tests = array_fill_keys(TestClass::ALL, 0);
        $tests[TestClass::DENIED] = 1;
        self::assertSame($tests, $list['nombres']['test']);
        self::assertIsArray($list['apis']);
        $record = $list['apis'][0];
        self::assertIsArray($record);
        self::assertSame(DataFiles::APIS_COLUMNS, [...\array_slice(array_keys($record), 0, 20), 'test_resultat', 'test_code_http', 'test_https', 'teste_le'], 'mêmes champs, dans le même ordre, dans le JSON et le CSV');
        self::assertSame(['resultat' => 'acces_refuse', 'code_http' => 401, 'https' => true, 'teste_le' => '2026-11-18'], $record['test']);
        self::assertSame(0.88, $record['disponibilite_observee']);
        self::assertSame('api.example.com', $record['hote']);
    }

    public function testAnUntestedApiHasNoTestDate(): void
    {
        $list = JsonFile::decode(DataFiles::apisJson(Sources::load(), '2026-11-18', [Apis::make(1, TestClass::UNTESTED)], []), 'apis.json');

        self::assertIsArray($list['apis']);
        self::assertIsArray($list['apis'][0]);
        self::assertSame(['resultat' => 'non_teste', 'code_http' => null, 'https' => true, 'teste_le' => null], $list['apis'][0]['test']);
        self::assertNull($list['apis'][0]['disponibilite_observee']);
    }

    public function testApisAreSortedByTitleThenIdentifier(): void
    {
        $apis = [Apis::make(3, title: 'Zèbre'), Apis::make(2, title: 'école'), Apis::make(1, title: 'Zèbre'), Apis::make(4, title: 'Abeille')];

        self::assertSame(['essai-0004', 'essai-0002', 'essai-0001', 'essai-0003'], array_map(static fn(Api $api): string => $api->id, DataFiles::sorted($apis)));
    }

    public function testTheCsvFileIsReadableBackAndQuotesWhatNeedsIt(): void
    {
        $csv = DataFiles::apisCsv('2026-11-18', [Apis::make(1, description: 'Une « API » ; avec "guillemets"')], []);

        self::assertStringStartsWith(DataFiles::BOM . 'id;titre;description;', $csv);
        $lines = explode("\n", trim(substr($csv, \strlen(DataFiles::BOM))));
        self::assertCount(2, $lines);
        $cells = str_getcsv($lines[1], DataFiles::SEPARATOR, '"', '');
        self::assertCount(\count(DataFiles::APIS_COLUMNS), $cells);
        self::assertSame('Une « API » ; avec "guillemets"', $cells[2]);
        self::assertSame(['repond', '200', 'oui', '2026-11-18'], \array_slice($cells, 20));
    }

    public function testHostsGiveTheirBestResultAndTheirCounts(): void
    {
        $hosts = DataFiles::hosts([
            ...Apis::range(1, 3, TestClass::UNREACHABLE, 'portail.example.org'),
            Apis::make(4, TestClass::DENIED, host: 'portail.example.org', organisation: 'Ville d\'essai'),
            Apis::make(5, TestClass::RESPONDS, host: 'portail.example.org', organisation: null),
            Apis::make(6, TestClass::SERVER_ERROR, host: 'api.example.com'),
            Apis::make(7, TestClass::NOT_FOUND, host: 'api.example.com'),
        ]);

        self::assertSame([
            ['hote' => 'portail.example.org', 'nombre_apis' => 5, 'organisations' => ['Organisation d\'essai', 'Ville d\'essai'], 'resultat' => 'repond', 'nombre_qui_repondent' => 1],
            ['hote' => 'api.example.com', 'nombre_apis' => 2, 'organisations' => ['Organisation d\'essai'], 'resultat' => 'introuvable', 'nombre_qui_repondent' => 0],
        ], $hosts);
        self::assertStringContainsString("portail.example.org;5;Organisation d'essai | Ville d'essai;repond;1\n", DataFiles::hostsCsv([...Apis::range(1, 3, TestClass::UNREACHABLE, 'portail.example.org'), Apis::make(4, TestClass::DENIED, host: 'portail.example.org', organisation: 'Ville d\'essai'), Apis::make(5, host: 'portail.example.org', organisation: null)]));
    }

    public function testPassesKeepTheLastEightAndReplaceTheSameDay(): void
    {
        $passes = [];
        for ($day = 1; $day <= 10; $day++) {
            $passes = DataFiles::withPass($passes, sprintf('2026-11-%02d', $day), [Apis::make(1)], 8);
        }
        self::assertSame(['2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07', '2026-11-08', '2026-11-09', '2026-11-10'], array_keys($passes));

        $passes = DataFiles::withPass($passes, '2026-11-10', [Apis::make(1, TestClass::UNREACHABLE), Apis::make(2)], 8);
        self::assertCount(8, $passes);
        self::assertSame(['essai-0001' => 'injoignable', 'essai-0002' => 'repond'], $passes['2026-11-10']);

        self::assertSame($passes, DataFiles::readPasses(DataFiles::passesCsv($passes)), 'le fichier des passages se relit à l\'identique');
    }

    public function testObservedAvailabilityNeedsThreeTestedPasses(): void
    {
        $passes = [
            '2026-11-01' => ['a' => 'repond', 'b' => 'repond', 'c' => 'non_teste', 'd' => 'injoignable'],
            '2026-11-08' => ['a' => 'acces_refuse', 'b' => 'erreur_serveur', 'c' => 'repond', 'd' => 'introuvable'],
        ];
        self::assertSame([], DataFiles::availability($passes, 3), 'moins de trois passages : aucune valeur');

        $passes['2026-11-15'] = ['a' => 'injoignable', 'b' => 'repond', 'c' => 'repond', 'd' => 'erreur_client', 'e' => 'repond'];
        self::assertSame(['a' => 0.67, 'b' => 0.67, 'd' => 0.0], DataFiles::availability($passes, 3), '« repond » et « acces_refuse » comptent ; une API non testée ou trop récente n\'a pas de valeur');
    }

    public function testAnUnexpectedPassesFileIsRefused(): void
    {
        $this->expectException(SourceError::class);
        DataFiles::readPasses(DataFiles::passesHeader() . "2026-11-01;a;parfait\n");
    }

    public function testHistoryOnlyGrowsAndGivesTheDiscardedApis(): void
    {
        $history = DataFiles::appendHistory('', [
            ['date' => '2026-11-01', 'evenement' => DataFiles::EVENT_DISCARDED, 'id' => 'a', 'titre' => 'API a', 'detail' => 'écartée : mot interdit'],
            ['date' => '2026-11-01', 'evenement' => DataFiles::EVENT_DISCARDED, 'id' => 'b', 'titre' => 'API ; b', 'detail' => 'écartée : adresse IPv4'],
        ]);
        $history = DataFiles::appendHistory($history, [['date' => '2026-11-08', 'evenement' => DataFiles::EVENT_ENTRY, 'id' => 'b', 'titre' => 'API ; b', 'detail' => 'entrée']]);

        self::assertStringStartsWith(DataFiles::historyHeader(), $history);
        self::assertSame(3, substr_count($history, "\n") - 1);
        self::assertSame(['a'], DataFiles::discardedIds($history));

        $this->expectException(SourceError::class);
        DataFiles::appendHistory("autre;en-tête\n", []);
    }

    public function testNumbersAreWrittenShort(): void
    {
        self::assertSame('99.9', DataFiles::number(99.9));
        self::assertSame('100', DataFiles::number(100.0));
        self::assertSame('0.67', DataFiles::number(0.67));
        self::assertSame('0', DataFiles::number(0.0));
        self::assertNull(DataFiles::number(null));
    }

    public function testTheReadmeBlockGivesTheFigures(): void
    {
        $apis = [
            ...Apis::range(1, 3),
            Apis::make(4, TestClass::UNREACHABLE, 'restreint', 'Ville d\'essai | <b>', 'portail.example.org'),
            Apis::make(5, TestClass::UNTESTED, null, null),
        ];

        $block = Readme::block('2026-11-18', $apis);

        self::assertStringContainsString('Catalogue du 18/11/2026 : 5 API, 2 hôtes, 2 organisations.', $block);
        self::assertStringContainsString('| Ouvert | `ouvert` | 3 |', $block);
        self::assertStringContainsString('| Restreint | `restreint` | 1 |', $block);
        self::assertStringContainsString('| Non précisé | `non_precise` | 1 |', $block);
        self::assertStringNotContainsString('`ouvert_avec_compte`', $block);
        self::assertStringContainsString('| Répond | `repond` | 3 |', $block);
        self::assertStringContainsString('| Erreur du serveur | `erreur_serveur` | 0 |', $block);
        self::assertStringContainsString("| Organisation d'essai | 3 |\n| Ville d'essai \\| &lt;b&gt; | 1 |", $block, 'organisations classées, nom échappé');
    }

    public function testTheReadmeBlockListsAtMostTwentyOrganisations(): void
    {
        $apis = [];
        for ($number = 1; $number <= 25; $number++) {
            $apis[] = Apis::make($number, organisation: sprintf('Organisation %02d', $number));
        }
        $apis[] = Apis::make(26, organisation: 'Organisation 25');

        $block = Readme::block('2026-11-18', $apis);

        self::assertStringContainsString('Les 20 organisations qui publient le plus d\'API', $block);
        self::assertSame(20, preg_match_all('/^\| Organisation \d\d \|/m', $block));
        self::assertStringContainsString("|---|---:|\n| Organisation 25 | 2 |\n| Organisation 01 | 1 |", $block);
    }

    public function testTheReadmeIsOnlyRewrittenBetweenItsTags(): void
    {
        $readme = "Avant\n" . Readme::START . "\nancien\n" . Readme::END . "\nAprès\n";

        self::assertSame("Avant\n" . Readme::START . "\nnouveau\n" . Readme::END . "\nAprès\n", Readme::replace($readme, "\nnouveau\n"));

        $this->expectException(SourceError::class);
        Readme::replace('Sans balise', "\nnouveau\n");
    }

    public function testTheListPageGroupsByOrganisationAndFoldsPortals(): void
    {
        $apis = [
            ...Apis::range(1, 31, TestClass::RESPONDS, 'portail.example.org'),
            Apis::make(32, TestClass::UNREACHABLE, host: 'portail.example.org'),
            Apis::make(33, TestClass::DENIED, 'restreint', 'Agence d\'essai', 'portail.example.org', 'API [de] l\'agence'),
            Apis::make(34, TestClass::NOT_FOUND, 'ouvert_avec_compte', 'Agence d\'essai', 'api.example.com', 'Service de l\'agence'),
            Apis::make(35, TestClass::RESPONDS, null, null, 'seul.example.net', 'API sans organisation'),
        ];

        $page = ListPage::render('2026-11-18', $apis);

        self::assertStringStartsWith('# Liste des API par organisation', $page);
        self::assertStringContainsString('Catalogue du 18/11/2026 : 35 API, 2 organisations.', $page);
        self::assertStringContainsString("## Agence d'essai (2)\n\n| API | Accès | Test |\n|---|---|---|\n| [Service de l'agence](https://www.data.gouv.fr/dataservices/essai-0034/) | Ouvert avec compte | `introuvable` |\n| 1 API sur `portail.example.org` : voir [`data/apis.csv`](data/apis.csv) | | 0 sur 1 répondent |", $page);
        self::assertStringContainsString("## Organisation d'essai (32)\n\n| API | Accès | Test |\n|---|---|---|\n| 32 API sur `portail.example.org` : voir [`data/apis.csv`](data/apis.csv) | | 31 sur 32 répondent |", $page);
        self::assertStringContainsString("## Sans organisation (1)\n\n| API | Accès | Test |\n|---|---|---|\n| [API sans organisation](https://www.data.gouv.fr/dataservices/essai-0035/) | Non précisé | `repond` |", $page);
        self::assertStringNotContainsString('essai-0001', $page, 'portail de plus de 30 API : replié');
        self::assertGreaterThan(strpos($page, '## Organisation d\'essai'), strpos($page, '## Sans organisation'), 'les API sans organisation viennent en dernier');
    }

    public function testAPortalOfThirtyApisIsNotFolded(): void
    {
        $page = ListPage::render('2026-11-18', Apis::range(1, 30, TestClass::RESPONDS, 'portail.example.org'));

        self::assertSame(30, substr_count($page, '](https://www.data.gouv.fr/dataservices/essai-'));
        self::assertStringNotContainsString('API sur `portail.example.org`', $page);
    }

    public function testEmptyPagesSayThatTheListIsComing(): void
    {
        self::assertStringContainsString('après la première lecture du catalogue', Readme::block(null, []));
        self::assertStringContainsString('après la première lecture du catalogue', ListPage::render(null, []));
    }
}
