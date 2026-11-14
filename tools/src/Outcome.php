<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Résultat du test d'une adresse : classe (voir TestClass), code HTTP de la dernière réponse reçue (null si aucune),
 * présence d'une description OpenAPI dans le corps lu, et raison en clair (jamais publiée, utile aux messages).
 */
final class Outcome
{
    public function __construct(
        public readonly string $class,
        public readonly ?int $code,
        public readonly bool $openApi,
        public readonly string $detail,
    ) {}

    public static function untested(string $reason): self
    {
        return new self(TestClass::UNTESTED, null, false, $reason);
    }

    public static function unreachable(string $reason): self
    {
        return new self(TestClass::UNREACHABLE, null, false, $reason);
    }
}
