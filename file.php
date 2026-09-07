<?php
/**
 * Access-controlled gateway to the documents stored in S3.
 *
 * Replaces the old direct links into the ./documents folder. Every link is
 * produced by doc_url() in s3.php, which appends an HMAC over the object key,
 * so a signed-in user cannot swap the key for somebody else's document.
 *
 * Usage:  file.php?key=<object key>&sig=<hmac>[&download=1]
 */

session_start();
require_once __DIR__ . '/s3.php';

function deny($code, $message)
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit();
}

// A session is required: either an applicant or an administrator.
if (!isset($_SESSION['email']) && !isset($_SESSION['admin_id'])) {
    deny(403, 'You must be signed in to view this document.');
}

$rawKey        = isset($_GET['key']) ? (string) $_GET['key'] : '';
$signature     = isset($_GET['sig']) ? (string) $_GET['sig'] : '';
$forceDownload = isset($_GET['download']) && $_GET['download'] !== '0';

// s3_key() also rejects traversal attempts and folders outside the whitelist.
$key = s3_key($rawKey, '');
if ($key === '') {
    deny(400, 'Invalid document reference.');
}

if (!hash_equals(doc_link_signature($key, $forceDownload), $signature)) {
    deny(403, 'This document link is not valid.');
}

$url = s3_presigned_url($key, $forceDownload);
if ($url === false) {
    deny(500, 'The document could not be retrieved from storage.');
}

header('Location: ' . $url, true, 302);
exit();
