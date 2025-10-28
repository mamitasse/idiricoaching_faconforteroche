<?php

declare(strict_types=1);

namespace App\controllers;

use App\views\View;

/**
 * HomeController
 * --------------
 * Regroupe les pages publiques simples (Home, Services, Nadia, Sabrina, Contact).
 * Chaque méthode "showXxxPage" rend une vue depuis /views/.
 */
final class HomeController
{
    /** Page d’accueil */
    public function showHomePage(): void
    {
        View::render('templates/home', [
            'title' => 'Accueil',
        ]);
    }

    /** Page Services (si tu as la vue) */
    public function showServicesPage(): void
    {
        View::render('services', [
            'title' => 'Nos services',
        ]);
    }

    /** Page Nadia (si tu as la vue) */
public function showNadiaPage(): void
{
    $folderFs = dirname(__DIR__) . '/public/assets/images/imageNadia';

    $gallery = [];
    if (is_dir($folderFs)) {
        $files = glob($folderFs . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
        natsort($files);
        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            $gallery[] = [
                'src'     => 'assets/images/imageNadia/' . $fileName, // <-- bien le slash ici
                'alt'     => 'demo Nadia',
                'caption' => '',
            ];
        }
    }

    \App\views\View::render('templates/nadia', [
        'title'   => 'Nadia',
        'gallery' => $gallery,
    ]);
}




    /** Page Sabrina (si tu as la vue) */
    public function showSabrinaPage(): void
    {
        View::render('templates/sabrina', [
            'title' => 'Coach Sabrina',
        ]);
    }

    /** Page Contact (si tu as la vue) */
    public function showContactPage(): void
    {
        View::render('contact', [
            'title' => 'Contact',
        ]);
    }
}
