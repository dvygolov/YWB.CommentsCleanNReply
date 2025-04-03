<?php
include_once __DIR__ . '/logger.php';
include_once __DIR__ . '/hookhandler.php';

try {
    if (isset($_GET['hub_mode'])) {
        FBWebhookHandler::verify_challenge($_GET);
    } else {
        $handler = new FBWebhookHandler();
        $inputPath = 'php://input';
        $input = file_get_contents($inputPath);
        if (!empty($input)) {
            $json = json_decode($input, true);
            $handler->handle_update($json);
        }
        else {
            header('Location: admin.php');
        }
    }
} catch (Exception $e) {
    CommentsLogger::log($e->getMessage(), 'Error', true);
}
