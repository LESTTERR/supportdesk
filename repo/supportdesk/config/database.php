<?php
$host = getenv('DB_HOST') ?: 'localhost';
$db = getenv('DB_NAME') ?: 'customer_support_ticketing';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $serverDsn = "mysql:host=$host;charset=$charset";
    $serverPdo = new PDO($serverDsn, $user, $pass, $options);
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, $options);
    ensureDatabaseSchema($pdo, $db);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed. Make sure MySQL is running in XAMPP.'
    ]);
    exit;
}

function ensureDatabaseSchema(PDO $pdo, string $db): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(150) NOT NULL,
            password varchar(255) NOT NULL DEFAULT '',
            role enum('customer','agent','admin') DEFAULT 'customer',
            phone_enc text DEFAULT NULL,
            phone_iv varchar(64) DEFAULT NULL,
            phone_tag varchar(64) DEFAULT NULL,
            address_enc text DEFAULT NULL,
            address_iv varchar(64) DEFAULT NULL,
            address_tag varchar(64) DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tickets (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            assignee_id int(11) DEFAULT NULL,
            category_id int(11) DEFAULT NULL,
            subject varchar(200) NOT NULL,
            description text NOT NULL,
            priority enum('low','medium','high','critical') DEFAULT 'medium',
            status enum('open','in_progress','resolved','closed') DEFAULT 'open',
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY assignee_id (assignee_id),
            KEY category_id (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ticket_replies (
            id int(11) NOT NULL AUTO_INCREMENT,
            ticket_id int(11) NOT NULL,
            user_id int(11) NOT NULL,
            message text NOT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attachments (
            id int(11) NOT NULL AUTO_INCREMENT,
            ticket_id int(11) NOT NULL,
            user_id int(11) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size int(11) DEFAULT NULL,
            mime_type varchar(100) DEFAULT NULL,
            uploaded_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    addColumnIfMissing($pdo, $db, 'tickets', 'assignee_id', "int(11) DEFAULT NULL AFTER user_id");
    addColumnIfMissing($pdo, $db, 'users', 'phone_enc', "text DEFAULT NULL AFTER role");
    addColumnIfMissing($pdo, $db, 'users', 'phone_iv', "varchar(64) DEFAULT NULL AFTER phone_enc");
    addColumnIfMissing($pdo, $db, 'users', 'phone_tag', "varchar(64) DEFAULT NULL AFTER phone_iv");
    addColumnIfMissing($pdo, $db, 'users', 'address_enc', "text DEFAULT NULL AFTER phone_tag");
    addColumnIfMissing($pdo, $db, 'users', 'address_iv', "varchar(64) DEFAULT NULL AFTER address_enc");
    addColumnIfMissing($pdo, $db, 'users', 'address_tag', "varchar(64) DEFAULT NULL AFTER address_iv");
    $pdo->exec("ALTER TABLE tickets MODIFY priority enum('low','medium','high','critical') DEFAULT 'medium'");
    $pdo->exec("UPDATE tickets SET priority = 'medium' WHERE priority IS NULL OR priority = ''");
    $pdo->exec("UPDATE tickets SET status = 'open' WHERE status IS NULL OR status = ''");

    addIndexIfMissing($pdo, $db, 'categories', 'uniq_categories_name', 'name', true);
    addIndexIfMissing($pdo, $db, 'tickets', 'assignee_id', 'assignee_id', false);

    addForeignKeyIfMissing($pdo, $db, 'tickets', 'tickets_user_fk', 'user_id', 'users', 'id', 'CASCADE');
    addForeignKeyIfMissing($pdo, $db, 'tickets', 'tickets_assignee_fk', 'assignee_id', 'users', 'id', 'SET NULL');
    addForeignKeyIfMissing($pdo, $db, 'tickets', 'tickets_category_fk', 'category_id', 'categories', 'id', 'SET NULL');
    addForeignKeyIfMissing($pdo, $db, 'ticket_replies', 'ticket_replies_ticket_fk', 'ticket_id', 'tickets', 'id', 'CASCADE');
    addForeignKeyIfMissing($pdo, $db, 'ticket_replies', 'ticket_replies_user_fk', 'user_id', 'users', 'id', 'CASCADE');
    addForeignKeyIfMissing($pdo, $db, 'attachments', 'attachments_ticket_fk', 'ticket_id', 'tickets', 'id', 'CASCADE');
    addForeignKeyIfMissing($pdo, $db, 'attachments', 'attachments_user_fk', 'user_id', 'users', 'id', 'CASCADE');

    $categories = ['Technical', 'Billing', 'Account', 'General Inquiry'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
    foreach ($categories as $category) {
        $stmt->execute([$category]);
    }
}

function addColumnIfMissing(PDO $pdo, string $db, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$db, $table, $column]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function addIndexIfMissing(PDO $pdo, string $db, string $table, string $indexName, string $column, bool $unique): void
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
    ");
    $stmt->execute([$db, $table, $indexName]);
    if ((int) $stmt->fetchColumn() === 0) {
        $type = $unique ? 'UNIQUE' : 'INDEX';
        try {
            $pdo->exec("ALTER TABLE `$table` ADD $type `$indexName` (`$column`)");
        } catch (PDOException $e) {
            // Existing data may not satisfy a new unique index; the app can still run.
        }
    }
}

function addForeignKeyIfMissing(
    PDO $pdo,
    string $db,
    string $table,
    string $constraint,
    string $column,
    string $referencedTable,
    string $referencedColumn,
    string $onDelete
): void {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLE_CONSTRAINTS tc
        LEFT JOIN information_schema.KEY_COLUMN_USAGE kcu
            ON tc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
            AND tc.TABLE_NAME = kcu.TABLE_NAME
            AND tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
        WHERE tc.CONSTRAINT_SCHEMA = ?
            AND tc.TABLE_NAME = ?
            AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND (
                tc.CONSTRAINT_NAME = ?
                OR (
                    kcu.COLUMN_NAME = ?
                    AND kcu.REFERENCED_TABLE_NAME = ?
                    AND kcu.REFERENCED_COLUMN_NAME = ?
                )
            )
    ");
    $stmt->execute([$db, $table, $constraint, $column, $referencedTable, $referencedColumn]);
    if ((int) $stmt->fetchColumn() === 0) {
        try {
            $pdo->exec("
                ALTER TABLE `$table`
                ADD CONSTRAINT `$constraint`
                FOREIGN KEY (`$column`) REFERENCES `$referencedTable` (`$referencedColumn`)
                ON DELETE $onDelete
            ");
        } catch (PDOException $e) {
            // Existing dumps may already have equivalent foreign keys with older names.
        }
    }
}
?>
