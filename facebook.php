<?php
require_once __DIR__.'/settings.php';
require_once __DIR__.'/logger.php';

class FacebookAPI {
    private $page_access_token;

    public function __construct(string $page_access_token) {
        $this->page_access_token = $page_access_token;
    }

    public function get_page_info(string $page_id): array {
        $url = $page_id . '?fields=name,picture.type(large)';
        $response = $this->make_curl_request($url, null, false, 'GET');
        
        $this->check_for_errors($response, 'get_page_info');
        
        return [
            'name' => $response['name'],
            'avatar' => $response['picture']['data']['url']
        ];
    }

    public function validate_token(): array {
        // First check if token works
        $url = 'me';
        $response = $this->make_curl_request($url, null, false, 'GET');
        
        if (isset($response['error'])) {
            return [
                'valid' => false,
                'error' => $response['error']['message'] ?? 'Unknown error'
            ];
        }
        
        // Get token expiration info
        $debugUrl = 'debug_token?input_token=' . $this->page_access_token;
        $debugResponse = $this->make_curl_request($debugUrl, null, false, 'GET');
        
        $expiresAt = null;
        if (isset($debugResponse['data']['expires_at']) && $debugResponse['data']['expires_at'] > 0) {
            $expiresAt = $debugResponse['data']['expires_at'];
        } elseif (isset($debugResponse['data']['data_access_expires_at']) && $debugResponse['data']['data_access_expires_at'] > 0) {
            $expiresAt = $debugResponse['data']['data_access_expires_at'];
        }
        
        return [
            'valid' => true,
            'id' => $response['id'] ?? null,
            'name' => $response['name'] ?? null,
            'expires_at' => $expiresAt
        ];
    }

    public function get_user_pages(): array {
        $url = 'me/accounts?fields=id,name,access_token,picture.type(large)&summary=total_count&limit=300';
        $response = $this->make_curl_request($url, null, false, 'GET');
        
        if (isset($response['error'])) {
            $this->check_for_errors($response, 'get_user_pages');
            return [];
        }
        
        if (!isset($response['data']) || empty($response['data'])) {
            return [];
        }
        
        $pages = [];
        foreach ($response['data'] as $page) {
            $pages[] = [
                'id' => $page['id'],
                'name' => $page['name'],
                'access_token' => $page['access_token'],
                'avatar' => $page['picture']['data']['url'] ?? ''
            ];
        }
        
        return $pages;
    }

    public function subscribe_to_feed(string $page_id): bool {
        $url = $page_id . '/subscribed_apps?subscribed_fields=feed';
        $response = $this->make_curl_request($url);
        $this->check_for_errors($response, 'subscribe_to_feed');
        return isset($response['success']) && $response['success'] === true;
    }
    
    public function unsubscribe_from_feed(string $page_id): bool {
        $url = $page_id . '/subscribed_apps';
        // Facebook requires subscribed_fields parameter even when unsubscribing
        // Pass an empty array as subscribed_fields to unsubscribe from all fields
        $response = $this->make_curl_request($url, ['subscribed_fields' => 'feed'], false, 'DELETE');
        $this->check_for_errors($response, 'unsubscribe_from_feed');
        return isset($response['success']) && $response['success'] === true;
    }

    public function hide_comment(string $comment_id): bool {
        $url = $comment_id;
        $data = ['is_hidden' => 'true'];
        $response = $this->make_curl_request($url, $data);
        $this->check_for_errors($response, 'hide_comment');
        return isset($response['success']) && $response['success'] === true;
    }

    public function delete_comment(string $comment_id): bool {
        $url = $comment_id;
        $response = $this->make_curl_request($url, null, false, 'DELETE');
        $this->check_for_errors($response, 'delete_comment');
        return isset($response['success']) && $response['success'] === true;
    }

