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
    if ($stmt) {
        $stmt->bind_param("i", $cid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        echo json_encode($row ?: null, JSON_UNESCAPED_UNICODE);
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
    $characterImages[$row['id']] = !empty($row['image']) ? 'uploads/characters/' . $row['image'] : "images/default.png";
}

// Truy vấn danh sách Nón Ánh Sáng, Di Vật, Phụ Kiện
$lightconeList = [];
$res = $conn->query("SELECT name FROM lightcones ORDER BY name ASC");
while ($row = $res->fetch_assoc()) $lightconeList[] = $row['name'];

$relicList = [];
$res2 = $conn->query("SELECT name FROM relics WHERE type='Relic' ORDER BY name ASC");
while ($row = $res2->fetch_assoc()) $relicList[] = $row['name'];

$planarList = [];
$res3 = $conn->query("SELECT name FROM relics WHERE type='Planetary Ornament Set' ORDER BY name ASC");
while ($row = $res3->fetch_assoc()) $planarList[] = $row['name'];


// ---------- POST handling: xử lý lưu build ----------
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_build'])) {
    $character_id = intval($_POST['character_id'] ?? 0);

    if ($character_id <= 0 || !isset($characters[$character_id])) {
        $msg = "Vui lòng chọn nhân vật trước khi lưu.";
    } else {
        // Helper function to process numeric inputs (handles '0' correctly)
        $num = function ($key) {
            return isset($_POST[$key]) && $_POST[$key] !== '' ? floatval($_POST[$key]) : null;
        };
        $str = function ($key) {
            return !empty($_POST[$key]) ? $_POST[$key] : null;
        };

        // Thu thập tất cả dữ liệu từ form
        $data = [
            'character_id'   => $character_id,
            'character_name' => $characters[$character_id] ?? null,
        ];

        for ($i = 1; $i <= 3; $i++) {
            $data["lightcone{$i}"]      = $str("lightcone{$i}");
            $data["lightcone{$i}_rate"] = $num("lightcone{$i}_rate");
            $data["lightcone{$i}_power"] = $num("lightcone{$i}_power"); // Thêm dòng này
        }

        for ($i = 1; $i <= 3; $i++) {
            $data["relic{$i}_set"]    = $str("relic{$i}_set");
            $data["relic{$i}_effect"] = $str("relic{$i}_effect");
            $data["relic{$i}_rate"]   = $num("relic{$i}_rate");
            $data["relic{$i}_power"]  = $num("relic{$i}_power"); // Thêm dòng này

            $sets2 = [];
            if ($data["relic{$i}_effect"] === '2') {
                $data["relic{$i}_rate"] = null;
                for ($j = 1; $j <= 5; $j++) {
                    if ($val = $str("relic{$i}_2set_{$j}")) {
                        $sets2[] = $val;
                    }
                }
            }
            $data["relic{$i}_2set"] = !empty($sets2) ? implode(' + ', $sets2) : null;
        }

        for ($i = 1; $i <= 3; $i++) {
            $data["ornament{$i}"]      = $str("ornament{$i}");
            $data["ornament{$i}_rate"] = $num("ornament{$i}_rate");
        }

        foreach (['body', 'boots', 'sphere', 'rope'] as $type) {
            $main_stats = [];
            for ($i = 1; $i <= 3; $i++) {
                if ($val = $str("main_{$type}_{$i}")) {
                    $main_stats[] = $val;
                }
            }
            $data["mainstat_{$type}"] = !empty($main_stats) ? implode(' / ', $main_stats) : null;
        }

        $data['substats']     = $str('substats');
        $data['target_stats'] = $str('target_stats');

        for ($t = 1; $t <= 10; $t++) {
            for ($s = 1; $s <= 4; $s++) {
                $data["team{$t}_{$s}"] = $str("team{$t}_{$s}");
            }
        }

        // ---------- DB Operation: INSERT or UPDATE ----------
        $stmt = $conn->prepare("SELECT id FROM builds WHERE character_id = ?");
        if (!$stmt) {
            die("Prepare failed (SELECT build): " . $conn->error);
        }

        $stmt->bind_param("i", $character_id);
        $stmt->execute();
        $existing_build = $stmt->get_result()->fetch_assoc();


        if ($existing_build) { // UPDATE
            $id = (int)$existing_build['id'];
            unset($data['character_id']);

            $set_parts = [];
            $params = [];
            $types = '';
            foreach ($data as $col => $val) {
                $set_parts[] = "`$col` = ?";
                $params[] = $val;
                if (is_float($val)) {
                    $types .= 'd';
                } elseif (is_int($val)) {
                    $types .= 'i';
                } else {
                    $types .= 's';
                }
            }
            $params[] = $id;
            $types .= 'i';

            $sql = "UPDATE builds SET " . implode(", ", $set_parts) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $ok = $stmt->execute();
                $msg = $ok ? "Cập nhật build thành công." : "Lỗi khi cập nhật: " . $stmt->error;
            } else {
                $msg = "Lỗi chuẩn bị câu lệnh UPDATE: " . $conn->error;
            }
        } else { // INSERT
            $cols = array_keys($data);
            $params = array_values($data);
            $types = '';
            foreach ($params as $val) {
                if (is_float($val)) {
                    $types .= 'd';
                } elseif (is_int($val)) {
                    $types .= 'i';
                } else {
                    $types .= 's';
                }
            }

            $placeholders = implode(", ", array_fill(0, count($cols), '?'));
            $sql = "INSERT INTO builds (`" . implode("`, `", $cols) . "`) VALUES ($placeholders)";

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $ok = $stmt->execute();
                $msg = $ok ? "Lưu build mới thành công." : "Lỗi khi lưu: " . $stmt->error;
            } else {
                $msg = "Lỗi chuẩn bị câu lệnh INSERT: " . $conn->error;
            }
        }
    }
}
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
            s = s.replace(/đ/g, 'd');
            if (s.normalize) s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
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
                // Cập nhật giá trị cho trường hidden trong form
                const hiddenCharField = document.getElementById('hidden_character_id');
                if (hiddenCharField) hiddenCharField.value = id;

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
                // Reset trường hidden
                const hiddenCharField = document.getElementById('hidden_character_id');
                if (hiddenCharField) hiddenCharField.value = '';
            }
        }

        // set main avatar images in team rows
        function setMainAvatarForAll(charId) {
            const url = characterImages[charId] || 'images/default.png';
            document.querySelectorAll('.team-main-avatar').forEach(img => {
                img.src = url;
                img.style.display = 'block';
            });
            // Cập nhật giá trị cho các trường hidden teamX_1
            const mainCharName = charactersMap[charId] || '';
            for (let t = 1; t <= 10; t++) {
                const hiddenInput = document.getElementById(`team${t}_1`);
                if (hiddenInput) {
                    hiddenInput.value = mainCharName;
                }
            }
        }

        // clear form (except selected character)
        function clearFormInputsExceptCharacter() {
            const characterSelectValue = document.getElementById('characterSelect').value;
            const characterIdValue = document.getElementById('hidden_character_id').value;

            document.getElementById('build-form').reset();

            document.getElementById('characterSelect').value = characterSelectValue;
            document.getElementById('hidden_character_id').value = characterIdValue;
            if (characterIdValue) {
                setMainAvatarForAll(characterIdValue);
            }

            // hide slot avatars
            document.querySelectorAll('.team-slot[data-slot]').forEach(slot => {
                const img = slot.querySelector('img.avatar');
                const rm = slot.querySelector('.team-remove');
                const plus = slot.querySelector('.team-plus');
                if (img && !slot.classList.contains('team-main')) {
                    img.style.display = 'none';
                    if (rm) rm.style.display = 'none';
                    if (plus) plus.style.display = 'flex';
                    slot.dataset.selected = '';
                }
            });
        }

        // populate form from DB row (JSON) returned by GET
        function populateFormFromBuild(row) {
            clearFormInputsExceptCharacter();
            try {
                // Nón ánh sáng
                for (let i = 1; i <= 3; i++) {
                    document.getElementById(`lightcone${i}`).value = row[`lightcone${i}`] || '';
                    document.getElementById(`lightcone${i}_rate`).value = row[`lightcone${i}_rate`] || '';
                }

                // Di vật
                for (let i = 1; i <= 3; i++) {
                    const relicSetInput = document.getElementById(`relic${i}_set`);
                    if (relicSetInput) relicSetInput.value = row[`relic${i}_set`] || '';
                    
                    const effectType = row[`relic${i}_effect`] || '4';
                    const effectSelect = document.getElementById(`relic${i}_effect`);
                    if (effectSelect) effectSelect.value = effectType;

                    const rateInput = document.getElementById(`relic${i}_rate`);
                    if (rateInput) rateInput.value = row[`relic${i}_rate`] || '';
                    
                    handleRelicEffectChange(i); // Cập nhật UI

                    if (effectType === '2' && row[`relic${i}_2set`]) {
                        const sets2 = (row[`relic${i}_2set`] || '').split(' + ');
                        for (let j = 1; j <= 5; j++) {
                            const sel = document.getElementById(`relic${i}_2set_${j}`);
                            if (sel) {
                                sel.value = sets2[j - 1] || '';
                            }
                        }
                    }
                    // Phải gọi lại sau khi gán giá trị để cập nhật các option bị disabled
                    handleRelicMainChange(i);
                    handleRelic2SetChange(i, 0); 
                }

                // Phụ kiện
                for (let i = 1; i <= 3; i++) {
                    document.getElementById(`ornament${i}`).value = row[`ornament${i}`] || '';
                    document.getElementById(`ornament${i}_rate`).value = row[`ornament${i}_rate`] || '';
                }

                // Chỉ số chính
                ['body', 'boots', 'sphere', 'rope'].forEach(type => {
                    const stats = (row[`mainstat_${type}`] || '').split(' / ');
                    for (let i = 1; i <= 3; i++) {
                        const sel = document.getElementById(`main_${type}_${i}`);
                        if (sel) {
                            sel.value = stats[i - 1] || '';
                        }
                    }
                    // Gọi lại để cập nhật hiển thị
                    for (let i = 1; i < 3; i++) {
                        showNextMainStat(`main_${type}`, i);
                    }
                });

                // Chỉ số phụ và mục tiêu
                document.getElementById('substats').value = row.substats || '';
                document.getElementById('target_stats').value = row.target_stats || '';

                // teams
                for (let t = 1; t <= 10; t++) {
                    for (let s = 1; s <= 4; s++) {
                        const col = `team${t}_${s}`;
                        const val = row[col] || '';
                        const hidden = document.getElementById(col);
                        if (hidden) hidden.value = val;
                        
                        if (s > 1) { // Bỏ qua slot 1 (nhân vật chính)
                            const slotEl = document.querySelector(`.team-row[data-team="${t}"] .team-slot[data-slot="${s}"]`);
                            if (slotEl) {
                                const img = slotEl.querySelector('img.avatar');
                                const plus = slotEl.querySelector('.team-plus');
                                const rm = slotEl.querySelector('.team-remove');
                                const select = slotEl.querySelector('select.team-select');

                                if (val) {
                                    slotEl.dataset.selected = val;
                                    if(select) select.value = val;

                                    let imgUrl = 'images/default.png';
                                    for (const [id, name] of Object.entries(charactersMap)) {
                                        if (name && normalizeNameJS(val) === normalizeNameJS(name)) {
                                            if (characterImages[id]) {
                                                imgUrl = characterImages[id];
                                                break;
                                            }
                                        }
                                    }
                                    if (img) {
                                        img.src = imgUrl;
                                        img.style.display = 'block';
                                    }
                                    if (plus) plus.style.display = 'none';
                                    if (rm) rm.style.display = 'flex';
                                } else {
                                    slotEl.dataset.selected = '';
                                     if(select) select.value = '';
                                    if (img) {
                                        img.style.display = 'none';
                                        img.src = '';
                                    }
                                    if (plus) plus.style.display = 'flex';
                                    if (rm) rm.style.display = 'none';
                                }
                            }
                        }
                    }
                }
            } catch (err) {
                console.error('Lỗi khi điền thông tin build:', err);
                alert('Đã xảy ra lỗi khi tải thông tin build của nhân vật. Vui lòng kiểm tra console để biết thêm chi tiết.');
            }
        }

        // --- Di vật logic ---
        function handleRelicEffectChange(idx) {
            const effectType = document.getElementById('relic' + idx + '_effect').value;
            const relic2SetContainer = document.getElementById('relic_2set_container_' + idx);
            const usageRateInput = document.getElementById('relic' + idx + '_rate');

            if (effectType === '2') {
                relic2SetContainer.style.display = 'block';
                usageRateInput.disabled = true;
                usageRateInput.value = ''; // Xóa giá trị khi bị vô hiệu hóa
                handleRelic2SetChange(idx, 0); // Gọi để cập nhật các option
            } else { // effectType === '4'
                relic2SetContainer.style.display = 'none';
                usageRateInput.disabled = false;
                // Reset all 2-set selects
                for (let i = 1; i <= 5; i++) {
                    const sel = document.getElementById('relic' + idx + '_2set_' + i);
                    if (sel) sel.value = '';
                }
            }
        }

        function handleRelic2SetChange(mainIdx, selectIdx) {
            const mainRelicValue = document.getElementById('relic' + mainIdx + '_set').value;
            let selectedValues = [mainRelicValue];

            // Thu thập tất cả các giá trị đã chọn trong hàng này
            for (let i = 1; i <= 5; i++) {
                const val = document.getElementById('relic' + mainIdx + '_2set_' + i).value;
                if (val) {
                    selectedValues.push(val);
                }
            }

            // Cập nhật các tùy chọn cho tất cả các select trong hàng
            for (let i = 1; i <= 5; i++) {
                const currentSelect = document.getElementById('relic' + mainIdx + '_2set_' + i);
                const currentValue = currentSelect.value;

                for (let opt of currentSelect.options) {
                    if (opt.value === "") continue;
                    // Vô hiệu hóa nếu giá trị đã được chọn ở nơi khác TRỪ KHI nó là giá trị hiện tại của select này
                    if (selectedValues.includes(opt.value) && opt.value !== currentValue) {
                        opt.disabled = true;
                    } else {
                        opt.disabled = false;
                    }
                }
            }
        }

        function handleRelicMainChange(idx) {
            // Khi di vật chính thay đổi, cần cập nhật lại các tùy chọn cho bộ 2
            handleRelic2SetChange(idx, 0);
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

            // Cập nhật hidden input
            const team = slot.dataset.team;
            const slotNum = slot.dataset.slot;
            const hiddenInput = document.getElementById(`team${team}_${slotNum}`);
            if (hiddenInput) hiddenInput.value = val;

            // Cập nhật UI
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

            // Xóa hidden input
            const team = slot.dataset.team;
            const slotNum = slot.dataset.slot;
            const hiddenInput = document.getElementById(`team${team}_${slotNum}`);
            if (hiddenInput) hiddenInput.value = "";

            // Cập nhật UI
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
                    if (i === 1) {
                        filterDatalistInput('lightcone2', 'datalist-lightcones', ['lightcone1']);
                        filterDatalistInput('lightcone3', 'datalist-lightcones', ['lightcone1', 'lightcone2']);
                    } else if (i === 2) {
                        filterDatalistInput('lightcone1', 'datalist-lightcones', ['lightcone2']);
                        filterDatalistInput('lightcone3', 'datalist-lightcones', ['lightcone1', 'lightcone2']);
                    } else { // i === 3
                        filterDatalistInput('lightcone1', 'datalist-lightcones', ['lightcone3']);
                        filterDatalistInput('lightcone2', 'datalist-lightcones', ['lightcone1', 'lightcone3']);
                    }
                }
            });
        }
        // Gắn sự kiện cho Phụ Kiện Vị Diện
        for (let i = 1; i <= 3; i++) {
            document.addEventListener('input', function(e) {
                if (e.target.id === 'ornament' + i) {
                    if (i === 1) {
                        filterDatalistInput('ornament2', 'datalist-planar', ['ornament1']);
                        filterDatalistInput('ornament3', 'datalist-planar', ['ornament1', 'ornament2']);
                    } else if (i === 2) {
                        filterDatalistInput('ornament1', 'datalist-planar', ['ornament2']);
                        filterDatalistInput('ornament3', 'datalist-planar', ['ornament1', 'ornament2']);
                    } else { // i === 3
                        filterDatalistInput('ornament1', 'datalist-planar', ['ornament3']);
                        filterDatalistInput('ornament2', 'datalist-planar', ['ornament1', 'ornament3']);
                    }
                }
            });
        }

        // Chạy khi trang tải xong để cập nhật trạng thái ban đầu
        document.addEventListener('DOMContentLoaded', function() {
            // Khởi tạo trạng thái hiển thị cho các hàng di vật
            for (let i = 1; i <= 3; i++) {
                handleRelicEffectChange(i);
            }

        });
    </script>
