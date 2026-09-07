<?php
/**
 * Central S3 storage layer for UTP_VCR.
 *
 * All user documents (IC copies, face captures, bank statements, declaration
 * forms, FYP forms, appointment letters, KYC6 reports and generated ZIPs) live
 * in the S3 bucket named by AWS_BUCKET instead of the local ./documents folder.
 *
 * Object keys are identical to the relative paths that used to be stored in the
 * database, e.g. "bank_statement/bank_statement_foo_20250101_120000.pdf".
 * Nothing about the database schema or the stored values had to change.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

// The .env may already have been loaded by config.php; createImmutable throws
// on a second load attempt only if it tries to overwrite, so guard on a key.
if (!isset($_ENV['AWS_BUCKET'])) {
    $s3Dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $s3Dotenv->safeLoad();
}

/** Top-level "folders" (key prefixes) the application is allowed to touch. */
const S3_ALLOWED_FOLDERS = [
    'ic_picture',
    'face_captures',
    'bank_statement',
    'declaration_form',
    'fyp_form_file',
    'appointment_file',
    'kyc6',
    'generated_zips',
];

/**
 * Shared S3 client instance.
 */
function s3_client()
{
    static $client = null;

    if ($client === null) {
        $client = new S3Client([
            'version'     => 'latest',
            'region'      => $_ENV['AWS_REGION'] ?? 'ap-southeast-1',
            'credentials' => [
                'key'    => $_ENV['AWS_ACCESS_KEY_ID'] ?? '',
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '',
            ],
        ]);
    }

    return $client;
}

function s3_bucket()
{
    return $_ENV['AWS_BUCKET'] ?? '';
}

/**
 * Normalise a value read from / written to the database into an S3 object key.
 *
 * The legacy code stored two different shapes: "bank_statement/file.pdf" for
 * most columns and a bare "file.pdf" for kyc6.KYC6_file. Both are accepted.
 *
 * @param string $dbValue Value from the DB (relative path or bare filename).
 * @param string $folder  Folder to assume when $dbValue has no folder part.
 * @return string The object key, or '' when the input is unusable.
 */
function s3_key($dbValue, $folder)
{
    $value = trim((string) $dbValue);
    if ($value === '') {
        return '';
    }

    $value = ltrim(str_replace('\\', '/', $value), '/');

    // Strip a leading "documents/" if some legacy row happens to carry it.
    if (strpos($value, 'documents/') === 0) {
        $value = substr($value, strlen('documents/'));
    }

    if (strpos($value, '..') !== false) {
        return '';
    }

    if (strpos($value, '/') === false) {
        $value = $folder . '/' . $value;
    }

    $topFolder = substr($value, 0, strpos($value, '/'));
    if (!in_array($topFolder, S3_ALLOWED_FOLDERS, true)) {
        return '';
    }

    return $value;
}

/**
 * Upload a file that is already on local disk (typically $_FILES[..]['tmp_name']).
 *
 * @return bool True on success.
 */
