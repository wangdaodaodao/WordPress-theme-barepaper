<?php
/*
 * barepaper WordPress 主题评论文件
 * 处理文章评论的显示和提交表单
 *
 * 相关样式文件: assets/css/comments.css (评论样式)
 *
 * @author wangdaodao
 * @version 1.0.4
 * @date 2025-10-16
 */
?>
<?php if (comments_open() || get_comments_number()) : ?>
<div class="comments-area">
    <?php if (have_comments()) : ?>
        <h3 class="comments-title"><?php printf(_n('%s 条评论', '%s 条评论', get_comments_number(), 'paper-wp'), number_format_i18n(get_comments_number())); ?></h3>
        <ol class="comment-list">
            <?php wp_list_comments(array('walker' => new Paper_WP_Walker_Comment())); ?>
        </ol>
        <?php paginate_comments_links(); ?>
    <?php endif; ?>

    <?php
    $commenter = wp_get_current_commenter();
    $req = get_option( 'require_name_email' );
    $aria_req = ( $req ? " aria-required='true'" : '' );

    $fields =  array(
        'author' =>
            '<div class="comment-form-author-email" style="display: flex; gap: 20px; flex-wrap: wrap;">' .
            '<p class="comment-form-author" style="display: flex; align-items: center; flex-wrap: wrap; margin: 0;">' .
            '<label for="author" style="margin-right: 10px;">' . '称呼' . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
            '<input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" size="30"' . $aria_req . ' required="required" style="flex-grow: 1; max-width: 200px;" />' .
            '</p>' .

            '<p class="comment-form-email" style="display: flex; align-items: center; flex-wrap: wrap; margin: 0;">' .
            '<label for="email" style="margin-right: 10px;">' . '邮箱' . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
            '<input id="email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" size="30"' . $aria_req . ' required="required" style="flex-grow: 1; max-width: 200px;" />' .
            '</p>' .
            '</div>',

        'cookies' => '',
    );

    $comment_form_args = array(
        'fields'               => $fields,
        'comment_field'        => '<p class="comment-form-comment"><label for="comment" style="display: inline-block; margin-right: 20px;">' . '内容' . '</label><span class="comment-form-cookies-consent" style="display: inline-flex; align-items: center; float: right;"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes" style="margin-right: 5px;" /><label for="wp-comment-cookies-consent" style="font-size: 13px; color: #666;">记住我</label></span><br><textarea id="comment" name="comment" cols="45" rows="8" required="required"></textarea></p>',
        'title_reply'          => '添加新评论',
        'title_reply_before'   => '<h3 id="reply-title" class="widget-title">',
        'title_reply_after'    => '</h3>',
        'title_reply_to'       => '回复给 %s',
        'cancel_reply_link'    => '取消回复',
        'label_submit'         => '提交评论',
        'submit_button'        => '<button type="submit" class="submit">%4$s</button>',
        'comment_notes_before' => '',
        'comment_notes_after'  => '',
        'id_form'              => 'comment-form',
        'class_form'           => 'comment-form',
        'must_log_in'          => '',
        'logged_in_as'         => '',
        'cookies'              => '',
    );

    comment_form($comment_form_args);
    ?>
</div>
<?php endif; ?>
