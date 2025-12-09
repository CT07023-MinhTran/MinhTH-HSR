<?php
// build.php - Part 1/4
// Top of file: DB connect, helper, POST/GET handlers, load characters for UI

// ---------- CONFIG / DB ----------
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "honkai_star_rail";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) die("Kết nối thất bại: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// ---------- Helpers ----------
function normalizeName($str)
{
    $str = mb_strtolower((string)$str, 'UTF-8');
    $str = preg_replace('/[áàảãạăắằẳẵặâấầẩẫậ]/u', 'a', $str);
    $str = preg_replace('/[éèẻẽẹêếềểễệ]/u', 'e', $str);
    $str = preg_replace('/[iíìỉĩị]/u', 'i', $str);
    $str = preg_replace('/[óòỏõọôốồổỗộơớờởỡợ]/u', 'o', $str);
    $str = preg_replace('/[úùủũụưứừửữự]/u', 'u', $str);
    $str = preg_replace('/[ýỳỷỹỵ]/u', 'y', $str);
    $str = preg_replace('/đ/u', 'd', $str);
    $str = preg_replace('/[^a-z0-9 ]/u', '', $str);
    return trim(preg_replace('/\s+/', ' ', $str));
}

// ---------- AJAX: return build JSON if requested ----------
if (isset($_GET['action']) && $_GET['action'] === 'get_build' && isset($_GET['character_id'])) {
    $cid = intval($_GET['character_id']);
    header('Content-Type: application/json; charset=utf-8');
    $stmt = $conn->prepare("SELECT * FROM builds WHERE character_id = ? LIMIT 1");
    $stmt->bind_param("i", $cid);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if ($row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(null);
    }
    exit;
}

// ---------- Load characters for UI ----------
$characters = [];         // id => name
$characterImages = [];    // id => image path (if exists)
$r = $conn->query("SELECT id, name, image FROM characters ORDER BY name ASC");
while ($row = $r->fetch_assoc()) {
    $characters[$row['id']] = $row['name'];
    $characterImages[$row['id']] = !empty($row['image']) ? 'Hình ảnh/characters/' . $row['image'] : "images/default.png";
}

// Truy vấn danh sách Nón Ánh Sáng
$lightconeList = [];
$res = $conn->query("SELECT name FROM lightcones ORDER BY name ASC");
while ($row = $res->fetch_assoc()) {
    $lightconeList[] = $row['name'];
}
// Truy vấn danh sách Di Vật (Relic)
$relicList = [];
$res2 = $conn->query("SELECT name FROM relics WHERE type='Relic' ORDER BY name ASC");
while ($row = $res2->fetch_assoc()) {
    $relicList[] = $row['name'];
}
// Truy vấn danh sách Phụ Kiện Vị Diện (Planetary Ornament Set)
$planarList = [];
$res3 = $conn->query("SELECT name FROM relics WHERE type='Planetary Ornament Set' ORDER BY name ASC");
while ($row = $res3->fetch_assoc()) {
    $planarList[] = $row['name'];
}

// ---------- POST handling: xử lý lưu build ----------
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_build'])) {
    // Sửa lại lấy đúng character_id từ POST
    $character_id = intval($_POST['character_id'] ?? 0);
    // Nếu character_id là "" (empty string), intval sẽ trả về 0
    // Nếu character_id là một số, sẽ trả về số đó
    // Nếu character_id là một chuỗi số, cũng trả về số đó
    // Nếu character_id là null, cũng trả về 0

    // Kiểm tra lại: character_id phải là số > 0 và tồn tại trong danh sách $characters
    if ($character_id <= 0 || !isset($characters[$character_id])) {
        $msg = "Vui lòng chọn nhân vật trước khi lưu.";
    } else {
        // collect form values
        $lightcone = $_POST['lightcone'] ?? '';
        $relics = $_POST['relics'] ?? '';
        $planar = $_POST['planar'] ?? '';
        $main_stats = $_POST['main_stats'] ?? '';
        $substats = $_POST['substats_priority'] ?? '';
        $target_stats = $_POST['target_stats'] ?? '';

        // teams: team1_1..team10_4 (hidden inputs)
        $teams = [];
        for ($t = 1; $t <= 10; $t++) {
            for ($s = 1; $s <= 4; $s++) {
                $col = "team{$t}_{$s}";
                $teams[$col] = $_POST[$col] ?? '';
            }
        }

        // check exists
        $stmt = $conn->prepare("SELECT id FROM builds WHERE character_id = ? LIMIT 1");
        $stmt->bind_param("i", $character_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $exist = $res->fetch_assoc();
        if ($exist) {
            // UPDATE row
            $id = (int)$exist['id'];
            $setParts = [
                "`lightcone` = ?",
                "`relics` = ?",
                "`planar` = ?",
                "`main_stats` = ?",
                "`substats_priority` = ?",
                "`target_stats` = ?"
            ];
            $bindVals = [$lightcone, $relics, $planar, $main_stats, $substats, $target_stats];
            foreach ($teams as $col => $val) {
                $setParts[] = "`$col` = ?";
                $bindVals[] = $val;
            }
            $sql = "UPDATE builds SET " . implode(", ", $setParts) . " WHERE id = ?";
            $bindVals[] = $id;
            $types = str_repeat("s", count($bindVals) - 1) . "i";
            $stmt = $conn->prepare($sql);
            $refs = [];
            $refs[] = &$types;
            foreach ($bindVals as $k => $v) $refs[] = &$bindVals[$k];
            call_user_func_array([$stmt, 'bind_param'], $refs);
            $ok = $stmt->execute();
            $msg = $ok ? "Cập nhật build thành công." : "Lỗi khi cập nhật: " . $stmt->error;
        } else {
            // INSERT new row
            $cols = ["character_id", "lightcone", "relics", "planar", "main_stats", "substats_priority", "target_stats"];
            $vals = [$character_id, $lightcone, $relics, $planar, $main_stats, $substats, $target_stats];
            foreach ($teams as $col => $val) {
                $cols[] = $col;
                $vals[] = $val;
            }
            $placeholders = implode(", ", array_fill(0, count($cols), '?'));
            $colList = implode(", ", array_map(function ($c) {
                return "`$c`";
            }, $cols));
            $sql = "INSERT INTO builds ($colList) VALUES ($placeholders)";
            $types = str_repeat("s", count($vals));
            $types = 'i' . substr($types, 1);
            $stmt = $conn->prepare($sql);
            $refs = [];
            $refs[] = &$types;
            foreach ($vals as $k => $v) $refs[] = &$vals[$k];
            call_user_func_array([$stmt, 'bind_param'], $refs);
            $ok = $stmt->execute();
            $msg = $ok ? "Lưu build mới thành công." : "Lỗi khi lưu: " . $stmt->error;
        }
    }
}
// Initialize $msg so HTML can show it (POST handling in Part 4 may set it).
$msg = $msg ?? "";
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Quản lý Build</title>
    <link rel="stylesheet" href="honkai.css">
    <style>
        /* Character select */
        .character-select-box {
            display: flex;
            align-items: center;
            background: #222;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 18px;
            color: #fff;
        }

        .character-avatar {
            width: 96px;
            height: 96px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 16px;
            display: none;
        }

        .search-bar input,
        .search-bar select {
            width: 100%;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        /* Sections */
        .section-title {
            font-weight: 700;
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .table th,
        .table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }

        /* Team slots */
        .team-table {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 12px;
        }

        .team-row {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .team-slot {
            width: 100px;
            height: 100px;
            background: #fff;
            border: 2px solid #ddd;
            border-radius: 8px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .team-slot img.avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
            display: none;
        }

        .team-plus {
            font-size: 38px;
            color: #666;
            cursor: pointer;
        }

        .team-remove {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #c00;
            color: #fff;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .team-select {
            position: absolute;
            top: 108%;
            left: 0;
            min-width: 220px;
            display: none;
            z-index: 30;
            background: #fff;
            border: 1px solid #ccc;
            padding: 6px;
        }

        /* Buttons */
        .btn-save {
            background: #1f8cff;
            color: #fff;
            padding: 10px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        /* small helpers */
        .msg {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .msg-success {
            background: #e8f8ec;
            border: 1px solid #bfeac1;
            color: #116a21;
        }

        .msg-error {
            background: #ffecec;
            border: 1px solid #f0b6b6;
            color: #a31f1f;
        }
    </style>

    <script>
        // client-side data
        const characterImages = <?php echo json_encode($characterImages, JSON_UNESCAPED_UNICODE); ?>;
        const charactersMap = <?php echo json_encode($characters, JSON_UNESCAPED_UNICODE); ?>;

        // normalizeName helper in JS
        function normalizeNameJS(s) {
            s = (s || "").toLowerCase();
            if (s.normalize) s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            s = s.replace(/đ/g, 'd');
            s = s.replace(/[^a-z0-9 ]/g, '');
            return s.trim().replace(/\s+/g, ' ');
        }

        // Khi chọn nhân vật, nếu đã có build thì tự động điền lại thông số
        function onCharacterChange(selectEl) {
            const id = parseInt(selectEl.value) || 0;
            const avatar = document.getElementById('character-avatar-img');
            if (id && characterImages[id]) {
                avatar.src = characterImages[id];
                avatar.style.display = 'block';
                document.getElementById('build-sections').style.display = 'block';
                setMainAvatarForAll(id);
                // load build từ DB qua AJAX
                fetch('?action=get_build&character_id=' + id)
                    .then(r => r.json())
                    .then(data => {
                        if (data) populateFormFromBuild(data);
                        else clearFormInputsExceptCharacter();
                    }).catch(err => {
                        console.warn('Load build error', err);
                    });
            } else {
                avatar.style.display = 'none';
                document.getElementById('build-sections').style.display = 'none';
            }
        }

        // set main avatar images in team rows
        function setMainAvatarForAll(charId) {
            const url = characterImages[charId] || 'images/default.png';
            document.querySelectorAll('.team-main-avatar').forEach(img => {
                img.src = url;
                img.style.display = 'block';
            });
        }

        // clear form (except selected character)
        function clearFormInputsExceptCharacter() {
            // clear inputs we created in parts 2..3 (see names used later)
            document.querySelectorAll('input[name^="team"]').forEach(i => i.value = '');
            // clear other inputs (lightcone, relics, planar, main stats, sub, target)
            ['lightcone', 'relics', 'planar', 'main_stats', 'substats_priority', 'target_stats'].forEach(n => {
                const el = document.querySelector('[name="' + n + '"]');
                if (el) el.value = '';
            });
            // hide slot avatars
            document.querySelectorAll('.team-slot[data-slot]').forEach(slot => {
                const img = slot.querySelector('img.avatar');
                const rm = slot.querySelector('.team-remove');
                const plus = slot.querySelector('.team-plus');
                if (img) img.style.display = 'none';
                if (rm) rm.style.display = 'none';
                if (plus) plus.style.display = 'flex';
                slot.dataset.selected = '';
            });
        }

        // populate form from DB row (JSON) returned by GET
        function populateFormFromBuild(row) {
            // row contains columns like lightcone, relics, planar, main_stats, substats_priority, target_stats, team1_1..team10_4
            try {
                if (row.lightcone) {
                    document.querySelector('[name="lightcone"]').value = row.lightcone;
                    // optional: parse JSON to visible fields if you created them
                    try {
                        const obj = JSON.parse(row.lightcone);
                        if (obj.list) {
                            for (let i = 1; i <= 3; i++) {
                                const item = obj.list[i - 1] || {
                                    name: '',
                                    rate: '',
                                    effect: ''
                                };
                                document.getElementById('lightcone' + i).value = item.name || '';
                                document.getElementById('lightcone' + i + '_rate').value = item.rate || '';
                                document.getElementById('lightcone' + i + '_effect').value = item.effect || '';
                            }
                        }
                    } catch (e) {}
                }
                if (row.relics) {
                    document.querySelector('[name="relics"]').value = row.relics;
                    try {
                        const obj = JSON.parse(row.relics);
                        if (obj.list) {
                            for (let i = 1; i <= 3; i++) {
                                const item = obj.list[i - 1] || {
                                    name: '',
                                    rate: '',
                                    effect: ''
                                };
                                document.getElementById('relic' + i).value = item.name || '';
                                document.getElementById('relic' + i + '_rate').value = item.rate || '';
                                document.getElementById('relic' + i + '_effect').value = item.effect || '';
                            }
                        }
                    } catch (e) {}
                }
                if (row.planar) {
                    document.querySelector('[name="planar"]').value = row.planar;
                    try {
                        const obj = JSON.parse(row.planar);
                        if (obj.list) {
                            for (let i = 1; i <= 3; i++) {
                                const item = obj.list[i - 1] || {
                                    name: '',
                                    rate: ''
                                };
                                document.getElementById('ornament' + i).value = item.name || '';
                                document.getElementById('ornament' + i + '_rate').value = item.rate || '';
                            }
                        }
                    } catch (e) {}
                }
                if (row.main_stats) {
                    document.querySelector('[name="main_stats"]').value = row.main_stats;
                    try {
                        const m = JSON.parse(row.main_stats);
                        document.getElementById('main_body').value = m.body || '';
                        document.getElementById('main_feet').value = m.feet || '';
                        document.getElementById('main_sphere').value = m.sphere || '';
                        document.getElementById('main_rope').value = m.rope || '';
                    } catch (e) {}
                }
                if (row.substats_priority) document.querySelector('[name="substats_priority"]').value = row.substats_priority;
                if (row.target_stats) document.querySelector('[name="target_stats"]').value = row.target_stats;

                // teams
                for (let t = 1; t <= 10; t++) {
                    for (let s = 1; s <= 4; s++) {
                        const col = 'team' + t + '_' + s;
                        const val = row[col] ?? '';
                        const hidden = document.getElementById(col);
                        if (hidden) hidden.value = val;
                        // visual: for slot >1 find that slot and set avatar
                        if (s > 1) {
                            const slotEl = document.querySelector('.team-row[data-team="' + t + '"] .team-slot[data-slot="' + s + '"]');
                            if (slotEl) {
                                const img = slotEl.querySelector('img.avatar');
                                const plus = slotEl.querySelector('.team-plus');
                                const rm = slotEl.querySelector('.team-remove');
                                if (val) {
                                    // try to map name -> image (we have charactersMap id=>name; inverse needed)
                                    let imgUrl = 'images/default.png';
                                    // try to find id by name
                                    for (const [id, name] of Object.entries(charactersMap)) {
                                        if (name && val.toString().toLowerCase() === name.toString().toLowerCase()) {
                                            if (characterImages[id]) {
                                                imgUrl = characterImages[id];
                                                break;
                                            }
                                        }
                                    }
                                    img.src = imgUrl;
                                    img.style.display = 'block';
                                    if (plus) plus.style.display = 'none';
                                    if (rm) rm.style.display = 'flex';
                                    slotEl.dataset.selected = val;
                                } else {
                                    if (img) img.style.display = 'none';
                                    if (plus) plus.style.display = 'flex';
                                    if (rm) rm.style.display = 'none';
                                    slotEl.dataset.selected = '';
                                }
                            }
                        } else {
                            // main slot: set main avatar already handled by character change
                        }
                    }
                }
            } catch (err) {
                console.error('populate error', err);
            }
        }

        // --- Di vật logic ---
        function handleRelicEffectChange(idx) {
            const effectType = document.getElementById('relic_effect_' + idx).value;
            const relic2set = document.getElementById('relic_2set_' + idx);
            if (effectType === '2') {
                relic2set.style.display = 'inline-block';
                // Loại bỏ bộ di vật trùng lặp
                const mainSelect = document.getElementById('relic' + idx);
                const selectedMain = mainSelect.value;
                for (let i = 1; i <= 3; i++) {
                    if (i === idx) continue;
                    const other2set = document.getElementById('relic_2set_' + i);
                    if (other2set) {
                        for (let opt of other2set.options) {
                            opt.disabled = (opt.value === selectedMain && opt.value !== "");
                        }
                    }
                }
                // Disable option trùng với bộ chính
                for (let opt of relic2set.options) {
                    opt.disabled = (opt.value === selectedMain && opt.value !== "");
                }
            } else {
                relic2set.style.display = 'none';
            }
        }

        function handleRelicMainChange(idx) {
            handleRelicEffectChange(idx);
        }

        // --- Đội hình đề xuất: Không chọn nhân vật trùng ---
        function slotOpen(slotEl) {
            const sel = slotEl.querySelector('.team-select');
            if (!sel) return;
            // Lấy các nhân vật đã chọn trong cùng team-row
            const teamRow = slotEl.closest('.team-row');
            let selectedNames = [];
            teamRow.querySelectorAll('.team-slot').forEach(slot => {
                if (slot.dataset.selected) selectedNames.push(slot.dataset.selected);
            });
            // Lấy nhân vật chính đang chọn
            const mainSelect = document.getElementById('characterSelect');
            let mainName = '';
            if (mainSelect && mainSelect.selectedOptions.length > 0) {
                mainName = mainSelect.selectedOptions[0].text.trim();
            }
            // Disable các option đã chọn và nhân vật chính
            for (let opt of sel.options) {
                opt.disabled = (selectedNames.includes(opt.value) && opt.value !== "") || (mainName && opt.value === mainName);
            }
            // hide others
            document.querySelectorAll('.team-select').forEach(s => {
                if (s !== sel) s.style.display = 'none';
            });
            sel.style.display = 'block';
            sel.focus();
        }

        function slotChoose(selectEl) {
            const slot = selectEl.closest('.team-slot');
            const val = selectEl.value;
            if (!val) return;
            const img = slot.querySelector('img.avatar');
            const plus = slot.querySelector('.team-plus');
            const rm = slot.querySelector('.team-remove');
            // find image of chosen if possible
            let imgUrl = 'images/default.png';
            for (const id in charactersMap) {
                if (charactersMap[id].toLowerCase() === val.toLowerCase()) {
                    if (characterImages[id]) imgUrl = characterImages[id];
                    break;
                }
            }
            if (img) {
                img.src = imgUrl;
                img.style.display = 'block';
            }
            if (plus) plus.style.display = 'none';
            if (rm) rm.style.display = 'flex';
            slot.dataset.selected = val;
            selectEl.style.display = 'none';
        }

        function slotRemove(btn) {
            const slot = btn.closest('.team-slot');
            const img = slot.querySelector('img.avatar');
            const plus = slot.querySelector('.team-plus');
            const sel = slot.querySelector('.team-select');
            if (img) {
                img.style.display = 'none';
                img.src = 'images/default.png';
            }
            if (sel) {
                sel.value = '';
                sel.style.display = 'none';
            }
            if (plus) plus.style.display = 'flex';
            btn.style.display = 'none';
            slot.dataset.selected = '';
        }
        // close selects on outside click
        document.addEventListener('click', function(e) {
            if (!e.target.classList.contains('team-plus') && !e.target.classList.contains('team-select')) {
                document.querySelectorAll('.team-select').forEach(s => s.style.display = 'none');
            }
        });

        // Loại trừ trùng lặp cho Nón Ánh Sáng và Phụ Kiện Vị Diện
        function filterDatalistInput(inputId, datalistId, excludeIds) {
            const input = document.getElementById(inputId);
            const datalist = document.getElementById(datalistId);
            if (!input || !datalist) return;
            const excludeValues = excludeIds.map(id => document.getElementById(id)?.value?.trim().toLowerCase()).filter(Boolean);
            // Lưu lại tất cả option gốc
            if (!datalist._allOptions) {
                datalist._allOptions = Array.from(datalist.options).map(opt => opt.value);
            }
            // Xóa hết option hiện tại
            datalist.innerHTML = '';
            // Thêm lại option không trùng
            datalist._allOptions.forEach(val => {
                if (!excludeValues.includes(val.trim().toLowerCase())) {
                    const opt = document.createElement('option');
                    opt.value = val;
                    datalist.appendChild(opt);
                }
            });
        }

        // Gắn sự kiện cho Nón Ánh Sáng
        for (let i = 1; i <= 3; i++) {
            document.addEventListener('input', function(e) {
                if (e.target.id === 'lightcone' + i) {
                    if (i === 2) filterDatalistInput('lightcone2', 'datalist-lightcones', ['lightcone1']);
                    if (i === 3) filterDatalistInput('lightcone3', 'datalist-lightcones', ['lightcone1', 'lightcone2']);
                }
            });
        }
        // Gắn sự kiện cho Phụ Kiện Vị Diện
        for (let i = 1; i <= 3; i++) {
            document.addEventListener('input', function(e) {
                if (e.target.id === 'ornament' + i) {
                    if (i === 2) filterDatalistInput('ornament2', 'datalist-planar', ['ornament1']);
                    if (i === 3) filterDatalistInput('ornament3', 'datalist-planar', ['ornament1', 'ornament2']);
                }
            });
        }
    </script>
</head>

<body>
    <div class="admin-header">
        <div class="admin-logo">
            <img src="https://webstatic.hoyoverse.com/upload/op-public/2023/09/14/3c862d085db721a5625b6e12649399bc_3523008591120432460.png" alt="Honkai Star Rail Banner">
            Administrator
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
                <li><a href="relics.php">Di vật</a></li>
                <li><a href="materials.php">Nguyên liệu</a></li>
                <li><a href="builds.php" class="active">Build</a></li>
                <li><a href="teams.php">Đội hình</a></li>
            </ul>
        </div>
        <div class="admin-main">
            <h1>Build Nhân vật</h1>

            <?php if (!empty($msg)): ?>
                <div class="msg msg-success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <!-- Character select -->
            <div class="character-select-box">
                <img id="character-avatar-img" class="character-avatar" src="images/default.png" alt="avatar" style="display:none;">
                <div class="search-bar">
                    <label style="color:#fff;">Chọn nhân vật</label>
                    <select id="characterSelect" name="character_id" onchange="onCharacterChange(this)" style="margin-top:6px;">
                        <option value="">-- Chọn nhân vật --</option>
                        <?php foreach ($characters as $id => $name): ?>
                            <option value="<?= intval($id) ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- open form and build sections -->
            <!-- Datalist cho Nón Ánh Sáng -->
            <datalist id="datalist-lightcones">
                <?php foreach ($lightconeList as $lc): ?>
                    <option value="<?= htmlspecialchars($lc) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <!-- Datalist cho Di Vật -->
            <datalist id="datalist-relics">
                <?php foreach ($relicList as $relic): ?>
                    <option value="<?= htmlspecialchars($relic) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <!-- Datalist cho Phụ Kiện Vị Diện -->
            <datalist id="datalist-planar">
                <?php foreach ($planarList as $planar): ?>
                    <option value="<?= htmlspecialchars($planar) ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <form method="POST">
                <div id="build-sections" style="display:none;">
                    <!-- Nón Ánh Sáng đề xuất -->
                    <div class="section-title">Nón Ánh Sáng đề xuất</div>
                    <table class="table" id="lightcone-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Tên Nón Ánh Sáng</th>
                                <th>Tỉ lệ sử dụng (%)</th>
                                <th>Hiệu quả</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>
                                    <input type="text" id="lightcone1" list="datalist-lightcones" placeholder="Tên Nón Ánh Sáng">
                                </td>
                                <td><input type="text" id="lightcone1_rate" placeholder="Tỉ lệ (%)" style="width:60px;"></td>
                                <td><input type="text" id="lightcone1_effect" placeholder="Hiệu quả (%)" style="width:80px;"></td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>
                                    <input type="text" id="lightcone2" list="datalist-lightcones" placeholder="Tên Nón Ánh Sáng">
                                </td>
                                <td><input type="text" id="lightcone2_rate" placeholder="Tỉ lệ (%)" style="width:60px;"></td>
                                <td><input type="text" id="lightcone2_effect" placeholder="Hiệu quả (%)" style="width:80px;"></td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>
                                    <input type="text" id="lightcone3" list="datalist-lightcones" placeholder="Tên Nón Ánh Sáng">
                                </td>
                                <td><input type="text" id="lightcone3_rate" placeholder="Tỉ lệ (%)" style="width:60px;"></td>
                                <td><input type="text" id="lightcone3_effect" placeholder="Hiệu quả (%)" style="width:80px;"></td>
                            </tr>
                        </tbody>
                    </table>
                    <input type="hidden" name="lightcone" id="lightcone">

                    <!-- Di Vật đề xuất -->
                    <div class="section-title">Di Vật đề xuất</div>
                    <table class="table" id="relic-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Bộ Di Vật</th>
                                <th>Hiệu quả</th>
                                <th>Bộ 2 (nếu chọn hiệu quả 2)</th>
                                <th>Tỉ lệ sử dụng (%)</th>
                                <th>Hiệu quả</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                            <tr>
                                <td><?= $i ?></td>
                                <td>
                                    <input type="text" id="relic<?= $i ?>" list="datalist-relics" placeholder="Tên Bộ Di Vật" onchange="handleRelicMainChange(<?= $i ?>)">
                                </td>
                                <td>
                                    <select id="relic_effect_<?= $i ?>" onchange="handleRelicEffectChange(<?= $i ?>)">
                                        <option value="2">Hiệu quả bộ 2</option>
                                        <option value="4">Hiệu quả bộ 4</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" id="relic_2set_<?= $i ?>" list="datalist-relics" placeholder="Bộ Di Vật 2" style="display:none;">
                                </td>
                                <td><input type="text" id="relic<?= $i ?>_rate" placeholder="Tỉ lệ (%)" style="width:60px;"></td>
                                <td><input type="text" id="relic<?= $i ?>_effect" placeholder="Hiệu quả (%)" style="width:80px;"></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                    <input type="hidden" name="relics" id="relics">

                    <!-- Phụ Kiện Vị Diện đề xuất -->
                    <div class="section-title">Phụ Kiện Vị Diện đề xuất</div>
                    <table class="table" id="planar-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Tên Phụ Kiện</th>
                                <th>Tỉ lệ sử dụng (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>
                                    <input type="text" id="ornament1" list="datalist-planar" placeholder="Tên Phụ Kiện Vị Diện">
                                </td>
                                <td><input type="text" id="ornament1_rate" placeholder="Tỉ lệ (%)" style="width:60px;"></td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>
                                    <input type="text" id="ornament2" list="datalist-planar" placeholder="Tên Phụ Kiện Vị Diện">
                                </td>
                                <td><input type="text" id="ornament2_rate" placeholder="Tỉ lệ (%)" style="width:60px;"></td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>
                                    <input type="text" id="ornament3" list="datalist-planar" placeholder="Tên Phụ Kiện Vị Diện">
                                </td>
                                <td><input type="text" id="ornament3_rate" placeholder="Tỉ lệ (%)" style="width:60px;"></td>
                            </tr>
                        </tbody>
                    </table>
                    <input type="hidden" name="planar" id="planar">

                    <!-- Chỉ số chính tốt nhất -->
                    <div class="section-title">Chỉ số chính tốt nhất</div>
                    <table class="table" id="mainstats-table">
                        <thead>
                            <tr>
                                <th>Thân</th>
                                <th>Giày</th>
                                <th>Cầu</th>
                                <th>Dây</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div id="main_body_group">
                                        <select id="main_body_1" onchange="showNextMainStat('main_body', 1)">
                                            <option value="">Chọn</option>
                                            <option>%HP</option>
                                            <option>%ATK</option>
                                            <option>%DEF</option>
                                            <option>Tỉ Lệ Bạo Kích</option>
                                            <option>Sát Thương Bạo Kích</option>
                                            <option>Chính Xác Hiệu Ứng</option>
                                            <option>Tăng Lượng Trị Liệu</option>
                                        </select>
                                        <select id="main_body_2" style="display:none;" onchange="showNextMainStat('main_body', 2)">
                                            <option value="">Chọn</option>
                                            <option>%HP</option>
                                            <option>%ATK</option>
                                            <option>%DEF</option>
                                            <option>Tỉ Lệ Bạo Kích</option>
                                            <option>Sát Thương Bạo Kích</option>
                                            <option>Chính Xác Hiệu Ứng</option>
                                            <option>Tăng Lượng Trị Liệu</option>
                                        </select>
                                        <select id="main_body_3" style="display:none;" onchange="showNextMainStat('main_body', 3)">
                                            <option value="">Chọn</option>
                                            <option>%HP</option>
                                            <option>%ATK</option>
                                            <option>%DEF</option>
                                            <option>Tỉ Lệ Bạo Kích</option>
                                            <option>Sát Thương Bạo Kích</option>
                                            <option>Chính Xác Hiệu Ứng</option>
                                            <option>Tăng Lượng Trị Liệu</option>
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <div id="main_feet_group">
                                        <select id="main_feet_1" onchange="showNextMainStat('main_feet', 1)">
                                            <option value="">Chọn</option>
                                            <option>%HP</option>
                                            <option>%ATK</option>
                                            <option>%DEF</option>
                                            <option>Tốc Độ</option>
                                        </select>
                                        <select id="main_feet_2" style="display:none;" onchange="showNextMainStat('main_feet', 2)">
                                            <option value="">Chọn</option>
                                            <option>%HP</option>
                                            <option>%ATK</option>
                                            <option>%DEF</option>
                                            <option>Tốc Độ</option>
                                        </select>
                                        <select id="main_feet_3" style="display:none;" onchange="showNextMainStat('main_feet', 3)">
                                            <option value="">Chọn</option>
                                            <option>%HP</option>
                                            <option>%ATK</option>
                                            <option>%DEF</option>
                                            <option>Tốc Độ</option>
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <div id="main_sphere_group">
                                        <select id="main_sphere_1" onchange="showNextMainStat('main_sphere', 1)">
                                            <option value="">Chọn</option>
                                            <option>%HP</option>
                                            <option>%ATK</option>
                                            <option>%DEF</option>
                                            <option>Tăng Sát Thương Vật Lý</option>
                                            <option>Tăng Sát Thương Hỏa</option>
                                            <option>Tăng Sát Thương Băng</option>
                                            <option>Tăng Sát Thương Lôi</option>
                                            <option>Tăng Sát Thương Phong</option>
                                            <option>Tăng Sát Thương Lượng Tử</option>
                                            <option>Tăng Sát Thương Số Ảo</option>
                                        </select>
                                        <select id="main_sphere_2" style="display:none;" onchange="showNextMainStat('main_sphere', 2)">
                                            <option value="">Chọn</option>
                                            <option>%HP</option>
                                            <option>%ATK</option>
                                            <option>%DEF</option>
                                            <option>Tăng Sát Thương Vật Lý</option>
                                            <option>Tăng Sát Thương Hỏa</option>
                                            <option>Tăng Sát Thương Băng</option>
                                            <option>Tăng Sát Thương Lôi</option>
                                            <option>Tăng Sát Thương Phong</option>
                                            <option>Tăng Sát Thương Lượng Tử</option>
                                            <option>Tăng Sát Thương Số Ảo</option>
                                        </select>
                                        <select id="main_sphere_3" style="display:none;" onchange="showNextMainStat('main_sphere', 3)">
                                            <option value="">Chọn</option>
                                            <option>%HP</option>
                                            <option>%ATK</option>
                                            <option>%DEF</option>
                                            <option>Tăng Sát Thương Vật Lý</option>
                                            <option>Tăng Sát Thương Hỏa</option>
                                            <option>Tăng Sát Thương Băng</option>
                                            <option>Tăng Sát Thương Lôi</option>
                                            <option>Tăng Sát Thương Phong</option>
                                            <option>Tăng Sát Thương Lượng Tử</option>
                                            <option>Tăng Sát Thương Số Ảo</option>
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <div id="main_rope_group">
                                        <select id="main_rope_1" onchange="showNextMainStat('main_rope', 1)">
                                            <option value="">Chọn</option>
                                            <option>%HP</option>
                                            <option>%ATK</option>
                                            <option>%DEF</option>
                                            <option>Tấn Công Kích Phá</option>
                                            <option>Hiệu Suất Hồi Năng Lượng</option>
                                        </select>
                                        <select id="main_rope_2" style="display:none;" onchange="showNextMainStat('main_rope', 2)">
                                            <option value="">Chọn</option>
                                            <option>%HP</option>
                                            <option>%ATK</option>
                                            <option>%DEF</option>
                                            <option>Tấn Công Kích Phá</option>
                                            <option>Hiệu Suất Hồi Năng Lượng</option>
                                        </select>
                                        <select id="main_rope_3" style="display:none;" onchange="showNextMainStat('main_rope', 3)">
                                            <option value="">Chọn</option>
                                            <option>%HP</option>
                                            <option>%ATK</option>
                                            <option>%DEF</option>
                                            <option>Tấn Công Kích Phá</option>
                                            <option>Hiệu Suất Hồi Năng Lượng</option>
                                        </select>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <input type="hidden" name="main_stats" id="main_stats">

                    <!-- Sub & Target -->
                    <div class="section-title">Chỉ số phụ ưu tiên</div>
                    <textarea id="sub_stats_display" style="width:100%;min-height:48px;margin-bottom:16px;" placeholder="VD: Crit Rate > Crit DMG"></textarea>
                    <input type="hidden" name="substats_priority" id="substats_priority">

                    <div class="section-title">Chỉ số hướng tới</div>
                    <textarea id="target_stats_display" style="width:100%;min-height:48px;margin-bottom:16px;" placeholder="VD: 150 SPD, 60% CRIT"></textarea>
                    <input type="hidden" name="target_stats" id="target_stats">
                    <!-- PHẦN 3/4: Team UI (10 teams) -->
                    <div class="section-title">Đội hình đề xuất (10 đội)</div>
                    <div style="display: flex; gap: 32px;">
                        <div class="team-table" style="flex:1;">
                            <?php for ($team = 1; $team <= 5; $team++): ?>
                                <div class="team-row" data-team="<?= $team ?>">
                                    <!-- main slot (auto-filled) -->
                                    <div class="team-slot team-main" data-slot="1">
                                        <img class="avatar team-main-avatar" src="images/default.png" style="display:none;">
                                    </div>
                                    <!-- three select slots -->
                                    <?php for ($slot = 2; $slot <= 4; $slot++): ?>
                                        <div class="team-slot" data-slot="<?= $slot ?>" data-team="<?= $team ?>">
                                            <img class="avatar" src="images/default.png" style="display:none;">
                                            <div class="team-remove" onclick="slotRemove(this)">✕</div>
                                            <div class="team-plus" onclick="slotOpen(this.parentElement)">+</div>
                                            <select class="team-select" onchange="slotChoose(this)">
                                                <option value="">-- Chọn nhân vật --</option>
                                                <?php foreach ($characters as $id => $name): ?>
                                                    <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endfor; ?>
                                    <!-- hidden inputs for saving -->
                                    <input type="hidden" name="team<?= $team ?>_1" id="team<?= $team ?>_1" value="">
                                    <input type="hidden" name="team<?= $team ?>_2" id="team<?= $team ?>_2" value="">
                                    <input type="hidden" name="team<?= $team ?>_3" id="team<?= $team ?>_3" value="">
                                    <input type="hidden" name="team<?= $team ?>_4" id="team<?= $team ?>_4" value="">
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div class="team-table" style="flex:1;">
                            <?php for ($team = 6; $team <= 10; $team++): ?>
                                <div class="team-row" data-team="<?= $team ?>">
                                    <!-- main slot (auto-filled) -->
                                    <div class="team-slot team-main" data-slot="1">
                                        <img class="avatar team-main-avatar" src="images/default.png" style="display:none;">
                                    </div>
                                    <!-- three select slots -->
                                    <?php for ($slot = 2; $slot <= 4; $slot++): ?>
                                        <div class="team-slot" data-slot="<?= $slot ?>" data-team="<?= $team ?>">
                                            <img class="avatar" src="images/default.png" style="display:none;">
                                            <div class="team-remove" onclick="slotRemove(this)">✕</div>
                                            <div class="team-plus" onclick="slotOpen(this.parentElement)">+</div>
                                            <select class="team-select" onchange="slotChoose(this)">
                                                <option value="">-- Chọn nhân vật --</option>
                                                <?php foreach ($characters as $id => $name): ?>
                                                    <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endfor; ?>
                                    <!-- hidden inputs for saving -->
                                    <input type="hidden" name="team<?= $team ?>_1" id="team<?= $team ?>_1" value="">
                                    <input type="hidden" name="team<?= $team ?>_2" id="team<?= $team ?>_2" value="">
                                    <input type="hidden" name="team<?= $team ?>_3" id="team<?= $team ?>_3" value="">
                                    <input type="hidden" name="team<?= $team ?>_4" id="team<?= $team ?>_4" value="">
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- SAVE BUTTON -->
                    <div style="margin-top:16px;">
                        <button type="submit" name="save_build" class="btn-save" onclick="return prepareAndSubmit();">Lưu</button>
                    </div>

                </div> <!-- end build-sections -->
            </form>

            <script>
                function showNextMainStat(type, idx) {
    // type: main_body, main_feet, main_sphere, main_rope
    // idx: 1, 2, 3
    if (idx >= 3) return;
    const nextIdx = idx + 1;
    const currSelect = document.getElementById(type + '_' + idx);
    const nextSelect = document.getElementById(type + '_' + nextIdx);

    // Nếu đã chọn giá trị thì hiện dropdown tiếp theo
    if (currSelect.value && nextSelect) {
        nextSelect.style.display = '';
        // Loại bỏ các lựa chọn đã chọn trước đó
        let selected = [];
        for (let i = 1; i <= idx; i++) {
            const val = document.getElementById(type + '_' + i).value;
            if (val) selected.push(val);
        }
        for (let opt of nextSelect.options) {
            opt.disabled = selected.includes(opt.value) && opt.value !== "";
        }
    } else {
        // Nếu bỏ chọn thì ẩn các dropdown sau
        for (let i = nextIdx; i <= 3; i++) {
            const el = document.getElementById(type + '_' + i);
            if (el) {
                el.style.display = 'none';
                el.value = '';
            }
        }
    }
}
            </script>
            <?php
            // PHẦN 4/4 - xử lý POST lưu (INSERT nếu chưa có, UPDATE nếu có) and SQL (commented)

            // Note: This block must be placed at top of file before HTML to actually process POST.
            // In our 4-part arrangement we placed a placeholder earlier; if you want to move POST processing here,
            // replace the earlier $msg assignment with the code below. But since we included a light POST handler earlier,
            // below is a robust handler you can use instead of the very top.

            // If you already submitted the form and want the server to save, the following code will process $_POST:

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_build'])) {
                $character_id = intval($_POST['character_id'] ?? 0);
                if ($character_id <= 0) {
                    $msg = "Vui lòng chọn nhân vật trước khi lưu.";
                } else {
                    // collect form values
                    $lightcone = $_POST['lightcone'] ?? '';
                    $relics = $_POST['relics'] ?? '';
                    $planar = $_POST['planar'] ?? '';
                    $main_stats = $_POST['main_stats'] ?? '';
                    $substats = $_POST['substats_priority'] ?? '';
                    $target_stats = $_POST['target_stats'] ?? '';

                    // teams: team1_1..team10_4 (hidden inputs)
                    $teams = [];
                    for ($t = 1; $t <= 10; $t++) {
                        for ($s = 1; $s <= 4; $s++) {
                            $col = "team{$t}_{$s}";
                            $teams[$col] = $_POST[$col] ?? '';
                        }
                    }

                    // check exists
                    $stmt = $conn->prepare("SELECT id FROM builds WHERE character_id = ? LIMIT 1");
                    $stmt->bind_param("i", $character_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $exist = $res->fetch_assoc();
                    if ($exist) {
                        // UPDATE row - keep character_id unique
                        $id = (int)$exist['id'];
                        // build set clause
                        $setParts = [
                            "`lightcone` = ?",
                            "`relics` = ?",
                            "`planar` = ?",
                            "`main_stats` = ?",
                            "`substats_priority` = ?",
                            "`target_stats` = ?"
                        ];
                        $bindVals = [$lightcone, $relics, $planar, $main_stats, $substats, $target_stats];
                        // add teams columns
                        foreach ($teams as $col => $val) {
                            $setParts[] = "`$col` = ?";
                            $bindVals[] = $val;
                        }
                        $sql = "UPDATE builds SET " . implode(", ", $setParts) . " WHERE id = ?";
                        $bindVals[] = $id;
                        // bind types
                        $types = str_repeat("s", count($bindVals) - 1) . "i";
                        $stmt = $conn->prepare($sql);
                        $refs = [];
                        $refs[] = &$types;
                        foreach ($bindVals as $k => $v) $refs[] = &$bindVals[$k];
                        call_user_func_array([$stmt, 'bind_param'], $refs);
                        $ok = $stmt->execute();
                        $msg = $ok ? "Cập nhật build thành công." : "Lỗi khi cập nhật: " . $stmt->error;
                    } else {
                        // INSERT new row
                        $cols = ["character_id", "lightcone", "relics", "planar", "main_stats", "substats_priority", "target_stats"];
                        $vals = [$character_id, $lightcone, $relics, $planar, $main_stats, $substats, $target_stats];
                        foreach ($teams as $col => $val) {
                            $cols[] = $col;
                            $vals[] = $val;
                        }
                        $placeholders = implode(", ", array_fill(0, count($cols), '?'));
                        $colList = implode(", ", array_map(function ($c) {
                            return "`$c`";
                        }, $cols));
                        $sql = "INSERT INTO builds ($colList) VALUES ($placeholders)";
                        $types = str_repeat("s", count($vals));
                        // first value is integer (character_id) - change first type to i
                        $types = 'i' . substr($types, 1);
                        $stmt = $conn->prepare($sql);
                        $refs = [];
                        $refs[] = &$types;
                        foreach ($vals as $k => $v) $refs[] = &$vals[$k];
                        call_user_func_array([$stmt, 'bind_param'], $refs);
                        $ok = $stmt->execute();
                        $msg = $ok ? "Lưu build mới thành công." : "Lỗi khi lưu: " . $stmt->error;
                    }
                }
            }
            // END POST processing

            // ---------- SQL to create builds table (run once in your DB) ----------
            /*
CREATE TABLE `builds` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `character_id` INT NOT NULL UNIQUE,
  `lightcone` TEXT,
  `relics` TEXT,
  `planar` TEXT,
  `main_stats` TEXT,
  `substats_priority` TEXT,
  `target_stats` TEXT,
  -- team columns:
  `team1_1` VARCHAR(150), `team1_2` VARCHAR(150), `team1_3` VARCHAR(150), `team1_4` VARCHAR(150),
  `team2_1` VARCHAR(150), `team2_2` VARCHAR(150), `team2_3` VARCHAR(150), `team2_4` VARCHAR(150),
  `team3_1` VARCHAR(150), `team3_2` VARCHAR(150), `team3_3` VARCHAR(150), `team3_4` VARCHAR(150),
  `team4_1` VARCHAR(150), `team4_2` VARCHAR(150), `team4_3` VARCHAR(150), `team4_4` VARCHAR(150),
  `team5_1` VARCHAR(150), `team5_2` VARCHAR(150), `team5_3` VARCHAR(150), `team5_4` VARCHAR(150),
  `team6_1` VARCHAR(150), `team6_2` VARCHAR(150), `team6_3` VARCHAR(150), `team6_4` VARCHAR(150),
  `team7_1` VARCHAR(150), `team7_2` VARCHAR(150), `team7_3` VARCHAR(150), `team7_4` VARCHAR(150),
  `team8_1` VARCHAR(150), `team8_2` VARCHAR(150), `team8_3` VARCHAR(150), `team8_4` VARCHAR(150),
  `team9_1` VARCHAR(150), `team9_2` VARCHAR(150), `team9_3` VARCHAR(150), `team9_4` VARCHAR(150),
  `team10_1` VARCHAR(150), `team10_2` VARCHAR(150), `team10_3` VARCHAR(150), `team10_4` VARCHAR(150),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/
            ?>
</body>

</html>