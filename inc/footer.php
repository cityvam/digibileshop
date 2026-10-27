<?php
// inc/footer.php

// (جدید) واکشی تنظیمات داینامیک فوتر از جدول footer_settings
$footer_settings_stmt = $conn->query("SELECT setting_key, setting_value FROM footer_settings");
$footer_settings = [];
if ($footer_settings_stmt) {
    while ($row = $footer_settings_stmt->fetch_assoc()) {
        $footer_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// (جدید) مقادیر پیش‌فرض و آماده‌سازی متغیرهای استایل
$footer_logo_url = $footer_settings['footer_logo_url'] ?? '';
$footer_bg_type = $footer_settings['footer_bg_type'] ?? 'solid';
$footer_bg_color1 = $footer_settings['footer_bg_color1'] ?? '#f8f9fa';
$footer_bg_color2 = $footer_settings['footer_bg_color2'] ?? '#f8f9fa';
$footer_bg_gradient_direction = $footer_settings['footer_bg_gradient_direction'] ?? 'to right';
$footer_title_font_size = $footer_settings['footer_title_font_size'] ?? '18';
$footer_link_font_size = $footer_settings['footer_link_font_size'] ?? '16';
$footer_item_color = $footer_settings['footer_item_color'] ?? '#f0f0f0'; // (جدید) خواندن رنگ فونت از دیتابیس
$footer_title_color = $footer_settings['footer_title_color'] ?? '#ffffff'; // (جدید) خواندن رنگ عنوان‌ها از دیتابیس
$footer_paragraph_color = $footer_settings['footer_paragraph_color'] ?? '#e0e0e0'; // (جدید) خواندن رنگ متن‌ها از دیتابیس

$footer_style = "border-radius: 30px; margin: 1rem auto;";
$footer_style .= ($footer_bg_type === 'gradient') ? "background: linear-gradient({$footer_bg_gradient_direction}, {$footer_bg_color1}, {$footer_bg_color2});" : "background-color: {$footer_bg_color1};";

// واکشی لینک‌های فعال فوتر از دیتابیس

// (جدید) واکشی متن کپی‌رایت از دیتابیس
// (اصلاح) واکشی عنوان، توضیحات بخش "فروشگاه شما" و متن کپی‌رایت
$site_settings_footer_stmt = $conn->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('footer_copyright_text', 'footer_shop_title', 'footer_shop_description')");
$site_settings_footer = [];
if ($site_settings_footer_stmt) {
    while ($row = $site_settings_footer_stmt->fetch_assoc()) {
        $site_settings_footer[$row['setting_key']] = $row['setting_value'];
    }
}
$dynamic_copyright_text = $site_settings_footer['footer_copyright_text'] ?? '© تمامی حقوق مادی و معنوی این سایت محفوظ است.';
$footer_shop_title = $site_settings_footer['footer_shop_title'] ?? 'فروشگاه شما';
$footer_shop_description = $site_settings_footer['footer_shop_description'] ?? 'لورم ایپسوم متن ساختگی با تولید سادگی نامفهوم از صنعت چاپ و با استفاده از طراحان گرافیک است.';

$footer_links_stmt = $conn->query("SELECT * FROM footer_links WHERE is_active = 1 ORDER BY column_number, sort_order ASC");
// (جدید) واکشی عناوین و کلاس‌های ستون‌ها
// (اصلاح) واکشی نوع ستون و محتوای متنی
$column_settings_stmt = $conn->query("SELECT column_number, title, col_class, column_type, text_content FROM footer_column_titles ORDER BY column_number ASC");
$column_settings = [];
while ($row = $column_settings_stmt->fetch_assoc()) {
    $column_settings[$row['column_number']] = [
        'title' => $row['title'],
        'col_class' => $row['col_class'],
        'column_type' => $row['column_type'] ?? 'links', // مقدار پیش‌فرض 'links' است
        'text_content' => $row['text_content']
    ];
}
$footer_links = [];
if ($footer_links_stmt) {
    while ($row = $footer_links_stmt->fetch_assoc()) {
        $footer_links[$row['column_number']][] = $row;
    }
}

// (جدید) بررسی اینکه آیا کاربر ادمین است برای فعال کردن ویرایش AJAX
$is_admin_logged_in_for_footer_edit = (isset($_SESSION['is_admin_logged_in']) && ($_SESSION['is_admin_logged_in'] == 1 || $_SESSION['is_admin_logged_in'] == 2));

// (جدید) واکشی نمادهای اعتماد فعال از دیتابیس
// (اصلاح) کوئری برای هماهنگی با ساختار جدید جدول trust_seals
$trust_symbols_stmt = $conn->query("SELECT name, logo, settings, slug FROM trust_seals WHERE is_active = 1 ORDER BY id ASC");
$trust_symbols = [];
if ($trust_symbols_stmt) {
    while ($row = $trust_symbols_stmt->fetch_assoc()) {
        $settings = json_decode($row['settings'] ?? '{}', true); // دیکد کردن جیسون تنظیمات
        $html_code = '';
        // (اصلاح) به جای جستجوی کلید ثابت 'html_code'، اولین مقدار داخل آبجکت settings را به عنوان کد اسکریپت در نظر می‌گیریم.
        // این کار باعث می‌شود با ساختار فعلی دیتابیس (مثلا: enamad_script_code) سازگار باشد.
        if (is_array($settings) && !empty($settings)) {
            $html_code = reset($settings); // گرفتن اولین مقدار از آرایه
        }
        $row['html_code'] = html_entity_decode($html_code); // (اصلاح) کدهای HTML انکود شده را دیکد می‌کنیم
        $trust_symbols[] = $row; // افزودن ردیف کامل با اطلاعات جدید
    }
}

// (جدید) واکشی شبکه‌های اجتماعی فعال از جدول social_manager
$social_links_stmt = $conn->query("SELECT title, url, icon_class FROM social_manager WHERE is_active = 1 ORDER BY sort_order ASC");
$social_links = [];
if ($social_links_stmt) {
    while ($row = $social_links_stmt->fetch_assoc()) {
        $social_links[] = $row;
    }
}

?>

<div class="container-fluid p-4" style="background-color: #0754ed; border-radius: 30px; margin: 62px auto 1rem auto;border: 1px dashed #dddddd;">
    <div class="row align-items-center justify-content-between">
        <!-- ستون خبرنامه -->
        <div class="col-lg-6 col-md-12 mb-4 mb-lg-0">
            <h5 style="color: white;">با ما همراه باشید!</h5>
            <p class="text-muted small" style="color: white!important;">از آخرین تخفیف‌ها و جدیدترین محصولات باخبر شوید.</p>
            <form action="<?php echo BASE_URL; ?>newsletter_subscribe.php" method="POST" class="mb-2" id="newsletter-form">
                <div class="input-group" style="max-width: 350px;">
                    <input type="tel" name="mobile_number" class="form-control" placeholder="شماره موبایل خود را وارد کنید" required pattern="09[0-9]{9}">
                    <button class="btn btn-primary" type="submit">ثبت</button>
                </div>
            </form>
        </div>
        <!-- ستون شبکه‌های اجتماعی -->
        <div class="col-lg-4 col-md-12 text-lg-end text-center">
            <h5 class="mb-3" style="color: white!important;">ما را در شبکه‌های اجتماعی دنبال کنید</h5>
            <?php if (!empty($social_links)): ?>
                <?php foreach ($social_links as $social): ?>
                    <a href="<?php echo htmlspecialchars($social['url']); ?>" title="<?php echo htmlspecialchars($social['title']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-light btn-lg rounded-circle mx-1"><i class="<?php echo htmlspecialchars($social['icon_class']); ?>"></i></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .main-footer h5 {
        font-size: <?php echo htmlspecialchars($footer_title_font_size); ?>px;
        color: <?php echo htmlspecialchars($footer_title_color); ?> !important; /* (جدید) اعمال رنگ داینامیک به عناوین */
    }
    footer.main-footer p, footer.main-footer .text-muted {
        color: <?php echo htmlspecialchars($footer_paragraph_color); ?> !important; /* (جدید) اعمال رنگ داینامیک به متن‌ها */
    }
    footer.main-footer .footer-editable-link {
        font-size: <?php echo htmlspecialchars($footer_link_font_size); ?>px;
        color: <?php echo htmlspecialchars($footer_item_color); ?> !important; /* (جدید) اعمال رنگ داینامیک به لینک‌ها */
    }
    /* انیمیشن تغییر قیمت */
    @keyframes highlightPrice {
        0% { color: #0d6efd; transform: scale(1); }
        50% { color: #198754; transform: scale(1.1); }
        100% { color: #0d6efd; transform: scale(1); }
    }
    .price-highlight {
        animation: highlightPrice 0.4s ease-in-out;
    }
</style>

<footer class="main-footer container-fluid p-4" style="<?php echo $footer_style; ?>">
    <?php if (!empty($footer_logo_url)): ?>
        <img src="<?php echo htmlspecialchars($footer_logo_url); ?>" alt="لوگوی فوتر" style="max-height: 40px; filter: brightness(0) invert(1);">
    <?php else: ?>
        <!-- در صورت نبود لوگو، می‌توانید یک لوگوی پیش‌فرض یا عنوان سایت را نمایش دهید -->
    <?php endif; ?>
    <hr class="my-4">
    <div class="row justify-content-md-center">
        <?php foreach ($column_settings as $col_num => $settings): // (اصلاح) حلقه بر اساس جدول عناوین ستون‌ها ?>
            <?php $col_class = $settings['col_class'] ?? 'col-lg-2'; ?>
            <div class="col-12 col-md-6 <?php echo htmlspecialchars($col_class); ?> mb-4 mb-md-0">
                <h5>
                    <?php echo htmlspecialchars($settings['title'] ?? 'لینک‌ها'); ?>
                </h5>
                <?php
                // (جدید) بررسی نوع ستون برای نمایش محتوای متنی یا لینک‌ها
                if (isset($settings['column_type']) && $settings['column_type'] === 'text'):
                    ?>
                    <p class="text-muted small">
                        <?php echo nl2br(htmlspecialchars($settings['text_content'] ?? '')); ?>
                    </p>
                    <?php
                    // اگر نوع ستون 'links' یا تعریف نشده باشد، لینک‌ها را نمایش بده
                elseif (isset($footer_links[$col_num]) && !empty($footer_links[$col_num])): ?>
                    <ul class="list-unstyled text-small">
                        <?php foreach ($footer_links[$col_num] as $link): ?>
                            <?php
                            // (جدید) بررسی اینکه آیا لینک باید در تب جدید باز شود یا خیر
                            $target_attr = ($link['target_blank'] ?? 0) == 1 ? ' target="_blank" rel="noopener noreferrer"' : '';
                            ?>
                            <li class="mb-2">
                                <a class="link-secondary text-decoration-none footer-editable-link"
                                    href="<?php echo htmlspecialchars($link['url']); ?>" data-link-id="<?php echo $link['id']; ?>"<?php echo $target_attr; ?>>
                                    <?php echo htmlspecialchars($link['title']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <hr style="margin: 11px 0px 30px 0;">
        
        <!-- ستون نمادهای اعتماد -->
        <div class="col-12 col-md-6 col-lg-12 mb-4 mb-lg-0">
            <h5>نمادهای اعتماد</h5>
            <div class="d-flex flex-wrap gap-3 justify-content-center mt-4">
                <?php if (!empty($trust_symbols)): ?>
                        <?php foreach ($trust_symbols as $symbol): ?>
                            <?php
                                // (جدید) ساخت یک شناسه منحصر به فرد برای هر نماد
                                $symbol_id = 'trust-symbol-' . htmlspecialchars($symbol['slug']);
                                // (جدید) آماده‌سازی آدرس تصویر جایگزین
                                $fallback_image_url = IMAGE_BASE_URL . 'upload/symbols/' . htmlspecialchars($symbol['logo']);
                            ?>
                            <div class="trust-symbol-item" id="<?php echo $symbol_id; ?>" data-fallback-img="<?php echo $fallback_image_url; ?>" data-alt="<?php echo htmlspecialchars($symbol['name']); ?>" style="max-width: 82px; min-width: 82px; min-height: 82px; background-color: #fff; border-radius: 10px; padding: 5px;">
                                <?php if (!empty($symbol['html_code'])): ?>
                                    <?php echo $symbol['html_code']; // (اصلاح) کد HTML دیکد شده را مستقیم نمایش می‌دهیم ?>
                                    <!-- (جدید) تصویر جایگزین که در ابتدا مخفی است -->
                                    <img src="<?php echo $fallback_image_url; ?>" alt="<?php echo htmlspecialchars($symbol['name']); ?>" style="display: none; width: 80px; height: auto;">
                                <?php else: ?>
                                    <a href="#" target="_blank" rel="noopener noreferrer"><img loading="lazy" width="80" height="80" src="<?php echo IMAGE_BASE_URL . 'upload/symbols/' . htmlspecialchars($symbol['logo']); ?>" alt="<?php echo htmlspecialchars($symbol['name']); ?>"></a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        // (جدید) کوئری برای واکشی slug نمادها جهت استفاده در جاوااسکریپت
        $trust_symbols_stmt_for_js = $conn->query("SELECT slug FROM trust_seals WHERE is_active = 1 AND settings IS NOT NULL AND settings != ''");
        $trust_seal_slugs = [];
        if ($trust_symbols_stmt_for_js) {
            while ($row = $trust_symbols_stmt_for_js->fetch_assoc()) {
                $trust_seal_slugs[] = $row['slug'];
            }
        }
        // (جدید) تبدیل آرایه slug ها به JSON برای استفاده در جاوااسکریپت
        $trust_seal_slugs_json = json_encode($trust_seal_slugs);
        ?>

    </div>
    <hr class="my-4">
    <div class="row">
        <div class="col-12 text-center">
            <small class="d-block text-muted">
                <?php
                // (اصلاح) جایگزینی placeholder سال شمسی در متن کپی‌رایت
                $current_jalali_year = to_jalali_date(date("Y-m-d"), 'yyyy'); // از functions.php
                echo str_replace('{{current_jalali_year}}', $current_jalali_year, $dynamic_copyright_text);
                ?>
            </small>
        </div>
    </div>
</footer>

<!-- (جدید) نوار مقایسه محصولات -->
<div class="compare-bar" id="compare-bar" style="display: none;">
    <div class="container-xl d-flex justify-content-between align-items-center h-100">
        <div class="compare-bar-items" id="compare-bar-items">
            <!-- آیتم‌های مقایسه اینجا با جاوااسکریپت اضافه می‌شوند -->
        </div>
        <div class="compare-bar-actions">
            <a href="<?php echo BASE_URL; ?>compare.php" class="btn btn-primary" id="compare-action-btn"
                style="display: none;">مقایسه</a>
            <button class="btn btn-sm btn-outline-danger" id="clear-compare-list">حذف همه</button>
        </div>
    </div>
</div>

<!-- (جدید) مودال موفقیت عضویت در خبرنامه -->
<div class="modal fade" id="newsletterSuccessModal" tabindex="-1" aria-labelledby="newsletterSuccessModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i class="fa fa-check-circle text-success mb-3" style="font-size: 4rem;"></i>
                <h5 class="modal-title mb-3" id="newsletterSuccessModalLabel">شما با موفقیت عضو خبرنامه شدید!</h5>
                <p class="text-muted small">از این پس از آخرین تخفیف‌ها و جدیدترین محصولات ما باخبر خواهید شد.</p>
            </div>
            <div class="modal-footer d-flex justify-content-center border-top-0 pt-0">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">متوجه شدم</button>
            </div>
        </div>
    </div>
</div>

<!-- (جدید) مودال عمومی برای نمایش پیام‌های خطا و اطلاع‌رسانی -->
<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i id="infoModalIcon" class="fa fa-times-circle text-danger mb-3" style="font-size: 4rem;"></i>
                <h5 class="modal-title mb-3" id="infoModalTitle">خطا</h5>
                <p class="text-muted small" id="infoModalMessage">متن پیام در اینجا قرار می‌گیرد.</p>
            </div>
            <div class="modal-footer d-flex justify-content-center border-top-0 pt-0">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">متوجه شدم</button>
            </div>
        </div>
    </div>
</div>


<!-- (جدید) مودال تایید خروج از حساب کاربری -->
<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutConfirmModalLabel" style="font-size: 17px;">از حساب کاربری خارج
                    می‌شوید؟</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p style="font-size: 14.5px; margin-bottom: 0;">بعد از خروج، به سبد خرید فعلی‌تان دسترسی نخواهید داشت.
                    هروقت بخواهید می‌توانید مجددا وارد شوید و خریدتان را ادامه دهید.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">انصراف</button>
                <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-danger btn-sm">خروج از حساب</a>
            </div>
        </div>
    </div>
</div>

<!-- (جدید) مودال تایید افزودن به سبد خرید -->
<div class="modal fade" id="addToCartSuccessModal" tabindex="-1" aria-labelledby="addToCartSuccessModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i class="fa fa-check-circle text-success mb-3" style="font-size: 4rem;"></i>
                <h5 class="modal-title mb-3" id="addToCartSuccessModalLabel">کالا به سبد خرید اضافه شد!</h5>
            </div>
            <!-- (اصلاح) بخش انتخاب بیمه به اینجا منتقل شد تا به درستی پیدا شود -->
            <div id="modal-insurance-selection"></div>
            <div class="modal-footer d-flex justify-content-center border-top-0 pt-0">
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="btn-continue-shopping">ادامه خرید</button>
                    <button type="button" class="btn btn-primary" id="btn-go-to-cart">رفتن به سبد خرید</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- (تغییر) بارگذاری محلی کتابخانه‌ها برای جلوگیری از خطای شبکه -->
<script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
<!-- noUiSlider JS -->
<script src="<?php echo BASE_URL; ?>assets/js/nouislider.min.js"></script>
<!-- (جدید) Swiper JS for sliders -->
<script src="<?php echo BASE_URL; ?>assets/js/swiper-bundle.min.js"></script>

<!-- (جدید) اسکریپت‌های مربوط به مقایسه -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const compareBar = document.getElementById('compare-bar');
        const compareBarItems = document.getElementById('compare-bar-items');
        const compareActionBtn = document.getElementById('compare-action-btn');
        const clearCompareBtn = document.getElementById('clear-compare-list');
        const newsletterForm = document.getElementById('newsletter-form');

        // (جدید) تابع برای تبدیل اعداد انگلیسی به فارسی در جاوااسکریپت
        function toPersianNumJS(numStr) {
            const persian = { 0: '۰', 1: '۱', 2: '۲', 3: '۳', 4: '۴', 5: '۵', 6: '۶', 7: '۷', 8: '۸', 9: '۹' };
            return numStr.toString().replace(/[0-9]/g, (w) => persian[w]);
        }

        // (جدید) تابع برای راه‌اندازی تایمرهای شمارش معکوس
        function initializeCountdowns() {
            const countdownElements = document.querySelectorAll('.countdown');
            countdownElements.forEach(el => {
                const endTime = new Date(el.dataset.endTime.replace(' ', 'T')).getTime(); // سازگار با فرمت دیتابیس

                const interval = setInterval(() => {
                    const now = new Date().getTime();
                    const distance = endTime - now;

                    if (distance < 0) {
                        clearInterval(interval);
                        // می‌توانید بنر را مخفی کنید یا پیام "پایان یافت" نمایش دهید
                        el.closest('.product-card-banner').style.display = 'none';
                        return;
                    }

                    const hours = String(Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
                    const minutes = String(Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
                    const seconds = String(Math.floor((distance % (1000 * 60)) / 1000)).padStart(2, '0');

                    el.innerHTML = `${toPersianNumJS(hours)}:${toPersianNumJS(minutes)}:${toPersianNumJS(seconds)}`;

                }, 1000);
            });
        }

        initializeCountdowns(); // فراخوانی تابع تایمر

        // (جدید) منطق ارسال ایجکس فرم خبرنامه
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const form = e.target;
                const submitButton = form.querySelector('button[type="submit"]');
                const mobileInput = form.querySelector('input[name="mobile_number"]');
                const originalButtonText = submitButton.innerHTML;

                // نمایش حالت لودینگ
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                const formData = new FormData(form);
                formData.append('ajax', '1'); // افزودن پارامتر برای شناسایی درخواست ایجکس در سمت سرور

                fetch(form.action, {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    // (جدید) ابتدا بررسی می‌کنیم که آیا پاسخ دریافتی از نوع JSON است یا خیر
                    const contentType = response.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json().then(data => {
                            if (data.status === 'success') {
                                // نمایش مودال موفقیت
                                const successModal = new bootstrap.Modal(document.getElementById('newsletterSuccessModal'));
                                successModal.show();
                                mobileInput.value = ''; // پاک کردن فیلد
                            } else {
                                // (اصلاح) نمایش خطا در مودال عمومی به جای alert
                                const infoModalEl = document.getElementById('infoModal');
                                const infoModal = new bootstrap.Modal(infoModalEl);
                                
                                // تنظیم محتوای مودال
                                infoModalEl.querySelector('#infoModalIcon').className = 'fa fa-exclamation-triangle text-warning mb-3'; // آیکون هشدار
                                infoModalEl.querySelector('#infoModalTitle').textContent = 'توجه';
                                infoModalEl.querySelector('#infoModalMessage').textContent = data.message || 'پاسخ نامشخص از سرور.';
                                infoModal.show();
                            }
                        });
                    } else {
                        // اگر پاسخ JSON نبود، آن را به عنوان متن می‌خوانیم و در کنسول نمایش می‌دهیم
                        return response.text().then(text => {
                            console.error("پاسخ سرور از نوع JSON نیست:", text);
                            alert('خطا در پردازش پاسخ از سرور. لطفاً کنسول مرورگر را برای جزئیات بیشتر بررسی کنید.');
                        });
                    }
                }).catch(error => {
                    console.error('Newsletter subscription error:', error);
                    alert('خطا در ارتباط با سرور.');
                }).finally(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                });
            });
        }

        window.updateCompareBar = function (items) {
            if (!compareBar) return;

            if (items.length === 0) {
                compareBar.style.display = 'none';
                return;
            }

            compareBar.style.display = 'block';
            compareBarItems.innerHTML = '';
            items.forEach(item => {
                compareBarItems.innerHTML += `
                <div class="compare-item">
                    <img src="${item.image ? '<?php echo IMAGE_BASE_URL; ?>products/' + item.image : 'https://via.placeholder.com/50'}" alt="${item.name}">
                </div>
            `;
            });

            compareActionBtn.style.display = items.length >= 2 ? 'inline-block' : 'none';
        }

        clearCompareBtn.addEventListener('click', function () {
            fetch('<?php echo BASE_URL; ?>compare_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=clear'
            }).then(res => res.json()).then(data => {
                if (data.status === 'success') {
                    updateCompareBar([]);
                    // به‌روزرسانی دکمه‌ها در صفحه محصول (اگر وجود داشته باشند)
                    document.querySelectorAll('.compare-btn.active').forEach(btn => {
                        btn.classList.remove('active', 'text-primary');
                        btn.querySelector('i').classList.replace('fa-check-square', 'fa-square');
                    });
                }
            });
        });

        // بارگذاری اولیه نوار مقایسه در صورت وجود آیتم در سشن
        fetch('<?php echo BASE_URL; ?>compare_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_list' // یک اکشن فرضی برای گرفتن لیست
        }).then(res => res.json()).then(data => {
            if (data.status === 'success' && data.compare_list) {
                updateCompareBar(data.compare_list);
            }
        });

        // (جدید) منطق مگامنو
        const mainCatItems = document.querySelectorAll('.mega-menu-main-cats li');
        const subCatContents = document.querySelectorAll('.mega-menu-sub-cats .sub-cat-content');

        // فعال کردن اولین آیتم به صورت پیش‌فرض
        if (mainCatItems.length > 0) {
            mainCatItems[0].classList.add('active');
            const firstTarget = mainCatItems[0].dataset.target;
            if (firstTarget && document.querySelector(firstTarget)) {
                document.querySelector(firstTarget)?.classList.add('active');
            }
        }

        mainCatItems.forEach(item => {
            item.addEventListener('mouseenter', function () {
                mainCatItems.forEach(i => i.classList.remove('active'));
                subCatContents.forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                const target = this.dataset.target;
                if (target && document.querySelector(target)) {
                    document.querySelector(target)?.classList.add('active');
                }
            });
        });

        // (جدید) منطق جستجوی پیشرفته
        const searchInput = document.getElementById('main-search-input');
        const searchForm = searchInput.closest('form');
        const recentSearchesContainer = document.getElementById('recent-searches-container');
        const clearRecentBtn = document.getElementById('clear-recent-searches');
        const suggestionsBox = document.getElementById('search-suggestions');
        const liveSearchResultsContainer = document.getElementById('live-search-results');
        const defaultSuggestions = document.getElementById('default-suggestions');

        function getRecentSearches() {
            return JSON.parse(localStorage.getItem('recentSearches') || '[]');
        }

        function saveRecentSearch(term) {
            let searches = getRecentSearches();
            // حذف جستجوی تکراری
            searches = searches.filter(s => s !== term);
            // افزودن جستجوی جدید به ابتدا
            searches.unshift(term);
            // محدود کردن به ۵ آیتم اخیر
            localStorage.setItem('recentSearches', JSON.stringify(searches.slice(0, 5)));
        }

        function renderRecentSearches() {
            const searches = getRecentSearches();
            recentSearchesContainer.innerHTML = '';
            if (searches.length > 0) {
                searches.forEach(term => {
                    recentSearchesContainer.innerHTML += `<a href="<?php echo BASE_URL; ?>search.php?q=${encodeURIComponent(term)}" class="btn btn-light btn-sm rounded-pill">${term}</a>`;
                });
                clearRecentBtn.style.display = 'inline-block';
            } else {
                recentSearchesContainer.innerHTML = '<p class="text-muted small w-100">جستجوی اخیر وجود ندارد.</p>';
                clearRecentBtn.style.display = 'none';
            }
        }

        // تابع debounce برای جلوگیری از ارسال درخواست‌های مکرر
        function debounce(func, delay) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), delay);
            };
        }

        const handleLiveSearch = debounce(function (query) {
            if (query.length < 2) {
                liveSearchResultsContainer.innerHTML = '';
                liveSearchResultsContainer.style.display = 'none';
                defaultSuggestions.style.display = 'block';
                return;
            }

            fetch(`<?php echo BASE_URL; ?>ajax_live_search.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        let html = '<div class="list-group list-group-flush">';
                        data.forEach(product => {
                            const imageUrl = product.image ? `<?php echo IMAGE_BASE_URL; ?>upload/products/${product.image}` : 'https://via.placeholder.com/50';
                            const productUrl = `product-${product.id}/${product.slug}`;
                            html += `
                            <a href="${productUrl}" class="list-group-item list-group-item-action d-flex align-items-center">
                                <img src="${imageUrl}" alt="${product.name}" class="me-3 rounded">
                                <span class="product-name">${product.name}</span>
                            </a>
                        `;
                        });
                        html += '</div>';
                        liveSearchResultsContainer.innerHTML = html;
                        liveSearchResultsContainer.style.display = 'block';
                        defaultSuggestions.style.display = 'none';
                    } else {
                        liveSearchResultsContainer.innerHTML = '<p class="p-3 text-muted small">محصولی یافت نشد.</p>';
                        liveSearchResultsContainer.style.display = 'block';
                        defaultSuggestions.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Live search error:', error);
                });
        }, 300); // تاخیر ۳۰۰ میلی‌ثانیه‌ای

        if (searchInput) {
            searchForm.addEventListener('submit', function () {
                if (searchInput.value.trim()) {
                    saveRecentSearch(searchInput.value.trim());
                }
            });

            searchInput.addEventListener('focus', function () {
                if (this.value.length < 2) {
                    renderRecentSearches();
                    liveSearchResultsContainer.style.display = 'none';
                    defaultSuggestions.style.display = 'block';
                }
            });

            clearRecentBtn.addEventListener('click', function () {
                localStorage.removeItem('recentSearches');
                renderRecentSearches();
            });

            // برای بستن کادر هنگام کلیک بیرون از آن
            document.addEventListener('click', (e) => {
                if (!suggestionsBox.contains(e.target) && e.target !== searchInput) {
                    suggestionsBox.style.display = 'none';
                }
            });

            searchInput.addEventListener('input', function () {
                handleLiveSearch(this.value);
            });

            // (جدید) منطق ارسال ایجکس فرم افزودن به سبد خرید
            document.body.addEventListener('submit', function (e) {
                if (e.target.matches('.ajax-add-to-cart')) {
                    e.preventDefault();
                    const form = e.target;
                    const submitButton = form.querySelector('button[type="submit"]');
                    const originalButtonText = submitButton.innerHTML;

                    // نمایش حالت لودینگ روی دکمه
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                    const formData = new FormData(form);
                    formData.append('dry_run', '1'); // (جدید) ارسال پرچم درخواست آزمایشی

                    fetch('<?php echo BASE_URL; ?>cart_process.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest' // برای تشخیص AJAX در PHP
                        },
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                const responseData = data.data;
                                const insuranceContainer = document.getElementById('modal-insurance-selection');
                                insuranceContainer.innerHTML = ''; // پاک کردن محتوای قبلی

                                // (جدید) ذخیره اطلاعات محصول در مودال برای استفاده در مرحله بعد
                                const modalElement = document.getElementById('addToCartSuccessModal');
                                modalElement.dataset.pendingOfferId = responseData.offer_id;
                                modalElement.dataset.pendingQuantity = formData.get('quantity');
                                document.getElementById('addToCartSuccessModalLabel').textContent = 'مشخصات محصول و خدمات'; // تغییر عنوان مودال

                                // (جدید) بررسی اینکه آیا کاربر قبلاً بیمه انتخاب کرده است
                                const hasSelectedInsurance = responseData.selected_insurance_ids && responseData.selected_insurance_ids.length > 0;

                                // (جدید) اگر بیمه‌ای برای محصول وجود داشت و کاربر بیمه‌ای انتخاب نکرده بود، بخش انتخاب را بساز
                                if (responseData.available_insurances && responseData.available_insurances.length > 0 && !hasSelectedInsurance) {
                                    const basePrice = parseFloat(responseData.base_price);
                                    let insuranceCost = 0;
                                    let insuranceHtml = `
                                <div class="card-footer bg-light insurance-selection-box" style="margin: 0px 20px 22px 20px; padding: 11px 20px 7px 20px !important; border-radius: 11px;">
                                    <label class="form-label fw-bold small w-100 mb-2">خدمات بیمه (اختیاری)</label>
                                    <div class="insurance-loader spinner-border spinner-border-sm text-primary" role="status"></div>
                            `;
                                    responseData.available_insurances.forEach(ins => {
                                        const isChecked = responseData.selected_insurance_ids.includes(ins.id.toString());
                                        if (isChecked) {
                                            insuranceCost += (basePrice * parseFloat(ins.price_percentage)) / 100;
                                        }
                                        insuranceHtml += `
                                    <div class="form-check small d-flex justify-content-between align-items-center">
                                        <div>
                                            <input class="form-check-input modal-insurance-checkbox" type="checkbox" value="${ins.id}" id="modal-insurance-${ins.id}" ${isChecked ? 'checked' : ''} data-offer-id="${responseData.offer_id}" data-percentage="${ins.price_percentage}">
                                            <label class="form-check-label" for="modal-insurance-${ins.id}">
                                                ${ins.name}
                                                <span class="text-muted">(${toPersianNumJS(ins.duration_months)} ماهه)</span>
                                            </label>
                                        </div>
                                    </div>
                                `;
                                    });
                                    insuranceHtml += '</div>';
                                    insuranceContainer.innerHTML = insuranceHtml;

                                    // (جدید) ساخت بخش جداگانه برای نمایش قیمت
                                    const totalPrice = basePrice + insuranceCost;
                                    const priceDetailsHtml = `
                                        <div class="card-footer bg-white" style="margin: 0px 20px 22px 20px; padding: 11px 20px 7px 20px !important; border-radius: 11px; border: 1px solid #e9ecef;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="small text-muted">قیمت محصول:</span>
                                                <span class="small">${new Intl.NumberFormat('fa-IR').format(basePrice)} تومان</span>
                                            </div>
                                            <div id="modal-price-details-extra" style="display: ${insuranceCost > 0 ? 'block' : 'none'};">
                                                <div class="d-flex justify-content-between align-items-center mt-2 mb-2">
                                                    <span class="small text-muted">بیمه:</span>
                                                    <span class="small modal-insurance-cost">${new Intl.NumberFormat('fa-IR').format(insuranceCost)} تومان</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center border-top pt-2">
                                                    <span class="small fw-bold">قیمت نهایی کالا:</span>
                                                    <span class="fw-bold text-primary modal-total-price" data-base-price="${basePrice}">${new Intl.NumberFormat('fa-IR').format(totalPrice)} تومان</span>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                    insuranceContainer.insertAdjacentHTML('beforeend', priceDetailsHtml);

                                    // (جدید) استایل اولیه را بر اساس وضعیت چک‌باکس‌ها تنظیم کن
                                    updateInsuranceBoxStyle();

                                    // نمایش مودال موفقیت
                                    const successModal = new bootstrap.Modal(document.getElementById('addToCartSuccessModal'));
                                    successModal.show();

                                    // به‌روزرسانی تعداد آیتم‌های سبد خرید در هدر
                                    const cartBadge = document.querySelector('.badge.bg-danger');
                                    if (cartBadge) {
                                        cartBadge.textContent = toPersianNumJS(data.cart_item_count);
                                    }
                                } else {
                                    // (جدید) اگر بیمه نداشت یا قبلاً انتخاب شده بود، مستقیم به سبد خرید اضافه کن و برو به cart.php
                                    const actualFormData = new FormData();
                                    actualFormData.append('action', 'add');
                                    actualFormData.append('offer_id', responseData.offer_id);
                                    actualFormData.append('quantity', formData.get('quantity'));
                                    
                                    // اگر بیمه انتخاب شده بود، آن را به فرم اضافه کن
                                    if (hasSelectedInsurance) {
                                        responseData.selected_insurance_ids.forEach(id => {
                                            actualFormData.append('insurance[]', id);
                                        });
                                    }

                                    fetch('<?php echo BASE_URL; ?>cart_process.php', {
                                        method: 'POST',
                                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                        body: actualFormData
                                    })
                                    .then(resp => resp.json())
                                    .then(realData => {
                                        if (realData.status === 'success') {
                                            window.location.href = '<?php echo BASE_URL; ?>cart.php';
                                        } else {
                                            alert(realData.message);
                                        }
                                    })
                                    .catch(err => console.error(err));
                                }

                            } else {
                                // نمایش پیام خطا
                                alert(data.message);
                            }
                        })
                        .catch(error => console.error('Error:', error))
                        .finally(() => {
                            // بازگرداندن دکمه به حالت اولیه
                            submitButton.disabled = false;
                            submitButton.innerHTML = originalButtonText;
                        });
                }
            });

            // (جدید) تابعی برای به‌روزرسانی استایل کادر بیمه بر اساس انتخاب کاربر
            function updateInsuranceBoxStyle() {
                const insuranceContainer = document.getElementById('modal-insurance-selection');
                if (!insuranceContainer) return;

                const insuranceBox = insuranceContainer.querySelector('.card-footer');
                if (!insuranceBox) return;

                const anyInsuranceChecked = insuranceContainer.querySelector('.modal-insurance-checkbox:checked');

                if (anyInsuranceChecked) {
                    insuranceBox.style.setProperty('border', '1px dashed #c3e6cb');
                    insuranceBox.style.setProperty('background-color', '#d4edda', 'important');
                    insuranceBox.style.setProperty('color', '#155724');
                } else {
                    insuranceBox.style.setProperty('border', '1px dashed #cdcdcd');
                    insuranceBox.style.setProperty('background-color', '#f8f9fa', 'important'); // بازگشت به رنگ bg-light
                    insuranceBox.style.setProperty('color', 'inherit');
                }
            }

            // (جدید) تابع محاسبه و نمایش قیمت در مودال
            function updateModalPrice() {
                const priceEl = document.querySelector('.modal-total-price');
                const extraDetailsEl = document.getElementById('modal-price-details-extra');
                const insuranceEl = document.querySelector('.modal-insurance-cost');
                if (!priceEl || !extraDetailsEl) return;
                
                const basePrice = parseFloat(priceEl.dataset.basePrice);
                let insuranceCost = 0;
                
                document.querySelectorAll('.modal-insurance-checkbox:checked').forEach(cb => {
                    const percentage = parseFloat(cb.dataset.percentage);
                    insuranceCost += (basePrice * percentage) / 100;
                });

                // (جدید) نمایش یا مخفی کردن بخش بیمه و قیمت نهایی
                if (insuranceCost > 0) {
                    extraDetailsEl.style.display = 'block';
                } else {
                    extraDetailsEl.style.display = 'none';
                }
                
                const total = basePrice + insuranceCost;
                priceEl.textContent = new Intl.NumberFormat('fa-IR').format(total) + ' تومان';
                
                if (insuranceEl) {
                    insuranceEl.textContent = new Intl.NumberFormat('fa-IR').format(insuranceCost) + ' تومان';
                }

                // اعمال انیمیشن
                priceEl.classList.remove('price-highlight');
                void priceEl.offsetWidth; // تریگر کردن reflow برای ریست انیمیشن
                priceEl.classList.add('price-highlight');
            }

            // (اصلاح) منطق تغییر بیمه: فقط آپدیت UI (قیمت و استایل) بدون ارسال درخواست
            document.body.addEventListener('change', function (e) {
                if (e.target.matches('.modal-insurance-checkbox')) {
                    updateModalPrice();
                    updateInsuranceBoxStyle();
                }
            });

            // (جدید) تابع ذخیره بیمه و انجام عملیات بعدی (رفتن به سبد خرید یا بستن مودال)
            function saveInsuranceAndAction(actionType) {
                const modalElement = document.getElementById('addToCartSuccessModal');
                const checkboxes = document.querySelectorAll('.modal-insurance-checkbox');
                
                // (اصلاح) دریافت شناسه پیشنهاد و تعداد از دیتاست مودال (یا چک‌باکس‌ها به عنوان پشتیبان)
                let offerId = modalElement.dataset.pendingOfferId;
                const quantity = modalElement.dataset.pendingQuantity || 1;

                if (!offerId && checkboxes.length > 0) {
                    offerId = checkboxes[0].dataset.offerId;
                }

                const selectedInsurances = Array.from(document.querySelectorAll('.modal-insurance-checkbox:checked')).map(cb => cb.value);
                
                // تغییر متن دکمه برای نشان دادن پردازش
                const btn = actionType === 'cart' ? document.getElementById('btn-go-to-cart') : document.getElementById('btn-continue-shopping');
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> در حال پردازش...';

                const formData = new FormData();
                formData.append('action', 'add');
                formData.append('offer_id', offerId);
                formData.append('quantity', quantity); // (اصلاح) ارسال تعداد واقعی محصول
                selectedInsurances.forEach(id => formData.append('insurance[]', id));

                fetch('<?php echo BASE_URL; ?>cart_process.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // (جدید) به‌روزرسانی بج سبد خرید
                    if (data.status === 'success' && data.cart_item_count !== undefined) {
                        const cartBadge = document.querySelector('.badge.bg-danger');
                        if (cartBadge) cartBadge.textContent = toPersianNumJS(data.cart_item_count);
                    }

                    if (actionType === 'cart') {
                        window.location.href = '<?php echo BASE_URL; ?>cart.php';
                    } else {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('addToCartSuccessModal'));
                        if(modal) modal.hide();
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (actionType === 'cart') window.location.href = '<?php echo BASE_URL; ?>cart.php';
                });
            }

            // اتصال رویداد کلیک به دکمه‌های جدید
            const btnGoToCart = document.getElementById('btn-go-to-cart');
            if (btnGoToCart) {
                btnGoToCart.addEventListener('click', function() {
                    saveInsuranceAndAction('cart');
                });
            }

            const btnContinueShopping = document.getElementById('btn-continue-shopping');
            if (btnContinueShopping) {
                btnContinueShopping.addEventListener('click', function() {
                    saveInsuranceAndAction('continue');
                });
            }
        }

        // (جدید) منطق مربوط به مودال جزئیات بیمه
        const insuranceModal = document.getElementById('insuranceDetailsModal');
        // (اصلاح) بررسی می‌کنیم که آیا مودال در صفحه فعلی وجود دارد یا خیر
        if (insuranceModal) { 
            insuranceModal.addEventListener('show.bs.modal', function (event) { 
                // دکمه‌ای که مودال را باز کرده است 
                const button = event.relatedTarget; 
 
                // استخراج اطلاعات از data-* attributes 
                const insuranceName = button.getAttribute('data-name'); 
                const insuranceDescription = button.getAttribute('data-description'); 
 
                // به‌روزرسانی محتوای مودال 
                const modalTitle = insuranceModal.querySelector('.modal-title'); 
                const modalBody = insuranceModal.querySelector('.modal-body'); 
 
                modalTitle.textContent = insuranceName; 
                modalBody.innerHTML = `<p>${insuranceDescription.replace(/\n/g, '<br>')}</p>`; 
            }); 
        } 

        // (جدید) منطق بررسی بارگذاری نمادهای اعتماد و نمایش تصویر جایگزین در صورت خطا
        function checkTrustSeals() {
            const sealSlugs = <?php echo $trust_seal_slugs_json; ?>;
            if (!sealSlugs || sealSlugs.length === 0) return;

            sealSlugs.forEach(slug => {
                const containerId = `trust-symbol-${slug}`;
                const container = document.getElementById(containerId);
                if (!container) return;

                // 2 ثانیه صبر می‌کنیم تا اسکریپت نماد فرصت بارگذاری داشته باشد
                setTimeout(() => {
                    // اگر بعد از 2 ثانیه، محتوای کانتینر خالی بود یا فقط شامل تگ script بود (یعنی تصویری رندر نشده)
                    // در اینجا با بررسی ارتفاع، متوجه می‌شویم که آیا محتوای قابل مشاهده‌ای رندر شده است یا خیر.
                    if (container.offsetHeight < 20) { 
                        const scriptTag = container.querySelector('script');
                        const fallbackImg = container.querySelector('img[data-fallback-img]');
                        
                        // اسکریپت را حذف کرده و تصویر جایگزین را نمایش می‌دهیم
                        if (scriptTag) scriptTag.remove();
                        if (fallbackImg) fallbackImg.style.display = 'block';
                    }
                }, 2000); // تاخیر 2 ثانیه‌ای
            });
        }

        // فراخوانی تابع بررسی نمادها
        checkTrustSeals();

    });
