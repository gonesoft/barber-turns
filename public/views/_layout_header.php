<?php
/**
 * Barber Turns shared HTML header layout.
 */

declare(strict_types=1);

$title = isset($page_title) ? "Barber Turns — {$page_title}" : 'Barber Turns';
$baseUrl = rtrim(bt_config()['base_url'] ?? '/', '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_text($title); ?></title>
    <link rel="stylesheet" href="<?= sanitize_text($baseUrl); ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?= sanitize_text($baseUrl); ?>/assets/css/tv.css">
</head>
<body>
<header class="site-header">
    <h1 class="site-title">Barber Turns</h1>
</header>
<main class="site-main">
