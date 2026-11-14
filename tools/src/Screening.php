<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Tri des API lues dans le catalogue, avant publication.
 *
 * Une API est écartée automatiquement, sans bloquer les autres, si l'une de ses valeurs publiées (identifiant,
 * titre, description, organisation, adresses, limites, mots-clés…) contient un mot interdit (option
 * --mots-interdits), une adresse IPv4, une adresse e-mail restée dans une adresse, un motif de secret (clé d'API,
 * jeton) ou un texte entre doubles accolades.
 *
 * Les adresses e-mail des textes ont déjà été remplacées à la lecture du catalogue (voir Catalogue).
 *
 * Une API écartée est citée par son identifiant et son titre, sauf si l'un d'eux est lui-même en cause : il devient
 * alors « [titre retiré xxxxxxxx] » ou « [id retiré xxxxxxxx] » (début de l'empreinte SHA-256 de l'identifiant), pour
 * que rien de refusé ne soit publié.
 *
 * @phpstan-type Discarded array{api: Api, id: string, title: string, reason: string}
 */
final class Screening
{
    public const IPV4 = '/(?<![\d.])((?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})(?!\.?\d)/';

    private const TEMPLATE = '/(?<!\$)\{\{[^{}\n]*\}\}/';

    /** Motifs de secrets (clés d'API, jetons). */
    private const SECRETS = [
        '/\bgh[pousr]_[A-Za-z0-9]{20,}/',
        '/\bgithub_pat_[A-Za-z0-9_]{20,}/',
        '/(?<![A-Za-z0-9_-])sk-[A-Za-z0-9_-]{20,}/',
        '/\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/',
        '/-{5}BEGIN/',
        '/\bxox[abposr]-[A-Za-z0-9-]{10,}/',
        '/\bxapp-[A-Za-z0-9-]{10,}/',
        '/\bSG\.[A-Za-z0-9_-]{16,}\.[A-Za-z0-9_-]{16,}/',
        '/\bAIza[0-9A-Za-z_-]{35}/',
        '/\b(?:sk|rk|pk)_(?:live|test)_[A-Za-z0-9]{16,}/',
        '/\bwhsec_[A-Za-z0-9]{20,}/',
        '/\bglpat-[A-Za-z0-9_-]{20,}/',
        '/\bnpm_[A-Za-z0-9]{36}\b/',
        '/\beyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}/',
        '~[a-z][a-z0-9+.-]*://[^/\s:@"\'<>]+:[^/\s@"\'<>]+@~i',
        '/\b(?:password|passwd|pwd|mot_de_passe|secret|api_?key|access_?key|token)\b["\']?\s*(?:=>|=|:)\s*["\']([^"\'\s]{12,})["\']/i',
    ];

    public function __construct(private readonly ForbiddenWords $forbiddenWords) {}

    /**
     * @param list<Api> $apis
     *
     * @return array{kept: list<Api>, discarded: list<Discarded>}
     */
    public function screen(array $apis): array
    {
        $kept = [];
        $discarded = [];
        foreach ($apis as $api) {
            $reason = null;
            foreach ($api->texts() as $field => $text) {
                $reason = $this->problem($text);
                if ($reason !== null) {
                    $reason .= ' (champ « ' . $field . ' »)';
                    break;
                }
            }
            if ($reason === null) {
                $kept[] = $api;
                continue;
            }
            $discarded[] = ['api' => $api, 'id' => $this->displayId($api), 'title' => $this->displayTitle($api), 'reason' => $reason];
        }

        return ['kept' => $kept, 'discarded' => $discarded];
    }

    /**
     * Titre publiable d'une API : son titre, ou « [titre retiré xxxxxxxx] » si le titre lui-même est refusé.
     */
    public function displayTitle(Api $api): string
    {
        return $this->problem($api->title) === null ? $api->title : '[titre retiré ' . substr(hash('sha256', $api->id), 0, 8) . ']';
    }

    /**
     * Identifiant publiable d'une API : son identifiant, ou « [id retiré xxxxxxxx] » s'il est lui-même refusé.
     */
    public function displayId(Api $api): string
    {
        return $this->problem($api->id) === null ? $api->id : '[id retiré ' . substr(hash('sha256', $api->id), 0, 8) . ']';
    }

    /**
     * Motif de refus d'une valeur, ou null si elle est publiable.
     */
    private function problem(string $text): ?string
    {
        if ($this->forbiddenWords->found($text)) {
            return 'mot interdit';
        }
        if (preg_match(self::IPV4, $text) === 1) {
            return 'adresse IPv4';
        }
        if (Text::hasEmail($text)) {
            return 'adresse e-mail';
        }
        foreach (self::SECRETS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return 'motif de clé d\'API ou de jeton';
            }
        }
        if (preg_match(self::TEMPLATE, $text) === 1) {
            return 'texte entre doubles accolades';
        }

        return null;
    }
}
