<?php
// Check if not logged in (session already started in admin.php)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/facebook.php';
require_once __DIR__ . '/logger.php';

$db = new CommentsDatabase();
$message = '';
$error = '';

// Handle file upload
function handleImageUpload($file) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        CommentsLogger::log("No file uploaded or empty file", 'Warning');
        return null;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        CommentsLogger::log("Invalid file type: " . $file['type'], 'Error');
        return false;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    
    $uploadsDir = __DIR__ . '/uploads';

    if (!file_exists($uploadsDir) || !is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
    }

    $uploadPath = $uploadsDir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        CommentsLogger::log("File successfully uploaded to: " . $uploadPath, 'Info');
        return $filename;
    }
    CommentsLogger::log("Failed to move uploaded file to: " . $uploadPath, 'Error');
    return false;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_rule') {
        $pageId = $_POST['page_id'];
        $triggerWords = $_POST['trigger_words'];
        $replyText = $_POST['reply_text'];
        
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
        
        header('Location: admin.php?page=replies&msg=' . urlencode($message) . '&err=' . urlencode($error));
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'remove_rule') {
        if ($db->removeReplyRule($_POST['rule_id'])) {
            $message = 'Reply rule removed successfully';
        } else {
            $error = 'Failed to remove reply rule';
        }
        
        header('Location: admin.php?page=replies&msg=' . urlencode($message) . '&err=' . urlencode($error));
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_rule') {
        $ruleId = $_POST['rule_id'];
        $triggerWords = $_POST['trigger_words'];
        $replyText = $_POST['reply_text'];
        
        $imagePath = $_POST['current_image'];
        if (isset($_FILES['reply_image']) && !empty($_FILES['reply_image']['tmp_name'])) {
            $newImage = handleImageUpload($_FILES['reply_image']);
            if ($newImage === false) {
                $error = "Invalid image file. Please upload JPG, PNG or GIF.";
            } else {
                if (!empty($imagePath)) {
                    @unlink(__DIR__ . '/uploads/' . $imagePath);
                }
                $imagePath = $newImage;
            }
        }
        
        if (empty($error)) {
            if ($db->updateReplyRule($ruleId, $triggerWords, $replyText, $imagePath)) {
                $message = 'Reply rule updated successfully';
            } else {
                $error = 'Failed to update reply rule';
            }
        }
        
        header('Location: admin.php?page=replies&msg=' . urlencode($message) . '&err=' . urlencode($error));
        exit;
    }
}

// Get messages from URL
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}
if (isset($_GET['err'])) {
    $error = $_GET['err'];
}

// Get current data
$fanPages = $db->getFanPages();
$replyRules = [];

foreach ($fanPages as $page) {
    $rules = $db->getReplyRules($page['id']);
    $replyRules = array_merge($replyRules, $rules);
}
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row">
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
                                    <?php echo htmlspecialchars($page['page_name']); ?> (<?php echo htmlspecialchars($page['id']); ?>)
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
