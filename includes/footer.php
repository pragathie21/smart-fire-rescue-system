<?php
/**
 * Smart Fire and Rescue Response System
 * Reusable Footer Layout
 */
?>
            </main> <!-- /.page-container -->

            <!-- Global Footer -->
            <footer class="app-footer">
                <div class="footer-left">
                    <span>&copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong>. Town of <?= TOWN_NAME ?> Emergency Response Division.</span>
                </div>
                <div class="footer-right">
                    <span class="badge-tech">PHP 8.2 &bull; MySQL &bull; Dijkstra Engine</span>
                </div>
            </footer>
        </div> <!-- /.app-main -->
    </div> <!-- /.app-layout -->

    <!-- Global Confirmation Modal Component -->
    <div class="modal-backdrop" id="confirmationModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h4 class="modal-title" id="modalTitle">Confirm Action</h4>
                <button type="button" class="modal-close" id="modalCancelBtn">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                Are you sure you want to proceed with this emergency operation?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modalDismissBtn">Cancel</button>
                <button type="button" class="btn btn-danger" id="modalConfirmBtn">Confirm Action</button>
            </div>
        </div>
    </div>

    <!-- Application JavaScript -->
    <script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
