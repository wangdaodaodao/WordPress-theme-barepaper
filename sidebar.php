<?php
/**
 * 侧边栏排行榜小工具渲染函数
 * 
 * 通用的排行榜渲染函数,支持不同类型的文章排行
 * 包含自动缓存机制,提升性能
 * 
 * @param string $title 小工具标题
 * @param array $query_args WP_Query 查询参数
 * @param string $cache_key 缓存键名
 * @param callable $display_count_callback 显示计数的回调函数
 * @param string $no_data_text 无数据时的提示文本
 * @param int $title_length 标题长度限制
 * @param bool $use_css_truncation 是否使用CSS截断
 * @param bool $force_refresh 是否强制刷新缓存
 * @param int $cache_duration 缓存时长(秒)
 */
function paper_wp_render_ranked_posts_widget($title, $query_args, $cache_key, $display_count_callback, $no_data_text = '暂无数据', $title_length = 16, $use_css_truncation = true, $force_refresh = false, $cache_duration = HOUR_IN_SECONDS) {
    ?>
    <section class="widget">
        <h3 class="widget-title"><?php echo esc_html($title); ?></h3>
        <ul class="widget-list">
            <?php
            $posts_html = get_transient($cache_key);
            if (false === $posts_html || !is_string($posts_html) || $force_refresh) {
                // 清除可能损坏的缓存或强制刷新
                if (!is_string($posts_html) || $force_refresh) {
                    delete_transient($cache_key);
                }
                ob_start();
                // 确保$query_args是有效的数组
                if (!is_array($query_args)) {
                    $query_args = [];
                }
                $query = new WP_Query($query_args);
                if ($query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        $post_title = get_the_title();
                        // 根据参数决定是否使用PHP截断还是CSS截断
                        if (!$use_css_truncation && $title_length > 0) {
                            $post_title = mb_strimwidth($post_title, 0, $title_length, '...');
                        }
                        $count_html = call_user_func($display_count_callback, get_the_ID());
                        ?>
                        <li class="widget-list-item">
                            <a href="<?php the_permalink(); ?>" title="<?php echo esc_attr(get_the_title()); ?>">
                                <span class="post-title"><?php echo esc_html($post_title); ?></span>
                                <?php if (!empty($count_html)): ?>
                                    <span class="post-count">(<?php echo $count_html; ?>)</span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php
                    }
                    wp_reset_postdata();
                } else {
                    echo '<li class="widget-no-data">' . esc_html($no_data_text) . '</li>';
                }
                $posts_html = ob_get_clean();
                set_transient($cache_key, $posts_html, $cache_duration);
            }
            echo $posts_html;
            ?>
        </ul>
    </section>
    <?php
}