    public function reply_to_comment(string $comment_id, string $message, ?string $image_path = null): bool {
        $url = $comment_id . '/comments';
        $data = ['message' => $message];
        
        // Handle image attachment if provided
        if (!is_null($image_path) && !empty($image_path)) {
            $fullPath = __DIR__ . '/images/' . $image_path;
            if (file_exists($fullPath)) {
                // Upload the photo first
                $photoUrl = 'me/photos';
                $photoData = [
                    'published' => 'false',
                    'source' => new CURLFile($fullPath)
                ];
                $photoResponse = $this->make_curl_request($photoUrl, $photoData, true);
                
                $this->check_for_errors($photoResponse, 'reply_to_comment');
                
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
        
        $this->check_for_errors($response, 'reply_to_comment');
        
        return !empty($response);
    }

    public function get_app_info(): array {
        $url = 'app';
        $response = $this->make_curl_request($url, null, false, 'GET');
        
        if (isset($response['error'])) {
            $this->check_for_errors($response, 'get_app_info');
            return [];
        }
        
        return [
            'id' => $response['id'] ?? null,
            'name' => $response['name'] ?? null
        ];
    }

    public function subscribe_app(string $app_id, string $callback_url, string $verify_token = 'my_verify_token'): bool {
        $url = $app_id . '/subscriptions?debug=true';
        $data = [
            'object' => 'page',
            'callback_url' => $callback_url,
            'fields' => ['feed'],
            'verify_token' => $verify_token
        ];
        $response = $this->make_curl_request($url, $data);
        
        if (isset($response['error'])) {
            $this->check_for_errors($response, 'subscribe_app');
            return false;
        }
        
        return isset($response['success']) && $response['success'] === true;
    }

    public function get_app_subscriptions(string $app_id): array {
        $url = $app_id . '/subscriptions';
        $response = $this->make_curl_request($url, null, false, 'GET');
        
        if (isset($response['error'])) {
            $this->check_for_errors($response, 'get_app_subscriptions');
            return [];
        }
        
        return $response['data'] ?? [];
    }

    public function unsubscribe_app(string $app_id, string $object = 'page'): bool {
        $url = $app_id . '/subscriptions';
        $data = ['object' => $object];
        $response = $this->make_curl_request($url, $data, false, 'DELETE');
        
        if (isset($response['error'])) {
            $this->check_for_errors($response, 'unsubscribe_app');
            return false;
        }
        
        return isset($response['success']) && $response['success'] === true;
    }

    public function clean_page_content(string $page_id): array {
        $deletedPosts = 0;
        $deletedPhotos = 0;
        $deletedVideos = 0;
        $errorCount = 0;
        $errors = [];
        
        CommentsLogger::log("Starting to clean content for page: $page_id", 'Info');
        
        // Step 1: Delete all posts with pagination
        $nextPageURL = $page_id . '/posts';
        while ($nextPageURL) {
            $response = $this->make_curl_request($nextPageURL, null, false, 'GET');
            
            if (isset($response['error'])) {
                $this->check_for_errors($response, 'clean_page_content:fetch_posts');
                $errors[] = "Failed to fetch posts: " . ($response['error']['message'] ?? 'Unknown error');
                break;
            }
            
            if (!isset($response['data']) || empty($response['data'])) {
                CommentsLogger::log("No more posts found", 'Info');
                break;
            }
            
            CommentsLogger::log("Got " . count($response['data']) . " posts to delete", 'Info');
            
            foreach ($response['data'] as $post) {
                $postId = $post['id'];
                CommentsLogger::log("Deleting post: $postId", 'Info');
                
                $deleteResponse = $this->make_curl_request($postId, null, false, 'DELETE');
                
                if (isset($deleteResponse['error'])) {
                    $this->check_for_errors($deleteResponse, "clean_page_content:delete_post:$postId");
                    $errorCount++;
                    $errors[] = "Post $postId: " . ($deleteResponse['error']['message'] ?? 'Unknown error');
                } elseif (isset($deleteResponse['success']) && $deleteResponse['success'] === true) {
                    $deletedPosts++;
                } else {
                    $errorCount++;
                    $errors[] = "Post $postId: Unexpected response";
                    CommentsLogger::log("Unexpected response deleting post $postId", 'Error');
                }
            }
            
            // Check for next page
            $nextPageURL = isset($response['paging']['next']) ? $response['paging']['next'] : null;
        }
        
        // Step 2: Delete all uploaded photos
        $photosResponse = $this->make_curl_request($page_id . '/photos?type=uploaded', null, false, 'GET');
        
        if (isset($photosResponse['error'])) {
            $this->check_for_errors($photosResponse, 'clean_page_content:fetch_photos');
            $errors[] = "Failed to fetch photos: " . ($photosResponse['error']['message'] ?? 'Unknown error');
        } elseif (isset($photosResponse['data']) && !empty($photosResponse['data'])) {
            CommentsLogger::log("Got " . count($photosResponse['data']) . " photos to delete", 'Info');
            
            foreach ($photosResponse['data'] as $photo) {
                $photoId = $photo['id'];
                CommentsLogger::log("Deleting photo: $photoId", 'Info');
                
                $deleteResponse = $this->make_curl_request($photoId, null, false, 'DELETE');
                
                if (isset($deleteResponse['error'])) {
                    $this->check_for_errors($deleteResponse, "clean_page_content:delete_photo:$photoId");
                    $errorCount++;
                    $errors[] = "Photo $photoId: " . ($deleteResponse['error']['message'] ?? 'Unknown error');
                } elseif (isset($deleteResponse['success']) && $deleteResponse['success'] === true) {
                    $deletedPhotos++;
                } else {
                    $errorCount++;
                    $errors[] = "Photo $photoId: Unexpected response";
                    CommentsLogger::log("Unexpected response deleting photo $photoId", 'Error');
                }
            }
        } else {
            CommentsLogger::log("No photos found", 'Info');
        }
        
        // Step 3: Delete all videos
        $videosResponse = $this->make_curl_request($page_id . '/videos', null, false, 'GET');
        
        if (isset($videosResponse['error'])) {
            $this->check_for_errors($videosResponse, 'clean_page_content:fetch_videos');
            $errors[] = "Failed to fetch videos: " . ($videosResponse['error']['message'] ?? 'Unknown error');
        } elseif (isset($videosResponse['data']) && !empty($videosResponse['data'])) {
            CommentsLogger::log("Got " . count($videosResponse['data']) . " videos to delete", 'Info');
            
            foreach ($videosResponse['data'] as $video) {
                $videoId = $video['id'];
                CommentsLogger::log("Deleting video: $videoId", 'Info');
                
                $deleteResponse = $this->make_curl_request($videoId, null, false, 'DELETE');
                
                if (isset($deleteResponse['error'])) {
                    $this->check_for_errors($deleteResponse, "clean_page_content:delete_video:$videoId");
                    $errorCount++;
                    $errors[] = "Video $videoId: " . ($deleteResponse['error']['message'] ?? 'Unknown error');
                } elseif (isset($deleteResponse['success']) && $deleteResponse['success'] === true) {
                    $deletedVideos++;
                } else {
                    $errorCount++;
                    $errors[] = "Video $videoId: Unexpected response";
                    CommentsLogger::log("Unexpected response deleting video $videoId", 'Error');
                }
            }
        } else {
            CommentsLogger::log("No videos found", 'Info');
        }
        
        $totalDeleted = $deletedPosts + $deletedPhotos + $deletedVideos;
        $message = "Deleted $deletedPosts posts, $deletedPhotos photos, $deletedVideos videos";
        if ($errorCount > 0) {
            $message .= " ($errorCount errors)";
        }
        
        CommentsLogger::log("Content cleaning completed: $message", 'Info');
        
        return [
            'success' => true,
            'deleted' => $totalDeleted,
            'deleted_posts' => $deletedPosts,
            'deleted_photos' => $deletedPhotos,
            'deleted_videos' => $deletedVideos,
            'errors' => $errorCount,
            'message' => $message,
            'error_details' => $errors
        ];
    }

    private function check_for_errors($response, string $methodName = '', $die=false):void
    {
        if (!isset($response['error'])) return;
        $errorMessage = $response['error']['message'] ?? 'Unknown error';
        $errorType = $response['error']['type'] ?? 'Unknown type';
        $errorCode = $response['error']['code'] ?? 'Unknown code';
        $errorTitle = $response['error']['error_user_title'] ?? '';
        $errorMsg = $response['error']['error_user_msg'] ?? '';
        
        $logMessage = "Facebook API error in method '$methodName': $errorMessage (Type: $errorType, Code: $errorCode)";
        if (!empty($errorTitle)) {
            $logMessage .= " | Title: $errorTitle";
        }
        if (!empty($errorMsg)) {
            $logMessage .= " | User Message: $errorMsg";
        }
        
        CommentsLogger::log($logMessage, 'Error', $die);
    }

    private function make_curl_request(string $url, ?array $data = null, bool $multipart = false, string $method = 'POST'): array {
        $baseUrl = "https://graph.facebook.com/v" . Settings::$fbApiVersion . ".0/";
        $finalUrl = $baseUrl . $url;
        if (is_null($data)) $data = [];
        $data['access_token'] = $this->page_access_token;
        
        $ch = curl_init($finalUrl);
        
        // For GET requests, append parameters to URL
        if ($method === 'GET') {
            $finalUrl .= (strpos($finalUrl, '?') !== false ? '&' : '?') . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $finalUrl);
        } elseif ($method === 'DELETE') {
            // For DELETE requests
            $finalUrl .= (strpos($finalUrl, '?') !== false ? '&' : '?') . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $finalUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } else {
            // For POST requests
            curl_setopt($ch, CURLOPT_POST, true);
            
            if ($multipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response === false) {
            $error_msg = curl_error($ch);
            unset($data['access_token']);
            $json_data = json_encode($data);
            CommentsLogger::log("Error sending $method request with $json_data to url: $url $error_msg", 'Error', true);
            return ['error' => ['message' => $error_msg, 'type' => 'CurlError']];
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonError = json_last_error_msg();
            CommentsLogger::log("JSON decode error for $url: $jsonError. Response: " . substr($response, 0, 500), 'Error');
            return ['error' => ['message' => "JSON decode error: $jsonError", 'type' => 'JsonError']];
        }
        
        return $decoded;
    }
}