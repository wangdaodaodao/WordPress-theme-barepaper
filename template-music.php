<?php
/*
 * barepaper WordPress 主题音乐页面模板
 * 专门用于展示音乐播放器的页面模板
 *
 * 支持在页面内容中使用 [music] 短代码播放各种音乐
 * 包括自定义音乐文件和平台音乐（网易云、QQ音乐等）
 *
 * @author wangdaodao
 * @version 1.0.0
 * @date 2025-10-22
 */
/*
Template Name: Music
*/

// 在音乐页面模板中直接加载音乐统计脚本
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('aplayer-js', get_template_directory_uri() . '/js/APlayer.min.js', ['jquery'], BAREPAPER_VERSION, true);
    wp_enqueue_script('meting-js', get_template_directory_uri() . '/js/Meting.min.js', ['aplayer-js'], BAREPAPER_VERSION, true);
    wp_enqueue_script('music-stats-tracker', get_template_directory_uri() . '/js/music-stats-tracker.js', ['jquery', 'aplayer-js'], BAREPAPER_VERSION, true);
    wp_localize_script('music-stats-tracker', 'musicStatsData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('music_stats_nonce')
    ]);
});

get_header();
?>

<div class="col-mb-12 col-8" id="main" role="main">
    <?php while ( have_posts() ) : the_post(); ?>
        <article class="post" itemscope itemtype="http://schema.org/BlogPosting">
            <h1 class="post-title" itemprop="name headline">
                <?php the_title(); ?>
            </h1>



            <div class="post-content music-content" itemprop="articleBody">
                <?php
                // 显示页面内容（包含短代码处理）
                the_content();
                ?>
            </div>

            <?php
            // 显示音乐播放统计
            if (class_exists('PaperMusicStats')) {
                $stats = PaperMusicStats::get_instance();
                echo $stats->render_stats_panel();
            }
            ?>



            
        </article>
    <?php endwhile; ?>
</div><!-- end #main-->

<style>
/* 音乐页面专用样式 */
.music-content {
    margin: 30px 0;
}

.music-tip {
    color: #e74c3c !important;
    font-weight: 500;
}

/* 音乐播放器容器优化 */
.music-content .aplayer-container {
    margin: 25px 0;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}
</style>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
