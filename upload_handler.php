<?php
require_once __DIR__ . '/vendor/autoload.php';

// 1. Initialize Dotenv to read your keys securely
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

/**
 * Uploads a file directly to your AWS S3 bucket.
 * 
 * @param array $file The standard $_FILES['input_name'] object
 * @param string $folderName The target folder inside S3 (e.g. 'bank_statements')
 * @return string|false The public cloud URL on success, false on failure.
 */
function uploadFileToS3($file, $folderName = 'uploads') {
    // 2. Build the S3 client instance using your .env credentials
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $_ENV['AWS_REGION'],
        'credentials' => [
            'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        ]
    ]);

    $bucket = $_ENV['AWS_BUCKET'];
    
    // Generate a unique filename to prevent files with the same name overwriting each other
    $uniqueFileName = uniqid() . '_' . basename($file['name']);
    $s3SavePath = $folderName . '/' . $uniqueFileName;

    try {
        // 3. Stream the file directly from your local tmp folder up to the cloud
        $result = $s3->putObject([
            'Bucket'      => $bucket,
            'Key'         => $s3SavePath,
            'SourceFile'  => $file['tmp_name'],
            'ContentType' => $file['type'],
            'ACL'         => 'public-read' // Gives users permission to view/download the file via its link
        ]);

        // 4. Return the permanent web URL of the uploaded asset
        return $result['ObjectURL'];

    } catch (S3Exception $e) {
        // Error handling fallback
        error_log("AWS S3 Upload Error: " . $e->getMessage());
        return false;
    }
}
