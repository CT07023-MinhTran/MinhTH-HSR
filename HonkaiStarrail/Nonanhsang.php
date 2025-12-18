<?php
// 1. Kết nối Database
$conn = new mysqli("localhost", "root", "", "honkai_star_rail");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 2. Lấy các giá trị lọc từ URL (nếu có)
$selected_rarity = isset($_GET['rarity']) ? $_GET['rarity'] : '';
$selected_path_id = isset($_GET['path_id']) ? $_GET['path_id'] : '';

// 3. Lấy danh sách Vận mệnh để hiển thị trong bộ lọc
$paths_result = $conn->query("SELECT id, path FROM paths ORDER BY path ASC");
$paths = [];
if ($paths_result->num_rows > 0) {
    while ($row = $paths_result->fetch_assoc()) {
        $paths[] = $row;
    }
}

// 4. Xây dựng câu truy vấn SQL động dựa trên bộ lọc
$sql = "
    SELECT 
        lc.*, 
        p.path AS path_name, 
        p.image AS path_icon
    FROM lightcones lc
    LEFT JOIN paths p ON lc.path_id = p.id";

$conditions = [];
$params = [];
$types = '';

if ($selected_rarity !== '') {
    $conditions[] = "lc.rarity = ?";
    $params[] = $selected_rarity;
    $types .= 'i';
}

if ($selected_path_id !== '') {
    $conditions[] = "lc.path_id = ?";
    $params[] = $selected_path_id;
    $types .= 'i';
}

if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY lc.rarity DESC, lc.name ASC";

