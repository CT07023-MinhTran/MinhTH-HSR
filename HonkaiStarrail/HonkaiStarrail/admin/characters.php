<?php
// Kết nối DB
$conn = new mysqli("localhost", "root", "", "honkai_star_rail");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Xử lý thêm/sửa
$id = $name = $path_id = $element_id = $rarity = $description = $image = $hp = $atk = $def = $spd = "";
$edit = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // KIỂM TRA LỖI UPLOAD QUAN TRỌNG: Xảy ra khi file quá lớn so với 'post_max_size' trong php.ini
    if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        die("Lỗi: Dữ liệu gửi lên quá lớn. Có thể file ảnh bạn chọn có dung lượng vượt quá giới hạn cho phép của máy chủ. Vui lòng kiểm tra lại dung lượng file hoặc cấu hình 'post_max_size' trong file php.ini.");
    }

    $name = $_POST["name"];
    $path_id = $_POST["path_id"];
    $element_id = $_POST["element_id"];
    $rarity = $_POST["rarity"] ?? null; // Sửa lỗi "Undefined array key"
    $description = $_POST["description"];
    $hp = $_POST["hp"];
    $atk = $_POST["atk"];
    $def = $_POST["def"];
    $spd = $_POST["spd"];
    // Xử lý upload ảnh
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/characters/";
        // Kiểm tra và tạo thư mục nếu chưa có
        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                die("Lỗi nghiêm trọng: Không thể tạo thư mục upload tại '$target_dir'. Vui lòng kiểm tra quyền của thư mục cha.");
            }
        }
        // Kiểm tra xem thư mục có quyền ghi không
        if (!is_writable($target_dir)) {
            die("Lỗi nghiêm trọng: Thư mục '$target_dir' không có quyền ghi. Vui lòng kiểm tra lại quyền của thư mục trên hệ thống của bạn.");
        }

        $image_name = basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $image_name;
        
        // Di chuyển file
        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            die("Lỗi: Đã có sự cố khi di chuyển file đã upload. Vui lòng thử lại.");
        }
        $image = $image_name; // Chỉ lưu tên file vào DB
    } else if (!empty($_POST["old_image"])) {
        // Nếu old_image có chứa đường dẫn, loại bỏ nó để đảm bảo chỉ lưu tên file.
        $image = basename($_POST["old_image"]);
    } else if (isset($_FILES['image']) && $_FILES['image']['error'] != UPLOAD_ERR_NO_FILE) {
        // Xử lý các lỗi upload khác
        $error_code = $_FILES['image']['error'];
        $error_message = "Lỗi không xác định (Mã: $error_code).";
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = "File ảnh quá lớn. Vui lòng chọn file có dung lượng nhỏ hơn.";
                break;
        }
        die("Lỗi upload file: " . $error_message);
    }
    if (isset($_POST["id"]) && $_POST["id"] != "") {
        // Sửa
        
        $stmt = $conn->prepare("UPDATE characters SET name=?, path_id=?, element_id=?, rarity=?, description=?, image=?, hp=?, atk=?, def=?, spd=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("siisssiiiii", $name, $path_id, $element_id, $rarity, $description, $image, $hp, $atk, $def, $spd, $_POST["id"]);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Lỗi chuẩn bị truy vấn: " . $conn->error);
        }
    } else {
        // Thêm mới
        $stmt = $conn->prepare("INSERT INTO characters (name, path_id, element_id, rarity, description, image, hp, atk, def, spd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("siisssiiii", $name, $path_id, $element_id, $rarity, $description, $image, $hp, $atk, $def, $spd);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Prepare failed: " . $conn->error);
        }
    }
    header("Location: characters.php");
    exit;
}

// Lấy dữ liệu để sửa
if (isset($_GET["edit"])) {
    $edit = true;
    $id = intval($_GET["edit"]);
    $result = $conn->query("SELECT * FROM characters WHERE id=$id");
    if ($row = $result->fetch_assoc()) {
        $name = $row["name"];
        $path_id = $row["path_id"];
        $element_id = $row["element_id"];
        $rarity = $row["rarity"];
        $description = $row["description"];
        $image = $row["image"];
        $hp = $row["hp"];
        $atk = $row["atk"];
        $def = $row["def"];
        $spd = $row["spd"];
    }
}

