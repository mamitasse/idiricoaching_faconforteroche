<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\UserManager;
use App\models\UserEntity;     // entité utilisateur (fortement typée)
use App\views\View;
use function App\services\flash;

/**
 * Contrôleur d’authentification (mentor-style)
 * - camelCase
 * - noms de variables explicites
 * - uniquement les méthodes orientées ENTITÉS (plus de findByEmail/getById)
 */
final class AuthController
{
    /* ====================== Helpers ====================== */

    /** Redirige vers une route relative au site (préfixée par BASE_URL). */
    private function redirectTo(string $relativePath): void
    {
        header('Location: ' . BASE_URL . $relativePath);
        exit;
    }

    /**
     * Vérifie le jeton CSRF (compat : nouvelle et ancienne fonction).
     * Retourne true si le token est valide.
     */
    private function isCsrfValid(): bool
    {
        $postedToken = $_POST['_token'] ?? null;

        if (function_exists('\App\services\csrfVerify')) {
            return \App\services\csrfVerify($postedToken);
        }
        if (function_exists('\App\services\csrf_verify')) {
            return \App\services\csrf_verify($postedToken);
        }
        // Si aucun helper n’existe, on rejette par sécurité
        return false;
    }

    /** Stocke l’utilisateur authentifié en session à partir d’une ENTITÉ. */
    private function storeAuthenticatedUserInSession(UserEntity $userEntity): void
    {
        $_SESSION['user'] = [
            'id'         => (int) $userEntity->getId(),
            'first_name' => (string) $userEntity->getFirstName(),
            'last_name'  => (string) $userEntity->getLastName(),
            'email'      => (string) $userEntity->getEmail(),
            'role'       => (string) ($userEntity->getRole() ?? 'adherent'),
            'coach_id'   => $userEntity->getCoachId() !== null ? (int) $userEntity->getCoachId() : null,
        ];
    }

    /** Redirige vers le dashboard selon le rôle. */
    private function redirectAfterSuccessfulLogin(): void
    {
        $role = (string)($_SESSION['user']['role'] ?? 'adherent');
        $destination = ($role === 'coach') ? 'coachDashboard' : 'adherentDashboard';
        $this->redirectTo('?action=' . $destination);
    }

    /* ====================== Formulaires (GET) ====================== */

    /** GET ?action=connexion — Affiche la page de connexion. */
    public function showLoginForm(): void
    {
        View::render('templates/auth/login', ['title' => 'Connexion']);
    }

    /** GET ?action=inscription — Affiche la page d’inscription. */
    public function showSignupForm(): void
    {
        $userManager = new UserManager();

        // On passe la liste des coachs en ENTITÉS
        $coachEntityList = $userManager->coachesEntities();

        View::render('templates/auth/signup', [
            'title'         => 'Inscription',
            'coachEntities' => $coachEntityList,
        ]);
    }

    /* ====================== Traitements (POST) ====================== */

    /** POST ?action=handleLoginPost — Authentifie l’utilisateur. */
    public function handleLoginPost(): void
    {
        if (!$this->isCsrfValid()) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirectTo('?action=connexion');
        }

        $emailAddress  = trim((string)($_POST['email'] ?? ''));
        $plainPassword = (string)($_POST['password'] ?? '');

        if ($emailAddress === '' || $plainPassword === '') {
            flash('error', 'Email et mot de passe sont requis.');
            $this->redirectTo('?action=connexion');
        }

        $userManager = new UserManager();
        $userEntity  = $userManager->findEntityByEmail($emailAddress);

        if (!$userEntity instanceof UserEntity) {
            flash('error', 'Identifiants invalides.');
            $this->redirectTo('?action=connexion');
        }

        // Vérification du mot de passe hashé (champ password_hash en BDD)
        $storedPasswordHash = (string)$userEntity->getPasswordHash();
        $isPasswordValid = false;

