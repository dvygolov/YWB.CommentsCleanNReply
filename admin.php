<?php
session_start();
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/facebook.php';
require_once __DIR__ . '/logger.php'; 

// Check if not logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$db = new CommentsDatabase();
$message = '';
$error = '';

// Handle file upload
function handleImageUpload($file) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        CommentsLogger::log("No file uploaded or empty file", 'Warning');
        return null;
    }

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        CommentsLogger::log("Invalid file type: " . $file['type'], 'Error');
        return false;
    }

    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $uploadPath = __DIR__ . '/uploads/' . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        CommentsLogger::log("File successfully uploaded to: " . $uploadPath, 'Info');
        return $filename;
    }
    CommentsLogger::log("Failed to move uploaded file to: " . $uploadPath, 'Error');
    return false;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_page'])) {
        $pageId = $_POST['page_id'];
        $accessToken = $_POST['access_token'];
        
        // First try to subscribe to the page feed
        $fb = new FacebookAPI($accessToken);
        if ($fb->subscribe_to_feed($pageId)) {
            $pageInfo = $fb->get_page_info($pageId);
            if (empty($pageInfo)) {
                $error = 'Failed to retrieve page info';
                CommentsLogger::log("Failed to retrieve page info for {$pageId}", 'Error');
                return;
            }
            // Only add to database if subscription was successful (cleaner disabled by default)
            if ($db->addFanPage($pageId, $accessToken, $pageInfo['name'], $pageInfo['avatar'], 0)) {
                $message = 'Fan page added successfully';
            } else {
                $error = 'Failed to add fan page to database';
            }
        } else {
            $error = 'Failed to subscribe to page feed. Please check the access token and page ID.';
        }
    } elseif (isset($_POST['remove_page'])) {
        $pageId = $_POST['page_id'];
        // Get page data to get the access token before removal
        $pageData = $db->getFanPage($pageId);
        if ($pageData) {
            // Initialize Facebook API with page token and unsubscribe from feed
            $fb = new FacebookAPI($pageData['access_token']);
            $fb->unsubscribe_from_feed($pageId);
        }
        
        // Now remove from database
        if ($db->removeFanPage($pageId)) {
            $message = "Fan page removed successfully!";
        } else {
            $error = "Failed to remove fan page.";
        }
    } elseif (isset($_POST['update_mode'])) {
        $pageId = $_POST['page_id'];
        $deleteMode = isset($_POST['delete_mode']) ? 1 : 0;
        if ($db->updateFanPageMode($pageId, $deleteMode)) {
            $message = "Working mode updated successfully!";
        } else {
            $error = "Failed to update working mode.";
        }
    } elseif (isset($_POST['toggle_cleaner'])) {
        $pageId = $_POST['page_id'];
        $cleanerEnabled = isset($_POST['cleaner_enabled']) ? 1 : 0;
        if ($db->updateCleanerStatus($pageId, $cleanerEnabled)) {
            $message = "Cleaner status updated successfully!";
        } else {
            $error = "Failed to update cleaner status.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_rule') {
        $pageId = $_POST['page_id'];
        $triggerWords = $_POST['trigger_words'];
        $replyText = $_POST['reply_text'];
        
        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['reply_image']) && !empty($_FILES['reply_image']['tmp_name'])) {
            $imagePath = handleImageUpload($_FILES['reply_image']);
            if ($imagePath === false) {
                $error = "Invalid image file. Please upload JPG, PNG or GIF.";
            }
        }
        
        if (empty($error)) {
            if ($db->addReplyRule($pageId, $triggerWords, $replyText, $imagePath)) {
                $message = 'Reply rule added successfully';
            } else {
                $error = 'Failed to add reply rule';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'remove_rule') {
        if ($db->removeReplyRule($_POST['rule_id'])) {
            $message = 'Reply rule removed successfully';
        } else {
            $error = 'Failed to remove reply rule';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_rule') {
        $ruleId = $_POST['rule_id'];
        $triggerWords = $_POST['trigger_words'];
        $replyText = $_POST['reply_text'];
        
        // Handle image upload
        $imagePath = $_POST['current_image']; // Keep existing image if no new one uploaded
        if (isset($_FILES['reply_image']) && !empty($_FILES['reply_image']['tmp_name'])) {
            $newImage = handleImageUpload($_FILES['reply_image']);
            if ($newImage === false) {
                $error = "Invalid image file. Please upload JPG, PNG or GIF.";
                CommentsLogger::log("Failed to upload image: " . print_r($_FILES['reply_image'], true), 'Error');
            } else {
                // Delete old image if exists
                if (!empty($imagePath)) {
                    if (!@unlink(__DIR__ . '/uploads/' . $imagePath)) {
                        CommentsLogger::log("Failed to delete old image: " . $imagePath, 'Warning');
                    }
                }
                $imagePath = $newImage;
                CommentsLogger::log("New image path set to: " . $newImage, 'Info');
            }
        }
        
        if (empty($error)) {
            $ruleUpdated = $db->updateReplyRule($ruleId, $triggerWords, $replyText, $imagePath);
            if ($ruleUpdated) {
                $message = 'Reply rule updated successfully';
                CommentsLogger::log("Rule $ruleId updated with image: $imagePath", 'Info');
            } else {
                $error = 'Failed to update reply rule';
                CommentsLogger::log("Failed to update rule $ruleId in database", 'Error');
            }
        }
    }
}

// Get current data
$fanPages = $db->getFanPages();
$replyRules = [];
$tokenValidation = [];

// Validate all tokens
foreach ($fanPages as $page) {
    $rules = $db->getReplyRules($page['id']);
    $replyRules = array_merge($replyRules, $rules);
    
    // Validate token
    $fb = new FacebookAPI($page['access_token']);
    $validation = $fb->validate_token();
    $tokenValidation[$page['id']] = $validation;
}
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
        .form-switch .form-check-input { margin-left: 0; }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 56px;
            left: 0;
            height: calc(100vh - 56px);
            background: #212529;
            transition: width 0.3s ease;
            z-index: 1000;
            overflow-x: hidden;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        
        .sidebar.expanded {
            width: 250px;
        }
        
        .sidebar.collapsed {
            width: 60px;
        }
        
        .sidebar-toggle {
            padding: 15px;
            color: #fff;
            cursor: pointer;
            border-bottom: 1px solid #343a40;
            text-align: center;
        }
        
        .sidebar-toggle:hover {
            background: #343a40;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            border-bottom: 1px solid #343a40;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px;
            color: #adb5bd;
            text-decoration: none;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .sidebar-menu a:hover {
            background: #343a40;
            color: #fff;
        }
        
        .sidebar-menu a.active {
            background: #0d6efd;
            color: #fff;
        }
        
        .sidebar-menu i {
            font-size: 1.2rem;
            min-width: 30px;
        }
        
        .sidebar-menu span {
            margin-left: 10px;
            opacity: 1;
            transition: opacity 0.3s;
        }
        
        .sidebar.collapsed .sidebar-menu span {
            opacity: 0;
            width: 0;
        }
        
        /* Main Content */
        .main-content {
            transition: margin-left 0.3s ease;
        }
        
        .main-content.expanded {
            margin-left: 250px;
        }
        
        .main-content.collapsed {
            margin-left: 60px;
        }
        
        .content-section {
            display: none;
        }
        
        .content-section.active {
            display: block;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">FB Comments Hide'N'Reply</a>
            <div class="d-flex">
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
                <a href="#" class="active" onclick="showSection('pages', this)">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Pages</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="showSection('cleaner', this)">
                    <i class="bi bi-shield-check"></i>
                    <span>Cleaner</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="showSection('replies', this)">
                    <i class="bi bi-chat-dots"></i>
                    <span>Replies</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content expanded" id="mainContent">
        <div class="container my-4">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Pages Section -->
        <div id="pagesSection" class="content-section active">
            <div class="row">
            <!-- Fan Pages Section -->
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Fan Pages</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" class="mb-4">
                            <div class="mb-3">
                                <label for="page_id" class="form-label">Page</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="page_id" name="page_id" placeholder="Page ID" required>
                                </div>
                                <small class="form-text text-muted">Enter the Page ID, the avatar and name will be fetched when you add the page</small>
                            </div>
                            <div class="mb-3">
                                <label for="access_token" class="form-label">Access Token</label>
                                <input type="text" class="form-control" id="access_token" name="access_token" required>
                            </div>
                            <button type="submit" name="add_page" class="btn btn-primary">Add Fan Page</button>
                        </form>

                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Fan Pages</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Page</th>
                                                <th>Access Token</th>
                                                <th class="text-center">Status</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($fanPages as $page): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($page['page_avatar'])): ?>
                                                            <img src="<?php echo htmlspecialchars($page['page_avatar']); ?>" alt="" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                                                        <?php endif; ?>
                                                        <div>
            <div><?php echo htmlspecialchars($page['page_name']); ?></div>
            <small class="text-muted"><?php echo htmlspecialchars($page['id']); ?></small>
        </div>
    </div>
</td>
                                                <td><?php echo htmlspecialchars(substr($page['access_token'], 0, 7) . '...'); ?></td>
                                                <td class="text-center">
                                                    <?php 
                                                    $validation = $tokenValidation[$page['id']] ?? ['valid' => false, 'error' => 'Unknown'];
                                                    if ($validation['valid']): 
                                                    ?>
                                                        <span title="Token is valid">✅</span>
                                                    <?php else: ?>
                                                        <span title="<?php echo htmlspecialchars($validation['error']); ?>">❌ <?php echo htmlspecialchars($validation['error']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($page['id']); ?>">
                                                        <button type="submit" name="remove_page" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Are you sure you want to remove this page?');">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>

        <!-- Cleaner Section -->
        <div id="cleanerSection" class="content-section">
            <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Comments Cleaner Configuration</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Configure comment processing mode for each page. Comments can be either hidden or permanently deleted.</p>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Page</th>
                                        <th class="text-center">Cleaner Status</th>
                                        <th class="text-center">Processing Mode</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fanPages as $page): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($page['page_avatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars($page['page_avatar']); ?>" alt="" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                                                <?php endif; ?>
                                                <div>
                                                    <div><?php echo htmlspecialchars($page['page_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($page['id']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($page['id']); ?>">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox" name="cleaner_enabled" value="1" 
                                                           <?php echo $page['cleaner_enabled'] ? 'checked' : ''; ?> 
                                                           onchange="this.form.submit()">
                                                    <label class="form-check-label ms-2"><?php echo $page['cleaner_enabled'] ? 'ON' : 'OFF'; ?></label>
                                                </div>
                                                <input type="hidden" name="toggle_cleaner" value="1">
                                            </form>
                                        </td>
                                        <td class="text-center">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($page['id']); ?>">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox" name="delete_mode" value="1" 
                                                           <?php echo $page['delete_mode'] ? 'checked' : ''; ?> 
                                                           onchange="this.form.submit()"
                                                           <?php echo !$page['cleaner_enabled'] ? 'disabled' : ''; ?>>
                                                    <label class="form-check-label ms-2"><?php echo $page['delete_mode'] ? 'Delete' : 'Hide'; ?></label>
                                                </div>
                                                <input type="hidden" name="update_mode" value="1">
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>

        <!-- Replies Section -->
        <div id="repliesSection" class="content-section">
            <div class="row">
            <!-- Reply Rules Section -->
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Reply Rules</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" class="mb-4">
                            <input type="hidden" name="action" value="add_rule">
                            <div class="mb-3">
                                <label for="rule_page_id" class="form-label">Page</label>
                                <select class="form-select" id="rule_page_id" name="page_id" required>
                                    <?php foreach ($fanPages as $page): ?>
                                        <option value="<?php echo htmlspecialchars($page['id']); ?>">
                                            <?php if (!empty($page['page_avatar'])): ?>
                                                <?php echo htmlspecialchars($page['page_name']); ?> 
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($page['id']); ?>
                                            <?php endif; ?>
</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="trigger_words" class="form-label">Trigger Words (comma-separated)</label>
                                <input type="text" class="form-control" id="trigger_words" name="trigger_words" required>
                            </div>
                            <div class="mb-3">
                                <label for="reply_text" class="form-label">Reply Text</label>
                                <textarea class="form-control" id="reply_text" name="reply_text" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="reply_image" class="form-label">Reply Image (optional)</label>
                                <input type="file" class="form-control" id="reply_image" name="reply_image" accept="image/*">
                            </div>
                            <button type="submit" class="btn btn-primary">Add Rule</button>
                        </form>

                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Page</th>
                                        <th>Triggers</th>
                                        <th>Reply</th>
                                        <th>Image</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($replyRules as $rule): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $pageId = $rule['page_id'];
                                                $pageFound = false;
                                                foreach ($fanPages as $page) {
                                                    if ($page['id'] === $pageId) {
                                                        $pageFound = true;
                                                        ?>
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($page['page_avatar'])): ?>
                                                                <img src="<?php echo htmlspecialchars($page['page_avatar']); ?>" alt="" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                                                            <?php endif; ?>
                <div>
                    <div><?php echo htmlspecialchars($page['page_name']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($pageId); ?></small>
                </div>
            </div>
            <?php
            break;
        }
    }
    if (!$pageFound) {
        echo htmlspecialchars($pageId);
    }
    ?>
</td>
                                            <td><?php echo htmlspecialchars(strlen($rule['trigger_words']) > 15 ? substr($rule['trigger_words'], 0, 15) . '...' : $rule['trigger_words']); ?></td>
                                            <td class="text-truncate" style="max-width: 200px;">
                                                <?php echo htmlspecialchars(strlen($rule['reply_text']) > 15 ? substr($rule['reply_text'], 0, 15) . '...' : $rule['reply_text']); ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($rule['image_path'])): ?>
                                                <img src="image.php?file=<?= urlencode($rule['image_path']) ?>" 
                                                     alt="Reply image" class="img-thumbnail" style="max-height: 50px;">
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-primary btn-sm me-1" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editRule<?php echo $rule['rule_id']; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="remove_rule">
                                                    <input type="hidden" name="rule_id" value="<?php echo $rule['rule_id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure?')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>

                                        <!-- Edit Rule Modal -->
                                        <div class="modal fade" id="editRule<?php echo $rule['rule_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Rule</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="post" enctype="multipart/form-data">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="update_rule">
                                                            <input type="hidden" name="rule_id" value="<?php echo $rule['rule_id']; ?>">
                                                            <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($rule['image_path'] ?? ''); ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Page ID</label>
                                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($rule['page_id']); ?>" readonly>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="edit_trigger_words" class="form-label">Trigger Words</label>
                                                                <input type="text" class="form-control" name="trigger_words" 
                                                                       value="<?php echo htmlspecialchars($rule['trigger_words']); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="edit_reply_text" class="form-label">Reply Text</label>
                                                                <textarea class="form-control" name="reply_text" rows="3" required><?php echo htmlspecialchars($rule['reply_text']); ?></textarea>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="edit_reply_image" class="form-label">Reply Image</label>
                                                                <?php if (!empty($rule['image_path'])): ?>
                                                                <div class="mb-2">
                                                                    <img src="image.php?file=<?= urlencode($rule['image_path']) ?>" 
                                                                         alt="Current image" class="img-thumbnail" style="max-height: 100px;">
                                                                </div>
                                                                <?php endif; ?>
                                                                <input type="file" class="form-control" 
                                                                       id="edit_reply_image"
                                                                       name="reply_image" accept="image/*">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
        
        // Show selected section
        function showSection(sectionName, element) {
            // Prevent default link behavior
            event.preventDefault();
            
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all menu items
            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                link.classList.remove('active');
            });
            
            // Show selected section
            if (sectionName === 'pages') {
                document.getElementById('pagesSection').classList.add('active');
            } else if (sectionName === 'cleaner') {
                document.getElementById('cleanerSection').classList.add('active');
            } else if (sectionName === 'replies') {
                document.getElementById('repliesSection').classList.add('active');
            }
            
            // Add active class to clicked menu item
            element.classList.add('active');
        }
    </script>
</body>
</html>
