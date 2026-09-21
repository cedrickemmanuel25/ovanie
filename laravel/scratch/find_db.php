<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
$dbs = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);

foreach ($dbs as $db) {
    try {
        $pdo->exec("USE `$db`");
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('products', $tables)) {
            $cnt = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            echo "Database [$db] -> products table has $cnt rows\n";
        }
    } catch (Exception $e) {
        // ignore
    }
}
