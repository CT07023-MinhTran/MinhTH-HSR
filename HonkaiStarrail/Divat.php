<?php
/**
 * Trang hiển thị danh sách các di vật từ cơ sở dữ liệu.
 * Đã được nâng cấp với giao diện thẻ và chức năng lọc/tìm kiếm động.
 */

// --- 1. THIẾT LẬP KẾT NỐI CSDL ---
// Tạo kết nối
$conn = new mysqli("localhost", "root", "", "honkai_star_rail");

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
// Đặt charset thành utf8mb4 để hỗ trợ đầy đủ tiếng Việt
$conn->set_charset("utf8mb4");

// --- 2. TRUY VẤN DỮ LIỆU ---
// Sắp xếp theo tên để đảm bảo thứ tự nhất quán.
$sql = "SELECT * FROM relics ORDER BY name ASC";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Lỗi chuẩn bị truy vấn: " . $conn->error);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Danh sách Di Vật - Honkai Star Rail</title>
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
            flex-grow: 1;
        }
        .filter-group label {
            font-weight: bold;
            color: #333;
            font-size: 0.9em;
        }
        .filter-group select, .filter-group input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            width: 100%;
        }

        .relic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(550px, 1fr));
            gap: 24px;
            max-width: 100%;
            margin: 0 auto;
        }
        .relic-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex;
            padding: 20px;
            gap: 20px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .relic-card:hover {
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
            background: linear-gradient(135deg, #e0e0e0, #b0b0b0); /* Nền xám trung tính */
        }
        .relic-image {
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
            margin: 0;
            font-size: 1.4em;
            font-weight: bold;
            color: #1e3a56;
        }
        .relic-type {
            font-size: 0.9em;
            font-style: italic;
            color: #6c757d;
            margin-top: 4px;
        }
        .effect-box {
            line-height: 1.5;
            background: #f9f9f9;
            padding: 12px;
            border-radius: 6px;
        }
        .effect-box strong {
            color: #1e3a56;
            display: block;
            margin-bottom: 5px;
        }
        @media (max-width: 600px) {
            .relic-card { flex-direction: column; }
            .image-container { width: 120px; height: 120px; margin: 0 auto; }
        }
        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            .relic-grid { grid-template-columns: 1fr; }
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
        <h2>Danh sách Di Vật</h2>

        <!-- Filter and Search Form -->
        <div class="filter-container">
            <div class="filter-group">
                <label for="search-text">Tìm kiếm theo tên</label>
                <input type="text" id="search-text" placeholder="Nhập tên di vật...">
            </div>
            <div class="filter-group">
                <label for="filter-type">Loại</label>
                <select id="filter-type">
                    <option value="">Tất cả</option>
                    <option value="Relic">Di Vật</option>
                    <option value="Planetary Ornament Set">Phụ Kiện Vị Diện</option>
                </select>
            </div>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="relic-grid">
                <?php while($relic = $result->fetch_assoc()): ?>
                    <div class="relic-card" data-name="<?php echo strtolower(htmlspecialchars($relic['name'])); ?>" data-type="<?php echo htmlspecialchars($relic['type']); ?>">
                        <!-- Hình ảnh bên trái -->
                        <div class="image-container">
                            <img class="relic-image" src="HonkaiStarrail/admin/uploads/relics/<?php echo htmlspecialchars(urlencode($relic['icon'])); ?>" alt="<?php echo htmlspecialchars($relic['name']); ?>">
                        </div>

                        <!-- Thông tin bên phải -->
                        <div class="info-container">
                            <div class="info-header">
                                <h3><?php echo htmlspecialchars($relic['name']); ?></h3>
                                <div class="relic-type"><?php echo ($relic['type'] === 'Relic') ? 'Di Vật' : 'Phụ Kiện Vị Diện'; ?></div>
                            </div>

                            <div class="effect-box">
                                <strong><?php echo ($relic['type'] === 'Relic') ? 'Hiệu ứng 2 món' : 'Hiệu ứng bộ'; ?>:</strong>
                                <span><?php echo nl2br(htmlspecialchars($relic['set2_effect'])); ?></span>
                            </div>

                            <?php if ($relic['type'] === 'Relic' && !empty($relic['set4_effect'])): ?>
                                <div class="effect-box">
                                    <strong>Hiệu ứng 4 món:</strong>
                                    <span><?php echo nl2br(htmlspecialchars($relic['set4_effect'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center;">Không có bộ di vật nào trong cơ sở dữ liệu.</p>
        <?php endif; ?>

        <p id="no-results" style="text-align: center; display: none; margin-top: 20px;">Không tìm thấy bộ di vật nào phù hợp.</p>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search-text');
            const typeFilter = document.getElementById('filter-type');
            const relicGrid = document.querySelector('.relic-grid');
            const relicCards = document.querySelectorAll('.relic-card');
            const noResultsMessage = document.getElementById('no-results');

            function filterRelics() {
                const searchText = searchInput.value.toLowerCase().trim();
                const selectedType = typeFilter.value;
                let visibleCount = 0;

                relicCards.forEach(card => {
                    const cardName = card.getAttribute('data-name');
                    const cardType = card.getAttribute('data-type');

                    const nameMatch = cardName.includes(searchText);
                    const typeMatch = selectedType === '' || cardType === selectedType;

                    if (nameMatch && typeMatch) {
                        card.style.display = 'flex';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (visibleCount === 0) {
                    noResultsMessage.style.display = 'block';
                } else {
                    noResultsMessage.style.display = 'none';
                }
            }

            searchInput.addEventListener('input', filterRelics);
            typeFilter.addEventListener('change', filterRelics);
        });
    </script>
</body>
</html>

<?php
// --- 3. ĐÓNG KẾT NỐI ---
$stmt->close();
$conn->close();
?>