<?php
session_start();
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/db.php';

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

// Handle login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    if (password_verify($password, Settings::$password)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
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
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">FB Comments Hide'N'Reply</h3>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                            <div class="text-center text-muted mt-3">
                                <small>Version <?php echo htmlspecialchars(trim(file_get_contents(__DIR__ . '/version.txt'))); ?> by <a href="https://yellowweb.top" target="_blank">Yellow Web</a></small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
