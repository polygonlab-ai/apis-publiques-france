<?php

/**
 * Actualise le catalogue des API publiques françaises à partir de data.gouv.fr, et teste chaque adresse.
 *
 * Usage :
 *   php tools/actualiser.php                         lit le catalogue, teste les adresses, réécrit data/, LISTE.md et le README
 *   php tools/actualiser.php --pages <dossier>       rejoue une collecte enregistrée (pages et tests), sans réseau
 *   php tools/actualiser.php --aide                  toutes les options
 *
 * Codes de sortie : 0 succès (ou liste inchangée), 1 source refusée (rien n'est écrit), 2 usage.
 * Aucune bibliothèque extérieure : extensions PHP curl, json et mbstring seulement ; vendor/ n'est pas nécessaire.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$arguments = array_values(array_filter(array_slice($argv, 1), 'is_string'));

// Chargeur automatique facultatif : celui désigné par --chargeur, sinon celui de Composer s'il est installé.
require_once __DIR__ . '/src/Command.php';
$loader = PolygonLab\ApisPubliquesFrance\Outils\Command::loader($arguments);
if ($loader !== null && !is_file($loader)) {
    fwrite(STDERR, "Chargeur introuvable : {$loader}\n");
    exit(2);
}
$loader ??= __DIR__ . '/../vendor/autoload.php';
if (is_file($loader)) {
    require_once $loader;
}

// Classes de l'outil, chargées depuis tools/src/ dans tous les cas.
spl_autoload_register(static function (string $class): void {
    $prefix = 'PolygonLab\\ApisPubliquesFrance\\Outils\\';
    if (str_starts_with($class, $prefix) && preg_match('/\A[A-Za-z0-9]+\z/', substr($class, strlen($prefix))) === 1) {
        $file = __DIR__ . '/src/' . substr($class, strlen($prefix)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

exit(PolygonLab\ApisPubliquesFrance\Outils\Command::run($arguments, STDOUT, STDERR));
