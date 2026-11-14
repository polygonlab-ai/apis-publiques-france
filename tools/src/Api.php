<?php

declare(strict_types=1);

namespace PolygonLab\ApisPubliquesFrance\Outils;

/**
 * Une API telle que reprise du catalogue : sa fiche (faits publiés par le producteur, nettoyés), le résultat du
 * test de son adresse et de sa documentation lisible par une machine, et ses dates.
 *
 * Ne sont jamais repris : les points de contact, le propriétaire, les droits, les mesures d'usage, les compléments
 * techniques du catalogue, ni aucune adresse e-mail.
 *
 * @phpstan-type Fiche array{id: string, titre: string, description: string|null, organisation: string|null, acces: string|null, adresse: string|null, hote: string|null, format: string|null, licence: string|null, documentation: string|null, documentation_machine: string|null, documentation_machine_test: string, demande_acces: string|null, disponibilite_declaree: float|null, limites: string|null, mots_cles: list<string>, page: string|null}
 */
final class Api
{
    /** Types d'accès du catalogue et leur valeur publiée. */
    public const ACCESS = ['open' => 'ouvert', 'open_with_account' => 'ouvert_avec_compte', 'restricted' => 'restreint'];

    /** Clé des compteurs pour un type d'accès absent ou inconnu (la valeur publiée est alors null). */
    public const ACCESS_UNKNOWN = 'non_precise';

    public const DESCRIPTION_LENGTH = 300;

    public const LIMITS_LENGTH = 200;

    /** Champs de la fiche comparés d'un passage à l'autre (ni les dates, ni le code HTTP, ni le résultat du test). */
    public const COMPARED = ['titre', 'description', 'organisation', 'adresse', 'format', 'licence', 'documentation', 'documentation_machine', 'documentation_machine_test', 'demande_acces', 'disponibilite_declaree', 'limites', 'mots_cles', 'page'];

    /**
     * @param list<string> $keywords
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $organisation,
        public readonly ?string $access,
        public readonly ?string $address,
        public readonly ?string $format,
        public readonly ?string $licence,
        public readonly ?string $documentation,
        public readonly ?string $machineDocumentation,
        public readonly ?string $accessRequest,
        public readonly ?float $declaredAvailability,
        public readonly ?string $limits,
        public readonly array $keywords,
        public readonly ?string $page,
        public readonly string $test = TestClass::UNTESTED,
        public readonly ?int $httpCode = null,
        public readonly string $documentationTest = TestClass::DOC_ABSENT,
        public readonly ?string $createdOn = null,
        public readonly ?string $modifiedOn = null,
    ) {}

    /**
     * Hôte de l'adresse (en minuscules), ou null.
     */
    public function host(): ?string
    {
        return Address::hostOf($this->address);
    }

    /**
     * Clé du type d'accès dans les compteurs.
     */
    public function accessKey(): string
    {
        return $this->access ?? self::ACCESS_UNKNOWN;
    }

    /**
     * Même API, avec le résultat de ses tests.
     */
    public function withTests(string $test, ?int $httpCode, string $documentationTest): self
    {
        return new self($this->id, $this->title, $this->description, $this->organisation, $this->access, $this->address, $this->format, $this->licence, $this->documentation, $this->machineDocumentation, $this->accessRequest, $this->declaredAvailability, $this->limits, $this->keywords, $this->page, $test, $httpCode, $documentationTest, $this->createdOn, $this->modifiedOn);
    }

    /**
     * Même API, avec les compléments qui ne font pas partie de l'empreinte : code HTTP et dates.
     */
    public function withComplements(?int $httpCode, ?string $createdOn, ?string $modifiedOn): self
    {
        return new self($this->id, $this->title, $this->description, $this->organisation, $this->access, $this->address, $this->format, $this->licence, $this->documentation, $this->machineDocumentation, $this->accessRequest, $this->declaredAvailability, $this->limits, $this->keywords, $this->page, $this->test, $httpCode, $this->documentationTest, $createdOn, $modifiedOn);
    }

