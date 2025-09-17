<?php
declare(strict_types=1);

namespace App\controllers;

use App\views\View;

final class HomeController
{
    public function index(): void
    {
        View::render('home', ['title'=>'Accueil']);
    }
}
