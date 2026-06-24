<?php
/**
 * Admin dashboard (HTML page + login form)
 *
 * This file is different from other backend files:
 * - It outputs HTML for the browser, not JSON.
 * - After login, JavaScript (admin.js) calls the other admin_*.php APIs.
 *
 * Related APIs: admin_overview.php, admin_reports.php, admin_suspects.php, …
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/helpers.php';

startAppSession();
$isAdmin = !empty($_SESSION['admin_logged_in']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    require_once dirname(__DIR__) . '/db.php';
    $username = cleanInput($_POST['username'] ?? '', 50);
    $password = $_POST['password'] ?? '';
    if (verifyAdminLogin($conn, $username, $password)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_name'] = $username;
        $isAdmin = true;
    } else {
        $loginError = 'Invalid username or password.';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_name']);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Theft Track &amp; Reporting — Admin Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css?v=2">
</head>
<body>
<?php if (!$isAdmin): ?>
  <div class="admin-login-wrap">
    <div class="admin-card">
      <h1>Theft Track &amp; Reporting Admin</h1>
      <p class="sub">Sign in to manage reports, users, and investigations.</p>
      <?php if (!empty($loginError)): ?>
        <p class="error"><?= escapeHtml($loginError) ?></p>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="admin_login" value="1">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autocomplete="username">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <button type="submit" class="btn btn-primary" style="width:100%">Sign in</button>
      </form>
      <p class="sub" style="margin-top:16px;margin-bottom:0">
        Default: admin / admin123 — run <code>backend/seed_admin.php</code> once if needed.
      </p>
    </div>
  </div>
<?php else: ?>
  <div class="admin-layout">
    <aside class="admin-sidebar">
      <div class="sidebar-brand">
        <span class="sidebar-brand-name">Theft Track &amp; Reporting</span>
        <span class="sidebar-brand-sub">Admin Panel</span>
      </div>
      <nav class="sidebar-nav">
        <button type="button" class="nav-item active" data-tab="overview">Overview</button>
        <button type="button" class="nav-item" data-tab="reports">Theft Reports</button>
        <button type="button" class="nav-item" data-tab="users">Registered Users</button>
        <button type="button" class="nav-item" data-tab="suspects">Suspects</button>
        <button type="button" class="nav-item" data-tab="activity">Activity Log</button>
      </nav>
      <div class="sidebar-footer">
        <a href="../../thefttrack_fn/index.html" class="btn btn-ghost btn-sm sidebar-link">View Public Site</a>
        <a href="?logout=1" class="btn btn-ghost btn-sm sidebar-link">Log out</a>
      </div>
    </aside>

    <main class="admin-main">
      <header class="admin-topbar">
        <h1 id="page-title">Overview</h1>
        <span class="admin-user">Signed in as <?= escapeHtml($_SESSION['admin_name'] ?? 'Admin') ?></span>
      </header>

      <div id="admin-msg"></div>

      <section id="panel-overview" class="admin-panel active">
        <p class="overview-intro">Key metrics for theft reports and registered users. Use the sidebar to manage cases in detail.</p>
        <div id="overview-stats" class="admin-stats admin-stats--wide"></div>
        <div class="admin-two-col admin-two-col--triple">
          <div class="panel-card">
            <div class="panel-card-head">
              <h2>Recent Reports</h2>
              <button type="button" class="btn btn-ghost btn-sm" data-goto-tab="reports">View all</button>
            </div>
            <div id="overview-recent-reports"></div>
          </div>
          <div class="panel-card">
            <div class="panel-card-head">
              <h2>Registered Users</h2>
              <button type="button" class="btn btn-ghost btn-sm" data-goto-tab="users">View all</button>
            </div>
            <div id="overview-recent-users"></div>
          </div>
          <div class="panel-card">
            <h2>Latest Activity</h2>
            <div id="overview-activity"></div>
          </div>
        </div>
      </section>

      <section id="panel-reports" class="admin-panel">
        <div class="admin-toolbar">
          <input type="search" id="search-q" placeholder="Search tracking ID, name, email, phone…">
          <select id="filter-status">
            <option value="">All statuses</option>
            <option value="Pending">Pending</option>
            <option value="Under Investigation">Under Investigation</option>
            <option value="Resolved">Resolved</option>
          </select>
          <button type="button" class="btn btn-primary" id="btn-search">Search</button>
          <button type="button" class="btn btn-ghost" id="btn-clear">Clear</button>
        </div>
        <div class="reports-table-wrap" id="reports-wrap"><p style="padding:20px">Loading reports…</p></div>
      </section>

      <section id="panel-suspects" class="admin-panel">
        <p class="panel-desc">Add suspects with photo and details. Active suspects appear on the public <a href="../../thefttrack_fn/fraud.html" target="_blank" rel="noopener">Fraud Alerts</a> page.</p>
        <div class="admin-two-col admin-two-col--suspects">
          <div class="panel-card">
            <h2 id="suspect-form-title">Add suspect</h2>
            <form id="suspect-form" class="admin-form" enctype="multipart/form-data" novalidate>
              <input type="hidden" id="suspect-id" name="id" value="">
              <label for="suspect-alias">Alias / description *</label>
              <input type="text" id="suspect-alias" name="alias" required maxlength="150" placeholder="e.g. Unknown male, blue jacket">
              <label for="suspect-case-type">Case type</label>
              <input type="text" id="suspect-case-type" name="case_type" maxlength="100" placeholder="Phone snatching, fraud, etc.">
              <label for="suspect-last-seen">Last seen location *</label>
              <input type="text" id="suspect-last-seen" name="last_seen" required maxlength="200" placeholder="Nyabugogo Bus Park">
              <label for="suspect-description">Additional notes</label>
              <textarea id="suspect-description" name="description" rows="3" maxlength="5000" placeholder="Physical description, behaviour, etc."></textarea>
              <label for="suspect-risk">Risk level</label>
              <select id="suspect-risk" name="risk_level">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
              <label for="suspect-status">Status</label>
              <select id="suspect-status" name="status">
                <option value="active" selected>Active (visible on site)</option>
                <option value="inactive">Inactive (hidden)</option>
              </select>
              <label for="suspect-tracking">Linked case ID (optional)</label>
              <input type="text" id="suspect-tracking" name="linked_tracking_id" maxlength="20" placeholder="TT-2026-XXXXXX">
              <label for="suspect-photo">Photo (JPG, PNG, WebP — max 2MB)</label>
              <input type="file" id="suspect-photo" name="photo" accept="image/jpeg,image/png,image/webp">
              <div id="suspect-photo-preview" class="suspect-photo-preview hidden"></div>
              <div class="admin-form-actions">
                <button type="submit" class="btn btn-primary" id="btn-save-suspect">Save suspect</button>
                <button type="button" class="btn btn-ghost hidden" id="btn-cancel-suspect">Cancel edit</button>
              </div>
            </form>
          </div>
          <div class="panel-card panel-card--wide">
            <div class="panel-card-head">
              <h2>Published suspects</h2>
              <span id="suspects-count" class="users-count-bar"></span>
            </div>
            <div id="suspects-wrap" class="suspects-grid-wrap"><p style="padding:20px">Loading suspects…</p></div>
          </div>
        </div>
      </section>

      <section id="panel-users" class="admin-panel">
        <p class="panel-desc">View all registered users, search by name or email, and open their theft reports.</p>
        <div class="admin-toolbar">
          <input type="search" id="search-users" placeholder="Search users by name, email, phone…">
          <button type="button" class="btn btn-primary" id="btn-search-users">Search</button>
          <button type="button" class="btn btn-ghost" id="btn-clear-users">Clear</button>
        </div>
        <div id="users-count" class="users-count-bar"></div>
        <div class="reports-table-wrap" id="users-wrap"><p style="padding:20px">Loading users…</p></div>
      </section>

      <section id="panel-activity" class="admin-panel">
        <div class="panel-card">
          <h2>All case updates & investigation notes</h2>
          <div id="activity-full-list"></div>
        </div>
      </section>
    </main>
  </div>

  <div id="detail-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-dialog">
      <div class="modal-panel">
        <button type="button" class="modal-close" data-close-modal aria-label="Close">&times;</button>
        <div class="modal-header">
          <h2 id="modal-title">Report Details</h2>
        </div>
        <div class="modal-body" id="modal-body"></div>
      </div>
    </div>
  </div>

  <script src="admin.js?v=3"></script>
<?php endif; ?>
</body>
</html>
