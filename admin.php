<?php
session_start();
require_once __DIR__ . '/settings.php';

// Check if not logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Determine which page to show
$currentPage = $_GET['page'] ?? 'apps';
$allowedPages = ['apps', 'pages', 'cleaner', 'replies'];

if (!in_array($currentPage, $allowedPages)) {
    $currentPage = 'apps';
}

$pageFile = __DIR__ . '/admin_' . $currentPage . '.php';
if (!file_exists($pageFile)) {
    die("Page not found: $pageFile");
}

// Include the page content
ob_start();
include $pageFile;
$pageContent = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FB Comments Hide'N'Reply</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="alternate icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAAXNSR0IArs4c6QAAAm5JREFUWEftl89LVFEYxz/3zjg6ZmqJi6xFEUKLwuimRRQVtGvRLwh/QK2CaBO0adW/EARF9AMignZBEIRYi6JFQURBtYigwFVqjjM6c+89cQ7eO3Pn3pk7d0baBGd17z3nfb/P9zzPc88VNPkRTY6PC0AzM2AFUAJmgWLYxbUyYANbgduABUwAz4HPYQBhAJuBR8AuQAeOgQHgkwZxEbgJXAZ+A++Ap8DnIIgggKvAbeC6huUAI8Bd4IUGsRu4D+wBVgIfgEfAeBDEUoD1wB3gFqDrXwpAP/BMQxgGHgLDQAewFhgCeoEfwHtNz8dFEKuAO8ADYIe2+RvwBJgEfgGngTPABuA8cAXo0rQMA0+ACeDvYhDtwCAwCJwEVuuVfwQGgVfAD+AQcA7YBpwCzuqVq/v3gOfA9DIQ7cA+oA84DnRqX74FBoA3wDRwEDgKHNFxqDjV8xp4C8wshWgDjgEXgD1Ah/blO/ASeA3MAIeBM8Bx4IS2W8XxEfgM/PFDtAL7gYvAPqBN+/IDeKV9mQUOAKeB08Ax7YuK4zPwBZjzQ7QA+4CLwH6gVfsyBbzWvvwB9gKngFPAEe2LiuML8BWY90O0AHuBi8ABbXMBmNK+TAJzwE7N+UHggPZFxfEV+AbYfohm7YvS3KN9KQJ/tS8TwDywXXN+QGtexaHi+A78BEp+iFXadqX5Vq15CZjVvowD80CX5vyA1ryKQ8XxE/gFlP0QLVrzg8AerXkZmNO+jGnNt2nOD2jNqzhUHL+BWaASVo4V5/uA3TqICjCvfRkDSsAWzXmf1ryKQ8XxB8iHAYQVo2aPNx3gP2U5eSGwF/AoAAAAAElFTkSuQmCC">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 60px;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        .sidebar {
            position: fixed;
            top: 56px;
            left: 0;
            height: calc(100vh - 56px);
            background: #2c3e50;
            transition: width 0.3s;
            z-index: 1000;
            overflow-x: hidden;
        }
        
        .sidebar.expanded {
            width: var(--sidebar-width);
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        
        .sidebar-toggle {
            padding: 15px;
            color: white;
            cursor: pointer;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .sidebar-menu li a:hover {
            background: #34495e;
        }
        
        .sidebar-menu li a.active {
            background: #3498db;
        }
        
        .sidebar-menu li a i {
            font-size: 1.2rem;
            min-width: 20px;
        }
        
        .sidebar-menu li a span {
            margin-left: 15px;
            white-space: nowrap;
        }
        
        .sidebar.collapsed .sidebar-menu li a span {
            display: none;
        }
        
        .main-content {
            transition: margin-left 0.3s;
            padding-top: 56px;
        }
        
        .main-content.expanded {
            margin-left: var(--sidebar-width);
        }
        
        .main-content.collapsed {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        .content-section {
            display: none;
        }
        
        .content-section.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">FB Comments Hide'N'Reply - Admin Panel</span>
            <div>
                <a href="logviewer.php" class="btn btn-info me-2">Log Viewer</a>
                <a href="logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar expanded" id="sidebar">
        <div class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="?page=apps" class="<?php echo $currentPage === 'apps' ? 'active' : ''; ?>">
                    <i class="bi bi-app-indicator"></i>
                    <span>Apps</span>
                </a>
            </li>
            <li>
                <a href="?page=pages" class="<?php echo $currentPage === 'pages' ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Pages</span>
                </a>
            </li>
            <li>
                <a href="?page=cleaner" class="<?php echo $currentPage === 'cleaner' ? 'active' : ''; ?>">
                    <i class="bi bi-shield-check"></i>
                    <span>Cleaner</span>
                </a>
            </li>
            <li>
                <a href="?page=replies" class="<?php echo $currentPage === 'replies' ? 'active' : ''; ?>">
                    <i class="bi bi-chat-dots"></i>
                    <span>Replies</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content expanded" id="mainContent">
        <div class="container my-4">
            <?php echo $pageContent; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Copy to clipboard function
        function copyToClipboard(text, btn) {
            navigator.clipboard.writeText(text).then(function() {
                // Show success feedback
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check"></i>';
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-success');
                
                setTimeout(function() {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-secondary');
                }, 1500);
            }).catch(function(err) {
                alert('Failed to copy: ' + err);
            });
        }
        
        // Toggle sidebar expanded/collapsed
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (sidebar.classList.contains('expanded')) {
                sidebar.classList.remove('expanded');
                sidebar.classList.add('collapsed');
                mainContent.classList.remove('expanded');
                mainContent.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
                sidebar.classList.add('expanded');
                mainContent.classList.remove('collapsed');
                mainContent.classList.add('expanded');
            }
        }
    </script>
</body>
</html>
