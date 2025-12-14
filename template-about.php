<?php
/*
 * barepaper WordPress 主题关于页面模板
 * 显示关于页面的内容
 *
 * 相关样式文件: assets/css/post.css (文章内容样式)
 *
 * @author wangdaodao
 * @version 1.0.4
 * @date 2025-10-16
 */
/*
Template Name: About
*/
?>
<?php get_header(); ?>

<div class="col-mb-12 col-8" id="main" role="main">
    <?php while ( have_posts() ) : the_post(); ?>
        <article class="post">
            <!-- <h1 class="post-title"><?php the_title(); ?></h1> -->
            <div class="post-content">
                <?php the_content(); ?>
            </div>
        </article>
    <?php endwhile; ?>
</div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
