<?php
// 1. Kết nối CSDL
$conn = new mysqli("localhost", "root", "", "honkai_star_rail");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 2. Khởi tạo biến
$id = $name = $icon = $rarity = $type = $description = $content = $obtain = "";
$edit = false;

// Danh sách các loại vật phẩm cho dropdown
$material_types = [
    'Ảnh Đại Diện', 'Công Thức', 'Đạo Cụ Nhiệm Vụ', 'Hình Nền Điện Thoại', 'Nguyên Liệu EXP Di Vật',
    'Nguyên Liệu EXP Nón Ánh Sáng', 'Nguyên Liệu EXP Nhân Vật', 'Nguyên Liệu Nâng Bậc Nón Ánh Sáng', 'Nguyên Liệu Nâng Bậc Nhân Vật',
    'Nguyên Liệu Vết Tích', 'Tài Liệu', 'Tiền Tệ Hiếm', 'Tiền Tệ Thường', 'Tiền Tệ Thế Giới',
    'Vật Liệu Tổng Hợp', 'Vật Phẩm Quý Giá', 'Vật Tiêu Hao', 'Không Rõ'
];
sort($material_types); // Sắp xếp cho dễ chọn

// 3. Xử lý dữ liệu từ form (Thêm/Sửa)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Kiểm tra lỗi upload file quá lớn
    if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        die("Lỗi: Dữ liệu gửi lên quá lớn. Vui lòng kiểm tra lại dung lượng file ảnh.");
    }

    // Lấy dữ liệu từ form và dùng trim() để loại bỏ khoảng trắng thừa
    $name = trim($_POST["name"]);
    $rarity = $_POST["rarity"] ?? null;
    $type = $_POST["type"] ?? null;
    // Lưu NULL nếu trường để trống, ngược lại lưu giá trị đã trim
    $description = !empty(trim($_POST["description"])) ? trim($_POST["description"]) : null;
    $content = trim($_POST["content"]);
    $obtain = trim($_POST["obtain"]);

    // Xử lý upload ảnh
    if (isset($_FILES["icon"]) && $_FILES["icon"]["error"] == 0) {
        $target_dir = "uploads/materials/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $icon = $target_dir . basename($_FILES["icon"]["name"]);
        move_uploaded_file($_FILES["icon"]["tmp_name"], $icon);
    } else if (!empty($_POST["old_icon"])) {
        $icon = $_POST["old_icon"];
    }

    if (isset($_POST["id"]) && !empty($_POST["id"])) {
        // Cập nhật (Sửa)
        $id = $_POST["id"];
        $stmt = $conn->prepare("UPDATE materials SET name=?, rarity=?, type=?, icon=?, description=?, content=?, obtain=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("sssssssi", $name, $rarity, $type, $icon, $description, $content, $obtain, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Prepare failed (UPDATE): " . $conn->error);
        }
    } else {
        // Thêm mới
        $stmt = $conn->prepare("INSERT INTO materials (name, rarity, type, icon, description, content, obtain) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssssss", $name, $rarity, $type, $icon, $description, $content, $obtain);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Prepare failed (INSERT): " . $conn->error);
        }
    }
    header("Location: materials.php");
    exit;
}

// 4. Lấy dữ liệu để sửa
if (isset($_GET["edit"])) {
    $edit = true;
    $id = intval($_GET["edit"]);
    $result = $conn->query("SELECT * FROM materials WHERE id=$id");
    if ($row = $result->fetch_assoc()) {
        $name = $row["name"];
        $icon = $row["icon"];
        $rarity = $row["rarity"];
        $type = $row["type"];
        $description = $row["description"];
        $content = $row["content"];
        $obtain = $row["obtain"];
    }
}

