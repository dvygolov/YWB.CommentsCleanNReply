<?php
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/db.php';

// Initialize settings and database
$db = new CommentsDatabase();

// Test data
$testPage = [
    'id' => '123456789',
    'access_token' => 'test_access_token_123'
];

$testRule = [
    'page_id' => $testPage['id'],
    'trigger_words' => 'hello,hi,hey',
    'reply_text' => 'Thank you for your message! We will respond shortly.'
];

// Test fan page operations
echo "<h2>Testing Fan Page Operations</h2>";

echo "<h3>Adding Fan Page</h3>";
$result = $db->addFanPage($testPage['id'], $testPage['access_token'],false);
echo "Add fan page result: " . ($result ? "Success" : "Failed") . "<br>";

echo "<h3>Getting Fan Pages</h3>";
$pages = $db->getFanPages();
echo "Fan pages in database:<br>";
echo "<pre>" . print_r($pages, true) . "</pre>";

echo "<h3>Getting Fan Page Token</h3>";
$token = $db->getFanPageToken($testPage['id']);
echo "Token for page {$testPage['id']}: " . $token . "<br>";

// Test reply rules operations
echo "<h2>Testing Reply Rules Operations</h2>";

echo "<h3>Adding Reply Rule</h3>";
$result = $db->addReplyRule($testRule['page_id'], $testRule['trigger_words'], $testRule['reply_text']);
echo "Add reply rule result: " . ($result ? "Success" : "Failed") . "<br>";

echo "<h3>Getting Reply Rules</h3>";
$rules = $db->getReplyRules();
echo "All reply rules:<br>";
echo "<pre>" . print_r($rules, true) . "</pre>";

echo "<h3>Testing Rule Matching</h3>";
$testComment = "Hi there, I have a question!";
$matches = $db->findMatchingRules($testPage['id'], $testComment);
echo "Matching rules for comment '{$testComment}':<br>";
echo "<pre>" . print_r($matches, true) . "</pre>";

// Clean up test data
echo "<h2>Cleanup</h2>";
$db->removeFanPage($testPage['id']);
echo "Removed test fan page and its rules.<br>";

echo "<br><a href='adminlogin.php'>Go to Admin Login</a>";
