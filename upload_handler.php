<?php
/**
 * Backwards-compatible wrapper around the S3 storage layer in s3.php.
 *
 * New code should call s3_put_file()/s3_key()/doc_url() directly.
 */

require_once __DIR__ . '/s3.php';

/**
 * Uploads a file to the S3 bucket.
 *
 * @param array  $file       The standard $_FILES['input_name'] array.
 * @param string $folderName Target folder inside the bucket (e.g. 'bank_statement').
 * @return string|false The stored object key on success, false on failure.
 */
function uploadFileToS3($file, $folderName = 'uploads')
{
    if (empty($file['tmp_name']) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $key = s3_key(uniqid() . '_' . basename($file['name']), $folderName);
    if ($key === '') {
        return false;
    }

    return s3_put_file($file['tmp_name'], $key, $file['type'] ?? null) ? $key : false;
}
