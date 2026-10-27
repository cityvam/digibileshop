<?php
// inc/megamenu.php

// (جدید) واکشی و ساختار درختی آیتم‌های منوی اصلی
$main_menu_result = $conn->query("SELECT id, title, link, icon, target, parent_id FROM main_menu_items ORDER BY parent_id, sort_order");
$all_main_menu_items = $main_menu_result->fetch_all(MYSQLI_ASSOC);

$main_menu_tree = [];
$main_menu_map = [];

// ابتدا همه آیتم‌ها را بر اساس ID مپ می‌کنیم
foreach ($all_main_menu_items as $item) {
    $main_menu_map[$item['id']] = $item;
    $main_menu_map[$item['id']]['children'] = [];
}

// سپس ساختار درختی را بر اساس parent_id می‌سازیم
foreach ($main_menu_map as $id => &$item) { // استفاده از reference
    if ($item['parent_id'] && isset($main_menu_map[$item['parent_id']])) {
        $main_menu_map[$item['parent_id']]['children'][] =& $item;
    } elseif (!$item['parent_id']) {
        $main_menu_tree[] =& $item;
    }
}
unset($item); // حذف reference

/**
 * (جدید) تابع بازگشتی برای رندر کردن آیتم‌های منو و زیرمنوهای آن‌ها
 */
function render_main_menu_item($item) {
    $has_children = !empty($item['children']);
    $li_class = 'nav-item' . ($has_children ? ' dropdown' : '');
    $a_class = 'nav-link' . ($has_children ? ' dropdown-toggle' : '');
    $a_attrs = $has_children ? ' href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"' : ' href="' . htmlspecialchars($item['link']) . '"';

    echo "<li class=\"{$li_class}\">";
    echo "<a class=\"{$a_class}\"{$a_attrs} target=\"" . htmlspecialchars($item['target']) . "\">";
    if (!empty($item['icon'])) {
        echo "<i class=\"" . htmlspecialchars($item['icon']) . " me-2\"></i>";
    }
    echo htmlspecialchars($item['title']);
    echo "</a>";

    // در اینجا فقط یک سطح زیرمنو پشتیبانی می‌شود
    if ($has_children) {
        echo '<ul class="dropdown-menu">';
        foreach ($item['children'] as $child) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars($child['link']) . '" target="' . htmlspecialchars($child['target']) . '">' . htmlspecialchars($child['title']) . '</a></li>';
        }
        echo '</ul>';
    }
    echo '</li>';
}

// واکشی و ساختار درختی دسته‌بندی‌ها برای مگامنو
$megamenu_items_result = $conn->query("SELECT id, title as name, link, parent_id, icon, target FROM megamenu_items ORDER BY parent_id, sort_order");
$all_items = $megamenu_items_result ? $megamenu_items_result->fetch_all(MYSQLI_ASSOC) : [];

$category_tree = [];
$category_map = [];

// ابتدا همه دسته‌بندی‌ها را بر اساس ID مپ می‌کنیم
foreach ($all_items as $category) {
    $category_map[$category['id']] = $category;
    $category_map[$category['id']]['children'] = [];
}

