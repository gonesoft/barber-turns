<?php
/**
 * Barber Turns shared HTML header layout.
 */

declare(strict_types=1);

$title = isset($page_title) ? "Barber Turns — {$page_title}" : 'Finest Cutz Dominican Barber Shop';
$baseUrl = rtrim(bt_config()['base_url'] ?? '', '/');
$assetBase = $baseUrl !== '' ? $baseUrl : '';
$currentUser = current_user();
$currentRoute = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isNavUser = $currentUser !== null;
$isAdminNav = $currentUser !== null && in_array($currentUser['role'] ?? '', ['admin', 'owner'], true);
$navClass = 'site-nav' . ($isAdminNav ? '' : ' site-nav--static');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_text($title); ?></title>
    <link rel="stylesheet" href="<?= sanitize_text($assetBase); ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?= sanitize_text($assetBase); ?>/assets/css/tv.css">
</head>
<body>
<header class="site-header">
    <div class="site-branding">
        <h1 class="site-title">Finest Cutz Dominican Barber Shop</h1>
    </div>
    <?php if ($isNavUser): ?>
        <?php if ($isAdminNav): ?>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav-menu">
                <span class="nav-toggle__label">Menu</span>
                <span class="nav-toggle__icon" aria-hidden="true"></span>
            </button>
        <?php endif; ?>
        <nav class="<?= sanitize_text($navClass); ?>" id="site-nav-menu" aria-label="Primary navigation">
            <a class="site-nav__link <?= in_array($currentRoute, ['/', '/queue'], true) ? 'is-active' : ''; ?>" href="<?= sanitize_text($baseUrl); ?>/queue">Home</a>
            <?php if ($isAdminNav): ?>
                <a class="site-nav__link <?= $currentRoute === '/barbers' ? 'is-active' : ''; ?>" href="<?= sanitize_text($baseUrl); ?>/barbers">Barbers</a>
                <a class="site-nav__link <?= $currentRoute === '/users' ? 'is-active' : ''; ?>" href="<?= sanitize_text($baseUrl); ?>/users">Users</a>
                <button type="button" class="site-nav__link site-nav__button" id="reset-barbers-btn">Reset Barbers</button>
            <?php endif; ?>
            <a class="site-nav__link logout-link" href="<?= sanitize_text($baseUrl); ?>/logout">Log Out</a>
        </nav>
    <?php endif; ?>
</header>
<main class="site-main">
