<?php
// Ensure session is started - but only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Billing System'; ?></title>
    
    <!-- Load fonts first -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    
    <!-- Force reload CSS by adding version parameter -->
    <link rel="stylesheet" href="/billing/global.css?v=<?php echo filemtime(__DIR__ . '/../global.css'); ?>">
    
    <!-- Add any additional page-specific styles below this line -->
    <?php if(isset($additionalStyles)) echo $additionalStyles; ?>
</head>
<body <?php echo isset($bodyClass) ? ' class="'.htmlspecialchars($bodyClass).'"' : ''; ?>>
    <div class="page-wrapper">
        <?php if(!isset($hideTopbar) || !$hideTopbar): ?>
            <?php include_once(__DIR__ . '/../ui/topbar.php'); ?>
        <?php endif; ?>

        <main class="main-content-area">
