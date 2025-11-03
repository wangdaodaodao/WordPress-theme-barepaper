<?php
$paper_wp_settings = get_option('paper_wp_theme_settings');
?>
<div class="col-mb-12 col-offset-1 col-3 kit-hidden-tb" id="secondary" role="complementary">

    <?php if (!empty($paper_wp_settings['show_search'])) : ?>
    <section class="widget">
        <h3 class="widget-title">搜索</h3>
        <?php get_search_form(); ?>
    </section>
    <?php endif; ?>

    <?php
    // 最新文章
    if (!empty($paper_wp_settings['show_random_posts'])) {
        paper_wp_render_ranked_posts_widget('最新文章',
            ['posts_per_page' => 10, 'orderby' => 'date', 'order' => 'DESC', 'ignore_sticky_posts' => true],
            'paper_wp_latest_posts_cache',
            fn($id) => '', // 最新文章不显示计数
            '暂无文章',
            18, // 最新文章标题长度（当不使用CSS截断时的最大长度）
            true // 使用CSS自适应截断
        );
    }

    // 推荐文章
    if (!empty($paper_wp_settings['show_recommended_posts'])) {
        // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
        // paper_wp_render_ranked_posts_widget('推荐文章', ...);
    }

    // 最近文章相册
    if (!empty($paper_wp_settings['show_recent_album'])) {
        // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
        // $album_html = get_transient('paper_wp_sidebar_recent_album');
        // if ($album_html === false) { ... }
    }

    // 阅读排行
    if (!empty($paper_wp_settings['show_reading_ranking'])) {
        // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
        // paper_wp_render_ranked_posts_widget('阅读排行', ...);
    }

    // 点赞排行
    if (!empty($paper_wp_settings['show_like_ranking'])) {
        // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
        // paper_wp_render_ranked_posts_widget('点赞排行', ...);
    }

    // 评论排行 (已优化查询)
    if (!empty($paper_wp_settings['show_comment_ranking'])) {
        // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
        // global $wpdb;
        // $commented_post_ids = $wpdb->get_col("...");
        // paper_wp_render_ranked_posts_widget('评论排行', ...);
    }
    ?>

    <?php if (!empty($paper_wp_settings['show_tag_cloud'])) : ?>
    <section class="widget">
        <h3 class="widget-title">标签</h3>
        <div class="tagcloud">
            <?php
            $tags = paper_wp_get_cached_tags();
            if (!empty($tags) && !is_wp_error($tags)) {
                echo '<ol class="widget-list tag-cloud-list">';
                foreach ($tags as $tag) {
                    echo '<li class="tag-cloud-item"><a class="tag-cloud-link" href="' . esc_url(get_term_link($tag)) . '">' . esc_html($tag->name) . ' (' . $tag->count . ')&nbsp&nbsp&nbsp</a></li>';
                }
                echo '</ol>';
            } else {
                echo '<span class="widget-no-data">暂无标签。</span>';
            }
            ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($paper_wp_settings['show_categories'])) : ?>
    <section class="widget">
        <h3 class="widget-title">分类</h3>
        <ul class="widget-list">
            <?php
            $categories = paper_wp_get_cached_categories();
            if (!empty($categories)) {
                foreach ($categories as $category) {
                    echo '<li><a href="' . esc_url(get_category_link($category->term_id)) . '">' . esc_html($category->name) . ' (' . $category->count . ')</a></li>';
                }
            }
            ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($paper_wp_settings['show_archives'])) : ?>
    <section class="widget">
        <h3 class="widget-title">归档</h3>
        <ul class="widget-list">
            <?php
            $archives = paper_wp_get_cached_archives();
            echo $archives;
            ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($paper_wp_settings['show_friend_links'])) : ?>
    <section class="widget widget-friend-links">
        <h3 class="widget-title">友情链接</h3>
        <div class="friend-links-container">
            <?php
            // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
            // $friend_links = get_option('paper_wp_friend_links', []);
            // if (!empty($friend_links)) { ... }
            ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($paper_wp_settings['show_sidebar_links'])) : ?>
    <section class="widget widget-sidebar-links">
        <h3 class="widget-title">其他</h3>
        <ul class="widget-list sidebar-links-list">
            <?php
            // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
            // if (!is_user_logged_in()) { ... }
            // get_feed_link(); paper_wp_get_sitemap_url();
            ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php
    // 功能已禁用 - 具体逻辑已移除，保留函数调用结构
    // $paper_wp_ad_settings = get_option('paper_wp_ad_settings');
    // if (!empty($paper_wp_ad_settings['show_sidebar_ad']) && !empty($paper_wp_ad_settings['sidebar_ad_code'])) : ...
    // <?php endif; ?>

</div><!-- end #sidebar -->
