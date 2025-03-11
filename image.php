<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

if (!isset($_GET['file']) || empty($_GET['file'])) {
    header('HTTP/1.0 400 Bad Request');
    exit('No file specified');
}

$filename = basename($_GET['file']); // Remove any directory components
$filepath = __DIR__ . '/uploads/' . $filename;

// Validate file exists and is within uploads directory
if (!file_exists($filepath) || !is_file($filepath) || dirname($filepath) !== __DIR__ . '/uploads') {
    header('HTTP/1.0 404 Not Found');
    exit('File not found');
}

// Get MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Validate MIME type
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($mime_type, $allowed_types)) {
    header('HTTP/1.0 403 Forbidden');
    exit('Invalid file type');
}

// Output image with correct headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
