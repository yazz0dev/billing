        </main>
        
        <footer class="app-footer">
            <p>&copy; <?php echo date('Y'); ?> Supermarket Billing System. All rights reserved.</p>
        </footer>
    </div> <!-- End page-wrapper -->

    <!-- Common scripts -->
    <script src="/billing/js/popup-notification.js"></script>
    
    <!-- Initialize popup notifications if script exists -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupNotification === 'function') {
                window.popupNotification = new PopupNotification();
            }
        });
    </script>

    <!-- Additional page-specific scripts -->
    <?php if(isset($pageScripts) && is_array($pageScripts)): ?>
        <?php foreach($pageScripts as $script): ?>
            <script src="<?php echo htmlspecialchars($script); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
