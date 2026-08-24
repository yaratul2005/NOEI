<?php

declare(strict_types=1);

/**
 * Automated Verification Suite for Milestone 4:
 * Content Engine (Posts, Pages, Taxonomies, Revisions, and Unicode Slugs).
 */

define('NOEI_TESTING', true);

require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();

use App\Models\Post;
use App\Models\Taxonomy;
use App\Services\RevisionService;
use App\Services\SlugService;
use Core\Database;

$passed = 0;
$failed = 0;

function assertM4(bool $condition, string $description): void
{
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$description}\n";
        $passed++;
    } else {
        echo " [FAIL] {$description}\n";
        $failed++;
    }
}

echo "=== NOEI CMS Milestone 4 Verification Suite ===\n\n";

// 1. Initialize In-Memory SQLite Database
echo "[1] Setting up In-Memory Database for Content Engine...\n";
$sqlitePdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$schemaSql = file_get_contents(__DIR__ . '/../install/schema.sql');
$cleanSchema = preg_replace('/--.*$/m', '', $schemaSql);
$sqliteSql = preg_replace('/ENGINE=InnoDB DEFAULT CHARSET=\w+ COLLATE=\w+;/i', ';', $cleanSchema);
$sqliteSql = preg_replace('/,?\s*KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $sqliteSql);
$sqliteSql = str_replace(
    ['LONGTEXT', 'ON UPDATE CURRENT_TIMESTAMP', 'BIGINT AUTO_INCREMENT PRIMARY KEY', 'INT AUTO_INCREMENT PRIMARY KEY'],
    ['TEXT', '', 'INTEGER PRIMARY KEY AUTOINCREMENT', 'INTEGER PRIMARY KEY AUTOINCREMENT'],
    $sqliteSql
);

$statements = array_filter(array_map('trim', explode(';', $sqliteSql)));
foreach ($statements as $stmt) {
    if (!empty($stmt) && !str_starts_with(strtoupper($stmt), 'SET ')) {
        $sqlitePdo->exec($stmt);
    }
}

Database::setPdo($sqlitePdo);
$db = Database::getInstance();

// Insert Author User ID 1
$db->execute("INSERT INTO cms_users (username, email, password_hash, role_id, status) VALUES ('author_test', 'author@test.com', 'hash', 1, 'active')");

// 2. SlugService Unicode & Collision Tests
echo "\n[2] Testing SlugService (English, Bangla, Unicode, Collisions)...\n";
$engSlug = SlugService::slugify("Hello World! Modern CMS");
assertM4($engSlug === "hello-world-modern-cms", "SlugService generates clean English slugs");

$banglaTitle = "হ্যালো বাংলাদেশ ২০২৬";
$banglaSlug = SlugService::slugify($banglaTitle);
assertM4($banglaSlug === "হ্যালো-বাংলাদেশ-২০২৬", "SlugService handles multi-byte Bangla/Unicode characters safely");

// Test collision detection
$slug1 = SlugService::uniqueSlug("Duplicate Title", "cms_posts");
$db->execute("INSERT INTO cms_posts (author_id, title, slug, content, type, status) VALUES (1, 'Duplicate Title', :s, 'Body', 'post', 'published')", ['s' => $slug1]);

$slug2 = SlugService::uniqueSlug("Duplicate Title", "cms_posts");
assertM4($slug2 === "duplicate-title-2", "SlugService detects collisions and appends -2 numeric suffix");

// 3. Post Model & Status Workflow Tests
echo "\n[3] Testing Post Model & Status Workflows...\n";
$postModel = new Post($db);

$postId = $postModel->create([
    'author_id' => 1,
    'title' => 'My First NOEI Post',
    'slug' => 'my-first-noei-post',
    'content' => 'This is the main post content body.',
    'excerpt' => 'Short summary.',
    'type' => 'post',
    'status' => 'draft',
]);

assertM4($postId > 0, "Post model creates new post record");
$fetchedPost = $postModel->find($postId);
assertM4($fetchedPost['status'] === 'draft', "Post status initialized as draft");

$postModel->update($postId, [
    'title' => 'My First NOEI Post Updated',
    'slug' => 'my-first-noei-post',
    'content' => 'Updated content body.',
    'excerpt' => 'Updated summary.',
    'status' => 'published',
]);

$updatedPost = $postModel->find($postId);
assertM4($updatedPost['status'] === 'published', "Post status updated to published");

// 4. Taxonomy & Relationship Tests
echo "\n[4] Testing Taxonomy (Categories & Tags)...\n";
$taxonomyModel = new Taxonomy($db);

$catTaxId = $taxonomyModel->createTerm('Technology', 'technology', 'category', 'Tech category');
$tagTaxId = $taxonomyModel->createTerm('PHP 8', 'php-8', 'post_tag', 'PHP tag');

assertM4($catTaxId > 0 && $tagTaxId > 0, "Taxonomy model creates Category and Tag terms");

$taxonomyModel->syncRelationships($postId, [$catTaxId, $tagTaxId]);
$attachedIds = $taxonomyModel->getObjectTaxonomyIds($postId);
assertM4(count($attachedIds) === 2 && in_array($catTaxId, $attachedIds, true), "Taxonomy model binds categories/tags to post");

// 5. RevisionService Snapshot & Restoration Tests
echo "\n[5] Testing RevisionService Snapshot & Rollback...\n";
$revisionService = new RevisionService();

// Create revision snapshot of initial post state
$revId = $revisionService->createRevision($postId, 1);
assertM4($revId > 0, "RevisionService creates revision snapshot record");

$revisions = $revisionService->getRevisions($postId);
assertM4(count($revisions) === 1 && (int)$revisions[0]['id'] === $revId, "RevisionService retrieves revisions list for post");

// Change post content
$postModel->update($postId, [
    'title' => 'V3 Changed Title',
    'slug' => 'my-first-noei-post',
    'content' => 'V3 Changed Content Body',
    'excerpt' => 'V3 Excerpt',
    'status' => 'published',
]);

// Restore back to revision
$restored = $revisionService->restoreRevision($revId, 1);
assertM4($restored === true, "RevisionService restores post from revision snapshot");

$restoredPost = $postModel->find($postId);
assertM4($restoredPost['title'] === 'My First NOEI Post Updated', "Restored post title matches target revision snapshot");

echo "\n=== Summary: {$passed} Passed, {$failed} Failed ===\n";
exit($failed > 0 ? 1 : 0);
