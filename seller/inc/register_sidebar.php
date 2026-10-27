<style>
    .register-sidebar {
        width: 280px;
        min-width: 280px;
        background-color: #fff;
        border-left: 1px solid #e9ecef;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        transition: margin-right 0.3s ease;
    }
    .register-sidebar .logo-container {
        text-align: center;
        padding-bottom: 1.5rem;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid #e9ecef;
    }
    .register-sidebar .logo-container img {
        max-width: 120px;
    }
    .register-sidebar .welcome-text {
        font-size: 0.9rem;
        color: #6c757d;
        text-align: center;
        margin-bottom: 2rem;
    }
    .step-list {
        list-style: none;
        padding: 0;
        position: relative;
    }
    .step-list::before {
        content: '';
        position: absolute;
        top: 10px;
        right: 12px;
        bottom: 10px;
        width: 2px;
        background-color: #e9ecef;
        z-index: 1;
    }
    .step-item {
        margin-bottom: 1.5rem;
        position: relative;
        padding-right: 35px;
    }
    .step-item .step-icon {
        position: absolute;
        right: 0;
        top: -2px;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background-color: #fff;
        border: 2px solid #adb5bd;
        color: #adb5bd;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2;
        font-size: 1.1rem;
    }
    .step-item.active .step-icon {
        border-color: var(--bs-primary);
        color: var(--bs-primary);
    }
    .step-item.completed .step-icon {
        border-color: var(--bs-success);
        background-color: var(--bs-success);
        color: #fff;
    }
    .step-item .step-title {
        font-weight: bold;
        font-size: 1rem;
        color: #495057;
    }
    .step-item.active .step-title {
        color: var(--bs-primary);
    }
    .step-item .step-subtitle {
        font-size: 0.85rem;
        color: #6c757d;
    }
    .register-sidebar .logout-link {
        margin-top: auto;
        text-align: center;
    }
</style>

<aside class="register-sidebar">
    <div class="logo-container">
        <img src="<?php echo BASE_URL . 'img/logo.png'; ?>" alt="Logo">
    </div>
    <p class="welcome-text">به پنل فروشندگان خوش آمدید.</p>

    <ul class="step-list">
        <?php
        // Define steps
        $steps = [
            'info' => ['title' => 'اطلاعات فروشنده', 'subtitle' => 'اطلاعات شخصی و تجاری', 'icon' => 'bi-person-vcard'],
            'bank' => ['title' => 'اطلاعات حساب بانکی', 'subtitle' => 'شماره شبا و حساب', 'icon' => 'bi-bank'],
            'address' => ['title' => 'اطلاعات تماس و آدرس', 'subtitle' => 'آدرس پستی و تماس', 'icon' => 'bi-geo-alt'],
            'docs' => ['title' => 'بارگذاری مدارک', 'subtitle' => 'مدارک شناسایی و کسب‌وکار', 'icon' => 'bi-cloud-upload'],
            'contract' => ['title' => 'اطلاعات قرارداد', 'subtitle' => 'تایید نهایی و قوانین', 'icon' => 'bi-patch-check'],
        ];
        $current_step_index = array_search($current_step, array_keys($steps));
        $i = 0;
        ?>
        <?php foreach ($steps as $key => $step): ?>
            <?php
            $class = '';
            if ($key === $current_step) {
                $class = 'active';
            } elseif ($i < $current_step_index) {
                $class = 'completed';
            }
            $i++;
            ?>
        <li class="step-item <?php echo $class; ?>" id="sidebar-step-<?php echo $key; ?>">
            <i class="bi <?php echo $step['icon']; ?> step-icon"></i>
            <div class="step-title"><?php echo $step['title']; ?></div>
            <div class="step-subtitle"><?php echo $step['subtitle']; ?></div>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="logout-link">
        <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-2"></i>خروج</a>
    </div>
</aside>