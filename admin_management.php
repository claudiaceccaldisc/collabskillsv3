<?php
// admin_management.php
require_once 'assets/includes/session_check.php';
require_once 'assets/includes/db.php';
require_once 'assets/includes/csrf.php';

// Vérifier que l'utilisateur est admin (une seule fois)
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="container mt-5"><p class="text-danger">Accès réservé aux administrateurs.</p></div>';
    include 'assets/includes/footer.php';
    exit;
}

// Initialisation des variables
$action = $_GET['action'] ?? 'dashboard';
$agentId = $_GET['id'] ?? 0;
$errors = [];

// Fonctions utilitaires
function fetchAgent($pdo, $agentId) {
    $sql = "SELECT a.id, a.username, a.email, a.role, p.location, p.languages, p.availability_hours 
            FROM agents a 
            LEFT JOIN agent_profiles p ON a.id = p.agent_id 
            WHERE a.id = :aid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['aid' => $agentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function deleteAgent($pdo, $agentId) {
    // Supprime toutes les références liées à l'agent
    $tables = ['agent_profiles', 'agent_skills', 'mission_collaborators', 'tasks', 'forum_posts', 'chat_messages', 'notifications'];
    foreach ($tables as $table) {
        $pdo->prepare("DELETE FROM $table WHERE " . ($table === 'tasks' ? 'assigned_to' : ($table === 'notifications' ? 'user_id' : 'agent_id')) . " = :aid")
            ->execute(['aid' => $agentId]);
    }
    // Supprime l'agent lui-même
    $pdo->prepare("DELETE FROM agents WHERE id = :aid")->execute(['aid' => $agentId]);
}

// Traitement des requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken($_POST['csrf_token'] ?? '');
    
    if (isset($_POST['confirm_delete']) && $action === 'delete_agent') {
        deleteAgent($pdo, $agentId);
        header("Location: admin_management.php?action=agents&deleted=success");
        exit;
    } elseif ($action === 'edit_agent') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'agent');
        $location = trim($_POST['location'] ?? '');
        $languages = trim($_POST['languages'] ?? '');
        $availability_hours = (int)($_POST['availability_hours'] ?? 0);

        if (strlen($username) < 3) $errors[] = "Le nom d'utilisateur doit comporter au moins 3 caractères.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "L'email est invalide.";
        if (!in_array($role, ['agent', 'admin'])) $errors[] = "Le rôle doit être 'agent' ou 'admin'.";

        if (empty($errors)) {
            // Met à jour la table agents
            $pdo->prepare("UPDATE agents SET username = :un, email = :em, role = :rl WHERE id = :aid")
                ->execute(['un' => $username, 'em' => $email, 'rl' => $role, 'aid' => $agentId]);

            // Met à jour ou insère le profil associé
            $pdo->prepare("INSERT INTO agent_profiles (agent_id, location, languages, availability_hours) 
                           VALUES (:aid, :loc, :lang, :ah) 
                           ON DUPLICATE KEY UPDATE location = :loc, languages = :lang, availability_hours = :ah")
                ->execute([
                    'aid' => $agentId, 
                    'loc' => $location, 
                    'lang' => $languages, 
                    'ah' => $availability_hours
                ]);

            header("Location: admin_management.php?action=agents&updated=success");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/style.css">
    <title>Gestion Admin - CAF 47</title>
</head>
<body>
    <?php include 'assets/includes/particles-background.php'; ?> <!-- Inclusion des particules juste après <body> -->
    <?php include 'assets/includes/header.php'; ?> <!-- Inclusion du header après les particules -->

    <div class="container mt-5">
        <?php if ($action === 'dashboard'): ?>
            <h1 class="mb-4">Tableau de bord Administrateur</h1>
            <div class="row">
                <?php
                // Statistiques générales
                $totalAgents = $pdo->query("SELECT COUNT(*) FROM agents")->fetchColumn();
                $totalMissions = $pdo->query("SELECT COUNT(*) FROM missions")->fetchColumn();
                $totalTasks = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
                $recentNotifs = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="col-md-4">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <h5>Agents</h5>
                            <p class="display-6"><?= $totalAgents ?></p>
                            <a href="?action=agents" class="btn btn-outline-primary btn-sm">Gérer</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <h5>Missions</h5>
                            <p class="display-6"><?= $totalMissions ?></p>
                            <a href="?action=missions" class="btn btn-outline-primary btn-sm">Gérer</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <h5>Tâches</h5>
                            <p class="display-6"><?= $totalTasks ?></p>
                            <a href="?action=tasks" class="btn btn-outline-primary btn-sm">Gérer</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">Dernières Notifications</h4>
                </div>
                <div class="card-body">
                    <?php if ($recentNotifs): ?>
                        <ul class="list-group">
                            <?php foreach ($recentNotifs as $n): ?>
                                <li class="list-group-item">
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

        <?php elseif ($action === 'agents'): ?>
            <h1 class="mb-4">Gestion des Agents</h1>
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">Agent mis à jour avec succès !</div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-warning">Agent supprimé avec succès !</div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">Liste des agents</h2>
                    <a href="auth.php?action=register" class="btn btn-light btn-sm">Ajouter un agent</a>
                </div>
                <div class="card-body">
                    <?php
                    $agents = $pdo->query("
                        SELECT a.id, a.username, a.email, a.role, p.location, p.availability_hours 
                        FROM agents a 
                        LEFT JOIN agent_profiles p ON a.id = p.agent_id 
                        ORDER BY a.username
                    ")->fetchAll(PDO::FETCH_ASSOC);

                    if ($agents): ?>
                        <table class="table table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>ID</th>
                                    <th>Nom d'utilisateur</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <th>Localisation</th>
                                    <th>Heures dispo.</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agents as $agent): ?>
                                    <tr>
                                        <td><?= $agent['id'] ?></td>
                                        <td><?= htmlspecialchars($agent['username']) ?></td>
                                        <td><?= htmlspecialchars($agent['email']) ?></td>
                                        <td><?= htmlspecialchars($agent['role']) ?></td>
                                        <td><?= htmlspecialchars($agent['location'] ?? 'Non défini') ?></td>
                                        <td><?= htmlspecialchars($agent['availability_hours'] ?? 'N/A') ?></td>
                                        <td>
                                            <a href="user_dashboard.php?action=view_profile&id=<?= $agent['id'] ?>" class="btn btn-sm btn-outline-info">Voir</a>
                                            <a href="?action=edit_agent&id=<?= $agent['id'] ?>" class="btn btn-sm btn-outline-primary">Modifier</a>
                                            <a href="?action=delete_agent&id=<?= $agent['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cet agent ?');">Supprimer</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">Aucun agent enregistré.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-3">
                <a href="?action=dashboard" class="btn btn-outline-secondary">Retour au tableau de bord</a>
            </div>

        <?php elseif ($action === 'edit_agent' && $agentId): ?>
            <?php $agent = fetchAgent($pdo, $agentId) ?: die('<p class="text-danger">Agent introuvable.</p>'); ?>
            <h1 class="mb-4">Modifier l'Agent : <?= htmlspecialchars($agent['username']) ?></h1>
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $err): ?>
                        <p><?= htmlspecialchars($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Détails de l'agent</h4>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                        <div class="mb-3">
                            <label class="form-label">Nom d'utilisateur :</label>
                            <input type="text" class="form-control" name="username" required value="<?= htmlspecialchars($agent['username']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email :</label>
                            <input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($agent['email']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rôle :</label>
                            <select class="form-select" name="role">
                                <option value="agent" <?= $agent['role'] === 'agent' ? 'selected' : '' ?>>Agent</option>
                                <option value="admin" <?= $agent['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Localisation :</label>
                            <input type="text" class="form-control" name="location" value="<?= htmlspecialchars($agent['location'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Langues :</label>
                            <input type="text" class="form-control" name="languages" value="<?= htmlspecialchars($agent['languages'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Heures disponibles :</label>
                            <input type="number" class="form-control" name="availability_hours" min="0" value="<?= htmlspecialchars($agent['availability_hours'] ?? 0) ?>">
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                            <a href="?action=agents" class="btn btn-outline-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($action === 'delete_agent' && $agentId): ?>
            <?php $agent = fetchAgent($pdo, $agentId) ?: die('<p class="text-danger">Agent introuvable.</p>'); ?>
            <h1 class="mb-4">Supprimer l'Agent : <?= htmlspecialchars($agent['username']) ?></h1>
            <div class="alert alert-warning">
                <p>Êtes-vous sûr de vouloir supprimer cet agent ? Cette action est irréversible et supprimera toutes ses données associées.</p>
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                        <div class="d-flex gap-2">
                            <button type="submit" name="confirm_delete" class="btn btn-danger">Oui, supprimer</button>
                            <a href="?action=agents" class="btn btn-outline-secondary">Non, annuler</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($action === 'missions'): ?>
            <h1 class="mb-4">Gestion des Missions</h1>
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">Liste des missions</h2>
                    <a href="mission_management.php?action=create" class="btn btn-light btn-sm">Créer une mission</a>
                </div>
                <div class="card-body">
                    <?php
                    $missions = $pdo->query("
                        SELECT m.id, m.title, m.description, m.required_hours, m.is_remote, a.username AS creator_name
                        FROM missions m 
                        LEFT JOIN agents a ON m.created_by = a.id 
                        ORDER BY m.id DESC
                    ")->fetchAll(PDO::FETCH_ASSOC);

                    if ($missions): ?>
                        <table class="table table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>ID</th>
                                    <th>Titre</th>
                                    <th>Créateur</th>
                                    <th>Heures/sem.</th>
                                    <th>Remote</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($missions as $mission): ?>
                                    <tr>
                                        <td><?= $mission['id'] ?></td>
                                        <td><?= htmlspecialchars($mission['title']) ?></td>
                                        <td><?= htmlspecialchars($mission['creator_name']) ?></td>
                                        <td><?= (int)$mission['required_hours'] ?></td>
                                        <td><?= $mission['is_remote'] ? 'Oui' : 'Non' ?></td>
                                        <td>
                                            <a href="mission_management.php?action=view&id=<?= $mission['id'] ?>" class="btn btn-sm btn-outline-info">Voir</a>
                                            <a href="mission_management.php?action=edit&id=<?= $mission['id'] ?>" class="btn btn-sm btn-outline-primary">Modifier</a>
                                            <a href="mission_management.php?action=delete&id=<?= $mission['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cette mission ?');">Supprimer</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">Aucune mission enregistrée.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-3">
                <a href="?action=dashboard" class="btn btn-outline-secondary">Retour au tableau de bord</a>
            </div>

        <?php elseif ($action === 'tasks'): ?>
            <h1 class="mb-4">Gestion des Tâches</h1>
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0">Liste des tâches</h2>
                </div>
                <div class="card-body">
                    <?php
                    $tasks = $pdo->query("
                        SELECT t.id, t.title, t.status, m.title AS mission_title, a.username AS assigned_name
                        FROM tasks t
                        LEFT JOIN missions m ON t.mission_id = m.id
                        LEFT JOIN agents a ON t.assigned_to = a.id
                        ORDER BY t.id DESC
                    ")->fetchAll(PDO::FETCH_ASSOC);

                    if ($tasks): ?>
                        <table class="table table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>ID</th>
                                    <th>Titre</th>
                                    <th>Mission</th>
                                    <th>Statut</th>
                                    <th>Assigné à</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                    <tr>
                                        <td><?= $task['id'] ?></td>
                                        <td><?= htmlspecialchars($task['title']) ?></td>
                                        <td><?= htmlspecialchars($task['mission_title']) ?></td>
                                        <td>
                                            <span class="badge 
                                                <?= $task['status'] === 'done' ? 'bg-success' : (
                                                       $task['status'] === 'in_progress' ? 'bg-primary' : 'bg-warning'
                                                   ) 
                                                ?>">
                                                <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($task['assigned_name'] ?? 'Non assigné') ?></td>
                                        <td>
                                            <a href="task_management.php?action=edit&task_id=<?= $task['id'] ?>" class="btn btn-sm btn-outline-primary">Modifier</a>
                                            <a href="task_management.php?action=delete&task_id=<?= $task['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cette tâche ?');">Supprimer</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">Aucune tâche enregistrée.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-3">
                <a href="?action=dashboard" class="btn btn-outline-secondary">Retour au tableau de bord</a>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'assets/includes/footer.php'; ?>
</body>
</html>