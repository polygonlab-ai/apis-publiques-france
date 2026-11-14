<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Tests;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use PolygonLab\ApisPubliquesFrance\Outils\Api;
use PolygonLab\ApisPubliquesFrance\Outils\Command;
use PolygonLab\ApisPubliquesFrance\Outils\DataFiles;
use PolygonLab\ApisPubliquesFrance\Outils\JsonFile;
use PolygonLab\ApisPubliquesFrance\Outils\ListPage;
use PolygonLab\ApisPubliquesFrance\Outils\Readme;
use PolygonLab\ApisPubliquesFrance\Outils\Screening;
use PolygonLab\ApisPubliquesFrance\Outils\Sources;
use PolygonLab\ApisPubliquesFrance\Outils\TestClass;
use PolygonLab\ApisPubliquesFrance\Outils\Text;
use PolygonLab\ApisPubliquesFrance\Outils\Updater;
use PolygonLab\ApisPubliquesFrance\Tests\Fixtures\Workspace;

/**
 * Les fichiers publiés de data/ : conformes aux schémas, cohérents entre eux, avec le README et avec LISTE.md, sans
 * adresse e-mail ni adresse IPv4. Rejoué par l'intégration continue à chaque publication.
 */
final class DataTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    private const DATA = self::ROOT . '/data';

    private const SCHEMAS = [DataFiles::APIS_JSON => self::ROOT . '/schema/apis.schema.json', DataFiles::HOSTS_JSON => self::ROOT . '/schema/hotes.schema.json'];

    public function testPublishedFilesMatchTheirSchemas(): void
    {
        foreach (self::SCHEMAS as $file => $schema) {
            self::assertSchemaValid((string) file_get_contents(self::DATA . '/' . $file), $schema);
        }
    }

    public function testGeneratedFilesMatchTheirSchemas(): void
    {
        $workspace = new Workspace();
        try {
            $process = proc_open([PHP_BINARY, self::ROOT . '/tools/actualiser.php', '--pages', Workspace::PAGES, '--donnees', $workspace->data, '--readme', $workspace->readme, '--liste', $workspace->list, '--mots-interdits', $workspace->forbiddenWords], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(Command::SUCCESS, proc_close($process), $output);
            $generated = [DataFiles::APIS_JSON => $workspace->read(DataFiles::APIS_JSON), DataFiles::HOSTS_JSON => $workspace->read(DataFiles::HOSTS_JSON)];
        } finally {
            $workspace->remove();
        }

        $empty = Updater::emptyFiles(Sources::load());
        foreach (self::SCHEMAS as $file => $schema) {
            self::assertSchemaValid($generated[$file], $schema);
            self::assertSchemaValid($empty[$file], $schema);
        }
    }

    public function testASchemaRefusesAnUnknownTestResult(): void
    {
        $list = (string) json_encode(['source' => 'x', 'page' => 'https://www.example.com', 'date_source' => null, 'nombre' => 0, 'hotes' => [['hote' => 'api.example.com', 'nombre_apis' => 1, 'organisations' => [], 'resultat' => 'parfait', 'nombre_qui_repondent' => 0]]]);
        $validator = new Validator();
        $data = json_decode($list);
        $validator->validate($data, json_decode((string) file_get_contents(self::SCHEMAS[DataFiles::HOSTS_JSON])), Constraint::CHECK_MODE_NORMAL);

        self::assertFalse($validator->isValid());
    }

    public function testPublishedFilesAreConsistent(): void
    {
        $apis = DataFiles::readApis((string) file_get_contents(self::DATA . '/' . DataFiles::APIS_JSON));
        $list = JsonFile::read(self::DATA . '/' . DataFiles::APIS_JSON, 'apis.json');
        $hosts = JsonFile::read(self::DATA . '/' . DataFiles::HOSTS_JSON, 'hotes.json');
        $source = JsonFile::read(self::DATA . '/' . DataFiles::SOURCE_JSON, 'source.json');
        $date = \is_string($list['date_source']) ? $list['date_source'] : null;
        $ordered = array_values($apis);

        // Le JSON est trié par titre puis identifiant ; les compteurs additionnent le total.
        self::assertSame(array_map(static fn(Api $api): string => $api->id, DataFiles::sorted($ordered)), array_map(strval(...), array_keys($apis)));
        self::assertSame(\count($apis), $list['nombre']);
        self::assertIsArray($list['nombres']);
        self::assertIsArray($list['nombres']['acces']);
        self::assertIsArray($list['nombres']['test']);
        self::assertSame($list['nombre'], array_sum($list['nombres']['acces']));
        self::assertSame($list['nombre'], array_sum($list['nombres']['test']));
        self::assertSame(TestClass::ALL, array_keys($list['nombres']['test']));

        // La lecture par hôte est celle des mêmes API.
        self::assertSame(DataFiles::hosts($ordered), $hosts['hotes']);
        self::assertSame(\count(DataFiles::hosts($ordered)), $list['nombre_hotes']);
        self::assertSame(DataFiles::hostsCsv($ordered), file_get_contents(self::DATA . '/' . DataFiles::HOSTS_CSV));

        // Le CSV est le rendu exact du JSON.
        $availability = [];
        self::assertIsArray($list['apis']);
        foreach ($list['apis'] as $record) {
            self::assertIsArray($record);
            self::assertIsString($record['id']);
            if (\is_float($record['disponibilite_observee']) || \is_int($record['disponibilite_observee'])) {
                $availability[$record['id']] = (float) $record['disponibilite_observee'];
            }
        }
        self::assertSame(DataFiles::apisCsv($date, $ordered, $availability), file_get_contents(self::DATA . '/' . DataFiles::APIS_CSV));

        // La source et les hôtes portent la même date ; une seule entrée, celle du catalogue, une fois la liste remplie.
        self::assertSame($list['date_source'], $source['date_source']);
        self::assertSame($list['date_source'], $hosts['date_source']);
        self::assertIsArray($source['fichiers']);
        if ($apis !== []) {
            self::assertSame(['catalogue'], array_column($source['fichiers'], 'cle'));
            self::assertSame([Sources::load()->listUrl()], array_column($source['fichiers'], 'url'));
            foreach (array_column($source['fichiers'], 'sha256') as $hash) {
                self::assertIsString($hash);
                self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $hash);
            }
        }

        // L'historique et les passages commencent par leur en-tête ; les passages donnent la disponibilité observée.
        self::assertStringStartsWith(DataFiles::historyHeader(), (string) file_get_contents(self::DATA . '/' . DataFiles::HISTORY_CSV));
        $passes = DataFiles::readPasses((string) file_get_contents(self::DATA . '/' . DataFiles::PASSES_CSV));
        self::assertLessThanOrEqual(Sources::load()->passes, \count($passes));
        self::assertSame(array_intersect_key(DataFiles::availability($passes, Sources::load()->minPasses), $apis), $availability);

        // Les chiffres du README et la page LISTE.md sont ceux des données publiées.
        self::assertStringContainsString(Readme::START . Readme::block($date, $ordered) . Readme::END, (string) file_get_contents(self::ROOT . '/README.md'));
        self::assertSame(ListPage::render($date, $ordered), file_get_contents(self::ROOT . '/' . ListPage::FILE));
    }

    /**
     * Aucune adresse e-mail ni adresse IPv4 ne doit arriver dans data/, ni dans les pages générées.
     */
    public function testPublishedFilesContainNoEmailAndNoIpv4Address(): void
    {
        foreach ([...(glob(self::DATA . '/*') ?: []), self::ROOT . '/' . ListPage::FILE] as $file) {
            $content = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression(Text::EMAIL, $content, basename($file) . ' contient une adresse e-mail');
            if (basename($file) !== DataFiles::SOURCE_JSON) {
                self::assertDoesNotMatchRegularExpression(Screening::IPV4, $content, basename($file) . ' contient une adresse IPv4');
            }
        }
    }

    private static function assertSchemaValid(string $json, string $schemaFile): void
    {
        $data = json_decode($json);
        $schema = json_decode((string) file_get_contents($schemaFile));
        self::assertIsObject($schema, 'schéma illisible');

        $validator = new Validator();
        $validator->validate($data, $schema, Constraint::CHECK_MODE_NORMAL);

        self::assertTrue($validator->isValid(), (string) json_encode($validator->getErrors(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
