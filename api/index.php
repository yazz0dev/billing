<?php
// api/index.php
// This file acts as the entry point for Vercel's PHP runtime.
// It simply includes your main router, which handles all further routing.

// Ensure the router is in the parent directory relative to this api/index.php file
require __DIR__ . '/../router.php';
?>
