// filepath: c:\xampp\htdocs\Lập trình web\HonkaiStarrail\database.php
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "honkai_star_rail";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tables
$sql = "CREATE TABLE IF NOT EXISTS admin (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS characters (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    path VARCHAR(50),
    element VARCHAR(50),
    description TEXT,
    tier VARCHAR(10)
);

CREATE TABLE IF NOT EXISTS paths (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    path_name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS elements (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    element_name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS lightcones (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS relics (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50),
    description TEXT
);

CREATE TABLE IF NOT EXISTS materials (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50),
    description TEXT
);

CREATE TABLE IF NOT EXISTS builds (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    character_id INT(11),
    lightcone_id INT(11),
    relic_id INT(11),
    FOREIGN KEY (character_id) REFERENCES characters(id),
    FOREIGN KEY (lightcone_id) REFERENCES lightcones(id),
    FOREIGN KEY (relic_id) REFERENCES relics(id)
);

CREATE TABLE IF NOT EXISTS teams (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL,
    character_ids TEXT
);";

if ($conn->multi_query($sql) === TRUE) {
    echo "Tables created successfully";
} else {
    echo "Error creating tables: " . $conn->error;
}

$conn->close();
?>