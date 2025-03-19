<?php
session_start();
require_once 'assets/includes/db.php';
require_once 'assets/includes/csrf.php';

// Détermine l'action en fonction de la query string
$action = $_GET['action'] ?? 'login';
$errors = [];

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken($_POST['csrf_token'] ?? '');

    if ($action === 'login') {
        // Connexion
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM agents WHERE email = :em LIMIT 1");
        $stmt->execute(['em' => $email]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($agent && password_verify($password, $agent['password'])) {
            $_SESSION['user_id'] = $agent['id'];
            $_SESSION['username'] = $agent['username'];
            $_SESSION['role'] = $agent['role']; // 'agent' ou 'admin'
            header("Location: index.php");
            exit;
        } else {
            $errors[] = "Email ou mot de passe incorrect.";
        }

    } elseif ($action === 'register') {
        // Inscription
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass1 = $_POST['password'] ?? '';
        $hash = password_hash($pass1, PASSWORD_BCRYPT);
        $service = trim($_POST['service'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $languages = trim($_POST['languages'] ?? '');
        $availability = (int)($_POST['availability_hours'] ?? 0);
        $experience = trim($_POST['experience'] ?? '');

        // Validations
        if (strlen($username) < 3) {
            $errors[] = "Le nom d'utilisateur doit faire au moins 3 caractères.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email invalide.";
        }
        if (strlen($pass1) < 6) {
            $errors[] = "Mot de passe trop court (6 caractères min).";
        }
        if (empty($service)) {
            $errors[] = "Le service est requis.";
        }

        // Si aucune erreur, on insère en BDD
        if (empty($errors)) {
            // Le rôle par défaut est 'agent'
            $stmt = $pdo->prepare("
                INSERT INTO agents (username, email, password, service, role) 
                VALUES (:un, :em, :pw, :sv, 'agent')
            ");
            $stmt->execute([
                'un' => $username,
                'em' => $email,
                'pw' => $hash,
                'sv' => $service
            ]);
            $agentId = $pdo->lastInsertId();

            // On insère également le profil de l'agent
            $stmt2 = $pdo->prepare("
                INSERT INTO agent_profiles (agent_id, location, languages, availability_hours, experience) 
                VALUES (:aid, :loc, :lang, :avh, :exp)
            ");
            $stmt2->execute([
                'aid' => $agentId,
                'loc' => $location,
                'lang' => $languages,
                'avh' => $availability,
                'exp' => $experience
            ]);

            // On connecte directement l'agent
            $_SESSION['user_id'] = $agentId;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'agent';
            header("Location: index.php");
            exit;
        }
    }
    
} elseif ($action === 'logout') {
    // Déconnexion
    session_unset();
    session_destroy();
    header("Location: index.php?logout=success");
    exit;
}

// Inclusion du header après le traitement pour éviter les conflits de redirection
include 'assets/includes/header.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $action === 'login' ? 'Connexion' : 'Inscription' ?> - CAF 47 Collab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'assets/includes/particles-background.php'; ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <?php if ($action === 'login'): ?>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h2 class="mb-0">Connexion</h2>
                        </div>
                        <div class="card-body">
                            <?php if ($errors): ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($errors as $err): ?>
                                        <p><?= htmlspecialchars($err) ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <form method="post" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email :</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Mot de passe :</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Se connecter</button>
                                    <a href="?action=register" class="btn btn-outline-secondary">
                                        Pas de compte ? S'inscrire
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php elseif ($action === 'register'): ?>
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h2 class="mb-0">Inscription Agent</h2>
                        </div>
                        <div class="card-body">
                            <?php if ($errors): ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($errors as $err): ?>
                                        <p><?= htmlspecialchars($err) ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <form method="post" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="username" class="form-label">Nom d'utilisateur :</label>
                                        <input type="text" class="form-control" id="username" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email :</label>
                                        <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Mot de passe :</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="service" class="form-label">Service :</label>
                                        <input type="text" class="form-control" id="service" name="service" placeholder="Ex: Pôle RSA" required value="<?= htmlspecialchars($_POST['service'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="location" class="form-label">Localisation :</label>
                                        <input type="text" class="form-control" id="location" name="location" placeholder="Ex: Agen" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="languages" class="form-label">Langues (ex: fr, en) :</label>
                                    <input type="text" class="form-control" id="languages" name="languages" placeholder="fr, en" value="<?= htmlspecialchars($_POST['languages'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="availability_hours" class="form-label">Disponibilité (heures/semaine) :</label>
                                    <input type="number" class="form-control" id="availability_hours" name="availability_hours" min="0" value="<?= htmlspecialchars($_POST['availability_hours'] ?? '0') ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="experience" class="form-label">Expérience / Observations :</label>
                                    <textarea class="form-control" id="experience" name="experience" rows="4"><?= htmlspecialchars($_POST['experience'] ?? '') ?></textarea>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success">S'inscrire</button>
                                    <a href="?action=login" class="btn btn-outline-secondary">
                                        Déjà inscrit ? Se connecter
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'assets/includes/footer.php'; ?>
</body>
</html>
