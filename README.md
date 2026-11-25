```
       ▄▄▀▀▄▄        ____       _                         _          _
    ▄▀▀  ▄▄  ▀▀▄    |  _ \ ___ | |_   _  __ _  ___  _ __ | |    __ _| |__
    █  ▄▀  ▀▄  █    | |_) / _ \| | | | |/ _` |/ _ \| '_ \| |   / _` | '_ \
    █  ▀▄  ▄▀  █    |  __/ (_) | | |_| | (_| | (_) | | | | |__| (_| | |_) |
    ▀▄▄  ▀▀  ▄▄▀    |_|   \___/|_|\__, |\__, |\___/|_| |_|_____\__,_|_.__/
       ▀▀▄▄▀▀                     |___/ |___/
                                   polygonlab.ai
```

# apis-publiques-france

> Catalogue des API publiques françaises repris de data.gouv.fr, testé chaque semaine, en JSON et CSV, avec historique.

[![CI](https://github.com/polygonlab-ai/apis-publiques-france/actions/workflows/ci.yml/badge.svg)](https://github.com/polygonlab-ai/apis-publiques-france/actions/workflows/ci.yml)
[![Version](https://img.shields.io/github/v/release/polygonlab-ai/apis-publiques-france)](https://github.com/polygonlab-ai/apis-publiques-france/releases)
[![Licence](https://img.shields.io/badge/licence-MIT-blue)](LICENSE)

**Catalogue repris de data.gouv.fr. PolygonLab ne recommande aucune API et ne garantit pas leur fonctionnement.**

## Pourquoi

Le catalogue officiel des API publiques françaises est tenu sur data.gouv.fr. Il dit qui publie quoi, mais il ne dit
pas si une API répond encore, il ne se télécharge pas en un seul fichier simple, et il mêle des services distincts à
des centaines d'entrées d'un même portail (une entrée par jeu de données).

Ce dépôt apporte trois choses :

1. **un fichier complet**, en JSON (avec son schéma) et en CSV (qui s'ouvre directement dans Excel) ;
2. **le résultat d'un test hebdomadaire de chaque adresse** : répond-elle, refuse-t-elle l'accès, est-elle
   introuvable ou injoignable ? ;
3. **un historique** : entrées, sorties, changements de résultat de test et de type d'accès.

Il donne aussi les deux lectures du catalogue : **par API** (une ligne par entrée) et **par hôte** (une ligne par
serveur, avec le nombre d'API qu'il porte). Le catalogue est lu **chaque mercredi** ; une version est publiée dès
qu'une API entre, sort, change de fiche ou change de résultat de test.

## Fonctionnalités

- Toutes les entrées publiques du catalogue des API de data.gouv.fr, sans sélection.
- Pour chaque API : titre, description courte, organisation, type d'accès, adresse, hôte, format, licence,
  documentation, demande d'accès, disponibilité déclarée, limites d'usage, mots-clés, page du catalogue, dates.
- Test de chaque adresse, une fois par semaine : sept résultats possibles, code HTTP, date du test.
- Test de la documentation lisible par une machine : est-ce bien une description OpenAPI ?
- Disponibilité observée : part des derniers passages où l'adresse a répondu.
- Lecture par hôte : nombre d'API, organisations, résultat du test.
- Historique daté et traçabilité de la source (adresse lue, date, empreinte SHA-256).
- Schémas JSON des deux fichiers principaux, vérifiés à chaque publication.
- Outil d'actualisation en PHP, sans bibliothèque extérieure, utilisable par n'importe qui.
- Aucune adresse e-mail, aucun point de contact : seuls des faits sur les API sont repris.

## Le catalogue en chiffres

Chiffres générés à chaque version, à partir de `data/apis.json`. La liste des API par organisation est dans
[`LISTE.md`](LISTE.md).

<!-- catalogue:debut -->
Catalogue du 25/11/2026 : 1270 API, 274 hôtes, 206 organisations. Fichiers complets : [`data/apis.json`](data/apis.json) et [`data/apis.csv`](data/apis.csv). Liste par organisation : [`LISTE.md`](LISTE.md).

| Type d'accès | Valeur | API |
|---|---|---:|
| Ouvert | `ouvert` | 1107 |
| Ouvert avec compte | `ouvert_avec_compte` | 44 |
| Restreint | `restreint` | 119 |

| Résultat du test | Valeur | API |
|---|---|---:|
| Répond | `repond` | 1078 |
| Accès refusé | `acces_refuse` | 52 |
| Introuvable | `introuvable` | 67 |
| Erreur du client | `erreur_client` | 19 |
| Erreur du serveur | `erreur_serveur` | 3 |
| Injoignable | `injoignable` | 16 |
| Non testé | `non_teste` | 35 |

Les 20 organisations qui publient le plus d'API :

| Organisation | API |
|---|---:|
| Département des Côtes d'Armor | 201 |
| Grand Poitiers Open Data | 142 |
| ADEME | 113 |
| Direction interministérielle du numérique | 66 |
| Electricité de France | 54 |
| Observatoire de l'environnement en Bretagne | 36 |
| SAINT-QUENTIN-EN-YVELINES | 35 |
| Lig'Air | 32 |
| Institut national de l'information géographique et forestière | 23 |
| Météo-France | 21 |
| Lannion-Trégor Communauté | 20 |
| Agence ORE | 19 |
| France Travail | 13 |
| Hub’eau | 13 |
| Bureau de Recherches Géologiques et Minières | 12 |
| La Poste | 12 |
| Saintes Grandes Rives, l'Agglo | 12 |
| Ministère de la Transition écologique | 11 |
| Saint-Brieuc Armor Agglomération | 11 |
| Musée d'art et d'histoire - Ville de Saint-Brieuc | 10 |

<!-- catalogue:fin -->

## Installation

Rien à installer pour lire les données : les fichiers se téléchargent directement depuis GitHub.

| Fichier | Adresse directe |
|---|---|
| API (JSON) | `https://raw.githubusercontent.com/polygonlab-ai/apis-publiques-france/main/data/apis.json` |
| API (CSV) | `https://raw.githubusercontent.com/polygonlab-ai/apis-publiques-france/main/data/apis.csv` |
| Hôtes (JSON) | `https://raw.githubusercontent.com/polygonlab-ai/apis-publiques-france/main/data/hotes.json` |
| Hôtes (CSV) | `https://raw.githubusercontent.com/polygonlab-ai/apis-publiques-france/main/data/hotes.csv` |
| Historique (CSV) | `https://raw.githubusercontent.com/polygonlab-ai/apis-publiques-france/main/data/historique.csv` |
| Derniers passages (CSV) | `https://raw.githubusercontent.com/polygonlab-ai/apis-publiques-france/main/data/passages.csv` |
| Source (JSON) | `https://raw.githubusercontent.com/polygonlab-ai/apis-publiques-france/main/data/source.json` |

Pour figer une version, remplacer `main` par une étiquette, par exemple `v0.1.0`.

## Démarrage rapide

```php
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
```

Sortie attendue (avec un catalogue d'exemple) :

```text
3 API publiques françaises sur 2 hôtes, catalogue du 2026-11-18
API Adresse d'essai (Institut d'essai géographique) : https://api.example.com/adresse
```

## Utilisation

**En ligne de commande**, avec `curl` et `jq` : les API en accès ouvert qui ne répondent plus.

```bash
curl -s https://raw.githubusercontent.com/polygonlab-ai/apis-publiques-france/main/data/apis.json \
  | jq -r '.apis[] | select(.acces == "ouvert" and .test.resultat == "injoignable") | "\(.titre) : \(.adresse)"'
```

**Dans Excel** : ouvrir directement `data/apis.csv` ou `data/hotes.csv` (UTF-8 avec BOM, séparateur point-virgule).

**Suivre les changements** : `data/historique.csv` ne fait que s'allonger. Les dernières lignes disent ce qui a
changé depuis votre dernière lecture.

```bash
curl -s https://raw.githubusercontent.com/polygonlab-ai/apis-publiques-france/main/data/historique.csv | tail -n 20
```

**Être prévenu** : sur GitHub, « Watch », puis « Custom » et « Releases » : chaque changement du catalogue ou d'un
résultat de test donne une version.

**Signaler une erreur** : ouvrir un ticket (« Issues ») en donnant l'identifiant de l'API (`id`) et ce qui ne va
pas. Une fiche fausse ou incomplète se corrige à la source, sur data.gouv.fr, par l'organisation qui publie l'API :
la correction arrive ici au passage suivant. Un résultat de test qui vous semble faux nous intéresse : dites
depuis où et quand vous avez essayé.

## Ce que mesure le test

Une fois par semaine, l'outil envoie **une seule requête** à chaque adresse distincte (`adresse`), puis range la
réponse dans une classe :

| Résultat | Ce qui s'est passé |
|---|---|
| `repond` | L'adresse a répondu sans erreur (codes HTTP 2xx et 3xx) |
| `acces_refuse` | Le serveur demande une identification ou interdit l'accès (401, 403) |
| `introuvable` | Le serveur ne connaît pas cette adresse (404, 410) |
| `erreur_client` | Le serveur refuse la demande pour une autre raison (autres codes 4xx) |
| `erreur_serveur` | Le serveur est en erreur (codes 5xx) |
| `injoignable` | Aucune réponse : nom introuvable, connexion refusée, certificat refusé, délai dépassé |
| `non_teste` | Aucun appel : pas d'adresse, adresse invalide, adresse IP, hôte local ou privé |

**À lire honnêtement :**

- `repond` signifie que l'adresse a répondu sans erreur, **pas que l'API fonctionne**. L'adresse de base d'une API
  n'est pas toujours faite pour être appelée seule : certaines renvoient une page d'accueil, d'autres une erreur 404
  alors que leurs points d'accès marchent très bien.
- Une API restreinte qui marche répond `acces_refuse` : **c'est normal**, le test n'envoie aucun identifiant.
- Le test est fait **une fois par semaine, depuis un seul serveur situé en France**. Une panne de quelques heures,
  un blocage par pays ou une limite de débit peuvent donner un résultat différent du vôtre.
- Le temps de réponse n'est pas publié : il change à chaque passage et ne dirait rien de fiable.

**La requête** : `GET`, sans identifiant ni en-tête d'authentification, sans témoin (cookie), délai de 10 secondes,
3 redirections au plus, corps lu jusqu'à 64 Ko puis abandonné. L'outil se présente par son en-tête `User-Agent` :
`PolygonLab-actualisation-donnees (+https://github.com/polygonlab-ai/apis-publiques-france)`.

**La politesse** : une requête par seconde au plus vers un même hôte, jamais deux en même temps ; les hôtes passent
à tour de rôle. Un seul essai par adresse, plus un second après 5 secondes en cas d'erreur réseau seulement. Après
trois adresses de suite sans réponse sur un même hôte, les adresses restantes de cet hôte sont classées
`injoignable` sans être appelées : on n'insiste pas sur un serveur en panne.

**La sécurité** : seules les adresses `http` et `https` sont appelées, tous ports admis. L'outil n'appelle jamais une
adresse IP, un nom local, ni un nom qui résout vers une adresse locale, privée ou de lien local ; la vérification est
refaite à chaque redirection, et la connexion se fait vers l'adresse vérifiée, sans serveur mandataire. Ces adresses
sont classées `non_teste`.

**La documentation lisible par une machine** (`documentation_machine`) reçoit la même requête, avec la même
politesse. Résultat dans `documentation_machine_test` :

| Valeur | Sens |
|---|---|
| `openapi` | La réponse est un document JSON ou YAML dont la racine porte `openapi` ou `swagger` (cherché dans les 64 premiers Ko) |
| `autre` | L'adresse répond, avec un autre contenu (page web, autre format) |
| `injoignable` | La documentation n'a pas pu être lue : erreur réseau, code d'erreur HTTP ou adresse non testée |
| `absente` | Le catalogue ne donne pas d'adresse |

**La disponibilité observée** (`disponibilite_observee`) est la part des derniers passages enregistrés (8 au plus,
voir `data/passages.csv`) où l'adresse a répondu, c'est-à-dire `repond` ou `acces_refuse`. Elle va de 0 à 1 et vaut
`null` tant que l'adresse n'a pas été testée à 3 passages. Un passage n'est enregistré que s'il donne une version :
une semaine où rien ne change n'ajoute pas de passage. Elle ne se compare pas à la disponibilité **déclarée** par le
producteur (`disponibilite_declaree`), reprise telle quelle du catalogue.

## Règles de reprise

1. **Source** : toutes les pages du catalogue des API de data.gouv.fr, lues par son API publique, sans clé.
2. **Entrées écartées à la lecture** : les entrées archivées, supprimées ou privées. Une entrée présente plusieurs
   fois garde sa version la plus récemment modifiée.
3. **Champs jamais repris** : les points de contact, le propriétaire, les droits, les mesures d'usage, les
   informations de moissonnage, les badges et les compléments techniques du catalogue.
4. **Adresses e-mail** : aucune n'est publiée. Dans un texte (titre, description, limites…), elle est remplacée
   par « [adresse retirée] » ; un lien qui en contient une (documentation, demande d'accès) est retiré.
5. **Textes** : la description est donnée en texte brut (mise en forme retirée), coupée proprement à 300 caractères ;
   les limites d'usage à 200 caractères.
6. **Écartées automatiquement** : une API dont une valeur publiée contient une adresse IPv4, un motif de clé d'API
   ou de jeton, un texte entre doubles accolades, ou un mot de la liste de mots interdits tenue par PolygonLab. Elle
   est notée « écartée » dans l'historique ; si c'est son titre qui pose problème, elle y figure sous la forme
   « [titre retiré xxxxxxxx] ».
7. **Garde-fous** : l'outil s'arrête sans rien écrire devant une liste vide, une entrée sans `id` ou sans `title`
   (entrée citée), un plafond de pages atteint, plus de 20 % d'API disparues d'un coup, plus de 30 % d'adresses
   injoignables ou plus de 25 % de résultats de test changés d'un passage à l'autre (panne probable de notre côté).

Les autres valeurs sont celles du catalogue, sans modification, sauf les espaces en trop retirés. Les dates sont
données au jour (heure de Paris).

## Format des fichiers

### `data/apis.json`

Schéma : [`schema/apis.schema.json`](schema/apis.schema.json).

| Champ | Type | Contenu |
|---|---|---|
| `source` | texte | « data.gouv.fr, catalogue des API » |
| `page` | texte | Page du catalogue |
| `date_source` | date ou null | Date du passage (`AAAA-MM-JJ`, heure de Paris) |
| `nombre` | entier | Nombre total d'API |
| `nombres` | objet | Nombre d'API par type d'accès (`acces`) et par résultat de test (`test`) |
| `nombre_hotes` | entier | Nombre d'hôtes distincts |
| `apis` | liste | API, triées par titre puis par identifiant |

Chaque API :

| Champ | Type | Contenu |
|---|---|---|
| `id` | texte | Identifiant de l'API dans le catalogue, unique et stable |
| `titre` | texte | Titre donné par le producteur |
| `description` | texte ou null | Description en texte brut, 300 caractères au plus |
| `organisation` | texte ou null | Nom de l'organisation qui publie l'API |
| `acces` | texte ou null | `ouvert`, `ouvert_avec_compte` ou `restreint` ; null si le catalogue ne le dit pas |
| `adresse` | texte ou null | Adresse de base de l'API |
| `hote` | texte ou null | Hôte de l'adresse, en minuscules |
| `format` | texte ou null | Format déclaré (par exemple `REST`) |
| `licence` | texte ou null | Licence déclarée par le producteur |
| `documentation` | texte ou null | Adresse de la documentation technique |
| `documentation_machine` | texte ou null | Adresse de la documentation lisible par une machine |
| `documentation_machine_test` | texte | `openapi`, `autre`, `injoignable` ou `absente` |
| `demande_acces` | texte ou null | Adresse où demander l'accès |
| `disponibilite_declaree` | nombre ou null | Disponibilité déclarée par le producteur, en pourcentage |
| `disponibilite_observee` | nombre ou null | Part des derniers passages où l'adresse a répondu, de 0 à 1 |
| `limites` | texte ou null | Limites d'usage déclarées, 200 caractères au plus |
| `mots_cles` | liste | Mots-clés du catalogue, triés |
| `page` | texte ou null | Page de l'API sur data.gouv.fr |
| `cree_le` | date ou null | Création de la fiche |
| `modifie_le` | date ou null | Dernière modification de la fiche |
| `test` | objet | `resultat` (voir « Ce que mesure le test »), `code_http` (ou null), `https` (vrai, faux, ou null sans adresse), `teste_le` (date du passage, null si l'adresse n'a pas été testée) |

### `data/apis.csv`

Les mêmes API, une par ligne, dans le même ordre et avec les mêmes champs ; ceux du test deviennent les colonnes
`test_resultat`, `test_code_http`, `test_https` (`oui` ou `non`) et `teste_le`. Les mots-clés sont aplatis (séparés
par « | »). UTF-8 avec BOM, séparateur `;`, valeurs entre guillemets seulement si nécessaire.

### `data/hotes.json` et `data/hotes.csv`

Schéma : [`schema/hotes.schema.json`](schema/hotes.schema.json). Une entrée par hôte, du plus chargé au moins
chargé : `hote`, `nombre_apis`, `organisations` (noms, triés), `resultat` et `nombre_qui_repondent`. Le résultat d'un
hôte est le plus favorable de ceux de ses API (dans l'ordre `repond`, `acces_refuse`, `erreur_client`,
`introuvable`, `erreur_serveur`, `injoignable`, `non_teste`) : il dit si le serveur est en vie, pas si toutes ses API
marchent. `nombre_qui_repondent` compte ses API de résultat `repond`. Les API sans adresse n'ont pas d'hôte.

### `data/historique.csv`

Une ligne par événement : `date` (jour du passage), `evenement` (`entree`, `sortie`, `changement_test`,
`changement_acces`, `ecartee`), `id`, `titre`, `detail`. Le fichier ne fait que s'allonger.

### `data/passages.csv`

Résultat du test de chaque API aux derniers passages enregistrés (8 au plus) : `date`, `id`, `resultat`. C'est la
base de la disponibilité observée.

### `data/source.json`

Adresse lue, date de la collecte (`lu_le`) et date du catalogue (`date_source`), nombre de pages et d'entrées lues,
d'entrées écartées, d'adresses testées et de requêtes, durée du test en secondes, et l'empreinte SHA-256 des données
normalisées de la collecte (fiches et résultats de test, sans date ni code HTTP) : un horodatage qui change sans que
la fiche ou le résultat change ne donne pas de nouvelle version.

## Configuration

L'outil d'actualisation refait le catalogue à partir de data.gouv.fr, sans aucune bibliothèque à installer :

```bash
php tools/actualiser.php
```

| Option | Type | Valeur par défaut | Rôle |
|---|---|---|---|
| `--collecter <dossier>` | chemin | aucun | Collecte seule : fichiers écrits dans ce dossier (avec `--manifeste`) |
| `--manifeste <fichier.json>` | chemin | aucun | Avec `--collecter` : manifeste à écrire ; seul : collecte déjà faite à utiliser |
| `--donnees <dossier>` | chemin | `data/` | Dossier des données à mettre à jour |
| `--readme <fichier.md>` | chemin | `README.md` | README dont les chiffres sont réécrits |
| `--liste <fichier.md>` | chemin | `LISTE.md` | Page de la liste par organisation |
| `--resume <fichier.json>` | chemin | aucun | Résumé des changements, en JSON |
| `--mots-interdits <fichier.json>` | chemin | aucun | Mots qui écartent une API : `{"mots": [{"mot": "…", "tolerees": ["…"]}]}` |
| `--pages <dossier>` | chemin | aucun | Rejoue une collecte enregistrée (pages et tests), sans réseau |
| `--enregistrer <dossier>` | chemin | aucun | Enregistre les pages lues et les tests, pour les rejouer avec `--pages` |
| `--chargeur <fichier.php>` | chemin | `vendor/autoload.php` | Chargeur automatique à utiliser à la place de celui de Composer |

`tools/sources.json` règle la lecture du catalogue et le test :

| Clé | Valeur | Rôle |
|---|---|---|
| `catalogue` | `https://www.data.gouv.fr/api/1/dataservices/` | API lue |
| `par_page` | 100 | Entrées par page |
| `pages_max` | 50 | Plafond de pages : atteint, la lecture s'arrête sans rien écrire |
| `pause_s` | 1 | Pause entre deux pages (secondes) |
| `essais` | 3 | Essais par page du catalogue (pauses de 5 puis 15 secondes) |
| `delai_s` | 60 | Délai d'une page (secondes) |
| `taille_max_page` | 20000000 | Taille maximale d'une page (octets) |
| `seuil_disparitions_pct` | 20 | Part maximale des API qui peuvent disparaître d'un coup |
| `test.delai_s` | 10 | Délai d'une requête de test (secondes) |
| `test.redirections_max` | 3 | Redirections suivies |
| `test.taille_max_corps` | 65536 | Octets lus dans le corps d'une réponse |
| `test.intervalle_hote_s` | 1 | Intervalle entre deux requêtes vers un même hôte (secondes) |
| `test.pause_second_essai_s` | 5 | Pause avant le second essai, après une erreur réseau (secondes) |
| `test.simultanes` | 8 | Requêtes en cours au même moment, vers des hôtes différents |
| `test.echecs_consecutifs_max` | 3 | Adresses de suite sans réponse avant de ne plus appeler un hôte |
| `test.duree_max_s` | 3000 | Durée maximale du test : dépassée, la collecte s'arrête sans rien écrire |
| `test.seuil_injoignables_pct` | 30 | Part maximale d'adresses injoignables |
| `test.seuil_changements_pct` | 25 | Part maximale de résultats de test changés d'un passage à l'autre |
| `test.passages` | 8 | Passages gardés pour la disponibilité observée |
| `test.passages_min` | 3 | Passages nécessaires pour donner une disponibilité observée |

## Limites

- **Seul le catalogue de data.gouv.fr fait foi.** Ce dépôt en est une copie, lue chaque semaine.
- Une API absente du catalogue est absente d'ici : le dépôt ne cherche pas d'API ailleurs.
- Le test dit si une adresse répond, pas si l'API rend le bon service (voir « Ce que mesure le test »).
- Les fiches sont celles des producteurs : une adresse fausse dans le catalogue donne un résultat de test faux.
- Le catalogue compte une entrée par jeu de données pour certains portails : le nombre d'API est donc bien plus
  grand que le nombre de services. `data/hotes.csv` donne la lecture par serveur.
- Ce dépôt ne dit rien de la qualité, de la sécurité ni du sérieux d'une API.

## Source et licence des données

Source : data.gouv.fr, catalogue des API ([page du catalogue](https://www.data.gouv.fr/dataservices/)). Les titres,
descriptions et autres valeurs sont ceux que les producteurs ont publiés dans le catalogue ; la date du passage est
donnée par `date_source` et dans `data/source.json`. Les résultats de test sont produits par ce dépôt.

Licence de la source : Licence Ouverte 2.0 (Etalab), celle du « Catalogue des données de data.gouv.fr », qui comprend la
liste des API publiées. Mention à reprendre en cas de réutilisation : « Source : data.gouv.fr », avec la date du passage.

Le code (outil, schémas, tests) est sous licence MIT.

## Compatibilité

Outil d'actualisation : PHP 8.2, 8.3 et 8.4 (extensions curl, json et mbstring, aucune autre bibliothèque),
testés par l'intégration continue. Les données se lisent avec n'importe quel langage.

## Tests

```bash
composer install
composer test
```

Les tests utilisent de fausses réponses du catalogue et de faux serveurs, sans aucun appel réseau, et vérifient que
`data/` respecte les schémas.

## Contribuer

Voir [CONTRIBUTING.md](https://github.com/polygonlab-ai/.github/blob/main/CONTRIBUTING.md).

## Sécurité

Une faille se signale en privé, jamais dans un ticket public : voir
[SECURITY.md](https://github.com/polygonlab-ai/.github/blob/main/SECURITY.md).

## Licence

Code sous licence MIT : voir [LICENSE](LICENSE). Données : voir « Source et licence des données ».

---
— Conçu et développé par PolygonLab · polygonlab.ai · Licence MIT
