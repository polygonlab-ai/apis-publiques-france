<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Tests;

use PHPUnit\Framework\TestCase;
use PolygonLab\ApisPubliquesFrance\Outils\Api;
use PolygonLab\ApisPubliquesFrance\Outils\ForbiddenWords;
use PolygonLab\ApisPubliquesFrance\Outils\Screening;
use PolygonLab\ApisPubliquesFrance\Outils\SourceError;
use PolygonLab\ApisPubliquesFrance\Tests\Fixtures\Apis;
use PolygonLab\ApisPubliquesFrance\Tests\Fixtures\Workspace;

/**
 * Le tri des API avant publication : mot interdit, adresse IPv4, adresse e-mail restée dans une adresse, motif de
 * secret, texte entre doubles accolades. Une API écartée ne bloque pas les autres.
 */
final class ScreeningTest extends TestCase
{
    private Workspace $workspace;

    protected function setUp(): void
    {
        $this->workspace = new Workspace();
    }

    protected function tearDown(): void
    {
        $this->workspace->remove();
    }

    public function testAPublishableApiIsKept(): void
    {
        $screened = $this->screening()->screen([Apis::make(1, description: 'Une API sans rien de particulier, version 2.1.'), Apis::make(2)]);

        self::assertCount(2, $screened['kept']);
        self::assertSame([], $screened['discarded']);
    }

    public function testAForbiddenWordInTheDescriptionDiscardsTheApi(): void
    {
        $screened = $this->screening()->screen([Apis::make(1), Apis::make(2, description: 'Service du MotInterdit national.')]);

        self::assertSame(['essai-0001'], array_map(static fn(Api $api): string => $api->id, $screened['kept']));
        self::assertCount(1, $screened['discarded']);
        self::assertSame('mot interdit (champ « description »)', $screened['discarded'][0]['reason']);
        self::assertSame('API d\'essai n° 2', $screened['discarded'][0]['title']);
    }

    public function testAForbiddenWordInTheTitleIsNeverShown(): void
    {
        $api = Apis::make(3, title: 'API ' . Workspace::FORBIDDEN_WORD);

        $screened = $this->screening()->screen([$api]);

        self::assertSame('mot interdit (champ « titre »)', $screened['discarded'][0]['reason']);
        self::assertSame('[titre retiré ' . substr(hash('sha256', 'essai-0003'), 0, 8) . ']', $screened['discarded'][0]['title']);
    }

    public function testARefusedIdentifierIsNeverShown(): void
    {
        $api = new Api('essai-' . Workspace::FORBIDDEN_WORD, 'API au nom correct', null, null, 'ouvert', null, null, null, null, null, null, null, null, [], null);

        $screened = $this->screening()->screen([$api]);

        self::assertSame('mot interdit (champ « id »)', $screened['discarded'][0]['reason']);
        self::assertSame('[id retiré ' . substr(hash('sha256', $api->id), 0, 8) . ']', $screened['discarded'][0]['id']);
        self::assertSame('API au nom correct', $screened['discarded'][0]['title']);
    }

    public function testAForbiddenWordIsLookedForInEveryPublishedValue(): void
    {
        $screened = $this->screening()->screen([
            Apis::make(4, organisation: 'Agence ' . Workspace::FORBIDDEN_WORD),
            Apis::make(5, host: Workspace::FORBIDDEN_WORD . '.example.com'),
        ]);

        self::assertSame([], $screened['kept']);
        self::assertSame(['mot interdit (champ « organisation »)', 'mot interdit (champ « adresse »)'], array_column($screened['discarded'], 'reason'));
    }

    public function testAToleratedWritingIsKept(): void
    {
        $screened = $this->screening()->screen([Apis::make(6, description: 'Compatible motinterdit-autorise.')]);

        self::assertCount(1, $screened['kept']);
    }

    public function testWithoutForbiddenWordsNothingIsDiscardedForThem(): void
    {
        $screened = (new Screening(ForbiddenWords::none()))->screen([Apis::make(7, title: 'API ' . Workspace::FORBIDDEN_WORD)]);

        self::assertCount(1, $screened['kept']);
    }

    public function testAnIpv4AddressDiscardsTheApi(): void
    {
        $screened = $this->screening()->screen([
            Apis::make(8, description: 'Serveur de secours sur 192.0.2.44.'),
            Apis::make(9, host: implode('.', [203, 0, 113, 9])),
        ]);

        self::assertSame(['adresse IPv4 (champ « description »)', 'adresse IPv4 (champ « adresse »)'], array_column($screened['discarded'], 'reason'));
    }

    public function testASecretOrATemplateDiscardsTheApi(): void
    {
        $token = 'ghp_' . str_repeat('a1B2', 9);
        $screened = $this->screening()->screen([
            Apis::make(10, description: 'Jeton de démonstration : ' . $token),
            Apis::make(11, description: 'Appeler /v1/' . '{' . '{identifiant}' . '}' . '/detail'),
        ]);

        self::assertSame(['motif de clé d\'API ou de jeton (champ « description »)', 'texte entre doubles accolades (champ « description »)'], array_column($screened['discarded'], 'reason'));
    }

    public function testAnEmailLeftInAnAddressDiscardsTheApi(): void
    {
        $screened = $this->screening()->screen([Apis::make(12, host: 'api.example.com/contact@example.com')]);

        self::assertSame('adresse e-mail (champ « adresse »)', $screened['discarded'][0]['reason']);
    }

    public function testAnInvalidForbiddenWordsFileIsRefused(): void
    {
        file_put_contents($this->workspace->forbiddenWords, '{"mots": [{"mot": ""}]}');

        $this->expectException(SourceError::class);
        $this->expectExceptionMessage('mot n° 1 vide ou absent');
        ForbiddenWords::load($this->workspace->forbiddenWords);
    }

    private function screening(): Screening
    {
        return new Screening(ForbiddenWords::load($this->workspace->forbiddenWords));
    }
}
