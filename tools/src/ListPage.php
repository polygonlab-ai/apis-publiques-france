<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Page LISTE.md, entièrement générée : les API regroupées par organisation, avec leur titre (lié à leur page du
 * catalogue), leur type d'accès et le résultat de leur test. Les portails de plus de 30 API (un même hôte qui publie
 * une API par jeu de données) sont repliés en une ligne par organisation : leur détail est dans data/apis.csv.
 *
 * Toute valeur venue du catalogue est échappée ; seule l'adresse https de la page du catalogue devient un lien.
 */
final class ListPage
{
    public const FILE = 'LISTE.md';

    /** Un hôte qui porte plus d'API que ce nombre est un portail, replié en une ligne. */
    public const FOLD_ABOVE = 30;

    public const NO_ORGANISATION = 'Sans organisation';

    private const SAFE_URL = '~\Ahttps://[^\s<>()\[\]|"\'`\\\\]+\z~';

    private const HEADER = "# Liste des API par organisation\n\n"
        . "> Page générée par `tools/actualiser.php` à chaque version du catalogue : ne pas la modifier à la main.\n"
        . "> Ce que mesure le test, et ses limites : voir le [README](README.md).\n";

    /**
     * @param list<Api> $apis
     */
    public static function render(?string $sourceDate, array $apis): string
    {
        if ($apis === [] || $sourceDate === null) {
            return self::HEADER . "\n_La liste sera publiée ici après la première lecture du catalogue._\n";
        }
        $perHost = [];
        /** @var array<string, list<Api>> $groups */
        $groups = [];
        $names = [];
        foreach (DataFiles::sorted($apis) as $api) {
            $host = $api->host();
            if ($host !== null) {
                $perHost[$host] = ($perHost[$host] ?? 0) + 1;
            }
            $name = $api->organisation ?? self::NO_ORGANISATION;
            $key = ($api->organisation === null ? '1' : '0') . Text::sortKey($name) . "\0" . $name;
            $groups[$key][] = $api;
            $names[$key] = $name;
        }
        ksort($groups, SORT_STRING);

        $lines = [
            self::HEADER,
            sprintf(
                'Catalogue du %s : %d API, %s. Les portails de plus de %d API sont repliés en une ligne ; leur détail est dans [`data/apis.csv`](data/apis.csv).',
                Readme::date($sourceDate),
                \count($apis),
                Updater::plural(\count(array_filter($names, static fn(string $name): bool => $name !== self::NO_ORGANISATION)), 'organisation', 'organisations'),
                self::FOLD_ABOVE,
            ),
        ];
        foreach ($groups as $key => $group) {
            $lines[] = '';
            $lines[] = '## ' . Text::markdown($names[$key] ?? self::NO_ORGANISATION) . ' (' . \count($group) . ')';
            $lines[] = '';
            $lines[] = '| API | Accès | Test |';
            $lines[] = '|---|---|---|';
            /** @var array<string, array{count: int, responding: int}> $folded */
            $folded = [];
            foreach ($group as $api) {
                $host = $api->host();
                if ($host !== null && ($perHost[$host] ?? 0) > self::FOLD_ABOVE) {
                    $entry = $folded[$host] ?? ['count' => 0, 'responding' => 0];
                    $entry['count']++;
                    $entry['responding'] += $api->test === TestClass::RESPONDS ? 1 : 0;
                    $folded[$host] = $entry;
                    continue;
                }
                $lines[] = '| ' . self::title($api) . ' | ' . (Readme::ACCESS_LABELS[$api->accessKey()] ?? $api->accessKey()) . ' | `' . $api->test . '` |';
            }
            ksort($folded, SORT_STRING);
            foreach ($folded as $host => $entry) {
                $lines[] = sprintf(
                    '| %d API sur `%s` : voir [`data/apis.csv`](data/apis.csv) | | %d sur %d répondent |',
                    $entry['count'],
                    (string) $host,
                    $entry['responding'],
                    $entry['count'],
                );
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private static function title(Api $api): string
    {
        $title = Text::markdown($api->title);

        return $api->page !== null && preg_match(self::SAFE_URL, $api->page) === 1 ? '[' . $title . '](' . $api->page . ')' : $title;
    }
}
