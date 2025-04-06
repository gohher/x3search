<?php
// Include database connection settings file
require_once 'admin/db_config.php'; 

// Enable error display
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Connect to the database
$pdo = getDBConnection(); 

// Set search term and page
$words = isset($_GET['words']) ? trim($_GET['words']) : '';
$view_all = isset($_GET['view_all']) ? true : false;
$results_per_page = 24;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $results_per_page;

// Initialize search result variables
$results = [];
$total_results = 0;

// Image conditions (common)
$image_condition = "image IS NOT NULL AND image != '' AND image NOT LIKE '%/%'";

try {
    // Save to input_temp table when a search term is entered
    if (!empty($words)) {
        $save_query = "INSERT INTO input_temp (search_word, search_time) VALUES (:word, NOW())";
        $save_stmt = $pdo->prepare($save_query);
        $save_stmt->bindValue(':word', $words, PDO::PARAM_STR);
        $save_stmt->execute();
    }
    
    // Latest and oldest search results
    $sort_order = isset($_GET['sort_order']) && $_GET['sort_order'] === 'asc' ? 'ASC' : 'DESC';
    
    if ($view_all) {
        // View all galleries
        $count_query = "SELECT COUNT(*) FROM pages WHERE {$image_condition}";
        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->execute();
        $total_results = $count_stmt->fetchColumn();
        
        // Retrieve results (latest first based on date)
        $query = "SELECT id, image, base, file_path, title, date, imgsnumber
                  FROM pages 
                  WHERE {$image_condition}
                  ORDER BY CASE WHEN date IS NULL THEN 1 ELSE 0 END, date $sort_order
                  LIMIT :limit_param OFFSET :offset_param";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':limit_param', $results_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset_param', $offset, PDO::PARAM_INT);
        $stmt->execute();

    } elseif (isset($_GET['photo_search']) && !empty($words)) {
        /// search only the images table
        $search_term = "%{$words}%";
        
        // Get the number of search results
        $count_query = "SELECT COUNT(*) FROM images i 
                        WHERE (i.title LIKE :search_param 
                        OR i.description LIKE :search_param 
                        OR i.filenames LIKE :search_param)";
        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->bindValue(':search_param', $search_term, PDO::PARAM_STR);
        $count_stmt->execute();
        $total_results = $count_stmt->fetchColumn();
        
        // Retrieve search results
        $query = "SELECT i.id, i.page_id, i.title, i.description, i.base, i.filenames, i.date,
                  p.file_path 
                  FROM images i
                  LEFT JOIN pages p ON i.page_id = p.id
                  WHERE (i.title LIKE :search_param 
                  OR i.description LIKE :search_param 
                  OR i.filenames LIKE :search_param)
                  ORDER BY i.date $sort_order
                  LIMIT :limit_param OFFSET :offset_param";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':search_param', $search_term, PDO::PARAM_STR);
        $stmt->bindValue(':limit_param', $results_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset_param', $offset, PDO::PARAM_INT);
        $stmt->execute();

    } elseif (!empty($words)) {
        // Search by keyword
        $search_term = "%{$words}%";
        
        // Get the number of search results
        $count_query = "SELECT COUNT(*) FROM (
                        SELECT p.id FROM pages p
                        WHERE (p.title LIKE :title_param 
                           OR p.label LIKE :label_param 
                           OR p.description LIKE :desc_param
                           OR p.image LIKE :image_param
                           OR p.seo_title LIKE :seo_title_param 
                           OR p.seo_description LIKE :seo_desc_param 
                           OR p.seo_keywords LIKE :seo_keywords_param)
                           AND p.{$image_condition}
                        UNION
                        SELECT DISTINCT p.id FROM pages p
                        JOIN images i ON p.id = i.page_id
                        WHERE (i.title LIKE :img_title_param
                           OR i.description LIKE :img_desc_param)
                           AND p.{$image_condition}
                        ) AS combined_results";
        
        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->bindValue(':title_param', $search_term, PDO::PARAM_STR);
        $count_stmt->bindValue(':label_param', $search_term, PDO::PARAM_STR);
        $count_stmt->bindValue(':desc_param', $search_term, PDO::PARAM_STR);
        $count_stmt->bindValue(':image_param', $search_term, PDO::PARAM_STR);
        $count_stmt->bindValue(':seo_title_param', $search_term, PDO::PARAM_STR);
        $count_stmt->bindValue(':seo_desc_param', $search_term, PDO::PARAM_STR);
        $count_stmt->bindValue(':seo_keywords_param', $search_term, PDO::PARAM_STR);
        $count_stmt->bindValue(':img_title_param', $search_term, PDO::PARAM_STR);
        $count_stmt->bindValue(':img_desc_param', $search_term, PDO::PARAM_STR);
        $count_stmt->execute();
        $total_results = $count_stmt->fetchColumn();
        
        // Retrieve search results 
        $query = "SELECT p.id, p.image, p.file_path, p.base, p.file_path, p.title, p.date, p.imgsnumber
                  FROM (
                      SELECT p.id FROM pages p
                      WHERE (p.title LIKE :title_param 
                         OR p.label LIKE :label_param 
                         OR p.description LIKE :desc_param
                         OR p.image LIKE :image_param
                         OR p.seo_title LIKE :seo_title_param 
                         OR p.seo_description LIKE :seo_desc_param 
                         OR p.seo_keywords LIKE :seo_keywords_param)
                         AND p.{$image_condition}
                      UNION
                      SELECT DISTINCT p.id FROM pages p
                      JOIN images i ON p.id = i.page_id
                      WHERE (i.title LIKE :img_title_param
                         OR i.description LIKE :img_desc_param)
                         AND p.{$image_condition}
                  ) AS combined_results
                  JOIN pages p ON combined_results.id = p.id
                  ORDER BY CASE WHEN p.date IS NULL THEN 1 ELSE 0 END, p.date $sort_order
                  LIMIT :limit_param OFFSET :offset_param";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':title_param', $search_term, PDO::PARAM_STR);
        $stmt->bindValue(':label_param', $search_term, PDO::PARAM_STR);
        $stmt->bindValue(':desc_param', $search_term, PDO::PARAM_STR);
        $stmt->bindValue(':image_param', $search_term, PDO::PARAM_STR);
        $stmt->bindValue(':seo_title_param', $search_term, PDO::PARAM_STR);
        $stmt->bindValue(':seo_desc_param', $search_term, PDO::PARAM_STR);
        $stmt->bindValue(':seo_keywords_param', $search_term, PDO::PARAM_STR);
        $stmt->bindValue(':img_title_param', $search_term, PDO::PARAM_STR);
        $stmt->bindValue(':img_desc_param', $search_term, PDO::PARAM_STR);
        $stmt->bindValue(':limit_param', $results_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset_param', $offset, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    if (isset($stmt)) {
        $results = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    die('Database Error: ' . $e->getMessage());
}

// Calculate total number of pages
$total_pages = ceil($total_results / $results_per_page);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LTAB Photo Gallery Search</title>
    <link rel="icon" href="/content/custom/favicon/favicon_32.png">
    <link rel="stylesheet" href="/x3search/styles.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
</head>
<body>
    <div class="scontainer">
        <table border='0'>
            <tr><td><a href="index.php"><img src="/content/custom/logo/ltab-logo840.png" alt="LTAB™ Photo Gallery" class="responsive-logo"></a></td></tr>
            
        </table>
        <div class="search-form">
            <input type="text" name="words" class="search-input" placeholder="Please enter a search term." value="<?= htmlspecialchars($words) ?>" id="search-input" autocomplete="off">    
            <button type="button" class="search-button" onclick="search()">Integrated Search</button>
            <a href="?view_all=1" class="view-all-button">All Galleries</a>
            <button type="button" class="photo-search-button" onclick="photoSearch()">Photo file</button>
        </div>
    </div>
    <div class="container">
        <?php if ($view_all || !empty($words)): ?>
        <table border='0' width='100%'><tr><td>
        <div class="results-count">
            <?php if ($view_all): ?>
                All Galleries: <span class="result-count"><?= $total_results ?>items</span>
            <?php else: ?>
                "<?= htmlspecialchars($words) ?>" Search Results: <span class="result-count"><?= $total_results ?>items</span>
            <?php endif; ?>
        </div>
        </td>
        <td align='right'>
        <?php
            // Maintain current URL parameters
            $query_params = $_GET;
            // Create latest-first link
            $query_params['sort_order'] = 'desc';
            $desc_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($query_params);
            // Create oldest-first link
            $query_params['sort_order'] = 'asc';
            $asc_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($query_params);
            ?>
            <div class="results-count">
                <a href="<?= htmlspecialchars($desc_url) ?>" <?= (!isset($_GET['sort_order']) || $_GET['sort_order'] !== 'asc') ? 'class="active"' : '' ?>>Newest</a> 
                <a href="<?= htmlspecialchars($asc_url) ?>" <?= (isset($_GET['sort_order']) && $_GET['sort_order'] === 'asc') ? 'class="active"' : '' ?>>Oldest</a>
            </div>
        <?php if (count($results) > 0): ?>
        </td></tr></table><hr color='#3a3a3a'><br /><?php endif; ?>
    <div class="results-grid">
    <?php foreach ($results as $row): ?>
        <div class="result-item <?= isset($_GET['photo_search']) ? 'photo-search-item' : '' ?>">
            <?php if (isset($_GET['photo_search'])): ?>
                <a href="/<?= htmlspecialchars($row['base']) ?>/<?= htmlspecialchars($row['file_path']) ?>" target="_blank">
                    
                    <img src="/render/w320/<?= htmlspecialchars($row['base']) ?>/<?= htmlspecialchars($row['file_path']) ?>/<?= htmlspecialchars($row['filenames']) ?>" alt="">
                </a>
            <?php else: ?>
                <a href="/<?= htmlspecialchars($row['base']) ?>/<?= htmlspecialchars($row['file_path']) ?>" target="_blank">
                    <div class="title-overlay"><?= htmlspecialchars($row['title'] ?? '') ?>&nbsp;&nbsp;<?= htmlspecialchars($row['imgsnumber'] ?? '') ?></div>
                    <img src="/render/w480-c1.1/<?= htmlspecialchars($row['base']) ?>/<?= htmlspecialchars($row['file_path']) ?>/<?= htmlspecialchars($row['image']) ?>" alt="">
                </a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
    
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php 
                // Query parameter settings
                if ($view_all) {
                    $query_params = 'view_all=1';
                } elseif (isset($_GET['photo_search'])) {
                    $query_params = 'photo_search=1&words=' . urlencode($words);
                } else {
                    $query_params = 'search=1&words=' . urlencode($words);
                }
                // sort order
                if (isset($_GET['sort_order'])) {
                    $query_params .= '&sort_order=' . urlencode($_GET['sort_order']);
                }
                // Previous page link
                if ($page > 1): ?>
                    <a href="?<?= $query_params ?>&page=<?= $page-1 ?>" class="pagination-link">&laquo; Previous</a>
                <?php endif; 

                // Page link display range
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                // First page link
                if ($start_page > 1): ?>
                    <a href="?<?= $query_params ?>&page=1" class="pagination-link">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="pagination-dots">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="pagination-current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= $query_params ?>&page=<?= $i ?>" class="pagination-link"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="pagination-dots">...</span>
                    <?php endif; ?>
                    <a href="?<?= $query_params ?>&page=<?= $total_pages ?>" class="pagination-link"><?= $total_pages ?></a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?= $query_params ?>&page=<?= $page+1 ?>" class="pagination-link">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        <?php elseif ($view_all || !empty($words)): ?>
            <div class="no-results">No search results found.</div>
        <?php endif; ?>
        <table border='0' width='100%' class="ft">
            <tr align='center'>
                <td><br/>© 2025 LTAB™ Gallery Search </td></tr>
    </table>
    </div>
   
<script>
    $(function() {
        // $("#search-input").focus(); 

        $("#search-input").autocomplete({
            source: "get_autocomplete.php",
            minLength: 1,
            select: function(event, ui) {
                $("#search-input").val(ui.item.value);
                search();
                return false;
            },
            focus: function(event, ui) {
            return false;
            }
        });
    });

    function search() {
        const words = document.getElementById('search-input').value.trim();
        if (words) {
            window.location.href = '?words=' + encodeURIComponent(words);
        }
    }

    function photoSearch() {
        const searchInput = document.getElementById('search-input');
        const searchTerm = searchInput.value.trim();
        if (searchTerm) {
            window.location.href = `?words=${encodeURIComponent(searchTerm)}&photo_search=1`;
            }
    }

    document.getElementById('search-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            search();
        }
    });

    document.addEventListener("contextmenu", function(event) {
        event.preventDefault(); 
    });

    document.addEventListener("keydown", function(event) {
        if (event.key === "F12" || 
            (event.ctrlKey && event.shiftKey && event.key === "I") ||
            (event.ctrlKey && event.shiftKey && event.key === "J") ||
            (event.ctrlKey && event.key === "u") ||
            (event.ctrlKey && event.key === "c")) { 
            event.preventDefault();
            alert("이 기능은 사용할 수 없습니다!");
        }
    });

    document.addEventListener("copy", function(event) {
        event.preventDefault();  
        alert("텍스트 복사가 금지되었습니다!");
    });

    document.addEventListener("selectstart", function(event) {
        event.preventDefault();  
    });

    </script>
</body>
</html>

