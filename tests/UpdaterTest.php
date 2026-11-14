<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Tests;

use PHPUnit\Framework\TestCase;
use PolygonLab\ApisPubliquesFrance\Outils\DataFiles;
use PolygonLab\ApisPubliquesFrance\Outils\ForbiddenWords;
use PolygonLab\ApisPubliquesFrance\Outils\JsonFile;
use PolygonLab\ApisPubliquesFrance\Outils\Screening;
use PolygonLab\ApisPubliquesFrance\Outils\SourceError;
use PolygonLab\ApisPubliquesFrance\Outils\Sources;
use PolygonLab\ApisPubliquesFrance\Outils\TestClass;
use PolygonLab\ApisPubliquesFrance\Outils\Updater;
use PolygonLab\ApisPubliquesFrance\Tests\Fixtures\Apis;
use PolygonLab\ApisPubliquesFrance\Tests\Fixtures\Workspace;

/**
 * La comparaison d'une collecte aux données en place : première lecture, catalogue inchangé, événements de
 * l'historique, disponibilité observée, garde-fous (liste vide, disparitions, changements de test).
 *
 * @phpstan-import-type Collected from Updater
 */
final class UpdaterTest extends TestCase
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

    public function testTheFirstReadingFillsEveryFile(): void
    {
        $result = $this->update(Apis::collected([Apis::make(1), Apis::make(2, TestClass::UNREACHABLE, 'restreint')]), '2026-11-18');

        self::assertTrue($result['changed']);
        self::assertSame([DataFiles::APIS_JSON, DataFiles::APIS_CSV, DataFiles::HOSTS_JSON, DataFiles::HOSTS_CSV, DataFiles::HISTORY_CSV, DataFiles::PASSES_CSV, DataFiles::SOURCE_JSON], array_keys($result['files']));
        $summary = $result['summary'];
        self::assertSame(1, $summary['format']);
        self::assertTrue($summary['change']);
        self::assertTrue($summary['premiere_lecture']);
        self::assertSame(0, $summary['avant']);
        self::assertSame(2, $summary['apres']);
        self::assertSame(0, $summary['retraits']);
        self::assertSame('API', $summary['unite']);
        self::assertSame([['cle' => 'apis', 'libelle' => 'API publiques françaises', 'nombre' => 2]], $summary['listes']);
        self::assertSame('Première lecture du catalogue du 18/11/2026 : 2 API, 1 hôte', $summary['titre']);
        self::assertStringContainsString(";entree;essai-0001;API d'essai n° 1;Organisation d'essai, accès ouvert, test repond\n", $this->workspace->read(DataFiles::HISTORY_CSV));
        self::assertSame("\u{FEFF}date;id;resultat\n2026-11-18;essai-0001;repond\n2026-11-18;essai-0002;injoignable\n", $this->workspace->read(DataFiles::PASSES_CSV));
        $source = JsonFile::decode($this->workspace->read(DataFiles::SOURCE_JSON), 'source.json');
        self::assertSame('2026-11-18', $source['date_source']);
        self::assertSame('2026-11-18T06:40:00+01:00', $source['lu_le']);
        self::assertIsArray($source['fichiers']);
        self::assertIsArray($source['fichiers'][0]);
        self::assertSame('catalogue', $source['fichiers'][0]['cle']);
        self::assertSame(2, $source['fichiers'][0]['adresses_testees']);
        self::assertSame(12, $source['fichiers'][0]['duree_test_s']);
        self::assertStringContainsString('Catalogue du 18/11/2026 : 2 API', $result['readme']);
        self::assertStringContainsString('## Organisation d\'essai (2)', $result['list']);
    }

    public function testTheSameCatalogueChangesNothing(): void
    {
        $apis = [Apis::make(1), Apis::make(2)];
        $this->update(Apis::collected($apis), '2026-11-18');

        $result = $this->update(Apis::collected($apis), '2026-11-25');

        self::assertFalse($result['changed']);
        self::assertSame([], $result['files']);
        self::assertFalse($result['summary']['change']);
        self::assertSame('Catalogue du 25/11/2026 : aucun changement', $result['summary']['titre']);
    }

    public function testDatesAndHttpCodesAloneChangeNothing(): void
    {
        $this->update(Apis::collected([Apis::make(1, httpCode: 200, modifiedOn: '2026-10-20')]), '2026-11-18');

        $result = $this->update(Apis::collected([Apis::make(1, httpCode: 204, modifiedOn: '2026-11-24')]), '2026-11-25');

        self::assertFalse($result['changed'], 'ni une date de modification ni un code HTTP de la même classe ne donnent une version');
    }

    public function testEveryChangeGivesItsEvent(): void
    {
        $this->update(Apis::collected([
            Apis::make(1),
            Apis::make(2),
            Apis::make(3),
            Apis::make(4),
            Apis::make(5),
            Apis::make(6),
        ]), '2026-11-18');

        $result = $this->update(Apis::collected([
            Apis::make(1),
            Apis::make(2, TestClass::UNREACHABLE),
            Apis::make(3, access: 'restreint'),
            Apis::make(4, description: 'Nouvelle description.'),
            Apis::make(5),
            Apis::make(7, TestClass::DENIED, 'ouvert_avec_compte', null),
        ]), '2026-11-25');

        self::assertTrue($result['changed']);
        $history = $this->workspace->read(DataFiles::HISTORY_CSV);
        self::assertStringContainsString("2026-11-25;changement_test;essai-0002;API d'essai n° 2;repond -> injoignable\n", $history);
        self::assertStringContainsString("2026-11-25;changement_acces;essai-0003;API d'essai n° 3;ouvert -> restreint\n", $history);
        self::assertStringContainsString("2026-11-25;entree;essai-0007;API d'essai n° 7;sans organisation, accès ouvert_avec_compte, test acces_refuse\n", $history);
        self::assertStringContainsString("2026-11-25;sortie;essai-0006;API d'essai n° 6;absente du catalogue\n", $history);
        self::assertSame(6 + 4, substr_count($history, "\n") - 1, 'l\'historique ne fait que s\'allonger');
        $summary = $result['summary'];
        self::assertSame('Catalogue du 25/11/2026 : 1 entrée, 1 sortie, 1 test changé, 1 accès changé, 1 autre modification', $summary['titre']);
        self::assertSame(6, $summary['avant']);
        self::assertSame(6, $summary['apres']);
        self::assertSame(1, $summary['retraits']);
        self::assertSame(['Entrées', 'Sorties', 'Changements de résultat de test', 'Changements de type d\'accès', 'Autres modifications', 'Listes'], array_column(self::sections($summary), 'titre'));
        self::assertIsString($summary['texte']);
        self::assertStringContainsString("Autres modifications\n- API d'essai n° 4 : description\n", $summary['texte']);
        self::assertStringContainsString("- Test injoignable : 1\n", $summary['texte'] . "\n");
    }

    public function testADiscardedApiIsNotedOnceAndLeavesTheList(): void
    {
        $this->update(Apis::collected([Apis::make(1), Apis::make(2), Apis::make(3), Apis::make(4), Apis::make(5), Apis::make(6)]), '2026-11-18');

        $changed = [Apis::make(1), Apis::make(2, title: 'API ' . Workspace::FORBIDDEN_WORD), Apis::make(3), Apis::make(4), Apis::make(5), Apis::make(6), Apis::make(7, description: 'Serveur 192.0.2.44.')];
        $result = $this->update(Apis::collected($changed), '2026-11-25');

        $history = $this->workspace->read(DataFiles::HISTORY_CSV);
        self::assertStringContainsString('2026-11-25;ecartee;essai-0002;[titre retiré ' . substr(hash('sha256', 'essai-0002'), 0, 8) . "];écartée : mot interdit (champ « titre »)\n", $history);
        self::assertStringContainsString("2026-11-25;ecartee;essai-0007;API d'essai n° 7;écartée : adresse IPv4 (champ « description »)\n", $history);
        self::assertStringNotContainsString(Workspace::FORBIDDEN_WORD, implode('', $result['files']) . $result['readme'] . $result['list'] . (string) json_encode($result['summary']));
        self::assertSame(1, $result['summary']['retraits']);
        self::assertSame(5, $result['summary']['apres']);
        self::assertSame('Catalogue du 25/11/2026 : 2 écartées', $result['summary']['titre']);
        self::assertSame(['essai-0002', 'essai-0007'], DataFiles::discardedIds($history));

        $again = $this->update(Apis::collected($changed), '2026-12-02');
        self::assertFalse($again['changed'], 'une API déjà écartée n\'est pas notée une seconde fois');
    }

    public function testEmailRemovalsAreReportedForNewOrChangedApis(): void
    {
        $emails = ['essai-0001' => ['description', 'limites']];
        $first = $this->update(Apis::collected(Apis::range(1, 9), $emails), '2026-11-18');
        self::assertIsString($first['summary']['texte']);
        self::assertStringContainsString("Remarques\n- Adresse e-mail retirée (description, limites) : API d'essai n° 1\n", $first['summary']['texte']);

        $second = $this->update(Apis::collected([Apis::make(1), Apis::make(2, TestClass::DENIED), ...Apis::range(3, 9)], $emails), '2026-11-25');
        self::assertTrue($second['changed']);
        self::assertIsString($second['summary']['texte']);
        self::assertStringNotContainsString('Remarques', $second['summary']['texte'], 'la remarque n\'est pas répétée à chaque passage');
    }

    public function testObservedAvailabilityAppearsAtTheThirdPass(): void
    {
        $stable = Apis::range(2, 9);
        $this->update(Apis::collected([Apis::make(1), ...$stable]), '2026-11-04');
        $this->update(Apis::collected([Apis::make(1, TestClass::UNREACHABLE), ...$stable]), '2026-11-11');
        self::assertNull(self::record($this->workspace->read(DataFiles::APIS_JSON), 'essai-0001')['disponibilite_observee']);

        $this->update(Apis::collected([Apis::make(1, TestClass::DENIED), ...$stable]), '2026-11-18');

        self::assertSame(0.67, self::record($this->workspace->read(DataFiles::APIS_JSON), 'essai-0001')['disponibilite_observee']);
        self::assertSame(1.0, self::record($this->workspace->read(DataFiles::APIS_JSON), 'essai-0002')['disponibilite_observee']);
        self::assertStringContainsString(';0.67;', $this->workspace->read(DataFiles::APIS_CSV));
    }

    public function testOnlyTheLastEightPassesAreKept(): void
    {
        $stable = Apis::range(2, 9);
        for ($week = 1; $week <= 10; $week++) {
            $this->update(Apis::collected([Apis::make(1, $week % 2 === 0 ? TestClass::RESPONDS : TestClass::SERVER_ERROR), ...$stable]), sprintf('2026-09-%02d', $week));
        }

        $passes = DataFiles::readPasses($this->workspace->read(DataFiles::PASSES_CSV));

        self::assertCount(8, $passes);
        self::assertSame('2026-09-03', array_key_first($passes));
        self::assertSame(0.5, self::record($this->workspace->read(DataFiles::APIS_JSON), 'essai-0001')['disponibilite_observee']);
    }

    public function testAnEmptyListStopsTheUpdate(): void
    {
        $this->expectException(SourceError::class);
        $this->expectExceptionMessage('Liste vide : aucune API publiable parmi les 1 lues dans le catalogue (1 écartée(s)).');
        $this->update(Apis::collected([Apis::make(1, title: 'API ' . Workspace::FORBIDDEN_WORD)]), '2026-11-18');
    }

    public function testMoreThanTwentyPercentOfExitsStopTheUpdate(): void
    {
        $this->update(Apis::collected(Apis::range(1, 10)), '2026-11-18');
        $before = $this->workspace->state();

        $twoExits = $this->update(Apis::collected(Apis::range(1, 8)), '2026-11-25', false);
        self::assertTrue($twoExits['changed'], '20 % exactement : accepté');

        try {
            $this->update(Apis::collected(Apis::range(1, 7)), '2026-11-25');
            self::fail('garde-fou non déclenché');
        } catch (SourceError $error) {
            self::assertSame('Garde-fou : 3 API sur 10 disparaissent d\'un coup (30 %, seuil 20 %).', $error->getMessage());
        }
        self::assertSame($before, $this->workspace->state());
    }

    public function testMoreThanAQuarterOfTestChangesStopTheUpdate(): void
    {
        $this->update(Apis::collected(Apis::range(1, 8)), '2026-11-18');

        $two = $this->update(Apis::collected([...Apis::range(1, 2, TestClass::UNREACHABLE), ...Apis::range(3, 8)]), '2026-11-25', false);
        self::assertTrue($two['changed'], '25 % exactement : accepté');

        $this->expectException(SourceError::class);
        $this->expectExceptionMessage('Garde-fou : 3 API sur 8 changent de résultat de test d\'un passage à l\'autre (37 %, seuil 25 %)');
        $this->update(Apis::collected([...Apis::range(1, 3, TestClass::UNREACHABLE), ...Apis::range(4, 8)]), '2026-11-25');
    }

    public function testALongListOfChangesIsCutInTheSummaryAndTheTitleStaysShort(): void
    {
        $this->update(Apis::collected(Apis::range(1, 1000)), '2026-11-18');

        $next = [
            ...Apis::range(1, 878),
            ...Apis::range(881, 1000, TestClass::SERVER_ERROR),
            ...Apis::range(1001, 1150),
            Apis::make(1151, title: 'API ' . Workspace::FORBIDDEN_WORD . ' 1'),
            Apis::make(1152, title: 'API ' . Workspace::FORBIDDEN_WORD . ' 2'),
        ];
        foreach ([1, 2, 3] as $number) {
            $next[$number - 1] = Apis::make($number, access: 'restreint', description: 'Autre.');
        }
        $result = $this->update(Apis::collected($next), '2026-11-25');

        $summary = $result['summary'];
        self::assertSame('Catalogue du 25/11/2026 : 280 changements', $summary['titre'], 'titre trop long : remplacé par le nombre de changements');
        $sections = self::sections($summary);
        self::assertSame('Entrées (150)', $sections[0]['titre']);
        self::assertCount(101, $sections[0]['elements']);
        self::assertSame('… et 50 autres : voir data/historique.csv', $sections[0]['elements'][100]);
        self::assertSame(150 + 2 + 120 + 3 + 2, substr_count($this->workspace->read(DataFiles::HISTORY_CSV), "\n2026-11-25;"), 'l\'historique, lui, garde tous les événements');
    }

    public function testEmptyFilesAreValidAndEmpty(): void
    {
        $files = Updater::emptyFiles(Sources::load());

        self::assertSame([], DataFiles::readApis($files[DataFiles::APIS_JSON]));
        self::assertSame(DataFiles::historyHeader(), $files[DataFiles::HISTORY_CSV]);
        self::assertSame(DataFiles::passesHeader(), $files[DataFiles::PASSES_CSV]);
        self::assertSame([], JsonFile::decode($files[DataFiles::SOURCE_JSON], 'source.json')['fichiers']);
    }

    /**
     * Lance la comparaison et, comme la commande, écrit les fichiers rendus dans le dossier d'essai.
     *
     * @param Collected $collected
     *
     * @return array{changed: bool, files: array<string, string>, readme: string, list: string, summary: array<string, mixed>}
     */
    private function update(array $collected, string $day, bool $write = true): array
    {
        $screening = new Screening(ForbiddenWords::load($this->workspace->forbiddenWords));
        $result = (new Updater(Sources::load(), $screening, $this->workspace->data))->run($collected, new \DateTimeImmutable($day . 'T06:40:00+01:00'));
        if ($write) {
            foreach ($result['files'] as $name => $content) {
                file_put_contents($this->workspace->data . '/' . $name, $content);
            }
        }

        return $result;
    }

    /**
     * @return array<mixed>
     */
    private static function record(string $json, string $id): array
    {
        $list = JsonFile::decode($json, 'apis.json')['apis'];
        self::assertIsArray($list);
        foreach ($list as $record) {
            if (\is_array($record) && ($record['id'] ?? null) === $id) {
                return $record;
            }
        }

        self::fail('API absente de apis.json : ' . $id);
    }

    /**
     * @param array<string, mixed> $summary
     *
     * @return list<array{titre: string, elements: list<string>}>
     */
    private static function sections(array $summary): array
    {
        $sections = [];
        self::assertIsArray($summary['sections']);
        foreach ($summary['sections'] as $section) {
            self::assertIsArray($section);
            self::assertIsString($section['titre']);
            self::assertIsArray($section['elements']);
            $elements = [];
            foreach ($section['elements'] as $element) {
                self::assertIsString($element);
                $elements[] = $element;
            }
            $sections[] = ['titre' => $section['titre'], 'elements' => $elements];
        }

        return $sections;
    }
}
