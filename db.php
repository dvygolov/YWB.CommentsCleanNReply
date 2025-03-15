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
        $this->db->exec("PRAGMA foreign_keys = ON");
        
        // Table for storing fan pages and their access tokens
        $this->db->exec("CREATE TABLE IF NOT EXISTS fan_pages (
            id TEXT PRIMARY KEY,
            access_token TEXT NOT NULL,
            delete_mode INTEGER DEFAULT 0
        )");

        // Table for storing comment reply rules
        $this->db->exec("CREATE TABLE IF NOT EXISTS reply_rules (
            rule_id INTEGER PRIMARY KEY AUTOINCREMENT,
            page_id TEXT NOT NULL,
            trigger_words TEXT NOT NULL,
            reply_text TEXT NOT NULL,
            image_path TEXT,
            FOREIGN KEY (page_id) REFERENCES fan_pages(id) ON DELETE CASCADE
        )");
    }


    private function prepare(string $query):SQLite3Stmt
    {
        $stmt = $this->db->prepare($query);
        if ($stmt===false) CommentsLogger::log($this->db->lastErrorMsg(), 'Error', true);
        return $stmt;
    }

    // Fan Page Methods
    public function addFanPage(string $pageId, string $accessToken, int $deleteMode): bool {
        $stmt = $this->prepare("INSERT OR REPLACE INTO fan_pages (id, access_token, delete_mode) VALUES (:id, :token, :mode)");
        $stmt->bindValue(':id', $pageId, SQLITE3_TEXT);
        $stmt->bindValue(':token', $accessToken, SQLITE3_TEXT);
        $stmt->bindValue(':mode', $deleteMode, SQLITE3_INTEGER);
        return $stmt->execute() !== false;
    }

    public function removeFanPage(string $pageId): bool {
        $stmt = $this->prepare("DELETE FROM fan_pages WHERE id = :id");
        $stmt->bindValue(':id', $pageId, SQLITE3_TEXT);
        return $stmt->execute() !== false;
    }

    public function getFanPages(): array {
        $result = $this->db->query("SELECT id, access_token, delete_mode FROM fan_pages");
        $pages = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['delete_mode'] = (bool)$row['delete_mode'];
            $pages[] = $row;
        }
        return $pages;
    }

    public function getFanPage(string $pageId): ?array {
        $stmt = $this->prepare("SELECT id,access_token,delete_mode FROM fan_pages WHERE id = :id");
        $stmt->bindValue(':id', $pageId, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            return $row;
        }
        return null;
    }

    public function updateFanPageMode(string $pageId, bool $deleteMode): bool {
        $stmt = $this->prepare("UPDATE fan_pages SET delete_mode = :mode WHERE id = :id");
        $stmt->bindValue(':id', $pageId, SQLITE3_TEXT);
        $stmt->bindValue(':mode', $deleteMode ? 1 : 0, SQLITE3_INTEGER);
        return $stmt->execute() !== false;
    }

    // Reply Rules Methods
    public function addReplyRule(string $pageId, string $triggerWords, string $replyText, $imagePath = null): bool {
        $stmt = $this->prepare('INSERT INTO reply_rules (page_id, trigger_words, reply_text, image_path) VALUES (:page_id, :trigger_words, :reply_text, :image_path)');
        $stmt->bindValue(':page_id', $pageId, SQLITE3_TEXT);
        $stmt->bindValue(':trigger_words', $triggerWords, SQLITE3_TEXT);
        $stmt->bindValue(':reply_text', $replyText, SQLITE3_TEXT);
        $stmt->bindValue(':image_path', $imagePath, SQLITE3_TEXT);
        return $stmt->execute() !== false;
    }

    public function updateReplyRule(int $ruleId, string $triggerWords, string $replyText, $imagePath = null): bool {
        $stmt = $this->prepare('UPDATE reply_rules SET trigger_words = :trigger_words, reply_text = :reply_text, image_path = :image_path WHERE rule_id = :rule_id');
        $stmt->bindValue(':rule_id', $ruleId, SQLITE3_INTEGER);
        $stmt->bindValue(':trigger_words', $triggerWords, SQLITE3_TEXT);
        $stmt->bindValue(':reply_text', $replyText, SQLITE3_TEXT);
        $stmt->bindValue(':image_path', $imagePath, SQLITE3_TEXT);
        return $stmt->execute() !== false;
    }

    public function removeReplyRule(int $ruleId): bool {
        $stmt = $this->prepare("DELETE FROM reply_rules WHERE rule_id = :id");
        $stmt->bindValue(':id', $ruleId, SQLITE3_INTEGER);
        return $stmt->execute() !== false;
    }

    public function getReplyRules(?string $pageId = null): array {
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
    }

    public function findMatchingRules(string $pageId, string $comment): array {
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
    }
}

?>