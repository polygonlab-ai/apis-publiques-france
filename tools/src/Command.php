<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Commande « php tools/actualiser.php » : arguments, collecte (lecture du catalogue puis test des adresses),
 * lecture du manifeste, écriture des fichiers de data/, de LISTE.md et des chiffres du README, résumé.
 *
 * Trois usages :
 *   - sans --collecter ni --manifeste : collecte puis actualisation, en une fois ;
 *   - --collecter <dossier> --manifeste <fichier.json> : collecte seule ; écrit dans le dossier le fichier normalisé
 *     (catalogue.json : fiches et classes de test, sans date ni code HTTP), ses compléments (complements.json : dates,
 *     codes HTTP, compteurs) et le manifeste (adresse, empreinte SHA-256 du fichier normalisé), sans toucher à data/ ;
 *   - --manifeste <fichier.json> : actualisation à partir d'une collecte déjà faite.
 *
 * Codes de sortie : 0 succès (fichiers réécrits, ou catalogue inchangé) ; 1 source refusée, rien n'est écrit ; 2 usage.
 *
 * @phpstan-import-type Collected from Updater
 * @phpstan-type Options array{collecter: string|null, manifeste: string|null, donnees: string, readme: string, liste: string, resume: string|null, mots-interdits: string|null, pages: string|null, enregistrer: string|null, aide: bool}
 */
final class Command
{
    public const SUCCESS = 0;
    public const REFUSED = 1;
    public const USAGE = 2;

    /** Fichier normalisé écrit par la collecte : c'est lui qui porte l'empreinte. */
    public const COLLECTED_FILE = 'catalogue.json';

    /** Compléments de la collecte, hors empreinte : dates, codes HTTP, compteurs. */
    public const COMPLEMENTS_FILE = 'complements.json';

    public const MANIFEST_FORMAT = 1;

    public const COLLECT_FORMAT = 1;