        if ($storedPasswordHash !== '' && password_verify($plainPassword, $storedPasswordHash)) {
            $isPasswordValid = true;

            // Rehash si l'algo par défaut a changé (sécurité)
            if (password_needs_rehash($storedPasswordHash, PASSWORD_DEFAULT)) {
                $userManager->updatePasswordHash((int)$userEntity->getId(), password_hash($plainPassword, PASSWORD_DEFAULT));
            }
        } else {
            // Compatibilité legacy (si un champ password en clair existait encore côté entité)
            if (method_exists($userEntity, 'getPassword') && $userEntity->getPassword() !== null) {
                if (hash_equals((string)$userEntity->getPassword(), $plainPassword)) {
                    $isPasswordValid = true;
                    // Conversion immédiate vers password_hash
                    $userManager->updatePasswordHash((int)$userEntity->getId(), password_hash($plainPassword, PASSWORD_DEFAULT));
                }
            }
        }

        if (!$isPasswordValid) {
            flash('error', 'Identifiants invalides.');
            $this->redirectTo('?action=connexion');
        }

        // OK → session + redirection
        $this->storeAuthenticatedUserInSession($userEntity);
        $this->redirectAfterSuccessfulLogin();
    }

    /** POST ?action=handleSignupPost — Crée un compte + connecte l’utilisateur. */
    public function handleSignupPost(): void
    {
        if (!$this->isCsrfValid()) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirectTo('?action=inscription');
        }

        // Récupération / validations simples
        $emailAddress        = trim((string)($_POST['email'] ?? ''));
        $emailAddressRepeat  = trim((string)($_POST['email_confirm'] ?? ''));
        $plainPassword       = (string)($_POST['password'] ?? '');
        $plainPasswordRepeat = (string)($_POST['password_confirm'] ?? '');
        $selectedRole        = (string)($_POST['role'] ?? 'adherent');
        $selectedCoachIdStr  = (string)($_POST['coach_id'] ?? '');

        if ($emailAddress === '' || $plainPassword === '') {
            flash('error', 'Email et mot de passe sont requis.');
            $this->redirectTo('?action=inscription');
        }
        if ($emailAddress !== $emailAddressRepeat) {
            flash('error', 'Les emails ne correspondent pas.');
            $this->redirectTo('?action=inscription');
        }
        if ($plainPassword !== $plainPasswordRepeat) {
            flash('error', 'Les mots de passe ne correspondent pas.');
            $this->redirectTo('?action=inscription');
        }
        if ($selectedRole === 'adherent' && $selectedCoachIdStr === '') {
            flash('error', 'Veuillez sélectionner un coach.');
            $this->redirectTo('?action=inscription');
        }

        // Prépare les données pour le manager (il fera le hash)
        $userData = [
            'first_name' => trim((string)($_POST['first_name'] ?? '')),
            'last_name'  => trim((string)($_POST['last_name'] ?? '')),
            'email'      => strtolower($emailAddress),
            'password'   => $plainPassword, // UserManager::create() fera password_hash(...)
            'phone'      => trim((string)($_POST['phone'] ?? '')),
            'address'    => trim((string)($_POST['address'] ?? '')),
            'age'        => ($_POST['age'] ?? '') !== '' ? (int)$_POST['age'] : null,
            'gender'     => (string)($_POST['gender'] ?? ''),
            'role'       => $selectedRole,
            'coach_id'   => $selectedCoachIdStr !== '' ? (int)$selectedCoachIdStr : null,
        ];

        $userManager = new UserManager();
        $newUserId   = $userManager->create($userData);

        if ($newUserId <= 0) {
            flash('error', 'Impossible de créer le compte (email peut-être déjà utilisé ?).');
            $this->redirectTo('?action=inscription');
        }

        // Récupère l’entité créée et connecte l’utilisateur
        $newUserEntity = $userManager->findEntityById($newUserId);
        if ($newUserEntity instanceof UserEntity) {
            $this->storeAuthenticatedUserInSession($newUserEntity);
        } else {
            // Cas extrêmement rare : sécurité pour éviter une session vide
            flash('error', 'Création OK mais lecture du compte impossible (incohérence).');
            $this->redirectTo('?action=connexion');
        }

        flash('success', 'Bienvenue ! Votre compte a bien été créé.');
        $this->redirectAfterSuccessfulLogin();
    }

    /** GET ?action=logout — Déconnecte l’utilisateur. */
    public function logout(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
        $this->redirectTo('');
    }

    /* ============ Alias compatibles avec tes anciennes routes ============ */

}
