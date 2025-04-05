<?php
// Include database connection settings file
require_once 'admin/db_config.php'; 

try {
    // Get the entered search term.
    $term = isset($_GET['term']) ? $_GET['term'] : '';
    
    if(!empty($term)) {
        // Search the table for words matching the search term
        $query = "SELECT word FROM index_word WHERE word LIKE :term LIMIT 20";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':term', $term.'%', PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetchAll();
        
        $wordList = [];
        foreach($result as $row) {
            $wordList[] = $row['word'];
        }
        
        // Return results as JSON
        echo json_encode($wordList);
    }
} catch(PDOException $e) {
    echo json_encode([]);
}
?>