// سپس ساختار درختی را بر اساس parent_id می‌سازیم
foreach ($category_map as $id => &$category) { // استفاده از reference
    if ($category['parent_id'] && isset($category_map[$category['parent_id']])) {
        $category_map[$category['parent_id']]['children'][] =& $category;
    } elseif (!$category['parent_id']) {
        $category_tree[] =& $category;
    }
}
unset($category); // حذف reference
?>
<!-- مگامنو دسته‌بندی محصولات -->
<li class="nav-item dropdown" id="mega-menu-container">
    <a href="#" class="nav-link fw-bold dropdown-toggle" id="mega-menu-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fa fa-bars me-2"></i>دسته‌بندی محصولات
    </a>
    
    <!-- Unified Dropdown Menu Container -->
    <div class="dropdown-menu p-0 border-0" id="unified-mega-menu">
        
        <!-- Desktop View -->
        <div class="d-none d-lg-block mega-menu-desktop-content">
            <div class="row g-0">
                <div class="col-3">
                    <ul class="list-unstyled mega-menu-main-cats">
                        <?php foreach ($category_tree as $main_cat): ?>
                            <li data-target="#menu-<?php echo $main_cat['id']; ?>">
                                <a href="<?php echo htmlspecialchars($main_cat['link'] ?? '#'); ?>" target="<?php echo htmlspecialchars($main_cat['target'] ?? '_self'); ?>">
                                    <?php if (!empty($main_cat['icon'])): ?><i class="fa <?php echo htmlspecialchars($main_cat['icon']); ?> fa-fw"></i><?php endif; ?>
                                    <span><?php echo htmlspecialchars($main_cat['name']); ?></span>
                                    <?php if (!empty($main_cat['children'])): ?><i class="fa fa-chevron-left float-end"></i><?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-9">
                    <div class="mega-menu-sub-cats">
                        <?php foreach ($category_tree as $main_cat): ?>
                            <?php if (!empty($main_cat['children'])): ?>
                                <div class="sub-cat-content" id="menu-<?php echo $main_cat['id']; ?>">
                                    <?php foreach ($main_cat['children'] as $sub_cat): ?>
                                        <div class="sub-cat-group">
                                            <a href="<?php echo htmlspecialchars($sub_cat['link'] ?? '#'); ?>" target="<?php echo htmlspecialchars($sub_cat['target'] ?? '_self'); ?>" class="sub-cat-title">
                                                <?php if (!empty($sub_cat['icon'])): ?>
                                                    <i class="fa <?php echo htmlspecialchars($sub_cat['icon']); ?> fa-fw"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($sub_cat['name']); ?>
                                            </a>
                                            <?php if (!empty($sub_cat['children'])): ?>
                                                <ul class="list-unstyled">
                                                    <?php foreach ($sub_cat['children'] as $grandchild_cat): ?>
                                                        <li><a href="<?php echo htmlspecialchars($grandchild_cat['link'] ?? '#'); ?>" target="<?php echo htmlspecialchars($grandchild_cat['target'] ?? '_self'); ?>">
                                                            <?php if (!empty($grandchild_cat['icon'])): ?>
                                                                <i class="fa <?php echo htmlspecialchars($grandchild_cat['icon']); ?> fa-fw"></i>
                                                            <?php endif; ?>
                                                            <?php echo htmlspecialchars($grandchild_cat['name']); ?>
                                                        </a></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile View (Tabbed Layout) -->
        <div class="d-lg-none mobile-menu-wrapper h-100" onclick="event.stopPropagation()">
            <!-- Header with Close Button -->
            <div class="d-flex align-items-center justify-content-between p-3 border-bottom bg-white sticky-top">
                <span class="fw-bold">دسته‌بندی محصولات</span>
                <button type="button" class="btn-close" onclick="document.getElementById('mega-menu-toggle').click()"></button>
            </div>
            
            <div class="d-flex h-100" style="height: calc(100% - 58px) !important;">
                <!-- Sidebar (Right) -->
                <div class="mobile-menu-sidebar bg-light overflow-auto custom-scrollbar" style="width: 90px; min-width: 90px; padding-bottom: 100px;">
                    <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                        <?php foreach ($category_tree as $index => $main_cat): ?>
                            <button class="nav-link <?php echo $index === 0 ? 'active' : ''; ?> text-center py-3 w-100 rounded-0 border-bottom border-light" 
                                    id="v-pills-<?php echo $main_cat['id']; ?>-tab" 
                                    data-bs-toggle="pill" 
                                    data-bs-target="#v-pills-<?php echo $main_cat['id']; ?>" 
                                    type="button" 
                                    role="tab" 
                                    aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                <?php if (!empty($main_cat['icon'])): ?>
                                    <i class="fa <?php echo htmlspecialchars($main_cat['icon']); ?> fs-5 mb-2 d-block"></i>
                                <?php else: ?>
                                    <i class="fa fa-folder fs-5 mb-2 d-block"></i>
                                <?php endif; ?>
                                <span class="d-block text-wrap" style="font-size: 11px; line-height: 1.4;"><?php echo htmlspecialchars($main_cat['name']); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Content (Left) -->
                <div class="mobile-menu-content flex-grow-1 bg-white overflow-auto custom-scrollbar" style="padding-bottom: 100px;">
                    <div class="tab-content" id="v-pills-tabContent">
                        <?php foreach ($category_tree as $index => $main_cat): ?>
                            <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>" 
                                 id="v-pills-<?php echo $main_cat['id']; ?>" 
                                 role="tabpanel">
                                
                                <div class="p-3">
                                    <a href="<?php echo htmlspecialchars($main_cat['link'] ?? '#'); ?>" class="text-decoration-none fw-bold text-primary d-flex align-items-center justify-content-between">
                                        <span class="small">همه محصولات <?php echo htmlspecialchars($main_cat['name']); ?></span>
                                        <i class="fa fa-chevron-left small"></i>
                                    </a>
                                </div>

                                <?php if (!empty($main_cat['children'])): ?>
                                    <div class="accordion accordion-flush" id="accordion-<?php echo $main_cat['id']; ?>">
                                        <?php foreach ($main_cat['children'] as $sub_cat): ?>
                                            <?php $has_sub_children = !empty($sub_cat['children']); ?>
                                            <div class="accordion-item border-0">
                                                <h2 class="accordion-header" id="heading-<?php echo $sub_cat['id']; ?>">
                                                    <?php if ($has_sub_children): ?>
                                                        <button class="accordion-button collapsed py-3 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $sub_cat['id']; ?>" aria-expanded="false">
                                                            <span class="fw-bold text-dark" style="font-size: 13px;"><?php echo htmlspecialchars($sub_cat['name']); ?></span>
                                                        </button>
                                                    <?php else: ?>
                                                        <a href="<?php echo htmlspecialchars($sub_cat['link'] ?? '#'); ?>" class="d-block py-3 px-3 text-decoration-none text-dark d-flex justify-content-between align-items-center border-bottom">
                                                            <span class="fw-bold" style="font-size: 13px;"><?php echo htmlspecialchars($sub_cat['name']); ?></span>
                                                        </a>
                                                    <?php endif; ?>
                                                </h2>
                                                <?php if ($has_sub_children): ?>
                                                    <div id="collapse-<?php echo $sub_cat['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#accordion-<?php echo $main_cat['id']; ?>">
                                                        <div class="accordion-body py-0 bg-light">
                                                            <ul class="list-unstyled m-0">
                                                                <?php foreach ($sub_cat['children'] as $grandchild): ?>
                                                                    <li>
                                                                        <a href="<?php echo htmlspecialchars($grandchild['link'] ?? '#'); ?>" class="d-block py-2 px-3 text-decoration-none text-secondary small border-bottom border-white">
                                                                            <?php echo htmlspecialchars($grandchild['name']); ?>
                                                                        </a>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Unified Styles */
        /* Desktop */
        @media (min-width: 992px) {
            #unified-mega-menu {
                width: 900px;
                left: auto;
                right: 0;
                margin-top: 0;
                box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
                border-radius: 0.375rem;
                background-color: #fff;
            }
            #mega-menu-container:hover #unified-mega-menu {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
                pointer-events: auto;
            }
        }

        /* Mobile */
        @media (max-width: 991.98px) {
            #unified-mega-menu {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                width: 100% !important;
                height: 100vh !important;
                max-height: none !important;
                transform: none !important;
                margin: 0 !important;
                border-radius: 0 !important;
                z-index: 9999;
                background-color: #fff;
                display: none;
                flex-direction: column;
            }
            #unified-mega-menu.show {
                display: flex !important;
            }
            .mobile-menu-wrapper {
                display: flex;
                flex-direction: column;
                height: 100%;
            }
        }
        
        /* Sidebar Styles */
        .mobile-menu-sidebar .nav-link {
            color: #616161;
            background-color: #f7f7f7;
            transition: all 0.2s;
        }
        .mobile-menu-sidebar .nav-link.active {
            color: #ef394e; /* Digikala Red */
            background-color: #ffffff;
            font-weight: bold;
            box-shadow: inset -4px 0 0 #ef394e; /* Red line indicator */
        }
        .mobile-menu-sidebar .nav-link i {
            color: inherit;
        }
        
        /* Accordion Styles */
        .accordion-button:not(.collapsed) {
            color: #ef394e;
            background-color: transparent;
            box-shadow: none;
        }
        .accordion-button:focus {
            box-shadow: none;
        }
        .accordion-button::after {
            background-size: 0.8rem;
        }
        
        /* Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #ccc;
            border-radius: 4px;
        }
    </style>
</li>