function s3_put_file($localPath, $key, $contentType = null)
{
    if ($key === '' || !is_readable($localPath)) {
        return false;
    }

    $args = [
        'Bucket'     => s3_bucket(),
        'Key'        => $key,
        'SourceFile' => $localPath,
    ];
    if (!empty($contentType)) {
        $args['ContentType'] = $contentType;
    }

    try {
        s3_client()->putObject($args);
        return true;
    } catch (S3Exception $e) {
        error_log('S3 put_file failed for ' . $key . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Upload raw bytes held in memory (generated PDFs, decoded base64 images, ...).
 *
 * @return bool True on success.
 */
function s3_put_data($key, $data, $contentType = 'application/octet-stream')
{
    if ($key === '') {
        return false;
    }

    try {
        s3_client()->putObject([
            'Bucket'      => s3_bucket(),
            'Key'         => $key,
            'Body'        => $data,
            'ContentType' => $contentType,
        ]);
        return true;
    } catch (S3Exception $e) {
        error_log('S3 put_data failed for ' . $key . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Does the object exist? (The S3 equivalent of file_exists().)
 */
function s3_exists($key)
{
    if ($key === '') {
        return false;
    }

    try {
        return s3_client()->doesObjectExist(s3_bucket(), $key);
    } catch (S3Exception $e) {
        error_log('S3 exists check failed for ' . $key . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete an object. (The S3 equivalent of unlink().)
 */
function s3_delete($key)
{
    if ($key === '') {
        return false;
    }

    try {
        s3_client()->deleteObject([
            'Bucket' => s3_bucket(),
            'Key'    => $key,
        ]);
        return true;
    } catch (S3Exception $e) {
        error_log('S3 delete failed for ' . $key . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Copy an object down to a local temp file so libraries that only accept a
 * filesystem path (ZipArchive, pdfparser, tesseract) can still work with it.
 *
 * @return string|false Absolute path of the temp file, or false on failure.
 */
function s3_download_temp($key)
{
    if ($key === '') {
        return false;
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'utpvcr_');
    if ($tmpPath === false) {
        return false;
    }

    // Keep the original extension so downstream sniffing still works.
    $extension = pathinfo($key, PATHINFO_EXTENSION);
    if ($extension !== '') {
        $withExt = $tmpPath . '.' . $extension;
        @rename($tmpPath, $withExt);
        $tmpPath = $withExt;
    }

    try {
        s3_client()->getObject([
            'Bucket' => s3_bucket(),
            'Key'    => $key,
            'SaveAs' => $tmpPath,
        ]);
        return $tmpPath;
    } catch (S3Exception $e) {
        error_log('S3 download failed for ' . $key . ': ' . $e->getMessage());
        @unlink($tmpPath);
        return false;
    }
}

/**
 * Build a short-lived pre-signed GET URL. The bucket stays private; the browser
 * is handed a temporary link instead of the object being proxied through PHP.
 *
 * @param string $key
 * @param bool   $forceDownload Send as an attachment instead of inline.
 * @param string $expires       Any strtotime()-compatible relative expression.
 * @return string|false
 */
function s3_presigned_url($key, $forceDownload = false, $expires = '+15 minutes')
{
    if ($key === '') {
        return false;
    }

    $args = [
        'Bucket' => s3_bucket(),
        'Key'    => $key,
    ];

    $disposition = $forceDownload ? 'attachment' : 'inline';
    $args['ResponseContentDisposition'] = $disposition . '; filename="' . basename($key) . '"';

    try {
        $command = s3_client()->getCommand('GetObject', $args);
        $request = s3_client()->createPresignedRequest($command, $expires);
        return (string) $request->getUri();
    } catch (S3Exception $e) {
        error_log('S3 presign failed for ' . $key . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Secret used to sign the document links handed to browsers, so a logged-in
 * user cannot enumerate other people's documents by editing the query string.
 */
function doc_link_secret()
{
    $secret = $_ENV['APP_SECRET'] ?? '';
    if ($secret === '') {
        // Fall back to a value that is already secret and always present.
        $secret = ($_ENV['AWS_SECRET_ACCESS_KEY'] ?? '') . '|' . ($_ENV['DB_PASS'] ?? '');
    }
    return $secret;
}

function doc_link_signature($key, $forceDownload)
{
    return hash_hmac('sha256', $key . '|' . ($forceDownload ? '1' : '0'), doc_link_secret());
}

/**
 * URL path of the application root, e.g. "/UTP_VCR/" under Laragon or "/" in
 * the Docker image. Used so document links work regardless of where the app is
 * mounted, without every caller having to count "../" segments.
 */
function app_base_url()
{
    static $base = null;

    if ($base === null) {
        $projectRoot = str_replace('\\', '/', realpath(__DIR__));
        $docRoot     = isset($_SERVER['DOCUMENT_ROOT'])
            ? str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT']))
            : '';

        if ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0) {
            $base = rtrim(substr($projectRoot, strlen($docRoot)), '/') . '/';
        } else {
            $base = '/';
        }
    }

    return $base;
}

/**
 * Build the browser-facing link for a stored document.
 *
 * Replaces the old "../../documents/<path>" hrefs. The returned URL points at
 * file.php, which checks the session and then redirects to a pre-signed S3 URL.
 *
 * @param string $dbValue       Value stored in the database.
 * @param string $folder        Folder to assume when $dbValue is a bare filename.
 * @param bool   $forceDownload Serve as a download rather than inline.
 * @return string Empty string when there is no usable file.
 */
function doc_url($dbValue, $folder, $forceDownload = false)
{
    $key = s3_key($dbValue, $folder);
    if ($key === '') {
        return '';
    }

    return app_base_url() . 'file.php?key=' . rawurlencode($key)
        . ($forceDownload ? '&download=1' : '')
        . '&sig=' . doc_link_signature($key, $forceDownload);
}