    /**
     * Valeurs textuelles publiées, par champ (liste des mots-clés aplatie), pour les contrôles de Screening.
     *
     * @return array<string, string>
     */
    public function texts(): array
    {
        $texts = ['id' => $this->id, 'titre' => $this->title];
        $optional = [
            'description' => $this->description,
            'organisation' => $this->organisation,
            'adresse' => $this->address,
            'format' => $this->format,
            'licence' => $this->licence,
            'documentation' => $this->documentation,
            'documentation_machine' => $this->machineDocumentation,
            'demande_acces' => $this->accessRequest,
            'limites' => $this->limits,
            'page' => $this->page,
        ];
        foreach ($optional as $field => $value) {
            if ($value !== null) {
                $texts[$field] = $value;
            }
        }
        if ($this->keywords !== []) {
            $texts['mots_cles'] = implode(' | ', $this->keywords);
        }

        return $texts;
    }

    /**
     * Fiche de l'API, dans l'ordre du fichier publié.
     *
     * @return Fiche
     */
    public function fiche(): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->title,
            'description' => $this->description,
            'organisation' => $this->organisation,
            'acces' => $this->access,
            'adresse' => $this->address,
            'hote' => $this->host(),
            'format' => $this->format,
            'licence' => $this->licence,
            'documentation' => $this->documentation,
            'documentation_machine' => $this->machineDocumentation,
            'documentation_machine_test' => $this->documentationTest,
            'demande_acces' => $this->accessRequest,
            'disponibilite_declaree' => $this->declaredAvailability,
            'limites' => $this->limits,
            'mots_cles' => $this->keywords,
            'page' => $this->page,
        ];
    }

    /**
     * Forme normalisée de la collecte : la fiche et la classe du test, sans date ni code HTTP. C'est elle qui porte
     * l'empreinte : elle ne change que si l'API change de fiche ou de classe de test.
     *
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        return $this->fiche() + ['test' => ['resultat' => $this->test]];
    }

    /**
     * API relue d'un fichier de l'outil (collecte normalisée ou data/apis.json).
     *
     * @param array<mixed> $data
     *
     * @throws SourceError
     */
    public static function fromArray(array $data, string $label): self
    {
        $id = $data['id'] ?? null;
        $title = $data['titre'] ?? null;
        if (!\is_string($id) || $id === '' || !\is_string($title) || $title === '') {
            throw new SourceError($label . ' : API sans « id » ou sans « titre ».');
        }
        $texts = [];
        foreach (['description', 'organisation', 'acces', 'adresse', 'format', 'licence', 'documentation', 'documentation_machine', 'demande_acces', 'limites', 'page', 'cree_le', 'modifie_le'] as $field) {
            $value = $data[$field] ?? null;
            if ($value !== null && !\is_string($value)) {
                throw new SourceError($label . ' : « ' . $field . ' » de « ' . $id . ' » : texte ou null attendu.');
            }
            $texts[$field] = $value;
        }
        $availability = $data['disponibilite_declaree'] ?? null;
        if ($availability !== null && !\is_int($availability) && !\is_float($availability)) {
            throw new SourceError($label . ' : « disponibilite_declaree » de « ' . $id . ' » : nombre ou null attendu.');
        }
        $test = $data['test'] ?? null;
        $class = \is_array($test) ? ($test['resultat'] ?? null) : null;
        if (!\is_string($class) || !\in_array($class, TestClass::ALL, true)) {
            throw new SourceError($label . ' : « test.resultat » de « ' . $id . ' » : classe de test attendue.');
        }
        $code = $test['code_http'] ?? null;
        $documentationTest = $data['documentation_machine_test'] ?? null;
        if (!\is_string($documentationTest) || !\in_array($documentationTest, TestClass::DOC_ALL, true)) {
            throw new SourceError($label . ' : « documentation_machine_test » de « ' . $id . ' » inattendu.');
        }

        return new self(
            $id,
            $title,
            $texts['description'],
            $texts['organisation'],
            $texts['acces'],
            $texts['adresse'],
            $texts['format'],
            $texts['licence'],
            $texts['documentation'],
            $texts['documentation_machine'],
            $texts['demande_acces'],
            $availability === null ? null : (float) $availability,
            $texts['limites'],
            JsonFile::strings($data['mots_cles'] ?? [], $label . ', « mots_cles » de « ' . $id . ' »'),
            $texts['page'],
            $class,
            \is_int($code) ? $code : null,
            $documentationTest,
            $texts['cree_le'],
            $texts['modifie_le'],
        );
    }
}
