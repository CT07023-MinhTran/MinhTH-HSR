<?php
$conn = new mysqli("localhost", "root", "", "honkai_star_rail");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$id = $name = $image = "";
$edit = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // KIỂM TRA LỖI UPLOAD QUAN TRỌNG: Xảy ra khi file quá lớn so với 'post_max_size' trong php.ini
    if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        die("Lỗi: Dữ liệu gửi lên quá lớn. Có thể file ảnh bạn chọn có dung lượng vượt quá giới hạn cho phép của máy chủ. Vui lòng kiểm tra lại dung lượng file hoặc cấu hình 'post_max_size' trong file php.ini.");
    }

    $name = $_POST["name"];
    // Mặc định giữ lại ảnh cũ nếu có
    $image = !empty($_POST["old_image"]) ? basename($_POST["old_image"]) : "";

    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $target_dir = "uploads/paths/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $image_name = basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $image_name;
        move_uploaded_file($_FILES["image"]["tmp_name"], $target_file);
        $image = $image_name; // Chỉ lưu tên file vào DB
    }
    if (isset($_POST["id"]) && $_POST["id"] != "") {
        $stmt = $conn->prepare("UPDATE paths SET path=?, image=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("ssi", $name, $image, $_POST["id"]);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Prepare failed: " . $conn->error);
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO paths (path, image) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("ss", $name, $image);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Prepare failed: " . $conn->error);
        }
    }
    header("Location: paths.php");
    exit;
}

if (isset($_GET["edit"])) {
    $edit = true;
    $id = intval($_GET["edit"]);
    $result = $conn->query("SELECT * FROM paths WHERE id=$id");
    if ($row = $result->fetch_assoc()) {
        $name = $row["path"];
        $image = $row["image"];
    }
}

$list = $conn->query("SELECT * FROM paths ORDER BY path ASC");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Vận mệnh</title>
    <link rel="stylesheet" href="honkai.css">
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
                <li><a href="paths.php" class="active">Vận Mệnh</a></li>
                <li><a href="elements.php">Hệ</a></li>
                <li><a href="lightcones.php">Nón Ánh Sáng</a></li>
                <li><a href="relics.php">Di vật</a></li>
                <li><a href="materials.php">Nguyên liệu</a></li>
                <li><a href="builds.php">Build</a></li>
                <li><a href="teams.php">Đội hình</a></li>
            </ul>
        </div>
        <div class="admin-main">
            <h2><?php echo $edit ? "Sửa Vận mệnh" : "Thêm Vận mệnh"; ?></h2>
            <form class="admin-form" method="post" enctype="multipart/form-data">
                <?php if ($edit): ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label>Hình đại diện:</label>
                    <input type="file" name="image">
                    <?php if ($image): ?>
                        <img src="uploads/paths/<?php echo htmlspecialchars($image); ?>" alt="avatar" style="height:40px;vertical-align:middle;">
                        <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($image); ?>">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Tên vận mệnh:</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($name); ?>">
                </div>
                <button type="submit" class="btn-save">Lưu</button>
            </form>
            <h3>Danh sách Vận mệnh</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="col-image">Ảnh</th>
                        <th>Tên</th>
                        <th class="col-action">Sửa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($list && $list->num_rows > 0): ?>
                        <?php while ($row = $list->fetch_assoc()): ?>
                        <tr>
                            <td class="col-image"><?php if ($row["image"]) echo '<img src="uploads/paths/'.htmlspecialchars($row["image"]).'" alt="'.htmlspecialchars($row["path"]).'" style="height:40px;">'; ?></td>
                            <td><?php echo htmlspecialchars($row["path"]); ?></td>
                            <td class="col-action"><a href="?edit=<?php echo $row["id"]; ?>" class="btn-edit">Sửa</a></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="text-align: center;">Không có vận mệnh nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>