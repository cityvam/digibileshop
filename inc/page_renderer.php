<?php
// فعال‌سازی نمایش خطاها برای رفع مشکل صفحه سفید
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once 'functions.php';
/**
 * تابع اصلی برای رندر کردن محتوای صفحه از یک آرایه
 * @param array $content_array آرایه محتوای صفحه
 */
function render_page_content($content_array) {
    global $conn; // دسترسی به اتصال دیتابیس

    // خواندن عرض کانتینر از جدول تنظیمات
    $container_max_width = '1320px'; // مقدار پیش‌فرض
    if (isset($conn)) {
        try {
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'container_max_width' LIMIT 1");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result && !empty($result['setting_value'])) {
                $container_max_width = htmlspecialchars($result['setting_value']);
            }
        } catch (Exception $e) { /* در صورت خطا از مقدار پیش‌فرض استفاده می‌شود */ }
    }

    if (!is_array($content_array)) return;
    
    // لاگ شروع رندر صفحه
    echo "<script>console.log('Page Renderer Started. Sections count: " . count($content_array) . "');</script>";

    foreach ($content_array as $sectionData) {
        if ($sectionData['type'] === 'section') {
            $settings = $sectionData['settings'];
            $bgColor = $settings['bgColor'] ?? 'transparent';
            $paddingTop = $settings['paddingTop'] ?? '0';
            $paddingEnd = $settings['paddingEnd'] ?? '0';
            $paddingBottom = $settings['paddingBottom'] ?? '0';
            $paddingStart = $settings['paddingStart'] ?? '0';
            $marginTop = $settings['marginTop'] ?? '0';
            $marginEnd = $settings['marginEnd'] ?? '0';
            $marginBottom = $settings['marginBottom'] ?? '0';
            $marginStart = $settings['marginStart'] ?? '0';
            $bgImage = $settings['bgImage'] ?? '';
            $bgSize = $settings['bgSize'] ?? 'cover';
            $bgPosition = $settings['bgPosition'] ?? 'center center';
            $bgAttachment = $settings['bgAttachment'] ?? 'scroll';
            $bgOverlay = $settings['bgOverlay'] ?? '';
            $marginTop = $settings['marginTop'] ?? '0';
            $marginBottom = $settings['marginBottom'] ?? '0';
            $marginStart = $settings['marginStart'] ?? '0';

            $gutter = $settings['gutter'] ?? '3'; // خواندن مقدار فاصله

            // پاک‌سازی کدهای تکراری و اصلاح استایل
            $style = "background-color: " . ($bgColor === '#ffffff' || $bgColor === '#000000' ? 'transparent' : htmlspecialchars($bgColor)) . "; position: relative;";
            if ($bgImage) {
                // مهم: از htmlspecialchars برای خود URL استفاده نکنید چون آن را خراب می‌کند.
                $style .= " background-image: url('" . $bgImage . "'); background-size: " . htmlspecialchars($bgSize) . "; background-position: " . htmlspecialchars($bgPosition) . "; background-attachment: " . htmlspecialchars($bgAttachment) . "; background-repeat: no-repeat;";
            }
            
            // --- Responsive Classes Logic ---
            $responsive_classes = [];
            $show_on_mobile = $settings['showOnMobile'] ?? 'true';
            $show_on_tablet = $settings['showOnTablet'] ?? 'true';
            $show_on_desktop = $settings['showOnDesktop'] ?? 'true';

            if ($show_on_mobile === 'false') $responsive_classes[] = 'd-none d-md-block'; // Hide on xs, show on md and up
            if ($show_on_tablet === 'false') $responsive_classes[] = 'd-md-none d-lg-block'; // Hide on md, show on others
            if ($show_on_desktop === 'false') $responsive_classes[] = 'd-lg-none'; // Hide on lg and up

            $responsive_class_string = implode(' ', array_unique($responsive_classes));

            // افزودن تمام کلاس‌های فاصله‌گذاری
            $spacing_classes = "pt-{$paddingTop} pe-{$paddingEnd} pb-{$paddingBottom} ps-{$paddingStart} mt-{$marginTop} me-{$marginEnd} mb-{$marginBottom} ms-{$marginStart}";

            echo "<section class='{$spacing_classes} {$responsive_class_string}' style=\"" . $style . "\">";
            // Add overlay if both image and overlay color are set

            if ($bgImage && $bgOverlay) {
                echo "<div style='position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-color: " . htmlspecialchars($bgOverlay) . "; z-index: 1;'></div>";
            }
            echo "<div class='container' style='position: relative; z-index: 2;max-width: {$container_max_width};'>"; // z-index to ensure content is above overlay
            
            // (اصلاح) محاسبه عرض دقیق ستون‌ها برای پشتیبانی از تعداد ستون‌های خاص (مثل 5 یا 7)
            $col_count = count($sectionData['columns']);
            $custom_row_class = '';
            $custom_col_class = '';
            if (in_array($col_count, [5, 7, 8, 9, 10, 11])) {
                $col_width = 100 / $col_count;
                $custom_row_class = 'custom-grid-' . uniqid();
                $custom_col_class = 'custom-col-item';
                echo "<style>@media (min-width: 768px) { .{$custom_row_class} > .{$custom_col_class} { flex: 0 0 auto; width: {$col_width}%; } }</style>";
            }

            echo "<div class='row g-{$gutter} {$custom_row_class}'>"; // استفاده از مقدار داینامیک برای فاصله

            foreach ($sectionData['columns'] as $columnData) {
                if ($columnData['type'] === 'column') {
                    $colSettings = $columnData['settings'];
                    $colSize = $columnData['size'] ?? 'col-md-12';
                    $colBgColor = $colSettings['bgColor'] ?? 'transparent';
                    $vAlign = $colSettings['vAlign'] ?? 'justify-content-start';
                    $padding = $colSettings['padding'] ?? '3'; // خواندن مقدار پدینگ
                    $colPaddingClass = "p-{$padding}";
                    $colStyle = "background-color: " . ($colBgColor === '#ffffff' || $colBgColor === '#000000' ? 'transparent' : htmlspecialchars($colBgColor)) . "; border-radius: 0.375rem;";

                    // افزودن استایل تصویر پس‌زمینه برای ستون
                    $colBgImage = $colSettings['bgImage'] ?? '';
                    if (!empty($colBgImage)) {
                        $colBgSize = htmlspecialchars($colSettings['bgSize'] ?? 'cover');
                        $colBgPosition = htmlspecialchars($colSettings['bgPosition'] ?? 'center center');
                        $colBgRepeat = htmlspecialchars($colSettings['bgRepeat'] ?? 'no-repeat');
                        $colStyle .= " background-image: url({$colBgImage}); background-size: {$colBgSize}; background-position: {$colBgPosition}; background-repeat: {$colBgRepeat};";
                    }

                    echo "<div class='" . htmlspecialchars($colSize) . " " . $custom_col_class . " d-flex flex-column " . htmlspecialchars($vAlign) . " " . $colPaddingClass . "' style='{$colStyle}'>";
                    foreach ($columnData['blocks'] as $blockData) {
                        render_block($blockData);
                    }
                    echo "</div>"; // end column
                }
            }

            echo "</div>"; // end row
            echo "</div>"; // end container
            echo "</section>"; // end section
        }
    }
}