</head>

<body>
    <div class="admin-header">
        <div class="admin-logo">
            <img src="https://webstatic.hoyoverse.com/upload/op-public/2023/09/14/3c862d085db721a5625b6e12649399bc_3523008591120432460.png" alt="Honkai Star Rail Banner">
            Administrator
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
                <li><a href="materials.php">Nguyên liệu</a></li>
                <li><a href="builds.php" class="active">Build</a></li>
                <li><a href="teams.php">Đội hình</a></li>
            </ul>
        </div>
        <div class="admin-main">
            <h1>Build Nhân vật</h1>

            <!-- Datalists must be outside the form if they are to be reused, but for simplicity here they are -->
            <datalist id="datalist-lightcones">
                <?php foreach ($lightconeList as $lc): ?>
                    <option value="<?= htmlspecialchars($lc) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <datalist id="datalist-relics">
                <?php foreach ($relicList as $relic): ?>
                    <option value="<?= htmlspecialchars($relic) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <datalist id="datalist-planar">
                <?php foreach ($planarList as $planar): ?>
                    <option value="<?= htmlspecialchars($planar) ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <form method="POST" id="build-form">
                <?php if (!empty($msg)): ?>
                    <div class="msg <?= (strpos($msg, 'thành công') !== false) ? 'msg-success' : 'msg-error' ?>"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <div class="character-select-box">
                    <img id="character-avatar-img" class="character-avatar" src="images/default.png" alt="avatar" style="display:none;">
                    <div class="search-bar">
                        <label style="color:#fff;">Chọn nhân vật</label>
                        <select id="characterSelect" onchange="onCharacterChange(this)" style="margin-top:6px;">
                            <option value="">-- Chọn nhân vật --</option>
                            <?php foreach ($characters as $id => $name): ?>
                                <option value="<?= intval($id) ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <input type="hidden" name="character_id" id="hidden_character_id" value="">

                <div id="build-sections" style="display:none;">
                    <!-- Nón Ánh Sáng đề xuất -->
                    <div class="section-title">Nón Ánh Sáng đề xuất</div>
                    <table class="table" id="lightcone-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Tên Nón Ánh Sáng</th>
                                <th>Tỉ lệ sử dụng (%)</th>
                                <th>% Sức Mạnh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                                <tr>
                                    <td><?= $i ?></td>
                                    <td>
                                        <input type="text" id="lightcone<?= $i ?>" name="lightcone<?= $i ?>" list="datalist-lightcones" placeholder="Tên Nón Ánh Sáng">
                                    </td>
                                    <td><input type="number" step="any" id="lightcone<?= $i ?>_rate" name="lightcone<?= $i ?>_rate" placeholder="Tỉ lệ (%)" style="width:80px;"></td>
                                    <td><input type="number" step="any" id="lightcone<?= $i ?>_power" name="lightcone<?= $i ?>_power" placeholder="% Sức Mạnh" style="width:80px;"></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>

                    <!-- Di Vật đề xuất -->
                    <div class="section-title">Di Vật đề xuất</div>
                    <table class="table" id="relic-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Bộ Di Vật</th>
                                <th>Hiệu quả Bộ</th>
                                <th>Bộ 2 (nếu chọn hiệu quả 2)</th>
                                <th>Tỉ lệ sử dụng (%)</th>
                                <th>% Sức Mạnh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                                <tr>
                                    <td><?= $i ?></td>
                                    <td>
                                        <input type="text" id="relic<?= $i ?>_set" name="relic<?= $i ?>_set" list="datalist-relics" placeholder="Tên Bộ Di Vật" onchange="handleRelicMainChange(<?= $i ?>)">
                                    </td>
                                    <td>
                                        <select id="relic<?= $i ?>_effect" name="relic<?= $i ?>_effect" onchange="handleRelicEffectChange(<?= $i ?>)">
                                            <option value="4" selected>Hiệu quả bộ 4</option>
                                            <option value="2">Hiệu quả bộ 2</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div id="relic_2set_container_<?= $i ?>" style="display: none; display: flex; flex-direction: column; gap: 5px;">
                                            <?php for ($j = 1; $j <= 5; $j++): ?>
                                                <select id="relic<?= $i ?>_2set_<?= $j ?>" name="relic<?= $i ?>_2set_<?= $j ?>" onchange="handleRelic2SetChange(<?= $i ?>, <?= $j ?>)">
                                                    <option value="">-- Chọn bộ 2 --</option>
                                                    <?php foreach ($relicList as $relic): ?>
                                                        <option value="<?= htmlspecialchars($relic) ?>"><?= htmlspecialchars($relic) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td><input type="number" step="any" id="relic<?= $i ?>_rate" name="relic<?= $i ?>_rate" placeholder="Tỉ lệ (%)" style="width:80px;"></td>
                                    <td><input type="number" step="any" id="relic<?= $i ?>_power" name="relic<?= $i ?>_power" placeholder="% Sức Mạnh" style="width:80px;"></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>

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
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                                <tr>
                                    <td><?= $i ?></td>
                                    <td>
                                        <input type="text" id="ornament<?= $i ?>" name="ornament<?= $i ?>" list="datalist-planar" placeholder="Tên Phụ Kiện Vị Diện">
                                    </td>
                                    <td><input type="number" step="any" id="ornament<?= $i ?>_rate" name="ornament<?= $i ?>_rate" placeholder="Tỉ lệ (%)" style="width:80px;"></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>

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
                                    <div id="main_body_group" style="display: flex; flex-direction: column; gap: 5px;">
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <select id="main_body_<?= $i ?>" name="main_body_<?= $i ?>" onchange="showNextMainStat('main_body', <?= $i ?>)" style="<?= $i > 1 ? 'display:none;' : '' ?>">
                                                <option value="">Chọn</option>
                                                <option>%HP</option>
                                                <option>%ATK</option>
                                                <option>%DEF</option>
                                                <option>Tỉ Lệ Bạo Kích</option>
                                                <option>Sát Thương Bạo Kích</option>
                                                <option>Chính Xác Hiệu Ứng</option>
                                                <option>Tăng Lượng Trị Liệu</option>
                                            </select>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                                <td>
                                    <div id="main_boots_group" style="display: flex; flex-direction: column; gap: 5px;">
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <select id="main_boots_<?= $i ?>" name="main_boots_<?= $i ?>" onchange="showNextMainStat('main_boots', <?= $i ?>)" style="<?= $i > 1 ? 'display:none;' : '' ?>">
                                                <option value="">Chọn</option>
                                                <option>%HP</option>
                                                <option>%ATK</option>
                                                <option>%DEF</option>
                                                <option>Tốc Độ</option>
                                            </select>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                                <td>
                                    <div id="main_sphere_group" style="display: flex; flex-direction: column; gap: 5px;">
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <select id="main_sphere_<?= $i ?>" name="main_sphere_<?= $i ?>" onchange="showNextMainStat('main_sphere', <?= $i ?>)" style="<?= $i > 1 ? 'display:none;' : '' ?>">
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
                                        <?php endfor; ?>
                                    </div>
                                </td>
                                <td>
                                    <div id="main_rope_group" style="display: flex; flex-direction: column; gap: 5px;">
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <select id="main_rope_<?= $i ?>" name="main_rope_<?= $i ?>" onchange="showNextMainStat('main_rope', <?= $i ?>)" style="<?= $i > 1 ? 'display:none;' : '' ?>">
                                                <option value="">Chọn</option>
                                                <option>%HP</option>
                                                <option>%ATK</option>
                                                <option>%DEF</option>
                                                <option>Tấn Công Kích Phá</option>
                                                <option>Hiệu Suất Hồi Năng Lượng</option>
                                            </select>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Sub & Target -->
                    <div class="section-title">Chỉ số phụ ưu tiên</div>
                    <textarea name="substats" id="substats" style="width:100%;min-height:48px;margin-bottom:16px;" placeholder="VD: Tỉ Lệ Bạo Kích > Sát Thương Bạo Kích > Tốc Độ > %ATK"></textarea>

                    <div class="section-title">Chỉ số hướng tới</div>
                    <textarea name="target_stats" id="target_stats" style="width:100%;min-height:48px;margin-bottom:16px;" placeholder="VD: Tốc Độ: 145, Tỉ Lệ Bạo Kích: 70%, Sát Thương Bạo Kích: 150%"></textarea>

                    <!-- Team UI (10 teams) -->
                    <div class="section-title">Đội hình đề xuất</div>
                    <div style="display: flex; gap: 32px; flex-wrap: wrap;">
                        <div class="team-table" style="flex:1; min-width: 400px;">
                            <?php for ($team = 1; $team <= 5; $team++): ?>
                                <div class="team-row" data-team="<?= $team ?>">
                                    <div class="team-slot team-main" data-slot="1">
                                        <img class="avatar team-main-avatar" src="images/default.png" style="display:none;">
                                    </div>
                                    <?php for ($slot = 2; $slot <= 4; $slot++): ?>
                                        <div class="team-slot" data-slot="<?= $slot ?>" data-team="<?= $team ?>">
                                            <img class="avatar" src="images/default.png" style="display:none;">
                                            <div class="team-remove" onclick="slotRemove(this)">✕</div>
                                            <div class="team-plus" onclick="slotOpen(this.parentElement)">+</div>
                                            <select class="team-select" onchange="slotChoose(this)">
                                                <option value="">-- Chọn --</option>
                                                <?php foreach ($characters as $id => $name): ?>
                                                    <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endfor; ?>
                                    <input type="hidden" name="team<?= $team ?>_1" id="team<?= $team ?>_1" value="">
                                    <input type="hidden" name="team<?= $team ?>_2" id="team<?= $team ?>_2" value="">
                                    <input type="hidden" name="team<?= $team ?>_3" id="team<?= $team ?>_3" value="">
                                    <input type="hidden" name="team<?= $team ?>_4" id="team<?= $team ?>_4" value="">
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div class="team-table" style="flex:1; min-width: 400px;">
                            <?php for ($team = 6; $team <= 10; $team++): ?>
                                <div class="team-row" data-team="<?= $team ?>">
                                    <div class="team-slot team-main" data-slot="1">
                                        <img class="avatar team-main-avatar" src="images/default.png" style="display:none;">
                                    </div>
                                    <?php for ($slot = 2; $slot <= 4; $slot++): ?>
                                        <div class="team-slot" data-slot="<?= $slot ?>" data-team="<?= $team ?>">
                                            <img class="avatar" src="images/default.png" style="display:none;">
                                            <div class="team-remove" onclick="slotRemove(this)">✕</div>
                                            <div class="team-plus" onclick="slotOpen(this.parentElement)">+</div>
                                            <select class="team-select" onchange="slotChoose(this)">
                                                <option value="">-- Chọn --</option>
                                                <?php foreach ($characters as $id => $name): ?>
                                                    <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endfor; ?>
                                    <input type="hidden" name="team<?= $team ?>_1" id="team<?= $team ?>_1" value="">
                                    <input type="hidden" name="team<?= $team ?>_2" id="team<?= $team ?>_2" value="">
                                    <input type="hidden" name="team<?= $team ?>_3" id="team<?= $team ?>_3" value="">
                                    <input type="hidden" name="team<?= $team ?>_4" id="team<?= $team ?>_4" value="">
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <button type="submit" name="save_build" class="btn-save">Lưu Build</button>
                    </div>

                </div>
            </form>

            <script>
                function showNextMainStat(type, idx) {
                    if (idx >= 3) return;
                    const currSelect = document.getElementById(type + '_' + idx);
                    const nextSelect = document.getElementById(type + '_' + (idx + 1));

                    if (currSelect.value && nextSelect) {
                        nextSelect.style.display = 'block';
                        // Disable previously selected options in the next dropdown
                        let selectedValues = [];
                        for (let i = 1; i <= idx; i++) {
                            const val = document.getElementById(type + '_' + i).value;
                            if (val) selectedValues.push(val);
                        }
                        for (let opt of nextSelect.options) {
                            opt.disabled = selectedValues.includes(opt.value) && opt.value !== "";
                        }
                    } else {
                        // Hide subsequent dropdowns if current one is cleared
                        for (let i = idx + 1; i <= 3; i++) {
                            const el = document.getElementById(type + '_' + i);
                            if (el) {
                                el.style.display = 'none';
                                el.value = '';
                            }
                        }
                    }
                }
            </script>
</body>

</html>