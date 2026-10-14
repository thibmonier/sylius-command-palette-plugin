# Audit qualité — sylius-command-palette-plugin

> Généré le 2026-10-14 · Branche `chore/quality-tooling` · Outillage claude-craft (ECS, PHPStan, Rector, PHPUnit) exécuté via Docker `ghcr.io/sylius/sylius-php:8.4-alpine`. La revue qualitative (dimensions PHP/Archi, Symfony/Sylius, Sécurité) a été effectuée directement par l'orchestrateur (lecture fichier par fichier de l'intégralité de `src/`).

## 1. Audit factuel (outils)

| Contrôle | Commande | Résultat |
|---|---|---|
| Style (ECS) | `vendor/bin/ecs check --no-progress-bar` | ✅ 0 erreur |
| Analyse statique (PHPStan **level max**) | `vendor/bin/phpstan analyse --no-progress` | ✅ 0 erreur |
| Tests unitaires | `vendor/bin/phpunit --testsuite=unit --no-coverage` | ✅ 61 tests, 573 assertions |
| Validation composer | `composer validate --strict` | ✅ valide |
| Modernisation (Rector, dry-run) | `vendor/bin/rector process --dry-run` | ⚠️ 11 fichiers avec suggestions |
| Vulnérabilités dépendances | `composer audit` | ⚠️ 2 paquets abandonnés (`behat/transliterator`, `php-http/message-factory`) — aucune advisory CVE |
| Tests `non-unit` (functional/integration) | — | ⏭️ non exécutés localement (DB + app Sylius requise) — couverts par le CI |

**Rector** vient d'être ajouté (`rector/rector ^2.0`, `rector.php`, cibles `make rector` / `make rector-fix`). Les 11 fichiers signalés relèvent d'une seule règle : `AddArrayFunctionClosureParamTypeRector` — ajout de types sur les paramètres de fermetures dans `array_map` / `array_filter`. Ce sont des **suggestions de modernisation non bloquantes**, non appliquées. Lancer `make rector-fix` pour les appliquer puis relancer ECS/PHPStan/tests.

**Paquets abandonnés :** `behat/transliterator` et `php-http/message-factory` sont des transitivités de `sylius/test-application` — non maîtrisables dans ce plugin. À surveiller lors de la montée de version de `sylius/test-application`.

## 2. Audit qualitatif (revue par l'orchestrateur)

| Dimension | Score | Synthèse |
|---|---|---|
| PHP / Clean Archi / SOLID | **9/10** | Typage strict, classes `final`, `readonly`, value object immuable, ISP minimal, OCP via tags. |
| Intégration Symfony / Sylius | **9/10** | DI PHP propre, tagged_iterator avec priorités, monolog channel dédié, bundle conforme. |
| Sécurité (OWASP 2025) | **9/10** | Double enforcement du rôle, DQL 100 % paramétré, LIKE sécurisé, cache no-store, XSS absent. |

### Points forts

- `declare(strict_types=1)` + `final` + `readonly` sur tous les fichiers `src/`.
- `SearchResult` : value object immuable implémentant `\JsonSerializable` — données cloisonnées et sérialisables sans fuite.
- `LikePattern` : classe dédiée à la construction des patterns `LIKE` avec échappement du caractère `!` sur MySQL, MariaDB et PostgreSQL. Usage cohérent dans tous les providers.
- `CommandPaletteSearch::search()` : resilience complète — `\Throwable` catchée par provider, log structuré, la palette ne se brise jamais à cause d'un provider tiers défaillant.
- `SearchController` : double guard (firewall + `isGranted`), réponse `private` + `no-store`, parsing défensif de `?q` (gère `q[]` sans 400).
- `AdminPageCatalog` : lazy initialization, filtrage des liens externes et `_blank`, déduplication par URI.
- `SearchProviderRegistry` : validation stricte du format de la clé (`[a-z0-9_]+`), détection de collision à la construction.
- Twig : rendu d'icônes via `<template>` clonés côté JS — aucun `innerHTML` avec donnée utilisateur.

### Findings par sévérité

#### PHP / Clean Archi / SOLID

🟡 `src/Page/AdminPageCatalog.php:82` — La méthode `collect()` utilise un passage par référence (`array &$pages`). PHP 8.x préfère retourner un tableau ou utiliser un objet accumulateur, plus lisible et sans effet de bord implicite.

🟡 `src/Search/Provider/Row.php:31` — `Row::string()` retourne `null` pour les valeurs booléennes sans lever d'exception ni logger. Un `true` en base de données devient silencieusement `null` dans le résultat. Comportement documenté via le type de retour mais potentiellement surprenant pour un auteur de provider tiers.

