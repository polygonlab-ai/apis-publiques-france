<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Source des données et réglages, déclarés dans tools/sources.json : adresse du catalogue des API de data.gouv.fr,
 * limites de sa lecture (taille des pages, plafond de pages, pause, essais, délai, taille) et réglages du test des
 * adresses (délai, redirections, taille lue, politesse par hôte, durée maximale, seuils des garde-fous).
 */
final class Sources
{
    public const FILE = __DIR__ . '/../sources.json';

    /** Clé de la source dans data/source.json et dans le manifeste de collecte. */
    public const KEY = 'catalogue';

    private function __construct(
        public readonly string $source,
        public readonly string $page,
        public readonly string $catalogue,
        public readonly int $perPage,
        public readonly int $maxPages,
        public readonly int $pauseSeconds,
        public readonly int $attempts,
        public readonly int $timeoutSeconds,
        public readonly int $maxPageSize,
        public readonly int $maxDropPercent,
        public readonly int $probeTimeoutSeconds,
        public readonly int $maxRedirects,
        public readonly int $maxBodySize,
        public readonly int $hostIntervalSeconds,
        public readonly int $retryPauseSeconds,
        public readonly int $concurrency,
        public readonly int $maxConsecutiveFailures,
        public readonly int $maxProbeDurationSeconds,
        public readonly int $maxUnreachablePercent,
        public readonly int $maxChangedPercent,
        public readonly int $passes,
        public readonly int $minPasses,
    ) {}

    /**
     * @throws SourceError
     */
    public static function load(string $path = self::FILE): self
    {
        $data = JsonFile::read($path, 'tools/sources.json');
        $texts = [];
        foreach (['source', 'page', 'catalogue'] as $field) {
            $value = $data[$field] ?? null;
            if (!\is_string($value) || trim($value) === '') {
                throw new SourceError('tools/sources.json : champ « ' . $field . ' » (texte) attendu.');
            }
            $texts[$field] = $value;
        }
        foreach (['page', 'catalogue'] as $field) {
            if (preg_match('~\Ahttps://[^\s?#]+\z~', $texts[$field]) !== 1) {
                throw new SourceError('tools/sources.json : « ' . $field . ' » doit être une adresse https, sans paramètre.');
            }
        }
        $test = $data['test'] ?? null;
        if (!\is_array($test)) {
            throw new SourceError('tools/sources.json : objet « test » attendu.');
        }
        $minPasses = self::integer($test, 'test.passages_min', 'passages_min', 1, 52);
        $passes = self::integer($test, 'test.passages', 'passages', $minPasses, 52);

        return new self(
            $texts['source'],
            $texts['page'],
            $texts['catalogue'],
            self::integer($data, 'par_page', 'par_page', 1, 100),
            self::integer($data, 'pages_max', 'pages_max', 1, 10000),
            self::integer($data, 'pause_s', 'pause_s', 0, 60),
            self::integer($data, 'essais', 'essais', 1, 10),
            self::integer($data, 'delai_s', 'delai_s', 1, 600),
            self::integer($data, 'taille_max_page', 'taille_max_page', 1000, 100_000_000),
            self::integer($data, 'seuil_disparitions_pct', 'seuil_disparitions_pct', 1, 100),
            self::integer($test, 'test.delai_s', 'delai_s', 1, 120),
            self::integer($test, 'test.redirections_max', 'redirections_max', 0, 10),
            self::integer($test, 'test.taille_max_corps', 'taille_max_corps', 1024, 10_000_000),
            self::integer($test, 'test.intervalle_hote_s', 'intervalle_hote_s', 1, 60),
            self::integer($test, 'test.pause_second_essai_s', 'pause_second_essai_s', 1, 120),
            self::integer($test, 'test.simultanes', 'simultanes', 1, 50),
            self::integer($test, 'test.echecs_consecutifs_max', 'echecs_consecutifs_max', 1, 100),
            self::integer($test, 'test.duree_max_s', 'duree_max_s', 10, 86400),
            self::integer($test, 'test.seuil_injoignables_pct', 'seuil_injoignables_pct', 1, 100),
            self::integer($test, 'test.seuil_changements_pct', 'seuil_changements_pct', 1, 100),
            $passes,
            $minPasses,
        );
    }

    /**
     * Adresse lue du catalogue (sans paramètre de page).
     */
    public function listUrl(): string
    {
        return $this->catalogue;
    }

    /**
     * Adresse d'une page du catalogue.
     */
    public function pageUrl(int $number): string
    {
        return $this->catalogue . '?page_size=' . $this->perPage . '&page=' . $number;
    }

    /**
     * @param array<mixed> $data
     *
     * @throws SourceError
     */
    private static function integer(array $data, string $label, string $field, int $min, int $max): int
    {
        $value = $data[$field] ?? null;
        if (!\is_int($value) || $value < $min || $value > $max) {
            throw new SourceError(sprintf('tools/sources.json : « %s » doit être un entier de %d à %d.', $label, $min, $max));
        }

        return $value;
    }
}
