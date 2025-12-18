<?php
/**
 * Trang chủ cho Honkai: Star Rail HoYoWiki.
 * Hiển thị thông điệp chào mừng, điều hướng chính và các mục nổi bật.
 */

// --- 1. THIẾT LẬP KẾT NỐI CSDL ---
$conn = new mysqli("localhost", "root", "", "honkai_star_rail");
if ($conn->connect_error) {
    // Không die() ở trang chủ, chỉ ẩn các mục nổi bật nếu lỗi
    $db_error = true;
} else {
    $conn->set_charset("utf8mb4");
    $db_error = false;
}

// --- 2. LẤY DỮ LIỆU NỔI BẬT (NẾU KẾT NỐI THÀNH CÔNG) ---
$featured_characters = [];
$featured_lightcones = [];

if (!$db_error) {
    // Lấy 6 nhân vật ngẫu nhiên
    $char_result = $conn->query("SELECT name, image FROM characters ORDER BY RAND() LIMIT 6");
    if ($char_result) {
        while ($row = $char_result->fetch_assoc()) {
            $featured_characters[] = $row;
        }
    }

    // Lấy 6 nón ánh sáng ngẫu nhiên
    $lc_result = $conn->query("SELECT name, image FROM lightcones ORDER BY RAND() LIMIT 6");
    if ($lc_result) {
        while ($row = $lc_result->fetch_assoc()) {
            $featured_lightcones[] = $row;
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Chủ - Honkai: Star Rail HoYoWiki</title>
    <style>
        :root {
            --primary-color: #1e3a56;
            --secondary-color: #f0f2f5;
            --text-color: #333;
            --sidebar-bg: #fff;
            --sidebar-width: 240px;
            --link-hover-color: #007bff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--secondary-color);
            color: var(--text-color);
            display: flex;
        }

        /* --- Sidebar --- */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--sidebar-bg);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 30px;
            text-align: center;
        }

        .sidebar-nav ul {
            list-style: none;
        }

        .sidebar-nav li {
            margin-bottom: 15px;
        }

        .sidebar-nav a {
            text-decoration: none;
            color: var(--text-color);
            font-size: 1.1em;
            padding: 10px 15px;
            display: block;
            border-radius: 8px;
            transition: background-color 0.2s, color 0.2s;
        }

        .sidebar-nav a:hover {
            background-color: var(--primary-color);
            color: #fff;
        }

        /* --- Main Content --- */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 40px;
        }

        /* --- Hero Section --- */
        .hero-section {
            text-align: center;
            padding: 80px 20px;
            background: #fff;
            border-radius: 12px;
            margin-bottom: 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .hero-section h1 {
            font-size: 2.5em;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .hero-section p {
            font-size: 1.2em;
            color: #555;
            max-width: 800px;
            margin: 0 auto 40px auto;
        }

        .main-nav-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .main-nav-buttons a {
            text-decoration: none;
            background-color: var(--primary-color);
            color: #fff;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            transition: background-color 0.2s, transform 0.2s;
        }

        .main-nav-buttons a:hover {
            background-color: var(--link-hover-color);
            transform: translateY(-3px);
        }

        /* --- Featured Section --- */
        .featured-section {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .featured-section h2 {
            font-size: 2em;
            color: var(--primary-color);
            margin-bottom: 25px;
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 10px;
        }

        .featured-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 20px;
        }

        .featured-item {
            text-align: center;
        }

        .featured-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .featured-item img:hover {
            transform: scale(1.05);
        }

        .featured-item span {
            font-weight: bold;
            color: #444;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .sidebar {
                display: none; /* Ẩn sidebar trên màn hình nhỏ */
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">Star Rail Wiki</div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="Trangchinh.php">Trang Chủ</a></li>
                <li><a href="Nhanvat.php">Nhân Vật</a></li>
                <li><a href="Nonanhsang.php">Nón Ánh Sáng</a></li>
                <li><a href="Divat.php">Di Vật</a></li>
                <li><a href="tierlist.php">Tier List</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <section class="hero-section">
            <h1>Chào mừng bạn đến với Star Rail Wiki!</h1>
            <p>Hy vọng cuộc hành trình này sẽ đưa chúng ta đến những vì sao.</p>
            <div class="main-nav-buttons">
                <a href="Nhanvat.php">Nhân Vật</a>
                <a href="Nonanhsang.php">Nón Ánh Sáng</a>
                <a href="Divat.php">Di Vật</a>
                <a href="tierlist.php">Tier List</a>
            </div>
        </section>

        <?php if (!$db_error && !empty($featured_characters)): ?>
        <section class="featured-section">
            <h2>Nhân vật nổi bật</h2>
            <div class="featured-grid">
                <?php foreach ($featured_characters as $char): ?>
                    <div class="featured-item">
                        <img src="HonkaiStarrail/admin/uploads//characters/<?php echo htmlspecialchars(urlencode($char['image'])); ?>" alt="<?php echo htmlspecialchars($char['name']); ?>">
                        <span><?php echo htmlspecialchars($char['name']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!$db_error && !empty($featured_lightcones)): ?>
        <section class="featured-section">
            <h2>Nón ánh sáng nổi bật</h2>
            <div class="featured-grid">
                <?php foreach ($featured_lightcones as $lc): ?>
                    <div class="featured-item">
                        <img src="HonkaiStarrail/admin/uploads/lightcones/<?php echo htmlspecialchars(urlencode($lc['image'])); ?>" alt="<?php echo htmlspecialchars($lc['name']); ?>">
                        <span><?php echo htmlspecialchars($lc['name']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </main>

</body>
</html>