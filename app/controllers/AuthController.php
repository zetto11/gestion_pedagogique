<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

final class AuthController extends Controller
{
    public function login(): void
    {
        $errors = [];
        $email = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Veuillez saisir une adresse email valide.';
            if ($password === '') $errors[] = 'Veuillez saisir votre mot de passe.';
            if (!$errors) {
                $user = (new User())->authenticate($email, $password);
                if ($user) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['id_utilisateur'];
                    $_SESSION['user_name'] = $user['nom_utilisateur'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $route = ['ADMIN'=>'admin','ENSEIGNANT'=>'professor','ETUDIANT'=>'student'][$user['role']] ?? 'login';
                    $this->redirect($route);
                }
                $errors[] = 'Email ou mot de passe incorrect.';
            }
        }
        $this->render('auth/login', compact('errors', 'email'));
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        $this->redirect('login');
    }
}
