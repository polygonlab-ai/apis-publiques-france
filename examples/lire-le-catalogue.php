<?php

declare(strict_types=1);

// Catalogue publié sur GitHub (ou copie locale passée en argument).
$adresse = $argv[1] ?? 'https://raw.githubusercontent.com/polygonlab-ai/apis-publiques-france/main/data/apis.json';
/** @var array{nombre: int, nombre_hotes: int, date_source: string, apis: list<array{titre: string, organisation: ?string, acces: ?string, adresse: ?string, test: array{resultat: string, code_http: ?int}}>} $catalogue */
$catalogue = json_decode((string) file_get_contents($adresse), true, 16, JSON_THROW_ON_ERROR);

printf("%d API publiques françaises sur %d hôtes, catalogue du %s\n", $catalogue['nombre'], $catalogue['nombre_hotes'], $catalogue['date_source']);

// Les API en accès ouvert dont l'adresse a répondu au dernier test.
foreach ($catalogue['apis'] as $api) {
    if ($api['acces'] === 'ouvert' && $api['test']['resultat'] === 'repond') {
        printf("%s (%s) : %s\n", $api['titre'], $api['organisation'] ?? 'sans organisation', $api['adresse'] ?? '');
    }
}
