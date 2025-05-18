<?php // templates/partials/footer.php
// $appConfig is available
?>
        </main> <!-- Closing main-content-area from layouts/main.php -->
        
        <footer class="app-footer">
            <p>© <?php echo date('Y'); ?> <?php echo $e($appConfig['name'] ?? 'Supermarket Billing System'); ?>. All rights reserved.</p>
        </footer>
    </div> <!-- End page-wrapper from layouts/* -->

    <!-- Common scripts are now loaded in the layout files (minimal.php, main.php) -->
    <!-- Page-specific scripts are also handled by the layout files based on $pageScripts variable -->
</body>
</html>
