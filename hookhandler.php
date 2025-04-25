<?php
include_once __DIR__ . '/db.php';
include_once __DIR__ . '/facebook.php';
include_once __DIR__ . '/logger.php';

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
        if ($get_params['hub_mode'] === 'subscribe') {
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
        
        // Get page access token and settings
        $pageData = $this->db->getFanPage($pageId);
        
        if (!$pageData) {
            CommentsLogger::log("Page not found in database: {$pageId}", 'Error', true);
            return;
        }
        else{
            CommentsLogger::log("Got page data for {$pageId}", 'Trace');
        }
        
        $accessToken = $pageData['access_token'];
        $deleteMode = $pageData['delete_mode'];
        
        // Initialize Facebook API with page token
        $fb = new FacebookAPI($accessToken);
        
        // Extract necessary values
        $commentId = $changeValue['comment_id'];
        $commentMessage = mb_strtolower($changeValue['message']);
        $commentMessage = preg_replace("/[\r\n\t ]+/", " ", $commentMessage);


        $responses = $this->loadResponses($pageId);
        CommentsLogger::log("Got responses for {$pageId}", 'Trace');
        
        $responseFound = false;
        // Check if the comment contains any keywords and respond
        foreach ($responses as $response) {
            foreach ($response['keywords'] as $keyword) {
                CommentsLogger::log("Checking keyword:{$keyword}...", 'Trace');
                if (trim($keyword) === '*' ||
                    stripos($commentMessage, trim($keyword)) !== false) {
                    CommentsLogger::log("Keyword:{$keyword} found! Replying...", 'Trace');
                    $replied = $fb->reply_to_comment($commentId, $response['reply'], $response['image']);
                    $rText = $replied?'true':'false';
                    CommentsLogger::log("Reply posted: {$rText}", 'Trace');
                    $responseFound = true;
                    break 2;
                }
            }
        }

        // Handle comment based on working mode
        if (!$responseFound) {
            CommentsLogger::log("No matching keywords found. Comment will be hidden or deleted based on mode.", 'Trace');
            if ($deleteMode) {
                $deleted = $fb->delete_comment($commentId);
                $dText = $deleted?'true':'false';
                CommentsLogger::log("Comment deleted: {$dText}. Comment ID: {$commentId}", 'Info');
            } else {
                $hidden = $fb->hide_comment($commentId);
                $hText = $hidden?'true':'false';
                CommentsLogger::log("Comment hidden: {$hText}. Comment ID: {$commentId}", 'Info');
            }
        }
    }
}