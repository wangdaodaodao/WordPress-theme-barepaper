<?php
if (!defined('ABSPATH')) exit;

// 加载管理器类和9ku音乐模块
require_once __DIR__ . '/music-managers.php';
require_once __DIR__ . '/9ku-music.php';
require_once __DIR__ . '/music-stats.php';







/**
 * 音乐列表独立模块类 - 主控制器
 *
 * 功能职责：
 * - 管理音乐播放器的渲染和显示
 * - 处理音乐短代码的解析和执行
 * - 协调统计功能和缓存机制
 * - 提供统一的API接口供外部调用
 *
 * 架构设计：
 * - 单例模式：确保全局只有一个实例
 * - 组合模式：内部包含MusicStatsManager实例
 * - 模板方法模式：定义音乐内容生成的框架
 * - 策略模式：支持不同类型的音乐播放器
 *
 * 工作流程：
 * 1. 初始化阶段：注册钩子、短代码、加载资源
 * 2. 内容生成：解析短代码 → 分类音乐 → 渲染播放器
 * 3. 统计处理：接收前端数据 → 验证 → 存储到数据库
 * 4. 缓存管理：生成内容缓存、清理过期缓存
 */
class Paper_Music {

    private static $instance = null;
    private $cache_expiration = 12 * HOUR_IN_SECONDS;
    private $music_manager;

    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        $this->music_manager = new MusicManager();
        $this->init();
    }

    /**
     * 初始化模块
     */
    private function init() {
        // 检查功能是否启用
        if (!$this->is_enabled()) {
            return;
        }

        // 注册短代码
        $this->register_shortcodes();

        // 加载样式
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);

        // 9ku相关的AJAX处理器已移至9ku-music.php

        // 调试功能已移除
    }

    /**
     * 检查功能是否启用
     */
    private function is_enabled() {
        // 音乐列表功能现在直接启用
        return true;
    }

    /**
     * 检查当前页面是否包含音乐内容
     */
    private function has_music_content() {
        // 检查是否是音乐页面模板
        if (is_page_template('template-music.php')) {
            return true;
        }

        // 检查当前页面内容是否包含音乐短代码
        if (is_singular()) {
            global $post;
            if ($post && has_shortcode($post->post_content, 'music')) {
                return true;
            }
        }

        return false;
    }

    /**
     * 注册短代码 - 音乐短代码现在在 shortcodes.php 中处理
     */
    private function register_shortcodes() {
        // 音乐短代码已移至 shortcodes.php 中统一管理
        // add_shortcode('music', [$this, 'render_music']);
    }

    /**
     * 加载样式文件
     */
    public function enqueue_styles() {
        // 音乐播放器样式
        wp_enqueue_style('music-player-styles', get_template_directory_uri() . '/css/music.css', [], '1.0.0');
    }

    /**
     * 渲染音乐播放器（优化版本）
     */
    public function render_music($atts) {
        $atts = shortcode_atts([
            'url' => '',
            'name' => '',
            'artist' => '',
            'autoplay' => '0',
            'cover' => '',
            'lrc' => '',
            'id' => '',
            'server' => 'netease',
            'type' => 'song'
        ], $atts);

        // 确定音乐类型
        $music_type = $this->detect_music_type($atts);
        if ($music_type === 'invalid') {
            return '<div class="music-error">请提供音乐URL或平台音乐ID</div>';
        }

        // 加载必要的资源
        $this->enqueue_music_resources();

        $unique_id = 'music-player-' . uniqid();

        if ($music_type === 'platform') {
            return $this->render_platform_player($atts, $unique_id);
        } elseif ($music_type === 'special_domain') {
            return $this->render_native_audio_player($atts);
        } else {
            return $this->render_custom_player($atts, $unique_id);
        }
    }

    /**
     * 检测音乐类型
     */
    private function detect_music_type($atts) {
        if (!empty($atts['id']) && empty($atts['url'])) {
            return 'platform';
        }
        if (!empty($atts['url'])) {
            return strpos($atts['url'], 'music.jsbaidu.com') !== false ? 'special_domain' : 'custom';
        }
        return 'invalid';
    }

    /**
     * 加载音乐播放器资源
     */
    private function enqueue_music_resources() {
        static $resources_loaded = false;
        if (!$resources_loaded) {
            wp_enqueue_style('aplayer', get_template_directory_uri() . '/css/APlayer.min.css');
            wp_enqueue_script('aplayer-js', get_template_directory_uri() . '/js/APlayer.min.js', [], null, true);
            wp_enqueue_script('meting-js', get_template_directory_uri() . '/js/Meting.min.js', ['aplayer-js'], null, true);
            $resources_loaded = true;
        }
    }

    /**
     * 渲染平台音乐播放器
     */
    private function render_platform_player($atts, $unique_id) {
        $server = esc_attr($atts['server']);
        $type = esc_attr($atts['type']);
        $id = esc_attr($atts['id']);
        $autoplay = $atts['autoplay'] == '1' ? 'true' : 'false';

        // 智能检测音乐类型
        $detected_type = $this->detect_platform_music_type($id, $type);

        $html = '<div class="music-player-container meting-player-container" id="' . $unique_id . '">';
        $html .= '<div class="aplayer" data-id="' . $id . '" data-server="' . $server . '" data-type="' . $detected_type . '" data-autoplay="' . $autoplay . '" data-theme="#007cba" data-mutex="true" data-list-folded="' . ($detected_type === 'playlist' ? 'false' : 'true') . '" data-preload="metadata">';
        $html .= '<div class="player-loading">正在加载' . ($detected_type === 'playlist' ? '网易云歌单' : '网易云音乐') . '...</div>';
        $html .= '</div></div>';

        // 添加初始化脚本
        $this->add_platform_player_script($unique_id, $server, $detected_type, $id);

        return $html;
    }

    /**
     * 检测平台音乐类型
     */
    private function detect_platform_music_type($id, $type) {
        if (!is_numeric($id)) return $type;

        $length = strlen($id);
        if ($length >= 10 && $type === 'song') {
            return 'playlist'; // 10位以上通常是歌单
        } elseif ($length >= 6 && $length <= 9 && $type === 'song') {
            return 'song'; // 6-9位通常是单曲
        }

        return $type;
    }

    /**
     * 添加平台播放器初始化脚本
     */
    private function add_platform_player_script($unique_id, $server, $type, $id) {
        add_action('wp_footer', function() use ($unique_id, $server, $type, $id) {
            $api_url = $this->get_platform_api_url($server, $type, $id);
            if (!$api_url) {
                echo "<script>document.getElementById('{$unique_id}').innerHTML = '<div class=\"player-error\">不支持的音乐平台</div>';</script>";
                return;
            }

            echo "<script>
            (function() {
                function initPlatformPlayer() {
                    if (typeof APlayer === 'undefined') {
                        setTimeout(initPlatformPlayer, 100);
                        return;
                    }

                    var container = document.getElementById('{$unique_id}');
                    if (!container) return;

                    var aplayerElement = container.querySelector('.aplayer');
                    if (!aplayerElement) return;

                    fetch('{$api_url}')
                        .then(r => r.ok ? r.json() : Promise.reject('API请求失败: ' + r.status))
                        .then(data => {
                            if (!data || !Array.isArray(data) || !data.length) {
                                throw new Error('API返回的数据无效');
                            }

                            var audioData = data.map(item => ({
                                name: item.name || item.title || '未知歌曲',
                                artist: item.artist || item.author || '未知艺术家',
                                url: item.url,
                                cover: item.cover || item.pic || '',
                                lrc: item.lrc || ''
                            }));

                            var player = new APlayer({
                                container: aplayerElement,
                                audio: audioData,
                                autoplay: false,
                                theme: '#007cba',
                                mutex: true,
                                listFolded: '{$type}' === 'playlist' ? false : true,
                                preload: 'metadata',
                                listMaxHeight: '{$type}' === 'playlist' ? '300px' : '200px'
                            });

                            aplayerElement.aplayer = player;
                            player._platformData = { server: '{$server}', id: '{$id}', type: '{$type}', songs: audioData };

                            if (!window.APlayer.instances) window.APlayer.instances = [];
                            if (!window.APlayer.instances.includes(player)) {
                                window.APlayer.instances.push(player);
                            }

                            aplayerElement.dataset.tracked = 'true';
                            document.dispatchEvent(new CustomEvent('meting-player-ready', {
                                detail: { player, element: aplayerElement, container }
                            }));

                            var loadingElement = container.querySelector('.player-loading');
                            if (loadingElement) {
                                loadingElement.style.display = 'none';
                            }
                        })
                        .catch(error => {
                            console.error('创建平台音乐播放器失败:', error);
                            container.innerHTML = '<div class=\"player-error\">播放器加载失败: ' + error.message + '<br>请刷新页面重试</div>';
                        });
                }
                setTimeout(initPlatformPlayer, 500);
            })();
            </script>";
        });
    }

    /**
     * 获取平台API URL
     */
    private function get_platform_api_url($server, $type, $id) {
        $base_urls = [
            'netease' => 'https://api.i-meto.com/meting/api?server=netease&type=',
            'tencent' => 'https://api.i-meto.com/meting/api?server=tencent&type='
        ];

        return isset($base_urls[$server]) ? $base_urls[$server] . $type . '&id=' . $id . '&r=' . rand() : false;
    }

    /**
     * 渲染原生音频播放器
     */
    private function render_native_audio_player($atts) {
        return '<div class="music-player-container native-audio-container">
            <div class="music-info">
                <span class="music-name">' . esc_html($atts['name'] ?: '未知歌曲') . '</span>
                <span class="music-artist"> - ' . esc_html($atts['artist'] ?: '未知艺术家') . '</span>
            </div>
            <audio controls preload="metadata" style="width: 100%; margin-top: 10px;">
                <source src="' . esc_url($atts['url']) . '" type="audio/mpeg">
                您的浏览器不支持音频播放。
            </audio>
        </div>';
    }

    /**
     * 渲染自定义播放器
     */
    private function render_custom_player($atts, $unique_id) {
        $custom_song_id = 'custom_' . md5($atts['url'] . $atts['name'] . $atts['artist']);
        $autoplay = $atts['autoplay'] == '1' ? 'true' : 'false';

        $html = '<div class="music-player-container custom-playlist-container" id="' . $unique_id . '" ';
        $html .= 'data-song-id="' . esc_attr($custom_song_id) . '" ';
        $html .= 'data-song-name="' . esc_attr($atts['name']) . '" ';
        $html .= 'data-song-artist="' . esc_attr($atts['artist']) . '">';
        $html .= '<div class="player-loading">正在加载播放器...</div>';
        $html .= '</div>';

        $this->add_custom_player_script($unique_id, $atts, $autoplay);

        return $html;
    }

    /**
     * 添加自定义播放器脚本
     */
    private function add_custom_player_script($unique_id, $atts, $autoplay) {
        add_action('wp_footer', function() use ($unique_id, $atts, $autoplay) {
            $default_cover = get_template_directory_uri() . '/images/album.svg';
            $cover = !empty($atts['cover']) ? $atts['cover'] : $default_cover;

            $music_data = [[
                'name' => esc_js($atts['name'] ?: '未知歌曲'),
                'artist' => esc_js($atts['artist'] ?: '未知艺术家'),
                'url' => esc_js($atts['url']),
                'cover' => esc_js($cover),
                'lrc' => esc_js($atts['lrc']),
                'type' => 'audio/mpeg'
            ]];

            echo "<script>
            (function() {
                function initPlayer() {
                    if (typeof APlayer === 'undefined' || !document.getElementById('{$unique_id}')) {
                        setTimeout(initPlayer, 100);
                        return;
                    }

                    var container = document.getElementById('{$unique_id}');
                    container.innerHTML = '';

                    try {
                        var player = new APlayer({
                            container: container,
                            audio: " . json_encode($music_data) . ",
                            autoplay: {$autoplay},
                            theme: '#007cba',
                            mutex: true,
                            lrcType: 0,
                            listFolded: false,
                            preload: 'metadata',
                            listMaxHeight: '200px'
                        });

                        container.aplayer = player;

                        if (window.APlayer && !window.APlayer.instances) {
                            window.APlayer.instances = [];
                        }
                        if (window.APlayer && window.APlayer.instances) {
                            window.APlayer.instances.push(player);
                        }

                        setTimeout(() => {
                            var listBtn = container.querySelector('.aplayer-icon-list');
                            if (listBtn) {
                                listBtn.style.display = 'block';
                                listBtn.style.opacity = '0.6';
                            }
                        }, 100);

                    } catch(e) {
                        console.error('APlayer初始化失败:', e);
                        container.innerHTML = '<div class=\"player-error\">播放器加载失败: ' + e.message + '</div>';
                    }
                }
                initPlayer();
            })();
            </script>";
        });
    }

    /**
     * 渲染页面内容（供模板调用）
     */
    public function render_page_content($post_id) {
        // 使用新缓存API
        $cache_key = 'music_content_' . $post_id;
        $cached_content = paper_wp_cache_get($cache_key, 'posts');

        if (false === $cached_content) {
            $cached_content = $this->generate_page_content($post_id);

            // 使用新缓存API设置缓存
            paper_wp_cache_set($cache_key, $cached_content, 'posts');
        }

        return $cached_content;
    }

    /**
     * 生成页面内容
     */
    private function generate_page_content($post_id) {
        $content = get_post_field('post_content', $post_id);

        // 查找所有音乐短代码
        $music_shortcodes = $this->extract_music_shortcodes($content);

        if (empty($music_shortcodes)) {
            return '<div class="music-empty"><p>暂无音乐内容</p></div>';
        }

        // 重新分类音乐类型
        $music_groups = $this->categorize_music_shortcodes($music_shortcodes);

        // 生成音乐列表HTML
        $html = '<div class="music-container">';
        $html .= '<div class="music-header">';
        $html .= '<h2 class="music-title">音乐播放列表</h2>';
        $html .= '<p class="music-description">共 ' . count($music_shortcodes) . ' 首音乐</p>';
        $html .= '</div>';

        // 渲染各个音乐组
        foreach ($music_groups as $group) {
            $html .= $this->render_music_group($group);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * 重新分类音乐短代码
     */
    private function categorize_music_shortcodes($music_shortcodes) {
        $groups = [];

        foreach ($music_shortcodes as $shortcode) {
            // 解析短代码参数
            $pattern = get_shortcode_regex(['music']);
            if (preg_match("/$pattern/", $shortcode, $matches)) {
                $atts = shortcode_parse_atts($matches[3]);

                // 确定音乐类型和分组
                $music_type = $this->determine_music_type($atts);
                $group_key = $this->get_music_group_key($atts, $music_type);

                if (!isset($groups[$group_key])) {
                    $groups[$group_key] = [
                        'type' => $music_type,
                        'atts' => [],
                        'title' => $atts['playlist'] ?? $this->get_default_group_title($music_type)
                    ];
                }

                $groups[$group_key]['atts'][] = $atts;
            }
        }

        return $groups;
    }

    /**
     * 确定音乐类型
     */
    private function determine_music_type($atts) {
        if (!empty($atts['id'])) {
            return 'platform';
        } elseif (!empty($atts['url'])) {
            return ($atts['type'] ?? 'song') === 'playlist' ? 'custom_playlist' : 'custom_single';
        }
        return 'unknown';
    }

    /**
     * 获取音乐分组键
     */
    private function get_music_group_key($atts, $music_type) {
        if ($music_type === 'platform') {
            return 'platform_' . ($atts['id'] ?? 'unknown');
        } elseif ($music_type === 'custom_playlist') {
            return 'playlist_' . ($atts['playlist'] ?? 'default');
        } elseif ($music_type === 'custom_single') {
            return 'single_' . md5($atts['url'] ?? 'unknown');
        }
        return 'unknown';
    }

    /**
     * 获取默认分组标题
     */
    private function get_default_group_title($music_type) {
        switch ($music_type) {
            case 'platform':
                return '平台音乐';
            case 'custom_playlist':
                return '自定义歌单';
            case 'custom_single':
                return '单曲播放';
            default:
                return '未知音乐';
        }
    }

    /**
     * 渲染音乐组
     */
    private function render_music_group($group) {
        $type = $group['type'];
        $atts_array = $group['atts'];
        $title = $group['title'];

        $html = '<div class="music-group">';
        $html .= '<h3 class="music-group-title">' . esc_html($title) . '</h3>';

        if ($type === 'platform') {
            // 平台音乐：合并为一个播放器
            $html .= $this->render_platform_music_group($atts_array);
        } elseif ($type === 'custom_playlist') {
            // 自定义歌单
            $html .= $this->render_custom_music_playlist($atts_array);
        } elseif ($type === 'custom_single') {
            // 单个自定义音乐
            foreach ($atts_array as $atts) {
                $html .= $this->render_music($atts);
            }
        } else {
            // 未知类型
            $html .= '<div class="music-error">不支持的音乐类型</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * 渲染平台音乐组
     */
    private function render_platform_music_group($atts_array) {
        if (empty($atts_array)) {
            return '<div class="music-error">无音乐数据</div>';
        }

        // 使用第一个音乐的设置作为组设置
        $first_atts = $atts_array[0];

        // 如果只有一个音乐，直接渲染单个播放器
        if (count($atts_array) === 1) {
            return $this->render_music($first_atts);
        }

        // 多个音乐：创建歌单
        $atts = array_merge($first_atts, [
            'type' => 'playlist',
            'songs' => $atts_array
        ]);

        return $this->render_music($atts);
    }

    /**
     * 提取音乐短代码
     */
    private function extract_music_shortcodes($content) {
        $pattern = get_shortcode_regex(['music']);
        $matches = [];
        preg_match_all("/$pattern/s", $content, $matches);
        return $matches[0];
    }

    /**
     * 渲染单个音乐
     */
    private function render_single_music($music) {
        $name = $music['name'] ?? '未知歌曲';
        $artist = $music['artist'] ?? '未知艺术家';
        $url = $music['url'] ?? '';

        $html = '<div class="music-item">';
        $html .= '<div class="music-info">';
        $html .= '<span class="music-name">' . esc_html($name) . '</span>';
        $html .= '<span class="music-artist"> - ' . esc_html($artist) . '</span>';
        $html .= '</div>';

        if (!empty($url)) {
            $html .= '<audio controls>';
            $html .= '<source src="' . esc_url($url) . '" type="audio/mpeg">';
            $html .= '您的浏览器不支持音频播放。';
            $html .= '</audio>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * 渲染自定义音乐播放列表
     */
    public function render_custom_music_playlist($custom_musics) {
        // 加载APlayer资源
        wp_enqueue_style('aplayer', get_template_directory_uri() . '/css/APlayer.min.css');
        wp_enqueue_script('aplayer-js', get_template_directory_uri() . '/js/APlayer.min.js', [], null, true);

        $unique_id = 'custom-playlist-' . uniqid();

        // 为歌单生成唯一的songId（基于所有歌曲的组合）
        $playlist_hash = '';
        foreach ($custom_musics as $music) {
            $playlist_hash .= $music['url'] . $music['name'] . $music['artist'];
        }
        $playlist_song_id = 'playlist_' . md5($playlist_hash);

        // 生成播放器HTML
        $html = '<div class="music-player-container custom-playlist-container" id="' . $unique_id . '" ';
        $html .= 'data-song-id="' . esc_attr($playlist_song_id) . '" ';
        $html .= 'data-playlist="true" ';
        $html .= 'data-playlist-songs="' . count($custom_musics) . '">';
        $html .= '<div class="player-loading">正在加载音乐合集...</div>';
        $html .= '</div>';

        // 构建音乐数据
        $music_data = [];
        $default_cover = get_template_directory_uri() . '/images/album.svg';

        foreach ($custom_musics as $atts) {
            // 确保所有属性都有默认值，避免undefined array key警告
            $atts = shortcode_atts([
                'url' => '',
                'name' => '',
                'artist' => '',
                'autoplay' => '0',
                'cover' => '',
                'lrc' => '',
                'id' => '',
                'server' => 'netease',
                'type' => 'song'
            ], $atts);

            $cover = !empty($atts['cover']) ? $atts['cover'] : $default_cover;
            $music_data[] = [
                'name' => esc_js($atts['name'] ?: '未知歌曲'),
                'artist' => esc_js($atts['artist'] ?: '未知艺术家'),
                'url' => esc_js($atts['url']),
                'cover' => esc_js($cover),
                'lrc' => esc_js($atts['lrc']),
                'type' => 'auto'
            ];
        }

        // 添加JavaScript
        add_action('wp_footer', function() use ($unique_id, $music_data) {
            $music_json = json_encode($music_data);

            echo "<script>
            (function() {
                var initPlaylist = function() {
                    if (typeof APlayer !== 'undefined' && document.getElementById('{$unique_id}')) {
                        var container = document.getElementById('{$unique_id}');
                        if (container) {
                            container.innerHTML = '';
                            try {
                                var player = new APlayer({
                                    container: container,
                                    audio: {$music_json},
                                    autoplay: false,
                                    theme: '#007cba',
                                    mutex: true,
                                    lrcType: 0,
                                    listFolded: false,
                                    preload: 'metadata',
                                    listMaxHeight: '300px'
                                });

                                // 将播放器实例挂载到容器元素上，供统计追踪器使用
                                container.aplayer = player;
                                
                                // 如果全局APlayer有instances数组，也添加进去
                                if (window.APlayer && !window.APlayer.instances) {
                                    window.APlayer.instances = [];
                                }
                                if (window.APlayer && window.APlayer.instances) {
                                    window.APlayer.instances.push(player);
                                }

                                console.log('歌单播放器创建成功:', '{$unique_id}');
                                console.log('播放器实例已挂载到容器:', container.aplayer);

                                // 移除封面图片的蓝色背景
                                var removeBlueBackground = function() {
                                    var picElements = container.querySelectorAll('.aplayer-pic');
                                    picElements.forEach(function(pic) {
                                        pic.style.backgroundColor = 'transparent';
                                    });
                                };

                                setTimeout(removeBlueBackground, 100);
                                player.on('loadstart', removeBlueBackground);
                                player.on('loadeddata', removeBlueBackground);
                                player.on('play', removeBlueBackground);
                                player.on('pause', removeBlueBackground);

                            } catch(e) {
                                console.error('歌单播放器创建失败:', e);
                                container.innerHTML = '<div class=\"player-error\">播放器加载失败: ' + e.message + '</div>';
                            }
                        }
                    } else {
                        console.log('等待APlayer加载或容器就绪...');
                        setTimeout(initPlaylist, 200);
                    }
                };
                initPlaylist();
            })();
            </script>";
        });

        return $html;
    }

    /**
     * 渲染自定义音乐播放器 - 使用APlayer界面
     */
    private function render_custom_music_player($atts) {
        $name = $atts['name'] ?? '未知歌曲';
        $artist = $atts['artist'] ?? '未知艺术家';
        $url = $atts['url'] ?? '';
        $cover = $atts['cover'] ?? '';
        $autoplay = isset($atts['autoplay']) && $atts['autoplay'] === '1';

        if (empty($url)) {
            return '<div class="music-error">音乐URL不能为空</div>';
        }

        // 加载APlayer脚本
        add_action('wp_footer', function() {
            echo '<script src="' . get_template_directory_uri() . '/js/custom-aplayer.js?v=' . filemtime(get_template_directory() . '/js/custom-aplayer.js') . '"></script>';
        }, 1);

        $unique_id = 'custom-music-' . uniqid();

        // 准备歌曲数据
        $song_data = [
            'name' => $name,
            'artist' => $artist,
            'url' => $url,
            'cover' => $cover ?: get_template_directory_uri() . '/images/album.svg',
            'lrc' => $atts['lrc'] ?? '',
            'type' => 'audio/mpeg'
        ];

        // 生成播放器HTML
        $html = '<div class="music-player-container custom-music-container" id="' . $unique_id . '">';

        // APlayer界面
        $html .= '<div class="custom-aplayer" id="custom-aplayer-' . uniqid() . '" style="background: #fff; border-radius: 4px; box-shadow: 0 2px 6px rgba(0,0,0,0.15); overflow: hidden; font-family: Arial, sans-serif;">';

        // 播放器主体 - 水平布局
        $html .= '<div class="aplayer-body" style="display: flex; height: 66px; align-items: center;">';

        // 左侧：封面
        $html .= '<div class="aplayer-pic" style="width: 66px; height: 66px; background: #e1e8ed; display: flex; align-items: center; justify-content: center; position: relative; margin: 0 12px; border-radius: 4px;">';
        $html .= '<img src="' . esc_url($song_data['cover']) . '" style="width: 60px; height: 60px; border-radius: 4px; object-fit: contain;" alt="专辑封面">';
        $html .= '<div class="aplayer-button aplayer-play" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 30px; height: 30px; background: rgba(0,0,0,0.5); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; opacity: 0; transition: opacity 0.3s;">';
        $html .= '<div class="aplayer-icon-play" style="width: 0; height: 0; border-left: 6px solid #fff; border-top: 4px solid transparent; border-bottom: 4px solid transparent; margin-left: 2px;"></div>';
        $html .= '</div>';
        $html .= '</div>';

        // 中间：歌曲信息和控制 - 水平布局
        $html .= '<div class="aplayer-info" style="flex: 1; display: flex; align-items: center; justify-content: space-between;">';

        // 歌曲信息
        $html .= '<div class="aplayer-music" style="display: flex; align-items: center;">';
        $html .= '<div class="aplayer-icon aplayer-icon-play" style="width: 32px; height: 32px; cursor: pointer; margin-right: 12px;" title="播放">';
        $html .= '<svg viewBox="0 0 24 24" style="width: 100%; height: 100%;"><path d="M8 5v14l11-7z" fill="currentColor"></path></svg>';
        $html .= '</div>';
        $html .= '<div class="aplayer-text">';
        $html .= '<div class="aplayer-title-author" style="display: flex; align-items: center;">';
        $html .= '<span class="aplayer-title" style="font-size: 14px; color: #333; font-weight: 500; margin-right: 8px;">' . esc_html($name) . '</span>';
        $html .= '<span class="aplayer-author" style="font-size: 12px; color: #666;">' . esc_html($artist) . '</span>';
        $html .= '</div>';
        $html .= '<div class="aplayer-controller" style="margin-top: 4px;">';
        $html .= '<div class="aplayer-bar-wrap" style="position: relative; height: 2px; background: #e1e8ed; border-radius: 1px; margin-bottom: 4px; cursor: pointer;">';
        $html .= '<div class="aplayer-bar" style="position: absolute; left: 0; top: 0; height: 100%; background: #007cba; border-radius: 1px; width: 0%; transition: width 0.1s;"></div>';
        $html .= '</div>';
        $html .= '<div class="aplayer-time" style="display: flex; justify-content: space-between; font-size: 11px; color: #666;">';
        $html .= '<span class="aplayer-ptime">00:00</span>';
        $html .= '<span class="aplayer-dtime">00:00</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        // 右侧：控制按钮
        $html .= '<div class="aplayer-control" style="display: flex; align-items: center; gap: 8px; margin-right: 12px;">';
        $html .= '<div class="aplayer-volume">';
        $html .= '<div class="aplayer-volume-icon" data-action="toggleMute" title="音量">';
        $html .= '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"></path></svg>';
        $html .= '</div>';
        $html .= '<div class="aplayer-volume-bar-wrap" data-action="setVolume">';
        $html .= '<div class="aplayer-volume-bar"></div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="aplayer-mode-icon" data-action="toggleMode" title="播放模式">';
        $html .= '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4zm-4-2V9h2v6h-2z"></path></svg>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        // 隐藏的audio元素
        $html .= '<audio preload="metadata" style="display: none;"></audio>';

        $html .= '</div>';
        $html .= '</div>';

        // 添加JavaScript - 初始化播放器
        add_action('wp_footer', function() use ($unique_id, $song_data, $autoplay) {
            $song_json = json_encode([$song_data]); // 包装成数组格式
            $autoplay_str = $autoplay ? 'true' : 'false';

            echo <<<SCRIPT
            <script>
            // 自定义音乐播放器初始化
            (function() {
                var container = document.getElementById('{$unique_id}');
                if (!container) return;

                if (typeof initCustomAPlayer !== 'undefined') {
                    try {
                        initCustomAPlayer({
                            containerId: '{$unique_id}',
                            songs: {$song_json},
                            autoplay: {$autoplay_str}
                        });
                    } catch (error) {
                        console.error('自定义音乐播放器初始化失败:', error);
                        container.innerHTML = '<div class="player-error">播放器初始化失败</div>';
                    }
                } else {
                    container.innerHTML = '<div class="player-error">播放器脚本未加载</div>';
                }
            })();
            </script>
SCRIPT;
        });

        return $html;
    }





    /**
     * 清除缓存
     */
    public function clear_cache($post_id = null) {
        if ($post_id) {
            // 使用新缓存API删除缓存
            paper_wp_cache_delete('music_content_' . $post_id, 'posts');
        } else {
            // 清除所有音乐列表缓存 - 使用新缓存API清空posts组
            paper_wp_cache_flush_group('posts');
        }
    }

    /**
     * 创建或更新自定义歌单
     */
    public function create_playlist($name, $songs = [], $description = '') {
        return $this->music_manager->create_playlist($name, $songs, $description);
    }

    /**
     * 获取歌单列表
     */
    public function get_playlists($user_id = null) {
        return $this->music_manager->get_playlists($user_id);
    }

    /**
     * 获取歌单详情
     */
    public function get_playlist($playlist_id) {
        return $this->music_manager->get_playlist($playlist_id);
    }

    /**
     * 添加歌曲到歌单
     */
    public function add_song_to_playlist($playlist_id, $song_data) {
        return $this->music_manager->add_song_to_playlist($playlist_id, $song_data);
    }

    /**
     * 从歌单删除歌曲
     */
    public function remove_song_from_playlist($playlist_id, $song_id) {
        return $this->music_manager->remove_song_from_playlist($playlist_id, $song_id);
    }

    /**
     * 删除歌单
     */
    public function delete_playlist($playlist_id) {
        return $this->music_manager->delete_playlist($playlist_id);
    }

    /**
     * 导出歌单数据
     */
    public function export_playlist($playlist_id, $format = 'json') {
        return $this->music_manager->export_playlist($playlist_id, $format);
    }

    /**
     * 导入歌单数据
     */
    public function import_playlist($data, $format = 'json') {
        return $this->music_manager->import_playlist($data, $format);
    }



}

/**
 * 初始化音乐列表模块
 */
function paper_music_init() {
    return Paper_Music::get_instance();
}

// 启动模块
add_action('init', 'paper_music_init', 5);

/**
 * 音乐列表API函数（供外部调用）
 */
function paper_get_music_content($post_id) {
    $music = Paper_Music::get_instance();
    return $music->render_page_content($post_id);
}

function paper_clear_music_cache($post_id = null) {
    $music = Paper_Music::get_instance();
    $music->clear_cache($post_id);
}
