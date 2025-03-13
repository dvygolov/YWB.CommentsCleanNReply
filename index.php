<?php
include_once __DIR__ . '/settings.php';
include_once __DIR__ . '/logger.php';
include_once __DIR__ . '/facebook.php';
include_once __DIR__ . '/db.php';

class FBWebhookHandler
{
    private $db;

    public function __construct()
    {
        $this->db = new CommentsDatabase();
    }

    private function loadResponses($pageId): array {
        $responses = [];
        $rules = $this->db->getReplyRules($pageId);
        foreach ($rules as $rule) {
            $responses[] = [
                'keywords' => explode(',', $rule['trigger_words']),
                'reply' => $rule['reply_text'],
                'image' => $rule['image_path']
            ];
        }
        return $responses;
    }

    public static function verify_challenge($get_params): bool
    {
        if ($get_params['hub_mode'] === 'subscribe' && 
            $get_params['hub_verify_token'] === 'my_voice_is_my_password_verify_me') {
            echo $get_params['hub_challenge'];
            exit;
        }
        http_response_code(403);
        exit;
    }

    public function handle_update($input)
    {
        if (!isset($input)) return;
        
        // Check for a comment and handle it
        $changeValue = $input['entry'][0]['changes'][0]['value'];
        if (!isset($changeValue['item']) ||
            $changeValue['item'] !== 'comment' ||
            !isset($changeValue['verb']) ||
            $changeValue['verb'] !== 'add') {
            return;
        }
        
        $pageId = $input['entry'][0]['id'];
        $from_id = $changeValue['from']['id'];
        
        // If comment is from the page itself, do not process
        if ($pageId === $from_id) {
            CommentsLogger::log( "Comment is from the Page itself!", 'Info');
            return;
        }

        $jsonData = json_encode($input, JSON_PRETTY_PRINT);
        CommentsLogger::log("Got New Comment: {$jsonData}", 'Info');
        
        $responses = $this->loadResponses($pageId);
        
        // Get page access token and settings
        $pageData = $this->db->getFanPages();
        $pageData = array_filter($pageData, function($p) use ($pageId) { return $p['id'] === $pageId; });
        $pageData = reset($pageData);
        
        if (!$pageData) {
            CommentsLogger::log("Page not found in database: {$pageId}", 'Error', true);
            return;
        }
        
        $accessToken = $pageData['access_token'];
        $deleteMode = $pageData['delete_mode'];
        
        // Initialize Facebook API with page token
        $fb = new FacebookAPI($accessToken);
        
        // Extract necessary values
        $commentId = $changeValue['comment_id'];
        $commentMessage = mb_strtolower($changeValue['message']);
        $commentMessage = preg_replace("/[\r\n\t ]+/", " ", $commentMessage);

        $responseFound = false;
        // Check if the comment contains any keywords and respond
        foreach ($responses as $response) {
            foreach ($response['keywords'] as $keyword) {
                CommentsLogger::log("Checking keyword:{$keyword}...", 'Trace');
                if (stripos($commentMessage, trim($keyword)) !== false) {
                    CommentsLogger::log("Keyword:{$keyword} found! Replying...", 'Trace');
                    $fb->reply_to_comment($commentId, $response['reply'], $response['image']);
                    $responseFound = true;
                    break 2;
                }
            }
        }

        // Handle comment based on working mode
        if (!$responseFound) {
            if ($deleteMode) {
                $fb->delete_comment($commentId);
                CommentsLogger::log("Deleted comment: {$commentId}", 'Info');
            } else {
                $fb->hide_comment($commentId);
                CommentsLogger::log("Hidden comment: {$commentId}", 'Info');
            }
        }
    }
}

try {
    if (isset($_GET['hub_mode'])) {
        FBWebhookHandler::verify_challenge($_GET);
    } else {
        $handler = new FBWebhookHandler();
        $inputPath = 'php://input'; //Settings::$debug ? __DIR__ . '/debugrequest.json' : 'php://input';
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
