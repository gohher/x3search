<?php
// * In the Photo Gallery X3 admin panel (panel), you must fill in and save the [Page] > Title, Menu Label, Description, and SEO information.
// * If any of these fields are null, the data will not be registered in the database.
// This PHP code sets the default folder, searches for page.json files, and registers the indexing information into the database.
// 2. Only modify the Folder Setup section. Do not edit any other parts.

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Include database connection settings file
require __DIR__ . '/db_config.php';

// 2. Folder Setup 
// * Register the location of the indexing folders in the format below.
// ** Multiple folders can be specified, and a comma (,) must be placed at the end.
$baseDirs = [
    'Gallery' => '/var/www/html/content/Gallery/',
    'EXHIBIT' => '/var/www/html/content/EXHIBIT/',
];

// 3. page.json File Search Function
function findPageJsonFiles($baseDirs) {
    $results = [];
    foreach ($baseDirs as $base => $dir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'page.json') {
                $results[] = [
                    'base' => $base,
                    'path' => $file->getPathname(),
                    'dir' => $dir
                ];
            }
        }
    }
    return $results;
}

// 4. Transaction Handling Functions
function handlePjindex($db, $path, $fileTime, $base) {
    $stmt = $db->prepare("SELECT id, file_creation_time FROM pjindex WHERE base=? AND directory_path=?");
    $stmt->execute([$base, $path]);
    
    $status = '';
    if ($row = $stmt->fetch()) {
        $dbTime = strtotime($row['file_creation_time']);
        $fileTimeStamp = strtotime($fileTime);
        
        if ($dbTime != $fileTimeStamp) {
            $updateStmt = $db->prepare("
                UPDATE pjindex 
                SET update_count = update_count + 1, 
                    file_creation_time = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$fileTime, $row['id']]);
            $status = 'updated';
            return ['id' => $row['id'], 'status' => $status];
        }
        $status = 'no_change';
        return ['id' => $row['id'], 'status' => $status];
    }

    $insertStmt = $db->prepare("
        INSERT INTO pjindex 
        (base, directory_path, file_creation_time, update_count) 
        VALUES (?,?,?,1)
    ");
    $insertStmt->execute([$base, $path, $fileTime]);
    $status = 'new';
    return ['id' => $db->lastInsertId(), 'status' => $status];
}

function handlePages($db, $pjindexId, $path, $data, $base) {
    $dateValue = (!empty($data['date']) && strtotime($data['date'])) 
        ? date('Y-m-d', strtotime($data['date'])) 
        : null;

    $db->prepare("DELETE FROM pages WHERE pjindex_id=?")->execute([$pjindexId]);

    $stmt = $db->prepare("
        INSERT INTO pages (
            pjindex_id, base, file_path, title, label, description, 
            date, image, seo_title, seo_description, seo_keywords, created_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
    ");
    $stmt->execute([
        $pjindexId, 
        $base,
        preg_replace('/\/page\.json$/', '', $path),
        $data['title'] ?? '', 
        $data['label'] ?? '', 
        $data['description'] ?? '',
        $dateValue, 
        $data['image'] ?? '',
        $data['seo']['title'] ?? '', 
        $data['seo']['description'] ?? '', 
        $data['seo']['keywords'] ?? ''
    ]);
    return $db->lastInsertId();
}

function handleImages($db, $pageId, $data, $base) {
    $excludeKeys = [
        'title','label','description','date','image',
        'seo','layout','context','folders','gallery','popup','plugins'
    ];
    
    $db->prepare("DELETE FROM images WHERE page_id=?")->execute([$pageId]);

    $stmtPage = $db->prepare("SELECT file_path, date FROM pages WHERE id=?");
    $stmtPage->execute([$pageId]);
    $pageData = $stmtPage->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        INSERT INTO images (
            page_id, base, file_path, title, description, 
            image_index, filenames, date, created_at
        ) VALUES (?,?,?,?,?,?,?,?,NOW())
    ");

    foreach ($data as $filename => $meta) {
        if (in_array($filename, $excludeKeys) || !is_array($meta)) continue;
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) continue;

        $stmt->execute([
            $pageId, 
            $base, 
            $pageData['file_path'],
            $meta['title'] ?? '', 
            $meta['description'] ?? '',
            (int)($meta['index'] ?? 0), 
            $filename, 
            $pageData['date']
        ]);
    }
}

// 5. Main Execution Logic
echo "<br>::: page.json Insert processor :::<br><br>";

try {
    $db = getDBConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    foreach (findPageJsonFiles($baseDirs) as $file) {
        $db->beginTransaction();

        try {
            $baseDir = rtrim($file['dir'], DIRECTORY_SEPARATOR);
            $fullDirPath = dirname($file['path']);
            $normalizedPath = ltrim(str_replace($baseDir, '', $fullDirPath), DIRECTORY_SEPARATOR);

            $jsonData = json_decode(file_get_contents($file['path']), true);
            if (!$jsonData) throw new Exception("Invalid JSON format");

            $result = handlePjindex($db, $normalizedPath, date("Y-m-d H:i:s", filectime($file['path'])), $file['base']);
            $pjindexId = $result['id'];
            $status = $result['status']; // Check file modification status.
            
            // Perform updates for pages and images only when files are newly added or modified.
            if ($status === 'new' || $status === 'updated') {
                $pageId = handlePages($db, $pjindexId, $normalizedPath, $jsonData, $file['base']);
                handleImages($db, $pageId, $jsonData, $file['base']);
                $statusMsg = ($status === 'new') ? "🌟 New Insert" : "🔄 Upadte(count +1)";
            } else {
                $statusMsg = "✅ No changes";
            }

            $db->commit();
            echo "{$statusMsg} ::: [{$file['base']}] {$file['path']}<br>";

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error ! ::: [{$file['base']}] {$file['path']} - {$e->getMessage()}");
            echo "❌ Error ::: [{$file['base']}] {$file['path']} - {$e->getMessage()}<br>";
        }
    }
    echo "<br>::: page.json database insert Processing Complete!<br><br>";
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// 6. Update Image Count
function updateImageCounts($db, $baseDirs) {
    foreach ($baseDirs as $base => $dir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $jpgCount = 0;
                foreach (scandir($item) as $file) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg'])) $jpgCount++;
                }
                
                if ($jpgCount > 0) {
                    $relativePath = ltrim(str_replace($dir, '', $item->getPathname()), DIRECTORY_SEPARATOR);
                    $stmt = $db->prepare("UPDATE pages SET imgsnumber=? 
                        WHERE base=? AND file_path=?");
                    $stmt->execute([$jpgCount, $base, $relativePath]);
                }
            }
        }
    }
}

// 7. Executing Image Count Update
try {
    $db->beginTransaction();
    updateImageCounts($db, $baseDirs);
    $db->commit();
    echo "<br>🔄 Image Count Update Complete! ";
} catch (Exception $e) {
    $db->rollBack();
    error_log("Image Count Update Failed: ".$e->getMessage());
}
echo "<br><br><br>LTAB Photo Gallery page.json Handler © 2025<br><br>";

?>
