<?php
define('DB_HOST', 'database IP ');
define('DB_PORT', '3306');
define('DB_NAME', 'database name');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_general_ci');

/**
 * PDO Returns a database connection object.
 * Raises an exception and logs an error if the connection fails.
 *
 * @return PDO PDO Database connection object
 * @throws PDOException On database connection failure
 */
function getDBConnection() {
    try {
        // DSN (Data Source Name) Create connection string
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        $options = [
            // PDO Set connection options
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // Raise an exception on error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // Fetch results as an associative array
            PDO::ATTR_PERSISTENT => false,                      // Do not use persistent connections (recommended false for web environments)
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '".DB_CHARSET."' COLLATE '".DB_COLLATE."'"   // Set character set and collation upon connection
        ];
        // PDO Create and return the connection object
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Log errors with date and time included
        error_log("[".date('Y-m-d H:i:s')."] DB Connection Error: ".$e->getMessage());
        // Re-throw the exception to be handled by the caller
        throw $e;
    }
}
?>
