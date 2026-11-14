<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Rejeu des tests d'adresses d'une collecte enregistrée (option --pages), sans aucun appel réseau.
 *
 * Fichier « sondes.json » du dossier de la collecte :
 *   {"resolutions": {"api.example.com": ["192.0.2.10"]},
 *    "reponses": {"https://api.example.com/v1": {"code": 200, "redirection": null, "corps": "…", "erreur": null}}}
 *
 * Un hôte absent de « resolutions » reçoit une adresse publique d'exemple ; une liste vide rejoue un nom
 * introuvable. Une adresse absente de « reponses » rejoue une absence de réponse.
 */
final class ProbeReplay implements Transport, Resolver
{
    public const FILE = 'sondes.json';

    /** Adresse publique d'exemple (plage réservée à la documentation). */
    public const DEFAULT_IP = '192.0.2.1';

    /** @var array<int, ProbeResponse> */
    private array $pending = [];

    /** @var list<string> adresses demandées, dans l'ordre */
    public array $requests = [];

    /**
     * @param array<string, list<string>> $resolutions
     * @param array<string, ProbeResponse> $responses
     */
    public function __construct(
        private readonly array $resolutions,
        private readonly array $responses,
    ) {}

    /**
     * @throws SourceError
     */
    public static function load(string $directory): self
    {
        $path = rtrim($directory, '/') . '/' . self::FILE;
        if (!is_file($path)) {
            return new self([], []);
        }
        $label = 'Tests enregistrés (' . self::FILE . ')';
        $data = JsonFile::read($path, $label);
        $resolutions = [];
        foreach (\is_array($data['resolutions'] ?? null) ? $data['resolutions'] : [] as $host => $addresses) {
            $resolutions[strtolower((string) $host)] = $addresses === [] ? [] : JsonFile::strings($addresses, $label . ', résolution de « ' . $host . ' »');
        }
        $responses = [];
        foreach (\is_array($data['reponses'] ?? null) ? $data['reponses'] : [] as $url => $response) {
            if (!\is_array($response)) {
                throw new SourceError($label . ' : réponse illisible pour « ' . $url . ' ».');
            }
            $code = $response['code'] ?? null;
            $location = $response['redirection'] ?? null;
            $body = $response['corps'] ?? '';
            $error = $response['erreur'] ?? null;
            $responses[(string) $url] = new ProbeResponse(
                \is_int($code) ? $code : null,
                \is_string($location) ? $location : null,
                \is_string($body) ? $body : '',
                \is_string($error) ? $error : (\is_int($code) ? null : 'aucune réponse enregistrée'),
            );
        }

        return new self($resolutions, $responses);
    }

    public function resolve(string $host): array
    {
        return $this->resolutions[$host] ?? [self::DEFAULT_IP];
    }

    public function start(int $id, ProbeRequest $request): void
    {
        $this->requests[] = $request->url;
        $this->pending[$id] = $this->responses[$request->url] ?? ProbeResponse::failure('aucune réponse enregistrée');
    }

    public function wait(float $seconds): array
    {
        $responses = $this->pending;
        $this->pending = [];

        return $responses;
    }
}
