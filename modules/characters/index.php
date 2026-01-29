<?php
$pageTitle = "Character Roster";
require_once '../../layout/main.php';

// Handle Delete
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    requireRole(['GOD']);
    $id = (int)$_POST['id'];
    $pdo->prepare("DELETE FROM characters WHERE id = ?")->execute([$id]);
    $success = "Character deleted successfully.";
}

// Fetch Characters
$chars = $pdo->query("
    SELECT c.*, i.name as island_name 
    FROM characters c 
    JOIN islands i ON c.island_id = i.id 
    ORDER BY c.id ASC
")->fetchAll();
?>

<?php if(isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="card">
    <div class="card-header bg-transparent border-secondary d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-white">ACTIVE ROSTER (<?= count($chars) ?>)</h5>
        <a href="editor.php" class="btn btn-sm btn-info fw-bold"><i class="bi bi-plus-lg"></i> DESIGN NEW CHARACTER</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr class="text-secondary text-uppercase text-xs">
                        <th>Preview</th>
                        <th>Name / Key</th>
                        <th>Island</th>
                        <th>Price</th>
                        <th>Type</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($chars as $c): 
                        // Decode SVG data for preview color extraction
                        $svgData = json_decode($c['svg_data'], true);
                        $primaryColor = $svgData['colors'][0] ?? '#fff';
                    ?>
                    <tr>
                        <td>
                            <div class="ratio ratio-1x1 rounded-circle overflow-hidden border border-secondary" style="width: 50px; background: #111;">
                                <!-- Simple preview using CSS gradient from character colors -->
                                <div style="background: radial-gradient(circle, <?= $primaryColor ?>, #000);"></div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-white"><?= htmlspecialchars($c['name']) ?></div>
                            <code class="text-info small"><?= htmlspecialchars($c['char_key']) ?></code>
                        </td>
                        <td>
                            <span class="badge bg-dark border border-secondary"><?= htmlspecialchars($c['island_name']) ?></span>
                        </td>
                        <td class="font-monospace text-warning">
                            <?= $c['price'] > 0 ? number_format($c['price']) : 'FREE' ?>
                        </td>
                        <td>
                            <?php if($c['is_premium']): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> SSR</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Standard</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="editor.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-light me-1"><i class="bi bi-pencil"></i></a>
                            
                            <?php if($_SESSION['admin_role'] === 'GOD'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this character? Users who own it might lose access.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>