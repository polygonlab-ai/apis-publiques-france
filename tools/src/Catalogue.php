<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Lecture du catalogue des API de data.gouv.fr, page par page, et reprise de chaque entrée.
 *
 *   - pagination par numéro de page, jusqu'à ce que « next_page » soit vide ; pause entre deux pages ;
 *   - plafond de pages : atteint alors qu'une page suivante est annoncée, la lecture s'arrête, rien n'est écrit ;
 *   - une entrée sans « id » ou sans « title » arrête la lecture, entrée citée ;
 *   - tout autre champ est facultatif : absent ou de forme inattendue, il est ignoré ;
 *   - les entrées archivées, supprimées ou privées sont écartées ;
 *   - un « id » présent plusieurs fois : on garde l'entrée la plus récemment modifiée ;
 *   - aucune adresse e-mail n'est reprise : dans un texte elle est remplacée par « [adresse retirée] », et un lien
 *     qui en contient une (documentation, demande d'accès) est retiré.
 *
 * @phpstan-type Reading array{apis: list<Api>, pages: int, read: int, skipped: int, emails: array<string, list<string>>}
 */
final class Catalogue
{
    private const TIMEZONE = 'Europe/Paris';

    private const MAX_KEYWORDS = 50;

    private readonly \Closure $sleep;

    public function __construct(
        private readonly HttpClient $client,
        private readonly Sources $sources,
        ?\Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (int $seconds): void {
            sleep($seconds);
        };
    }

    /**
     * @return Reading API triées par identifiant, pages et entrées lues, entrées écartées (archivées, supprimées,
     *         privées), champs dont une adresse e-mail a été retirée (par identifiant)
     *
     * @throws SourceError
     */
    public function read(): array
    {
        $pages = 0;
        $read = 0;
        $skipped = 0;
        /** @var array<string, array{api: Api, modified: int, emails: list<string>}> $byId */
        $byId = [];
        $more = true;
        while ($more) {
            if ($pages >= $this->sources->maxPages) {
                throw new SourceError(sprintf('Plafond de %d pages atteint alors que le catalogue annonce encore une page suivante : lecture arrêtée (relever « pages_max » dans tools/sources.json si le catalogue a vraiment grandi).', $this->sources->maxPages));
            }
            if ($pages > 0 && $this->sources->pauseSeconds > 0) {
                ($this->sleep)($this->sources->pauseSeconds);
            }
            $pages++;
            [$entries, $more] = self::page($this->client->get($this->sources->pageUrl($pages)), $pages);
            foreach ($entries as $index => $raw) {
                $read++;
                $entry = self::entry($raw, $pages, $index + 1);
                if ($entry === null) {
                    $skipped++;
                    continue;
                }
                $current = $byId[$entry['api']->id] ?? null;
                if ($current === null || $entry['modified'] > $current['modified']) {
                    $byId[$entry['api']->id] = $entry;
                }
            }
            if ($entries === []) {
                $more = false;
            }
        }
        ksort($byId, SORT_STRING);
        $apis = [];
        $emails = [];
        foreach ($byId as $id => $entry) {
            $apis[] = $entry['api'];
            if ($entry['emails'] !== []) {
                $emails[(string) $id] = $entry['emails'];
            }
        }

        return ['apis' => $apis, 'pages' => $pages, 'read' => $read, 'skipped' => $skipped, 'emails' => $emails];
    }

    /**
     * Jour (AAAA-MM-JJ, heure de Paris) d'un horodatage ISO 8601 du catalogue, ou null s'il est absent ou illisible.
     */
    public static function day(?string $timestamp): ?string
    {
        if ($timestamp === null || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $timestamp) !== 1) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($timestamp))->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return array{0: list<mixed>, 1: bool} entrées de la page, présence d'une page suivante
     *
     * @throws SourceError
     */
    private static function page(string $body, int $number): array
    {
        try {
            $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new SourceError(sprintf('Page %d : réponse illisible (JSON invalide : %s).', $number, $error->getMessage()));
        }
        $entries = \is_array($data) ? ($data['data'] ?? null) : null;
        if (!\is_array($data) || !\is_array($entries) || !array_is_list($entries)) {
            throw new SourceError(sprintf('Page %d : liste « data » absente de la réponse.', $number));
        }
        $next = $data['next_page'] ?? null;

        return [$entries, \is_string($next) && $next !== ''];
    }

    /**
     * @return array{api: Api, modified: int, emails: list<string>}|null null pour une entrée écartée
     *
     * @throws SourceError
     */
    private static function entry(mixed $raw, int $page, int $index): ?array
    {
        if (!\is_array($raw)) {
            throw self::missing($raw, $page, $index, 'id', null);
        }
        $id = self::text($raw, 'id');
        if ($id === null || \strlen($id) > 100) {
            throw self::missing($raw, $page, $index, 'id', null);
        }
        if (($raw['archived_at'] ?? null) !== null || ($raw['deleted_at'] ?? null) !== null || ($raw['private'] ?? false) === true) {
            return null;
        }
        $title = self::text($raw, 'title');
        if ($title === null) {
            throw self::missing($raw, $page, $index, 'title', $id);
        }

        /** @var list<string> $emails */
        $emails = [];
        $text = static function (?string $value, string $field) use (&$emails): ?string {
            if ($value === null || !Text::hasEmail($value)) {
                return $value;
            }
            $emails[] = $field;

            return Text::withoutEmails($value);
        };
        $link = static function (?string $value, string $field) use (&$emails): ?string {
            if ($value === null || !Text::hasEmail($value)) {
                return $value;
            }
            $emails[] = $field;

            return null;
        };

        $organization = \is_array($raw['organization'] ?? null) ? $raw['organization'] : [];
        $access = $raw['access_type'] ?? null;
        $availability = $raw['availability'] ?? null;
        $description = \is_string($raw['description'] ?? null) ? Text::plain($raw['description']) : null;
        $limits = \is_string($raw['rate_limiting'] ?? null) ? Text::plain($raw['rate_limiting']) : null;
        $keywords = [];
        foreach (\is_array($raw['tags'] ?? null) ? $raw['tags'] : [] as $tag) {
            $tag = \is_string($tag) ? Text::clean($tag) : null;
            if ($tag !== null) {
                $keywords[] = Text::withoutEmails($tag);
            }
        }
        $keywords = array_values(array_unique($keywords));
        sort($keywords, SORT_STRING);
        $modified = self::text($raw, 'metadata_modified_at');
        $timestamp = $modified === null ? false : strtotime($modified);

        $api = new Api(
            $id,
            (string) $text($title, 'titre'),
            Text::truncate($text($description, 'description'), Api::DESCRIPTION_LENGTH),
            $text(self::text($organization, 'name'), 'organisation'),
            \is_string($access) ? (Api::ACCESS[$access] ?? null) : null,
            self::text($raw, 'base_api_url'),
            $text(self::text($raw, 'format'), 'format'),
            $text(self::text($raw, 'license'), 'licence'),
            $link(self::text($raw, 'technical_documentation_url'), 'documentation'),
            $link(self::text($raw, 'machine_documentation_url'), 'documentation_machine'),
            $link(self::text($raw, 'authorization_request_url'), 'demande_acces'),
            \is_int($availability) || \is_float($availability) ? round((float) $availability, 2) : null,
            Text::truncate($text($limits, 'limites'), Api::LIMITS_LENGTH),
            \array_slice($keywords, 0, self::MAX_KEYWORDS),
            self::text($raw, 'self_web_url'),
            TestClass::UNTESTED,
            null,
            TestClass::DOC_ABSENT,
            self::day(self::text($raw, 'created_at')),
            self::day($modified),
        );

        return ['api' => $api, 'modified' => $timestamp === false ? 0 : $timestamp, 'emails' => $emails];
    }

    private static function missing(mixed $raw, int $page, int $index, string $field, ?string $id): SourceError
    {
        return new SourceError(sprintf(
            'Page %d, entrée n° %d : champ obligatoire « %s » absent ou vide ; entrée : %s',
            $page,
            $index,
            $field,
            $id ?? mb_strimwidth(Text::withoutEmails((string) json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 300, '…'),
        ));
    }

    /**
     * Texte nettoyé d'un champ, ou null s'il est absent, vide ou d'un autre type.
     *
     * @param array<mixed> $data
     */
    private static function text(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;

        return \is_string($value) ? Text::clean($value) : null;
    }
}
