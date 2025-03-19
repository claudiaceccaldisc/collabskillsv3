<?php
require_once 'assets/includes/session_check.php';
require_once 'assets/includes/db.php';
require_once 'assets/includes/csrf.php';
include 'assets/includes/header.php';

// Vérification de l'utilisateur connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo '<div class="container mt-5"><p class="text-danger">Non autorisé</p></div>';
    include 'assets/includes/footer.php';
    exit;
}

$section = $_GET['section'] ?? 'chat';
$channelId = isset($_GET['channel_id']) ? (int)$_GET['channel_id'] : 0;
$threadId = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;

// Gestion des actions d'édition/suppression pour chat et forum
if (isset($_GET['action'])) {
    $subAction = $_GET['action'];
    if ($subAction === 'edit_chat' && isset($_GET['msg_id'])) {
        $msg_id = (int)$_GET['msg_id'];
        $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE id = :id");
        $stmt->execute(['id' => $msg_id]);
        $messageData = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$messageData || $messageData['sender_id'] != $_SESSION['user_id']) {
            echo '<div class="container mt-5"><p class="text-danger">Message introuvable ou accès refusé.</p></div>';
            include 'assets/includes/footer.php';
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            checkCsrfToken($_POST['csrf_token'] ?? '');
            $newMessage = trim($_POST['message'] ?? '');
            if ($newMessage !== '') {
                $upd = $pdo->prepare("UPDATE chat_messages SET message = :msg WHERE id = :id");
                $upd->execute(['msg' => $newMessage, 'id' => $msg_id]);
                header("Location: communication_hub.php?section=chat");
                exit;
            }
        }
        ?>
        <div class="container mt-5">
            <h1>Modifier votre message</h1>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <div class="mb-3">
                    <textarea name="message" class="form-control" rows="3" required><?= htmlspecialchars($messageData['message']) ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="communication_hub.php?section=chat" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
        <?php
        include 'assets/includes/footer.php';
        exit;
    } elseif ($subAction === 'delete_chat' && isset($_GET['msg_id'])) {
        $msg_id = (int)$_GET['msg_id'];
        $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE id = :id");
        $stmt->execute(['id' => $msg_id]);
        $messageData = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$messageData || $messageData['sender_id'] != $_SESSION['user_id']) {
            echo '<div class="container mt-5"><p class="text-danger">Message introuvable ou accès refusé.</p></div>';
            include 'assets/includes/footer.php';
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            checkCsrfToken($_POST['csrf_token'] ?? '');
            $del = $pdo->prepare("DELETE FROM chat_messages WHERE id = :id");
            $del->execute(['id' => $msg_id]);
            header("Location: communication_hub.php?section=chat");
            exit;
        }
        ?>
        <div class="container mt-5">
            <h1>Supprimer votre message</h1>
            <p>Êtes-vous sûr de vouloir supprimer ce message ?</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <button type="submit" class="btn btn-danger">Supprimer</button>
                <a href="communication_hub.php?section=chat" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
        <?php
        include 'assets/includes/footer.php';
        exit;
    } elseif ($subAction === 'edit_post' && isset($_GET['post_id'])) {
        $post_id = (int)$_GET['post_id'];
        $stmt = $pdo->prepare("SELECT * FROM forum_posts WHERE id = :id");
        $stmt->execute(['id' => $post_id]);
        $postData = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$postData || $postData['agent_id'] != $_SESSION['user_id']) {
            echo '<div class="container mt-5"><p class="text-danger">Post introuvable ou accès refusé.</p></div>';
            include 'assets/includes/footer.php';
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            checkCsrfToken($_POST['csrf_token'] ?? '');
            $newMessage = trim($_POST['message'] ?? '');
            if ($newMessage !== '') {
                $upd = $pdo->prepare("UPDATE forum_posts SET message = :msg WHERE id = :id");
                $upd->execute(['msg' => $newMessage, 'id' => $post_id]);
                header("Location: communication_hub.php?section=posts&thread_id=" . $postData['thread_id']);
                exit;
            }
        }
        ?>
        <div class="container mt-5">
            <h1>Modifier votre post</h1>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <div class="mb-3">
                    <textarea name="message" class="form-control" rows="3" required><?= htmlspecialchars($postData['message']) ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="communication_hub.php?section=posts&thread_id=<?= $postData['thread_id'] ?>" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
        <?php
        include 'assets/includes/footer.php';
        exit;
    } elseif ($subAction === 'delete_post' && isset($_GET['post_id'])) {
        $post_id = (int)$_GET['post_id'];
        $stmt = $pdo->prepare("SELECT * FROM forum_posts WHERE id = :id");
        $stmt->execute(['id' => $post_id]);
        $postData = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$postData || $postData['agent_id'] != $_SESSION['user_id']) {
            echo '<div class="container mt-5"><p class="text-danger">Post introuvable ou accès refusé.</p></div>';
            include 'assets/includes/footer.php';
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            checkCsrfToken($_POST['csrf_token'] ?? '');
            $del = $pdo->prepare("DELETE FROM forum_posts WHERE id = :id");
            $del->execute(['id' => $post_id]);
            header("Location: communication_hub.php?section=posts&thread_id=" . $postData['thread_id']);
            exit;
        }
        ?>
        <div class="container mt-5">
            <h1>Supprimer votre post</h1>
            <p>Êtes-vous sûr de vouloir supprimer ce post ?</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                <button type="submit" class="btn btn-danger">Supprimer</button>
                <a href="communication_hub.php?section=posts&thread_id=<?= $postData['thread_id'] ?>" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
        <?php
        include 'assets/includes/footer.php';
        exit;
    }
}