🟡 `src/Twig/CommandPaletteExtension.php:33` — Le paramètre `$extraIcons` est typé `array` sans généric PHPDoc (`list<string>`). PHPStan accepte cela (l'ignore `missingType.iterableValue` est en place) mais une annotation `@param list<string>` renforcerait la précision.

#### Intégration Symfony / Sylius

🟡 `phpstan.neon:11` — `Configuration.php` est exclu de PHPStan car il fait « crasher PHPStan ». Ce contournement masque un problème de compatibilité entre le TreeBuilder et l'extension PHPStan Symfony. À investiguer lors d'une montée de version PHPStan.

🟡 `phpstan.neon:20` — L'ignoreError `missingType.iterableValue` est une suppression générique (tous fichiers). La rendre plus ciblée (`paths: [src/Twig/CommandPaletteExtension.php]`) éviterait qu'elle masque de futurs warnings sur d'autres fichiers.

ℹ️ `config/services.php` — Le tag `controller.service_arguments` sur `SearchController` est la convention Sylius mais ne devrait pas être nécessaire si le firewall s'occupe du routing. Conforme aux conventions Sylius 2.2/2.3 mais à vérifier lors d'une migration 2.4.

#### Sécurité OWASP 2025

🟡 `templates/admin/command_palette/dialog.html.twig:16` — Le catalogue de pages admin (`tmonier_command_palette_data()`) est sérialisé en JSON dans l'attribut `data-config` du DOM HTML. Le bloc `{% if is_granted('ROLE_ADMINISTRATION_ACCESS') %}` protège efficacement l'affichage. Cependant, si un auteur de plugin tiers injecte des données sensibles dans `AdminPageCatalog` (p. ex. via décoration), ces données seraient incluses dans le HTML statique rendu. Envisager une note d'avertissement dans la documentation d'extension.

🟡 Absence de rate limiting applicatif sur l'endpoint `/admin/command-palette/search`. Toute requête authentifiée admin peut déclencher jusqu'à 2 requêtes SQL par provider actif (jusqu'à 10 requêtes au total). Sans limiteur Symfony Rate Limiter ou infrastructure, un compte admin compromis pourrait générer une charge DQL significative. À documenter dans le README comme prérequis d'installation en production.

ℹ️ `src/Controller/Admin/SearchController.php:25` — Le double check `isGranted('ROLE_ADMINISTRATION_ACCESS')` (firewall + contrôleur) est volontairement défensif (commentaire en place). Bonne pratique OWASP A01:2025 de défense en profondeur — à conserver.

## 3. Recommandations priorisées

### 🟠 Majeur

Aucun finding majeur bloquant identifié. Le plugin est sûr en l'état.

### 🟡 Mineur

1. **Rector — moderniser les closures** (`11 fichiers`) : lancer `make rector-fix` pour ajouter les types de paramètres sur les fermetures array_map/filter, puis relancer ECS + PHPStan.
2. **Rate limiting** : documenter dans le README la recommandation d'activer le `symfony/rate-limiter` sur la route `tmonier_command_palette_admin_search` en production.
3. **PHPStan `Configuration.php`** : investiguer et supprimer l'exclusion dès que PHPStan ou Symfony Bundle Extension corrige la compatibilité TreeBuilder.
4. **PHPStan ignoreError générique** : restreindre `missingType.iterableValue` aux fichiers concernés (`CommandPaletteExtension`, `Row`).
5. **`AdminPageCatalog::collect()` par référence** : remplacer le passage `&$pages` par un retour ou un accumulateur explicite.

### ℹ️ Pour information (choix de conception assumés)

- `Row::string()` retourne `null` pour les booléens : comportement intentionnel pour le typage de résultats scalaires DQL.
- Double `isGranted` dans le contrôleur : défense en profondeur volontaire, à conserver.
- `behat/translit` et `php-http/message-factory` abandonnés : transitivité `sylius/test-application`, non maîtrisable.
- `Configuration.php` fait crasher PHPStan : exclusion temporaire assumée, commentée dans le neon.

## 4. Verdict

Plugin **bien conçu et discipliné** : outillage au vert (ECS ✅, PHPStan level max ✅, 61 tests unitaires / 573 assertions ✅), architecture SOLID exemplaire (typage strict, classes `final`, value objects immuables, tag system extensible), sécurité solide (double enforcement du rôle, DQL 100 % paramétré, LIKE sécurisé, cache no-store, XSS absent côté JS par conception).

Les seules améliorations à engager sont : **appliquer les 11 suggestions Rector** (`make rector-fix`) et **documenter le rate limiting** recommandé pour la production. Rien de bloquant pour une release 1.0.
