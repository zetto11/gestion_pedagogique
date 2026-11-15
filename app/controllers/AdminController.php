<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\AdminDashboard;

final class AdminController extends Controller
{
    private const NAV = [
        'overview'=>'Vue d’ensemble','students'=>'Étudiants','teachers'=>'Enseignants','users'=>'Utilisateurs',
        'departments'=>'Départements','formations'=>'Formations','filieres'=>'Filières','modules'=>'Modules',
        'registrations'=>'Inscriptions','evaluations'=>'Évaluations','grades'=>'Notes','announcements'=>'Annonces','notifications'=>'Notifications'
    ];

    public function index(): void
    {
        $this->requireRole('ADMIN');
        $page = $_GET['page'] ?? 'overview';
        if (!isset(self::NAV[$page])) $page = 'overview';
        $model = new AdminDashboard();
        $data = [
            'page'=>$page, 'title'=>self::NAV[$page], 'nav'=>self::NAV,
            'roleLabel'=>'Administrateur', 'roleTitle'=>'Espace administration',
            'userName'=>$_SESSION['user_name'] ?? '', 'stats'=>$page==='overview'?$model->overview():[],
            'records'=>[], 'columns'=>[], 'message'=>'', 'error'=>'', 'profile'=>null,
        ];
        if ($page !== 'overview' && !in_array($page,['announcements','notifications'],true)) {
            $table = $model->page($page);
            if ($table) $data = array_merge($data,$table);
        }
        $this->render('admin/page', $data, ['role'=>'ADMIN']);
    }
}
