<?php
require_once __DIR__.'/inc/db.php';
require_once __DIR__.'/inc/auth.php';
require_role(['BHW']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BHW Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-body">
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="#">BHW Panel</a>
    <div class="ms-auto d-flex gap-3">
      <span class="navbar-text small">Logged in as BHW</span>
      <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
    </div>
  </div>
</nav>
<div class="container py-4">
  <h1 class="h4 mb-4">Health Records</h1>
  <div class="alert alert-success small">
    Implement forms for maternal health records, tracking risk indicators, etc.
  </div>
</div>
</body>
</html>