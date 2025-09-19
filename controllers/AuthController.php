<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\UserManager;
use App\views\View;
use function App\services\flash;
use function App\services\csrf_verify;

final class AuthController
{
    /* ---------- helpers ---------- */

    private function redirect(string $path): void
    {
        header('Location: ' . BASE_URL . $path);
        exit;
    }

    private function csrfOk(): bool
    {
        // Compat: si tu as une fonction csrf_check(), on l’utilise, sinon on vérifie le token manuellement
        if (function_exists('\\App\\services\\csrf_check')) {
            return \App\services\csrf_check();
        }
        $t = $_POST['_token'] ?? null;
        return csrf_verify($t);
    }

    /* ---------- pages de formulaire ---------- */

    public function loginForm(): void
    {
        View::render('auth/login', [
            'title' => 'Connexion',
        ]);
    }

    public function signupForm(): void
    {
        $um = new UserManager();

        // On essaie de récupérer la liste des coachs, en restant tolérant au code existant
        $coaches = [];
        if (method_exists($um, 'coaches')) {
            $coaches = $um->coaches();
        } elseif (method_exists($um, 'listByRole')) {
            $coaches = $um->listByRole('coach');
        } // sinon: pas grave, la vue masque le select si la liste est vide

        View::render('auth/signup', [
            'title'   => 'Inscription',
            'coaches' => $coaches,
        ]);
    }

    /* ---------- actions ---------- */

    /** POST / connexion */
   public function loginPost(): void
{
    if (!$this->csrfOk()) {
        flash('error', 'Jeton CSRF invalide.');
        $this->redirect('?action=connexion');
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    if ($email === '' || $pass === '') {
        flash('error', 'Email et mot de passe sont requis.');
        $this->redirect('?action=connexion');
    }

    $um  = new UserManager();
    $row = method_exists($um, 'findByEmail') ? $um->findByEmail($email) : null;

    if (!$row) {
        flash('error', 'Identifiants invalides.');
        $this->redirect('?action=connexion');
    }

    // 1) on PRÉFÈRE password_hash s’il existe, sinon on retombe sur password
    $hash = null;
    if (!empty($row['password_hash'])) {
        $hash = (string)$row['password_hash'];
    } elseif (!empty($row['password'])) {
        $hash = (string)$row['password'];
    }

    $ok = false;

    // 2) essai normal: hash (bcrypt/argon) stocké
    if ($hash && password_verify($pass, $hash)) {
        $ok = true;

        // rehash si l'algorithme par défaut a changé
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            if (method_exists($um, 'updatePasswordHash')) {
                $um->updatePasswordHash((int)$row['id'], password_hash($pass, PASSWORD_DEFAULT));
            }
        }
    }
    // 3) compat legacy: si "hash" était en clair (ancien système)
    elseif ($hash !== null && hash_equals($hash, $pass)) {
        $ok = true;
        // on convertit immédiatement vers password_hash et on purge l'ancien champ
        if (method_exists($um, 'updatePasswordHash')) {
            $um->updatePasswordHash((int)$row['id'], password_hash($pass, PASSWORD_DEFAULT));
        }
    }

    if (!$ok) {
        flash('error', 'Identifiants invalides.');
        $this->redirect('?action=connexion');
    }

    // OK → session
    $_SESSION['user'] = [
        'id'         => (int)$row['id'],
        'first_name' => (string)($row['first_name'] ?? ''),
        'last_name'  => (string)($row['last_name'] ?? ''),
        'email'      => (string)($row['email'] ?? ''),
        'role'       => (string)($row['role'] ?? 'adherent'),
        'coach_id'   => isset($row['coach_id']) ? (int)$row['coach_id'] : null,
    ];

    $dest = ($_SESSION['user']['role'] === 'coach') ? 'coachDashboard' : 'adherentDashboard';
    $this->redirect('?action=' . $dest);
}

    /** POST / inscription */
    public function signupPost(): void
    {
        if (!$this->csrfOk()) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirect('?action=inscription');
        }

        // 1) emails identiques
        if (($_POST['email'] ?? '') !== ($_POST['email_confirm'] ?? '')) {
            flash('error', 'Les emails ne correspondent pas.');
            $this->redirect('?action=inscription');
        }
        // 2) mots de passe identiques
        if (($_POST['password'] ?? '') !== ($_POST['password_confirm'] ?? '')) {
            flash('error', 'Les mots de passe ne correspondent pas.');
            $this->redirect('?action=inscription');
        }
        // 3) si rôle adhérent → coach obligatoire
        if (($_POST['role'] ?? 'adherent') === 'adherent' && empty($_POST['coach_id'])) {
            flash('error', 'Veuillez sélectionner un coach.');
            $this->redirect('?action=inscription');
        }

        // 4) nettoyage / préparation
        $data = [
            'first_name' => trim((string)($_POST['first_name'] ?? '')),
            'last_name'  => trim((string)($_POST['last_name'] ?? '')),
            'email'      => trim(strtolower((string)($_POST['email'] ?? ''))),
            'password'   => (string)($_POST['password'] ?? ''),
            'phone'      => trim((string)($_POST['phone'] ?? '')),
            'address'    => trim((string)($_POST['address'] ?? '')),
            'age'        => ($_POST['age'] ?? '') !== '' ? (int)$_POST['age'] : null,
            'gender'     => (string)($_POST['gender'] ?? ''),
            'role'       => (string)($_POST['role'] ?? 'adherent'),
            'coach_id'   => ($_POST['coach_id'] ?? '') !== '' ? (int)$_POST['coach_id'] : null,
        ];

        // 5) création
        $um = new UserManager();
        $ok = $um->create($data); // ta méthode create() hash déjà le mot de passe

        if (!$ok) {
            flash('error', 'Impossible de créer le compte (email peut-être déjà utilisé ?).');
            $this->redirect('?action=inscription');
        }

        // 6) on connecte directement l’utilisateur
        $row = method_exists($um, 'findByEmail') ? $um->findByEmail($data['email']) : null;
        if ($row) {
            $_SESSION['user'] = [
                'id'         => (int)$row['id'],
                'first_name' => (string)($row['first_name'] ?? ''),
                'last_name'  => (string)($row['last_name'] ?? ''),
                'email'      => (string)($row['email'] ?? ''),
                'role'       => (string)($row['role'] ?? 'adherent'),
                'coach_id'   => isset($row['coach_id']) ? (int)$row['coach_id'] : null,
            ];
        }

        flash('success', 'Bienvenue ! Votre compte a bien été créé.');
        $dest = ($_SESSION['user']['role'] === 'coach') ? 'coachDashboard' : 'adherentDashboard';
        $this->redirect('?action=' . $dest);
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
        $this->redirect('');
    }
}
