<?php
require_once 'assets/includes/session_check.php';
require_once 'assets/includes/db.php';
require_once 'assets/includes/csrf.php';
include 'assets/includes/header.php';
include 'assets/includes/particles-background.php';

$action = $_GET['action'] ?? 'view';
$missionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

// Fonction utilitaire : récupérer la mission
function fetchMission($pdo, $missionId, $userId, $restrictToCreator = false) {
    $sql = "SELECT m.*, a.username AS creator_name 
            FROM missions m 
            LEFT JOIN agents a ON m.created_by = a.id 
            WHERE m.id = :mid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['mid' => $missionId]);
    $mission = $stmt->fetch(PDO::FETCH_ASSOC);
    return $mission && (!$restrictToCreator || $mission['created_by'] == $userId) ? $mission : null;
}

// Fonction pour traiter les compétences requises à partir du formulaire
function processRequiredSkills($pdo, $missionId) {
    // Supprimer les compétences existantes pour la mission
    $pdo->prepare("DELETE FROM mission_required_skills WHERE mission_id = :mid")->execute(['mid' => $missionId]);
    if(isset($_POST['required_skills']) && is_array($_POST['required_skills'])){
        foreach($_POST['required_skills'] as $skillEntry) {
            $skillName = trim($skillEntry['skill_name'] ?? '');
            $minLevel = (int)($skillEntry['min_level'] ?? 1);
            if($skillName !== '') {
                // Vérifier si la compétence existe déjà dans la table skills
                $chk = $pdo->prepare("SELECT id FROM skills WHERE LOWER(skill_name) = LOWER(:sn) LIMIT 1");
                $chk->execute(['sn' => $skillName]);
                $skillId = $chk->fetchColumn();
                if(!$skillId) {
                    // Sinon, l'insérer
                    $ins = $pdo->prepare("INSERT INTO skills (skill_name) VALUES (:sn)");
                    $ins->execute(['sn' => $skillName]);
                    $skillId = $pdo->lastInsertId();
                }
                // Insérer dans mission_required_skills
                $ins2 = $pdo->prepare("INSERT INTO mission_required_skills (mission_id, skill_id, min_level) VALUES (:mid, :sid, :ml)");
                $ins2->execute(['mid' => $missionId, 'sid' => $skillId, 'ml' => $minLevel]);
            }
        }
    }
}

