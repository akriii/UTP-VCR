<?php
/**
 * CLI health-check for the S3 configuration.
 *
 *   php tools/s3_check.php
 *
 * Verifies the credentials in .env, that AWS_BUCKET exists and that
 * AWS_REGION matches the bucket's real region, then does a round-trip
 * put/get/delete so you know uploads will actually work.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only be run from the command line.\n");
}

// phpdotenv 4 is noisy on PHP 8.4; keep the report readable.
error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/../s3.php';

use Aws\Exception\AwsException;

$bucket = s3_bucket();
$region = $_ENV['AWS_REGION'] ?? '(unset)';

echo "Bucket : {$bucket}\n";
echo "Region : {$region}\n";
echo str_repeat('-', 60) . "\n";

if ($bucket === '' || empty($_ENV['AWS_ACCESS_KEY_ID'])) {
    exit("FAIL: AWS_BUCKET / AWS_ACCESS_KEY_ID are not set in .env\n");
}

// --- 1. Confirm the bucket exists and that we are talking to the right region
try {
    $location = s3_client()->getBucketLocation(['Bucket' => $bucket]);
    $actualRegion = $location['LocationConstraint'] ?: 'us-east-1';

    if ($actualRegion === $region) {
        echo "OK   : bucket found, region matches.\n";
    } else {
        echo "WARN : bucket actually lives in '{$actualRegion}'.\n";
        echo "       Set AWS_REGION={$actualRegion} in .env.\n";
    }
} catch (AwsException $e) {
    echo "FAIL : cannot reach the bucket - " . $e->getAwsErrorCode() . "\n";
    echo "       " . $e->getAwsErrorMessage() . "\n";
    exit(1);
}

// --- 2. Round-trip a throwaway object
$testKey = 'generated_zips/.s3_check';

if (!s3_put_data($testKey, 'ok', 'text/plain')) {
    exit("FAIL : PutObject was denied. Check the IAM policy for s3:PutObject.\n");
}
echo "OK   : PutObject\n";

if (!s3_exists($testKey)) {
    exit("FAIL : HeadObject was denied. Check the IAM policy for s3:GetObject.\n");
}
echo "OK   : HeadObject\n";

$url = s3_presigned_url($testKey);
echo "OK   : pre-signed URL generated\n";

if (!s3_delete($testKey)) {
    echo "WARN : DeleteObject was denied. Deleting an application will leave orphaned files.\n";
} else {
    echo "OK   : DeleteObject\n";
}

echo str_repeat('-', 60) . "\n";
echo "S3 storage is configured correctly.\n";
