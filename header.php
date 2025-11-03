<!DOCTYPE HTML>
<html <?php language_attributes(); ?> data-theme="light">
<head>
    <!-- 立即设置主题，防止闪烁 -->
    <script>
    (function(){
        try {
            var theme = localStorage.getItem("barepaper_theme_preference");
            if (!theme || theme === "auto") {
                theme = "light";
                localStorage.setItem("barepaper_theme_preference", "light");
            }
            if (theme === "light" || theme === "dark") {
                document.documentElement.setAttribute("data-theme", theme);
            }
        } catch(e) {
            document.documentElement.setAttribute("data-theme", "light");
        }
    })();
    </script>
    
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="renderer" content="webkit">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=2">
    <meta name="theme-color" content="#3354AA">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <?php $paper_wp_settings = get_option( 'paper_wp_theme_settings' ); ?>
    <?php $paper_wp_ad_settings = get_option( 'paper_wp_ad_settings' ); ?>

    <header id="header" class="clearfix">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <?php
                    // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
                    // if ( isset( $paper_wp_ad_settings['show_header_ad'] ) && ... ) {
                    //     echo '<div class="header-banner">' . wp_kses_post( $paper_wp_ad_settings['header_ad_code'] ) . '</div>';
                    // }
                    ?>
                </div>
            </div>
            <div class="row">
                <div class="site-name col-mb-12 col-9" style="padding-top: 20px; display: flex; align-items: center;">
                    <div id="logo">
                        <a href="<?php echo home_url(); ?>"><?php bloginfo('name'); ?></a>
                    </div>
                </div>
                <?php if ( isset( $paper_wp_settings['show_poetry_recommendation'] ) && $paper_wp_settings['show_poetry_recommendation'] ) : ?>
                <?php
                // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
                // <div class="site-poetry ..."><span id="jinrishici-sentence">...</span></div>
                ?>
                <?php endif; ?>

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
    <!-- end #header -->
    <div id="body">
        <div class="container">
            <div class="row">
