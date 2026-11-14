<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Classes du résultat d'un test d'adresse, et de la lecture d'une documentation lisible par une machine.
 *
 *   repond          l'adresse a répondu sans erreur (codes 2xx et 3xx)
 *   acces_refuse    identification demandée ou accès interdit (401, 403)
 *   introuvable     adresse inconnue du serveur (404, 410)
 *   erreur_client   autre refus de la demande (autres codes 4xx)
 *   erreur_serveur  erreur du serveur (codes 5xx)
 *   injoignable     aucune réponse : nom introuvable, connexion refusée, certificat refusé, délai dépassé
 *   non_teste       pas d'adresse, adresse invalide, adresse IP, hôte local ou privé : aucun appel n'est fait
 */
final class TestClass
{
    public const RESPONDS = 'repond';
    public const DENIED = 'acces_refuse';
    public const NOT_FOUND = 'introuvable';
    public const CLIENT_ERROR = 'erreur_client';
    public const SERVER_ERROR = 'erreur_serveur';
    public const UNREACHABLE = 'injoignable';
    public const UNTESTED = 'non_teste';

    /** Toutes les classes, dans l'ordre des tableaux publiés. */
    public const ALL = [
        self::RESPONDS,
        self::DENIED,
        self::NOT_FOUND,
        self::CLIENT_ERROR,
        self::SERVER_ERROR,
        self::UNREACHABLE,
        self::UNTESTED,
    ];

    /** Classes de la plus favorable à la moins favorable : le résultat d'un hôte est la meilleure de ses API. */
    private const RANK = [
        self::RESPONDS,
        self::DENIED,
        self::CLIENT_ERROR,
        self::NOT_FOUND,
        self::SERVER_ERROR,
        self::UNREACHABLE,
        self::UNTESTED,
    ];

    /** Classes qui comptent comme « l'adresse a répondu » dans la disponibilité observée. */
    public const ANSWERED = [self::RESPONDS, self::DENIED];

    public const DOC_OPENAPI = 'openapi';
    public const DOC_OTHER = 'autre';
    public const DOC_UNREACHABLE = 'injoignable';
    public const DOC_ABSENT = 'absente';

    public const DOC_ALL = [self::DOC_OPENAPI, self::DOC_OTHER, self::DOC_UNREACHABLE, self::DOC_ABSENT];

    /**
     * Classe d'un code de réponse HTTP.
     */
    public static function fromStatus(int $status): string
    {
        return match (true) {
            $status >= 200 && $status < 400 => self::RESPONDS,
            $status === 401, $status === 403 => self::DENIED,
            $status === 404, $status === 410 => self::NOT_FOUND,
            $status >= 400 && $status < 500 => self::CLIENT_ERROR,
            default => self::SERVER_ERROR,
        };
    }

    /**
     * Classe de la documentation lisible par une machine, d'après le résultat du test de son adresse.
     */
    public static function documentation(?Outcome $outcome): string
    {
        if ($outcome === null) {
            return self::DOC_ABSENT;
        }
        if ($outcome->class !== self::RESPONDS || $outcome->code === null || $outcome->code >= 300) {
            return self::DOC_UNREACHABLE;
        }

        return $outcome->openApi ? self::DOC_OPENAPI : self::DOC_OTHER;
    }

    /**
     * La plus favorable des classes données (résultat d'un hôte) ; « non_teste » pour une liste vide.
     *
     * @param list<string> $classes
     */
    public static function best(array $classes): string
    {
        foreach (self::RANK as $class) {
            if (\in_array($class, $classes, true)) {
                return $class;
            }
        }

        return self::UNTESTED;
    }
}
