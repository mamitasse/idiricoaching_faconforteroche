<?php
declare(strict_types=1);
require __DIR__ . '/../config/autoload.php';

var_dump(class_exists(\App\views\View::class)); // doit afficher bool(true)
