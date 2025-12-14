<?php

// --- Heatmap SVG Generation ---
function barepaper_generate_heatmap_svg($data) {
    $end_date = new DateTime();
    $start_date = (new DateTime())->modify('-1 year');
    $dates = new DatePeriod($start_date, new DateInterval('P1D'), $end_date);

    $svg = '<div class="post-heatmap"><svg width="800" height="141">';

    $day_cells = '';
    $month_labels = '';
    $last_month = -1;
    $week_x = 0;

    foreach ($dates as $date) {
        $day_of_week = $date->format('w');
        $day_of_month = $date->format('j');
        $month = $date->format('n');

        if ($day_of_week == 1 && $day_of_month <= 7) {
            if ($month != $last_month) {
                $month_labels .= '<text x="' . ($week_x * 13 + 60) . '" y="15" class="month-label">' . $date->format('M') . '</text>';
                $last_month = $month;
            }
        }

        $date_string = $date->format('Y-m-d');
        $count = isset($data[$date_string]) ? $data[$date_string] : 0;
        $level = 0;
        if ($count > 0) $level = 1;
        if ($count > 2) $level = 2;
        if ($count > 4) $level = 3;
        if ($count > 6) $level = 4;

        $day_cells .= '<rect x="' . ($week_x * 13 + 60) . '" y="' . ($day_of_week * 13 + 20) . '" width="11" height="11" class="day-cell post-heatmap-level-' . $level . '" rx="2" ry="2" fill="#ebedf0"><title>' . $date_string . ' - ' . $count . ' 篇文章</title></rect>';

        if ($day_of_week == 6) {
            $week_x++;
        }
    }

    $svg .= $month_labels;
    $svg .= '<text x="30" y="39.5" class="weekday-label" text-anchor="end">Mon</text>';
    $svg .= '<text x="30" y="65.5" class="weekday-label" text-anchor="end">Wed</text>';
    $svg .= '<text x="30" y="91.5" class="weekday-label" text-anchor="end">Fri</text>';
    $svg .= $day_cells;

    $svg .= '<text x="60" y="126" class="legend-text">Less</text>';
    $svg .= '<rect x="95" y="116" width="11" height="11" class="day-cell post-heatmap-level-0" rx="2" ry="2" fill="#ebedf0" />';
    $svg .= '<rect x="108" y="116" width="11" height="11" class="day-cell post-heatmap-level-1" rx="2" ry="2" fill="#c6e48b" />';
    $svg .= '<rect x="121" y="116" width="11" height="11" class="day-cell post-heatmap-level-2" rx="2" ry="2" fill="#7bc96f" />';
    $svg .= '<rect x="134" y="116" width="11" height="11" class="day-cell post-heatmap-level-3" rx="2" ry="2" fill="#239a3b" />';
    $svg .= '<rect x="147" y="116" width="11" height="11" class="day-cell post-heatmap-level-4" rx="2" ry="2" fill="#196127" />';
    $svg .= '<text x="160" y="126" class="legend-text">More</text>';

    $svg .= '</svg></div>';
    return $svg;
}

function barepaper_render_archive_stats() {
    $stats_cache_key = 'barepaper_archive_stats';
    $stats = get_transient($stats_cache_key);

    if (false === $stats) {
        // Calculate running days
        $first_post = get_posts(['numberposts' => 1, 'orderby' => 'date', 'order' => 'ASC', 'post_type' => 'post', 'post_status' => 'publish']);
        $blog_start_date = $first_post ? $first_post[0]->post_date : date('Y-m-d');
        $start_timestamp = strtotime($blog_start_date);
        $current_timestamp = current_time('timestamp');
        $running_days = floor(($current_timestamp - $start_timestamp) / (60 * 60 * 24));

        // Get total posts (including private)
        $count_posts = wp_count_posts();
        $total_posts = $count_posts->publish + $count_posts->private;

        // Get total word count
        $total_words_wan = number_format(paper_wp_get_total_word_count() / 10000, 2);

        $stats = [
            'running_days' => $running_days,
            'total_posts' => $total_posts,
            'total_words_wan' => $total_words_wan,
        ];

        set_transient($stats_cache_key, $stats, 6 * HOUR_IN_SECONDS);
    }

    echo '<h2 class="archive-stats-highlight">';
    echo '博客运行了 <span class="archive-stats-number">' . $stats['running_days'] . '</span>天';
    echo '，发布了 <span class="archive-stats-number">' . $stats['total_posts'] . '</span> 篇文章';
    echo '，持续输出 <span class="archive-stats-number">' . $stats['total_words_wan'] . '</span> 万字。';
    echo '</h2>';
}

