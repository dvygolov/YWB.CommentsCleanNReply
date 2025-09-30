<?php
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/logger.php';
class CommentsDatabase {
    private $db;

    public function __construct() {
        try
        {
            $this->db = new SQLite3(Settings::$dbFilePath);
            $this->createTables();
        }
        catch (Exception $e) {
            CommentsLogger::log("An error occurred: " . $e->getMessage(), 'Error', true);
        }
    }

    private function createTables() {
        try {
            $this->db->exec("PRAGMA foreign_keys = ON");
            
            // Table for storing fan pages and their access tokens
            $this->db->exec("CREATE TABLE IF NOT EXISTS fan_pages (
                id TEXT PRIMARY KEY,
                access_token TEXT NOT NULL,
                page_name TEXT NOT NULL,
                page_avatar TEXT,
                delete_mode INTEGER DEFAULT 0,
                cleaner_enabled INTEGER DEFAULT 0
            )");
            
            // Add cleaner_enabled column if it doesn't exist (for existing databases)
            $result = $this->db->query("PRAGMA table_info(fan_pages)");
            $columns = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $row['name'];
            }
            if (!in_array('cleaner_enabled', $columns)) {
                $this->db->exec("ALTER TABLE fan_pages ADD COLUMN cleaner_enabled INTEGER DEFAULT 0");
            }

            // Table for storing comment reply rules
            $this->db->exec("CREATE TABLE IF NOT EXISTS reply_rules (
                rule_id INTEGER PRIMARY KEY AUTOINCREMENT,
                page_id TEXT NOT NULL,
                trigger_words TEXT NOT NULL,
                reply_text TEXT NOT NULL,
                image_path TEXT,
                FOREIGN KEY (page_id) REFERENCES fan_pages(id) ON DELETE CASCADE
            )");
        } catch (Exception $e) {
            CommentsLogger::log("Error creating tables: " . $e->getMessage(), 'Error', true);
        }
    }


    private function prepare(string $query):SQLite3Stmt
    {
        try {
            $stmt = $this->db->prepare($query);
            if ($stmt===false) CommentsLogger::log($this->db->lastErrorMsg(), 'Error', true);
            return $stmt;
        } catch (Exception $e) {
            CommentsLogger::log("Error preparing statement: " . $e->getMessage(), 'Error', true);
            throw $e; // Re-throw as this is a utility method and callers should handle the error
        }
    }

    // Fan Page Methods
    public function addFanPage(string $pageId, string $accessToken, string $pageName, string $pageAvatar, int $deleteMode = 0): bool {
        try {
            $stmt = $this->prepare("INSERT OR REPLACE INTO fan_pages (id, access_token, page_name, page_avatar, delete_mode, cleaner_enabled) VALUES (:id, :token, :name, :avatar, :mode, 0)");
            $stmt->bindValue(':id', $pageId, SQLITE3_TEXT);
            $stmt->bindValue(':token', $accessToken, SQLITE3_TEXT);
            $stmt->bindValue(':name', $pageName, SQLITE3_TEXT);
            $stmt->bindValue(':avatar', $pageAvatar, SQLITE3_TEXT);
            $stmt->bindValue(':mode', $deleteMode, SQLITE3_INTEGER);
            return $stmt->execute() !== false;
        } catch (Exception $e) {
            CommentsLogger::log("Error adding fan page: " . $e->getMessage(), 'Error', true);
            return false;
        }
    }

    public function removeFanPage(string $pageId): bool {
        try {
            $stmt = $this->prepare("DELETE FROM fan_pages WHERE id = :id");
            $stmt->bindValue(':id', $pageId, SQLITE3_TEXT);
            return $stmt->execute() !== false;
        } catch (Exception $e) {
            CommentsLogger::log("Error removing fan page: " . $e->getMessage(), 'Error', true);
            return false;
        }
    }

    public function getFanPages(): array {
        try {
            $result = $this->db->query("SELECT id, access_token, page_name, page_avatar, delete_mode, cleaner_enabled FROM fan_pages");
            $pages = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $row['delete_mode'] = (bool)$row['delete_mode'];
                $row['cleaner_enabled'] = (bool)$row['cleaner_enabled'];
                $pages[] = $row;
            }
            return $pages;
        } catch (Exception $e) {
            CommentsLogger::log("Error getting fan pages: " . $e->getMessage(), 'Error', true);
            return [];
        }
    }

    public function getFanPage(string $pageId): ?array {
        try {
            $stmt = $this->prepare("SELECT id,access_token,page_name,page_avatar,delete_mode,cleaner_enabled FROM fan_pages WHERE id = :id");
            $stmt->bindValue(':id', $pageId, SQLITE3_TEXT);
            $result = $stmt->execute();
            if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                return $row;
            }
            return null;
        } catch (Exception $e) {
            CommentsLogger::log("Error getting fan page: " . $e->getMessage(), 'Error', true);
            return null;
        }
    }

    public function updateFanPageMode(string $pageId, bool $deleteMode): bool {
        try {
            $stmt = $this->prepare("UPDATE fan_pages SET delete_mode = :mode WHERE id = :id");
            $stmt->bindValue(':id', $pageId, SQLITE3_TEXT);
            $stmt->bindValue(':mode', $deleteMode ? 1 : 0, SQLITE3_INTEGER);
            return $stmt->execute() !== false;
        } catch (Exception $e) {
            CommentsLogger::log("Error updating fan page mode: " . $e->getMessage(), 'Error', true);
            return false;
        }
    }

    public function updateCleanerStatus(string $pageId, bool $enabled): bool {
        try {
            $stmt = $this->prepare("UPDATE fan_pages SET cleaner_enabled = :enabled WHERE id = :id");
            $stmt->bindValue(':id', $pageId, SQLITE3_TEXT);
            $stmt->bindValue(':enabled', $enabled ? 1 : 0, SQLITE3_INTEGER);
            return $stmt->execute() !== false;
        } catch (Exception $e) {
            CommentsLogger::log("Error updating cleaner status: " . $e->getMessage(), 'Error', true);
            return false;
        }
    }

    // Reply Rules Methods
    public function addReplyRule(string $pageId, string $triggerWords, string $replyText, $imagePath = null): bool {
        try {
            $stmt = $this->prepare('INSERT INTO reply_rules (page_id, trigger_words, reply_text, image_path) VALUES (:page_id, :trigger_words, :reply_text, :image_path)');
            $stmt->bindValue(':page_id', $pageId, SQLITE3_TEXT);
            $stmt->bindValue(':trigger_words', $triggerWords, SQLITE3_TEXT);
            $stmt->bindValue(':reply_text', $replyText, SQLITE3_TEXT);
            $stmt->bindValue(':image_path', $imagePath, SQLITE3_TEXT);
            return $stmt->execute() !== false;
        } catch (Exception $e) {
            CommentsLogger::log("Error adding reply rule: " . $e->getMessage(), 'Error', true);
            return false;
        }
    }

    public function updateReplyRule(int $ruleId, string $triggerWords, string $replyText, $imagePath = null): bool {
        try {
            $stmt = $this->prepare('UPDATE reply_rules SET trigger_words = :trigger_words, reply_text = :reply_text, image_path = :image_path WHERE rule_id = :rule_id');
            $stmt->bindValue(':rule_id', $ruleId, SQLITE3_INTEGER);
            $stmt->bindValue(':trigger_words', $triggerWords, SQLITE3_TEXT);
            $stmt->bindValue(':reply_text', $replyText, SQLITE3_TEXT);
            $stmt->bindValue(':image_path', $imagePath, SQLITE3_TEXT);
            return $stmt->execute() !== false;
        } catch (Exception $e) {
            CommentsLogger::log("Error updating reply rule: " . $e->getMessage(), 'Error', true);
            return false;
        }
    }

    public function removeReplyRule(int $ruleId): bool {
        try {
            $stmt = $this->prepare("DELETE FROM reply_rules WHERE rule_id = :id");
            $stmt->bindValue(':id', $ruleId, SQLITE3_INTEGER);
            return $stmt->execute() !== false;
        } catch (Exception $e) {
            CommentsLogger::log("Error removing reply rule: " . $e->getMessage(), 'Error', true);
            return false;
        }
    }

    public function getReplyRules(?string $pageId = null): array {
        try {
            if ($pageId) {
                $stmt = $this->prepare("SELECT * FROM reply_rules WHERE page_id = :page_id");
                $stmt->bindValue(':page_id', $pageId, SQLITE3_TEXT);
                $result = $stmt->execute();
            } else {
                $result = $this->db->query("SELECT * FROM reply_rules");
            }
            
            $rules = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $rules[] = $row;
            }
            return $rules;
        } catch (Exception $e) {
            CommentsLogger::log("Error getting reply rules: " . $e->getMessage(), 'Error', true);
            return [];
        }
    }

    public function findMatchingRules(string $pageId, string $comment): array {
        try {
            $rules = $this->getReplyRules($pageId);
            $matches = [];
            
            foreach ($rules as $rule) {
                $triggerWords = explode(',', $rule['trigger_words']);
                foreach ($triggerWords as $word) {
                    $word = trim($word);
                    if (!empty($word) && stripos($comment, $word) !== false) {
                        $matches[] = $rule;
                        break;
                    }
                }
            }
            
            return $matches;
        } catch (Exception $e) {
            CommentsLogger::log("Error finding matching rules: " . $e->getMessage(), 'Error', true);
            return [];
        }
    }
}

?>