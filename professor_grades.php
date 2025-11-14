<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'professeur') {
    redirect('student_login.php');
}

$pdo = getDatabaseConnection();
$profId = (int)$_SESSION['user_id'];
$year = DEFAULT_ACADEMIC_YEAR;

// Classes du professeur (toutes sources)
$classes = [];
try {
    $sqlClasses = "SELECT DISTINCT c.id, c.nom
                   FROM classes c
                   WHERE c.id IN (
                       SELECT classe_id FROM professeur_classe WHERE professeur_id = ?
                   )
                   OR c.id IN (
                       SELECT classe_id FROM affectations WHERE professeur_id = ?
                   )
                   OR c.filiere_id IN (
                       SELECT DISTINCT m.filiere_id
                       FROM matiere_professeur mp
                       INNER JOIN matieres m ON mp.matiere_id = m.id
                       WHERE mp.professeur_id = ?
                   )
                   ORDER BY c.nom";
    $stmt = $pdo->prepare($sqlClasses);
    $stmt->execute([$profId, $profId, $profId]);
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    // silencieux: pas d'interruption de la page
}

$selectedClasseId = isset($_GET['classe_id']) ? (int)$_GET['classe_id'] : (isset($_POST['classe_id']) ? (int)$_POST['classe_id'] : 0);
// Définir la matière sélectionnée tôt pour éviter les redirections en boucle
$selectedMatiereId = isset($_GET['matiere_id']) ? (int)$_GET['matiere_id'] : (isset($_POST['matiere_id']) ? (int)$_POST['matiere_id'] : 0);

// Debug facultatif
$debugInfo = [];
if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['debug'])) {
    try {
        $c1 = $pdo->prepare("SELECT COUNT(*) FROM professeur_classe WHERE professeur_id = ?");
        $c1->execute([$profId]);
        $countPC = (int)$c1->fetchColumn();

        $c2 = $pdo->prepare("SELECT COUNT(DISTINCT classe_id) FROM affectations WHERE professeur_id = ?");
        $c2->execute([$profId]);
        $countAff = (int)$c2->fetchColumn();

        $c3 = $pdo->prepare("SELECT COUNT(*) FROM matiere_professeur WHERE professeur_id = ?");
        $c3->execute([$profId]);
        $countMP = (int)$c3->fetchColumn();

        $qF = $pdo->prepare("SELECT DISTINCT m.filiere_id FROM matiere_professeur mp INNER JOIN matieres m ON mp.matiere_id = m.id WHERE mp.professeur_id = ?");
        $qF->execute([$profId]);
        $filiereIds = $qF->fetchAll(PDO::FETCH_COLUMN);

        $debugInfo = [
            'session_user_id' => $profId,
            'professeur_classe_count' => $countPC,
            'affectations_classes_count' => $countAff,
            'matiere_professeur_count' => $countMP,
            'filiere_ids_from_mp' => $filiereIds,
            'classes_list_count' => is_array($classes) ? count($classes) : 0,
        ];
    } catch (Exception $e) {
        $debugInfo['error'] = $e->getMessage();
    }
}

