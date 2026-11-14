<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Enregistrement des tests d'adresses d'une collecte (option --enregistrer), pour les rejouer avec --pages.
 *
 * Le fichier « sondes.json » garde, pour chaque adresse appelée, le code reçu, la redirection demandée, l'erreur
 * et le début du corps (2 000 octets au plus : assez pour reconnaître une description OpenAPI dans la plupart des
 * cas), et pour chaque hôte ses adresses IP. Ce fichier sert au rejeu local : il n'est jamais publié.
 */
final class ProbeRecorder implements Transport, Resolver
{
    private const BODY_LENGTH = 2000;

    /** @var array<string, list<string>> */
    private array $resolutions = [];

    /** @var array<string, array{code: int|null, redirection: string|null, corps: string, erreur: string|null}> */
    private array $responses = [];

    /** @var array<int, string> adresse de chaque requête en cours */
    private array $urls = [];

    public function __construct(
        private readonly Transport $transport,
        private readonly Resolver $resolver,
        private readonly string $directory,
    ) {}

    public function resolve(string $host): array
    {
        return $this->resolutions[$host] = $this->resolver->resolve($host);
    }

    public function start(int $id, ProbeRequest $request): void
    {
        $this->urls[$id] = $request->url;
        $this->transport->start($id, $request);
    }

    public function wait(float $seconds): array
    {
        $responses = $this->transport->wait($seconds);
        foreach ($responses as $id => $response) {
            $url = $this->urls[$id] ?? null;
            if ($url !== null) {
                $this->responses[$url] = [
                    'code' => $response->status,
                    'redirection' => $response->location,
                    'corps' => mb_convert_encoding(substr($response->body, 0, self::BODY_LENGTH), 'UTF-8', 'UTF-8'),
                    'erreur' => $response->error,
                ];
                unset($this->urls[$id]);
            }
        }

        return $responses;
    }

    /**
     * Écrit le fichier des tests enregistrés.
     *
     * @throws SourceError
     */
    public function save(): void
    {
        ksort($this->resolutions, SORT_STRING);
        ksort($this->responses, SORT_STRING);
        $content = JsonFile::encode(['resolutions' => $this->resolutions, 'reponses' => $this->responses]);
        $path = rtrim($this->directory, '/') . '/' . ProbeReplay::FILE;
        if (file_put_contents($path, $content) !== \strlen($content)) {
            throw new SourceError('Enregistrement impossible : ' . $path . '.');
        }
    }
}
