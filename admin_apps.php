<?php
// Check if not logged in (session already started in admin.php)
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
    if (isset($_POST['add_app'])) {
        $appToken = $_POST['app_token'];
        
        // Get app info from token
        $fb = new FacebookAPI($appToken);
        $appInfo = $fb->get_app_info();
        
        if (empty($appInfo) || !isset($appInfo['id'])) {
            $error = 'Failed to get app info. Please check the app token.';
        } else {
            $appId = $appInfo['id'];
            $appName = $appInfo['name'] ?? 'Unknown App';
            
            // Construct full callback URL including subdirectory path
            $host = $_SERVER['HTTP_HOST'];
            $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
            $callbackUrl = 'https://' . $host . $scriptPath . '/index.php';
            
            // Subscribe app
            if ($fb->subscribe_app($appId, $callbackUrl)) {
                if ($db->addApp($appId, $appName, $appToken)) {
                    $message = 'App added and subscribed successfully';
                } else {
                    $error = 'Failed to add app to database';
                }
            } else {
                $error = 'Failed to subscribe app. Please check the app token.';
            }
        }
        
        // Redirect to prevent form resubmission
        header('Location: admin.php?page=apps&msg=' . urlencode($message) . '&err=' . urlencode($error));
        exit;
    } elseif (isset($_POST['remove_app'])) {
        $appId = $_POST['app_id'];
        $appData = $db->getApp($appId);
        
        if ($appData) {
            // Unsubscribe app
            $fb = new FacebookAPI($appData['app_token']);
            $fb->unsubscribe_app($appId);
            
            // Remove from database
            if ($db->removeApp($appId)) {
                $message = 'App removed successfully';
            } else {
                $error = 'Failed to remove app from database';
            }
        } else {
            $error = 'App not found';
        }
        
        // Redirect to prevent form resubmission
        header('Location: admin.php?page=apps&msg=' . urlencode($message) . '&err=' . urlencode($error));
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
$apps = $db->getApps();
$appSubscriptions = [];

// Get app subscriptions
foreach ($apps as $app) {
    $fb = new FacebookAPI($app['app_token']);
    $subscriptions = $fb->get_app_subscriptions($app['app_id']);
    $appSubscriptions[$app['app_id']] = $subscriptions;
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
                <h5 class="mb-0">Facebook Apps</h5>
            </div>
            <div class="card-body">
                <form method="post" class="mb-4">
                    <div class="mb-3">
                        <label for="app_token" class="form-label">App Access Token</label>
                        <input type="text" class="form-control" id="app_token" name="app_token" 
                               placeholder="Enter your app access token" required>
                        <small class="form-text text-muted">
                            App ID and name will be automatically fetched from the token
                        </small>
                    </div>
                    <button type="submit" name="add_app" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add App & Subscribe
                    </button>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>App ID</th>
                                <th>App Name</th>
                                <th class="text-center">Access Token</th>
                                <th class="text-center">Subscription Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($apps as $app): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['app_id']); ?></td>
                                <td><?php echo htmlspecialchars($app['app_name']); ?></td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <span class="me-2"><?php echo htmlspecialchars(substr($app['app_token'], 0, 7) . '...'); ?></span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                onclick="copyToClipboard('<?php echo htmlspecialchars($app['app_token'], ENT_QUOTES); ?>', this)"
                                                title="Copy token to clipboard">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $subscriptions = $appSubscriptions[$app['app_id']] ?? [];
                                    $pageSubscribed = false;
                                    $isActive = false;
                                    
                                    foreach ($subscriptions as $sub) {
                                        if ($sub['object'] === 'page') {
                                            $isActive = $sub['active'] ?? false;
                                            $fields = $sub['fields'] ?? [];
                                            foreach ($fields as $field) {
                                                if (isset($field['name']) && $field['name'] === 'feed') {
                                                    $pageSubscribed = true;
                                                    break 2;
                                                }
                                            }
                                        }
                                    }
                                    
                                    if ($pageSubscribed && $isActive): 
                                    ?>
                                        <span class="badge bg-success">✓ Active (page/feed)</span>
                                    <?php elseif ($pageSubscribed && !$isActive): ?>
                                        <span class="badge bg-warning">⚠ Inactive (page/feed)</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not subscribed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="app_id" value="<?php echo htmlspecialchars($app['app_id']); ?>">
                                        <button type="submit" name="remove_app" class="btn btn-danger btn-sm" 
                                                onclick="return confirm('Are you sure? This will unsubscribe and remove the app.');">
                                            <i class="bi bi-trash"></i> Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i> <strong>Webhook URL:</strong> 
                    <?php 
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
                    echo $protocol . '://' . $host . $scriptPath . '/index.php';
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
