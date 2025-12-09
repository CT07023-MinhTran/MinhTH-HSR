<?php
// 1. Kết nối CSDL
$conn = new mysqli("localhost", "root", "", "honkai_star_rail");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 3. Khởi tạo biến
$id = $name = $image = $path_id = $rarity = $path_effect = $description = $hp = $atk = $def = "";
$edit = false;

// 4. Lấy danh sách Vận Mệnh cho dropdown, tương tự trang characters.php
$paths = [];
$res = $conn->query("SELECT id, path FROM paths ORDER BY path ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $paths[] = ['id' => $row["id"], 'name' => $row["path"]];
    }
}

// 5. Xử lý dữ liệu từ form (Thêm/Sửa)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // KIỂM TRA LỖI UPLOAD QUAN TRỌNG: Xảy ra khi file quá lớn so với 'post_max_size' trong php.ini
    if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        die("Lỗi: Dữ liệu gửi lên quá lớn. Có thể file ảnh bạn chọn có dung lượng vượt quá giới hạn cho phép của máy chủ. Vui lòng kiểm tra lại dung lượng file hoặc cấu hình 'post_max_size' trong file php.ini.");
    }

    // Lấy dữ liệu từ form
    $name = $_POST["name"];
    $path_id = $_POST["path_id"];
    $rarity = $_POST["rarity"];
    $path_effect = $_POST["path_effect"];
    $description = $_POST["description"];
    $hp = $_POST["hp"] ?? null;
    $atk = $_POST["atk"] ?? null;
    $def = $_POST["def"] ?? null;

    // Xử lý upload ảnh
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $target_dir = "uploads/lightcones/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $image_basename = basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $target_dir . $image_basename);
        $image = $image_basename;
    } else if (!empty($_POST["old_image"])) {
        $image = basename($_POST["old_image"]);
    }

    if (isset($_POST["id"]) && !empty($_POST["id"])) {
        // Cập nhật (Sửa)
        $id = $_POST["id"];
        $stmt = $conn->prepare("UPDATE lightcones SET name=?, image=?, path_id=?, rarity=?, path_effect=?, description=?, hp=?, atk=?, def=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("ssisssiiii", $name, $image, $path_id, $rarity, $path_effect, $description, $hp, $atk, $def, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Prepare failed (UPDATE): " . $conn->error);
        }
    } else {
        // Thêm mới
        $stmt = $conn->prepare("INSERT INTO lightcones (name, image, path_id, rarity, path_effect, description, hp, atk, def) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssisssiii", $name, $image, $path_id, $rarity, $path_effect, $description, $hp, $atk, $def);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Prepare failed (INSERT): " . $conn->error);
        }
    }
    header("Location: lightcones.php");
    exit;
}

// 6. Lấy dữ liệu để sửa
if (isset($_GET["edit"])) {
    $edit = true;
    $id = intval($_GET["edit"]);
    $result = $conn->query("SELECT * FROM lightcones WHERE id=$id");
    if ($row = $result->fetch_assoc()) {
        $name = $row["name"];
        $image = $row["image"];
        $path_id = $row["path_id"];
        $rarity = $row["rarity"];
        $path_effect = $row["path_effect"];
        $description = $row["description"];
        $hp = $row["hp"];
        $atk = $row["atk"];
        $def = $row["def"];

        if ($image && strpos($image, 'uploads/lightcones/') === false) {
            $image = 'uploads/lightcones/' . $image;
        }
    }
}

// 7. Lấy danh sách tất cả Nón Ánh Sáng để hiển thị
$list_sql = "
    SELECT lc.*, p.path AS path_name, p.image AS path_image
    FROM lightcones lc
    LEFT JOIN paths p ON lc.path_id = p.id
    GROUP BY lc.name ORDER BY lc.name ASC
