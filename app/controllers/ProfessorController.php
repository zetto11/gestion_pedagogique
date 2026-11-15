<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Grade;
use App\Models\Professor;
use RuntimeException;
use Throwable;

final class ProfessorController extends Controller
{
    private const NAV = ['overview'=>'Tableau de bord','modules'=>'Mes modules','students'=>'Mes étudiants','evaluations'=>'Évaluations','grades'=>'Notes','notifications'=>'Notifications','profile'=>'Mon profil'];

    public function index(): void
    {
        $this->requireRole('ENSEIGNANT');
        $model = new Professor(); $gradeModel = new Grade();
        $profile = $model->profile((int)$_SESSION['user_id']);
        $page = $_GET['page'] ?? 'overview';
        if (!isset(self::NAV[$page])) $page = 'overview';
        $message = ''; $error = '';
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_grade'])) {
            try {
                if (!$profile) throw new RuntimeException('Aucun profil enseignant n’est rattaché à ce compte.');
                $evaluationId = filter_input(INPUT_POST,'id_evaluation',FILTER_VALIDATE_INT);
                $registrationId = filter_input(INPUT_POST,'id_inscription',FILTER_VALIDATE_INT);
                $value = filter_input(INPUT_POST,'valeur',FILTER_VALIDATE_FLOAT);
                if (!$evaluationId || !$registrationId || $value===false || $value===null) throw new RuntimeException('Vérifiez les champs de la note.');
                $gradeModel->saveForProfessor((int)$profile['id_enseignant'],$evaluationId,$registrationId,$value,trim($_POST['observation']??''));
                $message='Note enregistrée.'; $page='grades';
            } catch (Throwable $exception) { $error=$exception->getMessage(); $page='grades'; }
        }
        $records=[]; $columns=[];
        $overviewModules=$profile?$model->modules((int)$profile['id_enseignant']):[];
        $overviewColumns=['code_module','nom_module','nom_filiere','annee','semestre','statut'];
        if ($profile) {
            if ($page==='modules') { $records=$model->modules((int)$profile['id_enseignant']); $columns=['code_module','nom_module','nom_filiere','annee','semestre','statut']; }
            if ($page==='students') { $records=$model->students((int)$profile['id_enseignant']); $columns=array_keys($records[0]??[]); }
            if ($page==='evaluations') { $records=$model->evaluations((int)$profile['id_enseignant']); $columns=array_keys($records[0]??[]); }
            if ($page==='grades') { $records=$model->gradeRows((int)$profile['id_enseignant']); $columns=array_keys($records[0]??[]); }
        }
        $this->render('professor/page', [
            'page'=>$page,'title'=>self::NAV[$page],'nav'=>self::NAV,'roleLabel'=>'Professeur','roleTitle'=>'Espace enseignant',
            'userName'=>$_SESSION['user_name']??'','profile'=>$profile,'stats'=>$profile?$model->stats((int)$profile['id_enseignant']):[],
            'records'=>$records,'columns'=>$columns,'overviewModules'=>$overviewModules,'overviewColumns'=>$overviewColumns,'message'=>$message,'error'=>$error,
            'evaluationOptions'=>$profile?$gradeModel->evaluationOptions((int)$profile['id_enseignant']):[],
            'registrationOptions'=>$profile?$gradeModel->registrationOptions((int)$profile['id_enseignant']):[],
        ], ['role'=>'ENSEIGNANT']);
    }
}
