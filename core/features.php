<?php
return [
    'rss' => [
        'enabled' => true,
        'file' => 'features/rss.php',
        'description' => 'RSS Feed优化：解决中文编码问题，确保UTF-8正确显示',
        'load_in_admin' => false,    // RSS功能主要用于前端feed，不需要在后台加载
        'load_in_frontend' => true   // 前端feed页面需要RSS编码修复
    ],
    'performance' => [
        'enabled' => true,
        'file' => 'features/performance.php',
        'description' => '性能优化：缓存、资源提示、数据库优化等',
        'load_in_admin' => false,    // 性能优化主要用于前端
        'load_in_frontend' => true   // 前端页面需要性能优化
    ],

    'stats' => [
        'enabled' => true,
        'file' => 'features/stats.php',
        'description' => '博客统计功能：显示运行时间、文章篇数、总字数等',
        'load_in_admin' => true,     // 后台需要测试页面和管理功能
        'load_in_frontend' => true   // 前端侧边栏需要显示统计信息
    ],
    'image' => [
        'enabled' => true,
        'file' => 'features/image.php',
        'description' => '图片处理功能：智能尺寸容器、响应式图片、灯箱功能',
        'load_in_admin' => false,    // 图片处理主要用于前端显示
        'load_in_frontend' => true   // 前端文章页面需要图片处理功能
    ],

    'comments' => [
        'enabled' => true,
        'file' => 'features/comments.php',
        'description' => '评论系统定制：自定义评论显示、层级结构',
        'load_in_admin' => false,    // 评论功能主要用于前端显示
        'load_in_frontend' => true   // 前端需要评论显示功能
    ],
    'cache' => [
        'enabled' => true,
        'file' => 'features/cache.php',
        'description' => '缓存管理优化：智能缓存清理和预热机制',
        'load_in_admin' => true,     // 后台需要缓存清理功能
        'load_in_frontend' => true   // 前端内容更新时需要缓存管理
    ],
    'advanced-cache' => [
        'enabled' => true,
        'file' => 'features/advanced-cache.php',
        'description' => '高级缓存系统：多级缓存、智能缓存失效和性能监控',
        'load_in_admin' => true,     // 后台需要缓存管理界面
        'load_in_frontend' => true   // 前端需要高级缓存功能
    ],
    'utilities' => [
        'enabled' => true,
        'file' => 'core/utilities.php',
        'description' => '工具函数库：阅读统计、排行榜渲染、数据格式化',
        'load_in_admin' => false,    // 工具函数主要用于前端显示
        'load_in_frontend' => true   // 前端页面需要工具函数
    ],
    'recommended-posts' => [
        'enabled' => true,
        'file' => 'features/recommended-posts.php',
        'description' => '推荐文章功能：文章推荐标记、侧栏推荐文章显示、随机推荐',
        'load_in_admin' => true,     // 后台需要推荐文章设置功能
        'load_in_frontend' => true   // 前端侧栏需要显示推荐文章
    ],
    'music' => [
        'enabled' => true,
        'file' => 'features/music.php',
        'description' => '音乐播放器功能：APlayer播放器集成、歌单管理、缓存优化',
        'load_in_admin' => false,    // 音乐功能主要用于前端
        'load_in_frontend' => true   // 前端需要音乐播放功能
    ],
    'music-stats' => [
        'enabled' => true,
        'file' => 'features/music-stats.php',
        'description' => '音乐播放统计功能：记录播放时长、统计用户行为、排行榜展示',
        'load_in_admin' => false,    // 统计功能主要用于前端显示
        'load_in_frontend' => true   // 前端音乐页面需要统计功能
    ],
    '9ku-music' => [
        'enabled' => true,
        'file' => 'features/9ku-music.php',
        'description' => '9ku音乐播放器：专门处理9ku平台的音乐播放列表和单曲',
        'load_in_admin' => false,    // 9ku音乐功能主要用于前端
        'load_in_frontend' => true   // 前端需要9ku音乐播放功能
    ],

    'editor-functions' => [
        'enabled' => true,
        'file' => 'features/editor-functions.php',
        'description' => '编辑器增强功能主文件：整合所有编辑器相关的钩子注册和脚本加载',
        'load_in_admin' => true,     // 后台需要编辑器增强功能
        'load_in_frontend' => false  // 编辑器功能主要在后台
    ],
];
