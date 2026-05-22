<?php if (!empty($layoutStarted)): ?>
            <footer class="app-footer mt-auto">
                <div class="container-fluid d-flex flex-column flex-sm-row justify-content-between gap-1">
                    <span><?= APP_NAME ?></span>
                    <span>Version <?= APP_VERSION ?></span>
                </div>
            </footer>
        </div>
    </div>
<?php else: ?>
    <footer class="app-footer mt-auto">
        <div class="container d-flex flex-column flex-sm-row justify-content-between gap-1">
            <span><?= APP_NAME ?></span>
            <span>Version <?= APP_VERSION ?></span>
        </div>
    </footer>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.table-responsive table').forEach((table) => {
    const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent.trim());
    table.querySelectorAll('tbody tr').forEach((row) => {
        Array.from(row.children).forEach((cell, index) => {
            if (headers[index] && !cell.hasAttribute('data-label')) {
                cell.setAttribute('data-label', headers[index]);
            }
        });
    });
});
</script>
</body>
</html>