/**
 * تابع برای رندر کردن یک بلوک محتوایی
 * @param array $blockData آرایه اطلاعات بلوک
 */
function render_block($blockData) {
    global $conn; // Access to the database connection
    $type = $blockData['type'];
    
    // لاگ نوع بلوک
    echo "<script>console.log('Rendering Block: " . htmlspecialchars($type) . "');</script>";
    
    $settings = $blockData['settings'];
    $align = $settings['align'] ?? 'text-end';
    $custom_class = isset($settings['customClass']) ? htmlspecialchars($settings['customClass']) : ''; // Existing custom class
    $inline_css = isset($settings['inlineCss']) ? htmlspecialchars($settings['inlineCss']) : '';

    // --- خواندن تنظیمات پس‌زمینه ---
    $bg_image = $settings['bgImage'] ?? '';
    $bg_style = '';
    if (!empty($bg_image)) {
        $bg_size = htmlspecialchars($settings['bgSize'] ?? 'cover');
        $bg_position = htmlspecialchars($settings['bgPosition'] ?? 'center center');
        $bg_repeat = htmlspecialchars($settings['bgRepeat'] ?? 'no-repeat');
        $bg_style = "background-image: url('{$bg_image}'); background-size: {$bg_size}; background-position: {$bg_position}; background-repeat: {$bg_repeat}; padding: 20px;"; // افزودن پدینگ پیش‌فرض
    }

    $final_style = $bg_style . $inline_css;

    // --- خواندن تنظیمات انیمیشن ---
    $animation_name = isset($settings['animationName']) ? htmlspecialchars($settings['animationName']) : '';
    $animation_duration = isset($settings['animationDuration']) ? htmlspecialchars($settings['animationDuration']) : '';
    $animation_delay = isset($settings['animationDelay']) ? htmlspecialchars($settings['animationDelay']) : '';

    $animation_classes = '';
    $animation_data_attrs = '';
    if (!empty($animation_name)) {
        $animation_classes = 'wow animate__animated animate__' . $animation_name;
        if (!empty($animation_duration)) $animation_data_attrs .= ' data-wow-duration="' . $animation_duration . '"';
        if (!empty($animation_delay)) $animation_data_attrs .= ' data-wow-delay="' . $animation_delay . '"';
    }

    // Wrapper for each block with margin and custom class
    echo "<div class='content-block w-100 mb-3 {$custom_class} {$animation_classes}' style='{$final_style}' {$animation_data_attrs}>";

    switch ($type) {
        case 'heading':
            $level = htmlspecialchars($settings['level'] ?? 'h2');
            $text = htmlspecialchars($settings['text'] ?? '');
            $link = htmlspecialchars($settings['link'] ?? '');
            $target = htmlspecialchars($settings['target'] ?? '_self');
            $color = htmlspecialchars($settings['color'] ?? '#212529');
            $bg_color = htmlspecialchars($settings['bgColor'] ?? 'transparent');
            $style = "color: {$color}; background-color: {$bg_color};";
            if ($bg_color !== 'transparent') {
                $style .= " padding: 0.5rem; border-radius: 0.25rem; display: inline-block;";
            }
            if ($link) {
                $text = "<a href='{$link}' target='{$target}' style='color: inherit; text-decoration: none;'>{$text}</a>";
            }
            echo "<div class='{$align}'><{$level} style='{$style}'>{$text}</{$level}></div>";
            break;

        case 'text':
            // (تغییر) رمزگشایی صحیح محتوایی که با btoa(unescape(encodeURIComponent(...))) در جاوااسکریپت انکود شده
            $content = isset($settings['content']) ? js_b64_decode($settings['content']) : '';
            echo "<div>{$content}</div>"; // No alignment class, alignment is handled by TinyMCE/Editor
            break;

        case 'image':
            $src = $settings['src'] ?? '';
            $alt = htmlspecialchars($settings['alt'] ?? '');
            $link = htmlspecialchars($settings['link'] ?? '');
            $target = htmlspecialchars($settings['target'] ?? '_self');
            if ($src) {
                // (اصلاح) اگر آدرس تصویر با آدرس لوکال هاست ذخیره شده بود، آن را با آدرس پایه صحیح جایگزین کن
                $local_base_url = 'http://localhost/Pro1/';
                if (strpos($src, $local_base_url) === 0) {
                    $relative_path = substr($src, strlen($local_base_url));
                    $src = IMAGE_BASE_URL . $relative_path;
                }
                // برای امنیت، آدرس نهایی را htmlspecialchars می‌کنیم
                $src = htmlspecialchars($src);
                $img_html = "<img src='{$src}' alt='{$alt}' class='img-fluid rounded'>";
                if ($link) {
                    $img_html = "<a href='{$link}' target='{$target}'>{$img_html}</a>";
                }
                echo "<div class='{$align}'>{$img_html}</div>";
            }
            break;

        case 'trust_badges':
            $badges = isset($settings['badges']) ? json_decode(js_b64_decode($settings['badges']), true) : [];
            $columns = (int)($settings['columns'] ?? 4);

            if (!empty($badges) && is_array($badges)) {
                // (اصلاح) محاسبه عرض دقیق ستون‌ها برای پشتیبانی از تعداد ستون‌های دلخواه (مثل 5 یا 7)
                $col_width = 100 / $columns;
                $unique_class = 'trust-badges-' . uniqid();
                echo "<style>@media (min-width: 768px) { .{$unique_class} { flex: 0 0 auto; width: {$col_width}%; } }</style>";
                $bs_col_class = 'col-6 ' . $unique_class;

                echo "<div class='trust-badges-block p-4 border border-2 border-dashed rounded-3'>";
                echo "  <div class='row g-4'>";
                foreach ($badges as $badge) {
                    $icon = htmlspecialchars($badge['icon'] ?? 'bi-patch-check');
                    $image = htmlspecialchars($badge['image'] ?? '');
                    $title = htmlspecialchars($badge['title'] ?? '');
                    $subtitle = htmlspecialchars($badge['subtitle'] ?? '');

                    echo "<div class='{$bs_col_class}'>";
                    echo "  <div class='d-flex align-items-center'>";
                    if (!empty($image)) {
                        echo "      <img src='{$image}' alt='{$title}' class='trust-badge-image me-3'>";
                    } else {
                        echo "      <i class='{$icon} text-secondary me-3' style='font-size: 2.5rem;'></i>";
                    }
                    echo "      <div><strong class='d-block'>{$title}</strong><small class='text-muted'>{$subtitle}</small></div>";
                    echo "  </div>";
                    echo "</div>";
                }
                echo "  </div>";
                echo "</div>";
            }
            break;
        case 'button':
            $text = htmlspecialchars($settings['text'] ?? 'کلیک کنید');
            $link = htmlspecialchars($settings['link'] ?? '#');
            $style = htmlspecialchars($settings['style'] ?? 'btn-primary');
            $target = htmlspecialchars($settings['target'] ?? '_self');
            echo "<div class='{$align}'><a href='{$link}' target='{$target}' class='btn {$style}'>{$text}</a></div>";
            break;

        case 'divider':
            $style = htmlspecialchars($settings['style'] ?? 'solid');
            $color = htmlspecialchars($settings['color'] ?? 'border-secondary');
            $height = htmlspecialchars($settings['height'] ?? '1');
            $margin = htmlspecialchars($settings['margin'] ?? '3');
            echo "<div class='my-{$margin} border-top {$color}' style='border-top-style: {$style} !important; border-top-width: {$height}px !important;'></div>";
            break;

        case 'spacer':
            $height = htmlspecialchars($settings['height'] ?? '50');
            echo "<div style='height: {$height}px;'></div>";
            break;

        case 'custom_html':
            $html_content = isset($settings['htmlContent']) ? js_b64_decode($settings['htmlContent']) : '';
            echo $html_content; // Output raw HTML
            break;

        case 'video':
            $src = $settings['src'] ?? '';
            $embed_url = '';
            if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $src, $youtube_match)) {
                $embed_url = 'https://www.youtube.com/embed/' . $youtube_match[1];
            } elseif (preg_match('/aparat\.com\/v\/([a-zA-Z0-9]+)/', $src, $aparat_match)) {
                $embed_url = 'https://www.aparat.com/video/video/embed/videohash/' . $aparat_match[1] . '/vt/frame';
            }
            if ($embed_url) {
                echo "<div class='ratio ratio-16x9 rounded overflow-hidden'><iframe src='{$embed_url}' title='Video player' frameborder='0' allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share' allowfullscreen></iframe></div>";
            }
            else {
                echo "<div class='alert alert-warning'>آدرس ویدیو نامعتبر است یا پشتیبانی نمی‌شود.</div>";
            }
            break;

        case 'form':
            echo render_form_block($settings, $conn);
            break;

        case 'google_map':
            $iframe_content = isset($settings['iframeContent']) ? js_b64_decode($settings['iframeContent']) : '';
            if ($iframe_content) {
                // Add ratio class for responsiveness
                echo "<div class='ratio ratio-16x9'>{$iframe_content}</div>";
            }
            break;

        case 'full_width_banner':
            $image_url = htmlspecialchars($settings['imageUrl'] ?? '');
            $title = htmlspecialchars($settings['title'] ?? '');
            $subtitle = htmlspecialchars($settings['subtitle'] ?? '');
            $button_text = htmlspecialchars($settings['buttonText'] ?? '');
            $button_link = htmlspecialchars($settings['buttonLink'] ?? '#');
            $overlay_color = htmlspecialchars($settings['overlayColor'] ?? 'rgba(0, 0, 0, 0.5)');
            $min_height = htmlspecialchars($settings['minHeight'] ?? '400px');
            $text_color = htmlspecialchars($settings['textColor'] ?? '#FFFFFF');
            $button_style_class = htmlspecialchars($settings['buttonStyle'] ?? 'btn-primary');

            $banner_style = "position: relative; background-image: url('{$image_url}'); background-size: cover; background-position: center; min-height: {$min_height}; display: flex; align-items: center; justify-content: center; text-align: center; padding: 2rem; border-radius: 0.375rem; overflow: hidden;";
            echo "<div class='full-width-banner-block' style='{$banner_style}'>";
            echo "  <div style='position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-color: {$overlay_color}; z-index: 1;'></div>";
            echo "  <div style='position: relative; z-index: 2; color: {$text_color};'>";
            if ($title) echo "<h1 class='display-4 fw-bold'>{$title}</h1>";
            if ($subtitle) echo "<p class='lead'>{$subtitle}</p>";
            if ($button_text) echo "<a href='{$button_link}' class='btn {$button_style_class} btn-lg mt-3'>{$button_text}</a>";
            echo "  </div>";
            echo "</div>";
            break;

        case 'accordion':
            $items = isset($settings['items']) ? json_decode(js_b64_decode($settings['items']), true) : [];
            if (is_array($items) && !empty($items)) {
                $accordion_id = 'accordion-' . uniqid();
                echo "<div class='accordion' id='{$accordion_id}'>";
                foreach ($items as $index => $item) {
                    $item_id = 'item-' . $accordion_id . '-' . $index;
                    $is_open = $item['open'] ?? false;
                    $title = htmlspecialchars($item['title'] ?? '');
                    $content = nl2br(htmlspecialchars($item['content'] ?? ''));

                    echo "<div class='accordion-item'>
                            <h2 class='accordion-header' id='heading-{$item_id}'>
                                <button class='accordion-button" . ($is_open ? "" : " collapsed") . "' type='button' data-bs-toggle='collapse' data-bs-target='#collapse-{$item_id}' aria-expanded='" . ($is_open ? "true" : "false") . "' aria-controls='collapse-{$item_id}'>{$title}</button>
                            </h2>
                            <div id='collapse-{$item_id}' class='accordion-collapse collapse" . ($is_open ? " show" : "") . "' aria-labelledby='heading-{$item_id}' data-bs-parent='#{$accordion_id}'>
                                <div class='accordion-body'>{$content}</div>
                            </div>
                          </div>";
                }
                echo "</div>";
            }
            break;

        case 'pricing_table':
            $title = htmlspecialchars($settings['title'] ?? 'پلن');
            $price = htmlspecialchars($settings['price'] ?? '0');
            $currency = htmlspecialchars($settings['currency'] ?? 'تومان');
            $period = htmlspecialchars($settings['period'] ?? '/ ماهانه');
            $button_text = htmlspecialchars($settings['buttonText'] ?? 'انتخاب');
            $button_link = htmlspecialchars($settings['buttonLink'] ?? '#');
            $is_featured = ($settings['featured'] ?? 'false') === 'true';
            $features = isset($settings['features']) ? json_decode(js_b64_decode($settings['features']), true) : [];

            $card_class = $is_featured ? 'border-primary border-2 shadow' : 'shadow-sm';
            $header_class = $is_featured ? 'bg-primary text-white' : '';
            $button_class = $is_featured ? 'btn-primary' : 'btn-outline-primary';

            echo "<div class='card h-100 text-center {$card_class}'>";
            echo "  <div class='card-header {$header_class} py-3'>";
            echo "      <h4 class='my-0 fw-normal'>{$title}</h4>";
            echo "  </div>";
            echo "  <div class='card-body d-flex flex-column'>";
            echo "      <h1 class='card-title pricing-card-title'>{$price} <small class='text-muted fw-light'>{$currency}</small></h1>";
            echo "      <p class='text-muted'>{$period}</p>";
            echo "      <ul class='list-unstyled mt-3 mb-4'>";
            if (is_array($features)) {
                foreach ($features as $feature) {
                    echo "<li>" . htmlspecialchars($feature) . "</li>";
                }
            }
            echo "      </ul>";
            echo "      <a href='{$button_link}' class='btn btn-lg {$button_class} mt-auto'>{$button_text}</a>";
            echo "  </div>";
            echo "</div>";
            break;

        case 'gallery':
            $images = isset($settings['images']) ? json_decode(js_b64_decode($settings['images']), true) : [];
            $columns = htmlspecialchars($settings['columns'] ?? '4');
            if (!empty($images) && is_array($images)) {
                $gallery_id = 'gallery-' . uniqid();
                // (اصلاح) استفاده از استایل برای ستون‌بندی دقیق در گالری
                $col_width = 100 / (int)$columns;
                echo "<style>@media (min-width: 768px) { #{$gallery_id} > .col { flex: 0 0 auto; width: {$col_width}%; } }</style>";
                $bs_col_class = 'row-cols-1 row-cols-sm-2';
                echo "<div class='row g-3 {$bs_col_class}' id='{$gallery_id}'>";
                foreach ($images as $img) {
                    $src = htmlspecialchars($img['src'] ?? '');
                    // Note: glightbox is not included in header.php. You need to include it for this to work.
                    echo "<div class='col'><a href='{$src}' class='glightbox' data-gallery='{$gallery_id}'><img src='{$src}' class='img-fluid rounded w-100' style='aspect-ratio: 1/1; object-fit: cover;'></a></div>";
                }
                echo "</div>";
            }
            break;

        case 'counter':
            $end_number = htmlspecialchars($settings['endNumber'] ?? '1000');
            $duration = htmlspecialchars($settings['duration'] ?? '2000');
            $prefix = htmlspecialchars($settings['prefix'] ?? '');
            $suffix = htmlspecialchars($settings['suffix'] ?? '');
            $title = htmlspecialchars($settings['title'] ?? '');
            $align = $settings['align'] ?? 'text-center'; // Override default align for counter

            echo "<div class='animated-counter {$align}' 
                         data-target='{$end_number}' 
                         data-duration='{$duration}' 
                         data-prefix='{$prefix}' 
                         data-suffix='{$suffix}'>
                      <h2 class='counter-number fw-bold display-4'>{$prefix}0{$suffix}</h2>
                      <p class='counter-title text-muted mb-0'>{$title}</p>
                  </div>";
            // You will need to add JavaScript to make the counter animate (e.g., using a library like Waypoints or custom JS).
            break;



        default:
            // (جدید) دیباگ برای بلوک‌های ناشناخته در کنسول مرورگر
            $block_type_json = json_encode($type);
            echo "<script>console.warn('Unknown or unhandled block type encountered:', {$block_type_json});</script>";
            break;


        case 'banner_slider':
            $slides = isset($settings['slides']) ? json_decode(js_b64_decode($settings['slides']), true) : [];
            $height = htmlspecialchars($settings['height'] ?? '500');
            $slider_id = 'banner-slider-' . uniqid();

            if (!empty($slides) && is_array($slides)) {
                // Note: Swiper.js is not included in header.php. You need to include it for this to work.
                echo "<div id='{$slider_id}' class='swiper banner-slider' style='height: {$height}px;'>";
                echo "  <div class='swiper-wrapper'>";

                foreach ($slides as $slide) {
                    $image = htmlspecialchars($slide['image'] ?? '');
                    $title = htmlspecialchars($slide['title'] ?? '');
                    $subtitle = htmlspecialchars($slide['subtitle'] ?? '');
                    $button_text = htmlspecialchars($slide['button_text'] ?? '');
                    $button_link = htmlspecialchars($slide['button_link'] ?? '#');

                    echo "<div class='swiper-slide' style='background-image: url({$image});'>";
                    echo "  <div class='slide-overlay'></div>";
                    echo "  <div class='slide-content container text-center'>";
                    if ($title) echo "<h1 class='slide-title'>{$title}</h1>";
                    if ($subtitle) echo "<p class='slide-subtitle'>{$subtitle}</p>";
                    if ($button_text && $button_link) {
                        echo "  <a href='{$button_link}' class='btn btn-primary btn-lg slide-button'>{$button_text}</a>";
                    }
                    echo "  </div>";
                    echo "</div>";
                }

                echo "  </div>"; // swiper-wrapper
                // Add pagination
                echo "  <div class='swiper-pagination'></div>";
                echo "</div>"; // swiper container

                // Add the script to initialize this specific slider
                echo "<script>
                        document.addEventListener('DOMContentLoaded', function () {
                            if (typeof Swiper === 'function') {
                                new Swiper('#{$slider_id}', { 
                                    loop: true, autoplay: { delay: 5000 }, 
                                    pagination: { el: '.swiper-pagination', clickable: true } });
                            }
                        });
                      </script>";
            }
            break;

        case 'banner':
            $position = htmlspecialchars($settings['position'] ?? 'top_banner');

            try {
                $stmt = $conn->prepare("SELECT image_desktop, image_mobile, link_url, alt_text FROM site_banners WHERE position = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY sort_order ASC LIMIT 1");
                $stmt->bind_param("s", $position);
                $stmt->execute();
                $banner = $stmt->get_result()->fetch_assoc();

                if ($banner) {
                    $image_url = (!empty($banner['image_desktop'])) ? IMAGE_BASE_URL . 'banners/' . htmlspecialchars($banner['image_desktop']) : 'https://placehold.co/600x100?text=Banner';
                    $link_url = htmlspecialchars($banner['link_url'] ?? '#');
                    $alt_text = htmlspecialchars($banner['alt_text'] ?? 'Banner Image');

                    echo "<a href='{$link_url}'><img src='{$image_url}' alt='{$alt_text}' class='img-fluid rounded'></a>";
                } else {
                    echo "<div class='alert alert-info'>هیچ بنری برای موقعیت {$position} یافت نشد.</div>";
                }
            } catch (Exception $e) {
                echo "<div class='alert alert-warning'>خطا در واکشی بنر.</div>";
            }
            break;


        case 'tabs':
            $items = isset($settings['items']) ? json_decode(js_b64_decode($settings['items']), true) : [];
            $style = htmlspecialchars($settings['style'] ?? 'nav-tabs');
            $fade = ($settings['fade'] ?? 'true') === 'true' ? 'fade' : '';

            if (!empty($items) && is_array($items)) {
                $tabs_id = 'tabs-' . uniqid();
                // Nav tabs
                echo "<ul class='nav {$style}' id='{$tabs_id}' role='tablist'>";
                foreach ($items as $index => $item) {
                    $tab_id = "{$tabs_id}-tab-{$index}";
                    $pane_id = "{$tabs_id}-pane-{$index}";
                    $is_active = $item['active'] ?? ($index === 0);
                    $title = htmlspecialchars($item['title'] ?? '');

                    echo "<li class='nav-item' role='presentation'>
                            <button class='nav-link" . ($is_active ? " active" : "") . "' id='{$tab_id}' data-bs-toggle='tab' data-bs-target='#{$pane_id}' type='button' role='tab' aria-controls='{$pane_id}' aria-selected='" . ($is_active ? "true" : "false") . "'>{$title}</button>
                          </li>";
                }
                echo "</ul>";

                // Tab panes
                echo "<div class='tab-content' id='{$tabs_id}-content'>";
                foreach ($items as $index => $item) {
                    $pane_id = "{$tabs_id}-pane-{$index}";
                    $tab_id = "{$tabs_id}-tab-{$index}";
                    $is_active = $item['active'] ?? ($index === 0);
                    $content = nl2br(htmlspecialchars($item['content'] ?? ''));

                    echo "<div class='tab-pane {$fade}" . ($is_active ? " show active" : "") . "' id='{$pane_id}' role='tabpanel' aria-labelledby='{$tab_id}'>
                            <div class='p-3 border border-top-0 rounded-bottom'>{$content}</div>
                          </div>";
                }
                echo "</div>";
            }
            break;

        case 'testimonials_slider':
            $testimonials = isset($settings['testimonials']) ? json_decode(js_b64_decode($settings['testimonials']), true) : [];
            $autoplay = ($settings['autoplay'] ?? 'true') === 'true';
            $loop = ($settings['loop'] ?? 'true') === 'true';
            $slides_per_view = htmlspecialchars($settings['slidesPerView'] ?? '1');
            $slider_id = 'testimonials-slider-' . uniqid();

            if (!empty($testimonials) && is_array($testimonials)) {
                // Note: Swiper.js is not included in header.php. You need to include it for this to work.
                echo "<div id='{$slider_id}' class='swiper testimonials-slider'>";
                echo "  <div class='swiper-wrapper'>";

                foreach ($testimonials as $testimonial) {
                    $image = htmlspecialchars($testimonial['image'] ?? '');
                    $name = htmlspecialchars($testimonial['name'] ?? '');
                    $title = htmlspecialchars($testimonial['title'] ?? '');
                    $testimonial_text = nl2br(htmlspecialchars($testimonial['testimonial'] ?? ''));

                    echo "<div class='swiper-slide text-center p-4'>";
                    echo "  <div class='testimonial-card'>";
                    if ($image) {
                        echo "  <img src='{$image}' alt='{$name}' class='testimonial-avatar rounded-circle mb-3'>";
                    }
                    echo "  <p class='testimonial-text'>{$testimonial_text}</p>";
                    echo "  <h5 class='testimonial-name mt-3 mb-0'>{$name}</h5>";
                    if ($title) {
                        echo "  <p class='testimonial-title text-muted'>{$title}</p>";
                    }
                    echo "  </div>";
                    echo "</div>";
                }

                echo "  </div>"; // swiper-wrapper
                // Add pagination
                echo "  <div class='swiper-pagination'></div>";
                echo "</div>"; // swiper container

                // Add the script to initialize this specific slider
                $autoplay_option = $autoplay ? "{ delay: 5000, disableOnInteraction: false }" : "false";
                $loop_option = $loop ? "true" : "false";
                echo "<script>
                        document.addEventListener('DOMContentLoaded', function () {
                            if (typeof Swiper === 'function') {
                                new Swiper('#{$slider_id}', {
                                    loop: {$loop_option},
                                    autoplay: {$autoplay_option},
                                    slidesPerView: {$slides_per_view},
                                    spaceBetween: 30,
                                    pagination: { el: '.swiper-pagination', clickable: true }
                                });
                            }
                        });
                      </script>";
            }
            break;

        case 'icon_box':
            $icon = htmlspecialchars($settings['icon'] ?? 'bi-gem'); // Note: Uses Bootstrap Icons, not Font Awesome
            $title = htmlspecialchars($settings['title'] ?? '');
            $description = nl2br(htmlspecialchars($settings['description'] ?? ''));
            $link = htmlspecialchars($settings['link'] ?? '');
            $layout = htmlspecialchars($settings['layout'] ?? 'top');
            $icon_size = htmlspecialchars($settings['iconSize'] ?? '2.5rem');
            $icon_color = htmlspecialchars($settings['iconColor'] ?? '#FFFFFF');
            $icon_bg_color = htmlspecialchars($settings['iconBgColor'] ?? '#0d6efd');
            $align = htmlspecialchars($settings['align'] ?? 'text-center');

            $icon_style = "font-size: {$icon_size}; color: {$icon_color}; background-color: {$icon_bg_color}; width: calc({$icon_size} * 2); height: calc({$icon_size} * 2);";
            $wrapper_class = "feature-box p-3 rounded h-100";
            $content_wrapper_class = "feature-box-content";
            $icon_html = "<div class='feature-box-icon' style='{$icon_style}'><i class='{$icon}'></i></div>";
            $content_html = "<div class='{$content_wrapper_class}'><h5 class='mt-3 mb-2 feature-box-title'>{$title}</h5><p class='text-muted'>{$description}</p></div>";

            if ($layout === 'left') {
                $wrapper_class .= " feature-box-layout-left {$align}";
                $final_html = $icon_html . $content_html;
            } else { // top layout
                $wrapper_class .= " {$align}";
                $final_html = $icon_html . $content_html;
            }

            if ($link) {
                echo "<a href='{$link}' class='{$wrapper_class}'>";
                echo $final_html;
                echo "</a>";
            } else {
                echo "<div class='{$wrapper_class}'>";
                echo $final_html;
                echo "</div>";
            }
            break;

        case 'posts_grid':
            $count = (int)($settings['count'] ?? 3);
            $columns = (int)($settings['columns'] ?? 3);
            $show_image = ($settings['showImage'] ?? 'true') === 'true';
            $show_excerpt = ($settings['showExcerpt'] ?? 'true') === 'true';
            $show_date = ($settings['showDate'] ?? 'true') === 'true';

            // (اصلاح) محاسبه عرض ستون‌ها برای شبکه پست‌ها
            $col_width = 100 / $columns;
            $unique_class = 'posts-grid-' . uniqid();
            echo "<style>@media (min-width: 768px) { .{$unique_class} { flex: 0 0 auto; width: {$col_width}%; } }</style>";
            $bs_col_class = $unique_class;

            try {
                // فرض بر این است که جدولی به نام `articles` با ستون‌های id, title, slug, image, content, created_at وجود دارد.
                // تبدیل از PDO به MySQLi
                $stmt = $conn->prepare("SELECT id, title, slug, image, content, created_at FROM articles WHERE status = 'published' ORDER BY created_at DESC LIMIT ?");
                $stmt->bind_param("i", $count);
                $stmt->execute();
                $posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                if ($posts) {
                    echo "<div class='row g-4'>";
                    foreach ($posts as $post) {
                        echo "  <div class='{$bs_col_class}'>";
                        echo "      <div class='card h-100 post-card'>";
                        if ($show_image && !empty($post['image'])) {
                            echo "          <a href='" . BASE_URL . "article/{$post['slug']}'><img src='" . IMAGE_BASE_URL . "upload/posts/" . htmlspecialchars($post['image']) . "' class='card-img-top post-card-img' alt='" . htmlspecialchars($post['title']) . "'></a>";
                        }
                        echo "          <div class='card-body'>";
                        echo "              <h5 class='card-title'><a href='" . BASE_URL . "article/{$post['slug']}' class='text-decoration-none text-dark'>".htmlspecialchars($post['title'])."</a></h5>";
                        // تولید خلاصه متن از محتوا چون ستون excerpt وجود ندارد
                        $excerpt = !empty($post['content']) ? mb_substr(strip_tags($post['content']), 0, 120, 'UTF-8') . '...' : '';
                        if ($show_excerpt && !empty($excerpt)) {
                            echo "              <p class='card-text text-muted small'>" . htmlspecialchars($excerpt) . "</p>";
                        }
                        echo "          </div>";
                        if ($show_date && function_exists('to_jalali_date')) {
                            echo "          <div class='card-footer bg-transparent border-top-0'><small class='text-muted'>" . to_jalali_date($post['created_at'], 'd F Y') . "</small></div>";
                        }
                        echo "      </div>";
                        echo "  </div>";
                    }
                    echo "</div>";
                } else {
                    echo "<div class='alert alert-secondary'>پستی برای نمایش یافت نشد.</div>";
                }
            } catch (Exception $e) {
                // اگر جدول posts وجود نداشته باشد، این پیام نمایش داده می‌شود.
                echo "<div class='alert alert-warning'>بلوک شبکه پست‌ها به درستی پیکربندی نشده است (خطا در واکشی اطلاعات).</div>";
            }
            break;

        case 'team_members':
            $members = isset($settings['members']) ? json_decode(js_b64_decode($settings['members']), true) : [];
            $columns = (int)($settings['columns'] ?? 3);
            
            // (اصلاح) محاسبه عرض ستون‌ها برای اعضای تیم
            $col_width = 100 / $columns;
            $unique_class = 'team-grid-' . uniqid();
            echo "<style>@media (min-width: 992px) { .{$unique_class} { flex: 0 0 auto; width: {$col_width}%; } }</style>";
            $bs_col_class = 'col-md-6 ' . $unique_class;

            if (!empty($members) && is_array($members)) {
                // Note: Uses Bootstrap Icons (bi-linkedin, bi-twitter, bi-globe), not Font Awesome.
                echo "<div class='row g-4 justify-content-center'>";
                foreach ($members as $member) {
                    $image = htmlspecialchars($member['image'] ?? 'https://placehold.co/200x200/EFEFEF/AAAAAA?text=Avatar');
                    $name = htmlspecialchars($member['name'] ?? '');
                    $title = htmlspecialchars($member['title'] ?? '');
                    $bio = htmlspecialchars($member['bio'] ?? '');

                    echo "<div class='{$bs_col_class}'>";
                    echo "  <div class='card text-center border-0 shadow-sm team-member-card h-100'>";
                    echo "      <div class='card-body'>";
                    echo "          <img src='{$image}' alt='{$name}' class='rounded-circle team-member-img mb-3'>";
                    echo "          <h5 class='card-title mb-1'>{$name}</h5>";
                    echo "          <p class='text-primary mb-2'>{$title}</p>";
                    echo "          <p class='card-text text-muted small'>{$bio}</p>";
                    echo "          <ul class='team-member-socials mt-3'>";
                    if (!empty($member['social_linkedin'])) echo "<li><a href='" . htmlspecialchars($member['social_linkedin']) . "' target='_blank'><i class='bi bi-linkedin'></i></a></li>";
                    if (!empty($member['social_twitter'])) echo "<li><a href='" . htmlspecialchars($member['social_twitter']) . "' target='_blank'><i class='bi bi-twitter'></i></a></li>";
                    if (!empty($member['social_website'])) echo "<li><a href='" . htmlspecialchars($member['social_website']) . "' target='_blank'><i class='bi bi-globe'></i></a></li>";
                    echo "          </ul>";
                    echo "      </div>";
                    echo "  </div>";
                    echo "</div>";
                }
                echo "</div>";
            }
            break;

        case 'data_table':
            $data = isset($settings['tableData']) ? json_decode(js_b64_decode($settings['tableData']), true) : null;
            if ($data && !empty($data['headers'])) {
                $options = $data['options'] ?? [];
                $table_classes = 'table';
                if ($options['striped'] ?? false) $table_classes .= ' table-striped';
                if ($options['hover'] ?? false) $table_classes .= ' table-hover';
                if ($options['bordered'] ?? false) $table_classes .= ' table-bordered';

                echo "<div class='table-responsive'>";
                echo "  <table class='{$table_classes}'>";
                echo "      <thead>";
                echo "          <tr>";
                foreach ($data['headers'] as $header) {
                    echo "          <th>" . htmlspecialchars($header) . "</th>";
                }
                echo "          </tr>";
                echo "      </thead>";
                echo "      <tbody>";
                foreach ($data['rows'] as $row) {
                    echo "      <tr>";
                    foreach ($row as $cell) { echo "<td>" . htmlspecialchars($cell) . "</td>"; }
                    echo "      </tr>";
                }
                echo "      </tbody>";
                echo "  </table>";
                echo "</div>";
            }
            break;

        case 'progress_bar':
            $label = htmlspecialchars($settings['label'] ?? '');
            $percentage = htmlspecialchars($settings['percentage'] ?? '0');
            $color = htmlspecialchars($settings['color'] ?? 'bg-primary');
            $striped = ($settings['striped'] ?? 'false') === 'true' ? 'progress-bar-striped' : '';
            $animated = ($settings['animated'] ?? 'false') === 'true' ? 'progress-bar-animated' : '';
            echo "<div>";
            if ($label) echo "<label class='form-label fw-bold'>{$label}</label>";
            echo "  <div class='progress' style='height: 20px;'>";
            echo "      <div class='progress-bar {$color} {$striped} {$animated}' role='progressbar' style='width: {$percentage}%' aria-valuenow='{$percentage}' aria-valuemin='0' aria-valuemax='100'>{$percentage}%</div>";
            echo "  </div>";
            echo "</div>";
            break;

        case 'star_rating':
            $rating = (float)($settings['rating'] ?? 0);
            $max_rating = (int)($settings['maxRating'] ?? 5);
            $color = htmlspecialchars($settings['color'] ?? '#FFC107');
            // Note: Uses Bootstrap Icons (bi-star-fill, bi-star-half, bi-star), not Font Awesome.
            echo "<div class='star-rating-block {$align}'>";
            for ($i = 1; $i <= $max_rating; $i++) {
                if ($i <= $rating) {
                    echo "<i class='bi bi-star-fill' style='color: {$color};'></i>";
                } elseif ($i - $rating < 1) {
                    echo "<i class='bi bi-star-half' style='color: {$color};'></i>"; // Note: bi-star-half might not be in all Bootstrap Icons versions
                } else {
                    echo "<i class='bi bi-star' style='color: {$color};'></i>";
                }
            }
            echo " <span class='ms-2 text-muted small'>(" . to_persian_num($rating) . " از " . to_persian_num($max_rating) . ")</span></div>";
            break;

        case 'alert':
            $content = htmlspecialchars($settings['content'] ?? '');
            $type = htmlspecialchars($settings['type'] ?? 'alert-info');
            $dismissible = ($settings['dismissible'] ?? 'false') === 'true' ? 'alert-dismissible' : '';
            echo "<div class='alert {$type} {$dismissible} fade show' role='alert'>";
            echo $content;
            if ($dismissible) echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo "</div>";
            break;

        case 'blockquote':
            $text = htmlspecialchars($settings['text'] ?? '');
            $author = htmlspecialchars($settings['author'] ?? '');
            echo "<figure><blockquote class='blockquote'><p>{$text}</p></blockquote>";
            if ($author) echo "<figcaption class='blockquote-footer'>{$author}</figcaption>";
            echo "</figure>";
            break;

        case 'logo_slider':
            $logos = isset($settings['logos']) ? json_decode(js_b64_decode($settings['logos']), true) : [];
            $slides_per_view = htmlspecialchars($settings['slidesPerView'] ?? '5');
            $autoplay = ($settings['autoplay'] ?? 'true') === 'true';
            $loop = ($settings['loop'] ?? 'true') === 'true';
            $slider_id = 'logo-slider-' . uniqid();

            if (!empty($logos) && is_array($logos)) {
                // Note: Swiper.js is not included in header.php. You need to include it for this to work.
                echo "<div id='{$slider_id}' class='swiper logo-slider'>";
                echo "  <div class='swiper-wrapper'>";
                foreach ($logos as $logo) {
                    $src = htmlspecialchars($logo['src'] ?? '');
                    $link = htmlspecialchars($logo['link'] ?? '');
                    $img_html = "<img src='{$src}' alt='Client Logo'>";
                    if ($link && $link !== '#') {
                        echo "<div class='swiper-slide'><a href='{$link}' target='_blank' rel='noopener noreferrer'>{$img_html}</a></div>";
                    } else {
                        echo "<div class='swiper-slide'>{$img_html}</div>";
                    }
                }
                echo "  </div>"; // swiper-wrapper
                echo "</div>"; // swiper container

                $autoplay_option = $autoplay ? "{ delay: 2500, disableOnInteraction: false }" : "false";
                $loop_option = $loop ? "true" : "false";
                echo "<script>
                        document.addEventListener('DOMContentLoaded', function () {
                            if (typeof Swiper === 'function') {
                                new Swiper('#{$slider_id}', {
                                    loop: {$loop_option},
                                    autoplay: {$autoplay_option},
                                    slidesPerView: 'auto',
                                    spaceBetween: 30,
                                    breakpoints: { 320: { slidesPerView: 2 }, 768: { slidesPerView: 3 }, 992: { slidesPerView: {$slides_per_view} } }
                                });
                            }
                        });
                      </script>";
            }
            break;

        case 'category_grid':
            $categories = isset($settings['categories']) ? json_decode(js_b64_decode($settings['categories']), true) : [];
            $columns = (int)($settings['columns'] ?? 4);
            $layout = $settings['layout'] ?? 'side';
            $hover_effect = $settings['hoverEffect'] ?? 'lift';

            if (!empty($categories) && is_array($categories)) {
                // (اصلاح) محاسبه عرض ستون‌ها برای شبکه دسته‌بندی‌ها
                $col_width = 100 / $columns;
                $unique_class = 'cat-grid-' . uniqid();
                echo "<style>@media (min-width: 992px) { .{$unique_class} { flex: 0 0 auto; width: {$col_width}%; } }</style>";
                $bs_col_class = 'col-6 col-md-4 ' . $unique_class;
                $item_class = 'category-grid-item category-grid-hover-' . htmlspecialchars($hover_effect);

                echo "<div class='row g-3 category-grid-block'>";
                foreach ($categories as $category) {
                    $image = htmlspecialchars($category['image'] ?? '');
                    $title = htmlspecialchars($category['title'] ?? '');
                    $link = htmlspecialchars($category['link'] ?? '#');
                    $item_content = '';

                    if ($layout === 'top') {
                        $item_content = "<img src='{$image}' alt='{$title}' class='category-grid-image-top mb-3'><div><h6 class='category-grid-title mb-1'>{$title}</h6><span class='category-grid-link'>shop now &raquo;</span></div>";
                        echo "<div class='{$bs_col_class}'><a href='{$link}' class='{$item_class} text-center p-3 text-decoration-none text-dark border rounded h-100'>{$item_content}</a></div>";
                    } else { // 'side' layout
                        $item_content = "<img src='{$image}' alt='{$title}' class='category-grid-image me-3'><div><h6 class='category-grid-title mb-1'>{$title}</h6><span class='category-grid-link'>shop now &raquo;</span></div>";
                        echo "<div class='{$bs_col_class}'><a href='{$link}' class='{$item_class} d-flex align-items-center p-3 text-decoration-none text-dark border rounded h-100'>{$item_content}</a></div>";
                    }
                }
                echo "</div>";
            }
            break;





        case 'product_slider':
            $title = htmlspecialchars($settings['title'] ?? 'محصولات');
            $button_text = htmlspecialchars($settings['buttonText'] ?? 'نمایش همه');
            $button_link = htmlspecialchars($settings['buttonLink'] ?? '#');
            $count = (int)($settings['count'] ?? 8);
            $slides_per_view = (int)($settings['slidesPerView'] ?? 4);
            $source = $settings['source'] ?? 'latest';
            
            $raw_selected_products = $settings['selectedProducts'] ?? '[]';
            // بررسی اینکه آیا رشته ورودی جیسون خام است یا بیس۶۴ (اگر با [ شروع شود جیسون است)
            $selected_products_json = (strpos(trim($raw_selected_products), '[') === 0) 
                ? $raw_selected_products 
                : js_b64_decode($raw_selected_products);

            $selected_products = json_decode($selected_products_json, true);
            $slider_id = 'product-slider-' . uniqid();

            // لاگ تنظیمات اسلایدر
            $debug_info = [
                'source' => $source,
                'categoryId' => $settings['categoryId'] ?? 'N/A',
                'count' => $count,
                'selectedProductsCount' => is_array($selected_products) ? count($selected_products) : 0,
                'selectedProductsJson' => $selected_products_json // نمایش جیسون خام برای بررسی خطا
            ];
            echo "<script>console.log('Product Slider Debug:', " . json_encode($debug_info) . ");</script>";

            echo "<div class='d-flex justify-content-between align-items-center mb-3'>
                    <h3 class='m-0'>{$title}</h3>
                    " . ($button_text ? "<a href='{$button_link}' class='btn btn-outline-primary'>{$button_text}</a>" : "") . "
                  </div>";

            try {
                $products = [];
                if ($source === 'selected' || $source === 'product_source') {
                    if (!empty($selected_products) && is_array($selected_products)) {
                        $product_ids = array_map(function ($p) {
                            return (int)$p['id'];
                        }, $selected_products);
                        if (!empty($product_ids)) { //
                            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
                            $types = str_repeat('i', count($product_ids));
                            // استفاده از FIELD برای حفظ ترتیب انتخابی کاربر
                            $sql = "SELECT p.id, p.name, p.image, 
                                           (SELECT MIN(po.price) FROM product_offers po JOIN product_variants pv ON po.variant_id = pv.id WHERE pv.product_id = p.id) as price
                                    FROM products p 
                                    WHERE p.id IN ({$placeholders}) AND p.status = 'active'
                                    AND EXISTS (SELECT 1 FROM product_offers po_stock JOIN product_variants pv_stock ON po_stock.variant_id = pv_stock.id WHERE pv_stock.product_id = p.id AND po_stock.is_active = 1 AND po_stock.stock > 0)
";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param($types, ...$product_ids);
                            $stmt->execute();
                            $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); //
                        }
                    }
                } elseif ($source === 'category' || $source === 'source-category') {
                    $category_id = (int)($settings['categoryId'] ?? 0);
                    if ($category_id > 0) {
                         $stmt = $conn->prepare("SELECT p.id, p.name, p.image, (SELECT MIN(po.price) FROM product_offers po JOIN product_variants pv_sub ON po.variant_id = pv_sub.id WHERE pv_sub.product_id = p.id) as price FROM products p WHERE p.category_id = ? AND p.status = 'active' AND EXISTS (SELECT 1 FROM product_offers po_stock JOIN product_variants pv_stock ON po_stock.variant_id = pv_stock.id WHERE pv_stock.product_id = p.id AND po_stock.is_active = 1 AND po_stock.stock > 0) ORDER BY p.id DESC LIMIT ?");
                        $stmt->bind_param("ii", $category_id, $count);
                        $stmt->execute();
                        $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    }
                } else {
                    // در غیر این صورت، آخرین محصولات را بر اساس تعداد واکشی کن
                    $stmt = $conn->prepare("SELECT p.id, p.name, p.image, (SELECT MIN(po.price) FROM product_offers po JOIN product_variants pv_sub ON po.variant_id = pv_sub.id WHERE pv_sub.product_id = p.id) as price FROM products p WHERE p.status = 'active' AND EXISTS (SELECT 1 FROM product_offers po_stock JOIN product_variants pv_stock ON po_stock.variant_id = pv_stock.id WHERE pv_stock.product_id = p.id AND po_stock.is_active = 1 AND po_stock.stock > 0) ORDER BY p.id DESC LIMIT ?");
                    $stmt->bind_param("i", $count);
                    $stmt->execute();
                    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                }

                // لاگ تعداد محصولات
                echo "<script>console.log('Products Found: " . count($products) . "');</script>";

                if ($products) {
                    echo "<div id='{$slider_id}' class='swiper product-slider'><div class='swiper-wrapper'>";
                    foreach ($products as $product) {
                        $product_url = BASE_URL . 'product-' . $product['id'] . '/' . slugify($product['name']);
                        $image_url = !empty($product['image']) ? IMAGE_BASE_URL . 'products/' . htmlspecialchars($product['image']) : 'https://placehold.co/300x300/EFEFEF/AAAAAA?text=No+Image';
                        echo "<div class='swiper-slide'>
                                <div class='card h-100 product-card'>
                                    <a href='" . htmlspecialchars($product_url) . "'><img src='{$image_url}' class='card-img-top' style='height: 180px; object-fit: contain; padding: 10px;' alt='" . htmlspecialchars($product['name']) . "'></a>
                                    <div class='card-body'><h5 class='card-title fs-6' style='font-size: 14px !important; line-height: 21px; color: #eee !important; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;'><a href='{$product_url}' class='text-decoration-none text-dark'>" . htmlspecialchars($product['name']) . "</a></h5></div>
                                    <div class='card-footer bg-transparent border-top-0'><p class='card-text fw-bold'>" . (function_exists('to_persian_num') ? to_persian_num(number_format($product['price'])) : number_format($product['price'])) . " تومان</p></div>
                                </div>
                              </div>";
                    } // End foreach
                    echo "</div>"; // End swiper-wrapper
                    // Add navigation buttons and pagination
                    echo "<div class='swiper-pagination'></div>";
                    echo "</div>"; // End swiper container

                    // Initialize Swiper with Responsive Breakpoints (سه سایز استاندارد)
                    echo "<script>
                        document.addEventListener('DOMContentLoaded', function () { 
                            if (typeof Swiper === 'function') { 
                                new Swiper('#{$slider_id}', { 
                                    slidesPerView: 2, // موبایل: ۲ محصول
                                    spaceBetween: 15, 
                                    breakpoints: {
                                        768: {
                                            slidesPerView: 3, // تبلت: ۳ محصول
                                            spaceBetween: 20
                                        },
                                        1200: {
                                            slidesPerView: 5, // دسکتاپ: ۵ محصول (سایز استاندارد ثابت)
                                            spaceBetween: 25
                                        }
                                    },
                                    pagination: { el: '#{$slider_id} .swiper-pagination', clickable: true } 
                                }); 
                            } 
                        });
                      </script>";
                } else {
                     echo "<div class='alert alert-secondary'>محصولی برای نمایش یافت نشد.</div>";
                }
            } catch (Exception $e) {
                // --- دیباگ: نمایش خطای اصلی پایگاه داده ---
                echo "<script>console.error('Product Slider Error:', " . json_encode($e->getMessage()) . ");</script>";
                echo "<div class='alert alert-danger'>
                        <strong>خطا در واکشی محصولات.</strong><br>
                        <small>جزئیات فنی: " . htmlspecialchars($e->getMessage()) . "</small>
                      </div>";
            }
            break;

    }

    echo "</div>"; // End content-block wrapper
}