// 5. Sử dụng Prepared Statement để thực thi truy vấn an toàn
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Lỗi chuẩn bị truy vấn: " . $conn->error);
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Danh sách Nón Ánh Sáng - Honkai Star Rail</title>
    <style>
        :root {
            --primary-color: #1e3a56;
            --secondary-color: #f0f2f5;
            --text-color: #333;
            --sidebar-bg: #fff;
            --sidebar-width: 240px;
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

        .sidebar-nav ul { list-style: none; }
        .sidebar-nav li { margin-bottom: 15px; }
        .sidebar-nav a {
            text-decoration: none;
            color: var(--text-color);
            font-size: 1.1em;
            padding: 10px 15px;
            display: block;
            border-radius: 8px;
            transition: background-color 0.2s, color 0.2s;
        }
        .sidebar-nav a:hover { background-color: var(--primary-color); color: #fff; }

        /* --- Main Content --- */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 40px;
        }
        h2 { 
            text-align: center;
            margin-bottom: 30px; 
            color: #1e3a56;
        }

        /* Filter Form Styles */
        .filter-container {
            max-width: 100%;
            margin: 0 auto 30px auto;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-weight: bold;
            color: #333;
            font-size: 0.9em;
        }

        .filter-group select, .filter-container button {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
        }

        .lightcone-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(550px, 1fr));
            gap: 24px;            
            max-width: 100%;
            margin: 0 auto;
        }

        .lightcone-card {
            background: #fff; 
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex; 
            padding: 20px;
            gap: 20px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .lightcone-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }

        /* Vùng ảnh bên trái */
        .image-container {
            width: 150px;
            height: 150px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            padding: 8px;
        }

        .image-container.rarity-bg-5 {
            background: linear-gradient(135deg, #f8e6a0, #dca753); /* Gold gradient */
        }

        .image-container.rarity-bg-4 {
            background: linear-gradient(135deg, #c3a4f3, #a072de); /* Purple gradient */
        }

        .lightcone-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 4px;
        }

        /* Khối thông tin bên phải */
        .info-container { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            gap: 12px;
        }

        .info-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .info-header h3 {
            margin: 0 0 8px 0;
            font-size: 1.4em;
            color: #1e3a56;
        }

        .path-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .path-icon {
            width: 28px;
            height: 28px;
        }

        .rarity-stars {
            margin-left: auto;
            line-height: 1;
        }

        .star { font-size: 1.2em; }
        .star-5 { color: #dca753; }
        .star-4 { color: #a072de; }

        .path-effect {
            font-style: italic;
            color: #555;
            line-height: 1.5;
            background: #f9f9f9;
            padding: 10px;
            border-radius: 6px;
            flex-grow: 1; /* Đẩy khối stats xuống dưới */
        }

        .stats-footer {
            display: flex;
            justify-content: space-around;
            background: #f0f2f5;
            padding: 8px;
            border-radius: 6px;
            margin-top: auto; /* Đẩy xuống dưới cùng */
        }

        .stat { text-align: center; }
        .stat-label { font-weight: bold; font-size: 0.9em; color: #666; }
        .stat-value { font-size: 1.1em; font-weight: 500; }

        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            .lightcone-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 600px) {
            .lightcone-card { flex-direction: column; }
            .image-container { width: 120px; height: 120px; margin: 0 auto; }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">HoYoWiki</div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="Nhanvat.php">Nhân Vật</a></li>
                <li><a href="Nonanhsang.php">Nón Ánh Sáng</a></li>
                <li><a href="Divat.php">Di Vật</a></li>
                <li><a href="tierlist.php">Tier List</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <h2>Danh sách Nón Ánh Sáng</h2>

        <!-- Filter Form -->
        <div class="filter-container">
            <form method="GET" action="" style="display: flex; gap: 20px; align-items: flex-end;">
                <div class="filter-group">
                    <label for="rarity">Độ hiếm</label>
                    <select name="rarity" id="rarity">
                        <option value="">Tất cả</option>
                        <option value="5" <?php if ($selected_rarity == '5') echo 'selected'; ?>>5 ★</option>
                        <option value="4" <?php if ($selected_rarity == '4') echo 'selected'; ?>>4 ★</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="path_id">Vận mệnh</label>
                    <select name="path_id" id="path_id">
                        <option value="">Tất cả</option>
                        <?php foreach ($paths as $path): ?>
                            <option value="<?php echo $path['id']; ?>" <?php if ($selected_path_id == $path['id']) echo 'selected'; ?>><?php echo htmlspecialchars($path['path']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Lọc</button>
            </form>
        </div>

        <div class="lightcone-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($lc = $result->fetch_assoc()): ?>
                    <?php
                        // Xác định class CSS cho nền dựa trên độ hiếm
                        $rarity_bg_class = 'rarity-bg-' . htmlspecialchars($lc['rarity']);
                    ?>
                    <div class="lightcone-card">
                        <!-- Hình ảnh bên trái -->
                        <div class="image-container <?php echo $rarity_bg_class; ?>">
                            <img class="lightcone-image" src="HonkaiStarrail/admin/uploads/lightcones/<?php echo htmlspecialchars($lc['image']); ?>" alt="<?php echo htmlspecialchars($lc['name']); ?>">
                        </div>

                        <!-- Thông tin bên phải -->
                        <div class="info-container">
                            <!-- Ô trên: Tên, Vận mệnh, Độ hiếm -->
                            <div class="info-header">
                                <h3><?php echo htmlspecialchars($lc['name']); ?></h3>
                                <div class="path-info">
                                    <img class="path-icon" src="HonkaiStarrail/admin/uploads/paths/<?php echo htmlspecialchars($lc['path_icon']); ?>" title="<?php echo htmlspecialchars($lc['path_name']); ?>">
                                    <span><?php echo htmlspecialchars($lc['path_name']); ?></span>
                                    <div class="rarity-stars">
                                        <?php
                                        $rarity = intval($lc['rarity']);
                                        $star_class = "star star-" . $rarity;
                                        for ($i = 0; $i < $rarity; $i++) {
                                            echo '<span class="' . $star_class . '">&#9733;</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Ô giữa: Hiệu ứng -->
                            <div class="path-effect">
                                <?php echo nl2br(htmlspecialchars($lc['path_effect'])); ?>
                            </div>

                            <!-- Ô dưới: Chỉ số -->
                            <div class="stats-footer">
                                <div class="stat"><span class="stat-label">HP:</span> <span class="stat-value"><?php echo htmlspecialchars($lc['hp']); ?></span></div>
                                <div class="stat"><span class="stat-label">ATK:</span> <span class="stat-value"><?php echo htmlspecialchars($lc['atk']); ?></span></div>
                                <div class="stat"><span class="stat-label">DEF:</span> <span class="stat-value"><?php echo htmlspecialchars($lc['def']); ?></span></div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Không có Nón Ánh Sáng nào trong cơ sở dữ liệu.</p>
            <?php endif; ?>
        </div>
    </main>

<?php 
$stmt->close();
$conn->close(); 
?>
</body>
</html>