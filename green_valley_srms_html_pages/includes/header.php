<?php
/**
 * includes/header.php
 * ---------------------------------------------------------------------------
 * Top of every protected page. Renders <head> (links css/js), opens <body>,
 * pulls in the sidebar, and opens the main content area with a top bar.
 *
 * A page should set $page_title before including this file, and may optionally
 * set $page_subtitle for a short line of context under the title:
 *     $page_title    = 'Students';
 *     $page_subtitle = 'Add, search and manage student records';
 *     require_once __DIR__ . '/includes/header.php';
 * ---------------------------------------------------------------------------
 */

if (!isset($page_title)) {
    $page_title = 'Green Valley SRMS';
}
if (!isset($page_subtitle)) {
    $page_subtitle = '';
}
// Auth/functions are expected to be loaded by the page already; be defensive.
if (!function_exists('e')) {
    require_once __DIR__ . '/functions.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($page_title) ?> — Green Valley SRMS</title>
    <link rel="stylesheet" href="css/style.css">

    <!-- Progressive Web App: installable to a phone + works offline.
         The manifest and icons are static files; the offline behaviour lives
         in service-worker.js (registered from js/pwa.js). No DB access here. -->
    <meta name="theme-color" content="#1f5138">
    <meta name="description" content="Student Records Management System for Green Valley Secondary School, Murang'a County.">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="icon" href="icons/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GV Records">
</head>
<body>

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <header class="topbar">
        <button class="nav-toggle no-print" type="button" aria-label="Open menu"
                aria-controls="sidebar" aria-expanded="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 6h18M3 12h18M3 18h18"/>
            </svg>
        </button>
        <div class="topbar-title">
            <h1><?= e($page_title) ?></h1>
            <?php if ($page_subtitle !== ''): ?>
                <span class="page-sub"><?= e($page_subtitle) ?></span>
            <?php endif; ?>
        </div>
        <div class="topbar-meta">
            <div class="today"><?= e(date('D, j M Y')) ?></div>
            <div>Green Valley Secondary School</div>
        </div>
    </header>

    <?= function_exists('render_flashes') ? render_flashes() : '' ?>
