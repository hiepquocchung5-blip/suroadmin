<?php
$pageTitle = "Character Studio";
require_once '../../layout/main.php';

$id = $_GET['id'] ?? null;
$char = [
    'name' => '', 'char_key' => '', 'island_id' => 1, 'price' => 0, 'is_premium' => 0,
    'svg_data' => '{"colors": ["#FF0000", "#000000"], "hair": "", "outfit": ""}'
];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM characters WHERE id = ?");
    $stmt->execute([$id]);
    $fetched = $stmt->fetch();
    if($fetched) $char = $fetched;
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['GOD', 'FINANCE']);
    
    $name = cleanInput($_POST['name']);
    $key = cleanInput($_POST['char_key']);
    $islandId = (int)$_POST['island_id'];
    $price = (float)$_POST['price'];
    $isPremium = isset($_POST['is_premium']) ? 1 : 0;
    $svgData = $_POST['svg_data']; // Raw JSON string

    // Validate JSON
    if (!json_decode($svgData)) {
        $error = "Invalid JSON in SVG Data field.";
    } else {
        try {
            if ($id) {
                $sql = "UPDATE characters SET name=?, char_key=?, island_id=?, price=?, is_premium=?, svg_data=? WHERE id=?";
                $pdo->prepare($sql)->execute([$name, $key, $islandId, $price, $isPremium, $svgData, $id]);
                $success = "Character updated!";
            } else {
                $sql = "INSERT INTO characters (name, char_key, island_id, price, is_premium, svg_data) VALUES (?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$name, $key, $islandId, $price, $isPremium, $svgData]);
                $id = $pdo->lastInsertId(); // Redirect to edit mode
                $success = "Character created!";
                $char['id'] = $id; 
            }
            // Update local var for display
            $char = array_merge($char, $_POST);
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch Islands for dropdown
$islands = $pdo->query("SELECT id, name FROM islands ORDER BY id ASC")->fetchAll();
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<form method="POST" class="row">
    <!-- LEFT: CONFIG -->
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header border-secondary text-info fw-bold">BASIC CONFIG</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Character Name</label>
                        <input type="text" name="name" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($char['name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Unique Key (ID)</label>
                        <input type="text" name="char_key" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($char['char_key']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Home Island</label>
                        <select name="island_id" class="form-select bg-dark text-white border-secondary">
                            <?php foreach($islands as $isl): ?>
                                <option value="<?= $isl['id'] ?>" <?= $isl['id'] == $char['island_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($isl['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Price (MMK)</label>
                        <input type="number" name="price" class="form-control bg-dark text-white border-secondary" value="<?= $char['price'] ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_premium" value="1" <?= $char['is_premium'] ? 'checked' : '' ?>>
                            <label class="form-check-label text-warning">Premium SSR</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-secondary d-flex justify-content-between">
                <span class="text-info fw-bold">SVG DATA (JSON)</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatJson()">Format JSON</button>
            </div>
            <div class="card-body p-0">
                <textarea name="svg_data" id="svgJsonInput" class="form-control bg-black text-success font-monospace border-0" style="height: 400px; resize: none;" onkeyup="updatePreview()"><?= $char['svg_data'] ?></textarea>
            </div>
            <div class="card-footer border-secondary text-muted small">
                Required keys: <code>colors</code> (Array), <code>hair</code> (Path D), <code>outfit</code> (Path D).
            </div>
        </div>
        
        <div class="mt-3">
            <button type="submit" class="btn btn-info fw-bold w-100 py-3">SAVE CHARACTER</button>
        </div>
    </div>

    <!-- RIGHT: LIVE PREVIEW CANVAS -->
    <div class="col-md-5">
        <div class="card sticky-top" style="top: 20px;">
            <div class="card-header border-secondary text-center text-white">LIVE PREVIEW</div>
            <div class="card-body bg-dark d-flex justify-content-center align-items-center" style="height: 500px; background-image: radial-gradient(#333 1px, transparent 1px); background-size: 20px 20px;">
                
                <!-- THE SVG CANVAS -->
                <svg id="previewCanvas" viewBox="0 0 512 768" style="height: 100%; width: auto; filter: drop-shadow(0 0 20px rgba(0,0,0,0.5));">
                    <defs>
                        <radialGradient id="skin" cx="0.4" cy="0.4" r="0.8"><stop offset="0%" stopColor="#FFE0D0"/><stop offset="100%" stopColor="#E0B090"/></radialGradient>
                    </defs>
                    
                    <!-- Layers -->
                    <g id="layerHairBack"></g>
                    
                    <!-- Base Body (Static) -->
                    <path d="M180,250 Q256,200 332,250 Q360,350 330,450 Q400,550 380,700 L132,700 Q112,550 182,450 Q152,350 180,250" fill="url(#skin)" />
                    
                    <g id="layerOutfit"></g>
                    
                    <!-- Face (Static) -->
                    <ellipse cx="256" cy="200" rx="70" ry="85" fill="url(#skin)" />
                    <g transform="translate(0, 10)">
                        <circle cx="230" cy="190" r="14" fill="#FFF" /><circle cx="230" cy="190" r="8" fill="#333" />
                        <circle cx="282" cy="190" r="14" fill="#FFF" /><circle cx="282" cy="190" r="8" fill="#333" />
                        <path d="M245,230 Q256,245 267,230" fill="none" stroke="#D84315" strokeWidth="3" strokeLinecap="round" />
                    </g>
                    
                    <g id="layerHairFront"></g>
                </svg>

            </div>
            <div class="card-footer border-secondary text-center">
                <div id="parseStatus" class="badge bg-success">Valid JSON</div>
            </div>
        </div>
    </div>
</form>

<script>
function updatePreview() {
    const input = document.getElementById('svgJsonInput').value;
    const status = document.getElementById('parseStatus');
    
    try {
        const data = JSON.parse(input);
        status.className = 'badge bg-success';
        status.innerText = 'Valid JSON';
        
        // Colors
        const colors = data.colors || ['#666', '#333'];
        
        // Render Layers
        // This simulates the React Component logic using Vanilla JS DOM manipulation
        
        // 1. Hair
        const hairD = data.hair || "";
        // We put hair in back layer mainly, or split if JSON allows. 
        // For simple editor, we put path in back layer filled with Color 1
        const hairHtml = `<path d="${hairD}" fill="${colors[0]}" />`;
        document.getElementById('layerHairBack').innerHTML = hairHtml;
        
        // 2. Outfit
        const outfitD = data.outfit || "";
        const outfitHtml = `<path d="${outfitD}" fill="${colors[1]}" stroke="#FFD700" stroke-width="2" />`;
        document.getElementById('layerOutfit').innerHTML = outfitHtml;
        
    } catch (e) {
        status.className = 'badge bg-danger';
        status.innerText = 'Invalid JSON Syntax';
    }
}

function formatJson() {
    try {
        const val = document.getElementById('svgJsonInput').value;
        const parsed = JSON.parse(val);
        document.getElementById('svgJsonInput').value = JSON.stringify(parsed, null, 4);
        updatePreview();
    } catch(e) { alert("Fix JSON syntax errors first"); }
}

// Init
updatePreview();
</script>

<?php require_once '../../layout/footer.php'; ?>