    /**
     * @param list<string> $arguments arguments de la ligne de commande, sans le nom du script
     * @param resource $output
     * @param resource $errors
     */
    public static function run(array $arguments, $output, $errors, ?\DateTimeImmutable $now = null): int
    {
        try {
            $options = self::options($arguments);
        } catch (\InvalidArgumentException $error) {
            fwrite($errors, $error->getMessage() . "\n\n" . self::usage());

            return self::USAGE;
        }
        if ($options['aide']) {
            fwrite($output, self::usage());

            return self::SUCCESS;
        }

        $temporary = null;
        try {
            $sources = Sources::load();
            $manifest = $options['manifeste'];
            $directory = $options['collecter'];
            if ($directory === null && $manifest !== null) {
                $collected = self::readManifest($manifest);
            } else {
                if ($directory === null) {
                    $temporary = sys_get_temp_dir() . '/apis-publiques-france-' . bin2hex(random_bytes(6));
                }
                $collected = self::collect($sources, $directory ?? (string) $temporary, $options, $output);
                if ($directory !== null) {
                    self::writeManifest((string) $manifest, $collected);
                    fwrite($output, 'Manifeste écrit : ' . (string) $manifest . "\n");

                    return self::SUCCESS;
                }
            }

            $screening = new Screening($options['mots-interdits'] === null ? ForbiddenWords::none() : ForbiddenWords::load($options['mots-interdits']));
            $result = (new Updater($sources, $screening, $options['donnees']))->run($collected, $now ?? new \DateTimeImmutable('now'));
            if ($result['changed']) {
                $readme = @file_get_contents($options['readme']);
                if ($readme === false) {
                    throw new SourceError('README illisible : ' . $options['readme'] . '.');
                }
                $files = [];
                foreach ($result['files'] as $name => $content) {
                    $files[$options['donnees'] . '/' . $name] = $content;
                }
                $files[$options['liste']] = $result['list'];
                $files[$options['readme']] = Readme::replace($readme, $result['readme']);
                self::write($files);
            }
            if ($options['resume'] !== null && file_put_contents($options['resume'], JsonFile::encode($result['summary'])) === false) {
                throw new SourceError('Écriture du résumé impossible : ' . $options['resume'] . '.');
            }
            $text = $result['summary']['texte'];
            fwrite($output, (\is_string($text) ? $text : '') . "\n\n" . ($result['changed'] ? 'Fichiers de data/, LISTE.md et chiffres du README réécrits.' : 'Catalogue inchangé : aucun fichier modifié.') . "\n");

            return self::SUCCESS;
        } catch (SourceError $error) {
            fwrite($errors, 'Arrêt : ' . $error->getMessage() . "\nAucun fichier n'a été écrit.\n");

            return self::REFUSED;
        } finally {
            if ($temporary !== null) {
                foreach (glob($temporary . '/*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($temporary);
            }
        }
    }

    public static function usage(): string
    {
        return <<<'TXT'
            Usage : php tools/actualiser.php [options]

            Sans option, lit tout le catalogue des API de data.gouv.fr (tools/sources.json), teste chaque adresse une
            fois, compare le résultat à data/ et réécrit les fichiers, LISTE.md et les chiffres du README si le
            catalogue ou un résultat de test a changé.

              --collecter <dossier>         collecte seule : fichiers écrits dans ce dossier (avec --manifeste)
              --manifeste <fichier.json>    avec --collecter : manifeste à écrire ; seul : collecte à utiliser
              --donnees <dossier>           dossier des données (défaut : data/ du dépôt)
              --readme <fichier.md>         README dont les chiffres sont réécrits (défaut : README.md du dépôt)
              --liste <fichier.md>          page de la liste par organisation (défaut : LISTE.md du dépôt)
              --resume <fichier.json>       écrit le résumé des changements en JSON
              --mots-interdits <fichier>    mots qui écartent une API (JSON : {"mots": [{"mot": "…"}]})
              --pages <dossier>             rejoue une collecte enregistrée (pages et tests), sans réseau
              --enregistrer <dossier>       enregistre les pages lues et les tests, pour les rejouer avec --pages
              --chargeur <fichier.php>      chargeur automatique facultatif (défaut : vendor/autoload.php s'il existe)
              --aide                        affiche cette aide

            Codes de sortie : 0 succès, 1 source refusée (rien n'est écrit), 2 usage.

            TXT;
    }

    /**
     * Chargeur automatique désigné par --chargeur (lu avant toute autre option), sinon null.
     *
     * @param list<string> $arguments
     */
    public static function loader(array $arguments): ?string
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === '--chargeur') {
                return $arguments[$index + 1] ?? null;
            }
            if (str_starts_with($argument, '--chargeur=')) {
                return substr($argument, \strlen('--chargeur='));
            }
        }

        return null;
    }

    /**
     * Collecte : lecture du catalogue, test de chaque adresse distincte, garde-fou des adresses injoignables, puis
     * écriture du fichier normalisé et de ses compléments dans le dossier.
     *
     * @param Options $options
     * @param resource $output
     *
     * @return Collected
     *
     * @throws SourceError
     */
    private static function collect(Sources $sources, string $directory, array $options, $output): array
    {
        foreach (array_filter([$directory, $options['enregistrer']]) as $folder) {
            if (!is_dir($folder) && !@mkdir($folder, 0700, true)) {
                throw new SourceError('Dossier impossible à créer : ' . $folder . '.');
            }
        }
        $replayed = $options['pages'];
        $client = $replayed !== null ? new ReplayClient($replayed) : CurlClient::fromSources($sources);
        if ($options['enregistrer'] !== null) {
            $client = new RecordingClient($client, $options['enregistrer']);
        }
        fwrite($output, 'Lecture du catalogue : ' . $sources->listUrl() . "\n");
        $reading = (new Catalogue($client, $sources, $replayed !== null ? static function (int $seconds): void {} : null))->read();
        if ($reading['apis'] === []) {
            throw new SourceError(sprintf('Liste vide : le catalogue ne donne aucune API (%d entrée(s) lue(s) sur %d page(s), %d écartée(s)).', $reading['read'], $reading['pages'], $reading['skipped']));
        }

        $urls = [];
        foreach ($reading['apis'] as $api) {
            foreach ([$api->address, $api->machineDocumentation] as $url) {
                if ($url !== null) {
                    $urls[$url] = true;
                }
            }
        }
        $urls = array_map(strval(...), array_keys($urls));

        // Rejeu : aucune attente réelle, le temps avance seul. Sinon : horloge et attentes réelles.
        $sleep = null;
        $clock = static fn(): float => microtime(true);
        if ($replayed !== null) {
            $replay = ProbeReplay::load($replayed);
            $transport = $replay;
            $resolver = $replay;
            $time = 0.0;
            $sleep = static function (float $seconds) use (&$time): void {
                $time += $seconds;
            };
            $clock = static function () use (&$time): float {
                return $time;
            };
        } else {
            $transport = CurlTransport::fromSources($sources);
            $resolver = new DnsResolver();
        }
        $recorder = null;
        if ($options['enregistrer'] !== null) {
            $recorder = new ProbeRecorder($transport, $resolver, $options['enregistrer']);
            $transport = $recorder;
            $resolver = $recorder;
        }
        fwrite($output, sprintf("Test de %d adresse(s) distincte(s), une requête par seconde au plus par hôte…\n", \count($urls)));
        $start = $clock();
        $prober = new Prober($transport, $resolver, $sources, $sleep, $clock);
        $outcomes = $prober->probe($urls);
        $duration = (int) round($clock() - $start);
        $recorder?->save();

        $tested = 0;
        foreach ($outcomes as $outcome) {
            $tested += $outcome->class === TestClass::UNTESTED ? 0 : 1;
        }
        $addresses = [];
        $unreachable = 0;
        $apis = [];
        foreach ($reading['apis'] as $api) {
            $outcome = $api->address === null ? null : ($outcomes[$api->address] ?? null);
            $documentation = $api->machineDocumentation === null ? null : ($outcomes[$api->machineDocumentation] ?? Outcome::untested('adresse non testée'));
            $apis[] = $api->withTests($outcome === null ? TestClass::UNTESTED : $outcome->class, $outcome?->code, TestClass::documentation($documentation));
            if ($api->address !== null && $outcome !== null && $outcome->class !== TestClass::UNTESTED && !isset($addresses[$api->address])) {
                $addresses[$api->address] = true;
                $unreachable += $outcome->class === TestClass::UNREACHABLE ? 1 : 0;
            }
        }
        $called = \count($addresses);
        if ($called > 0 && $unreachable * 100 > $called * $sources->maxUnreachablePercent) {
            throw new SourceError(sprintf(
                'Garde-fou : %d adresse(s) d\'API sur %d sont injoignables (%d %%, seuil %d %%) : panne de réseau probable du côté de l\'outil.',
                $unreachable,
                $called,
                intdiv($unreachable * 100, $called),
                $sources->maxUnreachablePercent,
            ));
        }

        $content = JsonFile::encode([
            'format' => self::COLLECT_FORMAT,
            'apis' => array_map(static fn(Api $api): array => $api->normalized(), $apis),
        ]);
        $path = rtrim($directory, '/') . '/' . self::COLLECTED_FILE;
        $complements = [];
        foreach ($apis as $api) {
            $complements[$api->id] = ['code_http' => $api->httpCode, 'cree_le' => $api->createdOn, 'modifie_le' => $api->modifiedOn];
        }
        $collected = [
            'apis' => $apis,
            'sha256' => hash('sha256', $content),
            'size' => \strlen($content),
            'pages' => $reading['pages'],
            'read' => $reading['read'],
            'skipped' => $reading['skipped'],
            'tested' => $tested,
            'requests' => $prober->requests(),
            'duration' => $duration,
            'emails' => $reading['emails'],
            'url' => $sources->listUrl(),
            'path' => $path,
        ];
        $extra = JsonFile::encode([
            'format' => self::COLLECT_FORMAT,
            'pages' => $collected['pages'],
            'entrees_lues' => $collected['read'],
            'entrees_ecartees' => $collected['skipped'],
            'adresses_testees' => $collected['tested'],
            'requetes' => $collected['requests'],
            'duree_test_s' => $collected['duration'],
            'emails' => (object) $collected['emails'],
            'apis' => (object) $complements,
        ]);
        foreach ([$path => $content, \dirname($path) . '/' . self::COMPLEMENTS_FILE => $extra] as $file => $data) {
            if (file_put_contents($file, $data) !== \strlen($data)) {
                throw new SourceError('Écriture impossible : ' . $file . '.');
            }
        }
        fwrite($output, sprintf(
            "Collecte : %d page(s), %d entrée(s) lue(s), %d écartée(s) à la lecture, %d API, %d adresse(s) testée(s) en %d s (%d requête(s)).\n",
            $collected['pages'],
            $collected['read'],
            $collected['skipped'],
            \count($apis),
            $tested,
            $duration,
            $collected['requests'],
        ));

        return $collected;
    }

    /**
     * @param Collected $collected
     *
     * @throws SourceError
     */
    private static function writeManifest(string $path, array $collected): void
    {
        $content = JsonFile::encode([
            'format' => self::MANIFEST_FORMAT,
            'mode' => 'collecte',
            'fichiers' => [[
                'cle' => Sources::KEY,
                'chemin' => $collected['path'],
                'url' => $collected['url'],
                'derniere_modification' => null,
                'sha256' => $collected['sha256'],
                'taille' => $collected['size'],
                'pages' => $collected['pages'],
                'entrees_lues' => $collected['read'],
            ]],
        ]);
        if (file_put_contents($path, $content) !== \strlen($content)) {
            throw new SourceError('Écriture du manifeste impossible : ' . $path . '.');
        }
    }

    /**
     * Collecte décrite par un manifeste : le fichier normalisé est relu et son empreinte vérifiée, puis complété par
     * le fichier des compléments du même dossier.
     *
     * @return Collected
     *
     * @throws SourceError
     */
    private static function readManifest(string $manifest): array
    {
        $data = JsonFile::read($manifest, 'Manifeste');
        $files = $data['fichiers'] ?? null;
        $entry = null;
        foreach (\is_array($files) ? $files : [] as $file) {
            if (\is_array($file) && ($file['cle'] ?? null) === Sources::KEY) {
                $entry = $file;
            }
        }
        $path = $entry['chemin'] ?? null;
        $url = $entry['url'] ?? null;
        $hash = $entry['sha256'] ?? null;
        if ($entry === null || !\is_string($path) || !\is_string($url) || !\is_string($hash)) {
            throw new SourceError('Manifeste : entrée « ' . Sources::KEY . ' » avec « chemin », « url » et « sha256 » attendue.');
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new SourceError('Fichier de collecte illisible : ' . $path . '.');
        }
        if (!hash_equals($hash, hash('sha256', $content))) {
            throw new SourceError('Fichier de collecte : empreinte SHA-256 différente de celle du manifeste.');
        }
        $label = 'Fichier de collecte';
        $normalized = JsonFile::decode($content, $label);
        $list = $normalized['apis'] ?? null;
        if (($normalized['format'] ?? null) !== self::COLLECT_FORMAT || !\is_array($list) || !array_is_list($list)) {
            throw new SourceError($label . ' : format ' . self::COLLECT_FORMAT . ' et liste « apis » attendus.');
        }
        $label = 'Compléments de la collecte';
        $extra = JsonFile::read(\dirname($path) . '/' . self::COMPLEMENTS_FILE, $label);
        $counts = [];
        foreach (['pages', 'entrees_lues', 'entrees_ecartees', 'adresses_testees', 'requetes', 'duree_test_s'] as $field) {
            $value = $extra[$field] ?? null;
            if (!\is_int($value) || $value < 0) {
                throw new SourceError($label . ' : « ' . $field . ' » (entier) attendu.');
            }
            $counts[$field] = $value;
        }
        $complements = \is_array($extra['apis'] ?? null) ? $extra['apis'] : [];
        $apis = [];
        foreach ($list as $item) {
            if (!\is_array($item)) {
                throw new SourceError('Fichier de collecte : API illisible.');
            }
            $api = Api::fromArray($item, 'Fichier de collecte');
            $complement = \is_array($complements[$api->id] ?? null) ? $complements[$api->id] : [];
            $code = $complement['code_http'] ?? null;
            $created = $complement['cree_le'] ?? null;
            $modified = $complement['modifie_le'] ?? null;
            $apis[] = $api->withComplements(\is_int($code) ? $code : null, \is_string($created) ? $created : null, \is_string($modified) ? $modified : null);
        }
        $emails = [];
        foreach (\is_array($extra['emails'] ?? null) ? $extra['emails'] : [] as $id => $fields) {
            $emails[(string) $id] = JsonFile::strings($fields, $label . ', « emails »');
        }

        return [
            'apis' => $apis,
            'sha256' => $hash,
            'size' => \strlen($content),
            'pages' => $counts['pages'],
            'read' => $counts['entrees_lues'],
            'skipped' => $counts['entrees_ecartees'],
            'tested' => $counts['adresses_testees'],
            'requests' => $counts['requetes'],
            'duration' => $counts['duree_test_s'],
            'emails' => $emails,
            'url' => $url,
            'path' => $path,
        ];
    }

    /**
     * @param list<string> $arguments
     *
     * @return Options
     */
    private static function options(array $arguments): array
    {
        $root = \dirname(__DIR__, 2);
        $options = [
            'collecter' => null,
            'manifeste' => null,
            'donnees' => $root . '/data',
            'readme' => $root . '/README.md',
            'liste' => $root . '/' . ListPage::FILE,
            'resume' => null,
            'mots-interdits' => null,
            'pages' => null,
            'enregistrer' => null,
            'aide' => false,
        ];
        $withValue = ['--collecter', '--manifeste', '--donnees', '--readme', '--liste', '--resume', '--mots-interdits', '--pages', '--enregistrer', '--chargeur'];
        for ($i = 0, $count = \count($arguments); $i < $count; $i++) {
            $argument = $arguments[$i];
            $value = null;
            if (str_contains($argument, '=') && str_starts_with($argument, '--')) {
                [$argument, $value] = explode('=', $argument, 2);
            }
            if (\in_array($argument, $withValue, true) && $value === null) {
                $value = $arguments[++$i] ?? throw new \InvalidArgumentException('Valeur manquante après ' . $argument . '.');
            }
            if (\in_array($argument, $withValue, true) && $value === '') {
                throw new \InvalidArgumentException('Valeur vide après ' . $argument . '.');
            }
            switch ($argument) {
                case '--donnees':
                    $options['donnees'] = rtrim((string) $value, '/');
                    break;
                case '--collecter':
                    $options['collecter'] = (string) $value;
                    break;
                case '--manifeste':
                    $options['manifeste'] = (string) $value;
                    break;
                case '--readme':
                    $options['readme'] = (string) $value;
                    break;
                case '--liste':
                    $options['liste'] = (string) $value;
                    break;
                case '--resume':
                    $options['resume'] = (string) $value;
                    break;
                case '--mots-interdits':
                    $options['mots-interdits'] = (string) $value;
                    break;
                case '--pages':
                    $options['pages'] = (string) $value;
                    break;
                case '--enregistrer':
                    $options['enregistrer'] = (string) $value;
                    break;
                case '--chargeur':
                    break;
                case '--aide':
                case '--help':
                case '-h':
                    $options['aide'] = true;
                    break;
                default:
                    throw new \InvalidArgumentException('Argument non reconnu : ' . $argument . '.');
            }
        }
        if ($options['collecter'] !== null && $options['manifeste'] === null) {
            throw new \InvalidArgumentException('--collecter demande aussi --manifeste <fichier.json>.');
        }
        if (!$options['aide'] && $options['collecter'] === null && !is_dir($options['donnees'])) {
            throw new \InvalidArgumentException('Dossier des données introuvable : ' . $options['donnees'] . '.');
        }

        return $options;
    }

    /**
     * Écrit chaque fichier dans un fichier temporaire du même dossier, puis le renomme : un fichier n'est jamais
     * à moitié écrit.
     *
     * @param array<string, string> $files chemin => contenu
     *
     * @throws SourceError
     */
    private static function write(array $files): void
    {
        $temporaries = [];
        foreach ($files as $path => $content) {
            $temporary = \dirname($path) . '/.' . basename($path) . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (file_put_contents($temporary, $content) !== \strlen($content)) {
                foreach ($temporaries as $written) {
                    @unlink($written);
                }
                @unlink($temporary);

                throw new SourceError('Écriture impossible : ' . $path . '.');
            }
            @chmod($temporary, 0644);
            $temporaries[$path] = $temporary;
        }
        foreach ($temporaries as $path => $temporary) {
            if (!rename($temporary, $path)) {
                throw new SourceError('Remplacement impossible : ' . $path . '.');
            }
        }
    }
}
