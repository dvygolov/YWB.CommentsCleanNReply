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
$availablePages = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['fetch_pages'])) {
        $userToken = $_POST['user_token'];
        $fb = new FacebookAPI($userToken);
        $availablePages = $fb->get_user_pages();
        
        if (empty($availablePages)) {
            $error = 'No pages found or invalid token. Make sure you have pages_manage_posts and pages_read_engagement permissions.';
        }
    } elseif (isset($_POST['add_selected_pages'])) {
        $selectedPages = $_POST['selected_pages'] ?? [];
        $pagesData = json_decode($_POST['pages_data'], true);
        
        if (empty($selectedPages)) {
            $error = 'No pages selected';
        } else {
            $addedCount = 0;
            $failedCount = 0;
            
            foreach ($selectedPages as $pageId) {
                if (!isset($pagesData[$pageId])) continue;
                
                $pageData = $pagesData[$pageId];
                $fb = new FacebookAPI($pageData['access_token']);
                
                // Subscribe to page feed
                if ($fb->subscribe_to_feed($pageId)) {
                    if ($db->addFanPage($pageId, $pageData['access_token'], $pageData['name'], $pageData['avatar'], 0)) {
                        $addedCount++;
                    } else {
                        $failedCount++;
                    }
                } else {
                    $failedCount++;
                }
            }
            
            if ($addedCount > 0) {
                $message = "Successfully added $addedCount page(s)";
            }
            if ($failedCount > 0) {
                $error = "Failed to add $failedCount page(s)";
            }
        }
        
        header('Location: admin.php?page=pages&msg=' . urlencode($message) . '&err=' . urlencode($error));
        exit;
    } elseif (isset($_POST['remove_page'])) {
        $pageId = $_POST['page_id'];
        $pageData = $db->getFanPage($pageId);
        if ($pageData) {
            $fb = new FacebookAPI($pageData['access_token']);
            $fb->unsubscribe_from_feed($pageId);
        }
        
        if ($db->removeFanPage($pageId)) {
            $message = "Fan page removed successfully!";
        } else {
            $error = "Failed to remove fan page.";
        }
        
        header('Location: admin.php?page=pages&msg=' . urlencode($message) . '&err=' . urlencode($error));
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
$tokenValidation = [];

// Validate all tokens
foreach ($fanPages as $page) {
    $fb = new FacebookAPI($page['access_token']);
    $validation = $fb->validate_token();
    $tokenValidation[$page['id']] = $validation;
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
                <h5 class="mb-0">Add Fan Pages</h5>
            </div>
            <div class="card-body">
                <form method="post" class="mb-4">
                    <div class="mb-3">
                        <label for="user_token" class="form-label">User Access Token</label>
                        <input type="text" class="form-control" id="user_token" name="user_token" 
                               placeholder="Enter your Facebook user access token" required>
                        <small class="form-text text-muted">
                            Enter your user access token with pages_manage_posts and pages_read_engagement permissions
                        </small>
                    </div>
                    <button type="submit" name="fetch_pages" class="btn btn-primary">
                        <i class="bi bi-search"></i> Fetch My Pages
                    </button>
                </form>

                <?php if (!empty($availablePages)): ?>
                <form method="post" class="mb-4">
                    <h6 class="mb-3">Select pages to add:</h6>
                    <input type="hidden" name="pages_data" value="<?php echo htmlspecialchars(json_encode(array_column($availablePages, null, 'id'))); ?>">
                    
                    <div class="row">
                        <?php foreach ($availablePages as $page): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="selected_pages[]" 
                                               value="<?php echo htmlspecialchars($page['id']); ?>" 
                                               id="page_<?php echo htmlspecialchars($page['id']); ?>">
                                        <label class="form-check-label d-flex align-items-center w-100" 
                                               for="page_<?php echo htmlspecialchars($page['id']); ?>" 
                                               style="cursor: pointer;">
                                            <?php if (!empty($page['avatar'])): ?>
                                                <img src="<?php echo htmlspecialchars($page['avatar']); ?>" 
                                                     alt="" class="rounded-circle me-2" style="width: 40px; height: 40px;">
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($page['name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($page['id']); ?></small>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="submit" name="add_selected_pages" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Add Selected Pages
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Managed Pages</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th class="text-center">Access Token</th>
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
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <span class="me-2"><?php echo htmlspecialchars(substr($page['access_token'], 0, 7) . '...'); ?></span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                onclick="copyToClipboard('<?php echo htmlspecialchars($page['access_token'], ENT_QUOTES); ?>', this)"
                                                title="Copy token to clipboard">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $validation = $tokenValidation[$page['id']] ?? ['valid' => false, 'error' => 'Unknown'];
                                    if ($validation['valid']): 
                                        if (isset($validation['expires_at']) && $validation['expires_at']): 
                                            $expiryDate = date('Y-m-d H:i', $validation['expires_at']);
                                    ?>
                                        <span title="Token is valid">✅</span><br>
                                        <small class="text-muted">Expires: <?php echo $expiryDate; ?></small>
                                    <?php else: ?>
                                        <span title="Token is valid (never expires)">✅ Never expires</span>
                                    <?php endif; ?>
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
