<?php /** Full-screen CV editor. */ ?>
<!doctype html>
<html lang="en">
<head>
<?= App\Core\View::partial('partials/head', ['title' => $title ?? '']) ?>
<link rel="stylesheet" href="<?= e(asset('css/editor.css')) ?>">
<script src="<?= e(asset('js/editor.js')) ?>" defer></script>
</head>
<body class="editor-page">
<a class="skip-link" href="#editor-form">Skip to the editor</a>
<?= $content ?>
<?= App\Core\View::partial('partials/flash') ?>
</body>
</html>
