<!DOCTYPE HTML>
<html <?php language_attributes(); ?> data-theme="light">
<head>
    <?php
    $effects_settings = get_option('paper_wp_effects_settings');
    // 从后台设置读取默认主题模式
    $default_mode = !empty($effects_settings['theme_mode']) ? $effects_settings['theme_mode'] : 'auto';
    ?>
    <script>
    (function(){
        // 从PHP获取配置
        var defaultMode = "<?php echo $default_mode; ?>";

        // 确定要应用的主题
        var theme = defaultMode;
        var effectiveTheme = defaultMode;

        try {
            // 如果后台设置为固定主题(light/dark),强制使用后台设置
            if (defaultMode === "light" || defaultMode === "dark") {
                // 清除用户偏好,确保后台设置生效
                localStorage.removeItem("barepaper_theme_preference");
                theme = defaultMode;
                effectiveTheme = defaultMode;
            } else if (defaultMode === "auto") {
                // auto模式:检查用户偏好
                var saved = localStorage.getItem("barepaper_theme_preference");
                if (saved && (saved === "light" || saved === "dark")) {
                    // 使用用户偏好
                    theme = saved;
                    effectiveTheme = saved;
                } else {
                    // 没有用户偏好,跟随系统主题
                    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        effectiveTheme = "dark";
                    } else {
                        effectiveTheme = "light";
                    }
                }
            }
        } catch(e) {}

        // 立即应用主题到DOM
        document.documentElement.setAttribute("data-theme", effectiveTheme);

        // 暴露配置给后续脚本(兼容旧版本)
        window.BarepaperThemeConfig = {
            enableSwitch: true,
            defaultMode: defaultMode
        };
        
        // 暴露配置给effects.js使用
        window.paperWpSettings = {
            enable_theme_switch: "1",
            theme_mode: defaultMode
        };
    })();
    </script>
    
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="renderer" content="webkit">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=2">
    <meta name="theme-color" content="#3354AA">
    <?php 
    // 输出自定义统计代码
    if (!empty($effects_settings['stats_code'])) {
        echo $effects_settings['stats_code'] . "\n";
    }
    wp_head(); 
    ?>
</head>
<body <?php 
    $supports_postmessage = !empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['REQUEST_METHOD']);
    body_class(); 
    echo ' data-customize-support="' . ($supports_postmessage ? 'true' : 'false') . '"';
?>>
    <?php $paper_wp_settings = get_option( 'paper_wp_theme_settings' ); ?>
    <?php $paper_wp_ad_settings = get_option( 'paper_wp_ad_settings' ); ?>

    <header id="header" class="clearfix">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <?php
                    if (!empty($paper_wp_ad_settings['show_header_ad']) && !empty($paper_wp_ad_settings['header_ad_code'])) {
                        echo '<div class="header-banner">' . wp_kses_post($paper_wp_ad_settings['header_ad_code']) . '</div>';
                    }
                    ?>
                </div>
            </div>
            <div class="row">
                <div class="site-name col-mb-12 col-9" style="padding-top: 20px; display: flex; align-items: center;">
                    <div id="logo">
                        <?php 
                        // 获取自定义设置
                        $site_logo = !empty($effects_settings['site_logo']) ? $effects_settings['site_logo'] : '';
                        $site_title = !empty($effects_settings['site_title']) ? $effects_settings['site_title'] : get_bloginfo('name');
                        
                        if ($site_logo) {
                            echo '<a href="' . home_url() . '"><img src="' . esc_url($site_logo) . '" alt="' . esc_attr($site_title) . '" style="max-height: 50px;"></a>';
                        } else {
                            echo '<a href="' . home_url() . '">' . esc_html($site_title) . '</a>';
                        }
                        ?>
                    </div>
                </div>
                <div class="site-subtitle col-3 kit-hidden-tb" style="text-align: right; padding-top: 30px; display: flex; align-items: center; justify-content: flex-end;">
                    <p class="description" style="margin: 0;">
                        <?php 
                        $site_subtitle = !empty($effects_settings['site_subtitle']) ? $effects_settings['site_subtitle'] : get_bloginfo('description');
                        echo esc_html($site_subtitle);
                        ?>
                    </p>
                </div>

                <div class="col-mb-12" style="margin-top: 10px;">
                    <nav id="nav-menu" class="clearfix navbar" role="navigation">
                        <label for="nav-toggle">
                            <span class="menu-icon">
                                <svg viewBox="0 0 18 15" width="18px" height="15px">
                                    <path fill="currentColor" d="M18,1.484c0,0.82-0.665,1.484-1.484,1.484H1.484C0.665,2.969,0,2.304,0,1.484l0,0C0,0.665,0.665,0,1.484,0 h15.031C17.335,0,18,0.665,18,1.484L18,1.484z"/>
                                    <path fill="currentColor" d="M18,7.516C18,8.335,17.335,9,16.516,9H1.484C0.665,9,0,8.335,0,7.516l0,0c0-0.82,0.665-1.484,1.484-1.484 h15.031C17.335,6.031,18,6.696,18,7.516L18,7.516z"/>
                                    <path fill="currentColor" d="M18,13.516C18,14.335,17.335,15,16.516,15H1.484C0.665,15,0,14.335,0,13.516l0,0 c0-0.82,0.665-1.484,1.484-1.484h15.031C17.335,12.031,18,12.696,18,13.516L18,13.516z"/>
                                </svg>
                            </span>
                        </label>
                        <input type="checkbox" id="nav-toggle" class="nav-toggle"/>
                        <?php wp_nav_menu(array('theme_location' => 'primary', 'menu_class' => 'nav-list', 'container' => false, 'items_wrap' => '<ul id="%1$s" class="%2$s" style="margin-left: auto;">%3$s</ul>')); ?>
                    </nav>
                </div>
            </div>
        </div>
    </header>
    <div id="body">
        <div class="container">
            <div class="row">
