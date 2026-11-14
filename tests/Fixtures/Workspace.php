<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Tests\Fixtures;

use PolygonLab\ApisPubliquesFrance\Outils\JsonFile;
use PolygonLab\ApisPubliquesFrance\Outils\ListPage;
use PolygonLab\ApisPubliquesFrance\Outils\Sources;
use PolygonLab\ApisPubliquesFrance\Outils\Updater;

/**
 * Dossier de travail temporaire d'un test : dossier data/ vide mais valide, copie du README, page LISTE.md, fichier
 * de mots interdits d'essai, copie modifiable de la collecte d'essai, réglages modifiés, effacement.
 */
final class Workspace
{
    /** Mot interdit des essais (fictif). */
    public const FORBIDDEN_WORD = 'motinterdit';

    /** Collecte d'essai : deux pages du catalogue et les réponses des faux serveurs (sondes.json). */
    public const PAGES = __DIR__ . '/catalogue';

    public readonly string $path;

    public readonly string $data;

    public readonly string $readme;

    public readonly string $list;

    public readonly string $forbiddenWords;

    public function __construct()
    {
        $this->path = sys_get_temp_dir() . '/apis-publiques-essai-' . bin2hex(random_bytes(6));
        $this->data = $this->path . '/data';
        mkdir($this->data, 0700, true);
        foreach (Updater::emptyFiles(Sources::load()) as $name => $content) {
            file_put_contents($this->data . '/' . $name, $content);
        }
        $this->readme = $this->path . '/README.md';
        copy(__DIR__ . '/../../README.md', $this->readme);
        $this->list = $this->path . '/' . ListPage::FILE;
        file_put_contents($this->list, ListPage::render(null, []));
        $this->forbiddenWords = $this->path . '/mots-interdits.json';
        file_put_contents($this->forbiddenWords, (string) json_encode(['mots' => [['mot' => self::FORBIDDEN_WORD, 'tolerees' => ['motinterdit-autorise']]]]));
    }

    /**
     * Copie de la collecte d'essai dans un dossier du test, pour la modifier.
     */
    public function pages(string $name = 'pages'): string
    {
        $directory = $this->path . '/' . $name;
        mkdir($directory, 0700, true);
        foreach (glob(self::PAGES . '/*.json') ?: [] as $file) {
            copy($file, $directory . '/' . basename($file));
        }

        return $directory;
    }

    /**
     * Modifie un fichier JSON du dossier de travail : la fonction reçoit son contenu décodé et rend le nouveau.
     *
     * @param \Closure(array<string, mixed>): array<string, mixed> $change
     */
    public function edit(string $file, \Closure $change): void
    {
        file_put_contents($file, JsonFile::encode($change(JsonFile::read($file, basename($file)))));
    }

    /**
     * Réglages du dépôt, avec les valeurs données à la place des siennes.
     *
     * @param array<string, int> $settings réglages de premier niveau (« pages_max »…)
     * @param array<string, int> $test réglages du test des adresses (« duree_max_s »…)
     */
    public function sources(array $settings = [], array $test = []): Sources
    {
        $data = JsonFile::read(Sources::FILE, 'tools/sources.json');
        $data = array_merge($data, $settings);
        $data['test'] = array_merge(\is_array($data['test']) ? $data['test'] : [], $test);
        $path = $this->path . '/sources-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, JsonFile::encode($data));

        return Sources::load($path);
    }

    public function read(string $name): string
    {
        return (string) file_get_contents($this->data . '/' . $name);
    }

    /**
     * Empreintes des fichiers de data/, du README et de LISTE.md : état du dépôt d'essai.
     *
     * @return array<string, string>
     */
    public function state(): array
    {
        $state = [];
        foreach ([...(glob($this->data . '/*') ?: []), $this->readme, $this->list] as $file) {
            $state[basename($file)] = hash('sha256', (string) file_get_contents($file));
        }
        ksort($state, SORT_STRING);

        return $state;
    }

    public function remove(): void
    {
        self::delete($this->path);
    }

    private static function delete(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::delete($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}
