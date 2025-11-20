<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\UserManager;
use App\models\UserEntity;
use App\views\View;

// Helpers (camelCase + compat legacy snake_case)
use function App\services\flash;
use function App\services\csrfVerify;
use function App\services\csrf_verify;
use function App\services\validateEmail;
use function App\services\sendMailSmtp;   // SMTP via PHPMailer si configuré
use function App\services\sendMailNative; // fallback mail()
use function App\services\logMailForDev;  // log en DEV

/**
 * Contrôleur d’authentification (mentor-style)
 * - camelCase
 * - noms de variables explicites
 * - ENTITÉS pour l’auth (UserEntity)
 * - Ajout du flux "Mot de passe oublié" (forgot / reset)
 */
final class AuthController
{
    /* =========================================================
     * Helpers internes
     * =======================================================*/

    /** Redirection relative au site (préfixée par BASE_URL). */
    private function redirectTo(string $relativePath): void
    {
        header('Location: ' . BASE_URL . $relativePath);
        exit;
    }

    /** Vérifie le token CSRF avec compat nouvelle/ancienne fonction. */
    private function isCsrfValid(): bool
    {
        $postedToken = $_POST['_token'] ?? null;

        if (function_exists('\App\services\csrfVerify')) {
            return csrfVerify($postedToken);
        }
        if (function_exists('\App\services\csrf_verify')) {
            return csrf_verify($postedToken);
        }
        return false; // par sécurité
    }

    /** Stocke l’utilisateur authentifié en session à partir d’une ENTITÉ. */
    private function storeAuthenticatedUserInSession(UserEntity $userEntity): void
    {
        $_SESSION['user'] = [
            'id'         => (int)$userEntity->getId(),
            'first_name' => (string)$userEntity->getFirstName(),
            'last_name'  => (string)$userEntity->getLastName(),
            'email'      => (string)$userEntity->getEmail(),
            'role'       => (string)($userEntity->getRole() ?? 'adherent'),
            'coach_id'   => $userEntity->getCoachId() !== null ? (int)$userEntity->getCoachId() : null,
        ];
    }

    /** Redirige vers le dashboard selon le rôle. */
    private function redirectAfterSuccessfulLogin(): void
    {
        $role = (string)($_SESSION['user']['role'] ?? 'adherent');
        $destination = ($role === 'coach') ? 'coachDashboard' : 'adherentDashboard';
        $this->redirectTo('?action=' . $destination);
    }

    /** Envoie un e-mail (SMTP si dispo, sinon log DEV, sinon mail() natif). */
    private function sendEmailSmart(array $toList, string $subject, string $html, string $text): void
    {
        $hasSmtp = (defined('MAIL_TRANSPORT') && MAIL_TRANSPORT === 'smtp')
            && defined('SMTP_HOST') && SMTP_HOST
            && defined('SMTP_USER') && SMTP_USER
            && defined('SMTP_PASS') && SMTP_PASS;

        if ($hasSmtp && function_exists('\App\services\sendMailSmtp')) {
            foreach ($toList as $to) {
                sendMailSmtp(
                    $to,
                    $subject,
                    $html,
                    $text,
                    defined('MAIL_FROM') ? MAIL_FROM : null,
                    defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : null
                );
            }
            return;
        }

        if (defined('IN_DEV') && IN_DEV && function_exists('\App\services\logMailForDev')) {
            logMailForDev($toList, $subject, $text);
            return;
        }

        if (function_exists('\App\services\sendMailNative')) {
            sendMailNative($toList, $subject, $html, $text);
        }
    }

    /* =========================================================
     * Formulaires (GET)
     * =======================================================*/

    /** GET ?action=connexion — Affiche la page de connexion. */
    public function showLoginForm(): void
    {
        View::render('templates/auth/login', ['title' => 'Connexion']);
    }

    /** GET ?action=inscription — Affiche la page d’inscription. */
    public function showSignupForm(): void
    {
        $userManager     = new UserManager();
        $coachEntityList = $userManager->coachesEntities(); // ENTITÉS

        View::render('templates/auth/signup', [
            'title'         => 'Inscription',
            'coachEntities' => $coachEntityList,
        ]);
    }

    /** GET ?action=forgotPassword — Formulaire “mot de passe oublié”. */
    public function showForgotPasswordForm(): void
    {
        View::render('templates/auth/forgot', [
            'title' => 'Mot de passe oublié',
        ]);
    }

    /** GET ?action=resetPassword&token=... — Formulaire de réinitialisation. */
    public function showResetForm(): void
    {
        $token = (string)($_GET['token'] ?? '');
        if ($token === '') {
            flash('error', 'Lien invalide.');
            $this->redirectTo('?action=connexion');
        }

        $userManager = new UserManager();
        $userRow     = $userManager->findByResetToken($token);
        if (!$userRow) {
            flash('error', 'Lien expiré ou invalide.');
            $this->redirectTo('?action=connexion');
        }

        View::render('templates/auth/reset', [
            'title' => 'Réinitialiser le mot de passe',
            'token' => $token,
        ]);
    }

    /* =========================================================
     * Traitements (POST)
     * =======================================================*/

    /** POST ?action=loginPost — Authentifie l’utilisateur. */
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

        // Vérification du mot de passe (hash)
        $storedHash = (string)$userEntity->getPasswordHash();
        $isPasswordValid = false;

