<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Lecture et écriture des fichiers du dossier data/.
 *
 *   apis.json       catalogue courant, clés dans un ordre fixe, API triées par titre puis identifiant
 *   apis.csv        même contenu, une ligne par API, séparateur « ; », UTF-8 avec BOM, listes aplaties (« a | b »)
 *   hotes.json      une entrée par hôte : nombre d'API, organisations, résultat du test, API qui répondent
 *   hotes.csv       même contenu, une ligne par hôte
 *   historique.csv  un événement par ligne (entrée, sortie, changement de résultat de test, changement de type
 *                   d'accès, écartée) ; ne fait que s'allonger
 *   passages.csv    résultat du test de chaque API aux derniers passages (8 au plus) : base de la disponibilité observée
 *   source.json     adresse lue, date de collecte, pages et entrées lues, adresses testées, durée du test, empreinte
 *                   SHA-256 des données normalisées
 *
 * Le rendu est déterministe : mêmes données, mêmes octets. Aucune adresse e-mail n'est écrite.
 *
 * @phpstan-type Event array{date: string, evenement: string, id: string, titre: string, detail: string}
 * @phpstan-type Host array{hote: string, nombre_apis: int, organisations: list<string>, resultat: string, nombre_qui_repondent: int}
 * @phpstan-type Passes array<string, array<string, string>>
 */
final class DataFiles
{
    public const APIS_JSON = 'apis.json';
    public const APIS_CSV = 'apis.csv';
    public const HOSTS_JSON = 'hotes.json';
    public const HOSTS_CSV = 'hotes.csv';
    public const HISTORY_CSV = 'historique.csv';
    public const PASSES_CSV = 'passages.csv';
    public const SOURCE_JSON = 'source.json';

    public const BOM = "\u{FEFF}";
    public const SEPARATOR = ';';
    public const LIST_SEPARATOR = ' | ';

    public const APIS_COLUMNS = [
        'id', 'titre', 'description', 'organisation', 'acces', 'adresse', 'hote', 'format', 'licence', 'documentation',
        'documentation_machine', 'documentation_machine_test', 'demande_acces', 'disponibilite_declaree',
        'disponibilite_observee', 'limites', 'mots_cles', 'page', 'cree_le', 'modifie_le', 'test_resultat',
        'test_code_http', 'test_https', 'teste_le',
    ];

    public const HOSTS_COLUMNS = ['hote', 'nombre_apis', 'organisations', 'resultat', 'nombre_qui_repondent'];

    public const HISTORY_COLUMNS = ['date', 'evenement', 'id', 'titre', 'detail'];

    public const PASSES_COLUMNS = ['date', 'id', 'resultat'];

    public const EVENT_ENTRY = 'entree';
    public const EVENT_EXIT = 'sortie';
    public const EVENT_TEST = 'changement_test';
    public const EVENT_ACCESS = 'changement_acces';
    public const EVENT_DISCARDED = 'ecartee';

    /**
     * Enregistrement publié d'une API.
     *
     * @return array<string, mixed>
     */
    public static function record(Api $api, ?float $observed, ?string $testedOn): array
    {
        $fiche = $api->fiche();

        return [
            'id' => $fiche['id'],
            'titre' => $fiche['titre'],
            'description' => $fiche['description'],
            'organisation' => $fiche['organisation'],
            'acces' => $fiche['acces'],
            'adresse' => $fiche['adresse'],
            'hote' => $fiche['hote'],
            'format' => $fiche['format'],
            'licence' => $fiche['licence'],
            'documentation' => $fiche['documentation'],
            'documentation_machine' => $fiche['documentation_machine'],
            'documentation_machine_test' => $fiche['documentation_machine_test'],
            'demande_acces' => $fiche['demande_acces'],
            'disponibilite_declaree' => $fiche['disponibilite_declaree'],
            'disponibilite_observee' => $observed,
            'limites' => $fiche['limites'],
            'mots_cles' => $fiche['mots_cles'],
            'page' => $fiche['page'],
            'cree_le' => $api->createdOn,
            'modifie_le' => $api->modifiedOn,
            'test' => [
                'resultat' => $api->test,
                'code_http' => $api->httpCode,
                'https' => Address::isHttps($api->address),
                'teste_le' => $api->test === TestClass::UNTESTED ? null : $testedOn,
            ],
        ];
    }

    /**
     * @param list<Api> $apis
     * @param array<string, float> $availability disponibilité observée, par identifiant (absente : null)
     */
    public static function apisJson(Sources $sources, ?string $sourceDate, array $apis, array $availability): string
    {
        /** @var array<string, int> $access */
        $access = array_fill_keys([...array_values(Api::ACCESS), Api::ACCESS_UNKNOWN], 0);
        /** @var array<string, int> $tests */
        $tests = array_fill_keys(TestClass::ALL, 0);
        $list = [];
        foreach (self::sorted($apis) as $api) {
            $access[$api->accessKey()] = ($access[$api->accessKey()] ?? 0) + 1;
            $tests[$api->test] = ($tests[$api->test] ?? 0) + 1;
            $list[] = self::record($api, $availability[$api->id] ?? null, $sourceDate);
        }

        return JsonFile::encode([
            'source' => $sources->source,
            'page' => $sources->page,
            'date_source' => $sourceDate,
            'nombre' => \count($list),
            'nombres' => ['acces' => $access, 'test' => $tests],
            'nombre_hotes' => \count(self::hosts($apis)),
            'apis' => $list,
        ]);
    }

    /**
     * @param list<Api> $apis
     * @param array<string, float> $availability
     */
    public static function apisCsv(?string $sourceDate, array $apis, array $availability): string
    {
        $lines = [self::csvLine(self::APIS_COLUMNS)];
        foreach (self::sorted($apis) as $api) {
            $https = Address::isHttps($api->address);
            $lines[] = self::csvLine([
                $api->id,
                $api->title,
                $api->description,
                $api->organisation,
                $api->access,
                $api->address,
                $api->host(),
                $api->format,
                $api->licence,
                $api->documentation,
                $api->machineDocumentation,
                $api->documentationTest,
                $api->accessRequest,
                self::number($api->declaredAvailability),
                self::number($availability[$api->id] ?? null),
                $api->limits,
                implode(self::LIST_SEPARATOR, $api->keywords),
                $api->page,
                $api->createdOn,
                $api->modifiedOn,
                $api->test,
                $api->httpCode === null ? null : (string) $api->httpCode,
                $https === null ? null : ($https ? 'oui' : 'non'),
                $api->test === TestClass::UNTESTED ? null : $sourceDate,
            ]);
        }

        return self::BOM . implode("\n", $lines) . "\n";
    }

    /**
     * Hôtes des API, du plus chargé au moins chargé puis par nom. Le résultat d'un hôte est la plus favorable des
     * classes de ses API ; « nombre_qui_repondent » compte ses API de classe « repond ».
     *
     * @param list<Api> $apis
     *
     * @return list<Host>
     */
    public static function hosts(array $apis): array
    {
        /** @var array<string, array{count: int, organisations: array<string, string>, classes: list<string>, responding: int}> $byHost */
        $byHost = [];
        foreach ($apis as $api) {
            $host = $api->host();
            if ($host === null) {
                continue;
            }
            $entry = $byHost[$host] ?? ['count' => 0, 'organisations' => [], 'classes' => [], 'responding' => 0];
            $entry['count']++;
            if ($api->organisation !== null) {
                $entry['organisations'][Text::sortKey($api->organisation) . "\0" . $api->organisation] = $api->organisation;
            }
            $entry['classes'][] = $api->test;
            $entry['responding'] += $api->test === TestClass::RESPONDS ? 1 : 0;
            $byHost[$host] = $entry;
        }
        $hosts = [];
        foreach ($byHost as $host => $entry) {
            ksort($entry['organisations'], SORT_STRING);
            $hosts[] = [
                'hote' => (string) $host,
                'nombre_apis' => $entry['count'],
                'organisations' => array_values($entry['organisations']),
                'resultat' => TestClass::best($entry['classes']),
                'nombre_qui_repondent' => $entry['responding'],
            ];
        }
        usort($hosts, static fn(array $a, array $b): int => [$b['nombre_apis'], $a['hote']] <=> [$a['nombre_apis'], $b['hote']]);

        return $hosts;
    }

    /**
     * @param list<Api> $apis
     */
    public static function hostsJson(Sources $sources, ?string $sourceDate, array $apis): string
    {
        $hosts = self::hosts($apis);

        return JsonFile::encode([
            'source' => $sources->source,
            'page' => $sources->page,
            'date_source' => $sourceDate,
            'nombre' => \count($hosts),
            'hotes' => $hosts,
        ]);
    }

    /**
     * @param list<Api> $apis
     */
    public static function hostsCsv(array $apis): string
    {
        $lines = [self::csvLine(self::HOSTS_COLUMNS)];
        foreach (self::hosts($apis) as $host) {
            $lines[] = self::csvLine([
                $host['hote'],
                (string) $host['nombre_apis'],
                implode(self::LIST_SEPARATOR, $host['organisations']),
                $host['resultat'],
                (string) $host['nombre_qui_repondent'],
            ]);
        }

        return self::BOM . implode("\n", $lines) . "\n";
    }

    /**
     * En-tête seul de l'historique (fichier vide).
     *
     * @return non-empty-string
     */
    public static function historyHeader(): string
    {
        return self::BOM . self::csvLine(self::HISTORY_COLUMNS) . "\n";
    }

    /**
     * Historique allongé des nouveaux événements.
     *
     * @param list<Event> $events
     *
     * @throws SourceError
     */
    public static function appendHistory(string $current, array $events): string
    {
        if ($current === '') {
            $current = self::historyHeader();
        }
        if (!str_starts_with($current, self::historyHeader())) {
            throw new SourceError('data/historique.csv : en-tête inattendu, fichier non modifié.');
        }
        if (!str_ends_with($current, "\n")) {
            $current .= "\n";
        }
        foreach ($events as $event) {
            $current .= self::csvLine([$event['date'], $event['evenement'], $event['id'], $event['titre'], $event['detail']]) . "\n";
        }

        return $current;
    }

    /**
     * Identifiants des API actuellement écartées d'après l'historique : dernier événement « ecartee ».
     *
     * @return list<string>
     *
     * @throws SourceError
     */
    public static function discardedIds(string $history): array
    {
        $last = [];
        foreach (self::rows($history, self::historyHeader(), \count(self::HISTORY_COLUMNS), 'data/historique.csv') as $cells) {
            $last[$cells[2]] = $cells[1];
        }
        $discarded = [];
        foreach ($last as $id => $event) {
            if ($event === self::EVENT_DISCARDED) {
                $discarded[] = (string) $id;
            }
        }

        return $discarded;
    }

    /**
     * En-tête seul du fichier des passages (fichier vide).
     *
     * @return non-empty-string
     */
    public static function passesHeader(): string
    {
        return self::BOM . self::csvLine(self::PASSES_COLUMNS) . "\n";
    }

    /**
     * Passages enregistrés : date => identifiant => classe du test, du plus ancien au plus récent.
     *
     * @return Passes
     *
     * @throws SourceError
     */
    public static function readPasses(string $content): array
    {
        $passes = [];
        foreach (self::rows($content, self::passesHeader(), \count(self::PASSES_COLUMNS), 'data/passages.csv') as $cells) {
            if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $cells[0]) !== 1 || !\in_array($cells[2], TestClass::ALL, true)) {
                throw new SourceError('data/passages.csv : ligne inattendue « ' . mb_strimwidth(implode(self::SEPARATOR, $cells), 0, 120, '…') . ' ».');
            }
            $passes[$cells[0]][$cells[1]] = $cells[2];
        }
        ksort($passes, SORT_STRING);

        return $passes;
    }

    /**
     * Passages allongés du passage du jour (qui remplace un passage de la même date), limités aux $max derniers.
     *
     * @param Passes $passes
     * @param list<Api> $apis
     *
     * @return Passes
     */
    public static function withPass(array $passes, string $date, array $apis, int $max): array
    {
        $results = [];
        foreach ($apis as $api) {
            $results[$api->id] = $api->test;
        }
        ksort($results, SORT_STRING);
        $passes[$date] = $results;
        ksort($passes, SORT_STRING);

        return \array_slice($passes, -$max, null, true);
    }

    /**
     * @param Passes $passes
     */
    public static function passesCsv(array $passes): string
    {
        $content = self::passesHeader();
        foreach ($passes as $date => $results) {
            foreach ($results as $id => $class) {
                $content .= self::csvLine([(string) $date, (string) $id, $class]) . "\n";
            }
        }

        return $content;
    }

    /**
     * Disponibilité observée de chaque API : part des passages enregistrés où son adresse a répondu (« repond » ou
     * « acces_refuse »), de 0 à 1, arrondie à deux décimales. Les passages où l'adresse n'a pas été testée ne
     * comptent pas ; une API testée à moins de $min passages n'a pas de valeur.
     *
     * @param Passes $passes
     *
     * @return array<string, float>
     */
    public static function availability(array $passes, int $min): array
    {
        $tested = [];
        $answered = [];
        foreach ($passes as $results) {
            foreach ($results as $id => $class) {
                if ($class === TestClass::UNTESTED) {
                    continue;
                }
                $tested[$id] = ($tested[$id] ?? 0) + 1;
                $answered[$id] = ($answered[$id] ?? 0) + (\in_array($class, TestClass::ANSWERED, true) ? 1 : 0);
            }
        }
        $availability = [];
        foreach ($tested as $id => $count) {
            if ($count >= $min) {
                $availability[(string) $id] = round(($answered[$id] ?? 0) / $count, 2);
            }
        }

        return $availability;
    }

    /**
     * API de data/apis.json, par identifiant.
     *
     * @return array<string, Api>
     *
     * @throws SourceError
     */
    public static function readApis(string $content): array
    {
        $label = 'data/' . self::APIS_JSON;
        $data = JsonFile::decode($content, $label);
        $list = $data['apis'] ?? null;
        if (!\is_array($list) || !array_is_list($list)) {
            throw new SourceError($label . ' : liste « apis » absente.');
        }
        $apis = [];
        foreach ($list as $record) {
            if (!\is_array($record)) {
                throw new SourceError($label . ' : enregistrement invalide.');
            }
            $api = Api::fromArray($record, $label);
            $apis[$api->id] = $api;
        }

        return $apis;
    }

    /**
     * Ligne CSV : valeur entre guillemets si elle contient le séparateur, un guillemet ou un retour à la ligne.
     *
     * @param list<string|null> $values
     */
    public static function csvLine(array $values): string
    {
        $cells = [];
        foreach ($values as $value) {
            $value ??= '';
            if (strpbrk($value, self::SEPARATOR . "\"\n\r") !== false) {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }
            $cells[] = $value;
        }

        return implode(self::SEPARATOR, $cells);
    }

    /**
     * API triées par titre (sans tenir compte des majuscules ni des accents), puis par identifiant.
     *
     * @param list<Api> $apis
     *
     * @return list<Api>
     */
    public static function sorted(array $apis): array
    {
        usort($apis, static fn(Api $a, Api $b): int => [Text::sortKey($a->title), $a->title, $a->id] <=> [Text::sortKey($b->title), $b->title, $b->id]);

        return $apis;
    }

    /**
     * Nombre écrit au plus court (« 99.9 », « 100 », « 0.88 »), ou null.
     */
    public static function number(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = number_format($value, 2, '.', '');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }

    /**
     * Lignes d'un fichier CSV de l'outil, après son en-tête.
     *
     * @return list<list<string>>
     *
     * @throws SourceError
     */
    private static function rows(string $content, string $header, int $columns, string $label): array
    {
        if ($content === '') {
            return [];
        }
        if (!str_starts_with($content, $header)) {
            throw new SourceError($label . ' : en-tête inattendu.');
        }
        $rows = [];
        foreach (explode("\n", substr($content, \strlen($header))) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = [];
            foreach (str_getcsv($line, self::SEPARATOR, '"', '') as $cell) {
                $cells[] = (string) $cell;
            }
            if (\count($cells) !== $columns) {
                throw new SourceError($label . ' : ligne illisible « ' . mb_strimwidth($line, 0, 120, '…') . ' ».');
            }
            $rows[] = $cells;
        }

        return $rows;
    }
}
