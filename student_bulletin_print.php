<?php
require_once 'config.php';

// Sécurité: uniquement les étudiants connectés
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    redirect('student_login.php');
}

$pdo = getDatabaseConnection();
$etudiant = [];
$matieres = [];
$notes = [];
$bulletin = [];

try {
    // Infos étudiant (filière, classe, université)
    $stmt = $pdo->prepare('
        SELECT e.*, c.nom as classe_nom, c.annee, c.semestre, f.nom as filiere_nom,
               u.nom as universite_nom, u.logo_path as universite_logo, u.slogan as universite_slogan
        FROM etudiants e 
        LEFT JOIN classes c ON e.classe_id = c.id 
        LEFT JOIN filieres f ON e.filiere_id = f.id 
        LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
        LEFT JOIN universites u ON uf.universite_id = u.id 
        WHERE e.id = ?
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$etudiant) {
        redirect('student_login.php');
    }

    // Matières de la classe
    $stmt = $pdo->prepare('
        SELECT DISTINCT m.id, m.nom as matiere_nom, m.code as matiere_code
        FROM matieres m
        INNER JOIN affectations a ON m.id = a.matiere_id
        WHERE a.classe_id = ?
        ORDER BY m.nom
    ');
    $stmt->execute([$etudiant['classe_id']]);
    $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Présence de type_note ? (nouveau schéma)
    $chkCol = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notes' AND COLUMN_NAME = 'type_note'");
    $chkCol->execute();
    $hasTypeNote = ((int)$chkCol->fetchColumn() > 0);

    if ($hasTypeNote) {
        $stmt = $pdo->prepare('
            SELECT n.*, m.nom as matiere_nom, m.code as matiere_code,
                   n.note, n.type_note, n.annee_academique
            FROM notes n
            INNER JOIN matieres m ON n.matiere_id = m.id
            WHERE n.etudiant_id = ?
            ORDER BY m.nom, n.type_note
        ');
    } else {
        $stmt = $pdo->prepare('
            SELECT n.*, m.nom as matiere_nom, m.code as matiere_code,
                   n.note, n.session, n.annee_academique
            FROM notes n
            INNER JOIN matieres m ON n.matiere_id = m.id
            WHERE n.etudiant_id = ?
            ORDER BY m.nom, n.session
        ');
    }
    $stmt->execute([$_SESSION['user_id']]);
    $notes_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Regrouper par matière
    foreach ($notes_raw as $note) {
        $notes[$note['matiere_id']][] = $note;
    }

    // Construire le bulletin
    $total_moyennes = 0; $count_moyennes = 0;
    foreach ($matieres as $matiere) {
        $matiere_id = $matiere['id'];
        $notes_matiere = isset($notes[$matiere_id]) ? $notes[$matiere_id] : [];
        $note_classe = null; $note_examen = null; $total = 0; $count = 0;

        foreach ($notes_matiere as $n) {
            if (isset($n['type_note'])) {
                if ($n['type_note'] === 'classe') $note_classe = $n['note'];
                elseif ($n['type_note'] === 'examen') $note_examen = $n['note'];
            } else {
                if (isset($n['session']) && $n['session'] === 'normale') $note_classe = $n['note'];
                elseif (isset($n['session'])) $note_examen = $n['note'];
            }
            $total += $n['note'];
            $count++;
        }

        // Calcul de la moyenne pondérée: 40% note de classe, 60% note d'examen
        $moyenne = null;
        if ($note_classe !== null && $note_examen !== null) {
            $moyenne = (0.4 * (float)$note_classe) + (0.6 * (float)$note_examen);
        } elseif ($note_classe !== null) {
            // Si seule la note de classe est présente, on l'utilise telle quelle
            $moyenne = (float)$note_classe;
        } elseif ($note_examen !== null) {
            // Si seule la note d'examen est présente, on l'utilise telle quelle
            $moyenne = (float)$note_examen;
        }

        if ($moyenne !== null) { $total_moyennes += $moyenne; $count_moyennes++; }

        $bulletin[] = [
            'matiere_nom' => $matiere['matiere_nom'],
            'matiere_code' => $matiere['matiere_code'],
            'note_classe' => $note_classe,
            'note_examen' => $note_examen,
            'moyenne' => $moyenne,
            'mention' => $moyenne !== null ? ($moyenne >= 16 ? 'Très Bien' : ($moyenne >= 14 ? 'Bien' : ($moyenne >= 12 ? 'Assez Bien' : ($moyenne >= 10 ? 'Passable' : 'Insuffisant')))) : 'Non évalué'
        ];
    }

    $moyenne_generale = $count_moyennes > 0 ? $total_moyennes / $count_moyennes : null;

} catch (PDOException $e) {
    $error = 'Erreur: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulletin - <?php echo htmlspecialchars(($etudiant['prenom'] ?? '') . ' ' . ($etudiant['nom'] ?? '')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            .table th, .table td { padding: 0.45rem !important; }
            .meta-row { display: flex !important; }
            .meta-left, .meta-right { width: 50% !important; }
            .brand-logo-wrap { text-align: left !important; transform: none !important; margin-left: -32px !important; }
        }
        body { background: #f7f7f7; }
        .sheet {
            background: #fff; max-width: 900px; margin: 20px auto; padding: 32px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.08); border-radius: 12px;
        }
        .header-brand { border-bottom: 2px solid #e9ecef; margin-bottom: 18px; padding-bottom: 12px; }
        .brand-title { font-weight: 700; font-size: 1.25rem; }
        .meta small { color: #6c757d; }
        .badge-mention { font-size: .9rem; }
        .uni-logo { height: 64px; max-width: 160px; object-fit: contain; }
        .uni-slogan { font-style: italic; color: #6c757d; font-size: 0.95rem; }
        /* Ensure two-column meta layout on screen and print */
        .meta-row { display: flex; gap: 16px; }
        .meta-left, .meta-right { width: 50%; }
        .meta-right { text-align: right; }
        .brand-logo-wrap { text-align: left; transform: none; margin-left: -24px; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="d-flex justify-content-between align-items-center header-brand">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-logo-wrap">
                    <?php if (!empty($etudiant['universite_logo'])): ?>
                        <img src="<?php echo htmlspecialchars($etudiant['universite_logo']); ?>" alt="Logo Université" class="uni-logo">
                    <?php endif; ?>
                    <?php if (!empty($etudiant['universite_slogan'])): ?>
                        <div class="uni-slogan mt-1"><?php echo htmlspecialchars($etudiant['universite_slogan']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-end">
                <button class="btn btn-primary no-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimer
                </button>
            </div>
        </div>

        <div class="meta-row mb-3 meta">
            <div class="meta-left">
                <div><strong>Étudiant:</strong> <?php echo htmlspecialchars(($etudiant['prenom'] ?? '') . ' ' . ($etudiant['nom'] ?? '')); ?></div>
                <div><strong>Numéro:</strong> <?php echo htmlspecialchars($etudiant['numero_etudiant'] ?? '-'); ?></div>
                <div><strong>Filière:</strong> <?php echo htmlspecialchars($etudiant['filiere_nom'] ?? '-'); ?></div>
            </div>
            <div class="meta-right">
                <div><strong>Université:</strong> <?php echo htmlspecialchars($etudiant['universite_nom'] ?? '-'); ?></div>
                <div><strong>Classe:</strong> <?php echo htmlspecialchars($etudiant['classe_nom'] ?? '-'); ?></div>
                <div><strong>Date d'édition:</strong> <?php echo date('d/m/Y H:i'); ?></div>
            </div>
        </div>

        <?php if (!empty($bulletin)): ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40%">Matière</th>
                            <th class="text-center" style="width:15%">Note Classe</th>
                            <th class="text-center" style="width:15%">Note Examen</th>
                            <th class="text-center" style="width:15%">Moyenne</th>
                            <th class="text-center" style="width:15%">Mention</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bulletin as $ligne): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($ligne['matiere_nom']); ?></strong>
                                    <?php if (!empty($ligne['matiere_code'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($ligne['matiere_code']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?php echo $ligne['note_classe'] !== null ? number_format($ligne['note_classe'], 2) : '—'; ?></td>
                                <td class="text-center"><?php echo $ligne['note_examen'] !== null ? number_format($ligne['note_examen'], 2) : '—'; ?></td>
                                <td class="text-center"><?php echo $ligne['moyenne'] !== null ? number_format($ligne['moyenne'], 2) : '—'; ?></td>
                                <td class="text-center">
                                    <?php 
                                        $mention = $ligne['mention']; 
                                        $cls = ($mention === 'Très Bien') ? 'bg-success' : (($mention === 'Bien') ? 'bg-info' : (($mention === 'Assez Bien') ? 'bg-warning' : (($mention === 'Passable') ? 'bg-secondary' : 'bg-light text-dark')));
                                    ?>
                                    <span class="badge badge-mention <?php echo $cls; ?>"><?php echo htmlspecialchars($mention); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3">Moyenne Générale</th>
                            <th class="text-center">
                                <?php echo $moyenne_generale !== null ? number_format($moyenne_generale, 2) : '—'; ?>
                            </th>
                            <th class="text-center">
                                <?php if ($moyenne_generale !== null): ?>
                                    <?php 
                                        $mention_generale = $moyenne_generale >= 16 ? 'Très Bien' : ($moyenne_generale >= 14 ? 'Bien' : ($moyenne_generale >= 12 ? 'Assez Bien' : ($moyenne_generale >= 10 ? 'Passable' : 'Insuffisant')));
                                        $cls = ($mention_generale === 'Très Bien') ? 'bg-success' : (($mention_generale === 'Bien') ? 'bg-info' : (($mention_generale === 'Assez Bien') ? 'bg-warning' : (($mention_generale === 'Passable') ? 'bg-secondary' : 'bg-light text-dark')));
                                    ?>
                                    <span class="badge <?php echo $cls; ?>"><?php echo $mention_generale; ?></span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Aucune donnée disponible pour générer le bulletin.</div>
        <?php endif; ?>

        <div class="text-center mt-4 no-print">
            <a href="student_dashboard.php" class="btn btn-outline-secondary">Retour au tableau de bord</a>
            <button class="btn btn-primary ms-2" onclick="window.print()">Imprimer le bulletin</button>
        </div>
    </div>

    <script>
        // Décommente pour lancer l'impression automatiquement
        // window.onload = () => window.print();
    </script>
</body>
</html>