// --- Heatmap Data Preparation ---
$heatmap_data = get_transient('paper_wp_heatmap_data');
if (false === $heatmap_data) {
    $heatmap_data = array();
    
    // 优化：直接使用 SQL 聚合查询，避免加载大量 Post ID
    global $wpdb;
    $results = $wpdb->get_results("
        SELECT DATE(post_date) as date, COUNT(ID) as count
        FROM {$wpdb->posts}
        WHERE post_type = 'post' AND (post_status = 'publish' OR post_status = 'private')
        GROUP BY DATE(post_date)
    ");
    
    if ($results) {
        foreach ($results as $row) {
            $heatmap_data[$row->date] = (int)$row->count;
        }
    }
    
    set_transient('paper_wp_heatmap_data', $heatmap_data, DAY_IN_SECONDS);
}
// --- End Heatmap Data ---

get_header();
?>

<div id="body">
    <div class="container">
        <div class="row">
            <div class="col-mb-12 col-8" id="main" role="main">
                <article class="post" itemscope itemtype="http://schema.org/BlogPosting">
                    <div class="post-content" itemprop="articleBody">

                        <div class="post-heatmap-container">
                            <?php echo barepaper_generate_heatmap_svg($heatmap_data); ?>
                        </div>

                        <?php barepaper_render_archive_stats(); ?>

                        <div id="archives">
                            <?php
                            $archives = get_transient('paper_wp_archive_list');
                            if (false === $archives) {
                                $archives = array();
                                
                                // 优化：直接查询数据库获取必要字段，避免加载完整文章对象
                                global $wpdb;
                                $posts = $wpdb->get_results("
                                    SELECT ID, post_date, post_title 
                                    FROM {$wpdb->posts} 
                                    WHERE post_type = 'post' AND post_status = 'publish' 
                                    ORDER BY post_date DESC
                                ");

                                if ($posts) {
                                    foreach ($posts as $post) {
                                        $date = date_create($post->post_date);
                                        $year = date_format($date, 'Y');
                                        $month = date_format($date, 'm');
                                        // 使用 get_permalink 获取链接，虽然有一定开销但保证准确性
                                        $archives[$year][$month][] = '<li><a href="' . get_permalink($post->ID) . '">' . get_the_title($post) . '</a></li>';
                                    }
                                }
                                
                                set_transient('paper_wp_archive_list', $archives, DAY_IN_SECONDS);
                            }

                            foreach ($archives as $year => $months) {
                                echo '<h3 class="year custom-container alert">' . $year . '</h3>';
                                echo '<ol class="month-list">';
                                krsort($months);
                                foreach ($months as $month => $posts) {
                                    echo '<li><h4 class="month">' . intval($month) . '月</h4>';
                                    echo '<ol class="post-list" reversed>';
                                    echo implode('', $posts);
                                    echo '</ol></li>';
                                }
                                echo '</ol>';
                            }
                            ?>
                        </div>
                    </div>
                </article>
                <!-- <div id="comments">
                    <h3 class="closerespond">- The End -</h3>
                </div> -->
            </div>

            <?php get_sidebar(); ?>

        </div><!-- end .row -->
    </div>
</div><!-- end #body -->

<?php get_footer(); ?>

<style>
    .post-heatmap-container {
        margin: 20px 0;
    }

    .post-heatmap {
        margin-top: 10px;
    }
    .post-heatmap svg {
        font-size: 12px;
    }

    .post-heatmap .month-label {
        fill: #586069;
        font-size: 10px;
    }

    .post-heatmap .weekday-label {
        fill: #586069;
        font-size: 9px;
    }

    .post-heatmap .day-cell {
        rx: 2;
        ry: 2;
    }

    .post-heatmap .day-cell:hover {
        stroke: rgba(27,31,35,0.1);
        stroke-width: 1px;
    }

    .post-heatmap .legend-text {
        fill: #586069;
        font-size: 9px;
    }

    /* 浅色主题热力图颜色 */
    .post-heatmap-level-0 { fill: #ebedf0; }
    .post-heatmap-level-1 { fill: #c6e48b; }
    .post-heatmap-level-2 { fill: #7bc96f; }
    .post-heatmap-level-3 { fill: #239a3b; }
    .post-heatmap-level-4 { fill: #196127; }

    /* 暗主题热力图适配 */
    [data-theme="dark"] .post-heatmap .month-label,
    [data-theme="dark"] .post-heatmap .weekday-label,
    [data-theme="dark"] .post-heatmap .legend-text {
        fill: #8b949e;
    }

    [data-theme="dark"] .post-heatmap .day-cell:hover {
        stroke: rgba(255,255,255,0.2);
    }

    [data-theme="dark"] .post-heatmap-level-0 { fill: #161b22; }
    [data-theme="dark"] .post-heatmap-level-1 { fill: #0e4429; }
    [data-theme="dark"] .post-heatmap-level-2 { fill: #006d32; }
    [data-theme="dark"] .post-heatmap-level-3 { fill: #26a641; }
    [data-theme="dark"] .post-heatmap-level-4 { fill: #39d353; }

    .archive-stats-number {
        font-weight: 700;
        color: var(--color-accent, #0073aa);
        margin: 0 0.2em;
    }

    .archive-stats-highlight {
        background: rgba(0,0,0,0.06);
        padding: 16px 20px;
        border-radius: 6px;
        margin: 20px 0;
        font-size: 0.95em;
        line-height: 1.6;
        text-align: center;
        color: var(--color-text, #333);
    }

    /* 暗主题下的统计高亮 */
    [data-theme="dark"] .archive-stats-highlight {
        background: rgba(255,255,255,0.05);
    }

    /* 年份标题警示框样式 */
    h3.year.custom-container.alert {
        margin: 20px 0 !important;
        padding: 8px 15px !important;
        border-radius: 6px !important;
        border-left: 3px solid #faad14 !important;
        background: linear-gradient(135deg, #fffbe6 0%, #ffffff 100%) !important;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08) !important;
        font-size: 1.2em !important;
        font-weight: 600 !important;
        color: var(--color-text) !important;
        display: inline-block !important;
        width: auto !important;
        max-width: 200px !important;
    }

    /* 暗主题下的年份标题 */
    [data-theme="dark"] h3.year.custom-container.alert {
        background: linear-gradient(135deg, #2d2416 0%, #1a1a1a 100%) !important;
        border-left-color: #d48806 !important;
    }


</style>
