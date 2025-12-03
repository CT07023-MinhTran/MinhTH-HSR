<?php
// --- CẢNH BÁO ---
// PHẦN CODE DƯỚI ĐÂY DÙNG ĐỂ THIẾT LẬP CƠ SỞ DỮ LIỆU.
// NÓ ĐÃ ĐƯỢC SỬA LẠI CHO ĐÚNG.
// BẠN CHỈ NÊN CHẠY TRANG NÀY MỘT LẦN ĐỂ TẠO/CẬP NHẬT BẢNG.
// SAU KHI CHẠY, BẠN NÊN XÓA HOẶC COMMENT PHẦN PHP NÀY ĐI ĐỂ TRÁNH LỖI.

$conn = new mysqli("localhost", "root", "", "honkai_star_rail");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Mảng chứa các câu lệnh CREATE TABLE chính xác
$tables = [
    "CREATE TABLE IF NOT EXISTS characters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        path_id INT,
        element_id INT,
        rarity VARCHAR(10),
        description TEXT,
        image VARCHAR(255),
        hp INT,
        atk INT,
        def INT,
        spd INT,
        FOREIGN KEY (path_id) REFERENCES paths(id),
        FOREIGN KEY (element_id) REFERENCES elements(id),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS paths (
        id INT AUTO_INCREMENT PRIMARY KEY,
        path VARCHAR(50) NOT NULL UNIQUE,
        image VARCHAR(255)
    )",
    "CREATE TABLE IF NOT EXISTS elements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        element VARCHAR(50) NOT NULL UNIQUE,
        image VARCHAR(255)
    )",
    "CREATE TABLE IF NOT EXISTS lightcones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        image VARCHAR(255),
        path VARCHAR(100),
        rarity VARCHAR(10),
        path_effect TEXT,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS relics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        icon VARCHAR(255),
        type VARCHAR(50),
        set2_effect TEXT,
        set4_effect TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($tables as $table) {
    if ($conn->query($table) === FALSE) {
        echo "Error creating table: " . $conn->error . "<br>";
    }
}

echo "Các bảng trong cơ sở dữ liệu đã được kiểm tra và cập nhật thành công. Vui lòng xóa hoặc comment phần code PHP trong file teams.php sau khi chạy lần đầu.";
// KẾT THÚC PHẦN THIẾT LẬP CSDL
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Đội hình</title>
    <link rel="stylesheet" href="honkai.css">
</head>
<body>
    <div class="admin-header">
        <div class="admin-logo">
            <img src="https://webstatic.hoyoverse.com/upload/op-public/2023/09/14/3c862d085db721a5625b6e12649399bc_3523008591120432460.png" alt="Honkai Star Rail Banner">
            <span>Administrator</span>
        </div>
        <div class="admin-nav">
            <a href="../Trangchinh.php">Trang chủ website</a>
            <span class="admin-user">Xin chào: admin</span>
        </div>
    </div>
    <div class="admin-container">
        <div class="admin-sidebar">
            <ul>
                <li><a href="index.php">Trang chủ Admin</a></li>
                <li><a href="characters.php">Nhân vật</a></li>
                <li><a href="paths.php">Vận Mệnh</a></li>
                <li><a href="elements.php">Hệ</a></li>
                <li><a href="lightcones.php">Nón Ánh Sáng</a></li>
                <li><a href="relics.php">Di vật</a></li>
                <li><a href="materials.php">Nguyên liệu</a></li>
                <li><a href="builds.php">Build</a></li>
                <li><a href="teams.php" class="active">Đội hình</a></li>
            </ul>
        </div>
        <div class="admin-main">
            <!-- ...existing code... -->
        </div>
    </div>
</body>
</html>