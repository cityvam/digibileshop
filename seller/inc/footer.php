        </main> <!-- بستن تگ main.main-content -->

        <!-- Footer -->
        <footer class="mt-auto py-3 bg-light border-top">
            <div class="container-fluid text-center">
                <span class="text-muted">© <?php echo jdate('Y'); ?> پنل فروشندگان. تمامی حقوق محفوظ است.</span>
            </div>
        </footer>

    </div> <!-- بستن تگ div.content-wrapper -->
</div> <!-- بستن تگ div.main-layout -->

<div class="toast-container position-fixed bottom-0 start-0 p-3" style="z-index: 1100">
</div>

<!-- Local JS Files -->
<!-- jQuery (must be first) -->
<script src="<?php echo rtrim(BASE_URL, '/'); ?>/assets/js/jquery-3.7.1.min.js"></script>

<!-- Bootstrap JS -->
<script src="<?php echo rtrim(BASE_URL, '/'); ?>/assets/js/bootstrap.bundle.min.js"></script>

<!-- اسکریپت باز و بسته شدن سایدبار -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // انتخاب دکمه جدید برای نمایش سایدبار در موبایل
    const mobileSidebarToggle = document.getElementById('sidebar-toggle-mobile');
    if (mobileSidebarToggle) {
        const mainLayout = document.querySelector('.main-layout');
        mobileSidebarToggle.addEventListener('click', () => {
            // استفاده از کلاس مخصوص نمایش سایدبار در موبایل
            mainLayout.classList.toggle('sidebar-mobile-show');
        });
    }
});

function showToast(message, type = 'success') {
    const toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) return;

    const toastId = 'toast-' + Date.now();
    const toastBgClass = type === 'success' ? 'text-bg-success' : (type === 'danger' ? 'text-bg-danger' : 'text-bg-info');

    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center ${toastBgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 5000 });

    toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());

    toast.show();
}
</script>

<?php if (isset($page_scripts)) echo $page_scripts; ?>

</body>
</html>