// Lấy danh sách nhân vật (luôn lấy tất cả; lọc sẽ thực hiện ở client-side)
$list_sql = "
    SELECT c.*, p.path AS path_name, p.image AS path_image, e.element AS element_name, e.image AS element_image
    FROM characters c
    LEFT JOIN paths p ON c.path_id = p.id
    LEFT JOIN elements e ON c.element_id = e.id
    GROUP BY c.name ORDER BY c.name ASC
";
$list = $conn->query($list_sql);
if ($list === false) {
    die("Lỗi truy vấn SQL: " . $conn->error);
}
// $search không còn dùng server-side nhưng giữ biến để tránh notice nếu được tham chiếu trong template
$search = '';

// Lấy danh sách paths và elements cho dropdown
$paths = [];
$elements = [];
$res = $conn->query("SELECT id, path FROM paths ORDER BY path ASC");
if ($res === false) {
    die("Lỗi truy vấn paths: " . $conn->error);
}
while ($row = $res->fetch_assoc()) {
    $paths[] = ['id' => $row["id"], 'name' => $row["path"]];
}

$res = $conn->query("SELECT id, element FROM elements ORDER BY element ASC");
if ($res === false) {
    die("Lỗi truy vấn elements: " . $conn->error);
}
while ($row = $res->fetch_assoc()) {
    $elements[] = ['id' => $row["id"], 'name' => $row["element"]];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhân Vật</title>
    <link rel="stylesheet" href="honkai.css">
    <style>
        /* Tùy chỉnh độ rộng cho các cột trong bảng Nhân vật */
        .admin-table .col-char-name {
            width: 12%;
            min-width: 140px;
        }
        .admin-table .col-path,
        .admin-table .col-element {
            width: 12%;
            min-width: 150px;
        }
        .admin-table .col-description {
            width: 25%;
            min-width: 200px;
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
                <li><a href="characters.php" class="active">Nhân vật</a></li>
                <li><a href="paths.php">Vận Mệnh</a></li>
                <li><a href="elements.php">Hệ</a></li>
                <li><a href="lightcones.php">Nón Ánh Sáng</a></li>
                <li><a href="relics.php">Di vật</a></li>
                <li><a href="materials.php">Nguyên liệu</a></li>
                <li><a href="builds.php">Build</a></li>
                <li><a href="teams.php">Đội hình</a></li>
            </ul>
        </div>
        <div class="admin-main">
            <h2><?php echo $edit ? "Sửa Nhân vật" : "Thêm Nhân vật"; ?></h2>
            <form class="admin-form" method="post" enctype="multipart/form-data">
                <?php if ($edit): ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php endif; ?>
                <div class="form-columns">
                    <!-- Cột trái: Thông tin cơ bản -->
                    <div class="form-column">
                        <div class="form-group">
                            <label>Hình đại diện:</label>
                            <input type="file" name="image">
                            <?php if ($image): ?>
                                <img src="uploads/characters/<?php echo $image; ?>" alt="avatar" style="height:40px;vertical-align:middle; margin-top: 5px;">
                                <input type="hidden" name="old_image" value="<?php echo $image; ?>">
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Tên nhân vật:</label>
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
                            <label>Hệ:</label>
                            <select name="element_id" required>
                                <option value="">-- Chọn hệ --</option>
                                <?php foreach ($elements as $e): ?>
                                    <option value="<?php echo $e['id']; ?>" <?php if ($element_id == $e['id']) echo "selected"; ?>>
                                        <?php echo htmlspecialchars($e['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Độ hiếm:</label>
                            <span>
                                <label><input type="radio" name="rarity" value="5" <?php if ($rarity == "5") echo "checked"; ?>> 5 Sao</label>
                                <label style="margin-left:20px;"><input type="radio" name="rarity" value="4" <?php if ($rarity == "4") echo "checked"; ?>> 4 Sao</label>
                            </span>
                        </div>
                    </div>
                    <!-- Cột phải: Chỉ số và mô tả -->
                    <div class="form-column">
                        <div class="stats-grid">
                            <div class="form-group"><label>HP:</label><input type="number" name="hp" value="<?php echo htmlspecialchars($hp); ?>"></div>
                            <div class="form-group"><label>ATK:</label><input type="number" name="atk" value="<?php echo htmlspecialchars($atk); ?>"></div>
                            <div class="form-group"><label>DEF:</label><input type="number" name="def" value="<?php echo htmlspecialchars($def); ?>"></div>
                            <div class="form-group"><label>SPD:</label><input type="number" name="spd" value="<?php echo htmlspecialchars($spd); ?>"></div>
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
                <h3 style="margin:0;">Danh sách Nhân vật</h3>
                <!-- Input lọc trực tiếp: không có nút gửi -->
                <div style="margin:0;">
                    <input id="search-input" type="text" placeholder="Tìm kiếm nhân vật" value="" style="padding:6px 8px; width:220px;">
                </div>
            </div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th class="col-char-name">Tên</th>
                        <th class="col-path">Vận mệnh</th>
                        <th class="col-element">Hệ</th>
                        <th>Độ hiếm</th>
                        <th>HP</th>
                        <th>ATK</th>
                        <th>DEF</th>
                        <th>SPD</th>
                        <th class="col-description">Mô tả</th>
                        <th class="col-action">Sửa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($list && $list->num_rows > 0): while ($row = $list->fetch_assoc()): ?>
                        <tr>
                            <td class="col-image"><?php if (isset($row["image"]) && $row["image"]) echo '<img src="uploads/characters/' . htmlspecialchars($row["image"]) . '" alt="'.htmlspecialchars($row['name']).'" style="height:40px;">'; ?></td>
                            <td class="col-char-name"><?php echo htmlspecialchars($row["name"]); ?></td>
                            <td class="col-path">
                                <?php if (!empty($row["path_image"])) echo '<img src="' . htmlspecialchars($row["path_image"]) . '" alt="'.htmlspecialchars($row["path_name"]).'" style="height:24px; vertical-align:middle; margin-right: 5px;">'; ?>
                                <?php echo htmlspecialchars($row["path_name"] ?? 'N/A'); ?>
                            </td>
                            <td class="col-element">
                                <?php if (!empty($row["element_image"])) echo '<img src="' . htmlspecialchars($row["element_image"]) . '" alt="'.htmlspecialchars($row["element_name"]).'" style="height:24px; vertical-align:middle; margin-right: 5px;">'; ?>
                                <?php echo htmlspecialchars($row["element_name"] ?? 'N/A'); ?>
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
                            <td><?php echo htmlspecialchars($row["hp"]); ?></td>
                            <td><?php echo htmlspecialchars($row["atk"]); ?></td>
                            <td><?php echo htmlspecialchars($row["def"]); ?></td>
                            <td><?php echo htmlspecialchars($row["spd"]); ?></td>
                            <td class="col-description"><?php echo nl2br(htmlspecialchars($row["description"])); ?></td>
                            <td class="col-action"><a href="?edit=<?php echo $row["id"]; ?>" class="btn-edit">Sửa</a></td>
                        </tr>
                    <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="11" style="text-align: center;">Không có nhân vật nào.</td>
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
            // Script này đảm bảo ô "Mô tả" có cùng chiều cao với lưới chỉ số.
            // Giải pháp này linh hoạt và tốt hơn việc đặt một giá trị pixel cố định.
            const statsGrid = document.querySelector('.stats-grid');
            const descriptionTextarea = document.querySelector('textarea[name="description"]');
            if (statsGrid && descriptionTextarea) {
                const statsGridHeight = statsGrid.offsetHeight;
                descriptionTextarea.style.height = `${statsGridHeight}px`;
            }

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

            // Lọc bảng nhân vật theo tiền tố tên (prefix match, case-insensitive)
            const searchInput = document.getElementById('search-input');
            const tableBody = document.querySelector('.admin-table tbody');
            if (searchInput && tableBody) {
                searchInput.addEventListener('input', function() {
                    const q = this.value.trim().toLowerCase();
                    const rows = tableBody.querySelectorAll('tr');
                    rows.forEach(row => {
                        // Tên ở cột có class 'col-char-name'
                        const nameCell = row.querySelector('.col-char-name');
                        if (!nameCell) return;
                        const name = nameCell.textContent.trim().toLowerCase();
                        if (q === '') {
                            row.style.display = '';
                        } else {
                            // Hiển thị nếu tên bắt đầu bằng q
                            row.style.display = name.startsWith(q) ? '' : 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>

</html>
