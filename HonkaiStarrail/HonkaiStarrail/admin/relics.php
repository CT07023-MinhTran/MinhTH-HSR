<?php
// 1. Kết nối CSDL - Sử dụng thông tin đăng nhập chuẩn của XAMPP
$conn = new mysqli("localhost", "root", "", "honkai_star_rail");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 3. Khởi tạo biến
$id = $name = $icon = $type = $set2_effect = $set4_effect = "";
$edit = false;
$search = isset($_GET['search']) ? trim($_GET['search']) : ""; // <-- Thêm biến tìm kiếm

// 4. Xử lý dữ liệu từ form (Thêm/Sửa)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // KIỂM TRA LỖI UPLOAD QUAN TRỌNG: Xảy ra khi file quá lớn so với 'post_max_size' trong php.ini
    if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        die("Lỗi: Dữ liệu gửi lên quá lớn. Có thể file ảnh bạn chọn có dung lượng vượt quá giới hạn cho phép của máy chủ. Vui lòng kiểm tra lại dung lượng file hoặc cấu hình 'post_max_size' trong file php.ini.");
    }

    // Sử dụng trim() để loại bỏ khoảng trắng thừa từ dữ liệu người dùng nhập
    $name = trim($_POST["name"]);
    $type = trim($_POST["type"]);
    $set2_effect = trim($_POST["set2_effect"]);
    // Nếu là "Phụ Kiện Vị Diện", hiệu quả bộ 4 sẽ không có
    $set4_effect = ($type == "Relic") ? trim($_POST["set4_effect"]) : null;

    // Xử lý upload ảnh
    if (isset($_FILES["icon"]) && $_FILES["icon"]["error"] == 0) {
        $target_dir = "uploads/relics/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $image_basename = basename($_FILES["icon"]["name"]);
        move_uploaded_file($_FILES["icon"]["tmp_name"], $target_dir . $image_basename);
        $icon = $image_basename;
    } else if (!empty($_POST["old_icon"])) {
        $icon = basename($_POST["old_icon"]);
    }

    if (isset($_POST["id"]) && !empty($_POST["id"])) {
        // Cập nhật (Sửa)
        $id = $_POST["id"];
        $stmt = $conn->prepare("UPDATE relics SET name=?, icon=?, type=?, set2_effect=?, set4_effect=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("sssssi", $name, $icon, $type, $set2_effect, $set4_effect, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Prepare failed (UPDATE): " . $conn->error);
        }
    } else {
        // Thêm mới
        $stmt = $conn->prepare("INSERT INTO relics (name, icon, type, set2_effect, set4_effect) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssss", $name, $icon, $type, $set2_effect, $set4_effect);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Prepare failed (INSERT): " . $conn->error);
        }
    }
    header("Location: relics.php");
    exit;
}

// 5. Lấy dữ liệu để sửa
if (isset($_GET["edit"])) {
    $edit = true;
    $id = intval($_GET["edit"]);
    $result = $conn->query("SELECT * FROM relics WHERE id=$id");
    if ($row = $result->fetch_assoc()) {
        $name = $row["name"];
        $icon = $row["icon"];
        $type = $row["type"];
        $set2_effect = $row["set2_effect"];
        $set4_effect = $row["set4_effect"];

        if ($icon && strpos($icon, 'uploads/relics/') === false) {
            $icon = 'uploads/relics/' . $icon;
        }
    }
}

// 6. Lấy danh sách tất cả di vật để hiển thị
// Nếu có từ khóa tìm kiếm thì dùng prepared statement với LIKE, ngược lại trả về tất cả
if ($search !== "") {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare("SELECT * FROM relics WHERE name LIKE ? GROUP BY name ORDER BY name ASC");
    if ($stmt) {
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $list_query = $stmt->get_result();
        $stmt->close();
    } else {
        die("Prepare failed (SELECT with search): " . $conn->error);
    }
} else {
    $list_query = $conn->query("SELECT * FROM relics GROUP BY name ORDER BY name ASC");
}

