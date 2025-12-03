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
$sql = "
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS characters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    path VARCHAR(50),
    element VARCHAR(50),
    lightcone VARCHAR(100),
    relics VARCHAR(255),
    materials VARCHAR(255),
    build TEXT,
    team VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS paths (
    id INT AUTO_INCREMENT PRIMARY KEY,
    path_name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS elements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    element_name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS lightcones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lightcone_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE IF NOT EXISTS relics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    relic_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);
";

$pdo->exec($sql);

// Insert default admin account
$admin_username = 'admin';
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO admin (username, password) VALUES (:username, :password)");
$stmt->execute(['username' => $admin_username, 'password' => $admin_password]);

echo "Database and tables created successfully.";
?>