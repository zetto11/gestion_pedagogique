<?php
session_start();
require_once __DIR__ . '/connect_db.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'];
$userId = (int) $_SESSION['user_id'];
$roleConfig = [
    'ADMIN' => ['label' => 'Administrateur', 'title' => 'Espace administration', 'nav' => [
        'overview' => 'Vue d’ensemble', 'students' => 'Étudiants', 'teachers' => 'Enseignants',
        'users' => 'Utilisateurs', 'departments' => 'Départements', 'formations' => 'Formations',
        'filieres' => 'Filières', 'modules' => 'Modules', 'registrations' => 'Inscriptions',
        'evaluations' => 'Évaluations', 'grades' => 'Notes', 'announcements' => 'Annonces', 'notifications' => 'Notifications'
    ]],
    'ENSEIGNANT' => ['label' => 'Professeur', 'title' => 'Espace enseignant', 'nav' => [
        'overview' => 'Tableau de bord', 'modules' => 'Mes modules', 'students' => 'Mes étudiants',
        'evaluations' => 'Évaluations', 'grades' => 'Notes', 'notifications' => 'Notifications', 'profile' => 'Mon profil'
    ]],
    'ETUDIANT' => ['label' => 'Étudiant', 'title' => 'Espace étudiant', 'nav' => [
        'overview' => 'Tableau de bord', 'profile' => 'Mon profil', 'modules' => 'Mes modules',
        'evaluations' => 'Mes évaluations', 'grades' => 'Mes notes', 'notifications' => 'Notifications'
    ]]
];
if (!isset($roleConfig[$role])) { http_response_code(403); exit('Accès refusé.'); }
$config = $roleConfig[$role];
$page = $_GET['page'] ?? 'overview';
if (!isset($config['nav'][$page])) { $page = 'overview'; }
$esc = static fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$message = '';
$error = '';

// Resolve the signed-in academic profile from the existing role/profile relationship.
$profile = null;
if ($role === 'ENSEIGNANT') {
    $q = $pdo->prepare('SELECT id_enseignant, nom, prenom, matricule, telephone, specialite FROM Enseignant WHERE id_utilisateur = ?');
    $q->execute([$userId]); $profile = $q->fetch(PDO::FETCH_ASSOC) ?: null;
} elseif ($role === 'ETUDIANT') {
    $q = $pdo->prepare('SELECT id_etudiant, CNE, nom, prenom, date_naissance, telephone, adresse FROM Etudiant WHERE id_utilisateur = ?');
    $q->execute([$userId]); $profile = $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'ENSEIGNANT' && isset($_POST['save_grade'])) {
    try {
        if (!$profile) throw new RuntimeException('Aucun profil enseignant n’est rattaché à ce compte.');
        $evaluationId = filter_input(INPUT_POST, 'id_evaluation', FILTER_VALIDATE_INT);
        $registrationId = filter_input(INPUT_POST, 'id_inscription', FILTER_VALIDATE_INT);
        $value = filter_input(INPUT_POST, 'valeur', FILTER_VALIDATE_FLOAT);
        $observation = trim($_POST['observation'] ?? '');
        if (!$evaluationId || !$registrationId || $value === false || $value === null) throw new RuntimeException('Vérifiez les champs de la note.');
        $check = $pdo->prepare("SELECT ev.note_max FROM Evaluation ev JOIN Enseignement en ON en.id_enseignement = ev.id_enseignement JOIN Inscription i ON i.id_filiere = en.id_filiere AND i.id_annee = en.id_annee WHERE ev.id_evaluation = ? AND en.id_enseignant = ? AND i.id_inscription = ? AND i.statut = 'VALIDEE'");
        $check->execute([$evaluationId, $profile['id_enseignant'], $registrationId]);
        $limit = $check->fetchColumn();
        if ($limit === false) throw new RuntimeException('Cette évaluation ou cette inscription ne fait pas partie de vos enseignements.');
        if ($value < 0 || $value > (float)$limit) throw new RuntimeException('La note doit être comprise entre 0 et ' . $limit . '.');
        $existing = $pdo->prepare('SELECT id_note FROM Note WHERE id_evaluation = ? AND id_inscription = ?');
        $existing->execute([$evaluationId, $registrationId]);
        $noteId = $existing->fetchColumn();
        if ($noteId) {
            $save = $pdo->prepare('UPDATE Note SET valeur = ?, observation = ?, date_saisie = NOW() WHERE id_note = ?');
            $save->execute([$value, $observation ?: null, $noteId]);
        } else {
            $save = $pdo->prepare("INSERT INTO Note (id_evaluation, id_inscription, valeur, observation, statut_validation) VALUES (?, ?, ?, ?, 'BROUILLON')");
            $save->execute([$evaluationId, $registrationId, $value, $observation ?: null]);
        }
        $message = 'Note enregistrée.';
        $page = 'grades';
    } catch (Throwable $e) { $error = $e->getMessage(); $page = 'grades'; }
}

