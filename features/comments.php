<?php
if (!defined('ABSPATH')) exit;

/**
 * 自定义评论显示类
 */
class Paper_WP_Walker_Comment extends Walker_Comment {
    public function start_lvl(&$output, $depth = 0, $args = array()) {
        $GLOBALS['comment_depth'] = $depth + 1;
        $output .= '<div class="comment-children"><ol class="comment-list">';
    }

    public function end_lvl(&$output, $depth = 0, $args = array()) {
        $GLOBALS['comment_depth'] = $depth + 1;
        $output .= '</ol></div>';
    }

    public function start_el(&$output, $comment, $depth = 0, $args = array(), $id = 0) {
        $depth++;
        $GLOBALS['comment_depth'] = $depth;
        $GLOBALS['comment'] = $comment;

        $tag = 'li';
        $add_below = 'comment';
        $classes = ['comment-body'];

        if (!empty($args['has_children'])) $classes[] = 'comment-parent';
        if ($comment->comment_parent > 0) $classes[] = 'comment-child';
        if (get_comment_ID() % 2) $classes[] = 'comment-odd';
        else $classes[] = 'comment-even';

        $output .= '<' . $tag . ' id="li-comment-' . get_comment_ID() . '" class="' . implode(' ', $classes) . '">';
        $output .= '<div id="comment-' . get_comment_ID() . '" class="comment-item">';

        // 评论作者信息
        $output .= '<div class="comment-author">';
        if ($args['avatar_size'] != 0) $output .= get_avatar($comment, $args['avatar_size']);
        $output .= '<span class="fn">' . get_comment_author_link();
        if ($comment->user_id === $comment->post_author_id) $output .= ' <span class="author-after-text">[作者]</span>';
        $output .= '</span></div>';

        // 评论元信息
        $output .= '<div class="comment-meta"><a href="' . esc_url(get_comment_link($comment->comment_ID)) . '">' . get_comment_date() . ' ' . get_comment_time() . '</a></div>';

        // 回复链接
        $reply_link = get_comment_reply_link(array_merge($args, ['add_below' => $add_below, 'depth' => $depth, 'max_depth' => $args['max_depth']]));
        $output .= '<span class="comment-reply">' . $reply_link . '</span>';

        // 评论内容
        $output .= '<div class="comment-content">';
        if ('0' == $comment->comment_approved) $output .= '<p><em>您的评论正在等待审核。</em></p>';
        $output .= get_comment_text();
        $output .= '</div>';
    }

    public function end_el(&$output, $comment, $depth = 0, $args = array()) {
        $output .= "</div></li>\n";
    }
}
