<?php
declare(strict_types=1);

// (Optionnel) utilisé nulle part, tu peux le laisser
define('MAIL_TRANSPORT', 'smtp');

// SMTP Gmail
define('SMTP_HOST',   'smtp.gmail.com');
define('SMTP_PORT',   587);        // 587 = TLS
define('SMTP_SECURE', 'tls');      // 'tls' ou 'ssl'
define('SMTP_USER',   'idiricoaching56@gmail.com');
define('SMTP_PASS',   'wlmwlvszqeotiocw'); // mot de passe d’application Google

// IMPORTANT : ces deux constantes doivent exister avec ces noms-là
define('SMTP_FROM',       'idiricoaching56@gmail.com'); // = même adresse que SMTP_USER pour Gmail
define('SMTP_FROM_NAME',  'Idiri Coaching');

// Copie admin (utilisée par le contrôleur)
define('SITE_ADMIN_EMAIL', 'idiricoaching56@gmail.com');

// (Facultatif)
define('NADIA_EMAIL',   'idirinadia10@gmail.com');
define('SABRINA_EMAIL', 'sabrina.idir@gmail.com');