// Matières affectées au prof dans cette classe (uniquement via affectations explicites)
$matieres = [];
if ($selectedClasseId) {
    $st2 = $pdo->prepare("SELECT DISTINCT m.id, m.nom, m.code
                          FROM affectations a
                          INNER JOIN matieres m ON a.matiere_id = m.id
                          WHERE a.professeur_id = ? AND a.classe_id = ?
                          ORDER BY m.nom");
    $st2->execute([$profId, $selectedClasseId]);
    $matieres = $st2->fetchAll();

    // Debug per-classe: matières via affectations
    if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['debug'])) {
        try {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM affectations WHERE professeur_id = ? AND classe_id = ?");
            $cnt->execute([$profId, $selectedClasseId]);
            $affCountForClasse = (int)$cnt->fetchColumn();

            $lst = $pdo->prepare("SELECT a.matiere_id, m.nom, m.code FROM affectations a INNER JOIN matieres m ON a.matiere_id=m.id WHERE a.professeur_id=? AND a.classe_id=? ORDER BY m.nom");
            $lst->execute([$profId, $selectedClasseId]);
            $affMatList = $lst->fetchAll(PDO::FETCH_ASSOC);

            $debugInfo['per_class'] = [
                'classe_id' => (int)$selectedClasseId,
                'affectations_count_for_class' => $affCountForClasse,
                'affectations_matieres' => $affMatList,
            ];
        } catch (Exception $e) {
            $debugInfo['per_class_error'] = $e->getMessage();
        }
    }

    // Auto-sélection de la matière si une seule affectation existe et aucune matière encore choisie
    if (empty($selectedMatiereId) && is_array($matieres) && count($matieres) === 1) {
        $autoMat = (int)$matieres[0]['id'];
        // Préserver le flag debug le cas échéant
        $qs = 'classe_id=' . (int)$selectedClasseId . '&matiere_id=' . $autoMat;
        if (isset($_GET['debug'])) { $qs .= '&debug=1'; }
        // Ne pas échapper l'URL dans l'en-tête Location pour éviter de casser les paramètres (& devient &amp;)
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $qs);
        exit();
    }
}

$infoMsg = $errorMsg = '';

