<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Compare le catalogue issu de la collecte à la version en place dans data/ et prépare les nouveaux fichiers, le
 * bloc du README, la page LISTE.md et le résumé des changements.
 *
 * Rien n'est écrit ici : Updater::run() rend le contenu des fichiers (vide si rien n'a changé) et le résumé, repris
 * par le journal des changements et le message d'information.
 *
 * Événements de l'historique : entrée, sortie, changement de résultat de test, changement de type d'accès, écartée
 * (mot interdit, adresse IPv4… : voir Screening).
 *
 * Garde-fous (SourceError, rien n'est écrit) : liste vide ; plus de 20 % des API disparaissent d'un coup ; plus de
 * 25 % des API présentes aux deux passages changent de classe de test (panne de notre côté probable).
 *
 * @phpstan-import-type Event from DataFiles
 * @phpstan-import-type Discarded from Screening
 * @phpstan-type Collected array{apis: list<Api>, sha256: string, size: int, pages: int, read: int, skipped: int, tested: int, requests: int, duration: int, emails: array<string, list<string>>, url: string, path: string}
 * @phpstan-type Section array{titre: string, elements: list<string>}
 */
final class Updater
{
    public const SUMMARY_FORMAT = 1;
    public const REPOSITORY = 'apis-publiques-france';
    public const TIMEZONE = 'Europe/Paris';

    /** Unité des garde-fous du script d'actualisation (« 300 API sur 1 000 disparaissent »). */
    public const UNIT = 'API';

    public const LIST_KEY = 'apis';
    public const LIST_LABEL = 'API publiques françaises';

    /** Nombre maximal d'éléments cités dans une section du résumé ; la suite est dans data/historique.csv. */
    private const MAX_ELEMENTS = 100;

    /** Longueur maximale du titre d'une version (limite du journal des changements). */
    private const MAX_TITLE = 100;

    public function __construct(
        private readonly Sources $sources,
        private readonly Screening $screening,
        private readonly string $dataDirectory,
    ) {}

    /**
     * @param Collected $collected
     *
     * @return array{changed: bool, files: array<string, string>, readme: string, list: string, summary: array<string, mixed>}
     *
     * @throws SourceError
     */
    public function run(array $collected, \DateTimeImmutable $now): array
    {
        $now = $now->setTimezone(new \DateTimeZone(self::TIMEZONE));
        $today = $now->format('Y-m-d');
        $previousSource = $this->previousSource();
        $previous = DataFiles::readApis($this->read(DataFiles::APIS_JSON));
        $history = $this->read(DataFiles::HISTORY_CSV);
        $passes = DataFiles::readPasses($this->read(DataFiles::PASSES_CSV));
        $firstRead = $previous === [] && ($previousSource['fichiers'] ?? []) === [];

        $screened = $this->screening->screen($collected['apis']);
        if ($screened['kept'] === []) {
            throw new SourceError(sprintf(
                'Liste vide : aucune API publiable parmi les %d lues dans le catalogue (%d écartée(s)).',
                \count($collected['apis']),
                \count($screened['discarded']),
            ));
        }
        $current = [];
        foreach ($screened['kept'] as $api) {
            $current[$api->id] = $api;
        }

        [$events, $others, $exits, $touched, $testChanges, $common] = $this->compare($previous, $current, $screened['discarded'], DataFiles::discardedIds($history), $today);
        $before = \count($previous);
        if ($before > 0 && $exits * 100 > $before * $this->sources->maxDropPercent) {
            throw new SourceError(sprintf(
                'Garde-fou : %d API sur %d disparaissent d\'un coup (%d %%, seuil %d %%).',
                $exits,
                $before,
                intdiv($exits * 100, $before),
                $this->sources->maxDropPercent,
            ));
        }
        if ($common > 0 && $testChanges * 100 > $common * $this->sources->maxChangedPercent) {
            throw new SourceError(sprintf(
                'Garde-fou : %d API sur %d changent de résultat de test d\'un passage à l\'autre (%d %%, seuil %d %%) : panne probable du côté de l\'outil ou du réseau.',
                $testChanges,
                $common,
                intdiv($testChanges * 100, $common),
                $this->sources->maxChangedPercent,
            ));
        }
        $changed = $firstRead || $events !== [] || $others !== [];

        $list = array_values($current);
        $summary = $this->summary($firstRead, $changed, $before, $list, $events, $others, $exits, $touched, $collected['emails'], $today, $now->format(DATE_ATOM));
        if (!$changed) {
            return ['changed' => false, 'files' => [], 'readme' => '', 'list' => '', 'summary' => $summary];
        }

        $passes = DataFiles::withPass($passes, $today, $list, $this->sources->passes);
        $availability = DataFiles::availability($passes, $this->sources->minPasses);

        return [
            'changed' => true,
            'files' => [
                DataFiles::APIS_JSON => DataFiles::apisJson($this->sources, $today, $list, $availability),
                DataFiles::APIS_CSV => DataFiles::apisCsv($today, $list, $availability),
                DataFiles::HOSTS_JSON => DataFiles::hostsJson($this->sources, $today, $list),
                DataFiles::HOSTS_CSV => DataFiles::hostsCsv($list),
                DataFiles::HISTORY_CSV => DataFiles::appendHistory($history, $events),
                DataFiles::PASSES_CSV => DataFiles::passesCsv($passes),
                DataFiles::SOURCE_JSON => JsonFile::encode([
                    'source' => $this->sources->source,
                    'page' => $this->sources->page,
                    'lu_le' => $now->format(DATE_ATOM),
                    'date_source' => $today,
                    'fichiers' => [[
                        'cle' => Sources::KEY,
                        'url' => $collected['url'],
                        'sha256' => $collected['sha256'],
                        'taille' => $collected['size'],
                        'pages' => $collected['pages'],
                        'entrees_lues' => $collected['read'],
                        'entrees_ecartees' => $collected['skipped'],
                        'apis_lues' => \count($collected['apis']),
                        'adresses_testees' => $collected['tested'],
                        'requetes' => $collected['requests'],
                        'duree_test_s' => $collected['duration'],
                    ]],
                ]),
            ],
            'readme' => Readme::block($today, $list),
            'list' => ListPage::render($today, $list),
            'summary' => $summary,
        ];
    }

    /**
     * Fichiers vides mais valides, pour un dépôt neuf.
     *
     * @return array<string, string>
     */
    public static function emptyFiles(Sources $sources): array
    {
        return [
            DataFiles::APIS_JSON => DataFiles::apisJson($sources, null, [], []),
            DataFiles::APIS_CSV => DataFiles::apisCsv(null, [], []),
            DataFiles::HOSTS_JSON => DataFiles::hostsJson($sources, null, []),
            DataFiles::HOSTS_CSV => DataFiles::hostsCsv([]),
            DataFiles::HISTORY_CSV => DataFiles::historyHeader(),
            DataFiles::PASSES_CSV => DataFiles::passesHeader(),
            DataFiles::SOURCE_JSON => JsonFile::encode([
                'source' => $sources->source,
                'page' => $sources->page,
                'lu_le' => null,
                'date_source' => null,
                'fichiers' => [],
            ]),
        ];
    }

    public static function plural(int $count, string $singular, string $plural): string
    {
        return $count . ' ' . ($count > 1 ? $plural : $singular);
    }

    /**
     * @param array<string, Api> $previous
     * @param array<string, Api> $current
     * @param list<Discarded> $discarded
     * @param list<string> $alreadyDiscarded
     *
     * @return array{0: list<Event>, 1: list<string>, 2: int, 3: list<string>, 4: int, 5: int} événements, autres
     *         modifications, retraits de la liste, identifiants des API entrées ou modifiées, changements de
     *         classe de test, API présentes aux deux passages
     */
    private function compare(array $previous, array $current, array $discarded, array $alreadyDiscarded, string $date): array
    {
        $events = [];
        $others = [];
        $touched = [];
        $testChanges = 0;
        $common = 0;
        foreach (DataFiles::sorted(array_values($current)) as $api) {
            $before = $previous[$api->id] ?? null;
            if ($before === null) {
                $events[] = self::event($date, DataFiles::EVENT_ENTRY, $api->id, $api->title, implode(', ', [$api->organisation ?? 'sans organisation', 'accès ' . $api->accessKey(), 'test ' . $api->test]));
                $touched[] = $api->id;
                continue;
            }
            $common++;
            if ($before->test !== $api->test) {
                $testChanges++;
                $events[] = self::event($date, DataFiles::EVENT_TEST, $api->id, $api->title, $before->test . ' -> ' . $api->test);
            }
            if ($before->access !== $api->access) {
                $events[] = self::event($date, DataFiles::EVENT_ACCESS, $api->id, $api->title, $before->accessKey() . ' -> ' . $api->accessKey());
            }
            $fields = [];
            $old = $before->fiche();
            $new = $api->fiche();
            foreach (Api::COMPARED as $field) {
                if ($old[$field] !== $new[$field]) {
                    $fields[] = $field;
                }
            }
            if ($fields !== []) {
                $others[] = $api->title . ' : ' . implode(', ', $fields);
                $touched[] = $api->id;
            }
        }

        $discardedById = [];
        foreach ($discarded as $item) {
            $discardedById[$item['api']->id] = $item;
        }
        $exits = 0;
        foreach (DataFiles::sorted(array_values(array_diff_key($previous, $current))) as $api) {
            $exits++;
            $item = $discardedById[$api->id] ?? null;
            $events[] = $item === null
                ? self::event($date, DataFiles::EVENT_EXIT, $api->id, $api->title, 'absente du catalogue')
                : self::event($date, DataFiles::EVENT_DISCARDED, $item['id'], $item['title'], 'écartée : ' . $item['reason']);
        }
        foreach ($discarded as $item) {
            if (!isset($previous[$item['api']->id]) && !\in_array($item['id'], $alreadyDiscarded, true)) {
                $events[] = self::event($date, DataFiles::EVENT_DISCARDED, $item['id'], $item['title'], 'écartée : ' . $item['reason']);
            }
        }

        return [$events, $others, $exits, $touched, $testChanges, $common];
    }

    /**
     * @return Event
     */
    private static function event(string $date, string $type, string $id, string $title, string $detail): array
    {
        return ['date' => $date, 'evenement' => $type, 'id' => $id, 'titre' => $title, 'detail' => $detail];
    }

    /**
     * Résumé des changements : compteurs (garde-fous), sections (journal des changements, message), titre.
     *
     * @param list<Api> $current
     * @param list<Event> $events
     * @param list<string> $others
     * @param list<string> $touched
     * @param array<string, list<string>> $emails champs dont une adresse e-mail a été retirée, par identifiant
     *
     * @return array<string, mixed>
     */
    private function summary(bool $firstRead, bool $changed, int $before, array $current, array $events, array $others, int $exits, array $touched, array $emails, string $sourceDate, string $readAt): array
    {
        /** @var array<string, list<string>> $byType */
        $byType = array_fill_keys([DataFiles::EVENT_ENTRY, DataFiles::EVENT_EXIT, DataFiles::EVENT_TEST, DataFiles::EVENT_ACCESS, DataFiles::EVENT_DISCARDED], []);
        foreach ($events as $event) {
            $byType[$event['evenement']][] = $event['titre'] . ' : ' . $event['detail'];
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $sourceDate);
        $displayDate = $date === false ? $sourceDate : $date->format('d/m/Y');
        $hosts = \count(DataFiles::hosts($current));

        $sections = [];
        if ($firstRead) {
            $title = sprintf('Première lecture du catalogue du %s : %s, %s', $displayDate, \count($current) . ' API', self::plural($hosts, 'hôte', 'hôtes'));
            if ($byType[DataFiles::EVENT_DISCARDED] !== []) {
                $sections[] = self::section('Écartées automatiquement', $byType[DataFiles::EVENT_DISCARDED]);
            }
        } else {
            $parts = [];
            $labels = [
                DataFiles::EVENT_ENTRY => ['entrée', 'entrées', 'Entrées'],
                DataFiles::EVENT_EXIT => ['sortie', 'sorties', 'Sorties'],
                DataFiles::EVENT_TEST => ['test changé', 'tests changés', 'Changements de résultat de test'],
                DataFiles::EVENT_ACCESS => ['accès changé', 'accès changés', 'Changements de type d\'accès'],
                DataFiles::EVENT_DISCARDED => ['écartée', 'écartées', 'Écartées automatiquement'],
            ];
            foreach ($labels as $type => [$singular, $pluralLabel, $sectionTitle]) {
                if ($byType[$type] !== []) {
                    $parts[] = self::plural(\count($byType[$type]), $singular, $pluralLabel);
                    $sections[] = self::section($sectionTitle, $byType[$type]);
                }
            }
            if ($others !== []) {
                $parts[] = self::plural(\count($others), 'autre modification', 'autres modifications');
                $sections[] = self::section('Autres modifications', $others, false);
            }
            $title = $changed
                ? sprintf('Catalogue du %s : %s', $displayDate, implode(', ', $parts))
                : sprintf('Catalogue du %s : aucun changement', $displayDate);
            if (mb_strlen($title) > self::MAX_TITLE) {
                $title = sprintf('Catalogue du %s : %d changements', $displayDate, \count($events) + \count($others));
            }
        }

        $remarks = [];
        foreach ($current as $api) {
            $fields = $emails[$api->id] ?? [];
            if ($fields !== [] && \in_array($api->id, $touched, true)) {
                $remarks[] = 'Adresse e-mail retirée (' . implode(', ', $fields) . ') : ' . $api->title;
            }
        }
        if ($remarks !== []) {
            $sections[] = self::section('Remarques', $remarks);
        }

        $access = [];
        $tests = [];
        foreach ($current as $api) {
            $access[$api->accessKey()] = ($access[$api->accessKey()] ?? 0) + 1;
            $tests[$api->test] = ($tests[$api->test] ?? 0) + 1;
        }
        $lists = [self::LIST_LABEL . ' : ' . \count($current), 'Hôtes : ' . $hosts];
        foreach ([...array_values(Api::ACCESS), Api::ACCESS_UNKNOWN] as $key) {
            if (isset($access[$key])) {
                $lists[] = 'Accès ' . $key . ' : ' . $access[$key];
            }
        }
        foreach (TestClass::ALL as $class) {
            if (isset($tests[$class])) {
                $lists[] = 'Test ' . $class . ' : ' . $tests[$class];
            }
        }
        $sections[] = ['titre' => 'Listes', 'elements' => $lists];

        $text = [$title];
        foreach ($sections as $section) {
            $text[] = '';
            $text[] = $section['titre'];
            foreach ($section['elements'] as $element) {
                $text[] = '- ' . $element;
            }
        }

        return [
            'format' => self::SUMMARY_FORMAT,
            'depot' => self::REPOSITORY,
            'change' => $changed,
            'premiere_lecture' => $firstRead,
            'date_source' => $sourceDate,
            'lu_le' => $readAt,
            'avant' => $before,
            'apres' => \count($current),
            'retraits' => $exits,
            'unite' => self::UNIT,
            'listes' => [['cle' => self::LIST_KEY, 'libelle' => self::LIST_LABEL, 'nombre' => \count($current)]],
            'titre' => $title,
            'sections' => $sections,
            'texte' => implode("\n", $text),
        ];
    }

    /**
     * Section du résumé, limitée à ses premiers éléments ; le détail complet des événements est dans
     * data/historique.csv.
     *
     * @param list<string> $elements
     *
     * @return Section
     */
    private static function section(string $title, array $elements, bool $inHistory = true): array
    {
        $count = \count($elements);
        if ($count > self::MAX_ELEMENTS) {
            $elements = \array_slice($elements, 0, self::MAX_ELEMENTS);
            $elements[] = sprintf('… et %d autres', $count - self::MAX_ELEMENTS) . ($inHistory ? ' : voir data/historique.csv' : '');
        }

        return ['titre' => $title . ($count > self::MAX_ELEMENTS ? ' (' . $count . ')' : ''), 'elements' => $elements];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SourceError
     */
    private function previousSource(): array
    {
        $content = $this->read(DataFiles::SOURCE_JSON);

        return $content === '' ? [] : JsonFile::decode($content, 'data/' . DataFiles::SOURCE_JSON);
    }

    private function read(string $name): string
    {
        $path = $this->dataDirectory . '/' . $name;
        if (!is_file($path)) {
            return $name === DataFiles::APIS_JSON ? '{"apis": []}' : '';
        }

        return (string) file_get_contents($path);
    }
}
