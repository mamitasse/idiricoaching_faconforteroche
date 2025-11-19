<?php
declare(strict_types=1);

namespace App\controllers;

use App\views\View;

/**
 * HomeController
 * ============================================================
 * Rôle :
 * - Regroupe les pages publiques de base du site (Accueil,
 *   Services, Nadia, Sabrina, Contact).
 * - Ne contient aucune logique métier ; uniquement du rendu de vues
 *   et de petites aides pour préparer les données statiques.
 *
 * Table des matières
 * ------------------------------------------------------------
 * 1) Pages publiques (GET)
 *    1.1 showHomePage()
 *    1.2 showServicesPage()
 *    1.3 showNadiaPage()
 *    1.4 showSabrinaPage()
 *    1.5 showContactPage()
 *
 * 2) Helpers privés
 *    2.1 buildGallery(string $folderFs, string $webPrefix): array
 *
 * Notes
 * ------------------------------------------------------------
 * - Les vues ciblent le dossier /views/templates/...
 * - La casse du nom de fichier compte sous Linux :
 *   utilisez 'templates/sabrina' et pas 'templates/Sabrina'.
 * - Les images sont lues depuis /public/assets/images/...
 */
final class HomeController
{
    /* =========================================================
     * 1) Pages publiques (GET)
     * ======================================================= */

    /**
     * 1.1 Page d’accueil
     * Vue attendue : /views/templates/home.php
     */
    public function showHomePage(): void
    {
        View::render('templates/home', [
            'title' => 'Accueil',
        ]);
    }

    /**
     * 1.2 Page “Nos services”
     * Vue attendue : /views/templates/services.php
     */
    public function showServicesPage(): void
    {
        View::render('templates/services', [
            'title' => 'Nos services',
        ]);
    }

    /**
     * 1.3 Page “Nadia”
     * - Construit dynamiquement une galerie à partir des images
     *   placées dans /public/assets/images/imageNadia
     * Vue attendue : /views/templates/nadia.php
     */
    public function showNadiaPage(): void
    {
        // Racine projet (ex: C:/xampp/htdocs/idiricoaching_faconforteroche)
        $projectRoot = dirname(__DIR__); // /.../projet

        // Dossier physique (FS) où se trouvent les images
        $folderFs  = $projectRoot . '/public/assets/images/imageNadia';
        // Préfixe web correspondant (URL relative utilisée par la vue)
        $webPrefix = 'assets/images/imageNadia/';

        $gallery = $this->buildGallery($folderFs, $webPrefix);

        View::render('templates/nadia', [
            'title'   => 'Nadia',
            'gallery' => $gallery,
        ]);
    }

    /**
     * 1.4 Page “Sabrina”
     * - Même principe que Nadia, mais dans /public/assets/images/imageSabrina
     * Vue attendue : /views/templates/sabrina.php
     */
    public function showSabrinaPage(): void
    {
        $projectRoot = dirname(__DIR__);

        $folderFs  = $projectRoot . '/public/assets/images/imageSabrina';
        $webPrefix = 'assets/images/imageSabrina/';

        $gallery = $this->buildGallery($folderFs, $webPrefix);

        // ⚠️ Chemin de vue en minuscules : 'templates/sabrina'
        View::render('templates/sabrina', [
            'title'   => 'Sabrina',
            'gallery' => $gallery,
        ]);
    }

    /**
     * 1.5 Page “Contact”
     * Vue attendue : /views/templates/contact.php
     *
     * Remarque :
     * - Si tu as déjà un ContactController dédié au formulaire
     *   (affichage + POST), garde-le pour la logique métier,
     *   et laisse ici uniquement la page “vitrine” si besoin.
     */
    public function showContactPage(): void
    {
        View::render('templates/contact', [
            'title' => 'Contact',
        ]);
    }

    /* =========================================================
     * 2) Helpers privés
     * ======================================================= */

    /**
     * 2.1 Construit un tableau "gallery" exploitable par la vue.
     * - Parcourt un dossier local (FS) et ajoute les images trouvées.
     * - Retourne un tableau du type :
     *   [
     *     ['src' => 'assets/.../image1.jpg', 'alt' => '...', 'caption' => ''],
     *     ...
     *   ]
     *
     * @param string $folderFs  Chemin physique des images (ex: C:/.../public/assets/images/imageNadia)
     * @param string $webPrefix Préfixe web correspondant (ex: 'assets/images/imageNadia/')
     * @return array<int,array{src:string,alt:string,caption:string}>
     */
    private function buildGallery(string $folderFs, string $webPrefix): array
    {
        $gallery = [];

        if (!is_dir($folderFs)) {
            return $gallery; // Dossier introuvable => galerie vide (la vue peut gérer un “aucune image”)
        }

        // Recherche des fichiers autorisés (insensible à la casse)
        $files = glob($folderFs . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
        natsort($files); // tri “naturel” (slide1, slide2, ...)

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            $gallery[] = [
                'src'     => $webPrefix . $fileName, // Chemin web utilisé par <img src="...">
                'alt'     => 'Galerie',              // Laisse générique, ou adapte si tu veux
                'caption' => '',                     // Optionnel : légende sous l’image
            ];
        }

        return $gallery;
    }
}
