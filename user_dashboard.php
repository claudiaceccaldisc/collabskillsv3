<?php
require_once 'assets/includes/session_check.php';
require_once 'assets/includes/db.php';
require_once 'assets/includes/csrf.php';
include 'assets/includes/header.php';
include 'assets/includes/particles-background.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php?action=login');
    exit;
}

$section = $_GET['section'] ?? 'dashboard';
$agentId = $_SESSION['user_id'];
$errors = [];

/* Fonctions utilitaires */
function fetchProfile($pdo, $agentId) {
    $stmt = $pdo->prepare("SELECT a.username, a.email, a.service, p.location, p.languages, p.availability_hours, p.experience
                           FROM agents a LEFT JOIN agent_profiles p ON a.id = p.agent_id
                           WHERE a.id = :aid");
    $stmt->execute(['aid' => $agentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function fetchSkills($pdo, $agentId) {
    $stmt = $pdo->prepare("SELECT s.id, s.skill_name, ask.level 
                           FROM agent_skills ask JOIN skills s ON ask.skill_id = s.id 
                           WHERE ask.agent_id = :aid ORDER BY s.skill_name");
    $stmt->execute(['aid' => $agentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchMissions($pdo, $agentId) {
    $stmt = $pdo->prepare("SELECT m.*, COUNT(mc.agent_id) as collaborators 
                           FROM missions m JOIN mission_collaborators mc ON m.id = mc.mission_id 
                           WHERE mc.agent_id = :uid GROUP BY m.id ORDER BY m.created_at DESC");
    $stmt->execute(['uid' => $agentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchNotifications($pdo, $agentId, $limit = 50) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE agent_id = :aid ORDER BY id DESC LIMIT :lim");
    $stmt->bindValue(':aid', $agentId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* Récupération des données de l'utilisateur */
$profile = fetchProfile($pdo, $agentId);
$skills = fetchSkills($pdo, $agentId);
$missions = fetchMissions($pdo, $agentId);
$notifications = fetchNotifications($pdo, $agentId, $section === 'dashboard' ? 5 : 50);
?>
<div class="container mt-5">
    <!-- Navigation principale -->
    <nav class="mb-4">
        <a href="?section=dashboard" class="btn btn-outline-primary <?= $section === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="?section=profile" class="btn btn-outline-primary <?= $section === 'profile' ? 'active' : '' ?>">Profil</a>
        <a href="?section=notifications" class="btn btn-outline-primary <?= $section === 'notifications' ? 'active' : '' ?>">Notifications</a>
    </nav>

    <!-- Section d'accueil -->
    <div class="mb-4">
        <div class="p-4 bg-light rounded">
            <h1 class="display-5">Bienvenue, <?= htmlspecialchars($profile['username']) ?>!</h1>
            <p class="lead">Voici un aperçu de votre activité sur CAF 47 Collab.</p>
        </div>
    </div>

    <?php if ($section === 'dashboard'): ?>
        <?php
        // Widget de suggestions de collaborateurs
        if (!empty($missions)) {
            // Choisir la mission la plus récente comme mission active
            $currentMission = $missions[0];
            $missionIdForMatch = $currentMission['id'];

            // Récupérer les compétences requises pour la mission
            $reqSkillsStmt = $pdo->prepare("SELECT s.skill_name, mrs.min_level 
                                            FROM mission_required_skills mrs 
                                            JOIN skills s ON mrs.skill_id = s.id 
                                            WHERE mrs.mission_id = :mid");
            $reqSkillsStmt->execute(['mid' => $missionIdForMatch]);
            $requiredSkills = $reqSkillsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer les agents non déjà collaborateurs pour cette mission
            $sqlAgents = "SELECT a.id, a.username, p.location, p.languages, p.availability_hours 
                          FROM agents a 
                          LEFT JOIN agent_profiles p ON a.id = p.agent_id 
                          WHERE a.id NOT IN (SELECT agent_id FROM mission_collaborators WHERE mission_id = :mid) 
                          ORDER BY a.username";
            $aStmt = $pdo->prepare($sqlAgents);
            $aStmt->execute(['mid' => $missionIdForMatch]);
            $agents = $aStmt->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer les compétences de tous les agents
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

            // Calcul du score de matching pour chaque agent
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
                $neededHours = (int)$currentMission['required_hours'];
                $userHours = (int)($a['availability_hours'] ?? 0);
                $scoreTime = $neededHours > 0 ? min(1.0, $userHours / $neededHours) : 1.0;
                // Score sur la langue
                $missionLang = strtolower($currentMission['required_main_language'] ?? 'fr');
                $agentLangs = array_map('trim', explode(',', strtolower($a['languages'] ?? '')));
                $scoreLang = in_array($missionLang, $agentLangs) ? 1.0 : 0.0;
                // Score sur la localisation (si la mission n'est pas remote)
                $scoreLoc = $currentMission['is_remote'] ? 1.0 : (strtolower($a['location'] ?? '') === 'agen' ? 1.0 : 0.0);
                // Pondération des critères
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
            // Tri décroissant par score
            usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
            // Conserver les trois meilleurs résultats
            $topSuggestions = array_slice($results, 0, 3);
        }
        ?>
        <!-- Widget Suggestions de collaborateurs -->
        <div class="card shadow-sm mb-5">
            <div class="card-header bg-warning text-white">
                <h3 class="mb-0">Suggestions de collaborateurs</h3>
                <?php if (!empty($missions)): ?>
                    <small>Pour la mission : <?= htmlspecialchars($currentMission['title']) ?></small>
                <?php else: ?>
                    <small>Aucune mission active</small>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($missions) && !empty($topSuggestions)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
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
                                <?php foreach ($topSuggestions as $s): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['username']) ?></td>
                                        <td><span class="badge bg-success"><?= number_format($s['score'] * 100, 1) ?>%</span></td>
                                        <td><?= htmlspecialchars($s['skills_match']) ?></td>
                                        <td><?= htmlspecialchars($s['hours']) ?></td>
                                        <td><?= htmlspecialchars($s['languages']) ?></td>
                                        <td><?= htmlspecialchars($s['location']) ?></td>
                                        <td>
                                            <a href="suggested_collaborators_adapted.php?mission_id=<?= $missionIdForMatch ?>&invite=<?= $s['agent_id'] ?>&csrf_token=<?= htmlspecialchars(generateCsrfToken()) ?>" class="btn btn-primary btn-sm">Inviter</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif (!empty($missions)): ?>
                    <p class="text-muted">Aucun collaborateur potentiel trouvé pour la mission sélectionnée.</p>
                <?php else: ?>
                    <p class="text-muted">Aucune mission active pour afficher les suggestions.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Section Missions et Notifications -->
        <div class="row">
            <!-- Mes Missions -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0">Mes Missions</h2>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if ($missions): ?>
                            <?php foreach ($missions as $mission): ?>
                                <div class="card mb-3 border-0">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($mission['title']) ?></h5>
                                        <p class="card-text"><?= htmlspecialchars(substr($mission['description'], 0, 100)) . (strlen($mission['description']) > 100 ? '...' : '') ?></p>
                                        <p class="text-muted small">Collaborateurs : <?= $mission['collaborators'] ?></p>
                                        <a href="mission_management.php?action=view&id=<?= $mission['id'] ?>" class="btn btn-info btn-sm">Voir</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">Vous n'avez pas encore de missions.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Notifications -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h2 class="mb-0">Notifications <span class="badge bg-light text-dark"><?= count($notifications) ?></span></h2>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if ($notifications): ?>
                            <ul class="list-group">
                                <?php foreach ($notifications as $nt): ?>
                                    <li class="list-group-item <?= $nt['is_read'] ? '' : 'list-group-item-info' ?>">
                                        <?= nl2br(htmlspecialchars($nt['message'])) ?>
                                        <br><small class="text-muted"><?= date('d/m/Y H:i', strtotime($nt['created_at'])) ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="?section=notifications" class="btn btn-outline-info btn-sm mt-3">Voir toutes les notifications</a>
                        <?php else: ?>
                            <p class="text-muted">Aucune notification récente.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif ($section === 'profile'): ?>
        <!-- Section Profil -->
        <h1 class="mb-4">Mon Profil</h1>
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">Profil mis à jour avec succès !</div>
        <?php elseif (isset($_GET['skill_added'])): ?>
            <div class="alert alert-info">Compétence ajoutée/mise à jour !</div>
        <?php elseif (isset($_GET['skill_deleted'])): ?>
            <div class="alert alert-warning">Compétence supprimée !</div>
        <?php elseif (isset($_GET['skill_updated'])): ?>
            <div class="alert alert-success">Niveau de compétence mis à jour !</div>
        <?php endif; ?>
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Informations</h3>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Nom d'utilisateur :</strong> <?= htmlspecialchars($profile['username']) ?></li>
                        <li class="list-group-item"><strong>Email :</strong> <?= htmlspecialchars($profile['email']) ?></li>
                        <li class="list-group-item"><strong>Service :</strong> <?= htmlspecialchars($profile['service']) ?></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h3 class="mb-0">Mettre à jour le profil</h3>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="mb-3">
                                <label for="location" class="form-label">Localisation :</label>
                                <input type="text" name="location" id="location" class="form-control" value="<?= htmlspecialchars($profile['location'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="languages" class="form-label">Langues (ex: fr, en) :</label>
                                <input type="text" name="languages" id="languages" class="form-control" value="<?= htmlspecialchars($profile['languages'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="availability_hours" class="form-label">Disponibilité (heures/semaine) :</label>
                                <input type="number" name="availability_hours" id="availability_hours" class="form-control" value="<?= htmlspecialchars($profile['availability_hours'] ?? 0) ?>" min="0">
                            </div>
                            <div class="mb-3">
                                <label for="experience" class="form-label">Expérience :</label>
                                <textarea name="experience" id="experience" class="form-control" rows="3"><?= htmlspecialchars($profile['experience'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Enregistrer</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h3 class="mb-0">Mes Compétences</h3>
            </div>
            <div class="card-body">
                <?php if ($skills): ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($skills as $sk): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <strong><?= htmlspecialchars($sk['skill_name']) ?></strong> - Niveau <?= (int)$sk['level'] ?>/5
                                </span>
                                <div>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                        <input type="hidden" name="update_skill" value="1">
                                        <input type="hidden" name="skill_id" value="<?= $sk['id'] ?>">
                                        <input type="number" name="new_level" value="<?= $sk['level'] ?>" min="1" max="5" class="form-control form-control-sm d-inline-block" style="width: 70px;">
                                        <button type="submit" class="btn btn-sm btn-secondary">Mettre à jour</button>
                                    </form>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                        <input type="hidden" name="delete_skill" value="1">
                                        <input type="hidden" name="skill_id" value="<?= $sk['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">Aucune compétence renseignée.</p>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                    <input type="hidden" name="add_skill" value="1">
                    <div class="mb-3">
                        <label for="skill_name" class="form-label">Nouvelle compétence :</label>
                        <input type="text" name="skill_name" id="skill_name" class="form-control" placeholder="Ex: RSA, GDEA">
                    </div>
                    <div class="mb-3">
                        <label for="level" class="form-label">Niveau (1=Débutant, 5=Expert) :</label>
                        <input type="number" name="level" id="level" class="form-control" min="1" max="5" value="3">
                    </div>
                    <button type="submit" class="btn btn-info w-100">Ajouter / Mettre à jour</button>
                </form>
            </div>
        </div>
    <?php elseif ($section === 'notifications'): ?>
        <h1 class="mb-4">Mes Notifications</h1>
        <?php if (isset($_GET['marked'])): ?>
            <div class="alert alert-success">Toutes les notifications ont été marquées comme lues.</div>
        <?php endif; ?>
        <div class="d-flex justify-content-between mb-3">
            <a href="?section=notifications&mark_all_read=1" class="btn btn-secondary">Marquer tout comme lu</a>
            <span class="badge bg-primary"><?= count($notifications) ?> notifications</span>
        </div>
        <div class="card shadow-sm">
            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                <?php if ($notifications): ?>
                    <ul class="list-group">
                        <?php foreach ($notifications as $n): ?>
                            <li class="list-group-item <?= $n['is_read'] ? '' : 'list-group-item-info' ?>">
                                <?= nl2br(htmlspecialchars($n['message'])) ?>
                                <br><small class="text-muted"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">Aucune notification récente.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include 'assets/includes/footer.php'; ?>
