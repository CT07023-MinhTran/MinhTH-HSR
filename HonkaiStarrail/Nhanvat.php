<?php
// Kết nối Database
$conn = new mysqli("localhost", "root", "", "honkai_star_rail");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// --- Lấy danh sách Paths và Elements ---
$paths = [];
$elements = [];

$pathRes = $conn->query("SELECT id, path FROM paths ORDER BY path ASC");
$elementRes = $conn->query("SELECT id, element FROM elements ORDER BY element ASC");

while ($row = $pathRes->fetch_assoc()) $paths[] = $row["path"];
while ($row = $elementRes->fetch_assoc()) $elements[] = $row["element"];

// --- Xử lý lọc ---
$conditions = [];
$params = [];
$types = "";

$vanmenh = $_GET["vanmenh"] ?? "";
$thuoctinh = $_GET["thuoc_tinh"] ?? "";

if (!empty($vanmenh)) {
    $conditions[] = "p.path = ?";
    $params[] = $vanmenh;
    $types .= "s";
}
if (!empty($thuoctinh)) {
    $conditions[] = "e.element = ?";
    $params[] = $thuoctinh;
    $types .= "s";
}

$whereSQL = "";
if (!empty($conditions)) $whereSQL = "WHERE " . implode(" AND ", $conditions);

// --- Query lấy danh sách nhân vật ---
$sql = "
    SELECT 
        c.*, 
        p.path AS path_name, 
        p.image AS path_icon,
        e.element AS element_name,
        e.image AS element_icon
    FROM characters c
    JOIN paths p ON c.path_id = p.id
    JOIN elements e ON c.element_id = e.id
    $whereSQL
    ORDER BY c.name ASC
";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Lỗi chuẩn bị truy vấn: " . $conn->error);
}
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Danh sách Nhân Vật Honkai Star Rail</title>

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
            margin-bottom: 30px; 
            text-align: center;
            color: var(--primary-color);
        }

        /* Bộ lọc */
        .filter-box { 
            max-width: 100%;
            margin: 0 auto 30px auto;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .filter-box label {
            font-weight: bold;
            color: #333;
            font-size: 0.9em;
            margin-right: -10px;
        }

        .filter-box select,
        .filter-box button {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
        }

        .filter-box button {
            background-color: var(--primary-color);
            color: white;
            cursor: pointer;
        }

        /* Grid 2 cột */
        .character-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
            gap: 24px;
        }

        /* Card nhân vật */
        .character-card {
            background: #fff; 
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex; 
            padding: 16px;
            gap: 20px;
            align-items: flex-start; /* Căn các item từ trên xuống */
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .character-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }

        /* Ảnh đại diện bên trái */
        .avatar {
            width: 160px; 
            height: 160px;
            object-fit: cover; 
            border-radius: 8px;
        }

        /* Khối thông tin bên phải */
        .info-box { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            gap: 8px; /* Khoảng cách giữa các mục con */
        }

        .info-box h3 {
            margin: 0;
            font-size: 1.5em;
        }
        
        /* Icon nhỏ */
        .small-icons { 
            display: flex; 
            gap: 12px; 
            align-items: center; 
            margin-bottom: 8px; 
        }

        .small-icon { 
            width: 40px; 
            height: 40px; 
            object-fit: contain; 
        }

        /* Stats */
        .stats { margin-top: auto; } /* Đẩy khối stats xuống dưới cùng */
        .stat-row { 
            display: flex; 
            gap: 24px; 
            margin-bottom: 4px; 
        }
        .stat-label { 
            font-weight: bold; 
            width: 50px; 
        }

        /* Responsive */
        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            .character-grid { grid-template-columns: 1fr; }
        }

        /* Rarity stars */
        .rarity-stars {
            margin-bottom: 8px;
            line-height: 1;
        }
        .star {
            font-size: 1.3em;
        }
        .star-5 {
            color: #dca753; /* Gold */
        }
        .star-4 {
            color: #a072de; /* Purple */
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">HoYoWiki</div>
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
        <h2>Danh sách Nhân Vật Honkai Star Rail</h2>

        <!-- FORM LỌC -->
        <form method="get" class="filter-box">
            
            <label for="vanmenh">Vận Mệnh:</label>
            <select name="vanmenh" id="vanmenh">
                <option value="">Tất cả</option>
                <?php foreach ($paths as $p): ?>
                    <option value="<?= htmlspecialchars($p) ?>"
                        <?= ($vanmenh === $p) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="thuoc_tinh">Thuộc Tính:</label>
            <select name="thuoc_tinh" id="thuoc_tinh">
                <option value="">Tất cả</option>
                <?php foreach ($elements as $e): ?>
                    <option value="<?= htmlspecialchars($e) ?>"
                        <?= ($thuoctinh === $e) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Lọc</button>
        </form>
        <!-- GRID HIỂN THỊ NHÂN VẬT -->
        <div class="character-grid">

        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($char = $result->fetch_assoc()): ?>

                <div class="character-card">

                    <!-- ẢNH NHÂN VẬT -->
                    <img class="avatar"
                        src="HonkaiStarrail/admin/uploads/characters/<?= htmlspecialchars(urlencode($char['image'])) ?>"
                        alt="<?= htmlspecialchars($char['name']) ?>">

                    <div class="info-box">
                        
                        <h3><?= htmlspecialchars($char['name']) ?></h3>

                        <!-- HIỂN THỊ SAO -->
                        <div class="rarity-stars">
                            <?php
                            $rarity = intval($char['rarity']);
                            if ($rarity > 0) {
                                $star_class = "star star-" . $rarity;
                                for ($i = 0; $i < $rarity; $i++) {
                                    echo '<span class="' . $star_class . '">&#9733;</span>';
                                }
                            }
                            ?>
                        </div>
                        <!-- ICON PATH + ELEMENT -->
                        <div class="small-icons">

                            <img class="small-icon"
                                src="HonkaiStarrail/admin/uploads/paths/<?= htmlspecialchars(urlencode($char['path_icon'])) ?>"
                                alt="Path Icon"
                                title="<?= htmlspecialchars($char['path_name']) ?>">

                            <img class="small-icon"
                                src="HonkaiStarrail/admin/uploads/elements/<?= htmlspecialchars(urlencode($char['element_icon'])) ?>"
                                alt="Element Icon"
                                title="<?= htmlspecialchars($char['element_name']) ?>">

                        </div>

                        <!-- THUỘC TÍNH -->
                        <div class="stats">
                            <div class="stat-row">
                                <span class="stat-label">HP:</span><span><?= $char['hp'] ?></span>
                                <span class="stat-label">ATK:</span><span><?= $char['atk'] ?></span>
                            </div>

                            <div class="stat-row">
                                <span class="stat-label">DEF:</span><span><?= $char['def'] ?></span>
                                <span class="stat-label">SPD:</span><span><?= $char['spd'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endwhile; ?>

        <?php else: ?>
            <p>Không có nhân vật nào phù hợp.</p>
        <?php endif; ?>

        </div> <!-- END GRID -->
    </main>

<?php 
$stmt->close(); 
$conn->close();
?>
</body>
</html>
