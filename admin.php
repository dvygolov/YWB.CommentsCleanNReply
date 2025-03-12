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
        $deleteMode = isset($_POST['delete_mode']) ? 1 : 0;
        
        // First try to subscribe to the page feed
        $fb = new FacebookAPI($accessToken);
        if ($fb->subscribe_to_feed($pageId)) {
            // Only add to database if subscription was successful
            if ($db->addFanPage($pageId, $accessToken, $deleteMode)) {
                $message = 'Fan page added successfully';
            } else {
                $error = 'Failed to add fan page to database';
            }
        } else {
            $error = 'Failed to subscribe to page feed. Please check the access token and page ID.';
        }
    } elseif (isset($_POST['remove_page'])) {
        $pageId = $_POST['page_id'];
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
foreach ($fanPages as $page) {
    $rules = $db->getReplyRules($page['id']);
    $replyRules = array_merge($replyRules, $rules);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YWB Comments Hide'N'Reply</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .form-switch .form-check-input { margin-left: 0; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">YWB Comments Hide'N'Reply</a>
            <div class="d-flex">
                <a href="logviewer.php" class="btn btn-info me-2">Log Viewer</a>
                <a href="logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Fan Pages Section -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Fan Pages</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" class="mb-4">
                            <div class="mb-3">
                                <label for="page_id" class="form-label">Page ID</label>
                                <input type="text" class="form-control" id="page_id" name="page_id" required>
                            </div>
                            <div class="mb-3">
                                <label for="access_token" class="form-label">Access Token</label>
                                <input type="text" class="form-control" id="access_token" name="access_token" required>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="delete_mode" name="delete_mode" value="1">
                                    <label class="form-check-label" for="delete_mode">Delete comments (if unchecked, comments will be hidden)</label>
                                </div>
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
                                                <th>Page ID</th>
                                                <th>Access Token</th>
                                                <th class="text-center">Working Mode</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($fanPages as $page): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($page['id']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($page['access_token'], 0, 7) . '...'); ?></td>
                                                <td class="text-center">
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($page['id']); ?>">
                                                        <div class="form-check form-switch d-flex justify-content-center">
                                                            <input class="form-check-input" type="checkbox" name="delete_mode" value="1" 
                                                                   <?php echo $page['delete_mode'] ? 'checked' : ''; ?> 
                                                                   onchange="this.form.submit()">
                                                            <label class="form-check-label ms-2"><?php echo $page['delete_mode'] ? 'Delete' : 'Hide'; ?></label>
                                                        </div>
                                                        <input type="hidden" name="update_mode" value="1">
                                                    </form>
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

            <!-- Reply Rules Section -->
            <div class="col-md-6 mb-4">
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
                                            <?php echo htmlspecialchars($page['id']); ?>
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
                                        <th>Page ID</th>
                                        <th>Triggers</th>
                                        <th>Reply</th>
                                        <th>Image</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($replyRules as $rule): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($rule['page_id']); ?></td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
