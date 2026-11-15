<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Student;

final class StudentController extends Controller
{
    private const NAV = ['overview'=>'Tableau de bord','profile'=>'Mon profil','modules'=>'Mes modules','evaluations'=>'Mes évaluations','grades'=>'Mes notes','notifications'=>'Notifications'];

    public function index(): void
    {
        $this->requireRole('ETUDIANT');
        $model = new Student(); $profile=$model->profile((int)$_SESSION['user_id']);
        $page=$_GET['page']??'overview'; if(!isset(self::NAV[$page])) $page='overview';
        $records=[]; $columns=[];
        if($profile && in_array($page,['modules','evaluations','grades'],true)) {
            $records=$model->academicRows((int)$profile['id_etudiant'],$page); $columns=array_keys($records[0]??[]);
        }
        $overviewModules=$profile?$model->academicRows((int)$profile['id_etudiant'],'modules'):[];
        $overviewColumns=array_keys($overviewModules[0]??[]);
        $this->render('student/page',[
            'page'=>$page,'title'=>self::NAV[$page],'nav'=>self::NAV,'roleLabel'=>'Étudiant','roleTitle'=>'Espace étudiant',
            'userName'=>$_SESSION['user_name']??'','profile'=>$profile,'stats'=>$profile?$model->stats((int)$profile['id_etudiant']):[],
            'records'=>$records,'columns'=>$columns,'overviewModules'=>$overviewModules,'overviewColumns'=>$overviewColumns,'message'=>'','error'=>'',
        ],['role'=>'ETUDIANT']);
    }
}