// Enregistrement des notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedClasseId && $selectedMatiereId) {
    // Vérification stricte: la matière doit être explicitement affectée à ce professeur pour cette classe
    $ver = $pdo->prepare("SELECT COUNT(*) FROM affectations WHERE professeur_id = ? AND classe_id = ? AND matiere_id = ?");
    $ver->execute([$profId, $selectedClasseId, $selectedMatiereId]);
    if ((int)$ver->fetchColumn() === 0) {
        $errorMsg = "Cette matière n'est pas affectée à ce professeur pour cette classe.";
    } else {
        $notesClasse = $_POST['notes_classe'] ?? [];
        $notesExamen = $_POST['notes_examen'] ?? [];
        try {
            // Sauvegarde notes de classe
            foreach ($notesClasse as $etudiantId => $noteVal) {
                $noteVal = str_replace(',', '.', trim($noteVal));
                if ($noteVal === '') continue;
                if (!isValidNote($noteVal)) continue;
                $chk = $pdo->prepare("SELECT id FROM notes WHERE etudiant_id=? AND matiere_id=? AND annee_academique=? AND type_note='classe'");
                $chk->execute([(int)$etudiantId, $selectedMatiereId, $year]);
                $existing = $chk->fetchColumn();
                if ($existing) {
                    $up = $pdo->prepare("UPDATE notes SET note=? WHERE id=?");
                    $up->execute([$noteVal, $existing]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO notes (etudiant_id, matiere_id, note, type_note, annee_academique) VALUES (?,?,?,?,?)");
                    $ins->execute([(int)$etudiantId, $selectedMatiereId, $noteVal, 'classe', $year]);
                }
            }

            // Sauvegarde notes d'examen
            foreach ($notesExamen as $etudiantId => $noteVal) {
                $noteVal = str_replace(',', '.', trim($noteVal));
                if ($noteVal === '') continue;
                if (!isValidNote($noteVal)) continue;
                $chk = $pdo->prepare("SELECT id FROM notes WHERE etudiant_id=? AND matiere_id=? AND annee_academique=? AND type_note='examen'");
                $chk->execute([(int)$etudiantId, $selectedMatiereId, $year]);
                $existing = $chk->fetchColumn();
                if ($existing) {
                    $up = $pdo->prepare("UPDATE notes SET note=? WHERE id=?");
                    $up->execute([$noteVal, $existing]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO notes (etudiant_id, matiere_id, note, type_note, annee_academique) VALUES (?,?,?,?,?)");
                    $ins->execute([(int)$etudiantId, $selectedMatiereId, $noteVal, 'examen', $year]);
                }
            }

            $infoMsg = 'Notes enregistrées avec succès.';
        } catch (PDOException $e) {
            $errorMsg = "Erreur lors de l'enregistrement des notes.";
        }
    }
}

// Étudiants de la classe + notes existantes
$etudiants = [];
$notesClasseExistantes = [];
$notesExamenExistantes = [];
if ($selectedClasseId && $selectedMatiereId) {
    $se = $pdo->prepare("SELECT id, nom, prenom, numero_etudiant FROM etudiants WHERE classe_id=? ORDER BY nom, prenom");
    $se->execute([$selectedClasseId]);
    $etudiants = $se->fetchAll();

    // Notes existantes: classe
    $snc = $pdo->prepare("SELECT etudiant_id, note FROM notes WHERE matiere_id=? AND annee_academique=? AND type_note='classe'");
    $snc->execute([$selectedMatiereId, $year]);
    foreach ($snc->fetchAll() as $n) {
        $notesClasseExistantes[(int)$n['etudiant_id']] = $n['note'];
    }
    // Notes existantes: examen
    $sne = $pdo->prepare("SELECT etudiant_id, note FROM notes WHERE matiere_id=? AND annee_academique=? AND type_note='examen'");
    $sne->execute([$selectedMatiereId, $year]);
    foreach ($sne->fetchAll() as $n) {
        $notesExamenExistantes[(int)$n['etudiant_id']] = $n['note'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saisie des Notes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="professor_dashboard.php">⟵ Tableau de bord</a>
        <span class="navbar-text">Saisie des Notes</span>
    </div>
</nav>
<div class="container py-4">
    <h3 class="mb-3">Saisie des Notes</h3>

    <?php if ($infoMsg): ?><div class="alert alert-success"><?= htmlspecialchars($infoMsg) ?></div><?php endif; ?>
    <?php if ($errorMsg): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

    <form method="get" class="row gy-2 gx-2 align-items-end mb-4">
        <div class="col-md-4">
            <label class="form-label">Classe</label>
            <select name="classe_id" class="form-select" required onchange="this.form.submit()">
                <option value="">Sélectionner...</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $selectedClasseId===(int)$c['id']?'selected':'' ?>><?= htmlspecialchars($c['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Matière</label>
            <select name="matiere_id" class="form-select" required <?= $selectedClasseId? '':'disabled' ?> onchange="this.form.submit()">
                <option value="">Sélectionner...</option>
                <?php foreach ($matieres as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= $selectedMatiereId===(int)$m['id']?'selected':'' ?>>
                        <?= htmlspecialchars($m['nom']) ?><?= $m['code']?' ('.htmlspecialchars($m['code']).')':'' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($selectedClasseId && $selectedMatiereId): ?>
        <form method="post" class="card shadow-sm">
            <input type="hidden" name="classe_id" value="<?= (int)$selectedClasseId ?>">
            <input type="hidden" name="matiere_id" value="<?= (int)$selectedMatiereId ?>">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Matricule</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Note de classe (/20)</th>
                                <th>Note d'examen (/20)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i=1; foreach ($etudiants as $e): $eid=(int)$e['id']; ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($e['numero_etudiant']) ?></td>
                                    <td><?= htmlspecialchars($e['nom']) ?></td>
                                    <td><?= htmlspecialchars($e['prenom']) ?></td>
                                    <td style="max-width:140px">
                                        <input type="number" name="notes_classe[<?= $eid ?>]" step="0.01" min="<?= MIN_NOTE ?>" max="<?= MAX_NOTE ?>" value="<?= isset($notesClasseExistantes[$eid])? htmlspecialchars($notesClasseExistantes[$eid]):'' ?>" class="form-control form-control-sm" placeholder="ex: 12.50">
                                    </td>
                                    <td style="max-width:140px">
                                        <input type="number" name="notes_examen[<?= $eid ?>]" step="0.01" min="<?= MIN_NOTE ?>" max="<?= MAX_NOTE ?>" value="<?= isset($notesExamenExistantes[$eid])? htmlspecialchars($notesExamenExistantes[$eid]):'' ?>" class="form-control form-control-sm" placeholder="ex: 12.50">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <div class="text-muted">Année académique: <strong><?= htmlspecialchars($year) ?></strong></div>
                <button class="btn btn-success">Enregistrer les notes</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
