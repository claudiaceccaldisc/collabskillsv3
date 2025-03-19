<?php
session_start();
require_once 'assets/includes/session_check.php';
require_once 'assets/includes/db.php';
require_once 'assets/includes/csrf.php';
include 'assets/includes/header.php';

$action = $_GET['action'] ?? 'list';
$missionId = isset($_GET['mission_id']) ? (int)$_GET['mission_id'] : 0;
$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
$errors = [];
$allowedStatuses = ['todo', 'in_progress', 'done'];

// Fonctions utilitaires
function fetchMission($pdo, $missionId) {
    $stmt = $pdo->prepare("SELECT * FROM missions WHERE id = :mid");
    $stmt->execute(['mid' => $missionId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function fetchTask($pdo, $taskId) {
    $stmt = $pdo->prepare("SELECT t.*, m.id AS mission_id, m.title AS mission_title 
                           FROM tasks t JOIN missions m ON t.mission_id = m.id 
                           WHERE t.id = :tid");
    $stmt->execute(['tid' => $taskId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function fetchCollaborators($pdo, $missionId) {
    $stmt = $pdo->prepare("SELECT a.id, a.username 
                           FROM mission_collaborators mc JOIN agents a ON mc.agent_id = a.id 
                           WHERE mc.mission_id = :mid");
    $stmt->execute(['mid' => $missionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Vérification de la session
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo '<div class="container mt-5"><p class="text-danger">Non autorisé</p></div>';
    include 'assets/includes/footer.php';
    exit;
}

// Mise à jour manuelle du statut via formulaire
if ($action === 'update_status_manual' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'assets/includes/csrf.php';
    checkCsrfToken($_POST['csrf_token'] ?? '');
    $taskIdManual = (int)($_POST['task_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? 'todo';
    if (!in_array($newStatus, $allowedStatuses) || $taskIdManual <= 0) {
        $errors[] = "Erreur : Statut ou ID invalide.";
    } else {
        $stmt = $pdo->prepare("UPDATE tasks SET status = :st WHERE id = :tid");
        $stmt->execute(['st' => $newStatus, 'tid' => $taskIdManual]);
        header("Location: task_management.php?action=list&mission_id=" . $_POST['mission_id']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'assets/includes/csrf.php';
    checkCsrfToken($_POST['csrf_token'] ?? '');
    if ($action === 'create' && $missionId) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        if (strlen($title) < 3) {
            $errors[] = "Le titre doit comporter au moins 3 caractères.";
        }
        if (empty($description)) {
            $errors[] = "La description est requise.";
        }
        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO tasks (mission_id, title, description, assigned_to, status) 
                                   VALUES (:mid, :t, :d, :at, 'todo')");
            $stmt->execute([
                'mid' => $missionId,
                't'   => $title,
                'd'   => $description,
                'at'  => $assignedTo ?: null
            ]);
            header("Location: task_management.php?action=list&mission_id=$missionId&created=success");
            exit;
        }
    } elseif ($action === 'edit' && $taskId) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        if (strlen($title) < 3) {
            $errors[] = "Le titre doit comporter au moins 3 caractères.";
        }
        if (empty($description)) {
            $errors[] = "La description est requise.";
        }
        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE tasks SET title = :t, description = :d, assigned_to = :at WHERE id = :tid");
            $stmt->execute([
                't'   => $title,
                'd'   => $description,
                'at'  => $assignedTo ?: null,
                'tid' => $taskId
            ]);
            $task = fetchTask($pdo, $taskId);
            header("Location: task_management.php?action=list&mission_id={$task['mission_id']}&updated=success");
            exit;
        }
    } elseif ($action === 'delete' && $taskId && isset($_POST['confirm_delete'])) {
        $task = fetchTask($pdo, $taskId);
        if ($task) {
            $pdo->prepare("DELETE FROM tasks WHERE id = :tid")->execute(['tid' => $taskId]);
            header("Location: task_management.php?action=list&mission_id={$task['mission_id']}&deleted=success");
            exit;
        }
    }
}

if ($action === 'list' && $missionId) {
    $stmt = $pdo->prepare("SELECT t.*, a.username AS assigned_name 
                           FROM tasks t LEFT JOIN agents a ON t.assigned_to = a.id 
                           WHERE t.mission_id = :mid ORDER BY t.created_at DESC");
    $stmt->execute(['mid' => $missionId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $todo = array_filter($tasks, fn($t) => $t['status'] === 'todo');
    $inProgress = array_filter($tasks, fn($t) => $t['status'] === 'in_progress');
    $done = array_filter($tasks, fn($t) => $t['status'] === 'done');
} elseif ($action === 'create' && $missionId) {
    $mission = fetchMission($pdo, $missionId);
    $collaborators = fetchCollaborators($pdo, $missionId);
    if (!$mission) {
        echo '<div class="container mt-5"><p class="text-danger">Mission introuvable.</p></div>';
        include 'assets/includes/footer.php';
        exit;
    }
} elseif (($action === 'edit' || $action === 'delete') && $taskId) {
    $task = fetchTask($pdo, $taskId);
    if (!$task) {
        echo '<div class="container mt-5"><p class="text-danger">Tâche introuvable.</p></div>';
        include 'assets/includes/footer.php';
        exit;
    }
    if ($action === 'edit') {
        $collaborators = fetchCollaborators($pdo, $task['mission_id']);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/style.css">
    <title>Gestion des Tâches - CAF 47</title>
</head>
<body>
    <?php include 'assets/includes/particles-background.php'; ?> <!-- Inclusion des particules juste après <body> -->
    <?php include 'assets/includes/header.php'; ?> <!-- Header après les particules -->

    <div class="container mt-5">
        <?php if ($action === 'list' && $missionId): ?>
            <h1 class="mb-4">Tâches (Kanban) - Mission ID: <?= $missionId ?></h1>
            <?php if (isset($_GET['created'])): ?>
                <div class="alert alert-success">Tâche créée avec succès !</div>
            <?php elseif (isset($_GET['updated'])): ?>
                <div class="alert alert-info">Tâche mise à jour avec succès !</div>
            <?php elseif (isset($_GET['deleted'])): ?>
                <div class="alert alert-warning">Tâche supprimée avec succès !</div>
            <?php endif; ?>
            <div class="mb-3">
                <a href="?action=create&mission_id=<?= $missionId ?>" class="btn btn-success">Créer une tâche</a>
            </div>
            <div class="row">
                <?php
                function renderTaskColumn($tasks, $columnName, $columnLabel, $allowedStatuses) {
                    ?>
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-<?php echo ($columnName === 'todo' ? 'warning' : ($columnName === 'in_progress' ? 'primary' : 'success')); ?> text-white">
                                <h3 class="mb-0"><?= $columnLabel ?></h3>
                            </div>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($tasks as $t): ?>
                                    <li class="list-group-item">
                                        <strong><?= htmlspecialchars($t['title']) ?></strong>
                                        <p class="mb-1"><?= htmlspecialchars(substr($t['description'], 0, 50)) . (strlen($t['description']) > 50 ? '...' : '') ?></p>
                                        <small class="text-muted">Assigné à : <?= htmlspecialchars($t['assigned_name'] ?? 'Personne') ?></small>
                                        <div class="mt-2">
                                            <a href="?action=edit&task_id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary">Modifier</a>
                                            <a href="?action=delete&task_id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cette tâche ?');">Supprimer</a>
                                        </div>
                                        <div class="mt-2">
                                            <form method="post" class="d-flex">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                                <input type="hidden" name="mission_id" value="<?= isset($missionId) ? $missionId : 0 ?>">
                                                <select name="new_status" class="form-select form-select-sm me-2" required>
                                                    <?php foreach ($allowedStatuses as $status): ?>
                                                        <option value="<?= $status ?>" <?= $t['status'] === $status ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $status)) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" formaction="task_management.php?action=update_status_manual" class="btn btn-sm btn-secondary">Mettre à jour</button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php
                }
                ?>
                <?php renderTaskColumn($todo, 'todo', 'À faire', $allowedStatuses); ?>
                <?php renderTaskColumn($inProgress, 'in_progress', 'En cours', $allowedStatuses); ?>
                <?php renderTaskColumn($done, 'done', 'Terminé', $allowedStatuses); ?>
            </div>
            <div class="mt-3">
                <a href="mission_management.php?action=view&id=<?= $missionId ?>" class="btn btn-outline-secondary">Retour à la mission</a>
            </div>
        <?php elseif ($action === 'create' && isset($mission)): ?>
            <h1 class="mb-4">Créer une Tâche pour la Mission : <?= htmlspecialchars($mission['title']) ?></h1>
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $err): ?>
                        <p><?= htmlspecialchars($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Détails de la tâche</h4>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                        <div class="mb-3">
                            <label for="title" class="form-label">Titre :</label>
                            <input type="text" class="form-control" id="title" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description :</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="assigned_to" class="form-label">Assigner à :</label>
                            <select class="form-select" id="assigned_to" name="assigned_to">
                                <option value="0">Non assigné</option>
                                <?php foreach ($collaborators as $col): ?>
                                    <option value="<?= $col['id'] ?>" <?= (($_POST['assigned_to'] ?? '') == $col['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($col['username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Créer la tâche</button>
                            <a href="?action=list&mission_id=<?= $missionId ?>" class="btn btn-outline-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif ($action === 'edit' && isset($task)): ?>
            <h1 class="mb-4">Modifier la Tâche : <?= htmlspecialchars($task['title']) ?></h1>
            <p class="text-muted">Mission : <?= htmlspecialchars($task['mission_title']) ?></p>
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $err): ?>
                        <p><?= htmlspecialchars($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Détails de la tâche</h4>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                        <div class="mb-3">
                            <label for="title" class="form-label">Titre :</label>
                            <input type="text" class="form-control" id="title" name="title" required value="<?= htmlspecialchars($task['title']) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description :</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?= htmlspecialchars($task['description']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="assigned_to" class="form-label">Assigner à :</label>
                            <select class="form-select" id="assigned_to" name="assigned_to">
                                <option value="0">Non assigné</option>
                                <?php foreach ($collaborators as $col): ?>
                                    <option value="<?= $col['id'] ?>" <?= $task['assigned_to'] == $col['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($col['username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                            <a href="?action=list&mission_id=<?= $task['mission_id'] ?>" class="btn btn-outline-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif ($action === 'delete' && isset($task)): ?>
            <h1 class="mb-4">Supprimer la Tâche : <?= htmlspecialchars($task['title']) ?></h1>
            <div class="alert alert-warning">
                <p>Êtes-vous sûr de vouloir supprimer cette tâche ? Cette action est irréversible.</p>
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                        <div class="d-flex gap-2">
                            <button type="submit" name="confirm_delete" class="btn btn-danger">Oui, supprimer</button>
                            <a href="?action=list&mission_id=<?= $task['mission_id'] ?>" class="btn btn-outline-secondary">Non, annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'assets/includes/footer.php'; ?>
</body>
</html>