// Fonctions utilitaires pour récupérer les données
function fetchMessages($pdo, $userId) {
    $sql = "SELECT cm.*, a.username 
            FROM chat_messages cm 
            LEFT JOIN agents a ON cm.sender_id = a.id 
            ORDER BY cm.id DESC 
            LIMIT 50";
    $messages = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return array_reverse($messages);
}
function fetchChannel($pdo, $channelId) {
    $stmt = $pdo->prepare("SELECT * FROM forum_channels WHERE id = :cid");
    $stmt->execute(['cid' => $channelId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function fetchThread($pdo, $threadId) {
    $stmt = $pdo->prepare("SELECT ft.*, fc.name as channel_name, a.username as thread_author 
                           FROM forum_threads ft 
                           JOIN forum_channels fc ON ft.channel_id = fc.id 
                           JOIN agents a ON ft.created_by = a.id 
                           WHERE ft.id = :tid");
    $stmt->execute(['tid' => $threadId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Traitement des requêtes POST pour chat, channels, threads et posts
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken($_POST['csrf_token'] ?? '');
    if ($section === 'chat') {
        $msg = trim($_POST['message'] ?? '');
        if ($msg) {
            $pdo->prepare("INSERT INTO chat_messages (sender_id, message) VALUES (:sid, :m)")
                ->execute(['sid' => $_SESSION['user_id'], 'm' => $msg]);
            header("Location: communication_hub.php?section=chat");
            exit;
        }
    } elseif ($section === 'channels') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $pdo->prepare("INSERT INTO forum_channels (name, created_at) VALUES (:nm, NOW())")
                ->execute(['nm' => $name]);
            header("Location: communication_hub.php?section=channels&created=success");
            exit;
        }
    } elseif ($section === 'threads' && $channelId) {
        $title = trim($_POST['title'] ?? '');
        if ($title) {
            $pdo->prepare("INSERT INTO forum_threads (channel_id, title, created_by) VALUES (:cid, :t, :cb)")
                ->execute(['cid' => $channelId, 't' => $title, 'cb' => $_SESSION['user_id']]);
            header("Location: communication_hub.php?section=threads&channel_id=$channelId&created=success");
            exit;
        }
    } elseif ($section === 'posts' && $threadId) {
        $msg = trim($_POST['message'] ?? '');
        if ($msg) {
            $pdo->prepare("INSERT INTO forum_posts (thread_id, agent_id, message) VALUES (:tid, :aid, :msg)")
                ->execute(['tid' => $threadId, 'aid' => $_SESSION['user_id'], 'msg' => $msg]);
            header("Location: communication_hub.php?section=posts&thread_id=$threadId");
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
    <title>Communication Hub - CAF 47 Collab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'assets/includes/particles-background.php'; ?>
    <div class="container mt-5">
        <!-- Navigation entre sections -->
        <nav class="mb-4">
            <a href="communication_hub.php?section=chat" class="btn btn-outline-primary <?= $section === 'chat' ? 'active' : '' ?>">Chat</a>
            <a href="communication_hub.php?section=channels" class="btn btn-outline-primary <?= $section === 'channels' ? 'active' : '' ?>">Forum - Channels</a>
        </nav>

        <?php if ($section === 'chat'): ?>
            <h1 class="mb-4">Chat Collaboratif</h1>
            <div class="card shadow-sm">
                <div class="card-body" style="height: 400px; overflow-y: auto;" id="chat-box">
                    <?php foreach (fetchMessages($pdo, $_SESSION['user_id']) as $m): ?>
                        <div class="mb-3 <?= $m['sender_id'] == $_SESSION['user_id'] ? 'text-end' : '' ?>">
                            <div class="d-inline-block p-2 rounded <?= $m['sender_id'] == $_SESSION['user_id'] ? 'bg-primary text-white' : 'bg-light' ?>">
                                <strong><?= htmlspecialchars($m['username'] ?? 'Anonyme') ?> :</strong>
                                <?= nl2br(htmlspecialchars($m['message'])) ?>
                            </div>
                            <div class="text-muted small"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></div>
                            <?php if ($m['sender_id'] == $_SESSION['user_id']): ?>
                                <div class="mt-1">
                                    <a href="communication_hub.php?action=edit_chat&msg_id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary">Modifier</a>
                                    <a href="communication_hub.php?action=delete_chat&msg_id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ce message ?');">Supprimer</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <form method="post" class="mt-3">
                <div class="input-group">
                    <input type="text" name="message" class="form-control" placeholder="Tapez votre message..." required>
                    <button type="submit" class="btn btn-primary">Envoyer</button>
                </div>
            </form>
            <script>document.getElementById('chat-box').scrollTop = document.getElementById('chat-box').scrollHeight;</script>
        <?php elseif ($section === 'channels'): ?>
            <h1 class="mb-4">Forum - Channels</h1>
            <?php if (isset($_GET['created'])): ?>
                <div class="alert alert-success">Channel créé avec succès !</div>
            <?php endif; ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <?php
                            $channels = $pdo->query("SELECT * FROM forum_channels ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
                            if ($channels):
                                foreach ($channels as $ch):
                            ?>
                                    <div class="card mb-3 border-0 shadow-sm">
                                        <div class="card-body d-flex justify-content-between align-items-center">
                                            <h5 class="card-title mb-0"><?= htmlspecialchars($ch['name']) ?></h5>
                                            <a href="communication_hub.php?section=threads&channel_id=<?= $ch['id'] ?>" class="btn btn-primary btn-sm">Voir Threads</a>
                                        </div>
                                    </div>
                            <?php
                                endforeach;
                            else:
                            ?>
                                <p class="text-muted">Aucun channel disponible.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0">Créer un Channel</h4>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nom du channel :</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <button type="submit" class="btn btn-success w-100">Créer</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($section === 'threads' && $channelId): ?>
            <?php $channel = fetchChannel($pdo, $channelId) ?: die('<div class="container mt-5"><p class="text-danger">Channel introuvable.</p></div>'); ?>
            <h1 class="mb-4">Channel : <?= htmlspecialchars($channel['name']) ?></h1>
            <?php if (isset($_GET['created'])): ?>
                <div class="alert alert-success">Thread créé avec succès !</div>
            <?php endif; ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <?php
                            $threadsStmt = $pdo->prepare("SELECT ft.*, a.username as author 
                                                          FROM forum_threads ft 
                                                          JOIN agents a ON ft.created_by = a.id 
                                                          WHERE ft.channel_id = :cid 
                                                          ORDER BY ft.id DESC");
                            $threadsStmt->execute(['cid' => $channelId]);
                            $threads = $threadsStmt->fetchAll(PDO::FETCH_ASSOC);
                            if ($threads):
                                foreach ($threads as $th):
                            ?>
                                    <div class="card mb-3 border-0 shadow-sm">
                                        <div class="card-body d-flex justify-content-between align-items-center">
                                            <div>
                                                <h5 class="card-title mb-0"><?= htmlspecialchars($th['title']) ?></h5>
                                                <p class="text-muted small mb-0">
                                                    Par <?= htmlspecialchars($th['author']) ?> - <?= date('d/m/Y', strtotime($th['created_at'])) ?>
                                                </p>
                                            </div>
                                            <a href="communication_hub.php?section=posts&thread_id=<?= $th['id'] ?>" class="btn btn-primary btn-sm">Voir Posts</a>
                                        </div>
                                    </div>
                            <?php
                                endforeach;
                            else:
                            ?>
                                <p class="text-muted">Aucun thread dans ce channel.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0">Nouveau Thread</h4>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Titre :</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                <button type="submit" class="btn btn-success w-100">Créer Thread</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($section === 'posts' && $threadId): ?>
            <?php $thread = fetchThread($pdo, $threadId) ?: die('<div class="container mt-5"><p class="text-danger">Thread introuvable.</p></div>'); ?>
            <h1 class="mb-4">Thread : <?= htmlspecialchars($thread['title']) ?></h1>
            <p class="text-muted">
                Channel : <?= htmlspecialchars($thread['channel_name']) ?> | Auteur : <?= htmlspecialchars($thread['thread_author']) ?>
            </p>
            <div class="card shadow-sm mb-4">
                <div class="card-body" style="max-height: 500px; overflow-y: auto;" id="post-box">
                    <?php
                    $postsStmt = $pdo->prepare("SELECT fp.*, ag.username as author 
                                                FROM forum_posts fp 
                                                JOIN agents ag ON fp.agent_id = ag.id 
                                                WHERE fp.thread_id = :tid 
                                                ORDER BY fp.created_at ASC");
                    $postsStmt->execute(['tid' => $threadId]);
                    $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($posts as $post):
                    ?>
                        <div class="mb-3 <?= $post['agent_id'] == $_SESSION['user_id'] ? 'text-end' : '' ?>">
                            <div class="d-inline-block p-3 rounded <?= $post['agent_id'] == $_SESSION['user_id'] ? 'bg-primary text-white' : 'bg-light' ?>">
                                <strong><?= htmlspecialchars($post['author']) ?> :</strong>
                                <?= nl2br(htmlspecialchars($post['message'])) ?>
                            </div>
                            <div class="text-muted small"><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></div>
                            <?php if ($post['agent_id'] == $_SESSION['user_id']): ?>
                                <div class="mt-1">
                                    <a href="communication_hub.php?action=edit_post&post_id=<?= $post['id'] ?>" class="btn btn-sm btn-outline-secondary">Modifier</a>
                                    <a href="communication_hub.php?action=delete_post&post_id=<?= $post['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ce post ?');">Supprimer</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Ajouter un message</h4>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                        <div class="mb-3">
                            <label for="message" class="form-label">Nouveau message :</label>
                            <textarea class="form-control" id="message" name="message" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Envoyer</button>
                    </form>
                </div>
            </div>
            <script>
                document.getElementById('post-box').scrollTop = document.getElementById('post-box').scrollHeight;
            </script>
        <?php endif; ?>
    </div>
    <?php include 'assets/includes/footer.php'; ?>
</body>
</html>
