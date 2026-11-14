# Journal des changements

Toutes les évolutions notables de ce dépôt sont décrites ici : après la première publication, chaque changement
du catalogue (entrée, sortie, changement de fiche, de type d'accès ou de résultat de test) donne une version de
correctif, publiée automatiquement.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et le projet respecte
le [versionnage sémantique](https://semver.org/lang/fr/).

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

[0.1.0]: https://github.com/polygonlab-ai/apis-publiques-france/releases/tag/v0.1.0

---
— Conçu et développé par PolygonLab · polygonlab.ai · Licence MIT