";
$list = $conn->query($list_sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Nón Ánh Sáng</title>
    <link rel="stylesheet" href="honkai.css">
    <style>
        /* Tùy chỉnh độ rộng cho các cột trong bảng Nón Ánh Sáng */
        .admin-table .col-name {
            width: 15%;
            min-width: 150px;
        }
        .admin-table .col-path {
            width: 15%;
            min-width: 160px;
        }
        .admin-table .col-description {
            width: 30%;
            min-width: 220px;
        }
        .admin-table .col-effect {
            width: 20%;
            min-width: 180px;
        }
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
                <li><a href="lightcones.php" class="active">Nón Ánh Sáng</a></li>
                <li><a href="relics.php">Di vật</a></li>
                <li><a href="materials.php">Nguyên liệu</a></li>
                <li><a href="builds.php">Build</a></li>
                <li><a href="teams.php">Đội hình</a></li>
            </ul>
        </div>
        <div class="admin-main">
            <h2><?php echo $edit ? "Sửa Nón Ánh Sáng" : "Thêm Nón Ánh Sáng"; ?></h2>
            <form class="admin-form" method="post" enctype="multipart/form-data">
                <?php if ($edit): ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php endif; ?>
                <div class="form-columns">
                    <!-- Cột trái: Thông tin cơ bản -->
                    <div class="form-column">
                        <div class="form-group">
                            <label>Hình ảnh Nón Ánh Sáng:</label>
                            <input type="file" name="image">
                            <?php if ($image): ?>
                                <img src="<?php echo htmlspecialchars($image); ?>" alt="lightcone image" style="height:40px;vertical-align:middle; margin-top: 5px;">
                                <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($image); ?>">
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Tên Nón Ánh Sáng:</label>
                            <input type="text" name="name" required value="<?php echo htmlspecialchars($name); ?>">
                        </div>
                        <div class="form-group">
                            <label>Vận mệnh:</label>
                            <select name="path_id" required>
                                <option value="">-- Chọn vận mệnh --</option>
                                <?php foreach ($paths as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php if ($path_id == $p['id']) echo "selected"; ?>>
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Độ hiếm:</label>
                            <span>
                                <label><input type="radio" name="rarity" value="5" <?php if ($rarity == "5") echo "checked"; ?> required> 5 Sao</label>
                                <label style="margin-left:20px;"><input type="radio" name="rarity" value="4" <?php if ($rarity == "4") echo "checked"; ?>> 4 Sao</label>
                            </span>
                        </div>
                    </div>
                    <!-- Cột phải: Chỉ số và mô tả -->
                    <div class="form-column">
                        <div class="stats-grid">
                            <div class="form-group"><label>HP:</label><input type="number" name="hp" value="<?php echo htmlspecialchars($hp ?? ''); ?>"></div>
                            <div class="form-group"><label>ATK:</label><input type="number" name="atk" value="<?php echo htmlspecialchars($atk ?? ''); ?>"></div>
                            <div class="form-group"><label>DEF:</label><input type="number" name="def" value="<?php echo htmlspecialchars($def ?? ''); ?>"></div>
                        </div>
                        <div class="form-group">
                            <label>Hiệu quả khi đi với Vận Mệnh tương ứng:</label>
                            <textarea name="path_effect"><?php echo htmlspecialchars($path_effect); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Mô tả:</label>
                            <textarea name="description"><?php echo htmlspecialchars($description); ?></textarea>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-save">Lưu</button>
            </form>

            <div style="display:flex; align-items:center; justify-content:space-between; gap:16px;">
                <h3 style="margin:0;">Danh sách Nón Ánh Sáng</h3>
                <div style="margin:0;">
                    <input id="search-input" type="text" placeholder="Tìm kiếm Nón Ánh Sáng" value="" style="padding:6px 8px; width:300px;">
                </div>
            </div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="col-image">Ảnh</th>
                        <th class="col-name">Tên</th>
                        <th class="col-path">Vận mệnh</th>
                        <th class="col-rarity">Độ hiếm</th>
                        <th>HP</th>
                        <th>ATK</th>
                        <th>DEF</th>
                        <th class="col-effect">Hiệu quả</th>
                        <th class="col-description">Mô tả</th>
                        <th class="col-action">Sửa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($list && $list->num_rows > 0): ?>
                        <?php while ($row = $list->fetch_assoc()): 
                            if (!empty($row['image']) && strpos($row['image'], 'uploads/lightcones/') === false) {
                                $row['image'] = 'uploads/lightcones/' . $row['image'];
                            }
                        ?>
                        <tr>
                            <td class="col-image"><?php if ($row["image"]) echo '<img src="'.htmlspecialchars($row["image"]).'" alt="'.htmlspecialchars($row["name"]).'" style="height:40px;">'; ?></td>
                            <td class="col-name"><?php echo htmlspecialchars($row["name"]); ?></td>
                            <td class="col-path">
                                <?php 
                                if (!empty($row["path_image"])) {
                                    // Thêm tiền tố đường dẫn chính xác vào ảnh Vận Mệnh
                                    $path_img_src = 'uploads/paths/' . htmlspecialchars($row["path_image"]);
                                    echo '<img src="'.$path_img_src.'" alt="'.htmlspecialchars($row["path_name"] ?? '').'" style="height:24px; vertical-align:middle; margin-right: 5px;">'; 
                                }
                                ?>
                                <?php echo htmlspecialchars($row["path_name"] ?? 'N/A'); ?>
                            </td>
                            <td class="col-rarity">
                                <?php
                                $rarity = intval($row["rarity"]);
                                $star_class = "star star-" . $rarity; // Tạo class động, ví dụ: "star star-4"
                                for ($i = 0; $i < $rarity; $i++) {
                                    echo '<span class="' . $star_class . '">&#9733;</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row["hp"] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row["atk"] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row["def"] ?? ''); ?></td>
                            <td class="col-effect"><?php echo nl2br(htmlspecialchars($row["path_effect"])); ?></td>
                            <td class="col-description"><?php echo nl2br(htmlspecialchars($row["description"])); ?></td>
                            <td class="col-action"><a href="?edit=<?php echo $row["id"]; ?>" class="btn-edit">Sửa</a></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center;">Chưa có Nón Ánh Sáng nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Nút quay về đầu trang -->
    <a href="#" id="back-to-top" class="back-to-top-btn" title="Quay về đầu trang">&#8679;</a>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            // Thêm: lọc bảng Lightcones theo tiền tố tên (prefix match, case-insensitive)
            const searchInput = document.getElementById('search-input');
            const tableBody = document.querySelector('.admin-table tbody');
            if (searchInput && tableBody) {
                searchInput.addEventListener('input', function() {
                    const q = this.value.trim().toLowerCase();
                    const rows = tableBody.querySelectorAll('tr');
                    rows.forEach(row => {
                        const nameCell = row.querySelector('.col-name');
                        if (!nameCell) return;
                        const name = nameCell.textContent.trim().toLowerCase();
                        if (q === '') {
                            row.style.display = '';
                        } else {
                            row.style.display = name.startsWith(q) ? '' : 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>