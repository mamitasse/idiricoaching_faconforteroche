<?php
declare(strict_types=1);

namespace App\controllers;

use App\views\View;
use App\models\UserManager;
use function App\services\{flash, csrf_check};

final class AuthController
{
    /* ---------- FORMULAIRES ---------- */

    public function loginForm(): void
    {
        View::render('auth/login', ['title' => 'Connexion']);
    }

    public function signupForm(): void
    {
        $coaches = (new UserManager())->coaches();
        View::render('auth/signup', ['title' => 'Inscription', 'coaches' => $coaches]);
    }

    /* ---------- ACTIONS POST ---------- */

    public function loginPost(): void
    {
        if (!csrf_check()) {
            flash('error', 'Jeton CSRF invalide.');
            header('Location: ' . BASE_URL . '?action=connexion');
            exit;
        }

        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');

        if ($email === '' || $pass === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Veuillez saisir un email et un mot de passe valides.');
            header('Location: ' . BASE_URL . '?action=connexion');
            exit;
        }

        $um = new UserManager();
        $u  = $um->findByEmail($email);

        // Vérifie l’utilisateur + compatibilité password/password_hash
        $hash = $u['password_hash'] ?? ($u['password'] ?? null);
        if (!$u || !$hash || !password_verify($pass, $hash)) {
            flash('error', 'Identifiants incorrects.');
            header('Location: ' . BASE_URL . '?action=connexion');
            exit;
        }

        // Connexion
        $_SESSION['user'] = [
            'id'         => (int)$u['id'],
            'first_name' => (string)$u['first_name'],
            'last_name'  => (string)$u['last_name'],
            'email'      => (string)$u['email'],
            'role'       => (string)$u['role'],
            'coach_id'   => isset($u['coach_id']) ? (int)$u['coach_id'] : null,
        ];

        // Redirection selon le rôle
        $dest = ($u['role'] === 'coach') ? 'coachDashboard' : 'adherentDashboard';
        header('Location: ' . BASE_URL . '?action=' . $dest);
        exit;
    }

    public function signupPost(): void
    {
        if (!csrf_check()) {
            flash('error', 'Jeton CSRF invalide.');
            header('Location: ' . BASE_URL . '?action=inscription');
            exit;
        }

        $data = [
            'first_name' => trim((string)($_POST['first_name'] ?? '')),
            'last_name'  => trim((string)($_POST['last_name'] ?? '')),
            'email'      => trim((string)($_POST['email'] ?? '')),
            'phone'      => trim((string)($_POST['phone'] ?? '')),
            'address'    => trim((string)($_POST['address'] ?? '')),
            'gender'     => ($_POST['gender'] ?? null) ?: null,
            'age'        => (isset($_POST['age']) && $_POST['age'] !== '') ? (int)$_POST['age'] : null,
            'role'       => ((string)($_POST['role'] ?? 'adherent')) === 'coach' ? 'coach' : 'adherent',
        ];
        $passwordPlain = (string)($_POST['password'] ?? '');

        // coach choisi uniquement si adhérent
        $coachId = null;
        if ($data['role'] === 'adherent') {
            if (isset($_POST['coach_id']) && ctype_digit((string)$_POST['coach_id'])) {
                $coachId = (int)$_POST['coach_id'];
            } else {
                flash('error', 'Veuillez sélectionner un coach.');
                header('Location: ' . BASE_URL . '?action=inscription');
                exit;
            }
        }
        $data['coach_id'] = $coachId;

        // validations basiques
        if ($data['first_name'] === '' || $data['last_name'] === '' ||
            $data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) ||
            $passwordPlain === ''
        ) {
            flash('error', 'Veuillez remplir tous les champs obligatoires.');
            header('Location: ' . BASE_URL . '?action=inscription');
            exit;
        }

        $um = new UserManager();

        // email déjà pris ?
        if ($um->findByEmail($data['email'])) {
            flash('error', 'Cet email est déjà utilisé.');
            header('Location: ' . BASE_URL . '?action=inscription');
            exit;
        }

        // mot de passe hashé — on fournit les deux clés pour compat (password et password_hash)
        $hash = password_hash($passwordPlain, PASSWORD_DEFAULT);
        $data['password']      = $hash; // si ta table a la colonne `password`
        $data['password_hash'] = $hash; // si ta table a la colonne `password_hash`

        // création
        try {
            $um->create($data);
            flash('success', 'Compte créé. Vous pouvez vous connecter.');
            header('Location: ' . BASE_URL . '?action=connexion');
            exit;
        } catch (\Throwable $e) {
            // Si ton UserManager cible une colonne différente (password vs password_hash),
            // adapte-le, ou dis-moi et je te donne la version correspondante.
            flash('error', 'Création impossible: ' . ($e->getMessage()));
            header('Location: ' . BASE_URL . '?action=inscription');
            exit;
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        header('Location: ' . BASE_URL);
        exit;
    }
}
