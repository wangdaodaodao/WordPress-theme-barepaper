            </div>

        </div>
    </div>

    <footer id="footer" role="contentinfo">
        <?php
        $effects_settings = get_option('paper_wp_effects_settings');
        
        // 如果有自定义页脚HTML,显示自定义内容;否则显示默认统计信息
        if (!empty($effects_settings['footer_html'])) {
            echo '<div class="custom-footer-html">' . wp_kses_post($effects_settings['footer_html']) . '</div>';
        } else {
            // 默认显示完整的统计信息
            paper_wp_render_footer_stats();
        }
        ?>
    </footer>
    <?php wp_footer(); ?>
</body>
</html>