// 5. Lấy danh sách tất cả vật phẩm để hiển thị
// Sắp xếp theo thứ tự nhập trước (id nhỏ hơn)
$list_query = $conn->query("SELECT * FROM materials ORDER BY id ASC");

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Nguyên liệu</title>
    <link rel="stylesheet" href="honkai.css">
    <style>
        .admin-table .col-name { width: 15%; }
        .admin-table .col-type { width: 15%; }
        .admin-table .col-description { width: 20%; }
        .admin-table .col-content { width: 20%; }
        .admin-table .col-obtain { width: 20%; }
        .admin-table .col-content {
            position: relative; /* Làm nền tảng định vị cho tooltip */
        }

        .col-image {
            text-align: center;
            vertical-align: middle !important; /* Đảm bảo ảnh luôn ở giữa ô */
        }
        .col-image img {
            background: rgba(0,0,0,0.2); /* Thêm một lớp nền nhẹ sau ảnh để nổi bật hơn */
            border-radius: 4px;
        }
        /* Nền màu theo độ hiếm cho ô chứa ảnh */
        .rarity-bg-5 { background-color: #eedc76; } /* Vàng kim */
        .rarity-bg-4 { background-color: #9662cf; } /* Tím */
        .rarity-bg-3 { background-color: #4a7ec1; } /* Xanh da trời */
        .rarity-bg-2 { background-color: #428487; } /* Xanh lá cây */
        .rarity-bg-1 { background-color: #7a7b80; } /* Xám */

        /* Style để cắt ngắn văn bản và hiển thị dấu "..." */
        .truncate-text {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3; /* Giới hạn ở 3 dòng, có thể thay đổi */
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.4em; /* Đảm bảo chiều cao dòng nhất quán */
            max-height: 4.2em; /* Hỗ trợ trình duyệt cũ: line-height * line-clamp */
        }

        /* Tooltip tùy chỉnh để hiển thị đầy đủ nội dung */
        .col-content .tooltip-text {
            visibility: hidden;
            position: absolute;
            width: 350px; /* Rộng hơn để dễ đọc */
            background-color: #2c3b41; /* Màu nền tối, nhất quán với sidebar */
            color: #fff;
            text-align: left;
            border-radius: 6px;
            padding: 12px;
            z-index: 10;
            bottom: 100%; /* Hiển thị ngay phía trên ô */
            left: 50%;
            transform: translateX(-50%); /* Căn giữa tooltip */
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none; /* Không cản trở sự kiện chuột */
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        }

        .col-content:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Nút quay về đầu trang */
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
            <a href="/HSR/HonkaiStarrail/Trangchinh.php">Trang chủ website</a>
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
                <li><a href="materials.php" class="active">Nguyên liệu</a></li>
                <li><a href="builds.php">Build</a></li>
                <li><a href="teams.php">Đội hình</a></li>
            </ul>
        </div>
        <div class="admin-main">
            <h2><?php echo $edit ? "Sửa Nguyên liệu" : "Thêm Nguyên liệu"; ?></h2>
            <form class="admin-form" method="post" enctype="multipart/form-data">
                <?php if ($edit): ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Tên vật phẩm:</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($name); ?>">
                </div>

                <div class="form-columns">
                    <div class="form-column">
                        <div class="form-group">
                            <label>Hình ảnh:</label>
                            <input type="file" name="icon">
                            <?php if ($icon): ?>
                                <img src="<?php echo htmlspecialchars($icon); ?>" alt="icon" style="height:40px;vertical-align:middle; margin-top: 5px;">
                                <input type="hidden" name="old_icon" value="<?php echo htmlspecialchars($icon); ?>">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-column">
                        <div class="form-group">
                            <label>Độ hiếm:</label>
                            <select name="rarity" required>
                                <option value="">-- Chọn độ hiếm --</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php if ($rarity == $i) echo "selected"; ?>><?php echo $i; ?> Sao</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-column">
                        <div class="form-group">
                            <label>Phân loại:</label>
                            <select name="type" required>
                                <option value="">-- Chọn phân loại --</option>
                                <?php foreach ($material_types as $t): ?>
                                    <option value="<?php echo $t; ?>" <?php if ($type == $t) echo "selected"; ?>>
                                        <?php echo htmlspecialchars($t); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Mô tả vắn tắt:</label>
                    <textarea name="description"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Nội dung chi tiết (có thể để trống):</label>
                    <textarea name="content"><?php echo htmlspecialchars($content); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Cách nhận:</label>
                    <textarea name="obtain"><?php echo htmlspecialchars($obtain ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn-save">Lưu</button>
            </form>

            <h3 style="margin-top: 40px;">Danh sách Nguyên liệu</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="col-image">Ảnh</th>
                        <th class="col-name">Tên</th>
                        <th class="col-rarity">Độ hiếm</th>
                        <th class="col-type">Phân loại</th>
                        <th class="col-description">Mô tả</th>
                        <th class="col-content">Nội dung</th>
                        <th class="col-obtain">Cách nhận</th>
                        <th class="col-action">Sửa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($list_query && $list_query->num_rows > 0): while ($row = $list_query->fetch_assoc()): ?>
                        <?php
                            // Xác định class CSS dựa trên độ hiếm
                            $rarity_class = '';
                            if (isset($row['rarity'])) {
                                switch (intval($row['rarity'])) {
                                    case 5: $rarity_class = 'rarity-bg-5'; break; // Vàng kim
                                    case 4: $rarity_class = 'rarity-bg-4'; break; // Tím
                                    case 3: $rarity_class = 'rarity-bg-3'; break; // Xanh da trời
                                    case 2: $rarity_class = 'rarity-bg-2'; break; // Xanh lá
                                    case 1: $rarity_class = 'rarity-bg-1'; break; // Xám
                                }
                            }
                        ?>
                        <tr>
                            <td class="col-image <?php echo $rarity_class; ?>"><?php if ($row["icon"]) echo '<img src="' . htmlspecialchars($row["icon"]) . '" alt="'.htmlspecialchars($row['name']).'" style="height:40px;">'; ?></td>
                            <td class="col-name"><?php echo htmlspecialchars($row["name"]); ?></td>
                            <td class="col-rarity"><?php echo htmlspecialchars($row["rarity"]); ?> &#9733;</td>
                            <td class="col-type"><?php echo htmlspecialchars($row["type"]); ?></td>
                            <td class="col-description"><?php echo nl2br(htmlspecialchars($row["description"])); ?></td>
                            <?php
                                $is_truncated = !empty($row['content']) && strlen($row['content']) > strlen($row['description']);
                                $content_wrapper_class = $is_truncated ? 'truncate-text' : '';
                            ?>
                            <td class="col-content">
                                <div class="<?php echo $content_wrapper_class; ?>">
                                    <?php echo nl2br(htmlspecialchars($row["content"])); ?>
                                </div>
                                <?php if ($is_truncated): ?>
                                    <span class="tooltip-text"><?php echo nl2br(htmlspecialchars($row["content"])); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="col-obtain"><?php echo nl2br(htmlspecialchars($row["obtain"])); ?></td>
                            <td class="col-action"><a href="?edit=<?php echo $row["id"]; ?>" class="btn-edit">Sửa</a></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="8" style="text-align: center;">Chưa có nguyên liệu nào.</td></tr>
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
        });
    </script>
</body>
</html>