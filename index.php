<?php
require_once 'assets/includes/session_check.php';
require_once 'assets/includes/db.php';
include 'assets/includes/header.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAF 47 Collab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'assets/includes/particles-background.php'; ?>
    <div class="container mt-5">
        <h1 class="mb-4">Bienvenue sur CAF 47 Collab</h1>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Mission supprimée avec succès !</div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <p class="lead">Plateforme collaborative interne pour les agents de la CAF 47.</p>
                <p>Utilisez cette application pour gérer vos missions, collaborer avec vos collègues, et échanger via le chat ou le forum.</p>
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <a href="user_dashboard.php" class="btn btn-primary">Accéder au tableau de bord</a>
                <?php else: ?>
                    <a href="auth.php?action=login" class="btn btn-primary">Se connecter</a>
                    <a href="auth.php?action=register" class="btn btn-outline-primary">S’inscrire</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'assets/includes/footer.php'; ?>
</body>
</html>
