<?php
/**
 * includes/footer.php
 * Phần cuối trang dùng chung: đóng thẻ <main> mở ở header.php, thêm footer
 * nhỏ, nhúng Bootstrap JS bundle (cần cho navbar-toggler, alert dismiss) và
 * main.js. Được include ở cuối mỗi file .php hiển thị giao diện.
 */
?>
</main>

<footer class="text-center text-muted py-4 border-top">
    <div class="container small">
        &copy; <?= date('Y') ?> Golden Lotus Restaurant &mdash; HSB2006 MET4 student project, for academic demonstration only.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/public/js/main.js"></script>
</body>
</html>