function rows(PDO $pdo, string $sql, array $params = []): array { $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC); }
function countRows(PDO $pdo, string $table): int { return (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn(); }
$title = $config['nav'][$page];
$stats = [];
if ($role === 'ADMIN' && $page === 'overview') {
    foreach ([['Étudiants','Etudiant'],['Enseignants','Enseignant'],['Modules','Module'],['Inscriptions','Inscription']] as [$label,$table]) $stats[] = [$label,countRows($pdo,$table)];
}
if ($role === 'ENSEIGNANT' && $profile && in_array($page,['overview','modules','students','evaluations','grades'],true)) {
    $base = ' FROM Enseignement en JOIN Module m ON m.id_module=en.id_module JOIN Filiere f ON f.id_filiere=en.id_filiere JOIN Annee_univ a ON a.id_annee=en.id_annee JOIN Semestre s ON s.id_semestre=en.id_semestre';
    $own = rows($pdo,'SELECT en.id_enseignement, en.id_filiere, en.id_annee, m.code_module, m.nom_module, f.nom_filiere, a.libelle AS annee, s.libelle AS semestre, en.statut'.$base.' WHERE en.id_enseignant=? ORDER BY a.date_debut DESC, s.numero_semestre',[$profile['id_enseignant']]);
}
if ($role === 'ETUDIANT' && $profile) {
    $studentId = (int)$profile['id_etudiant'];
    $studentTeachingJoin = ' FROM Inscription i JOIN Enseignement en ON en.id_filiere=i.id_filiere AND en.id_annee=i.id_annee JOIN Module m ON m.id_module=en.id_module JOIN Filiere f ON f.id_filiere=en.id_filiere JOIN Annee_univ a ON a.id_annee=en.id_annee JOIN Semestre s ON s.id_semestre=en.id_semestre';
}

$records = [];
$columns = [];
if ($role === 'ADMIN') {
    $queries = [
        'students'=>['Étudiants','SELECT e.CNE, e.nom, e.prenom, u.email, e.telephone FROM Etudiant e JOIN Utilisateur u ON u.id_utilisateur=e.id_utilisateur ORDER BY e.nom'],
        'teachers'=>['Enseignants','SELECT e.matricule, e.nom, e.prenom, u.email, e.specialite FROM Enseignant e JOIN Utilisateur u ON u.id_utilisateur=e.id_utilisateur ORDER BY e.nom'],
        'users'=>['Utilisateurs','SELECT nom_utilisateur, email, role, actif, date_creation FROM Utilisateur ORDER BY date_creation DESC'],
        'departments'=>['Départements','SELECT code_dept, nom_dept, description FROM Departement ORDER BY nom_dept'],
        'formations'=>['Formations','SELECT f.code_formation, f.nom_formation, f.niveau, d.nom_dept AS departement FROM Formation f JOIN Departement d ON d.id_departement=f.id_departement ORDER BY f.nom_formation'],
        'filieres'=>['Filières','SELECT f.code_filiere, f.nom_filiere, fo.nom_formation AS formation FROM Filiere f JOIN Formation fo ON fo.id_formation=f.id_formation ORDER BY f.nom_filiere'],
        'modules'=>['Modules','SELECT code_module, nom_module, description FROM Module ORDER BY nom_module'],
        'registrations'=>['Inscriptions','SELECT e.CNE, CONCAT(e.prenom, " ", e.nom) AS etudiant, f.nom_filiere, a.libelle AS annee, i.date_inscription, i.statut FROM Inscription i JOIN Etudiant e ON e.id_etudiant=i.id_etudiant JOIN Filiere f ON f.id_filiere=i.id_filiere JOIN Annee_univ a ON a.id_annee=i.id_annee ORDER BY i.date_inscription DESC'],
        'evaluations'=>['Évaluations','SELECT m.code_module, m.nom_module, ev.libelle, ev.type_evaluation, ev.date_evaluation, ev.note_max FROM Evaluation ev JOIN Enseignement en ON en.id_enseignement=ev.id_enseignement JOIN Module m ON m.id_module=en.id_module ORDER BY ev.date_evaluation DESC'],
        'grades'=>['Notes','SELECT e.CNE, CONCAT(e.prenom, " ", e.nom) AS etudiant, m.code_module, ev.libelle AS evaluation, n.valeur, ev.note_max, n.statut_validation FROM Note n JOIN Evaluation ev ON ev.id_evaluation=n.id_evaluation JOIN Enseignement en ON en.id_enseignement=ev.id_enseignement JOIN Module m ON m.id_module=en.id_module JOIN Inscription i ON i.id_inscription=n.id_inscription JOIN Etudiant e ON e.id_etudiant=i.id_etudiant ORDER BY m.nom_module, e.nom']
    ];
    if (isset($queries[$page])) { [$title,$sql] = $queries[$page]; $records = rows($pdo,$sql); $columns = array_keys($records[0] ?? []); }
}
if ($role === 'ENSEIGNANT' && $profile) {
    $teacherId=(int)$profile['id_enseignant'];
    if ($page==='students') {
        $records=rows($pdo,"SELECT DISTINCT e.CNE, e.nom, e.prenom, u.email, m.code_module, m.nom_module, f.nom_filiere FROM Enseignement en JOIN Module m ON m.id_module=en.id_module JOIN Inscription i ON i.id_filiere=en.id_filiere AND i.id_annee=en.id_annee AND i.statut='VALIDEE' JOIN Etudiant e ON e.id_etudiant=i.id_etudiant JOIN Utilisateur u ON u.id_utilisateur=e.id_utilisateur JOIN Filiere f ON f.id_filiere=en.id_filiere WHERE en.id_enseignant=? ORDER BY m.nom_module,e.nom",[$teacherId]); $columns=array_keys($records[0]??[]);
    } elseif ($page==='modules') {
        $records=$own??[]; $columns=['code_module','nom_module','nom_filiere','annee','semestre','statut'];
    } elseif ($page==='evaluations') {
        $records=rows($pdo,'SELECT m.code_module, m.nom_module, ev.libelle, ev.type_evaluation, ev.date_evaluation, ev.note_max FROM Evaluation ev JOIN Enseignement en ON en.id_enseignement=ev.id_enseignement JOIN Module m ON m.id_module=en.id_module WHERE en.id_enseignant=? ORDER BY ev.date_evaluation DESC',[$teacherId]); $columns=array_keys($records[0]??[]);
    } elseif ($page==='grades') {
        $records=rows($pdo,'SELECT ev.id_evaluation, i.id_inscription, m.code_module, ev.libelle AS evaluation, e.CNE, CONCAT(e.prenom," ",e.nom) AS etudiant, n.valeur, ev.note_max, n.statut_validation FROM Evaluation ev JOIN Enseignement en ON en.id_enseignement=ev.id_enseignement JOIN Module m ON m.id_module=en.id_module JOIN Inscription i ON i.id_filiere=en.id_filiere AND i.id_annee=en.id_annee AND i.statut="VALIDEE" JOIN Etudiant e ON e.id_etudiant=i.id_etudiant LEFT JOIN Note n ON n.id_evaluation=ev.id_evaluation AND n.id_inscription=i.id_inscription WHERE en.id_enseignant=? ORDER BY ev.date_evaluation DESC, e.nom',[$teacherId]); $columns=array_keys($records[0]??[]);
    }
}
if ($role === 'ETUDIANT' && $profile) {
    if (in_array($page,['modules','evaluations','grades'],true)) {
        $sql='SELECT m.code_module, m.nom_module, f.nom_filiere, a.libelle AS annee, s.libelle AS semestre';
        if ($page==='evaluations') $sql.=', ev.libelle AS evaluation, ev.type_evaluation, ev.date_evaluation, ev.note_max';
        if ($page==='grades') $sql.=', ev.libelle AS evaluation, ev.note_max, n.valeur, n.statut_validation';
        $sql.=$studentTeachingJoin;
        if ($page!=='modules') $sql.=' LEFT JOIN Evaluation ev ON ev.id_enseignement=en.id_enseignement LEFT JOIN Note n ON n.id_evaluation=ev.id_evaluation AND n.id_inscription=i.id_inscription';
        $sql.=' WHERE i.id_etudiant=? AND i.statut="VALIDEE" ORDER BY a.date_debut DESC,s.numero_semestre,m.nom_module';
        $records=rows($pdo,$sql,[$studentId]); $columns=array_keys($records[0]??[]);
    }
}
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $esc($title) ?> · Gestio Pédagogique</title><link rel="stylesheet" href="../CSS/dashboard.css"></head>
<body><div class="app-shell">
<aside class="sidebar"><a class="brand" href="dashboard.php"><span class="brand-mark">G</span><span>Gestio<span class="brand-light"> Pédagogique</span></span></a><div class="role-label"><?= $esc($config['label']) ?></div><nav class="nav-list" aria-label="Navigation principale"><?php foreach ($config['nav'] as $key=>$label): ?><a class="nav-link <?= $page===$key?'active':'' ?>" href="?page=<?= $esc($key) ?>"><span class="nav-dot"></span><?= $esc($label) ?></a><?php endforeach; ?></nav><div class="sidebar-foot"><span class="online-dot"></span> Espace sécurisé</div></aside>
<main class="main-area"><header class="topbar"><button class="menu-toggle" aria-label="Afficher le menu" onclick="document.body.classList.toggle('menu-open')">☰</button><div><div class="eyebrow"><?= $esc($config['title']) ?></div><h1><?= $esc($title) ?></h1></div><div class="top-actions"><span class="top-date"><?= date('d/m/Y') ?></span><div class="user-chip"><span class="avatar"><?= $esc(mb_substr($_SESSION['user_name'] ?? 'U',0,1)) ?></span><span><b><?= $esc($_SESSION['user_name'] ?? '') ?></b><small><?= $esc($config['label']) ?></small></span></div><a class="logout" href="logout.php">Déconnexion</a></div></header>
<section class="content">
<?php if ($message): ?><div class="notice success"><?= $esc($message) ?></div><?php endif; ?><?php if ($error): ?><div class="notice error"><?= $esc($error) ?></div><?php endif; ?>
<?php if ($role==='ADMIN' && $page==='overview'): ?>
<div class="welcome"><div><span class="eyebrow">ANNÉE UNIVERSITAIRE</span><h2>Bonjour, <?= $esc($_SESSION['user_name'] ?? 'Administrateur') ?></h2><p>Retrouvez les données académiques de votre établissement au même endroit.</p></div><div class="welcome-icon">✦</div></div>
<div class="stats-grid"><?php foreach($stats as [$label,$value]): ?><article class="stat-card"><span><?= $esc($label) ?></span><strong><?= $value ?></strong><small>Enregistrés dans la base</small></article><?php endforeach; ?></div>
<div class="section-heading"><div><span class="eyebrow">GESTION ACADÉMIQUE</span><h2>Accès rapide</h2></div></div><div class="quick-grid"><?php foreach(array_slice($config['nav'],1,8,true) as $key=>$label): ?><a class="quick-card" href="?page=<?= $esc($key) ?>"><span class="quick-icon"><?= $esc(mb_substr($label,0,1)) ?></span><span><b><?= $esc($label) ?></b><small>Consulter les données</small></span><span class="arrow">↗</span></a><?php endforeach; ?></div>
<?php elseif ($role==='ENSEIGNANT' && !$profile || $role==='ETUDIANT' && !$profile): ?><div class="empty-card"><span class="empty-symbol">!</span><h2>Profil académique introuvable</h2><p>Le compte connecté n’a pas encore de profil associé dans la table <?= $role==='ENSEIGNANT'?'Enseignant':'Etudiant' ?>.</p></div>
<?php elseif (($role==='ENSEIGNANT'||$role==='ETUDIANT') && $page==='overview'): ?>
<div class="welcome"><div><span class="eyebrow">VOTRE ESPACE</span><h2>Bonjour, <?= $esc($profile['prenom'] ?? $_SESSION['user_name']) ?> 👋</h2><p><?= $role==='ENSEIGNANT'?'Consultez vos modules, suivez vos étudiants et gérez leurs notes.':'Consultez votre parcours, vos évaluations et vos résultats.' ?></p></div><div class="welcome-icon">✦</div></div>
<?php if($role==='ENSEIGNANT'): ?><div class="stats-grid"><article class="stat-card"><span>Mes modules</span><strong><?= count($own ?? []) ?></strong><small>Enseignements attribués</small></article><article class="stat-card"><span>Évaluations</span><strong><?= (int)$pdo->query('SELECT COUNT(*) FROM Evaluation ev JOIN Enseignement en ON en.id_enseignement=ev.id_enseignement WHERE en.id_enseignant='.(int)$profile['id_enseignant'])->fetchColumn() ?></strong><small>Dans mes enseignements</small></article><article class="stat-card"><span>Notes saisies</span><strong><?= (int)$pdo->query('SELECT COUNT(*) FROM Note n JOIN Evaluation ev ON ev.id_evaluation=n.id_evaluation JOIN Enseignement en ON en.id_enseignement=ev.id_enseignement WHERE en.id_enseignant='.(int)$profile['id_enseignant'])->fetchColumn() ?></strong><small>Notes enregistrées</small></article></div><?php else: ?><div class="stats-grid"><article class="stat-card"><span>Mes modules</span><strong><?= (int)$pdo->query('SELECT COUNT(DISTINCT en.id_enseignement) FROM Inscription i JOIN Enseignement en ON en.id_filiere=i.id_filiere AND en.id_annee=i.id_annee WHERE i.id_etudiant='.(int)$profile['id_etudiant'].' AND i.statut="VALIDEE"')->fetchColumn() ?></strong><small>Pour mes inscriptions validées</small></article><article class="stat-card"><span>Mes notes</span><strong><?= (int)$pdo->query('SELECT COUNT(*) FROM Note n JOIN Inscription i ON i.id_inscription=n.id_inscription WHERE i.id_etudiant='.(int)$profile['id_etudiant'])->fetchColumn() ?></strong><small>Enregistrées dans le système</small></article><article class="stat-card"><span>Identifiant</span><strong class="stat-code"><?= $esc($role==='ETUDIANT'?$profile['CNE']:$profile['matricule']) ?></strong><small><?= $role==='ETUDIANT'?'CNE':'Matricule' ?></small></article></div><?php endif; ?>
<div class="section-heading"><div><span class="eyebrow">RÉCAPITULATIF</span><h2><?= $role==='ENSEIGNANT'?'Mes enseignements':'Mon parcours académique' ?></h2></div><a class="text-link" href="?page=<?= $role==='ENSEIGNANT'?'modules':'modules' ?>">Tout afficher <span>→</span></a></div>
<?php if($role==='ENSEIGNANT'): $records=$own??[]; $columns=['code_module','nom_module','nom_filiere','annee','semestre','statut']; else: $records=rows($pdo,'SELECT m.code_module,m.nom_module,f.nom_filiere,a.libelle AS annee,s.libelle AS semestre FROM Inscription i JOIN Enseignement en ON en.id_filiere=i.id_filiere AND en.id_annee=i.id_annee JOIN Module m ON m.id_module=en.id_module JOIN Filiere f ON f.id_filiere=en.id_filiere JOIN Annee_univ a ON a.id_annee=en.id_annee JOIN Semestre s ON s.id_semestre=en.id_semestre WHERE i.id_etudiant=? AND i.statut="VALIDEE" ORDER BY a.date_debut DESC,s.numero_semestre LIMIT 5',[(int)$profile['id_etudiant']]); $columns=array_keys($records[0]??[]); endif; ?>
<?php if($records): ?><div class="table-wrap"><table><thead><tr><?php foreach($columns as $c): ?><th><?= $esc(ucfirst(str_replace('_',' ',$c))) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach(array_slice($records,0,8) as $row): ?><tr><?php foreach($columns as $c): ?><td><?= $esc($row[$c] ?? '—') ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-inline">Aucun enseignement n’est associé à votre profil pour le moment.</div><?php endif; ?>
<?php elseif($page==='profile' && $profile): ?><div class="profile-card"><div class="profile-avatar"><?= $esc(mb_substr($profile['prenom'],0,1).mb_substr($profile['nom'],0,1)) ?></div><div><span class="eyebrow">PROFIL ACADÉMIQUE</span><h2><?= $esc($profile['prenom'].' '.$profile['nom']) ?></h2><p><?= $esc($_SESSION['user_email']) ?></p></div></div><div class="detail-grid"><?php foreach($profile as $key=>$value): ?><div class="detail-item"><span><?= $esc(ucfirst(str_replace('_',' ',$key))) ?></span><b><?= $esc($value ?: '—') ?></b></div><?php endforeach; ?></div>
<?php elseif(in_array($page,['announcements','notifications'],true)): ?><div class="empty-card"><span class="empty-symbol">✦</span><h2><?= $page==='announcements'?'Les annonces apparaîtront ici':'Vous êtes à jour' ?></h2><p>Le schéma SQL actuel ne contient pas de table pour les <?= $page==='announcements'?'annonces':'notifications' ?>. Aucun contenu factice n’est affiché.</p></div>
<?php else: ?>
<?php if($role==='ENSEIGNANT' && $page==='grades'): ?><div class="section-heading"><div><span class="eyebrow">SAISIE / MODIFICATION</span><h2>Enregistrer une note</h2></div></div><form class="grade-form" method="post"><label>Évaluation<select name="id_evaluation" required><option value="">Choisir une évaluation</option><?php foreach(rows($pdo,'SELECT ev.id_evaluation, ev.libelle, ev.note_max, m.code_module FROM Evaluation ev JOIN Enseignement en ON en.id_enseignement=ev.id_enseignement JOIN Module m ON m.id_module=en.id_module WHERE en.id_enseignant=? ORDER BY ev.date_evaluation DESC',[(int)$profile['id_enseignant']]) as $ev): ?><option value="<?= (int)$ev['id_evaluation'] ?>"><?= $esc($ev['code_module'].' · '.$ev['libelle'].' ( / '.$ev['note_max'].')') ?></option><?php endforeach; ?></select></label><label>Inscription étudiant<select name="id_inscription" required><option value="">Choisir un étudiant</option><?php foreach(rows($pdo,'SELECT DISTINCT i.id_inscription, e.CNE, e.prenom, e.nom, m.code_module FROM Inscription i JOIN Etudiant e ON e.id_etudiant=i.id_etudiant JOIN Enseignement en ON en.id_filiere=i.id_filiere AND en.id_annee=i.id_annee JOIN Module m ON m.id_module=en.id_module WHERE en.id_enseignant=? AND i.statut="VALIDEE" ORDER BY e.nom',[(int)$profile['id_enseignant']]) as $st): ?><option value="<?= (int)$st['id_inscription'] ?>"><?= $esc($st['code_module'].' · '.$st['CNE'].' · '.$st['prenom'].' '.$st['nom']) ?></option><?php endforeach; ?></select></label><label>Note<input type="number" name="valeur" min="0" step="0.01" required placeholder="Ex. 15.50"></label><label>Observation <span class="optional">(facultative)</span><input type="text" name="observation" maxlength="1000" placeholder="Ajouter une observation"></label><button class="primary-button" type="submit" name="save_grade" value="1">Enregistrer la note</button></form><?php endif; ?>
<div class="section-heading"><div><span class="eyebrow">DONNÉES ACADÉMIQUES</span><h2><?= $esc($title) ?></h2></div><?php if($role==='ENSEIGNANT' && $page==='grades'): ?><span class="muted">Valeurs issues des tables Note, Évaluation et Inscription</span><?php endif; ?></div>
<?php if($records): ?><div class="table-wrap"><table><thead><tr><?php foreach($columns as $c): ?><th><?= $esc(ucfirst(str_replace('_',' ',$c))) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($records as $row): ?><tr><?php foreach($columns as $c): ?><td><?php if($c==='statut_validation'||$c==='statut'||$c==='actif'): ?><span class="status-pill"><?= $esc($row[$c] ?: '—') ?></span><?php else: ?><?= $esc($row[$c] ?? '—') ?><?php endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-inline">Aucune donnée à afficher pour le moment.</div><?php endif; ?>
<?php endif; ?>
<footer class="page-footer">Gestio Pédagogique <span>·</span> Données issues de la base académique</footer>
</section></main></div><script>document.querySelectorAll('.nav-link').forEach(link=>link.addEventListener('click',()=>document.body.classList.remove('menu-open')));</script></body></html>