$paper_wp_settings = get_option('paper_wp_theme_settings');
?>
<div class="col-mb-12 col-offset-1 col-3 kit-hidden-tb" id="secondary" role="complementary">

    <?php if (!empty($paper_wp_settings['show_search'])) : ?>
    <section class="widget">
        <h3 class="widget-title">搜索</h3>
        <form id="search" method="get" action="<?php echo home_url('/'); ?>" role="search">
            <label for="s" class="sr-only">搜索关键字</label>
            <input type="text" id="s" name="s" class="text" placeholder="输入关键字搜索" value="<?php the_search_query(); ?>" />
            <button type="submit" class="submit">
                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </button>
        </form>
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
        paper_wp_render_ranked_posts_widget('推荐文章',
            array(
                'posts_per_page' => 10,
                'meta_key' => '_paper_wp_recommended',
                'meta_value' => '1',
                'orderby' => 'rand',
                'ignore_sticky_posts' => true
            ),
            'paper_wp_recommended_posts_cache',
            function($id) { return ''; }, // 推荐文章不显示计数
            '暂无推荐文章',
            18, // 推荐文章标题长度（当不使用CSS截断时的最大长度）
            true // 使用CSS自适应截断
        );
    }

    // 最近文章相册
    if (!empty($paper_wp_settings['show_recent_album'])) {
        $album_html = get_transient('paper_wp_sidebar_recent_album');
        if ($album_html === false) {
            // 查询所有文章，按发布时间倒序排列
            $recent_album_query = new WP_Query([
                'posts_per_page' => 20, // 查询更多文章以确保能找到足够的有图片文章
                'orderby' => 'date',
                'order' => 'DESC',
                'ignore_sticky_posts' => true,
                'post_status' => 'publish'
            ]);

            $album_posts = [];

            if ($recent_album_query->have_posts()) {
                while ($recent_album_query->have_posts()) {
                    $recent_album_query->the_post();

                    // 检查文章内容中是否包含图片
                    $post_content = get_the_content();
                    $has_image = preg_match('/<img[^>]+>|!\[.*?\]\(.*?\)|\[fullimage[^\]]*\]/i', $post_content);

                    if ($has_image) {
                        // 从文章内容中提取第一张图片作为封面
                        $cover_image = '';
                        $patterns = [
                            '/<img[^>]+src="([^"]+)"[^>]*>/i',
                            '/!\[.*?\]\((.*?)\)/',
                            '/\[fullimage[^\]]*src="([^"]+)"[^\]]*\]/i',
                            '/\[fullimage[^\]]*\](.*?)\|.*?\[\/fullimage\]/i'
                        ];
                        
                        foreach ($patterns as $pattern) {
                            if (preg_match($pattern, $post_content, $matches)) {
                                $cover_image = trim($matches[1] ?? '');
                                if (!empty($cover_image)) break;
                            }
                        }

                        // 如果提取到有效的图片URL，添加到图集
                        if (!empty($cover_image)) {
                            // URL验证和代理处理
                            $is_valid_url = filter_var($cover_image, FILTER_VALIDATE_URL);
                            $is_relative_path = strpos($cover_image, '/') === 0;
                            $is_data_uri = strpos($cover_image, 'data:') === 0;

                            if ($is_valid_url || $is_relative_path || $is_data_uri) {
                                if ($is_relative_path) {
                                    $cover_image = home_url($cover_image);
                                }

                                // 直接使用原始图片URL，不使用代理
                            }

                            $album_posts[] = [
                                'title' => get_the_title(),
                                'permalink' => get_permalink(),
                                'cover' => $cover_image
                            ];
                        }

                        // 找到7篇有图片的文章后停止
                        if (count($album_posts) >= 8) {
                            break;
                        }
                    }
                }
                wp_reset_postdata();
            } else {
                wp_reset_postdata();
            }

            if (!empty($album_posts)) {
                ob_start();
                ?>
                <section class="widget widget-recent-album" aria-label="最新文章图片轮播">
                    <h3 class="widget-title">精选图文</h3>
                    <div class="sidebar-album" data-autoplay="true" data-interval="2500">
                        <div class="sidebar-album__viewport" role="list">
                            <?php foreach ($album_posts as $album_post) : ?>
                                <article class="sidebar-album__item" role="listitem">
                                    <a href="<?php echo esc_url($album_post['permalink']); ?>" class="sidebar-album__link" aria-label="<?php echo esc_attr($album_post['title']); ?>">
                                        <div class="sidebar-album__image-wrapper">
                                            <img src="<?php echo esc_url($album_post['cover']); ?>" alt="<?php echo esc_attr($album_post['title']); ?>" loading="lazy">
                                            <span class="sidebar-album__title" title="<?php echo esc_attr($album_post['title']); ?>"><?php echo esc_html($album_post['title']); ?></span>
                                        </div>
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($album_posts) > 1) : ?>
                        <div class="sidebar-album__controls">
                            <button type="button" class="sidebar-album__nav sidebar-album__nav--prev" aria-label="上一张">
                                <span aria-hidden="true">‹</span>
                            </button>
                            <div class="sidebar-album__indicators" role="tablist" aria-label="轮播导航"></div>
                            <button type="button" class="sidebar-album__nav sidebar-album__nav--next" aria-label="下一张">
                                <span aria-hidden="true">›</span>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
                <?php
                $album_html = ob_get_clean();
            } else {
                $album_html = '';
            }

            if (!empty($album_html)) {
                set_transient('paper_wp_sidebar_recent_album', $album_html, 12 * HOUR_IN_SECONDS);
            }
        }
        echo $album_html;
    }

    // 阅读排行
    if (!empty($paper_wp_settings['show_reading_ranking'])) {
        paper_wp_render_ranked_posts_widget('阅读排行',
            array('posts_per_page' => 10, 'meta_key' => 'post_views_count', 'orderby' => 'meta_value_num', 'order' => 'DESC', 'ignore_sticky_posts' => 1),
            'paper_wp_reading_ranking',
            function($id) {
                $count = get_post_meta($id, 'post_views_count', true) ?: 0;
                return paper_wp_format_number($count);
            },
            '暂无排行数据',
            16, // 标题长度（当不使用CSS截断时的最大长度）
            true // 使用CSS自适应截断
        );
    }

    // 点赞排行
    if (!empty($paper_wp_settings['show_like_ranking'])) {
        paper_wp_render_ranked_posts_widget('点赞排行',
            array('posts_per_page' => 10, 'meta_key' => '_post_recommend_count', 'orderby' => 'meta_value_num', 'order' => 'DESC', 'ignore_sticky_posts' => true),
            'paper_wp_like_ranking',
            function($id) {
                $count = get_post_meta($id, '_post_recommend_count', true) ?: 0;
                return paper_wp_format_number($count);
            },
            '暂无推荐文章',
            16, // 标题长度（当不使用CSS截断时的最大长度）
            true // 使用CSS自适应截断
        );
    }

    // 评论排行 (已优化查询)
    if (!empty($paper_wp_settings['show_comment_ranking'])) {
        // 先获取所有有评论的文章ID
        global $wpdb;
        $commented_post_ids = $wpdb->get_col("
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'post'
            AND post_status = 'publish'
            AND comment_count > 0
            ORDER BY comment_count DESC
            LIMIT 10
        ");

        if (!empty($commented_post_ids)) {
            paper_wp_render_ranked_posts_widget('评论排行',
                array('post__in' => $commented_post_ids, 'orderby' => 'comment_count', 'order' => 'DESC', 'ignore_sticky_posts' => true),
                'paper_wp_comment_ranking',
                function($id) {
                    $count = get_comments_number($id);
                    return paper_wp_format_number($count);
                },
                '暂无评论文章',
                16, // 标题长度（当不使用CSS截断时的最大长度）
                true // 使用CSS自适应截断
            );
        }
    }
    ?>

    <?php if (!empty($paper_wp_settings['show_tag_cloud'])) : ?>
    <section class="widget">
        <h3 class="widget-title">标签</h3>
        <div class="tagcloud">
            <?php
            $tags = paper_wp_get_cached_tags();
            if (!empty($tags) && !is_wp_error($tags)) {
                echo '<ul class="widget-list">';
                foreach ($tags as $tag) {
                    echo '<li><a href="' . esc_url(get_term_link($tag)) . '">' . esc_html($tag->name) .'('. $tag->count .')</a></li>';
                }
                echo '</ul>';
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
            $friend_links = get_option('paper_wp_friend_links', []);
            if (!empty($friend_links)) {
                echo '<ul class="widget-list friend-links-list">';
                foreach ($friend_links as $link) {
                    $description = !empty($link['description']) ? ' title="' . esc_attr($link['description']) . '"' : '';
                    echo '<li class="friend-link-item"><a href="' . esc_url($link['url']) . '"' . $description . ' target="_blank" rel="noopener noreferrer">' . esc_html($link['name']) . '&nbsp&nbsp&nbsp</a></li>';
                }
                echo '</ul>';
            } else {
                echo '<span class="widget-no-data">暂无友情链接。</span>';
            }
            ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($paper_wp_settings['show_sidebar_links'])) : ?>
    <section class="widget widget-sidebar-links">
        <h3 class="widget-title">其他</h3>
        <ul class="widget-list sidebar-links-list">
        <?php if (!is_user_logged_in()) : ?>
            <li class="sidebar-link-item">
                <?php
                // 在首页、存档页等列表页面时，重定向到首页；在文章页面时重定向回当前文章
                $redirect_url = (is_home() || is_archive() || is_search()) ? home_url() : get_permalink();
                ?>
                <a href="<?php echo esc_url(wp_login_url($redirect_url)); ?>" class="sidebar-link login-link">
                    登录
                </a>
            </li>
        <?php else : ?>
            <li class="sidebar-link-item">
                <?php
                // 在首页、存档页等列表页面时，重定向到首页；在文章页面时重定向回当前文章
                $redirect_url = (is_home() || is_archive() || is_search()) ? home_url() : get_permalink();
                ?>
                <a href="<?php echo esc_url(wp_logout_url($redirect_url)); ?>" class="sidebar-link logout-link">
                    退出
                </a>
            </li>
        <?php endif; ?>

            <li class="sidebar-link-item">
                <a href="<?php echo esc_url(get_feed_link()); ?>" class="sidebar-link rss-link" target="_blank" rel="noopener noreferrer">
                    文章 RSS
                </a>
            </li>

            <li class="sidebar-link-item">
                <a href="<?php echo esc_url(paper_wp_get_sitemap_url()); ?>" class="sidebar-link sitemap-link" target="_blank" rel="noopener noreferrer">
                    站点地图
                </a>
            </li>
        </ul>
    </section>
    <?php endif; ?>

    <?php
    $paper_wp_ad_settings = get_option('paper_wp_ad_settings');
    if (!empty($paper_wp_ad_settings['show_sidebar_ad']) && !empty($paper_wp_ad_settings['sidebar_ad_code'])) : ?>
    <section class="widget">
        <div class="widget-space">
            <?php echo wp_kses_post($paper_wp_ad_settings['sidebar_ad_code']); ?>
        </div>
    </section>
    <?php endif; ?>

</div>
