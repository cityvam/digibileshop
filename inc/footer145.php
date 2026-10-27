<?php
// واکشی تنظیمات فوتر
$footer_settings_stmt = $conn->query("SELECT setting_key, setting_value FROM footer_settings");
$footer_settings = [];
if ($footer_settings_stmt) {
    while ($row = $footer_settings_stmt->fetch_assoc()) {
        $footer_settings[$row['setting_key']] = $row['setting_value'];
    }
}
$footer_logo_url = $footer_settings['footer_logo_url'] ?? '';

// متن طولانی زیر لوگو
$footer_full_text = "بازارگاه دیجی بایل سال‌ها است که به‌عنوان بزرگترین فروشگاه کالای دیجیتال مشغول فعالیت است. از آن‌جا که خرید اینترنتی همواره موجی از بی‌اعتمادی و شک را با خود به‌همراه داشته، نماد الکترونیکی می‌تواند خیال خیلی از افراد را راحت کند. دیجی بایل با داشتن نماد اعتماد الکترونیکی و عضویت در سازمان صنفی رایانه‌ای کشور، همچنین عضویت در انجمن صنفی فروشگاه‌های اینترنتی، فضای ایمن برای خرید آنلاین را برای مشتریان خود ایجاد کرده است. شما می‌توانید خرید کالای دیجیتال مانند خرید لپ تاپ ، گوشی موبایل در مدل‌ها و برندهای مختلف، لوازم جانبی موبایل ، هدفون، و کلیه لوازم دیجیتال مدنظر خود را با بهترین قیمت، در فروشگاه دیجی بایل به ثبت برسانید. خرید گوشی از دیجی بایل برای خیلی از کاربران، دیجی بایل فروشگاه اینترنتی موبایل محسوب می‌شود. این بدان خاطر است که شما می‌توانید انواع گوشی موبایل مورد نظر خود، از جمله گوشی شیائومی ، گوشی سامسونگ، گوشی هواوی، آیفون و دیگر برندها را در دیجی بایل به‌راحتی پیدا و با قیمتی مناسب، خریداری کنید. خرید گوشی موبایل، آن‌هم اینترنتی، می‌تواند کاری دشوار باشد؛ اما شما می‌توانید با اطمینان کامل، گوشی موبایل مد نظرتان را از فروشگاه اینترنتی موبایل دیجی بایل استعلام قیمت کرده و خریداری کنید.";

// استخراج دو خط اول
$lines = explode("\n", wordwrap($footer_full_text, 150, "\n"));
$short_text = implode("\n", array_slice($lines, 0, 2));

// ستون‌ها
$column_settings_stmt = $conn->query("SELECT column_number, title, col_class, column_type, text_content FROM footer_column_titles ORDER BY column_number ASC");
$column_settings = [];
while ($row = $column_settings_stmt->fetch_assoc()) {
    $column_settings[$row['column_number']] = $row;
}

// لینک‌ها
$footer_links_stmt = $conn->query("SELECT * FROM footer_links WHERE is_active = 1 ORDER BY column_number, sort_order ASC");
$footer_links = [];
while ($link = $footer_links_stmt->fetch_assoc()) {
    $footer_links[$link['column_number']][] = $link;
}

// نمادها
$trust_symbols_stmt = $conn->query("SELECT name, logo, settings, slug FROM trust_seals WHERE is_active = 1 ORDER BY id ASC");
$trust_symbols = [];
if ($trust_symbols_stmt) {
    while ($row = $trust_symbols_stmt->fetch_assoc()) {
        $settings = json_decode($row['settings'] ?? '{}', true);
        $html_code = is_array($settings) && !empty($settings) ? reset($settings) : '';
        $row['html_code'] = html_entity_decode($html_code);
        $trust_symbols[] = $row;
    }
}

// شبکه‌های اجتماعی
$social_links_stmt = $conn->query("SELECT title, url, icon_class FROM social_manager WHERE is_active = 1 ORDER BY sort_order ASC");
$social_links = [];
while ($social = $social_links_stmt->fetch_assoc()) {
    $social_links[] = $social;
}