// Traitement des requêtes POST
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    checkCsrfToken($_POST['csrf_token'] ?? '');
    if($action === 'create' || $action === 'edit'){
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $reqHours = (int)($_POST['required_hours'] ?? 0);
        $lang = trim($_POST['required_main_language'] ?? 'fr');
        $isRemote = isset($_POST['is_remote']) ? 1 : 0;
        
        if(strlen($title) < 3){
            $errors[] = "Le titre doit comporter au moins 3 caractères.";
        }
        if(empty($description)){
            $errors[] = "La description est requise.";
        }
        
        if(empty($errors)){
            if($action === 'create'){
                $stmt = $pdo->prepare("INSERT INTO missions (created_by, title, description, required_hours, required_main_language, is_remote) 
                                       VALUES (:cb, :t, :d, :rh, :rl, :ir)");
                $stmt->execute([
                    'cb' => $_SESSION['user_id'],
                    't'  => $title,
                    'd'  => $description,
                    'rh' => $reqHours,
                    'rl' => $lang,
                    'ir' => $isRemote
                ]);
                $missionId = $pdo->lastInsertId();
                processRequiredSkills($pdo, $missionId);
                header("Location: mission_management.php?action=view&id=$missionId&created=success");
                exit;
            } else { // action 'edit'
                $stmt = $pdo->prepare("UPDATE missions 
                                       SET title = :t, description = :d, required_hours = :rh, required_main_language = :rl, is_remote = :ir 
                                       WHERE id = :mid AND created_by = :uid");
                $stmt->execute([
                    't'   => $title,
                    'd'   => $description,
                    'rh'  => $reqHours,
                    'rl'  => $lang,
                    'ir'  => $isRemote,
                    'mid' => $missionId,
                    'uid' => $_SESSION['user_id']
                ]);
                processRequiredSkills($pdo, $missionId);
                header("Location: mission_management.php?action=view&id=$missionId&updated=success");
                exit;
            }
        }
    } elseif($action === 'delete' && isset($_POST['confirm_delete'])){
        $pdo->prepare("DELETE FROM tasks WHERE mission_id = :mid")->execute(['mid' => $missionId]);
        $pdo->prepare("DELETE FROM mission_collaborators WHERE mission_id = :mid")->execute(['mid' => $missionId]);
        $pdo->prepare("DELETE FROM mission_required_skills WHERE mission_id = :mid")->execute(['mid' => $missionId]);
        $pdo->prepare("DELETE FROM missions WHERE id = :mid AND created_by = :uid")
            ->execute(['mid' => $missionId, 'uid' => $_SESSION['user_id']]);
        header("Location: index.php?deleted=success");
        exit;
    }
}

// Pour les vues 'view', 'edit' et 'delete'
if(in_array($action, ['view','edit','delete']) && $missionId){
    $restrictToCreator = in_array($action, ['edit','delete']);
    $mission = fetchMission($pdo, $missionId, $_SESSION['user_id'], $restrictToCreator);
    if(!$mission){
        echo '<div class="container mt-5"><p class="text-danger">Mission introuvable ou accès refusé.</p></div>';
        include 'assets/includes/footer.php';
        exit;
    }
    if($action === 'view'){
        $reqSkillsStmt = $pdo->prepare("SELECT s.skill_name, mrs.min_level 
                                        FROM mission_required_skills mrs 
                                        JOIN skills s ON mrs.skill_id = s.id 
                                        WHERE mrs.mission_id = :mid");
        $reqSkillsStmt->execute(['mid' => $missionId]);
        $reqSkills = $reqSkillsStmt->fetchAll(PDO::FETCH_ASSOC);
        $collabsStmt = $pdo->prepare("SELECT a.username 
                                      FROM mission_collaborators mc 
                                      JOIN agents a ON mc.agent_id = a.id 
                                      WHERE mc.mission_id = :mid");
        $collabsStmt->execute(['mid' => $missionId]);
        $collabs = $collabsStmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>

<div class="container mt-5">
    <?php if($action === 'create'): ?>
        <h1 class="mb-4">Créer une nouvelle Mission</h1>
        <?php if($errors): ?>
            <div class="alert alert-danger">
                <?php foreach($errors as $err): ?>
                    <p><?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Détails de la mission</h4>
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
                        <label for="required_hours" class="form-label">Heures requises (par semaine) :</label>
                        <input type="number" class="form-control" id="required_hours" name="required_hours" value="<?= htmlspecialchars($_POST['required_hours'] ?? '0') ?>" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="required_main_language" class="form-label">Langue principale :</label>
                        <input type="text" class="form-control" id="required_main_language" name="required_main_language" value="<?= htmlspecialchars($_POST['required_main_language'] ?? 'fr') ?>">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_remote" name="is_remote" <?= isset($_POST['is_remote']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_remote">Remote possible</label>
                    </div>
                    <hr>
                    <h4>Compétences requises</h4>
                    <div id="required-skills-container">
                        <?php
                        $reqSkillsInput = $_POST['required_skills'] ?? [['skill_name'=>'', 'min_level'=>1]];
                        foreach($reqSkillsInput as $index => $skillEntry):
                        ?>
                        <div class="row mb-2 skill-row">
                            <div class="col-md-8">
                                <input type="text" name="required_skills[<?= $index ?>][skill_name]" class="form-control" placeholder="Nom de la compétence" value="<?= htmlspecialchars($skillEntry['skill_name']) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="required_skills[<?= $index ?>][min_level]" class="form-control" placeholder="Niveau min" value="<?= htmlspecialchars($skillEntry['min_level']) ?>" min="1" max="5" required>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm remove-skill">X</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" id="add-skill">Ajouter une compétence</button>
                    <hr>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Créer la mission</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            document.getElementById('add-skill').addEventListener('click', function(){
                var container = document.getElementById('required-skills-container');
                var index = container.getElementsByClassName('skill-row').length;
                var div = document.createElement('div');
                div.className = 'row mb-2 skill-row';
                div.innerHTML = '<div class="col-md-8">'+
                                '<input type="text" name="required_skills['+index+'][skill_name]" class="form-control" placeholder="Nom de la compétence" required>'+
                                '</div>'+
                                '<div class="col-md-3">'+
                                '<input type="number" name="required_skills['+index+'][min_level]" class="form-control" placeholder="Niveau min" value="1" min="1" max="5" required>'+
                                '</div>'+
                                '<div class="col-md-1">'+
                                '<button type="button" class="btn btn-danger btn-sm remove-skill">X</button>'+
                                '</div>';
                container.appendChild(div);
            });
            document.getElementById('required-skills-container').addEventListener('click', function(e){
                if(e.target && e.target.classList.contains('remove-skill')){
                    var row = e.target.closest('.skill-row');
                    row.parentNode.removeChild(row);
                }
            });
        </script>
    <?php elseif($action === 'edit' && $mission): ?>
        <h1 class="mb-4">Modifier la Mission : <?= htmlspecialchars($mission['title']) ?></h1>
        <?php if($errors): ?>
            <div class="alert alert-danger">
                <?php foreach($errors as $err): ?>
                    <p><?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php
        $reqSkillsStmt = $pdo->prepare("SELECT s.skill_name, mrs.min_level 
                                        FROM mission_required_skills mrs 
                                        JOIN skills s ON mrs.skill_id = s.id 
                                        WHERE mrs.mission_id = :mid");
        $reqSkillsStmt->execute(['mid' => $missionId]);
        $existingSkills = $reqSkillsStmt->fetchAll(PDO::FETCH_ASSOC);
        if(empty($existingSkills)){
            $existingSkills = [['skill_name'=>'', 'min_level'=>1]];
        }
        ?>
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Détails de la mission</h4>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                    <div class="mb-3">
                        <label for="title" class="form-label">Titre :</label>
                        <input type="text" class="form-control" id="title" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? $mission['title']) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description :</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required><?= htmlspecialchars($_POST['description'] ?? $mission['description']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="required_hours" class="form-label">Heures requises (par semaine) :</label>
                        <input type="number" class="form-control" id="required_hours" name="required_hours" value="<?= htmlspecialchars($_POST['required_hours'] ?? $mission['required_hours']) ?>" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="required_main_language" class="form-label">Langue principale :</label>
                        <input type="text" class="form-control" id="required_main_language" name="required_main_language" value="<?= htmlspecialchars($_POST['required_main_language'] ?? $mission['required_main_language']) ?>">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_remote" name="is_remote" <?= (isset($_POST['is_remote']) ? 'checked' : ($mission['is_remote'] ? 'checked' : '')) ?>>
                        <label class="form-check-label" for="is_remote">Remote possible</label>
                    </div>
                    <hr>
                    <h4>Compétences requises</h4>
                    <div id="required-skills-container">
                        <?php
                        $skillsInput = $_POST['required_skills'] ?? $existingSkills;
                        foreach($skillsInput as $index => $skillEntry):
                        ?>
                        <div class="row mb-2 skill-row">
                            <div class="col-md-8">
                                <input type="text" name="required_skills[<?= $index ?>][skill_name]" class="form-control" placeholder="Nom de la compétence" value="<?= htmlspecialchars($skillEntry['skill_name']) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="required_skills[<?= $index ?>][min_level]" class="form-control" placeholder="Niveau min" value="<?= htmlspecialchars($skillEntry['min_level']) ?>" min="1" max="5" required>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm remove-skill">X</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" id="add-skill">Ajouter une compétence</button>
                    <hr>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        <a href="?action=view&id=<?= $missionId ?>" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
        <script>
            document.getElementById('add-skill').addEventListener('click', function(){
                var container = document.getElementById('required-skills-container');
                var index = container.getElementsByClassName('skill-row').length;
                var div = document.createElement('div');
                div.className = 'row mb-2 skill-row';
                div.innerHTML = '<div class="col-md-8">'+
                                '<input type="text" name="required_skills['+index+'][skill_name]" class="form-control" placeholder="Nom de la compétence" required>'+
                                '</div>'+
                                '<div class="col-md-3">'+
                                '<input type="number" name="required_skills['+index+'][min_level]" class="form-control" placeholder="Niveau min" value="1" min="1" max="5" required>'+
                                '</div>'+
                                '<div class="col-md-1">'+
                                '<button type="button" class="btn btn-danger btn-sm remove-skill">X</button>'+
                                '</div>';
                container.appendChild(div);
            });
            document.getElementById('required-skills-container').addEventListener('click', function(e){
                if(e.target && e.target.classList.contains('remove-skill')){
                    var row = e.target.closest('.skill-row');
                    row.parentNode.removeChild(row);
                }
            });
        </script>
    <?php elseif($action === 'delete' && $mission): ?>
        <h1 class="mb-4">Supprimer la Mission : <?= htmlspecialchars($mission['title']) ?></h1>
        <div class="alert alert-warning">
            <p>Êtes-vous sûr de vouloir supprimer cette mission ? Cette action est irréversible et supprimera également toutes les tâches, collaborateurs et compétences requises associées.</p>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                    <div class="d-flex gap-2">
                        <button type="submit" name="confirm_delete" class="btn btn-danger">Oui, supprimer</button>
                        <a href="?action=view&id=<?= $missionId ?>" class="btn btn-outline-secondary">Non, annuler</a>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif($action === 'view' && $mission): ?>
        <h1 class="mb-4"><?= htmlspecialchars($mission['title']) ?></h1>
        <?php if(isset($_GET['created'])): ?>
            <div class="alert alert-success">Mission créée avec succès !</div>
        <?php elseif(isset($_GET['updated'])): ?>
            <div class="alert alert-success">Mission mise à jour avec succès !</div>
        <?php endif; ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <p class="lead"><?= nl2br(htmlspecialchars($mission['description'])) ?></p>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><strong>Créée par :</strong> <?= htmlspecialchars($mission['creator_name']) ?></li>
                    <li class="list-group-item"><strong>Heures requises :</strong> <?= (int)$mission['required_hours'] ?> / semaine</li>
                    <li class="list-group-item"><strong>Langue principale :</strong> <?= htmlspecialchars($mission['required_main_language']) ?></li>
                    <li class="list-group-item"><strong>Remote :</strong> <?= $mission['is_remote'] ? 'Oui' : 'Non' ?></li>
                </ul>
            </div>
        </div>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h4 class="mb-0">Compétences requises</h4>
            </div>
            <div class="card-body">
                <?php if($reqSkills): ?>
                    <ul class="list-group">
                        <?php foreach($reqSkills as $rs): ?>
                            <li class="list-group-item"><?= htmlspecialchars($rs['skill_name']) ?> (Niveau min. <?= (int)$rs['min_level'] ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">Aucune compétence requise définie.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0">Collaborateurs</h4>
            </div>
            <div class="card-body">
                <?php if($collabs): ?>
                    <ul class="list-group">
                        <?php foreach($collabs as $c): ?>
                            <li class="list-group-item"><?= htmlspecialchars($c) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">Aucun collaborateur pour l’instant.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="mt-4">
            <a href="suggested_collaborators_advanced.php?mission_id=<?= $missionId ?>" class="btn btn-warning">Trouver des collaborateurs</a>
            <?php if($_SESSION['user_id'] == $mission['created_by']): ?>
                <a href="?action=edit&id=<?= $missionId ?>" class="btn btn-outline-primary">Modifier</a>
                <a href="?action=delete&id=<?= $missionId ?>" class="btn btn-outline-danger" onclick="return confirm('Voulez-vous vraiment supprimer cette mission ?');">Supprimer</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'assets/includes/footer.php'; ?>
