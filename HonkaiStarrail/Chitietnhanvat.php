<?php
// Trang chi tiết nhân vật Honkai Star Rail
// Kết nối DB
$conn = new mysqli("localhost", "root", "", "honkai_star_rail");
if ($conn->connect_error) die("Kết nối thất bại: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// Lấy id nhân vật từ URL
$character_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($character_id <= 0) die("Thiếu thông tin nhân vật.");

// Lấy thông tin nhân vật
$sql = "SELECT c.*, p.path AS path_name, p.image AS path_icon, e.element AS element_name, e.image AS element_icon FROM characters c JOIN paths p ON c.path_id = p.id JOIN elements e ON c.element_id = e.id WHERE c.id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Lỗi truy vấn nhân vật: " . $conn->error);
$stmt->bind_param("i", $character_id);
$stmt->execute();
$char = $stmt->get_result()->fetch_assoc();
if (!$char) die("Không tìm thấy nhân vật.");
$stmt->close();

// Lấy build của nhân vật
$build = null;
$stmt = $conn->prepare("SELECT * FROM builds WHERE character_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $character_id);
    $stmt->execute();
    $build = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi tiết nhân vật - <?= htmlspecialchars($char['name']) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .detail-container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); padding: 32px; }
        .detail-header { display: flex; gap: 32px; align-items: center; margin-bottom: 32px; }
        .detail-avatar { width: 220px; height: 220px; object-fit: cover; border-radius: 12px; }
        .detail-info { flex: 1; }
        .detail-info h2 { margin: 0 0 12px 0; font-size: 2em; color: #1e3a56; }
        .small-icons { display: flex; gap: 18px; align-items: center; margin-bottom: 12px; }
        .small-icon { width: 48px; height: 48px; object-fit: contain; }
        .stats { margin-bottom: 18px; }
        .stat-row { display: flex; gap: 32px; margin-bottom: 6px; }
        .stat-label { font-weight: bold; width: 60px; }
        .rarity-stars { margin-bottom: 10px; }
        .star { font-size: 1.5em; }
        .star-5 { color: #dca753; }
        .star-4 { color: #a072de; }
        .section-title { font-size: 1.2em; font-weight: bold; margin-top: 32px; margin-bottom: 12px; color: #1e3a56; }
        .build-box { background: #f8f9fb; border-radius: 10px; padding: 18px; margin-bottom: 24px; }
        .build-row { margin-bottom: 10px; }
        .build-label { font-weight: bold; color: #333; }
        .build-value { color: #444; }
        .team-list { display: flex; flex-wrap: wrap; gap: 18px; }
        .team-item { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 10px 18px; min-width: 180px; }
        .lightcone-list { margin-top: 8px; }
        .lightcone-item { display: flex; align-items: center; gap: 14px; margin-bottom: 8px; background: #232136; padding: 10px 16px; border-radius: 8px; }
        .lc-power { font-weight: bold; font-size: 1.25em; color: #ff6ad5; min-width: 70px; }
        .lc-name { font-weight: bold; color: #ffe066; }
        .lc-usage { margin-left: 18px; color: #7ed6df; font-size: 0.98em; }
    </style>
</head>
<body>
    <div class="detail-container">
        <div class="detail-header">
            <img class="detail-avatar" src="HonkaiStarrail/admin/uploads/characters/<?= htmlspecialchars(urlencode($char['image'])) ?>" alt="<?= htmlspecialchars($char['name']) ?>">
            <div class="detail-info">
                <h2><?= htmlspecialchars($char['name']) ?></h2>
                <div class="rarity-stars">
                    <?php $rarity = intval($char['rarity']); if ($rarity > 0) { $star_class = "star star-" . $rarity; for ($i = 0; $i < $rarity; $i++) echo '<span class="' . $star_class . '">&#9733;</span>'; } ?>
                </div>
                <div class="small-icons">
                    <img class="small-icon" src="HonkaiStarrail/admin/uploads/paths/<?= htmlspecialchars(urlencode($char['path_icon'])) ?>" alt="Path Icon" title="<?= htmlspecialchars($char['path_name']) ?>">
                    <img class="small-icon" src="HonkaiStarrail/admin/uploads/elements/<?= htmlspecialchars(urlencode($char['element_icon'])) ?>" alt="Element Icon" title="<?= htmlspecialchars($char['element_name']) ?>">
                </div>
                <div class="stats">
                    <div class="stat-row"><span class="stat-label">HP:</span><span><?= $char['hp'] ?></span> <span class="stat-label">ATK:</span><span><?= $char['atk'] ?></span></div>
                    <div class="stat-row"><span class="stat-label">DEF:</span><span><?= $char['def'] ?></span> <span class="stat-label">SPD:</span><span><?= $char['spd'] ?></span></div>
                </div>
                <div class="build-row"><span class="build-label">Mô tả:</span> <span class="build-value"><?= htmlspecialchars($char['description']) ?></span></div>
            </div>
        </div>
        <div class="section-title">Build đề xuất</div>
        <?php if ($build): ?>
        <div class="build-box">
            <!-- Nón Ánh Sáng -->
            <div class="build-row" style="margin-bottom:18px;">
                <span class="build-label" style="display:block;margin-bottom:8px;">Nón Ánh Sáng:</span>
                <div class="lightcone-list">
                    <?php
                    // Chuẩn bị truy vấn lấy hình ảnh và path_effect nón ánh sáng
                    function get_lightcone_info($conn, $name) {
                        $sql = "SELECT image, path_effect, rarity FROM lightcones WHERE name = ? LIMIT 1";
                        $stmt = $conn->prepare($sql);
                        if (!$stmt) return [null, null, null];
                        $stmt->bind_param("s", $name);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $img = null;
                        $effect = null;
                        $rarity = null;
                        if ($row = $result->fetch_assoc()) {
                            $img = $row['image'];
                            $effect = $row['path_effect'];
                            $rarity = $row['rarity'];
                        }
                        $stmt->close();
                        return [$img, $effect, $rarity];
                    }
                    // Kết nối lại DB chỉ để lấy ảnh và path_effect nón ánh sáng (nếu cần)
                    $conn_lc = new mysqli("localhost", "root", "", "honkai_star_rail");
                    $conn_lc->set_charset("utf8mb4");
                    for ($i = 1; $i <= 3; $i++):
                        $name = trim($build["lightcone{$i}"] ?? '');
                        $power = isset($build["lightcone{$i}_power"]) && $build["lightcone{$i}_power"] !== null ? floatval($build["lightcone{$i}_power"]) : null;
                        $rate = isset($build["lightcone{$i}_rate"]) && $build["lightcone{$i}_rate"] !== null ? floatval($build["lightcone{$i}_rate"]) : null;
                        if (!$name) continue;

                        // Lấy hình ảnh, path_effect, rarity từ DB
                        list($img_file, $path_effect, $lc_rarity) = get_lightcone_info($conn_lc, $name);
                        if ($img_file && file_exists("HonkaiStarrail/admin/uploads/lightcones/" . $img_file)) {
                            $img_path = "HonkaiStarrail/admin/uploads/lightcones/" . $img_file;
                        } else {
                            $img_path = "HonkaiStarrail/admin/default_lightcone.png";
                        }
                        $effect_id = "lc-effect-{$i}";
                        // Xác định màu tên theo rarity
                        $lc_name_color = "#ffe066"; // mặc định vàng
                        if ($lc_rarity == 4) $lc_name_color = "#a072de";
                        if ($lc_rarity == 5) $lc_name_color = "#ffe066";
                    ?>
                    <div class="lightcone-item" style="display:flex;align-items:center;gap:14px;margin-bottom:8px;background:#ffffff;padding:10px 16px;border-radius:8px;flex-wrap:wrap;">
                        <span class="lc-power" style="font-weight:bold;font-size:1.25em;color:#ff6ad5;min-width:70px;"><?= $power !== null ? number_format($power, 2) . '%' : '' ?></span>
                        <img src="<?= htmlspecialchars($img_path) ?>"
                             alt="<?= htmlspecialchars($name) ?>"
                             style="width:48px;height:48px;object-fit:cover;border-radius:6px;background:#222;"
                             onerror="this.onerror=null;this.src='HonkaiStarrail/admin/default_lightcone.png';">
                        <span class="lc-name" style="font-weight:bold;color:<?= $lc_name_color ?>;"><?= htmlspecialchars($name) ?></span>
                        <?php if ($rate !== null): ?>
                            <span class="lc-usage" style="margin-left:18px;color:#7ed6df;font-size:0.98em;">Tỉ Lệ Sử Dụng: <?= number_format($rate, 2) ?>%</span>
                        <?php endif; ?>
                        <?php if ($path_effect): ?>
                            <button type="button" class="lc-effect-toggle" data-target="<?= $effect_id ?>" style="margin-left:18px;padding:2px 10px;border-radius:6px;border:none;background:#ffe066;color:#232136;cursor:pointer;font-size:0.95em;">Xem hiệu ứng</button>
                            <div id="<?= $effect_id ?>" class="lc-effect-content" style="display:none;width:100%;margin-top:8px;background:#fffbe6;color:#232136;padding:10px 14px;border-radius:6px;font-size:0.98em;">
                                <?= nl2br(htmlspecialchars($path_effect)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endfor;
                    $conn_lc->close();
                    ?>
                </div>
            </div>
            <!-- Di Vật -->
            <div class="build-row"><span class="build-label">Di Vật:</span> <span class="build-value"><?= htmlspecialchars($build['relic1_set'] ?? '') ?><?= $build['relic2_set'] ? ', ' . htmlspecialchars($build['relic2_set']) : '' ?><?= $build['relic3_set'] ? ', ' . htmlspecialchars($build['relic3_set']) : '' ?></span></div>
            <div class="build-row"><span class="build-label">Phụ Kiện:</span> <span class="build-value"><?= htmlspecialchars($build['ornament1'] ?? '') ?><?= $build['ornament2'] ? ', ' . htmlspecialchars($build['ornament2']) : '' ?><?= $build['ornament3'] ? ', ' . htmlspecialchars($build['ornament3']) : '' ?></span></div>
            <div class="build-row"><span class="build-label">Chỉ số chính:</span> <span class="build-value">Body: <?= htmlspecialchars($build['mainstat_body'] ?? '') ?>, Boots: <?= htmlspecialchars($build['mainstat_boots'] ?? '') ?>, Sphere: <?= htmlspecialchars($build['mainstat_sphere'] ?? '') ?>, Rope: <?= htmlspecialchars($build['mainstat_rope'] ?? '') ?></span></div>
            <div class="build-row"><span class="build-label">Chỉ số phụ ưu tiên:</span> <span class="build-value"><?= htmlspecialchars($build['substats'] ?? '') ?></span></div>
            <div class="build-row"><span class="build-label">Chỉ số hướng tới:</span> <span class="build-value"><?= htmlspecialchars($build['target_stats'] ?? '') ?></span></div>
        </div>
        <div class="section-title">Đội hình đề xuất</div>
        <div class="team-list">
            <?php for ($t = 1; $t <= 10; $t++): ?>
                <?php $team = []; for ($s = 1; $s <= 4; $s++) { if (!empty($build["team{$t}_{$s}"])) $team[] = htmlspecialchars($build["team{$t}_{$s}"]); } ?>
                <?php if ($team): ?>
                    <div class="team-item">Đội <?= $t ?>: <?= implode(' - ', $team) ?></div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php else: ?>
            <div class="build-box">Chưa có build đề xuất cho nhân vật này.</div>
        <?php endif; ?>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.lc-effect-toggle').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var target = document.getElementById(btn.getAttribute('data-target'));
                if (target) {
                    target.style.display = (target.style.display === 'none' || target.style.display === '') ? 'block' : 'none';
                }
            });
        });
    });
    </script>
</body>
</html>