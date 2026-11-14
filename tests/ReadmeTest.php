<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Tests;

use PHPUnit\Framework\TestCase;
use PolygonLab\ApisPubliquesFrance\Outils\Api;
use PolygonLab\ApisPubliquesFrance\Outils\CurlTransport;
use PolygonLab\ApisPubliquesFrance\Outils\JsonFile;
use PolygonLab\ApisPubliquesFrance\Outils\Readme;
use PolygonLab\ApisPubliquesFrance\Outils\Sources;
use PolygonLab\ApisPubliquesFrance\Outils\TestClass;

/**
 * Le démarrage rapide du README est le script d'exemple, et il produit la sortie annoncée (lancé ici sur une copie
 * locale fabriquée, sans appel réseau) ; les réglages, les classes de test et les mentions du README sont à jour.
 */
final class ReadmeTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    private const EXAMPLE = self::ROOT . '/examples/lire-le-catalogue.php';

    public function testQuickStartCodeIsTheExampleScript(): void
    {
        self::assertSame(rtrim((string) file_get_contents(self::EXAMPLE), "\n"), self::block('php'));
    }

    public function testQuickStartOutputIsTheExpectedOutput(): void
    {
        $copy = sys_get_temp_dir() . '/apis-exemple-' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($copy, json_encode([
            'source' => 'data.gouv.fr, catalogue des API',
            'page' => 'https://www.data.gouv.fr/dataservices/',
            'date_source' => '2026-11-18',
            'nombre' => 3,
            'nombres' => ['acces' => ['ouvert' => 2, 'restreint' => 1], 'test' => ['repond' => 1, 'acces_refuse' => 1, 'injoignable' => 1]],
            'nombre_hotes' => 2,
            'apis' => [
                self::exampleApi('API Adresse d\'essai', 'Institut d\'essai géographique', 'ouvert', 'https://api.example.com/adresse', 'repond', 200),
                self::exampleApi('API Entreprises d\'essai', 'Institut d\'essai des statistiques', 'restreint', 'https://api.example.com/entreprises', 'acces_refuse', 401),
                self::exampleApi('API disparue', null, 'ouvert', 'https://disparue.example.org/api', 'injoignable', null),
            ],
        ]));
        try {
            $process = proc_open([PHP_BINARY, self::EXAMPLE, $copy], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $output = (string) stream_get_contents($pipes[1]);
            $errors = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $errors);
        } finally {
            unlink($copy);
        }

        self::assertSame(self::block('text'), rtrim($output, "\n"));
    }

    public function testDirectAddressesTargetThePublishedFiles(): void
    {
        $readme = self::readme();

        foreach (['apis.json', 'apis.csv', 'hotes.json', 'hotes.csv', 'historique.csv', 'passages.csv', 'source.json'] as $file) {
            self::assertStringContainsString('https://raw.githubusercontent.com/polygonlab-ai/apis-publiques-france/main/data/' . $file, $readme);
            self::assertFileExists(self::ROOT . '/data/' . $file);
        }
    }

    public function testTheGeneratedBlockHasItsTwoTagsOnce(): void
    {
        $readme = self::readme();

        self::assertSame(1, substr_count($readme, Readme::START));
        self::assertSame(1, substr_count($readme, Readme::END));
        self::assertStringContainsString(Readme::START . "\nessai\n" . Readme::END, Readme::replace($readme, "\nessai\n"));
    }

    public function testEveryTestResultIsExplained(): void
    {
        $readme = self::readme();

        foreach ([...TestClass::ALL, ...TestClass::DOC_ALL] as $class) {
            self::assertStringContainsString('| `' . $class . '` |', $readme);
        }
        foreach (Api::ACCESS as $access) {
            self::assertStringContainsString('`' . $access . '`', $readme);
        }
        self::assertStringContainsString('pas que l\'API fonctionne', $readme);
        self::assertStringContainsString('Une API restreinte qui marche répond `acces_refuse`', $readme);
        self::assertStringContainsString('une fois par semaine, depuis un seul serveur situé en France', $readme);
        self::assertStringContainsString('`' . CurlTransport::USER_AGENT . '`', $readme);
    }

    public function testTheSettingsTableIsUpToDate(): void
    {
        $readme = self::readme();
        $settings = JsonFile::read(Sources::FILE, 'tools/sources.json');

        self::assertIsArray($settings['test']);
        self::assertIsString($settings['catalogue']);
        self::assertStringContainsString('| `catalogue` | `' . $settings['catalogue'] . '` |', $readme);
        foreach (['par_page', 'pages_max', 'pause_s', 'essais', 'delai_s', 'taille_max_page', 'seuil_disparitions_pct'] as $key) {
            self::assertIsInt($settings[$key]);
            self::assertStringContainsString('| `' . $key . '` | ' . $settings[$key] . ' |', $readme);
        }
        foreach ($settings['test'] as $key => $value) {
            self::assertIsInt($value);
            self::assertStringContainsString('| `test.' . $key . '` | ' . $value . ' |', $readme);
        }
    }

    public function testTheMentionsAskedForArePresent(): void
    {
        $readme = self::readme();

        self::assertStringContainsString('Catalogue repris de data.gouv.fr. PolygonLab ne recommande aucune API et ne garantit pas leur fonctionnement.', $readme);
        self::assertStringContainsString('Licence de la source : Licence Ouverte 2.0 (Etalab)', $readme);
        self::assertStringContainsString('ouvrir un ticket', $readme);
    }

    /**
     * @return array<string, mixed>
     */
    private static function exampleApi(string $title, ?string $organisation, string $access, string $address, string $result, ?int $code): array
    {
        return [
            'id' => substr(hash('sha256', $title), 0, 24),
            'titre' => $title,
            'organisation' => $organisation,
            'acces' => $access,
            'adresse' => $address,
            'test' => ['resultat' => $result, 'code_http' => $code, 'https' => true, 'teste_le' => '2026-11-18'],
        ];
    }

    private static function readme(): string
    {
        return (string) file_get_contents(self::ROOT . '/README.md');
    }

    /**
     * Contenu du premier bloc de code du README dans ce langage.
     */
    private static function block(string $language): string
    {
        self::assertSame(1, preg_match('/```' . $language . '\n(.*?)\n```/s', self::readme(), $match), 'bloc ' . $language . ' introuvable');

        return $match[1];
    }
}
