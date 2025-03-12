<?php
require_once __DIR__.'/settings.php';
require_once __DIR__.'/logger.php';

class FacebookAPI {
    private $page_access_token;

    public function __construct(string $page_access_token) {
        $this->page_access_token = $page_access_token;
    }

    public function subscribe_to_feed(string $page_id): bool {
        $url = $page_id . '/subscribed_apps?subscribed_fields=feed';
        $response = json_decode($this->make_curl_request($url), true);
        
        if (!$response || !isset($response['success'])) {
            CommentsLogger::log("Failed to subscribe to page feed: " . json_encode($response), 'Error', true);
            return false;
        }
        
        return $response['success'];
    }

    public function hide_comment(string $comment_id): bool {
        $url = $comment_id;
        $data = ['is_hidden' => 'true'];
        $response = $this->make_curl_request($url, $data);
        return $response === 'true';
    }

    public function delete_comment(string $comment_id): bool {
        $url = $comment_id;
        $response = $this->make_curl_request($url, ['method' => 'delete']);
        return $response === 'true';
    }

    public function reply_to_comment(string $comment_id, string $message, ?string $image_path = null): bool {
        $url = $comment_id . '/comments';
        $data = ['message' => $message];

        // If image path is provided, attach it to the comment
        if ($image_path !== null) {
            $fullPath = __DIR__ . '/uploads/' . $image_path;
            if (file_exists($fullPath)) {
                // First upload the photo to get its ID
                $photoUrl = 'me/photos';
                $photoData = [
                    'published' => 'false',
                    'source' => new CURLFile($fullPath)
                ];
                
                $photoResponse = json_decode($this->make_curl_request($photoUrl, $photoData, true), true);
                
                if (isset($photoResponse['id'])) {
                    // Attach the uploaded photo to the comment
                    $data['attachment_id'] = $photoResponse['id'];
                } else {
                    CommentsLogger::log("Failed to upload photo: " . json_encode($photoResponse), 'Error', true);
                    return false;
                }
            } else {
                CommentsLogger::log("Image file not found: $fullPath", 'Error', true);
                return false;
            }
        }

        $response = $this->make_curl_request($url, $data);
        return !empty($response);
    }

    private function make_curl_request(string $url, ?array $data = null, bool $multipart = false): string {
        $baseUrl = "https://graph.facebook.com/v" . Settings::$fbApiVersion . ".0/";
        $finalUrl = $baseUrl . $url;
        if (is_null($data)) $data = [];
        $data['access_token'] = $this->page_access_token;
        
        $ch = curl_init($finalUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if ($multipart) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        if ($response === false) {
            $error_msg = curl_error($ch);
            unset($data['access_token']);
            $json_data = json_encode($data);
            CommentsLogger::log("Error sending $json_data to url: $url $error_msg", 'Error', true);
        }
        curl_close($ch);
        return $response;
    }
}