/**
 * Renders a form block by its ID.
 * @param array $settings The block settings containing the formId.
 * @param mysqli $conn The database connection object.
 * @return string The HTML of the form.
 */
function render_form_block($settings, $conn) {
    $form_id = isset($settings['formId']) ? (int)$settings['formId'] : 0;
    if (!$form_id) return "<div class='alert alert-warning'>فرم انتخاب نشده است.</div>";

    $stmt = $conn->prepare("SELECT title, form_json FROM forms WHERE id = ?");
    $stmt->bind_param("i", $form_id);
    $stmt->execute();
    $form = $stmt->get_result()->fetch_assoc();

    if (!$form) return "<div class='alert alert-danger'>فرم با شناسه {$form_id} یافت نشد.</div>";

    $fields = json_decode($form['form_json'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return "<div class='alert alert-danger'>ساختار فرم نامعتبر است.</div>";
    }

    // نمایش پیام‌های موفقیت یا خطا
    $output = '';
    if (isset($_SESSION['form_success_message'])) {
        $output .= "<div class='alert alert-success'>" . htmlspecialchars($_SESSION['form_success_message']) . "</div>";
        unset($_SESSION['form_success_message']);
    }
    if (isset($_SESSION['form_error_message'])) {
        $output .= "<div class='alert alert-danger'>" . $_SESSION['form_error_message'] . "</div>"; // No htmlspecialchars to allow <br>
        unset($_SESSION['form_error_message']);
    }
    $old_data = $_SESSION['form_old_data'] ?? [];
    unset($_SESSION['form_old_data']);

    $output .= "<form action='" . BASE_URL . "forms/form-handler.php' method='POST' enctype='multipart/form-data' class='needs-validation' novalidate>";
    $output .= "<input type='hidden' name='form_id' value='{$form_id}'>";

    foreach ($fields as $field) {
        $type = $field['type'] ?? 'text';
        $label = htmlspecialchars($field['label'] ?? '');
        $name = htmlspecialchars($field['name'] ?? '');
        $placeholder = htmlspecialchars($field['placeholder'] ?? '');
        $required = ($field['required'] ?? 'false') === 'true' ? 'required' : '';
        $required_span = $required ? ' <span class="text-danger">*</span>' : '';
        $value = htmlspecialchars($old_data[$name] ?? '');

        $output .= "<div class='mb-3'>";
        switch ($type) {
            case 'text':
            case 'email':
                $output .= "<label for='{$name}' class='form-label'>{$label}{$required_span}</label>";
                $output .= "<input type='{$type}' id='{$name}' name='{$name}' class='form-control' placeholder='{$placeholder}' value='{$value}' {$required}>";
                break;
            case 'textarea':
                $output .= "<label for='{$name}' class='form-label'>{$label}{$required_span}</label>";
                $output .= "<textarea id='{$name}' name='{$name}' class='form-control' placeholder='{$placeholder}' {$required}>{$value}</textarea>";
                break;
            case 'select':
                $output .= "<label for='{$name}' class='form-label'>{$label}{$required_span}</label>";
                $output .= "<select id='{$name}' name='{$name}' class='form-select' {$required}>";
                $options = explode("\n", $field['options'] ?? '');
                foreach ($options as $option) {
                    $opt_val = htmlspecialchars(trim($option));
                    $selected = ($value === $opt_val) ? 'selected' : '';
                    $output .= "<option value='{$opt_val}' {$selected}>{$opt_val}</option>";
                }
                $output .= "</select>";
                break;
            case 'submit':
                $button_text = htmlspecialchars($field['buttonText'] ?? 'ارسال');
                $output .= "<button type='submit' class='btn btn-primary'>{$button_text}</button>";
                break;
            case 'file':
                $output .= "<label for='{$name}' class='form-label'>{$label}{$required_span}</label>";
                $output .= "<input type='file' id='{$name}' name='{$name}' class='form-control' {$required}>";
                break;
        }
        $output .= "</div>";
    }

    $output .= "</form>";
    return $output;
}
?>