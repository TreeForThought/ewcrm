# app/views/layout.php
<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../auth.php';
$u = auth_user();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= e($title ?? 'eFed CRM') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 CDN (free) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= e(base_url()) ?>/assets/styles.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= e(base_url()) ?>/">eFed CRM</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <?php if ($u): ?>
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="<?= e(base_url()) ?>/feds">Feds</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(base_url()) ?>/members">Roster</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(base_url()) ?>/events">Events</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(base_url()) ?>/matches">Matches</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= e(base_url()) ?>/promos">Promos</a></li>
          <?php if (auth_check('admin')): ?>
            <li class="nav-item"><a class="nav-link" href="<?= e(base_url()) ?>/users">Users</a></li>
          <?php endif; ?>
        </ul>
        <div class="d-flex text-white-50 small align-items-center gap-3">
          <span><?= e($u['name']) ?> (<?= e($u['role']) ?>)</span>
          <a class="btn btn-sm btn-outline-light" href="<?= e(base_url()) ?>/logout">Logout</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</nav>

<main class="container py-4">
  <?php if ($m = flash('success')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
  <?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= e($m) ?></div><?php endif; ?>
  <?= $content ?? '' ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
