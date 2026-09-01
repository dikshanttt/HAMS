<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$db = getDB();
$flash = get_flash();

// Handle Form Submissions (Add / Edit / Toggle Status / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_hospital') {
        $name        = clean($_POST['name'] ?? '');
        $address     = clean($_POST['address'] ?? '');
        $phone       = clean($_POST['phone'] ?? '');
        $email       = clean($_POST['email'] ?? '');
        $emPhone     = clean($_POST['emergency_phone'] ?? '102');
        $departments = clean($_POST['departments'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $rating      = (float)($_POST['rating'] ?? 4.8);
        $slug        = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));

        if (!$name || !$address || !$phone || !$email) {
            set_flash('error', 'Hospital name, address, phone, and official alert email are required.');
        } else {
            $stmt = $db->prepare("
                INSERT INTO hospitals (name, slug, address, phone, email, emergency_phone, departments, description, rating, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([$name, $slug . '-' . random_int(100, 999), $address, $phone, $email, $emPhone, $departments, $description, $rating]);
            set_flash('success', "Hospital '$name' successfully added to partner network.");
        }
        redirect('/admin/hospitals.php');
    }

    if ($action === 'edit_hospital') {
        $id          = (int)($_POST['hospital_id'] ?? 0);
        $name        = clean($_POST['name'] ?? '');
        $address     = clean($_POST['address'] ?? '');
        $phone       = clean($_POST['phone'] ?? '');
        $email       = clean($_POST['email'] ?? '');
        $emPhone     = clean($_POST['emergency_phone'] ?? '102');
        $departments = clean($_POST['departments'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $rating      = (float)($_POST['rating'] ?? 4.8);

        if (!$id || !$name || !$address || !$phone || !$email) {
            set_flash('error', 'All required hospital fields must be filled.');
        } else {
            $stmt = $db->prepare("
                UPDATE hospitals 
                SET name = ?, address = ?, phone = ?, email = ?, emergency_phone = ?, departments = ?, description = ?, rating = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$name, $address, $phone, $email, $emPhone, $departments, $description, $rating, $id]);
            set_flash('success', "Hospital '$name' details updated successfully.");
        }
        redirect('/admin/hospitals.php');
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['hospital_id'] ?? 0);
        $stmt = $db->prepare("UPDATE hospitals SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('success', 'Hospital status toggled successfully.');
        redirect('/admin/hospitals.php');
    }

    if ($action === 'delete_hospital') {
        $id = (int)($_POST['hospital_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM hospitals WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('success', 'Hospital permanently removed from system.');
        redirect('/admin/hospitals.php');
    }
}

// Query all hospitals with doctor counts
$hospitals = $db->query("
    SELECT h.*,
           COUNT(DISTINCT dh.doctor_id) FILTER (WHERE dh.status = 'active') AS active_doctors,
           COUNT(DISTINCT a.id) AS total_appointments
    FROM hospitals h
    LEFT JOIN doctor_hospital dh ON dh.hospital_id = h.id
    LEFT JOIN appointments a ON a.hospital_id = h.id
    GROUP BY h.id
    ORDER BY h.is_active DESC, h.name ASC
")->fetchAll();

// Get count of pending doctor approvals and schedule requests for badges
$pendingDocs = (int)$db->query("SELECT COUNT(*) FROM doctor_profiles WHERE verification_status = 'pending'")->fetchColumn();
$pendingSched = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending_approval'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Hospitals | HAMS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <style>
        :root{--sidebar-bg:#0f2b22;--sidebar-border:rgba(255,255,255,0.07);--sidebar-text:#9ab8a8;--sidebar-active-bg:rgba(255,255,255,0.09);--gold:#e5c16f}
        body.admin-page{background:#f3f6f4;font-family:var(--font-body);color:var(--text-body);min-height:100vh}
        .admin-shell{display:grid;grid-template-columns:248px minmax(0,1fr);min-height:100vh}
        .adm-sidebar{background:var(--sidebar-bg);display:flex;flex-direction:column;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .adm-brand{display:flex;align-items:center;gap:12px;padding:28px 22px 24px;border-bottom:1px solid var(--sidebar-border);text-decoration:none}
        .adm-brand-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:var(--primary);color:#fff;font-size:1.3rem;flex-shrink:0}
        .adm-brand-text strong{display:block;color:#fff;font-family:var(--font-heading);font-size:1.1rem}
        .adm-brand-text small{color:var(--sidebar-text);font-size:.7rem;letter-spacing:.1em;text-transform:uppercase}
        .adm-nav{padding:20px 12px;flex:1;display:grid;gap:4px}
        .adm-nav-label{font-size:.66rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#3d6052;padding:14px 10px 6px;margin-top:8px}
        .adm-nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;text-decoration:none;color:var(--sidebar-text);font-size:.88rem;font-weight:600;transition:all 150ms}
        .adm-nav a:hover, .adm-nav a.active{background:var(--sidebar-active-bg);color:#fff}
        .adm-badge{margin-left:auto;background:var(--gold);color:#0f2b22;font-size:.7rem;font-weight:800;padding:2px 7px;border-radius:99px}
        .adm-content{display:flex;flex-direction:column;min-height:100vh}
        .adm-topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 36px;display:flex;align-items:center;justify-content:space-between;height:70px;position:sticky;top:0;z-index:40}
        .adm-body{padding:32px 36px;flex:1}
        .panel{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(15,43,34,.04)}
        .panel-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px}
        .adm-table{width:100%;border-collapse:collapse;font-size:.85rem}
        .adm-table th{padding:10px 18px;text-align:left;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-muted);background:#f8faf7;border-bottom:1px solid var(--border)}
        .adm-table td{padding:14px 18px;border-bottom:1px solid var(--border);color:var(--text-body);vertical-align:middle}
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,43,34,0.6);backdrop-filter:blur(4px);z-index:100;place-items:center;padding:20px}
        .modal-overlay.open{display:grid}
        .modal-box{background:#fff;border-radius:16px;width:min(100%,560px);box-shadow:0 20px 40px rgba(0,0,0,0.2);overflow:hidden}
        .modal-head{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--primary-surface)}
        .modal-body{padding:24px;display:grid;gap:14px}
    </style>
</head>
<body class="admin-page">
<div class="adm-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<button class="adm-mob-toggle" id="sidebarToggle" onclick="openSidebar()" aria-label="Open navigation">☰</button>

<div class="admin-shell">

    <!-- Sidebar -->
    <aside class="adm-sidebar">
        <a class="adm-brand" href="dashboard.php">
            <span class="adm-brand-icon">✚</span>
            <div class="adm-brand-text">
                <strong>HAMS</strong>
                <small>Admin Console</small>
            </div>
        </a>

        <nav class="adm-nav">
            <span class="adm-nav-label">Hospital Network</span>
            <a href="dashboard.php"><span class="nav-icon">⊞</span>Overview</a>
            <a class="active" href="hospitals.php"><span class="nav-icon">🏥</span>Manage Hospitals</a>
            <a href="verify_doctors.php">
                <span class="nav-icon">🩺</span>Verify & Affiliations
                <?php if ($pendingDocs > 0): ?><span class="adm-badge"><?= $pendingDocs ?></span><?php endif; ?>
            </a>
            <a href="schedule_approvals.php">
                <span class="nav-icon">📅</span>Schedule Approvals
                <?php if ($pendingSched > 0): ?><span class="adm-badge"><?= $pendingSched ?></span><?php endif; ?>
            </a>
            <a href="appointments.php"><span class="nav-icon">📋</span>Appointments</a>

            <span class="adm-nav-label">Account</span>
            <a href="../logout.php"><span class="nav-icon">↩</span>Sign Out</a>
        </nav>
    </aside>

    <!-- Content -->
    <div class="adm-content">
        <header class="adm-topbar">
            <div>
                <p style="color:var(--text-muted);font-size:.8rem">Hospital Network Operations</p>
                <h1 style="font-size:1.35rem;font-weight:800;color:var(--text-main)">Partner Hospitals &amp; Clinics</h1>
            </div>
            <button class="btn btn-primary btn-sm" onclick="openAddModal()">✚&nbsp;Add New Hospital</button>
        </header>

        <div class="adm-body">
            <?php if ($flash): ?>
            <div class="<?= $flash['type']==='error'?'error-message':'success-message' ?>" style="margin-bottom:20px">
                <?= clean($flash['message']) ?>
            </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 style="font-size:1.1rem;font-weight:700">Registered Partner Facilities (<?= count($hospitals) ?>)</h2>
                        <small style="color:var(--text-muted)">Hospitals receive real-time appointment request notifications at their designated email.</small>
                    </div>
                </div>

                <div class="panel-body" style="overflow-x:auto">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>Hospital Name &amp; Location</th>
                                <th>Alert &amp; Reception Contact</th>
                                <th>Active Doctors</th>
                                <th>Departments</th>
                                <th>Status</th>
                                <th style="text-align:right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hospitals as $h): ?>
                            <tr>
                                <td>
                                    <strong style="display:block;font-size:.95rem;color:var(--text-main)"><?= clean($h['name']) ?></strong>
                                    <small style="color:var(--text-muted)">📍 <?= clean($h['address']) ?> &bull; ★ <?= number_format($h['rating'], 1) ?></small>
                                </td>
                                <td>
                                    <div style="font-size:.84rem;font-weight:600;color:var(--primary-dark)">📧 <?= clean($h['email']) ?></div>
                                    <small style="color:var(--text-muted)">📞 <?= clean($h['phone']) ?> &bull; 🚨 <?= clean($h['emergency_phone'] ?: '102') ?></small>
                                </td>
                                <td>
                                    <span class="status-badge consulting" style="font-size:.76rem">
                                        🩺 <?= (int)$h['active_doctors'] ?> Specialist<?= (int)$h['active_doctors'] !== 1 ? 's' : '' ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:.8rem;color:var(--text-muted);max-width:220px;display:inline-block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                        <?= clean($h['departments'] ?: 'General Medicine') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $h['is_active'] ? 'done' : 'waiting' ?>">
                                        <?= $h['is_active'] ? 'Active Partner' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:inline-flex;gap:6px">
                                        <button class="btn btn-outline btn-sm" onclick='openEditModal(<?= json_encode($h) ?>)'>Edit</button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Toggle active status for <?= clean(addslashes($h['name'])) ?>?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="hospital_id" value="<?= $h['id'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm"><?= $h['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Hospital -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 style="font-size:1.1rem;font-weight:700">Add New Partner Hospital</h3>
            <button onclick="closeAddModal()" style="border:none;background:none;font-size:1.2rem;cursor:pointer">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_hospital">
            <div class="modal-body">
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Hospital Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Kathmandu Care Hospital" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                </div>
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Address *</label>
                    <input type="text" name="address" required placeholder="e.g. Ring Road, Kathmandu" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Official Alert Email *</label>
                        <input type="email" name="email" required placeholder="appointments@hospital.org" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                    </div>
                    <div>
                        <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Phone Number *</label>
                        <input type="text" name="phone" required placeholder="+977 1 4200000" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Departments (comma separated)</label>
                    <input type="text" name="departments" placeholder="Cardiology, Pediatrics, Orthopedics, Neurology" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                </div>
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Description</label>
                    <textarea name="description" rows="2" placeholder="Facility overview and specializations..." style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px"></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Hospital</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Hospital -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 style="font-size:1.1rem;font-weight:700">Edit Hospital Details</h3>
            <button onclick="closeEditModal()" style="border:none;background:none;font-size:1.2rem;cursor:pointer">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_hospital">
            <input type="hidden" name="hospital_id" id="edit_id">
            <div class="modal-body">
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Hospital Name *</label>
                    <input type="text" name="name" id="edit_name" required style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                </div>
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Address *</label>
                    <input type="text" name="address" id="edit_address" required style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Alert Email *</label>
                        <input type="email" name="email" id="edit_email" required style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                    </div>
                    <div>
                        <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Phone Number *</label>
                        <input type="text" name="phone" id="edit_phone" required style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Departments</label>
                    <input type="text" name="departments" id="edit_departments" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                </div>
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Description</label>
                    <textarea name="description" id="edit_description" rows="2" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px"></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Update Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() { document.getElementById('addModal').classList.add('open'); }
function closeAddModal() { document.getElementById('addModal').classList.remove('open'); }
function openEditModal(h) {
    document.getElementById('edit_id').value = h.id;
    document.getElementById('edit_name').value = h.name;
    document.getElementById('edit_address').value = h.address;
    document.getElementById('edit_email').value = h.email;
    document.getElementById('edit_phone').value = h.phone;
    document.getElementById('edit_departments').value = h.departments || '';
    document.getElementById('edit_description').value = h.description || '';
    document.getElementById('editModal').classList.add('open');
}
function closeEditModal() { document.getElementById('editModal').classList.remove('open'); }
function openSidebar(){ document.querySelector(".adm-sidebar").classList.add("open"); document.getElementById("sidebarOverlay").classList.add("open"); }
function closeSidebar(){ document.querySelector(".adm-sidebar").classList.remove("open"); document.getElementById("sidebarOverlay").classList.remove("open"); }
</script>
</body>
</html>