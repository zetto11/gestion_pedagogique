<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->row('SELECT id_utilisateur, nom_utilisateur, email, mot_de_passe_hash, role FROM Utilisateur WHERE email = ? AND actif = 1 LIMIT 1', [$email]);
        if (!$user || !password_verify($password, $user['mot_de_passe_hash'])) {
            return null;
        }
        unset($user['mot_de_passe_hash']);
        return $user;
    }
}
