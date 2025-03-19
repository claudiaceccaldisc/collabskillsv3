<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Plateforme collaborative interne pour les agents de la CAF 47">
    <title>CAF 47 Collab</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container-fluid">
            <!-- Marque -->
            <a class="navbar-brand fw-bold" href="/index.php">
                <span class="d-inline-block align-text-top">CAF 47 Collab</span>
            </a>

            <!-- Bouton pour mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#navbarContent" aria-controls="navbarContent" 
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Contenu de la navbar -->
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <!-- Liens pour utilisateurs connectés -->
                        <li class="nav-item">
                            <a class="nav-link" href="/user_dashboard.php">
                                <i class="bi bi-person me-1"></i> Mon Profil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/mission_management.php?action=create">
                                <i class="bi bi-plus-circle me-1"></i> Créer Mission
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/communication_hub.php?section=channels">
                                <i class="bi bi-chat-square-text me-1"></i> Forum
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/communication_hub.php?section=chat">
                                <i class="bi bi-chat-dots me-1"></i> Chat
                            </a>
                        </li>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <!-- Lien Admin -->
                            <li class="nav-item">
                                <a class="nav-link admin-link" href="/admin_management.php?action=dashboard">
                                    <i class="bi bi-gear-fill me-1"></i> Gestion Admin
                                </a>
                            </li>
                        <?php endif; ?>
                        <!-- Déconnexion -->
                        <li class="nav-item">
                            <a class="nav-link text-danger fw-bold" href="/auth.php?action=logout">
                                <i class="bi bi-box-arrow-right me-1"></i> Déconnexion
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- Liens pour utilisateurs non connectés -->
                        <li class="nav-item">
                            <a class="nav-link" href="/auth.php?action=login">
                                <i class="bi bi-box-arrow-in-left me-1"></i> Connexion
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/auth.php?action=register">
                                <i class="bi bi-person-plus me-1"></i> Inscription
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Conteneur principal -->
    <main class="container my-4">