$relics_list = [];
$ornaments_list = [];
if ($list_query && $list_query->num_rows > 0) {
    while ($row = $list_query->fetch_assoc()) {
        if (!empty($row['icon']) && strpos($row['icon'], 'uploads/relics/') === false) {
            $row['icon'] = 'uploads/relics/' . $row['icon'];
        }
        // Sử dụng trim() để loại bỏ khoảng trắng và so sánh an toàn hơn
        if (isset($row['type']) && trim($row['type']) == 'Planetary Ornament Set') {
            $ornaments_list[] = $row;
        } else {
            // Ngược lại, mặc định coi là 'Di Vật' (bao gồm cả dữ liệu cũ có type=NULL).
            $relics_list[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Di vật</title>
    <link rel="stylesheet" href="honkai.css">
    <style>
        .back-to-top-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #333;
            color: white;
            text-align: center;
            line-height: 40px;
            font-size: 24px;
            z-index: 1000;
            text-decoration: none;
            transition: background-color 0.3s, opacity 0.3s;
        }
        .back-to-top-btn:hover {
            background-color: #555;
        }
    </style>
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
                <li><a href="relics.php" class="active">Di vật</a></li>
                <li><a href="materials.php">Nguyên liệu</a></li>
                <li><a href="builds.php">Build</a></li>
                <li><a href="teams.php">Đội hình</a></li>
            </ul>
        </div>
        <div class="admin-main">
            <h2><?php echo $edit ? "Sửa Di vật" : "Thêm Di vật"; ?></h2>
            <form class="admin-form" method="post" enctype="multipart/form-data">
                <?php if ($edit): ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label>Hình ảnh:</label>
                    <input type="file" name="icon">
                    <?php if ($icon): ?>
                        <img src="<?php echo htmlspecialchars($icon); ?>" alt="relic image" style="height:40px;vertical-align:middle;">
                        <input type="hidden" name="old_icon" value="<?php echo htmlspecialchars($icon); ?>">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Loại:</label>
                    <span>
                        <label><input type="radio" name="type" value="Relic" required <?php if ($type == 'Relic' || $type == 'Relics' || $type == 'Di Vật' || $type == '') echo 'checked'; ?>> Di Vật</label>
                        <label style="margin-left:20px;"><input type="radio" name="type" value="Planetary Ornament Set" <?php if ($type == 'Planetary Ornament Set' || $type == 'Phụ Kiện Vị Diện') echo 'checked'; ?>> Phụ Kiện Vị Diện</label>
                    </span>
                </div>
                <div class="form-group">
                    <label>Tên Di vật:</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($name); ?>">
                </div>

                <!-- Các ô hiệu quả, sẽ được điều khiển bởi JavaScript -->
                <div id="relic-effects-wrapper">
                    <div class="form-group">
                        <label>Hiệu quả bộ 2 món:</label>
                        <textarea name="set2_effect"
                            required><?php echo htmlspecialchars($set2_effect); ?></textarea>
                    </div>
                    <div class="form-group" id="effect-4-piece-group"
                        style="display: <?php echo ($type == 'Relic' || $type == 'Relics' || $type == 'Di Vật' || $type == '') ? 'block' : 'none'; ?>;">
                        <label>Hiệu quả bộ 4 món:</label>
                        <textarea
                            name="set4_effect"><?php echo htmlspecialchars($set4_effect); ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn-save">Lưu</button>
            </form>

            <!-- Thêm form tìm kiếm -->
            <form method="get" id="relic-search-form" class="admin-search" style="margin:20px 0;">
                <input id="relic-search-input" type="text" name="search" placeholder="Tìm kiếm Di Vật/Phụ Kiện Vị Diện" value="<?php echo htmlspecialchars($search); ?>" style="padding:6px;width:240px;">
                <?php if ($search !== ""): ?>
                    <a href="relics.php" class="btn-clear" style="margin-left:8px;padding:6px 10px;display:inline-block;text-decoration:none;background:#ddd;color:#000;border-radius:3px;">Xóa</a>
                <?php endif; ?>
            </form>

            <h3>Danh sách Di Vật</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="col-image">Ảnh</th>
                        <th>Tên</th>
                        <th>Hiệu quả bộ 2</th>
                        <th>Hiệu quả bộ 4</th>
                        <th class="col-action">Sửa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($relics_list)): ?>
                        <?php foreach ($relics_list as $row): ?>
                        <tr>
                            <td class="col-image"><?php if ($row["icon"]) echo '<img src="' . htmlspecialchars($row["icon"]) . '" alt="'.htmlspecialchars($row["name"]).'" style="height:40px;">'; ?></td>
                            <td><?php echo htmlspecialchars($row["name"]); ?></td>
                            <td><?php if (!empty($row["set2_effect"])) echo nl2br(htmlspecialchars($row["set2_effect"])); ?></td>
                            <td><?php if (!empty($row["set4_effect"])) echo nl2br(htmlspecialchars($row["set4_effect"])); ?></td>
                            <td class="col-action"><a href="?edit=<?php echo $row["id"]; ?>" class="btn-edit">Sửa</a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">Chưa có Di Vật nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h3 style="margin-top: 40px;">Danh sách Phụ Kiện Vị Diện</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="col-image">Ảnh</th>
                        <th>Tên</th>
                        <th>Hiệu quả bộ 2</th>
                        <th class="col-action">Sửa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($ornaments_list)): ?>
                        <?php foreach ($ornaments_list as $row): ?>
                        <tr>
                            <td class="col-image"><?php if ($row["icon"]) echo '<img src="' . htmlspecialchars($row["icon"]) . '" alt="'.htmlspecialchars($row["name"]).'" style="height:40px;">'; ?></td>
                            <td><?php echo htmlspecialchars($row["name"]); ?></td>
                            <td><?php if (!empty($row["set2_effect"])) echo nl2br(htmlspecialchars($row["set2_effect"])); ?></td>
                            <td class="col-action"><a href="?edit=<?php echo $row["id"]; ?>" class="btn-edit">Sửa</a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">Chưa có Phụ Kiện Vị Diện nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Nút quay về đầu trang -->
    <a href="#" id="back-to-top" class="back-to-top-btn" title="Quay về đầu trang">&#8679;</a>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const typeRadios = document.querySelectorAll('input[name="type"]');
            const effectsWrapper = document.getElementById('relic-effects-wrapper');
            const effect4PieceGroup = document.getElementById('effect-4-piece-group');
            // Tìm kiếm tự động khi gõ (debounce)
            const searchInput = document.getElementById('relic-search-input');
            if (searchInput) {
                let debounceTimeout = null;
                searchInput.addEventListener('input', function () {
                    clearTimeout(debounceTimeout);
                    debounceTimeout = setTimeout(function () {
                        const v = searchInput.value.trim();
                        if (v === "") {
                            // nếu rỗng, chuyển về trang gốc (xóa param)
                            window.location.href = 'relics.php';
                        } else {
                            // đổi URL để gửi param tìm kiếm và reload
                            window.location.href = 'relics.php?search=' + encodeURIComponent(v);
                        }
                    }, 350);
                });
            }

            function toggleEffectFields() {
                const selectedType = document.querySelector('input[name="type"]:checked');
                if (selectedType) {
                    effectsWrapper.style.display = 'block';
                    effect4PieceGroup.style.display = (selectedType.value === 'Relic') ? 'block' : 'none';
                }
            }

            typeRadios.forEach(radio => radio.addEventListener('change', toggleEffectFields));

            // Script cho nút quay về đầu trang
            const backToTopButton = document.getElementById('back-to-top');
            if (backToTopButton) {
                window.onscroll = function() {
                    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
                        backToTopButton.style.display = "block";
                    } else {
                        backToTopButton.style.display = "none";
                    }
                };

                backToTopButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }
        });
    </script>
</body>
</html>