// کپی‌رایت
$site_footer_stmt = $conn->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('footer_copyright_text')");
$site_footer = [];
if ($site_footer_stmt) {
    while ($row = $site_footer_stmt->fetch_assoc()) {
        $site_footer[$row['setting_key']] = $row['setting_value'];
    }
}
$copyright_text = $site_footer['footer_copyright_text'] ?? '© تمامی حقوق محفوظ است.';

// توابع فارسی
function toPersianNumber($number){ $farsi_digits=['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹']; return strtr(strval($number), array_combine(range(0,9), $farsi_digits)); }
function jalaliYear(){ $d=new DateTime(); $gy=(int)$d->format('Y'); $gm=(int)$d->format('m'); $gd=(int)$d->format('d'); $g_d_m=[0,31,59,90,120,151,181,212,243,273,304,334]; if($gy>1600){$jy=979;$gy-=1600;}else{$jy=0;$gy-=621;} $gy2=($gm>2)?($gy+1):$gy; $days=(365*$gy)+(int)(($gy2+3)/4)-(int)(($gy2+99)/100)+(int)(($gy2+399)/400)-80+$gd+$g_d_m[$gm-1]; $jy+=33*(int)($days/12053); $days%=12053; $jy+=4*(int)($days/1461); $days%=1461; if($days>365){$jy+=(int)(($days-1)/365); $days=($days-1)%365;} return toPersianNumber($jy);}
?>

<footer class="footer-digikala-like">
    <div class="container">

        <!-- لوگو + متن کوتاه + ادامه مطلب -->
        <div class="footer-section py-4 text-start">
            <?php if($footer_logo_url): ?>
                <img src="<?php echo htmlspecialchars($footer_logo_url); ?>" alt="لوگو فوتر" class="footer-logo mb-2">
            <?php endif; ?>
            <p class="footer-short-text"><?php echo nl2br(htmlspecialchars($short_text)); ?></p>
            <p id="footer-full-text" class="footer-full-text" style="display:none;"><?php echo nl2br(htmlspecialchars($footer_full_text)); ?></p>
            <button class="btn btn-footer-readmore mt-2">ادامه مطلب</button>
        </div>
        <hr>

        <!-- ستون‌ها -->
        <div class="footer-section py-4">
            <div class="row">
                <?php foreach($column_settings as $col_num=>$col): ?>
                    <div class="<?php echo htmlspecialchars($col['col_class']?:'col-lg-3 col-md-6'); ?> mb-4">
                        <h6 class="footer-col-title"><?php echo htmlspecialchars($col['title']); ?></h6>
                        <?php if($col['column_type']==='text'): ?>
                            <p class="footer-col-text"><?php echo nl2br(htmlspecialchars($col['text_content'])); ?></p>
                        <?php elseif(!empty($footer_links[$col_num])): ?>
                            <ul class="footer-col-links list-unstyled">
                                <?php foreach($footer_links[$col_num] as $link): ?>
                                    <li><a href="<?php echo htmlspecialchars($link['url']); ?>" <?php echo ($link['target_blank']??0)?'target="_blank"':''; ?>><?php echo htmlspecialchars($link['title']); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <hr>

        <!-- خبرنامه بزرگ -->
        <div class="footer-section py-4">
            <div class="newsletter-section mb-4">
                <h3 class="newsletter-heading">با عضویت در خبرنامه ما از آخرین اخبار و تخفیف‌ها مطلع شوید</h3>
                <form id="newsletterFormFooter" action="<?php echo BASE_URL; ?>newsletter_subscribe.php" method="POST" class="footer-newsletter-form">
                    <input type="tel" name="mobile_number" placeholder="شماره موبایل" required pattern="09[0-9]{9}">
                    <button type="submit">ثبت</button>
                </form>
            </div>
        </div>
        <hr>

        <!-- نمادها و شبکه‌های اجتماعی بالای اپلیکیشن -->
        <div class="footer-section py-4 d-flex justify-content-center flex-wrap gap-3">
            <?php foreach($trust_symbols as $symbol): ?>
                <div class="trust-item p-2 border rounded">
                    <?php if($symbol['html_code']): echo $symbol['html_code']; else: ?>
                        <img src="<?php echo IMAGE_BASE_URL . 'upload/symbols/' . htmlspecialchars($symbol['logo']); ?>" alt="<?php echo htmlspecialchars($symbol['name']); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php foreach($social_links as $social): ?>
                <a href="<?php echo htmlspecialchars($social['url']); ?>" target="_blank" title="<?php echo htmlspecialchars($social['title']); ?>" class="social-icon">
                    <img src="<?php echo IMAGE_BASE_URL . 'upload/social/' . htmlspecialchars($social['icon_class']); ?>" alt="<?php echo htmlspecialchars($social['title']); ?>" style="height:40px;">
                </a>
            <?php endforeach; ?>
        </div>
        <hr>

        <!-- دانلود اپلیکیشن -->
        <div class="footer-section py-4">
            <div class="row align-items-center">
                <div class="col-md-6 text-start">
                    <h6>دانلود اپلیکیشن دیجی‌بایل</h6>
                </div>
                <div class="col-md-6 d-flex justify-content-end gap-3 flex-wrap">
                    <a href="#" class="btn btn-app-download"><i class="fab fa-android"></i> کافه‌بازار</a>
                    <a href="#" class="btn btn-app-download"><i class="fab fa-google-play"></i> گوگل پلی</a>
                    <a href="#" class="btn btn-app-download"><i class="fab fa-apple"></i> اپ استور</a>
                </div>
            </div>
        </div>
        <hr>

        <!-- کپی‌رایت -->
        <div class="footer-section py-3 text-center">
            <small><?php echo str_replace('{{current_jalali_year}}', jalaliYear(), $copyright_text); ?></small>
        </div>

    </div>
