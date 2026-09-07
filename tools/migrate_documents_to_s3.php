<?php
/**
 * One-time migration of the legacy ./documents tree into the S3 bucket.
 *
 *   php tools/migrate_documents_to_s3.php --dry-run   # list what would be sent
 *   php tools/migrate_documents_to_s3.php             # actually upload
 *
 * Object keys mirror the folder layout exactly, e.g.
 *   documents/bank_statement/foo.pdf  ->  bank_statement/foo.pdf
 * which is precisely what the database rows already point at, so no data
 * migration is needed alongside this.
 *
 * Files that are already in the bucket are skipped, so it is safe to re-run.
 * Nothing is deleted locally - remove ./documents yourself once you are happy.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only be run from the command line.\n");
}

// phpdotenv 4 is noisy on PHP 8.4; keep the output readable.
error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/../s3.php';

$dryRun    = in_array('--dry-run', $argv, true);
$sourceDir = realpath(__DIR__ . '/../documents');

if ($sourceDir === false) {
    exit("Nothing to do: ./documents does not exist.\n");
}

echo ($dryRun ? "DRY RUN - " : "") . "Migrating {$sourceDir} -> s3://" . s3_bucket() . "\n";
echo str_repeat('-', 60) . "\n";

$uploaded = $skipped = $failed = 0;

foreach (S3_ALLOWED_FOLDERS as $folder) {
    $folderPath = $sourceDir . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($folderPath)) {
        continue;
    }

    foreach (new DirectoryIterator($folderPath) as $entry) {
        // .gitkeep only existed to keep the empty folders in git; S3 has no
        // concept of an empty folder, so there is nothing to carry over.
        if ($entry->isDot() || !$entry->isFile() || $entry->getFilename()[0] === '.') {
            continue;
        }

        $key = $folder . '/' . $entry->getFilename();

        if (s3_exists($key)) {
            echo "skip   {$key}\n";
            $skipped++;
            continue;
        }

        if ($dryRun) {
            echo "would  {$key}\n";
            $uploaded++;
            continue;
        }

        if (s3_put_file($entry->getPathname(), $key, mime_content_type($entry->getPathname()) ?: null)) {
            echo "upload {$key}\n";
            $uploaded++;
        } else {
            echo "FAIL   {$key}\n";
            $failed++;
        }
    }
}

echo str_repeat('-', 60) . "\n";
echo ($dryRun ? "Would upload" : "Uploaded") . ": {$uploaded}   Skipped: {$skipped}   Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
