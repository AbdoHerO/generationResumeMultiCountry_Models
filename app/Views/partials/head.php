<?php /** @var string $title */ ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="app-base" content="<?= e(rtrim(url("/"), "/")) ?>">
<meta name="description" content="Create professional, country-specific CVs for France, Morocco, the United States, Canada and China, and download them as A4 PDFs.">
<meta name="theme-color" content="#3A4FD6">
<title><?= e(($title ?? '') !== '' ? $title . ' · ' . app_name() : app_name()) ?></title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/icons.js')) ?>" defer></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
