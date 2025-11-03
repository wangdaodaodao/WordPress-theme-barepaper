            </div>
            <!-- end .row -->
        </div>
    </div>
    <!-- end #body -->

    <footer id="footer" role="contentinfo">
        <?php
        $paper_wp_settings = get_option('paper_wp_theme_settings');
        if (!empty($paper_wp_settings['show_blog_stats'])) {
            // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
            // paper_wp_render_footer_stats();
        } else {
            // 默认显示简版版权信息（使用WordPress时区）
            echo '&copy; ' . wp_date('Y') . ' <a href="' . home_url() . '">' . get_bloginfo('name') . '</a>';
        }
        ?>

        <?php
        // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
        // $paper_wp_effects_settings = get_option( 'paper_wp_effects_settings' );
        // if ( !empty( $paper_wp_effects_settings['show_theme_toggle'] ) ) : ...
        // <?php endif; ?>
    </footer>
    <!-- end #footer -->

    <?php wp_footer(); ?>


</body>
</html>
