<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AdminDashboard extends Model
{
    private const PAGES = [
        'students' => ['SELECT e.CNE, e.nom, e.prenom, u.email, e.telephone FROM Etudiant e JOIN Utilisateur u ON u.id_utilisateur=e.id_utilisateur ORDER BY e.nom', 'Étudiants'],
        'teachers' => ['SELECT e.matricule, e.nom, e.prenom, u.email, e.specialite FROM Enseignant e JOIN Utilisateur u ON u.id_utilisateur=e.id_utilisateur ORDER BY e.nom', 'Enseignants'],
        'users' => ['SELECT nom_utilisateur, email, role, actif, date_creation FROM Utilisateur ORDER BY date_creation DESC', 'Utilisateurs'],
        'departments' => ['SELECT code_dept, nom_dept, description FROM Departement ORDER BY nom_dept', 'Départements'],
        'formations' => ['SELECT f.code_formation, f.nom_formation, f.niveau, d.nom_dept AS departement FROM Formation f JOIN Departement d ON d.id_departement=f.id_departement ORDER BY f.nom_formation', 'Formations'],
        'filieres' => ['SELECT f.code_filiere, f.nom_filiere, fo.nom_formation AS formation FROM Filiere f JOIN Formation fo ON fo.id_formation=f.id_formation ORDER BY f.nom_filiere', 'Filières'],
        'modules' => ['SELECT code_module, nom_module, description FROM Module ORDER BY nom_module', 'Modules'],
        'registrations' => ['SELECT e.CNE, CONCAT(e.prenom, " ", e.nom) AS etudiant, f.nom_filiere, a.libelle AS annee, i.date_inscription, i.statut FROM Inscription i JOIN Etudiant e ON e.id_etudiant=i.id_etudiant JOIN Filiere f ON f.id_filiere=i.id_filiere JOIN Annee_univ a ON a.id_annee=i.id_annee ORDER BY i.date_inscription DESC', 'Inscriptions'],
        'evaluations' => ['SELECT m.code_module, m.nom_module, ev.libelle, ev.type_evaluation, ev.date_evaluation, ev.note_max FROM Evaluation ev JOIN Enseignement en ON en.id_enseignement=ev.id_enseignement JOIN Module m ON m.id_module=en.id_module ORDER BY ev.date_evaluation DESC', 'Évaluations'],
        'grades' => ['SELECT e.CNE, CONCAT(e.prenom, " ", e.nom) AS etudiant, m.code_module, ev.libelle AS evaluation, n.valeur, ev.note_max, n.statut_validation FROM Note n JOIN Evaluation ev ON ev.id_evaluation=n.id_evaluation JOIN Enseignement en ON en.id_enseignement=ev.id_enseignement JOIN Module m ON m.id_module=en.id_module JOIN Inscription i ON i.id_inscription=n.id_inscription JOIN Etudiant e ON e.id_etudiant=i.id_etudiant ORDER BY m.nom_module, e.nom', 'Notes'],
    ];

    public function overview(): array
    {
        $counts = [];
        foreach (['Étudiants' => 'Etudiant', 'Enseignants' => 'Enseignant', 'Modules' => 'Module', 'Inscriptions' => 'Inscription'] as $label => $table) {
            $counts[] = [$label, (int)$this->db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn()];
        }
        return $counts;
    }

    public function page(string $page): ?array
    {
        if (!isset(self::PAGES[$page])) {
            return null;
        }
        [$sql, $title] = self::PAGES[$page];
        $records = $this->rows($sql);
        return ['title' => $title, 'records' => $records, 'columns' => array_keys($records[0] ?? [])];
    }
}
