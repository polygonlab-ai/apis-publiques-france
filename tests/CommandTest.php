<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Tests;

use PHPUnit\Framework\TestCase;
use PolygonLab\ApisPubliquesFrance\Outils\Command;
use PolygonLab\ApisPubliquesFrance\Outils\DataFiles;
use PolygonLab\ApisPubliquesFrance\Outils\JsonFile;
use PolygonLab\ApisPubliquesFrance\Outils\Screening;
use PolygonLab\ApisPubliquesFrance\Outils\Text;
use PolygonLab\ApisPubliquesFrance\Tests\Fixtures\Workspace;

/**
 * La commande d'actualisation, sur une collecte rejouée (fausses pages du catalogue et faux serveurs, aucun appel
 * réseau) : usage, collecte et manifeste, empreinte, actualisation, garde-fous, rien d'écrit en cas de refus.
 */
final class CommandTest extends TestCase
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

    public function testInvalidArgumentsGiveTheUsage(): void
    {
        foreach ([['--inconnu'], ['--donnees'], ['--resume='], ['--collecter', $this->workspace->path . '/collecte'], ['--donnees', $this->workspace->path . '/absent']] as $arguments) {
            [$code, , $errors] = $this->command($arguments, false);
            self::assertSame(Command::USAGE, $code, implode(' ', $arguments));
            self::assertStringContainsString('Usage : php tools/actualiser.php', $errors);
        }
        [$code, $output] = $this->command(['--aide'], false);
        self::assertSame(Command::SUCCESS, $code);
        self::assertStringContainsString('--mots-interdits', $output);
    }

    public function testTheFirstRunWritesTheDataTheListAndTheFigures(): void
    {
        [$code, $output, $errors] = $this->command(['--pages', Workspace::PAGES, '--mots-interdits', $this->workspace->forbiddenWords]);

        self::assertSame(Command::SUCCESS, $code, $errors);
        self::assertStringContainsString('Collecte : 2 page(s), 19 entrée(s) lue(s), 3 écartée(s) à la lecture, 15 API, 17 adresse(s) testée(s)', $output);
        self::assertStringContainsString('Première lecture du catalogue du 18/11/2026 : 13 API, 8 hôtes', $output);
        self::assertStringContainsString('Fichiers de data/, LISTE.md et chiffres du README réécrits.', $output);

        $apis = DataFiles::readApis($this->workspace->read(DataFiles::APIS_JSON));
        self::assertCount(13, $apis);
        $tests = [];
        foreach ($apis as $id => $api) {
            $tests[substr($id, -4)] = [$api->test, $api->httpCode, $api->documentationTest];
        }
        ksort($tests);
        self::assertSame([
            'a001' => ['repond', 200, 'openapi'],
            'a002' => ['acces_refuse', 401, 'openapi'],
            'a003' => ['introuvable', 404, 'absente'],
            'a004' => ['non_teste', null, 'absente'],
            'a005' => ['repond', 200, 'absente'],
            'a006' => ['erreur_serveur', 503, 'autre'],
            'a007' => ['erreur_client', 429, 'injoignable'],
            'a008' => ['injoignable', null, 'absente'],
            'a009' => ['non_teste', null, 'absente'],
            'a010' => ['non_teste', null, 'absente'],
            'a014' => ['repond', 200, 'openapi'],
            'a015' => ['repond', 200, 'openapi'],
            'a018' => ['acces_refuse', 403, 'absente'],
        ], $tests);

        $history = $this->workspace->read(DataFiles::HISTORY_CSV);
        self::assertStringContainsString(';ecartee;aaaa0000000000000000a016;[titre retiré ', $history);
        self::assertStringContainsString(';ecartee;aaaa0000000000000000a017;API avec adresse chiffrée;écartée : adresse IPv4 (champ « description »)', $history);
        self::assertStringContainsString("api.example.com;4;Institut d'essai des statistiques | Institut d'essai géographique | Ville d'essai;repond;1\n", $this->workspace->read(DataFiles::HOSTS_CSV));
        self::assertStringContainsString('Catalogue du 18/11/2026 : 13 API, 8 hôtes, 3 organisations.', (string) file_get_contents($this->workspace->readme));
        self::assertStringContainsString("## Sans organisation (1)\n\n| API | Accès | Test |\n|---|---|---|\n| [Annuaire d'essai](https://www.data.gouv.fr/dataservices/essai-a003/) | Ouvert avec compte | `introuvable` |", (string) file_get_contents($this->workspace->list));
        self::assertSame([], glob($this->workspace->data . '/.*.tmp'), 'aucun fichier temporaire laissé');
    }

    public function testASecondRunOnTheSameCatalogueChangesNothing(): void
    {
        $this->command(['--pages', Workspace::PAGES, '--mots-interdits', $this->workspace->forbiddenWords]);
        $before = $this->workspace->state();

        [$code, $output] = $this->command(['--pages', Workspace::PAGES, '--mots-interdits', $this->workspace->forbiddenWords], true, '2026-11-25');

        self::assertSame(Command::SUCCESS, $code);
        self::assertStringContainsString('Catalogue inchangé : aucun fichier modifié.', $output);
        self::assertSame($before, $this->workspace->state());
    }

    public function testNoEmailAddressAndNoContactIsEverWritten(): void
    {
        $this->command(['--pages', Workspace::PAGES]);

        $written = '';
        foreach ([...(glob($this->workspace->data . '/*') ?: []), $this->workspace->readme, $this->workspace->list] as $file) {
            $written .= (string) file_get_contents($file);
        }
        self::assertDoesNotMatchRegularExpression(Text::EMAIL, $written);
        self::assertStringContainsString(Text::EMAIL_REPLACEMENT, $written);
        foreach (['Équipe d\'essai', 'Camille', 'followers'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $written);
        }
    }

    public function testCollectThenUpdateFromTheManifest(): void
    {
        $directory = $this->workspace->path . '/collecte';
        $manifest = $this->workspace->path . '/manifeste.json';
        $before = $this->workspace->state();

        [$code, $output, $errors] = $this->command(['--collecter', $directory, '--manifeste', $manifest, '--pages', Workspace::PAGES], false);

        self::assertSame(Command::SUCCESS, $code, $errors);
        self::assertStringContainsString('Manifeste écrit : ' . $manifest, $output);
        self::assertSame($before, $this->workspace->state(), 'la collecte seule ne touche pas aux données');
        $entry = self::manifestEntry($manifest);
        self::assertSame('catalogue', $entry['cle']);
        self::assertSame('https://www.data.gouv.fr/api/1/dataservices/', $entry['url']);
        self::assertSame($directory . '/' . Command::COLLECTED_FILE, $entry['chemin']);
        self::assertSame(hash_file('sha256', $directory . '/' . Command::COLLECTED_FILE), $entry['sha256']);
        $collected = (string) file_get_contents($directory . '/' . Command::COLLECTED_FILE);
        foreach (['cree_le', 'modifie_le', 'code_http', 'teste_le', '2026-'] as $volatile) {
            self::assertStringNotContainsString($volatile, $collected, 'le fichier qui porte l\'empreinte ne contient ni date ni code HTTP');
        }
        self::assertDoesNotMatchRegularExpression(Text::EMAIL, $collected . (string) file_get_contents($directory . '/' . Command::COMPLEMENTS_FILE));

        [$code, $output, $errors] = $this->command(['--manifeste', $manifest, '--resume', $this->workspace->path . '/resume.json', '--mots-interdits', $this->workspace->forbiddenWords]);

        self::assertSame(Command::SUCCESS, $code, $errors);
        $summary = JsonFile::read($this->workspace->path . '/resume.json', 'résumé');
        self::assertSame(1, $summary['format']);
        self::assertTrue($summary['change']);
        self::assertSame(13, $summary['apres']);
        $source = JsonFile::decode($this->workspace->read(DataFiles::SOURCE_JSON), 'source.json');
        self::assertIsArray($source['fichiers']);
        self::assertIsArray($source['fichiers'][0]);
        self::assertSame($entry['sha256'], $source['fichiers'][0]['sha256'], 'data/source.json garde l\'empreinte de la collecte');
        self::assertSame(2, $source['fichiers'][0]['pages']);
        self::assertSame(19, $source['fichiers'][0]['entrees_lues']);
        self::assertSame(3, $source['fichiers'][0]['entrees_ecartees']);
        self::assertSame(17, $source['fichiers'][0]['adresses_testees']);
        self::assertSame(18, $source['fichiers'][0]['requetes']);
        self::assertStringContainsString(';2026-01-16;2026-10-20;repond;200;oui;2026-11-18', $this->workspace->read(DataFiles::APIS_CSV), 'dates et code HTTP repris des compléments de la collecte');
    }

    public function testTimestampsAndCodesOfTheSameClassDoNotChangeTheFingerprint(): void
    {
        $first = $this->collect(Workspace::PAGES, 'un');

        $pages = $this->workspace->pages('pages-horodatages');
        $content = (string) file_get_contents($pages . '/page-0001.json');
        file_put_contents($pages . '/page-0001.json', str_replace(['2026-10-20T14:30:00', '"views": 120'], ['2026-11-17T09:00:00', '"views": 999'], $content));
        $this->workspace->edit($pages . '/sondes.json', static function (array $probes): array {
            self::assertIsArray($probes['reponses']);
            $probes['reponses']['https://api.example.com/adresse'] = ['code' => 204, 'redirection' => null, 'corps' => '', 'erreur' => null];
            $probes['reponses']['https://panne.example.net/api'] = ['code' => 500, 'redirection' => null, 'corps' => '', 'erreur' => null];

            return $probes;
        });
        // Pages dans un autre ordre : la seconde d'abord.
        rename($pages . '/page-0001.json', $pages . '/page-tmp.json');
        $second = (string) file_get_contents($pages . '/page-0002.json');
        file_put_contents($pages . '/page-0001.json', str_replace('"next_page": null', '"next_page": "https://www.data.gouv.fr/api/1/dataservices/?page_size=100&page=2"', $second));
        file_put_contents($pages . '/page-0002.json', str_replace('"next_page": "https://www.data.gouv.fr/api/1/dataservices/?page_size=100&page=2"', '"next_page": null', (string) file_get_contents($pages . '/page-tmp.json')));
        unlink($pages . '/page-tmp.json');

        self::assertSame($first, $this->collect($pages, 'deux'), 'ni un horodatage, ni une mesure, ni un code de la même classe, ni l\'ordre des pages ne changent l\'empreinte');
    }

    public function testATestClassChangeChangesTheFingerprint(): void
    {
        $first = $this->collect(Workspace::PAGES, 'un');

        $pages = $this->workspace->pages('pages-test');
        $this->workspace->edit($pages . '/sondes.json', static function (array $probes): array {
            self::assertIsArray($probes['reponses']);
            $probes['reponses']['https://api.example.com/adresse'] = ['code' => 503, 'redirection' => null, 'corps' => '', 'erreur' => null];

            return $probes;
        });

        self::assertNotSame($first, $this->collect($pages, 'deux'));
    }

    public function testTooManyUnreachableAddressesStopTheCollect(): void
    {
        $pages = $this->workspace->pages();
        $this->workspace->edit($pages . '/sondes.json', static function (array $probes): array {
            self::assertIsArray($probes['reponses']);
            foreach (['https://api.example.com/adresse', 'https://api.example.com/entreprises', 'https://annuaire.example.org/api', 'https://portail.example.org/api/jeux/arbres'] as $url) {
                unset($probes['reponses'][$url]);
            }

            return $probes;
        });
        $before = $this->workspace->state();

        [$code, , $errors] = $this->command(['--pages', $pages]);

        self::assertSame(Command::REFUSED, $code);
        self::assertStringContainsString('Garde-fou : 5 adresse(s) d\'API sur 12 sont injoignables (41 %, seuil 30 %)', $errors);
        self::assertStringContainsString('Aucun fichier n\'a été écrit.', $errors);
        self::assertSame($before, $this->workspace->state());
    }

    public function testARefusedSourceWritesNothing(): void
    {
        $pages = $this->workspace->pages();
        file_put_contents($pages . '/page-0002.json', '{"data": [{"title": "Sans identifiant"}], "next_page": null}');
        $before = $this->workspace->state();

        [$code, $output, $errors] = $this->command(['--pages', $pages, '--resume', $this->workspace->path . '/resume.json']);

        self::assertSame(Command::REFUSED, $code);
        self::assertStringContainsString('Arrêt : Page 2, entrée n° 1 : champ obligatoire « id » absent ou vide', $errors);
        self::assertStringNotContainsString('réécrits', $output);
        self::assertSame($before, $this->workspace->state());
        self::assertFileDoesNotExist($this->workspace->path . '/resume.json');
    }

    public function testAnEmptyCatalogueStopsTheCollect(): void
    {
        $pages = $this->workspace->pages();
        file_put_contents($pages . '/page-0001.json', '{"data": [], "next_page": null, "total": 0}');

        [$code, , $errors] = $this->command(['--pages', $pages]);

        self::assertSame(Command::REFUSED, $code);
        self::assertStringContainsString('Liste vide : le catalogue ne donne aucune API', $errors);
    }

    public function testAnAlteredCollectIsRefused(): void
    {
        $directory = $this->workspace->path . '/collecte';
        $manifest = $this->workspace->path . '/manifeste.json';
        $this->command(['--collecter', $directory, '--manifeste', $manifest, '--pages', Workspace::PAGES], false);
        file_put_contents($directory . '/' . Command::COLLECTED_FILE, ' ', FILE_APPEND);
        $before = $this->workspace->state();

        [$code, , $errors] = $this->command(['--manifeste', $manifest]);

        self::assertSame(Command::REFUSED, $code);
        self::assertStringContainsString('empreinte SHA-256 différente de celle du manifeste', $errors);
        self::assertSame($before, $this->workspace->state());
    }

    public function testAReadmeWithoutTagsStopsTheUpdate(): void
    {
        file_put_contents($this->workspace->readme, "# Sans balise\n");
        $before = $this->workspace->state();

        [$code, , $errors] = $this->command(['--pages', Workspace::PAGES]);

        self::assertSame(Command::REFUSED, $code);
        self::assertStringContainsString('README.md : balises', $errors);
        self::assertSame($before, $this->workspace->state());
    }

    public function testTheCollectCanBeRecordedAndReplayed(): void
    {
        $record = $this->workspace->path . '/enregistrement';

        [$code, , $errors] = $this->command(['--pages', Workspace::PAGES, '--enregistrer', $record]);
        self::assertSame(Command::SUCCESS, $code, $errors);
        self::assertFileExists($record . '/page-0002.json');
        self::assertFileExists($record . '/sondes.json');
        $before = $this->workspace->state();

        [$code, $output] = $this->command(['--pages', $record], true, '2026-11-25');
        self::assertSame(Command::SUCCESS, $code);
        self::assertStringContainsString('Catalogue inchangé', $output, 'la collecte rejouée depuis son enregistrement donne le même catalogue');
        self::assertSame($before, $this->workspace->state());
    }

    public function testPublishedValuesHoldNoIpv4Address(): void
    {
        $this->command(['--pages', Workspace::PAGES]);

        foreach ([DataFiles::APIS_JSON, DataFiles::APIS_CSV, DataFiles::HOSTS_JSON, DataFiles::HOSTS_CSV] as $file) {
            self::assertDoesNotMatchRegularExpression(Screening::IPV4, $this->workspace->read($file), $file);
        }
    }

    /**
     * Lance la commande ; les dossiers et fichiers d'essai sont ajoutés aux arguments si $defaults est vrai.
     *
     * @param list<string> $arguments
     *
     * @return array{0: int, 1: string, 2: string} code de sortie, sortie, erreurs
     */
    private function command(array $arguments, bool $defaults = true, string $day = '2026-11-18'): array
    {
        if ($defaults) {
            array_push($arguments, '--donnees', $this->workspace->data, '--readme', $this->workspace->readme, '--liste', $this->workspace->list);
        }
        $output = fopen('php://memory', 'w+');
        $errors = fopen('php://memory', 'w+');
        self::assertIsResource($output);
        self::assertIsResource($errors);
        $code = Command::run($arguments, $output, $errors, new \DateTimeImmutable($day . 'T06:40:00+01:00'));
        rewind($output);
        rewind($errors);
        $result = [$code, (string) stream_get_contents($output), (string) stream_get_contents($errors)];
        fclose($output);
        fclose($errors);

        return $result;
    }

    /**
     * Empreinte d'une collecte rejouée depuis ces pages.
     */
    private function collect(string $pages, string $name): string
    {
        $manifest = $this->workspace->path . '/manifeste-' . $name . '.json';
        [$code, , $errors] = $this->command(['--collecter', $this->workspace->path . '/collecte-' . $name, '--manifeste', $manifest, '--pages', $pages], false);
        self::assertSame(Command::SUCCESS, $code, $errors);
        $hash = self::manifestEntry($manifest)['sha256'];
        self::assertIsString($hash);

        return $hash;
    }

    /**
     * @return array<mixed>
     */
    private static function manifestEntry(string $manifest): array
    {
        $data = JsonFile::read($manifest, 'manifeste');
        self::assertSame('collecte', $data['mode']);
        self::assertIsArray($data['fichiers']);
        self::assertCount(1, $data['fichiers']);
        self::assertIsArray($data['fichiers'][0]);

        return $data['fichiers'][0];
    }
}
