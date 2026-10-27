<?php
// فعال‌سازی نمایش خطاها برای دیباگ
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'inc/config.php';
require_once 'inc/functions.php';
include 'inc/header.php';

// تنظیمات صفحه‌بندی
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12; // تعداد مقاله در هر صفحه
$offset = ($page - 1) * $per_page;

// شمارش کل مقالات منتشر شده
$total_stmt = $conn->query("SELECT COUNT(*) FROM articles WHERE status = 'published'");
$total_articles = $total_stmt->fetch_row()[0];
$total_pages = ceil($total_articles / $per_page);

// واکشی مقالات
$stmt = $conn->prepare("SELECT id, title, slug, image, content, created_at FROM articles WHERE status = 'published' ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$articles = $result->fetch_all(MYSQLI_ASSOC);
?>

<main class="container py-5">
    <h1 class="mb-4 fw-bold border-bottom pb-3">مقالات و اخبار</h1>
    
    <?php if (empty($articles)): ?>
        <div class="alert alert-info text-center py-4">هنوز مقاله‌ای منتشر نشده است.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($articles as $article): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 hover-shadow transition-all">
                        <?php if (!empty($article['image'])): ?>
                            <a href="<?php echo BASE_URL . 'article/' . $article['slug']; ?>">
                                <img src="<?php echo IMAGE_BASE_URL . 'upload/posts/' . htmlspecialchars($article['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($article['title']); ?>" style="height: 220px; object-fit: cover;">
                            </a>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title lh-base">
                                <a href="<?php echo BASE_URL . 'article/' . $article['slug']; ?>" class="text-decoration-none text-dark fw-bold">
                                    <?php echo htmlspecialchars($article['title']); ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted small flex-grow-1 mt-2 text-justify">
                                <?php 
                                    // ساخت خلاصه متن از محتوا
                                    $excerpt = mb_substr(strip_tags($article['content']), 0, 150, 'UTF-8') . '...';
                                    echo htmlspecialchars($excerpt); 
                                ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                                <small class="text-muted">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?php echo function_exists('to_jalali_date') ? to_jalali_date($article['created_at']) : $article['created_at']; ?>
                                </small>
                                <a href="<?php echo BASE_URL . 'article/' . $article['slug']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">ادامه مطلب</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- صفحه‌بندی -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-5">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link shadow-none" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

    <?php endif; ?>
</main>

<?php include 'inc/footer.php'; ?>