</footer>

<style>
.footer-digikala-like { background:#003c8f; color:#fff; font-family:'IranSans',sans-serif; font-size:16px; direction:rtl; }
.footer-logo { max-height:70px; filter:brightness(0) invert(1); margin-bottom:10px; }
.footer-short-text { font-size:16px; line-height:1.6; }
.footer-full-text { font-size:14px; line-height:1.7; color:#e0e0e0; margin-top:10px; }
.btn-footer-readmore { background:#ffd54f; border:none; padding:8px 20px; border-radius:5px; cursor:pointer; font-size:16px; font-weight:bold; }
.footer-col-title { font-weight:700; margin-bottom:12px; color:#fff; font-size:16px; }
.footer-col-links li a { color:#e0e0e0; text-decoration:none; transition:0.2s; font-size:15px; }
.footer-col-links li a:hover { color:#ffd54f; }
.footer-newsletter-form { display:flex; border:1px solid #ccc; border-radius:30px; overflow:hidden; background:#fff; margin-top:10px; }
.footer-newsletter-form input { flex:1; padding:10px 15px; border:none; outline:none; font-size:16px; }
.footer-newsletter-form button { background:#ffd54f; color:#000; border:none; padding:10px 18px; cursor:pointer; font-size:16px; font-weight:bold; }
.newsletter-heading { font-size:20px; font-weight:bold; margin-bottom:10px; }
.btn-app-download { background:#fff; color:#000; font-weight:700; font-size:16px; padding:10px 15px; border-radius:5px; text-decoration:none; display:flex; align-items:center; gap:5px; transition:0.3s; }
.btn-app-download i { font-size:18px; }
.btn-app-download:hover { background:#e0e0e0; }
.trust-item img, .trust-item svg { max-height:50px; }
.trust-item { display:inline-flex; justify-content:center; align-items:center; background:#fff; border-radius:8px; }
.social-icon img { height:40px; margin-left:10px; }
hr { border-color: rgba(255,255,255,0.2); margin:25px 0; }
</style>

<script>
document.querySelector('.btn-footer-readmore').addEventListener('click', function(){
    document.getElementById('footer-full-text').style.display='block';
    this.style.display='none';
});

document.getElementById('newsletterFormFooter')?.addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this); formData.append('ajax','1');
    fetch(this.action,{method:'POST', body:formData})
        .then(r=>r.json())
        .then(data=>{ alert(data.message||'ثبت نام انجام شد.'); })
        .catch(console.error);
});
</script>
