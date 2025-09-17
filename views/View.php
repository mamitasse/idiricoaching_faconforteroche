<?php
declare(strict_types=1);

namespace App\views;

use function App\services\{e, consume_flash};

final class View
{
    /**
     * Rend une vue dans le layout principal.
     * - $view : chemin relatif sous /views sans .php (ex: 'home', 'auth/login')
     * - $data : variables passées à la vue
     */
    public static function render(string $view, array $data = []): void
    {
        // 1) récupérer + normaliser les flashs
        $flashes_raw = consume_flash();                 // ['success'=>['ok'], 'error'=>['bad']]
        $flashes     = self::normalizeFlashes($flashes_raw); // [['type'=>'success','msg'=>'ok'], ...]

        // 2) extraire les variables pour la vue
        extract($data, EXTR_OVERWRITE);

        // 3) capturer le contenu de la vue
        ob_start();
        require self::resolveViewPath($view);
        $content = ob_get_clean();

        // 4) afficher le layout
        require self::resolveViewPath('layouts/main');
    }

    /**
     * Normalise les flashs quel que soit leur format d’origine.
     * Entrée possible : ['error'=>['A','B'], 'success'=>['C']]
     * Sortie : [['type'=>'error','msg'=>'A'], ['type'=>'error','msg'=>'B'], ['type'=>'success','msg'=>'C']]
     */
    private static function normalizeFlashes(array $raw): array
    {
        $out = [];
        foreach ($raw as $type => $list) {
            if (!is_array($list)) { // au cas où
                $list = [$list];
            }
            foreach ($list as $m) {
                if (is_array($m)) {
                    // si un jour tu stockes déjà des tableaux
                    $text = $m['msg'] ?? $m['message'] ?? (string)reset($m);
                    $t    = $m['type'] ?? (string)$type;
                } else {
                    $text = (string)$m;
                    $t    = (string)$type;
                }
                $out[] = ['type' => $t, 'msg' => $text];
            }
        }
        return $out;
    }

    /** Résout le chemin absolu vers un fichier de vue. */
    private static function resolveViewPath(string $rel): string
    {
        $file = rtrim(\APP_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR ."templates".DIRECTORY_SEPARATOR
              . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel) . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException("View file not found: {$file}");
        }
        return $file;
    }

    /** Petit helper si tu veux générer le HTML des flashs directement ici (optionnel) */
    public static function flashesHtml(array $flashes): string
    {
        if (empty($flashes)) return '';
        $h = '';
        foreach ($flashes as $f) {
            $h .= '<div class="flash '.e($f['type']).'">'.e($f['msg']).'</div>';
        }
        return $h;
    }
}
