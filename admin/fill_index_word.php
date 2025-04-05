<?php
// Include database connection settings file
require __DIR__ . '/db_config.php';

// Enable error display
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Connect to the database
$pdo = getDBConnection(); 

try {
    //Fetch data from pages table 
    $query1 = "SELECT title, label, description, seo_title, seo_description, seo_keywords
               FROM pages
               WHERE title IS NOT NULL OR label IS NOT NULL OR description IS NOT NULL
               OR seo_title IS NOT NULL OR seo_description IS NOT NULL OR seo_keywords IS NOT NULL";
    
    $stmt1 = $pdo->prepare($query1);
    $stmt1->execute();
    $results1 = $stmt1->fetchAll();
    
    // Fetch data from images table
    $query2 = "SELECT title, description
               FROM images
               WHERE title IS NOT NULL OR description IS NOT NULL";
    
    $stmt2 = $pdo->prepare($query2);
    $stmt2->execute();
    $results2 = $stmt2->fetchAll();

    // Extract and clean words
    $words = [];

    // Process pages table
    foreach ($results1 as $row) {
        foreach ($row as $field) {
            if (!empty($field)) {
                // Remove special characters and split words by spaces
                $cleaned = preg_replace('/[^A-Za-z0-9가-힣\s]/', ' ', $field);
                $cleaned = preg_replace('/\s+/', ' ', $cleaned);
                $cleaned = trim($cleaned);
                $fieldWords = explode(' ', $cleaned);
                
                foreach ($fieldWords as $word) {
                    $word = trim($word);
                    if (strlen($word) >= 2) { // Save only words with 2 or more characters
                        $words[] = $word;
                    }
                }
            }
        }
    }
    
    // Fetch data from images table
    foreach ($results2 as $row) {
        foreach ($row as $field) {
            if (!empty($field)) {
                // Remove special characters and split words by spaces
                $cleaned = preg_replace('/[^A-Za-z0-9가-힣\s]/', ' ', $field);
                $cleaned = preg_replace('/\s+/', ' ', $cleaned);
                $cleaned = trim($cleaned);
                $fieldWords = explode(' ', $cleaned);
                
                foreach ($fieldWords as $word) {
                    $word = trim($word);
                    if (strlen($word) >= 2) { // Save only words with 2 or more characters
                        $words[] = $word;
                    }
                }
            }
        }
    }

    // Remove duplicates and sort
    $words = array_unique($words);
    sort($words);
    $results = [];
    try {
        // Transaction start
        if ($pdo->beginTransaction()) {
            echo "<p>Transaction start</p>";
            
            try {
                // Delete existing data (optional)
                $truncate = $pdo->prepare("TRUNCATE TABLE index_word");
                $truncate->execute();

                // Insert words
                $insertStmt = $pdo->prepare("INSERT INTO index_word (word, created_at) VALUES (?, NOW())");
                $count = 0;
                
                foreach ($words as $word) {
                    try {
                        $insertStmt->execute([$word]);
                        $count++;
                        $results[] = ['word' => $word, 'status' => 'Success'];
                    } catch (PDOException $e) {
                        // Ignore duplicate key errors (in case of UNIQUE constraint)
                        if ($e->getCode() != '23000') {
                            $results[] = ['word' => $word, 'status' => 'Failure: ' . $e->getMessage()];
                            throw $e;
                        } else {
                            $results[] = ['word' => $word, 'status' => 'Duplicate'];
                        }
                    }
                }

                // Commit transaction
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                    echo "<p>Transaction commit completed</p>";
                }

                echo "<p>Word extraction completed:: 총 {$count}A total of (number) words were added.</p>";

                // Output result table
                echo "<table border='1'>
                        <tr>
                            <th>Word</th>
                            <th>Status</th>
                        </tr>";
                foreach ($results as $result) {
                    echo "<tr>
                            <td>{$result['word']}</td>
                            <td>{$result['status']}</td>
                          </tr>";
                }
                echo "</table>";

            } catch (Exception $e) {
                // Roll back if an internal error occurs
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                    echo "<p>Transaction rollback completed.</p>";
                }
                
                throw $e; // Propagate exception to the upper catch block
            }
        } else {
            echo "<p>Failed to start transaction.</p>";
        }
    } catch (Exception $e) {
        echo "<p>Transaction management error: " . $e->getMessage() . "</p>";
    }
} catch (PDOException $e) {
    // Error during database connection or query execution
    echo "<p>Database error: " . $e->getMessage() . "</p>";
    // Log error
    error_log("Database Error in fill_index_word.php: " . $e->getMessage());
}
?>