<?php
session_start();

// Check if not logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/facebook.php';

$db = new CommentsDatabase();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_mode'])) {
        $pageId = $_POST['page_id'];
        $deleteMode = isset($_POST['delete_mode']) ? 1 : 0;
        if ($db->updateFanPageMode($pageId, $deleteMode)) {
            $message = "Working mode updated successfully!";
        } else {
            $error = "Failed to update working mode.";
        }
        
        header('Location: admin.php?page=cleaner&msg=' . urlencode($message) . '&err=' . urlencode($error));
        exit;
    } elseif (isset($_POST['toggle_cleaner'])) {
        $pageId = $_POST['page_id'];
        $cleanerEnabled = isset($_POST['cleaner_enabled']) ? 1 : 0;
        if ($db->updateCleanerStatus($pageId, $cleanerEnabled)) {
            $message = "Cleaner status updated successfully!";
        } else {
            $error = "Failed to update cleaner status.";
        }
        
        header('Location: admin.php?page=cleaner&msg=' . urlencode($message) . '&err=' . urlencode($error));
        exit;
    } elseif (isset($_POST['clean_content'])) {
        $pageId = $_POST['page_id'];
        $pageData = $db->getFanPage($pageId);
        if ($pageData) {
            $fb = new FacebookAPI($pageData['access_token']);
            $result = $fb->clean_page_content($pageId);
            
            if ($result['success']) {
                $message = $result['message'];
                if (!empty($result['error_details'])) {
                    $error = "Some errors occurred: " . implode("; ", $result['error_details']);
                }
            } else {
                $error = $result['message'];
            }
        } else {
            $error = "Page not found.";
        }
        
        header('Location: admin.php?page=cleaner&msg=' . urlencode($message) . '&err=' . urlencode($error));
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
                                <td class="text-center">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($page['id']); ?>">
                                        <button type="submit" name="clean_content" class="btn btn-warning btn-sm"
                                                onclick="return confirm('This will delete/hide all posts, photos and videos. Are you sure?');">
                                            <i class="bi bi-trash"></i> Clean Content
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