        if ($storedHash !== '' && password_verify($plainPassword, $storedHash)) {
            $isPasswordValid = true;
            if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                $userManager->updatePasswordHash((int)$userEntity->getId(), password_hash($plainPassword, PASSWORD_DEFAULT));
            }
        } else {
            // Compat legacy éventuelle (rare)
            if (method_exists($userEntity, 'getPassword') && $userEntity->getPassword() !== null) {
                if (hash_equals((string)$userEntity->getPassword(), $plainPassword)) {
                    $isPasswordValid = true;
                    $userManager->updatePasswordHash((int)$userEntity->getId(), password_hash($plainPassword, PASSWORD_DEFAULT));
                }
            }
        }

        if (!$isPasswordValid) {
            flash('error', 'Identifiants invalides.');
            $this->redirectTo('?action=connexion');
        }

        $this->storeAuthenticatedUserInSession($userEntity);
        $this->redirectAfterSuccessfulLogin();
    }

    /** POST ?action=signupPost — Crée un compte + connecte l’utilisateur. */
    public function handleSignupPost(): void
    {
        if (!$this->isCsrfValid()) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirectTo('?action=inscription');
        }

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

        $userData = [
            'first_name' => trim((string)($_POST['first_name'] ?? '')),
            'last_name'  => trim((string)($_POST['last_name'] ?? '')),
            'email'      => strtolower($emailAddress),
            'password'   => $plainPassword, // hashé par UserManager::create()
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

        $newUserEntity = $userManager->findEntityById($newUserId);
        if ($newUserEntity instanceof UserEntity) {
            $this->storeAuthenticatedUserInSession($newUserEntity);
        } else {
            flash('error', 'Création OK mais lecture du compte impossible.');
            $this->redirectTo('?action=connexion');
        }

        flash('success', 'Bienvenue ! Votre compte a bien été créé.');
        $this->redirectAfterSuccessfulLogin();
    }

    /** POST ?action=forgotPasswordPost — Envoie un lien de réinitialisation. */
    public function handleForgotPasswordPost(): void
    {
        if (!$this->isCsrfValid()) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirectTo('?action=forgotPassword');
        }

        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '' || !validateEmail($email)) {
            flash('error', 'Merci de renseigner un e-mail valide.');
            $this->redirectTo('?action=forgotPassword');
        }

        $userManager = new UserManager();
        $userRow     = $userManager->findByEmail($email);

        // Réponse constante (ne pas révéler si l’email existe ou non)
        if (!$userRow) {
            flash('success', 'Si cet e-mail existe, un lien de réinitialisation a été envoyé.');
            $this->redirectTo('?action=connexion');


        }


        // Génère/pose le token + expiration (+1h)
        $token   = $userManager->setPasswordResetToken((int)$userRow['id']);
        $resetUrl = BASE_URL . '?action=resetPassword&token=' . urlencode($token);

        // Construit l’e-mail
        $subject = 'Réinitialisation de votre mot de passe';
        $html = '<p>Bonjour,</p>
                 <p>Pour réinitialiser votre mot de passe, cliquez sur le lien suivant (valable 1 heure) :</p>
                 <p><a href="'.htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8').'">'.$resetUrl.'</a></p>
                 <p>Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.</p>
                 <p>Idiri Coaching</p>';
        $text = "Bonjour,\n\n"
              . "Pour réinitialiser votre mot de passe (lien valable 1 heure) :\n"
              . $resetUrl . "\n\n"
              . "Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.\n"
              . "Idiri Coaching\n";

        $this->sendEmailSmart([$email], $subject, $html, $text);

        flash('success', 'Si cet e-mail existe, un lien de réinitialisation a été envoyé.');
        $this->redirectTo('?action=connexion');
    }

    /** POST ?action=resetPasswordPost — Applique le nouveau mot de passe. */
    public function handleResetPost(): void
    {
        if (!$this->isCsrfValid()) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirectTo('?action=connexion');
        }

        $token = trim((string)($_POST['token'] ?? ''));
        $pass1 = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password_confirm'] ?? '');

        if ($token === '') {
            flash('error', 'Lien invalide.');
            $this->redirectTo('?action=connexion');
        }
        if ($pass1 === '' || strlen($pass1) < 8) {
            flash('error', 'Mot de passe trop court (8 caractères minimum).');
            $this->redirectTo('?action=resetPassword&token=' . urlencode($token));
        }
        if ($pass1 !== $pass2) {
            flash('error', 'Les deux mots de passe ne correspondent pas.');
            $this->redirectTo('?action=resetPassword&token=' . urlencode($token));
        }

        $userManager = new UserManager();
        $userRow     = $userManager->findByResetToken($token);

        if (!$userRow) {
            flash('error', 'Lien expiré ou invalide.');
            $this->redirectTo('?action=connexion');
        }

        // Met à jour le mot de passe et supprime le token
        $userManager->updatePassword((int)$userRow['id'], $pass1);
        $userManager->clearPasswordResetToken((int)$userRow['id']);

        flash('success', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');
        $this->redirectTo('?action=connexion');
    }

    /* =========================================================
     * Déconnexion
     * =======================================================*/

    /** GET ?action=logout — Déconnecte l’utilisateur. */
    public function logout(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
        $this->redirectTo('');
    }
}