</script>

<!-- دکمه بازگشت به بالا -->
<style>
    #back-to-top {
        position: fixed;
        bottom: 30px;
        left: 30px;
        width: 50px;
        height: 50px;
        z-index: 1050; /* بالاتر از اکثر المان‌ها */
        display: none; /* توسط JS کنترل می‌شود */
        align-items: center;
        justify-content: center;
        opacity: 0.9;
        transition: all 0.3s ease;
    }
    #back-to-top:hover {
        opacity: 1;
        transform: translateY(-3px);
    }
    /* تنظیم موقعیت در موبایل برای جلوگیری از تداخل با نوار پایین */
    @media (max-width: 767.98px) {
        #back-to-top {
            bottom: 95px; /* فاصله بیشتر از پایین در موبایل */
            left: 20px;
            width: 45px;
            height: 45px;
        }
    }
</style>
<button id="back-to-top" class="btn btn-primary rounded-circle shadow-lg" aria-label="بازگشت به بالا">
    <i class="fa fa-arrow-up"></i>
</button>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const backToTopBtn = document.getElementById('back-to-top');
    if (backToTopBtn) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                backToTopBtn.style.display = 'flex';
            } else {
                backToTopBtn.style.display = 'none';
            }
        });
        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
});
</script>
</body>

</html>