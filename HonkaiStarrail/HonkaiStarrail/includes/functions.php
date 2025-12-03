// filepath: c:\xampp\htdocs\Lập trình web\HonkaiStarrail\database.php
<?php
$host = 'localhost';
$db_name = 'honkai_star_rail';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

// Create tables
$queries = [
    "CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL
    )",

    "CREATE TABLE IF NOT EXISTS characters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        path VARCHAR(50),
        element VARCHAR(50),
        description TEXT,
        tier VARCHAR(10),
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS paths (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE
    )",

    "CREATE TABLE IF NOT EXISTS elements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE
    )",

    "CREATE TABLE IF NOT EXISTS lightcones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT
    )",

    "CREATE TABLE IF NOT EXISTS relics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(50),
        description TEXT
    )",

    "CREATE TABLE IF NOT EXISTS materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT
    )",

    "CREATE TABLE IF NOT EXISTS builds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT,
        lightcone_id INT,
        relic_id INT,
        FOREIGN KEY (character_id) REFERENCES characters(id),
        FOREIGN KEY (lightcone_id) REFERENCES lightcones(id),
        FOREIGN KEY (relic_id) REFERENCES relics(id)
    )",

    "CREATE TABLE IF NOT EXISTS teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        character_ids TEXT,
        description TEXT
    )"
];

foreach ($queries as $query) {
    $pdo->exec($query);
}

// Insert default admin account
$admin_username = 'admin';
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT IGNORE INTO admin (username, password) VALUES (:username, :password)");
$stmt->execute(['username' => $admin_username, 'password' => $admin_password]);

echo "Database and tables created successfully.";
?>