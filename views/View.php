<?php
declare(strict_types=1);

namespace App\views;

use RuntimeException;
use function App\services\consumeFlashes;

/**
 * View
 * ----
 * - render('home', ['title'=>'Accueil']) rend la vue /views/home.php
 *   à l’intérieur du layout /views/templates/layouts/main.php
 * - Pas de dépendance à des constantes de chemin : on se base
 *   sur __DIR__ (qui pointe sur /views).
 */
final class View
{
    /**
     * Rend une vue "nom" dans le layout principal.
     * @param string $viewName   ex: 'home', 'auth/login', 'dashboard/coach'
     * @param array  $data       variables passées à la vue (extraites en variables locales)
     */
    public static function render(string $viewName, array $data = []): void
    {
        // 1) Récupère les messages flash (et les vide)
        $flashes = consumeFlashes();

        // 2) Prépare les variables pour la vue
        $title = $data['title'] ?? 'Idiri Coaching';

        // 3) Capture le contenu de la vue
        ob_start();
        // On n’écrase pas les variables existantes
        extract($data, EXTR_SKIP);
        $viewPath = self::resolveViewPath($viewName);
        require $viewPath;
        $content = ob_get_clean();

        // 4) Injecte dans le layout
        $layoutPath = self::resolveLayoutPath();
        require $layoutPath;
    }

    /** Retourne le chemin absolu du fichier de vue demandé. */
    private static function resolveViewPath(string $viewName): string
    {
        // __DIR__ = .../views
        $viewsBaseDir = rtrim(str_replace('\\', '/', __DIR__), '/') . '/';

        // Sécurise le nom (évite '../')
        $safeName = ltrim($viewName, '/');
        $safeName = str_replace(['..\\','../'], '', $safeName);

        $fullPath = $viewsBaseDir . $safeName . '.php';
        if (!is_file($fullPath)) {
            throw new RuntimeException('View file not found: ' . $fullPath);
        }
        return $fullPath;
    }

    /** Retourne le chemin absolu du layout principal. */
    private static function resolveLayoutPath(): string
    {
        // /views/templates/layouts/main.php
        $viewsBaseDir = rtrim(str_replace('\\', '/', __DIR__), '/') . '/';
        $layout = $viewsBaseDir . 'templates/layouts/main.php';

        if (!is_file($layout)) {
            throw new RuntimeException('Layout file not found: ' . $layout);
        }
        return $layout;
    }
}
