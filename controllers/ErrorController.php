<?php
declare(strict_types=1);

namespace App\controllers;

use App\views\View;

final class ErrorController
{
/** 404 Not Found */
public function notFound(string $message = 'Page introuvable.'): void
{
http_response_code(404);
View::render('templates/errors/404', [
'title' => 'Page introuvable',
'message' => $message,
]);
}
}