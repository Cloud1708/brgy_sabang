<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<title>Barangay Health & Nutrition Portal</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Official Barangay Health and Nutrition Information Portal">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top py-1">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
      <img src="assets/img/sabang.jpg" alt="" width="60" height="60" class="d-inline-block align-text-top rounded-circle border">
      <span class="px-3">Brgy. Sabang Health</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <nav id="topNav" class="collapse navbar-collapse">
      <ul class="navbar-nav fw-bold ms-auto align-items-lg-center gap-3">
        <li class="nav-item"><a href="#announcements" class="nav-link" style="border-bottom: 2px solid #fd0d0dff;">Announcements</a></li>
        <li class="nav-item"><a href="#programs" class="nav-link" style="border-bottom: 2px solid #fd0d0dff;">Programs</a></li>
        <li class="nav-item"><a href="#about" class="nav-link" style="border-bottom: 2px solid #fd0d0dff;">About Barangay</a></li>
        <li class="nav-item"><a href="#contact" class="nav-link" style="border-bottom: 2px solid #fd0d0dff;">Contacts</a></li>
      </ul>
      <!-- Hidden staff login trigger (not visible, accessible via keyboard shortcut Alt+L) -->
      <a href="staff_login.php" class="visually-hidden" id="staffLoginLink" aria-hidden="true" tabindex="-1">Staff Login</a>
    </nav>
  </div>
</header>
<main class="page-content">