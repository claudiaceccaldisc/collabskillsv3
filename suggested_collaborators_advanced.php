<?php
require_once 'assets/includes/session_check.php';
require_once 'assets/includes/db.php';
require_once 'assets/includes/csrf.php';
include 'assets/includes/header.php';

$missionId = isset($_GET['mission_id']) ? (int)$_GET['mission_id'] : 0;

if (!$missionId) {
    echo '<div class="container mt-5"><p class="text-danger">Mission non spécifiée.</p></div>';
    include 'assets/includes/footer.php';
    exit;
}

// Récupération de la mission
$mSql = "SELECT * FROM missions WHERE id = :mid";
$mStmt = $pdo->prepare($mSql);
$mStmt->execute(['mid' => $missionId]);
$mission = $mStmt->fetch(PDO::FETCH_ASSOC);

if (!$mission) {
    echo '<div class="container mt-5"><p class="text-danger">Mission introuvable.</p></div>';
    include 'assets/includes/footer.php';
    exit;
}

// Récupérer les compétences requises pour la mission
$reqSql = "SELECT s.skill_name, mrs.min_level 
           FROM mission_required_skills mrs 
           JOIN skills s ON mrs.skill_id = s.id 
           WHERE mrs.mission_id = :mid";
$reqStmt = $pdo->prepare($reqSql);
$reqStmt->execute(['mid' => $missionId]);
$requiredSkills = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les agents non collaborateurs pour la mission
$sqlAgents = "SELECT a.id, a.username, p.location, p.languages, p.availability_hours 
              FROM agents a 
              LEFT JOIN agent_profiles p ON a.id = p.agent_id 
              WHERE a.id NOT IN (SELECT agent_id FROM mission_collaborators WHERE mission_id = :mid) 
              ORDER BY a.username";
$aStmt = $pdo->prepare($sqlAgents);
$aStmt->execute(['mid' => $missionId]);
$agents = $aStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les compétences des agents
$skSql = "SELECT ask.agent_id, s.skill_name, ask.level 
          FROM agent_skills ask 
          JOIN skills s ON ask.skill_id = s.id";
$skRows = $pdo->query($skSql)->fetchAll(PDO::FETCH_ASSOC);

$agentSkillsMap = [];
foreach ($skRows as $r) {
    $aid = $r['agent_id'];
    if (!isset($agentSkillsMap[$aid])) {
        $agentSkillsMap[$aid] = [];
    }
    $agentSkillsMap[$aid][$r['skill_name']] = (int)$r['level'];
}

// Calculer le score de matching pour chaque agent
$results = [];
foreach ($agents as $a) {
    $aid = $a['id'];
    $totalReq = count($requiredSkills);
    $matchCount = 0;
    foreach ($requiredSkills as $rs) {
        $sn = $rs['skill_name'];
        $reqLv = (int)$rs['min_level'];
        $userLv = isset($agentSkillsMap[$aid][$sn]) ? $agentSkillsMap[$aid][$sn] : 0;
        if ($userLv >= $reqLv) {
            $matchCount++;
        }
    }
    $scoreSkills = $totalReq > 0 ? ($matchCount / $totalReq) : 0;
    
    // Score basé sur les heures disponibles
    $neededHours = (int)$mission['required_hours'];
    $userHours = (int)($a['availability_hours'] ?? 0);
    $scoreTime = $neededHours > 0 ? min(1.0, $userHours / $neededHours) : 1.0;
    
    // Score sur la langue
    $missionLang = strtolower($mission['required_main_language'] ?? 'fr');
    $agentLangs = array_map('trim', explode(',', strtolower($a['languages'] ?? '')));
    $scoreLang = in_array($missionLang, $agentLangs) ? 1.0 : 0.0;
    
    // Score sur la localisation (si mission non remote)
    $scoreLoc = $mission['is_remote'] ? 1.0 : (strtolower($a['location'] ?? '') === 'agen' ? 1.0 : 0.0);
    
    // Calcul du score global avec pondération
    $Wskills = 0.5;
    $Wtime   = 0.2;
    $Wlang   = 0.2;
    $Wloc    = 0.1;
    $score = $Wskills * $scoreSkills + $Wtime * $scoreTime + $Wlang * $scoreLang + $Wloc * $scoreLoc;
    
    $results[] = [
        'agent_id'     => $aid,
        'username'     => $a['username'],
        'score'        => $score,
        'skills_match' => $matchCount . '/' . $totalReq,
        'hours'        => $userHours,
        'languages'    => $a['languages'] ?? 'N/A',
        'location'     => $a['location'] ?? 'Non défini'
    ];
}
usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

// Traitement de l'invitation
if (isset($_GET['invite'])) {
    $inviteId = (int)$_GET['invite'];
    if ($inviteId > 0) {
        // Vérification CSRF via token passé en GET
        checkCsrfToken($_GET['csrf_token'] ?? '');
        $ins = $pdo->prepare("INSERT IGNORE INTO mission_collaborators (mission_id, agent_id) VALUES (:mid, :aid)");
        $ins->execute(['mid' => $missionId, 'aid' => $inviteId]);

        // Ajout d'une notification pour l'agent invité
        $notifSql = "INSERT INTO notifications (agent_id, message, is_read, created_at) 
                     VALUES (:aid, :msg, 0, NOW())";
        $notifStmt = $pdo->prepare($notifSql);
        $notifStmt->execute([
            'aid' => $inviteId,
            'msg' => "Vous avez été invité à collaborer sur la mission : " . htmlspecialchars($mission['title'])
        ]);

        header("Location: suggested_collaborators_adapted.php?mission_id=$missionId&invited=success");
        exit;
    }
}
?>

<div class="container mt-5">
    <h1 class="mb-4">Suggestions de collaborateurs pour "<?= htmlspecialchars($mission['title']) ?>"</h1>
    <?php if (isset($_GET['invited'])): ?>
        <div class="alert alert-success">Agent invité avec succès ! Une notification a été envoyée.</div>
    <?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Agents suggérés</h4>
        </div>
        <div class="card-body">
            <?php if ($results): ?>
                <table class="table table-hover">
                    <thead class="table-primary">
                        <tr>
                            <th>Agent</th>
                            <th>Score</th>
                            <th>Compétences</th>
                            <th>Heures dispo</th>
                            <th>Langues</th>
                            <th>Localisation</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['username']) ?></td>
                                <td><span class="badge bg-success"><?= number_format($r['score'] * 100, 1) ?>%</span></td>
                                <td><?= htmlspecialchars($r['skills_match']) ?></td>
                                <td><?= htmlspecialchars($r['hours']) ?></td>
                                <td><?= htmlspecialchars($r['languages']) ?></td>
                                <td><?= htmlspecialchars($r['location']) ?></td>
                                <td>
                                    <a href="suggested_collaborators_adapted.php?mission_id=<?= $missionId ?>&invite=<?= $r['agent_id'] ?>&csrf_token=<?= htmlspecialchars(generateCsrfToken()) ?>" class="btn btn-primary btn-sm">Inviter</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">Aucun collaborateur potentiel disponible pour cette mission.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="mt-4">
        <a href="mission_management.php?action=view&id=<?= $missionId ?>" class="btn btn-outline-secondary">Retour à la mission</a>
    </div>
</div>
<?php include 'assets/includes/footer.php'; ?>
