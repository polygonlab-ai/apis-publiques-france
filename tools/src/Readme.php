<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Chiffres du catalogue, générés dans le README entre deux balises de commentaire : date, nombre d'API et d'hôtes,
 * répartition par type d'accès et par résultat de test, organisations qui publient le plus d'API. Le reste du README
 * n'est jamais réécrit. Toute valeur venue du catalogue est échappée : aucun lien, aucune image ni aucun code HTML
 * ne peut être fabriqué par un nom d'organisation.
 */
final class Readme
{
    public const START = '<!-- catalogue:debut -->';
    public const END = '<!-- catalogue:fin -->';

    public const TOP_ORGANISATIONS = 20;

    public const ACCESS_LABELS = [
        'ouvert' => 'Ouvert',
        'ouvert_avec_compte' => 'Ouvert avec compte',
        'restreint' => 'Restreint',
        Api::ACCESS_UNKNOWN => 'Non précisé',
    ];

    public const TEST_LABELS = [
        TestClass::RESPONDS => 'Répond',
        TestClass::DENIED => 'Accès refusé',
        TestClass::NOT_FOUND => 'Introuvable',
        TestClass::CLIENT_ERROR => 'Erreur du client',
        TestClass::SERVER_ERROR => 'Erreur du serveur',
        TestClass::UNREACHABLE => 'Injoignable',
        TestClass::UNTESTED => 'Non testé',
    ];

    /**
     * Contenu placé entre les balises.
     *
     * @param list<Api> $apis
     */
    public static function block(?string $sourceDate, array $apis): string
    {
        if ($apis === [] || $sourceDate === null) {
            return "\n_Les chiffres seront publiés ici après la première lecture du catalogue._\n\n";
        }
        /** @var array<string, int> $access */
        $access = array_fill_keys(array_keys(self::ACCESS_LABELS), 0);
        /** @var array<string, int> $tests */
        $tests = array_fill_keys(array_keys(self::TEST_LABELS), 0);
        /** @var array<string, int> $organisations */
        $organisations = [];
        foreach ($apis as $api) {
            $access[$api->accessKey()] = ($access[$api->accessKey()] ?? 0) + 1;
            $tests[$api->test] = ($tests[$api->test] ?? 0) + 1;
            if ($api->organisation !== null) {
                $organisations[$api->organisation] = ($organisations[$api->organisation] ?? 0) + 1;
            }
        }
        $lines = [
            '',
            sprintf(
                'Catalogue du %s : %d API, %s, %s. Fichiers complets : [`data/apis.json`](data/apis.json) et [`data/apis.csv`](data/apis.csv). Liste par organisation : [`LISTE.md`](LISTE.md).',
                self::date($sourceDate),
                \count($apis),
                Updater::plural(\count(DataFiles::hosts($apis)), 'hôte', 'hôtes'),
                Updater::plural(\count($organisations), 'organisation', 'organisations'),
            ),
            '',
            '| Type d\'accès | Valeur | API |',
            '|---|---|---:|',
        ];
        foreach (self::ACCESS_LABELS as $key => $label) {
            if (($access[$key] ?? 0) > 0) {
                $lines[] = '| ' . $label . ' | `' . $key . '` | ' . $access[$key] . ' |';
            }
        }
        $lines[] = '';
        $lines[] = '| Résultat du test | Valeur | API |';
        $lines[] = '|---|---|---:|';
        foreach (self::TEST_LABELS as $key => $label) {
            $lines[] = '| ' . $label . ' | `' . $key . '` | ' . ($tests[$key] ?? 0) . ' |';
        }
        $lines[] = '';
        $lines[] = sprintf('Les %d organisations qui publient le plus d\'API :', min(self::TOP_ORGANISATIONS, \count($organisations)));
        $lines[] = '';
        $lines[] = '| Organisation | API |';
        $lines[] = '|---|---:|';
        foreach (\array_slice(self::ranked($organisations), 0, self::TOP_ORGANISATIONS) as [$name, $count]) {
            $lines[] = '| ' . Text::markdown($name) . ' | ' . $count . ' |';
        }

        return implode("\n", $lines) . "\n\n";
    }

    /**
     * README dont la partie entre les balises est remplacée.
     *
     * @throws SourceError
     */
    public static function replace(string $readme, string $block): string
    {
        $start = strpos($readme, self::START);
        $end = strpos($readme, self::END);
        if ($start === false || $end === false || $end < $start
            || substr_count($readme, self::START) !== 1 || substr_count($readme, self::END) !== 1) {
            throw new SourceError('README.md : balises « ' . self::START . ' » et « ' . self::END . ' » attendues, une fois chacune et dans cet ordre.');
        }

        return substr($readme, 0, $start + \strlen(self::START)) . $block . substr($readme, $end);
    }

    /**
     * Date affichée (JJ/MM/AAAA) d'une date AAAA-MM-JJ.
     */
    public static function date(string $sourceDate): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $sourceDate);

        return $date === false ? $sourceDate : $date->format('d/m/Y');
    }

    /**
     * Organisations de la plus fournie à la moins fournie, puis par ordre alphabétique.
     *
     * @param array<string, int> $organisations nom => nombre d'API
     *
     * @return list<array{0: string, 1: int}>
     */
    public static function ranked(array $organisations): array
    {
        $ranked = [];
        foreach ($organisations as $name => $count) {
            $ranked[] = [(string) $name, $count];
        }
        usort($ranked, static fn(array $a, array $b): int => [$b[1], Text::sortKey($a[0]), $a[0]] <=> [$a[1], Text::sortKey($b[0]), $b[0]]);

        return $ranked;
    }
}
