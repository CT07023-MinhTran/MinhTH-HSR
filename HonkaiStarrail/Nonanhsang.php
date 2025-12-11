<?php
// 1. Kết nối Database
$conn = new mysqli("localhost", "root", "", "honkai_star_rail");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 2. Query lấy danh sách Nón Ánh Sáng
$sql = "
    SELECT 
        lc.*, 
        p.path AS path_name, 
        p.image AS path_icon
    FROM lightcones lc
    LEFT JOIN paths p ON lc.path_id = p.id
    ORDER BY lc.rarity DESC, lc.name ASC
";

$result = $conn->query($sql);
if ($result === false) {
    die("Lỗi truy vấn: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Danh sách Nón Ánh Sáng - Honkai Star Rail</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #f0f2f5; 
            padding: 20px;
            color: #333;
        }

        h2 { 
            text-align: center;
            margin-bottom: 30px; 
            color: #1e3a56;
        }

        .lightcone-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(550px, 1fr));
            gap: 24px;
            max-width: 1200px;
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

        @media (max-width: 600px) {
            .lightcone-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<h2>Danh sách Nón Ánh Sáng</h2>

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
                    <img class="lightcone-image" src="HonkaiStarrail/admin/<?php echo htmlspecialchars($lc['image']); ?>" alt="<?php echo htmlspecialchars($lc['name']); ?>">
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

<?php $conn->close(); ?>
</body>
</html>