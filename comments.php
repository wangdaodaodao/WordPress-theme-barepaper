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

// 确保 Walker 类被加载
require_once get_template_directory() . '/features/post-enhancements.php';
?>
<?php if (comments_open() || get_comments_number()) : ?>
<div id="comments" class="comments-area">
    <?php
    $commenter = wp_get_current_commenter();
    // $req = get_option( 'require_name_email' ); // 原逻辑：跟随后台设置
    $req = false; // 现逻辑：代码强制控制。true=必填，false=选填（此处设为 false，即都不必填）
    $aria_req = ( $req ? " aria-required='true'" : '' );
    $user = wp_get_current_user();
    $user_identity = $user->exists() ? $user->display_name : '';

    // 如果已登录，不显示称呼和邮箱字段
    if ( $user_identity ) {
        $fields = array();
        $comment_field = 
            '<p class="comment-form-comment">' .
            '<label for="comment">' . '内容' . '</label>' .
            '<textarea id="comment" name="comment" cols="45" rows="8" required="required"></textarea>' .
            '</p>';
        // 已登录用户不需要"保存信息"复选框
        $submit_button = '<button type="submit" class="submit">%4$s</button>';
    } else {
        // 未登录时显示称呼和邮箱
        $fields = array();
        $comment_field = 
            '<p class="comment-form-meta-row">' .
            '<label for="author">称呼(必填) <span class="required">*</span></label>' .
            '<input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" size="30" required="required" />' .
            '<label for="url">网址(选填，点击头像可访问)</label>' .
            '<input id="url" name="url" type="text" value="' . esc_attr( $commenter['comment_author_url'] ) . '" size="30" />' .
            '</p>' .
            '<p class="comment-form-comment">' .
            '<label for="comment">' . '内容' . '</label>' .
            '<textarea id="comment" name="comment" cols="45" rows="8" required="required"></textarea>' .
            '</p>';
        // 未登录用户显示"保存信息"复选框
        $submit_button = 
            '<p class="form-submit-row">' .
            '<span class="comment-form-cookies-consent">' .
            '<input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes" />' .
            '<label for="wp-comment-cookies-consent">保存我的信息以便下次评论</label>' .
            '</span>' .
            '<button type="submit" class="submit">%4$s</button>' .
            '</p>';
    }

    $comment_form_args = array(
        'fields'               => $fields,
        'comment_field'        => $comment_field,
        'title_reply'          => '添加新评论',
        'title_reply_before'   => '<h3 id="reply-title" class="widget-title">',
        'title_reply_after'    => '</h3>',
        'title_reply_to'       => '回复给 %s',
        'cancel_reply_link'    => '取消回复',
        'label_submit'         => '提交评论',
        'submit_button'        => $submit_button,
        'comment_notes_before' => '',
        'comment_notes_after'  => '',
        'id_form'              => 'comment-form',
        'class_form'           => 'comment-form',
        'must_log_in'          => '',
        'logged_in_as'         => '',
        'cookies'              => '',  // 禁用默认的 cookies 同意字段
    );

    comment_form($comment_form_args);
    ?>


    <?php if (have_comments()) : ?>
        <?php
        $comment_count = get_comments_number();
        $show_toggle = $comment_count > 0; // 只要有评论就显示展开按钮
        ?>
        
        <?php if ($show_toggle) : ?>
        <h3 class="comments-title-toggle">
            <span class="comments-count"><?php printf(_n('%s 条评论', '%s 条评论', $comment_count, 'paper-wp'), number_format_i18n($comment_count)); ?></span>
            <button class="comments-toggle-btn-inline" id="commentsToggleBtn">
                <span class="toggle-text-more">(展开)</span>
                <span class="toggle-text-less" style="display: none;">(收起)</span>
            </button>
        </h3>
        <?php else : ?>
        <h3 class="comments-title"><?php printf(_n('%s 条评论', '%s 条评论', get_comments_number(), 'paper-wp'), number_format_i18n(get_comments_number())); ?></h3>
        <?php endif; ?>
        
        <ol class="comment-list <?php echo $show_toggle ? 'comments-collapsed' : ''; ?>" id="commentList">
            <?php wp_list_comments(array(
                'walker' => new Paper_WP_Walker_Comment(),
                'max_depth' => 5,
                'avatar_size' => 32
            )); ?>
        </ol>
        <?php paginate_comments_links(); ?>
        
        <?php if ($show_toggle) : ?>
        <?php endif; ?>
    <?php endif; ?>

    <script>
    (function() {
        // 评论列表展开/收起逻辑
        const toggleBtn = document.getElementById('commentsToggleBtn');
        const commentList = document.getElementById('commentList');
        
        if (toggleBtn && commentList) {
            const textMore = toggleBtn.querySelector('.toggle-text-more');
            const textLess = toggleBtn.querySelector('.toggle-text-less');
            
            toggleBtn.addEventListener('click', function() {
                commentList.classList.toggle('comments-collapsed');
                updateButtonState();
            });

            function updateButtonState() {
                if (commentList.classList.contains('comments-collapsed')) {
                    if(textMore) textMore.style.display = '';
                    if(textLess) textLess.style.display = 'none';
                } else {
                    if(textMore) textMore.style.display = 'none';
                    if(textLess) textLess.style.display = '';
                }
            }
            
            // 初始化状态
            updateButtonState();
        }

        // 点击元数据评论链接时，自动展开评论并滚动
        function handleCommentLinkClick() {
            // 查找所有指向评论区的链接
            const commentLinks = document.querySelectorAll('a[href*="#comments"], a[itemprop="discussionUrl"]');
            
            commentLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    
                    // 如果是指向评论区的链接
                    if (href && (href.includes('#comments') || href.includes('#respond'))) {
                        e.preventDefault();
                        
                        // 自动展开评论列表
                        if (commentList && commentList.classList.contains('comments-collapsed')) {
                            commentList.classList.remove('comments-collapsed');
                            if (typeof updateButtonState === 'function') {
                                updateButtonState();
                            }
                        }
                        
                        // 滚动到评论区域
                        setTimeout(function() {
                            const commentsArea = document.getElementById('comments');
                            if (commentsArea) {
                                const offset = 100;
                                const elementPosition = commentsArea.getBoundingClientRect().top;
                                const offsetPosition = elementPosition + window.pageYOffset - offset;
                                window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
                            }
                        }, 100);
                    }
                });
            });
        }
        
        // 页面加载完成后绑定事件
        handleCommentLinkClick();

        // 自动展开和滚动逻辑（针对 URL hash）
        // 支持 #comments、#respond 或 #comment-123 等
        if (window.location.hash) {
            const hash = window.location.hash;
            const shouldExpandComments = hash.indexOf('comment') !== -1 || hash === '#comments' || hash === '#respond';
            
            if (shouldExpandComments) {
                // 自动展开评论列表
                if (commentList && commentList.classList.contains('comments-collapsed')) {
                    commentList.classList.remove('comments-collapsed');
                    if (typeof updateButtonState === 'function') updateButtonState();
                }

                // 滚动到目标位置
                setTimeout(function() {
                    let target;
                    
                    // 优先查找具体的评论或评论区域
                    if (hash === '#comments' || hash === '#respond') {
                        target = document.getElementById('comments');
                    } else {
                        target = document.querySelector(hash);
                    }
                    
                    if (target) {
                        var offset = 100;
                        var elementPosition = target.getBoundingClientRect().top;
                        var offsetPosition = elementPosition + window.pageYOffset - offset;
                        window.scrollTo({ top: offsetPosition, behavior: "smooth" });
                    }
                }, 500);
            }
        }
    })();
    
    </script>
    <?php endif; ?>

</div>
