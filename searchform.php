<?php
/*
 * barepaper WordPress 主题搜索表单文件
 * 提供网站搜索功能表单
 *
 * @author wangdaodao
 * @version 1.0.4
 * @date 2025-10-16
 */
?>
<?php
/**
 * 自定义搜索表单模板
 */
?>
<form id="search" method="get" action="<?php echo home_url('/'); ?>" role="search">
    <label for="s" class="sr-only">搜索关键字</label>
    <input type="text" id="s" name="s" class="text" placeholder="输入关键字搜索" value="<?php the_search_query(); ?>" />
    <button type="submit" class="submit">
        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    </button>
</form>
