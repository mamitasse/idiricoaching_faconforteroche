<?php
declare(strict_types=1);

namespace App\views;

use RuntimeException;

if (!defined('APP_ROOT')) { // sécurité si autoload non chargé
    define('APP_ROOT', dirname(__DIR__));
}
const VIEWS_ROOT = APP_ROOT . '/views';

final class View
{
    /**
     * Rend une vue et l'enveloppe dans le layout views/templates/layouts/main.php
     * $view = chemin relatif sous views/templates (ex: "auth/signup", "home")
     */
    public static function render(string $view, array $vars = [], ?string $layout = 'layouts/main'): void
    {
        $viewFile = self::resolveViewPath($view);
        if (!is_file($viewFile)) {
            throw new RuntimeException("View file not found: {$viewFile}");
        }

        // Variables pour la vue
        extract($vars, EXTR_SKIP);

        // Flashes dispo dans le layout
        $flashes = \App\services\consume_flash();

        // Rendu partiel → $content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Layout
        if ($layout) {
            $layoutFile = self::resolveLayoutPath($layout);
            if (!is_file($layoutFile)) {
                throw new RuntimeException("Layout not found: {$layoutFile}");
            }
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    private static function resolveViewPath(string $view): string
    {
        return VIEWS_ROOT . '/templates/' . trim($view, '/\\') . '.php';
    }

    private static function resolveLayoutPath(string $layout): string
    {
        return VIEWS_ROOT . '/templates/' . trim($layout, '/\\') . '.php';
    }
}
