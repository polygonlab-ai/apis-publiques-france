# Journal des changements

Toutes les évolutions notables de ce dépôt sont décrites ici : après la première publication, chaque changement
du catalogue (entrée, sortie, changement de fiche, de type d'accès ou de résultat de test) donne une version de
correctif, publiée automatiquement.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet respecte
le [versionnage sémantique](https://semver.org/lang/fr/).

## [0.1.1] - 2026-11-18 - Catalogue du 18/11/2026 : 9 entrées, 4 tests changés, 1 autre modification

### Entrées

- Acteurs de l'économie sociale et solidaire dédiés au réemploi en Bretagne : Observatoire de l'environnement en Bretagne, accès ouvert, test repond
- API OpenBase : dirigeants, bilans, procédures collectives, score et site web des entreprises françaises : OpenBase, accès ouvert, test erreur_client
- Karoto Data : API des prix de la voiture d'occasion (France, Allemagne) : Karoto, accès ouvert_avec_compte, test repond
- Sentinelle catnat : arrêtés de catastrophe naturelle par commune et délai de déclaration du sinistre : Amacunia, accès ouvert, test introuvable
- Sentinelle créances : procédures collectives du BODACC et date limite de déclaration de créance : Amacunia, accès ouvert, test introuvable
- Sentinelle oppositions : ventes de fonds et fusions du BODACC, avec la date limite d'opposition des créanciers : Amacunia, accès ouvert, test introuvable
- Sentinelle phyto : produits phytosanitaires retirés et délais de vente et d'utilisation : Amacunia, accès ouvert, test introuvable
- Sentinelle syndic : syndic d'une copropriété et fin de son mandat : Amacunia, accès ouvert, test introuvable
- Serveur MCP OpenBase : entreprises françaises pour les assistants IA : OpenBase, accès ouvert, test repond

### Changements de résultat de test

- API d'écriture de la base nationale de la règlementation de circulation : repond -> injoignable
- API de données immobilières (Pillr Data) : annonces de vente analysées, pré-marché, prix immobiliers, loyers et adresses : introuvable -> repond
- API données ouvertes de l'ONISEP : acces_refuse -> repond
- Flux DATEX II des restrictions de circulation : repond -> injoignable

### Autres modifications

- API Open Products Facts : documentation_machine_test

### Listes

- API publiques françaises : 1269
- Hôtes : 272
- Accès ouvert : 1106
- Accès ouvert_avec_compte : 44
- Accès restreint : 119
- Test repond : 1108
- Test acces_refuse : 21
- Test introuvable : 66
- Test erreur_client : 18
- Test erreur_serveur : 3
- Test injoignable : 18
- Test non_teste : 35

## [0.1.0] - 2026-11-14 - Première publication du catalogue

### Ajouté

- `data/apis.json` et `data/apis.csv` : toutes les API publiques du catalogue de data.gouv.fr, avec pour chacune sa fiche (titre, description courte, organisation, type d'accès, adresse, hôte, format, licence, documentation, demande d'accès, disponibilité déclarée, limites, mots-clés, page, dates) et le résultat du test de son adresse.
- Test hebdomadaire de chaque adresse : une requête `GET` sans identifiant, sept résultats possibles (`repond`, `acces_refuse`, `introuvable`, `erreur_client`, `erreur_serveur`, `injoignable`, `non_teste`), code HTTP et date du test.
- Test de la documentation lisible par une machine : `openapi`, `autre`, `injoignable` ou `absente`.
- Disponibilité observée : part des derniers passages (8 au plus, à partir de 3) où l'adresse a répondu ; `data/passages.csv` en garde le détail.
- `data/hotes.json` et `data/hotes.csv` : la lecture par hôte (nombre d'API, organisations, résultat du test, API qui répondent).
- `data/historique.csv` : entrées, sorties, changements de résultat de test et de type d'accès, API écartées.
- `data/source.json` : adresse lue, date de collecte, pages et entrées lues, adresses testées, durée du test, empreinte SHA-256 des données normalisées.
- `schema/apis.schema.json` et `schema/hotes.schema.json` : schémas JSON des deux fichiers principaux.
- `LISTE.md` : les API par organisation, page entièrement générée ; chiffres du catalogue générés dans le README.
- `tools/actualiser.php` : outil sans bibliothèque extérieure qui lit le catalogue, teste les adresses et réécrit les données ; collecte enregistrable et rejouable sans réseau.
- Politesse du test : une requête par seconde au plus par hôte, hôtes à tour de rôle, un second essai seulement après une erreur réseau, hôte en panne laissé tranquille.
- Sécurité du test : protocoles http et https seulement, aucune adresse IP ni adresse locale ou privée appelée, vérification refaite à chaque redirection.
- Aucune adresse e-mail ni point de contact publié ; API écartées automatiquement si une valeur contient une adresse IPv4, un motif de clé d'API ou un mot interdit.
- Garde-fous : rien n'est écrit devant une liste vide, une entrée sans champ obligatoire, plus de 20 % d'API disparues, plus de 30 % d'adresses injoignables ou plus de 25 % de résultats de test changés.

[0.1.1]: https://github.com/polygonlab-ai/apis-publiques-france/releases/tag/v0.1.1
[0.1.0]: https://github.com/polygonlab-ai/apis-publiques-france/releases/tag/v0.1.0

---
— Conçu et développé par PolygonLab · polygonlab.ai · Licence MIT
