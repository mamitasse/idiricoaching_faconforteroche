<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\UserManager;
use App\models\UserEntity;
use App\views\View;
use function App\services\flash;
use function App\services\csrf_verify;

final class AuthController
{
    private function redirect(string $path): void
    {
        header('Location: ' . BASE_URL . $path);
        exit;
    }

    private function csrfOk(): bool
    {
        if (\function_exists('\\App\\services\\csrf_check')) {
            return \App\services\csrf_check();
        }
        return csrf_verify($_POST['_token'] ?? null);
    }

    public function loginForm(): void
    {
        View::render('auth/login', ['title' => 'Connexion']);
    }

    public function signupForm(): void
    {
        $um = new UserManager();
        $coachEntities = $um->coachesEntities(); // ENTITÉS directes pour la vue

        View::render('auth/signup', [
            'title'          => 'Inscription',
            'coachEntities'  => $coachEntities, // <--- on passera les entités au template
        ]);
    }

    public function loginPost(): void
    {
        if (!$this->csrfOk()) { flash('error','Jeton CSRF invalide.'); $this->redirect('?action=connexion'); }

        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        if ($email === '' || $pass === '') {
            flash('error','Email et mot de passe sont requis.');
            $this->redirect('?action=connexion');
        }

        $um = new UserManager();
        $ue = $um->findEntityByEmail($email); // ENTITÉ
        if (!$ue) { flash('error','Identifiants invalides.'); $this->redirect('?action=connexion'); }

        $hash = $ue->getPasswordHash();
        if ($hash === '' || !password_verify($pass, $hash)) {
            flash('error','Identifiants invalides.');
            $this->redirect('?action=connexion');
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $um->updatePasswordHash((int)$ue->getId(), password_hash($pass, PASSWORD_DEFAULT));
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'         => (int)$ue->getId(),
            'first_name' => $ue->getFirstName(),
            'last_name'  => $ue->getLastName(),
            'email'      => $ue->getEmail(),
            'role'       => $ue->getRole() ?? 'adherent',
            'coach_id'   => $ue->getCoachId(),
        ];

        $dest = ($_SESSION['user']['role'] === 'coach') ? 'coachDashboard' : 'adherentDashboard';
        $this->redirect('?action='.$dest);
    }

    public function signupPost(): void
    {
        if (!$this->csrfOk()) { flash('error','Jeton CSRF invalide.'); $this->redirect('?action=inscription'); }

        if (($_POST['email'] ?? '') !== ($_POST['email_confirm'] ?? '')) {
            flash('error','Les emails ne correspondent pas.');
            $this->redirect('?action=inscription');
        }
        if (($_POST['password'] ?? '') !== ($_POST['password_confirm'] ?? '')) {
            flash('error','Les mots de passe ne correspondent pas.');
            $this->redirect('?action=inscription');
        }
        if (($_POST['role'] ?? 'adherent') === 'adherent' && empty($_POST['coach_id'])) {
            flash('error','Veuillez sélectionner un coach.');
            $this->redirect('?action=inscription');
        }

        $data = [
            'first_name' => trim((string)($_POST['first_name'] ?? '')),
            'last_name'  => trim((string)($_POST['last_name'] ?? '')),
            'email'      => trim(strtolower((string)($_POST['email'] ?? ''))),
            'password'   => (string)($_POST['password'] ?? ''), // hashé dans UserManager::create()
            'phone'      => trim((string)($_POST['phone'] ?? '')),
            'address'    => trim((string)($_POST['address'] ?? '')),
            'age'        => ($_POST['age'] ?? '') !== '' ? (int)$_POST['age'] : null,
            'gender'     => (string)($_POST['gender'] ?? ''),
            'role'       => (string)($_POST['role'] ?? 'adherent'),
            'coach_id'   => ($_POST['coach_id'] ?? '') !== '' ? (int)$_POST['coach_id'] : null,
        ];

        $um    = new UserManager();
        $newId = (int)$um->create($data);
        if ($newId <= 0) { flash('error','Impossible de créer le compte (email déjà utilisé ?).'); $this->redirect('?action=inscription'); }

        $ue = $um->findEntityById($newId); // ENTITÉ
        if ($ue) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'         => (int)$ue->getId(),
                'first_name' => $ue->getFirstName(),
                'last_name'  => $ue->getLastName(),
                'email'      => $ue->getEmail(),
                'role'       => $ue->getRole() ?? 'adherent',
                'coach_id'   => $ue->getCoachId(),
            ];
        }

        flash('success','Bienvenue ! Votre compte a bien été créé.');
        $dest = ($_SESSION['user']['role'] === 'coach') ? 'coachDashboard' : 'adherentDashboard';
        $this->redirect('?action='.$dest);
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
        $this->redirect('');
    }
}
