<?php
/**
 * ===========================================
 * 9ku音乐播放器模块 - 独立模块
 * ===========================================
 *
 * 🎵 模块目标
 *   - 专门处理9ku音乐平台的播放功能
 *   - 提供完整的播放列表支持
 *   - 集成APlayer播放器界面
 *   - 支持按需加载和批量加载
 *
 * 🎼 功能特性
 *   - 9ku音乐播放列表解析
 *   - APlayer播放器集成
 *   - AJAX按需加载
 *   - 播放状态记忆
 *   - 响应式播放界面
 *
 * 🔧 架构设计
 *   - 独立模块：与主音乐模块分离
 *   - 组合模式：使用MusicManager处理数据
 *   - 模板方法模式：定义播放器渲染流程
 *   - 策略模式：支持不同的加载策略
 *
 * 📁 文件结构
 *   - 9ku-music.php: 9ku音乐播放器主控制器
 *   - music-managers.php: 共享的管理器类
 *
 * @author wangdaodao
 * @version 1.0.0
 * @date 2025-10-27
 */

if (!defined('ABSPATH')) exit;

// 加载管理器类
require_once __DIR__ . '/music-managers.php';

/**
 * 9ku音乐播放器独立模块类
 *
 * 功能职责：
 * - 处理9ku音乐播放列表的解析和渲染
 * - 管理APlayer播放器的初始化
 * - 协调AJAX加载和数据缓存
 * - 提供统一的9ku音乐API接口
 *
 * 架构设计：
 * - 单例模式：确保全局只有一个实例
 * - 组合模式：内部包含MusicManager实例
 * - 模板方法模式：定义播放器生成流程
 * - 观察者模式：监听播放器事件
 *
 * 工作流程：
 * 1. 初始化阶段：注册钩子、加载资源
 * 2. 内容生成：解析URL → 获取播放列表 → 渲染播放器
 * 3. 数据加载：AJAX请求 → 验证数据 → 返回前端
 * 4. 状态管理：保存播放状态、恢复播放进度
 */
class NineKu_Music_Player {

    private static $instance = null;
    private $music_manager;
    private $cache_expiration;

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

        // 注册AJAX处理器
        $this->register_ajax_handlers();

        // 初始化缓存系统
        $this->init_cache_system();

        // 调试功能已移除
    }

    /**
     * 初始化缓存系统
     */
    private function init_cache_system() {
        // 设置缓存过期时间（1小时）
        $this->cache_expiration = HOUR_IN_SECONDS;
        
        // 清理过期缓存
        add_action('wp_scheduled_delete', [$this, 'cleanup_expired_cache']);
    }

    /**
     * 清理过期缓存
     */
    public function cleanup_expired_cache() {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
                '_transient_timeout_9ku_playlist_%',
                time()
            )
        );
    }

    /**
     * 检查功能是否启用
     */
    private function is_enabled() {
        // 9ku音乐播放器直接启用
        return true;
    }

    /**
     * 渲染9ku音乐播放列表 - 直接显示播放器界面
     */
    public function render_9ku_music_playlist($atts_array) {
        if (empty($atts_array) || empty($atts_array[0]['url'])) {
            return '<div class="music-error">9ku音乐URL缺失</div>';
        }

        $playlist_url = $atts_array[0]['url'];

        // 使用APlayer资源
        wp_enqueue_style('aplayer', get_template_directory_uri() . '/css/APlayer.min.css');
        wp_enqueue_script('aplayer-js', get_template_directory_uri() . '/js/APlayer.min.js', [], null, true);

        // 注入不发送 Referer 的策略，避免直链被防盗链拦截
        add_action('wp_head', function() {
            static $done = false;
            if ($done) return;
            $done = true;
            echo '<meta name="referrer" content="no-referrer">';
        }, 1);

        $unique_id = '9ku-playlist-' . uniqid();
        $playlist_hash = md5($playlist_url);
        $playlist_song_id = '9ku_' . $playlist_hash;

        // 生成播放器HTML（APlayer 容器 + 分页容器）
        $html = '<div class="music-player-container 9ku-playlist-container" id="' . $unique_id . '" ';
        $html .= 'data-song-id="' . esc_attr($playlist_song_id) . '" ';
        $html .= 'data-playlist-url="' . esc_attr($playlist_url) . '" ';
        $html .= 'data-playlist-type="9ku">';
        $html .= '<div class="aplayer" id="aplayer-' . $playlist_hash . '"></div>';
        $html .= '<div class="apager" id="apager-' . $playlist_hash . '" style="margin:8px 0; display:flex; align-items:center; gap:8px;">';
        $html .= '<button type="button" class="apager-prev" disabled style="padding:4px 10px;">上一页</button>';
        $html .= '<span class="apager-status">第 1 / 1 页</span>';
        $html .= '<button type="button" class="apager-next" disabled style="padding:4px 10px;">下一页</button>';
        $html .= '</div>';
        $html .= '</div>';

        // 添加JavaScript - 使用DOMContentLoaded确保脚本加载完成
        add_action('wp_footer', function() use ($unique_id, $playlist_url, $playlist_hash) {
            $ajax_url = admin_url('admin-ajax.php');
            $nonce_playlist = wp_create_nonce('load_9ku_playlist');
            $nonce_single = wp_create_nonce('load_single_song');

            echo <<<SCRIPT
            <script>
            // 9ku + APlayer 初始化
            document.addEventListener('DOMContentLoaded', function() {
                var wrapper = document.getElementById('{$unique_id}');
                if (!wrapper) return;
                function waitAPlayer(){
                    if (typeof APlayer === 'undefined') { setTimeout(waitAPlayer, 100); return; }
                    setup();
                }
                waitAPlayer();

                function setup(){
                    var aplayerEl = document.getElementById('aplayer-{$playlist_hash}');
                    var pagerEl = document.getElementById('apager-{$playlist_hash}');
                    var statusEl = pagerEl.querySelector('.apager-status');
                    var prevBtn = pagerEl.querySelector('.apager-prev');
                    var nextBtn = pagerEl.querySelector('.apager-next');

                    console.log('[9ku-aplayer] setup start', { containerId: '{$unique_id}', aplayerEl: !!aplayerEl });

                    var pageSize = 20; // 每页20首歌曲作为一个完整播放列表
                    var currentPage = 0;
                    var playlistAll = []; // 存储所有歌曲信息（轻量，不含音频URL）
                    var currentPlaylist = []; // 当前显示的20首歌曲播放列表
                    // 使用更可靠的静音音频源 - 使用data URI避免网络请求
                    var SILENT_TYPE = 'audio/mpeg';
                    var SILENT_URL = 'data:audio/mpeg;base64,SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA//tQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAWGluZwAAAA8AAAABAAACcQCAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////';

                    // 延迟创建APlayer实例，确保容器完全就绪
                    var player = null;
                    
                    function initAPlayer() {
                        // 先创建空的APlayer实例，稍后添加音频
                        player = new APlayer({
                            container: aplayerEl,
                            audio: [], // 初始为空数组
                            listFolded: false,
                            preload: 'none',
                            order: 'list',
                            mutex: true,
                            lrcType: 0,
                            autoplay: false,
                            volume: 0.8,
                            // 禁用自定义音频类型处理，使用默认行为
                            customAudioType: {}
                        });
                        
                        console.log('[9ku-aplayer] APlayer initialized');
                        
                        // 初始化完成后设置事件监听器
                        setupEventListeners();
                    }
                    
                    // 延迟初始化，确保DOM完全就绪
                    setTimeout(initAPlayer, 100);

                    window.__debug9ku = {
                        player: player,
                        pager: pagerEl,
                        getAll: function(){ return playlistAll; },
                        getPage: function(){ return currentPage; },
                        getCurrentAudio: function(){ return player.list.audios[player.list.index]; }
                    };
                    
                    // 拉取播放列表（轻量）
                    console.log('[9ku-aplayer] fetching playlist', { url: '{$playlist_url}' });
                    fetch('{$ajax_url}', {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: 'action=load_9ku_playlist&url=' + encodeURIComponent('{$playlist_url}') + '&nonce={$nonce_playlist}'
                    }).then(function(r){ 
                        if (!r.ok) {
                            throw new Error('HTTP错误: ' + r.status);
                        }
                        return r.json(); 
                    }).then(function(resp){
                        console.log('[9ku-aplayer] playlist response', resp);
                        
                        // 更灵活的响应格式检查
                        let songsData = [];
                        
                        // 调试：检查响应结构
                        console.log('[9ku-aplayer] 响应结构检查:', {
                            hasResp: !!resp,
                            respType: typeof resp,
                            isArray: Array.isArray(resp),
                            hasSuccess: resp && resp.hasOwnProperty('success'),
                            successValue: resp && resp.success,
                            hasData: resp && resp.hasOwnProperty('data'),
                            dataType: resp && resp.data ? typeof resp.data : 'undefined',
                            dataIsArray: resp && resp.data ? Array.isArray(resp.data) : false,
                            hasSongs: resp && resp.hasOwnProperty('songs'),
                            songsIsArray: resp && resp.songs ? Array.isArray(resp.songs) : false
                        });
                        
                        if (resp && resp.success) {
                            // 优先使用 resp.data
                            if (Array.isArray(resp.data)) {
                                songsData = resp.data;
                                console.log('[9ku-aplayer] 使用 resp.data，数量:', songsData.length);
                            } 
                            // 兼容 resp.songs 格式
                            else if (Array.isArray(resp.songs)) {
                                songsData = resp.songs;
                                console.log('[9ku-aplayer] 使用 resp.songs，数量:', songsData.length);
                            }
                            // 兼容直接返回数组
                            else if (Array.isArray(resp)) {
                                songsData = resp;
                                console.log('[9ku-aplayer] 使用直接数组，数量:', songsData.length);
                            }
                        }
                        
                        if (songsData.length === 0) {
                            console.warn('[9ku-aplayer] 播放列表为空或格式不正确，原始响应:', JSON.stringify(resp, null, 2));
                            
                            // 提供降级方案：创建空的播放列表
                            songsData = [{
                                name: '暂无歌曲',
                                artist: '请检查URL是否正确',
                                cover: '', // 使用空封面或默认图片
                                url: '',
                                page_url: ''
                            }];
                        }
                        
                        // 存储所有歌曲信息（轻量，不含音频URL）
                        playlistAll = songsData;
                        
                        // 只加载第一页20首歌曲作为初始播放列表
                        currentPage = 0;
                        loadCurrentPage();
                        updatePager();
                        console.log('[9ku-aplayer] playlist ready', { total: playlistAll.length, currentPage: currentPage, pageSize: pageSize });
                    }).catch(function(e){
                        console.error('[9ku-aplayer] playlist load error', e);
                        
                        // 提供更友好的错误信息
                        let errorMsg = '播放列表加载失败';
                        if (e.message.includes('HTTP错误: 403')) {
                            errorMsg = '访问被拒绝，请检查网络设置';
                        } else if (e.message.includes('HTTP错误: 404')) {
                            errorMsg = '播放列表不存在，请检查URL';
                        } else if (e.message.includes('HTTP错误: 500')) {
                            errorMsg = '服务器内部错误，请稍后重试';
                        }
                        
                        aplayerEl.innerHTML = '<div style="padding:15px;text-align:center;color:#f00;background:#ffe6e6;border-radius:5px;margin:10px 0;">' + 
                            '<strong>错误:</strong> ' + errorMsg + '<br>' +
                            '<small style="color:#666;">详细信息: ' + e.message + '</small>' +
                            '</div>';
                    });

                    function updatePager(){
                        var totalPages = Math.max(1, Math.ceil(playlistAll.length / pageSize));
                        statusEl.textContent = '第 ' + (currentPage + 1) + ' / ' + totalPages + ' 页 (共 ' + playlistAll.length + ' 首)';
                        prevBtn.disabled = currentPage <= 0;
                        nextBtn.disabled = currentPage >= totalPages - 1;
                        console.log('[9ku-aplayer] pager update', { currentPage, totalPages });
                    }

                    function toAudioStub(song){
                        return {
                            name: song.name || '未知歌曲',
                            artist: song.artist || '',
                            cover: song.cover || '',
                            // 使用一个极短的静音MP3作为占位，避免APlayer报错
                            url: 'https://cdn.jsdelivr.net/gh/anars/blank-audio/1-second-of-silence.mp3',
                            type: 'audio/mpeg',
                            // 自定义字段：原始索引与页面链接
                            _page_url: song.page_url || '',
                            _global_index: song._global_index,
                            _is_silent: true
                        };
                    }

                    function loadCurrentPage(){
                        console.log('[9ku-aplayer] loading page', currentPage);
                        
                        // 计算当前页的歌曲范围
                        var start = currentPage * pageSize;
                        var end = Math.min(start + pageSize, playlistAll.length);
                        
                        // 创建当前页的播放列表
                        currentPlaylist = [];
                        for (var i = start; i < end; i++) {
                            var stub = Object.assign({}, playlistAll[i]);
                            stub._global_index = i;
                            stub._page_index = i - start; // 当前页内的索引
                            currentPlaylist.push(toAudioStub(stub));
                        }
                        
                        // 安全地清空并重新加载播放列表
                        try {
                            player.list.clear();
                            if (currentPlaylist.length) {
                                // 使用try-catch包装音频添加操作
                                player.list.add(currentPlaylist);
                            }
                        } catch (e) {
                            console.error('[9ku-aplayer] 加载播放列表失败', e);
                            // 如果添加失败，尝试备用方案
                            if (currentPlaylist.length) {
                                setTimeout(() => {
                                    try {
                                        player.list.add(currentPlaylist);
                                    } catch (e2) {
                                        console.error('[9ku-aplayer] 备用加载也失败', e2);
                                    }
                                }, 100);
                            }
                        }
                        
                        console.log('[9ku-aplayer] page loaded', { 
                            page: currentPage, 
                            start: start, 
                            end: end, 
                            songs: currentPlaylist.length,
                            total: playlistAll.length 
                        });
                    }

                    function renderPage(page){
                        currentPage = page;
                        loadCurrentPage();
                    }

                    prevBtn.addEventListener('click', function(){ if (currentPage > 0){ renderPage(currentPage - 1); updatePager(); }});
                    nextBtn.addEventListener('click', function(){ var totalPages = Math.ceil(playlistAll.length / pageSize); if (currentPage < totalPages - 1){ renderPage(currentPage + 1); updatePager(); }});

                    async function ensureUrlAndPlay(indexInPage){
                        var audio = player.list.audios[indexInPage];
                        if (!audio) { console.warn('[9ku-aplayer] no audio at indexInPage', indexInPage); return; }
                        
                        // 如果已经加载过真实URL，直接播放
                        if (!audio._is_silent && audio.url && !audio.url.includes('blank-audio')) {
                            console.log('[9ku-aplayer] 已加载真实URL，直接播放');
                            player.list.switch(indexInPage);
                            setTimeout(() => { player.play(); }, 100);
                            return;
                        }
                        
                        var globalIndex = audio._global_index || 0;
                        var pageUrl = audio._page_url || '';
                        console.log('[9ku-aplayer] fetching single song', { globalIndex, pageUrl });
                        
                        try {
                            var resp = await fetch('{$ajax_url}', {
                                method: 'POST',
                                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                                body: 'action=load_single_song&url=' + encodeURIComponent('{$playlist_url}') + '&index=' + globalIndex + '&page_url=' + encodeURIComponent(pageUrl) + '&nonce={$nonce_single}'
                            }).then(function(r){ return r.json(); });
                            
                            console.log('[9ku-aplayer] single song response', resp);
                            
                            // 简化响应解析：直接使用最可能的数据结构
                            var songData = null;
                            
                            if (resp && resp.success) {
                                // 优先使用 resp.data.song（嵌套结构）
                                if (resp.data && resp.data.song && resp.data.song.url) {
                                    songData = resp.data.song;
                                }
                                // 使用 resp.data（直接结构）
                                else if (resp.data && resp.data.url) {
                                    songData = resp.data;
                                }
                                // 兼容直接返回歌曲对象
                                else if (resp.url) {
                                    songData = resp;
                                }
                                // 兼容 resp.song 格式
                                else if (resp.song && resp.song.url) {
                                    songData = resp.song;
                                }
                            }
                            
                            if (!songData || !songData.url) {
                                console.error('[9ku-aplayer] 单曲数据格式不正确', resp);
                                throw new Error('加载单曲失败：数据格式不正确');
                            }
                            
                            console.log('[9ku-aplayer] 最终歌曲数据:', songData);
                            
                            // 更新当前音频对象
                            var updated = Object.assign({}, audio, songData, { _is_silent: false });
                            player.list.audios[indexInPage] = updated;
                            
                            // 直接切换到当前歌曲，但不自动播放
                            player.list.switch(indexInPage);
                            
                            // 只更新音频源，让用户手动点击播放
                            console.log('[9ku-aplayer] 音频源已更新，请手动点击播放按钮');
                            
                            // 如果APlayer不支持，创建备用音频元素
                            setTimeout(() => {
                                try {
                                    // 检查音频元素是否有效
                                    var audioEl = player.audio;
                                    if (!audioEl || !audioEl.src) {
                                        throw new Error('音频元素无效');
                                    }
                                } catch (e) {
                                    console.warn('[9ku-aplayer] APlayer音频元素检查失败', e);
                                    
                                    // 创建备用音频元素
                                    console.log('[9ku-aplayer] 创建备用音频');
                                    
                                    var fallbackAudio = document.createElement('audio');
                                    fallbackAudio.controls = true;
                                    fallbackAudio.style.width = '100%';
                                    fallbackAudio.style.marginTop = '10px';
                                    
                                    var source = document.createElement('source');
                                    source.src = updated.url;
                                    source.type = updated.type || 'audio/mpeg';
                                    
                                    fallbackAudio.appendChild(source);
                                    fallbackAudio.appendChild(document.createTextNode('您的浏览器不支持音频播放'));
                                    
                                    // 将备用播放器插入到APlayer容器旁边
                                    aplayerEl.parentNode.insertBefore(fallbackAudio, aplayerEl.nextSibling);
                                }
                            }, 300);
                        } catch (err){
                            console.error('[9ku-aplayer] single song load error', err);
                        }
                    }

                    // 事件监听器设置函数
                    function setupEventListeners() {
                        // 简化事件处理：只在用户真正需要播放时才加载URL
                        var isSwitching = false;
                        var isFirstPlay = true; // 标记是否是第一次播放
                        
                        player.on('listswitch', function(obj){
                            console.log('[9ku-aplayer] event listswitch', obj);
                            if (isSwitching) return;
                            isSwitching = true;
                            
                            var a = player.list.audios[obj.index];
                            // 切换歌曲时只更新显示，不自动加载URL
                            console.log('[9ku-aplayer] 切换到歌曲:', a.name);
                            
                            setTimeout(() => { isSwitching = false; }, 100);
                        });
                        
                        // 在play事件中按需加载真实URL
                        player.on('play', function(){
                            var i = player.list.index;
                            var a = player.list.audios[i];
                            console.log('[9ku-aplayer] event play', { index: i, audio: a });
                            
                            // 如果是第一次播放或者当前是静音占位，需要加载真实URL
                            if ((isFirstPlay || (a && a._is_silent)) && a) {
                                console.log('[9ku-aplayer] 需要加载真实URL');
                                isFirstPlay = false;
                                
                                // 暂停播放，先加载URL
                                try { player.pause(); } catch(e) {}
                                ensureUrlAndPlay(i);
                            }
                        });
                        
                        // 监听播放结束事件，自动加载下一页
                        player.on('ended', function(){
                            console.log('[9ku-aplayer] event ended');
                            
                            // 检查是否是当前页的最后一首
                            var currentIndex = player.list.index;
                            var isLastSongInPage = currentIndex >= currentPlaylist.length - 1;
                            var hasNextPage = currentPage < Math.ceil(playlistAll.length / pageSize) - 1;
                            
                            console.log('[9ku-aplayer] auto load check', {
                                currentIndex: currentIndex,
                                isLastSongInPage: isLastSongInPage,
                                hasNextPage: hasNextPage,
                                currentPage: currentPage,
                                totalPages: Math.ceil(playlistAll.length / pageSize)
                            });
                            
                            // 如果是当前页最后一首且有下一页，自动加载下一页
                            if (isLastSongInPage && hasNextPage) {
                                console.log('[9ku-aplayer] auto loading next page');
                                currentPage++;
                                loadCurrentPage();
                                updatePager();
                                
                                // 自动播放下一页的第一首
                                setTimeout(() => {
                                    player.list.switch(0);
                                    ensureUrlAndPlay(0);
                                }, 500);
                            }
                        });
                        
                        player.on('error', function(e){
                            console.error('[9ku-aplayer] event error', {
                                error: e,
                                errorType: e.type,
                                errorMessage: e.message,
                                currentAudio: player.list.audios[player.list.index],
                                audioElement: player.audio
                            });
                            
                            // 如果是音频源问题，尝试重新加载
                            if (e.message && (e.message.includes('supported sources') || e.message.includes('NotSupportedError'))) {
                                console.log('[9ku-aplayer] 音频源不支持，尝试重新加载');
                                var currentIndex = player.list.index;
                                var currentAudio = player.list.audios[currentIndex];
                                
                                if (currentAudio && currentAudio._is_silent) {
                                    console.log('[9ku-aplayer] 当前是静音占位，需要加载真实URL');
                                    ensureUrlAndPlay(currentIndex);
                                }
                            }
                            
                            try { player.pause(); } catch(_) {}
                        });
                        
                        console.log('[9ku-aplayer] 事件监听器设置完成');
                    }
                }
            });
            </script>
SCRIPT;
        });

        return $html;
    }

    /**
     * 注册AJAX处理器
     */
    private function register_ajax_handlers() {
        add_action('wp_ajax_load_9ku_playlist', [$this, 'ajax_load_9ku_playlist']);
        add_action('wp_ajax_nopriv_load_9ku_playlist', [$this, 'ajax_load_9ku_playlist']);
        add_action('wp_ajax_load_single_song', [$this, 'ajax_load_single_song']);
        add_action('wp_ajax_nopriv_load_single_song', [$this, 'ajax_load_single_song']);

    }

    /**
     * AJAX处理器：懒加载9ku播放列表（优化版）
     */
    public function ajax_load_9ku_playlist() {
        $debug = ['ts_start' => microtime(true)];
        
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'load_9ku_playlist')) {
            wp_send_json_error(['message' => '安全验证失败', 'debug' => $debug]);
            return;
        }

        $url = sanitize_text_field($_POST['url'] ?? '');
        $debug['input_url'] = $url;

        // 验证URL
        if (empty($url)) {
            $debug['reason'] = 'empty_url';
            wp_send_json_error(['message' => 'URL不能为空', 'debug' => $debug]);
            return;
        }

        if (!preg_match('/^https?:\/\/.*9ku\.com.*(?:laoge|play|music)/', $url)) {
            $debug['reason'] = 'invalid_url';
            error_log('load_9ku_playlist invalid url: ' . $url);
            wp_send_json_error(['message' => '无效的9ku URL，请检查URL格式是否正确', 'debug' => $debug]);
            return;
        }

        // 检查缓存
        $cache_key = '9ku_playlist_v2_' . md5($url);
        $cached_data = get_transient($cache_key);
        $debug['cache_key'] = $cache_key;
        $debug['cache_hit'] = $cached_data !== false;
        
        if ($cached_data !== false) {
            $debug['ts_end'] = microtime(true);
            
            // 确保缓存数据也使用新的响应格式
            $response_data = [
                'success' => true,
                'data' => $cached_data,
                'songs' => $cached_data, // 兼容旧格式
                'count' => count($cached_data),
                'message' => '播放列表加载成功（缓存）'
            ];
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $response_data['debug'] = $debug;
            }
            
            wp_send_json($response_data);
            return;
        }

        try {
            $debug['stage'] = 'fetch_playlist';
            
            // 尝试重试机制
            $max_retries = 2;
            $songs = [];
            
            for ($attempt = 1; $attempt <= $max_retries + 1; $attempt++) {
                try {
                    $debug['attempt'] = $attempt;
                    $songs = $this->music_manager->get_songs_from_9ku_url($url);
                    
                    if (!empty($songs)) {
                        break; // 成功获取数据，跳出循环
                    }
                    
                    if ($attempt <= $max_retries) {
                        sleep(1); // 等待1秒后重试
                    }
                    
                } catch (Exception $e) {
                    $debug['attempt_error'] = $e->getMessage();
                    if ($attempt <= $max_retries) {
                        sleep(1); // 等待1秒后重试
                    } else {
                        throw $e; // 重试次数用完，抛出异常
                    }
                }
            }
            
            $debug['found'] = is_array($songs) ? count($songs) : 0;

            if (empty($songs)) {
                $debug['reason'] = 'empty_songs';
                error_log('load_9ku_playlist empty result after retries: ' . $url);
                
                // 提供更详细的错误信息
                $error_message = '无法获取音乐数据，可能的原因：\n';
                $error_message .= '1. 网络连接问题\n';
                $error_message .= '2. 9ku网站暂时不可用\n';
                $error_message .= '3. URL格式不正确或已失效\n';
                $error_message .= '4. 网站结构发生变化';
                
                wp_send_json_error(['message' => $error_message, 'debug' => $debug]);
                return;
            }

            // 格式化数据供前端使用（占位：无直链，点击或播放时再按索引加载单曲）
            $formatted_songs = array_map(function($song) {
                $safe = [
                    'name' => $song['name'] ?? '未知歌曲',
                    'artist' => $song['artist'] ?? '',
                    'cover' => $song['cover'] ?? get_template_directory_uri() . '/images/album.svg',
                    'url' => '',
                    'page_url' => $song['page_url'] ?? ''
                ];
                return $safe;
            }, $songs);

            // 缓存数据1小时
            set_transient($cache_key, $formatted_songs, $this->cache_expiration);
            $debug['ts_end'] = microtime(true);

            // 确保返回格式一致
            $response_data = [
                'success' => true,
                'data' => $formatted_songs,
                'songs' => $formatted_songs, // 兼容旧格式
                'count' => count($formatted_songs),
                'message' => '播放列表加载成功'
            ];
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $response_data['debug'] = $debug;
            }

            wp_send_json($response_data);

        } catch (Exception $e) {
            $debug['error'] = $e->getMessage();
            error_log('AJAX加载9ku播放列表失败: ' . $e->getMessage());
            
            // 提供详细的错误信息和解决方案
            $error_message = '加载失败：' . $e->getMessage() . '\n\n';
            $error_message .= '可能的解决方案：\n';
            $error_message .= '1. 检查网络连接\n';
            $error_message .= '2. 稍后重试\n';
            $error_message .= '3. 检查URL是否正确\n';
            $error_message .= '4. 联系网站管理员';
            
            // 确保错误响应格式一致
            $error_response = [
                'success' => false,
                'message' => $error_message,
                'data' => [],
                'songs' => []
            ];
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $error_response['debug'] = $debug;
            }
            
            wp_send_json($error_response);
        }
    }

    /**
     * 带重试机制的9ku歌曲获取
     */
    private function get_9ku_songs_with_retry($url, $max_retries = 2) {
        $retry_count = 0;
        
        while ($retry_count <= $max_retries) {
            try {
                $songs = $this->music_manager->get_songs_from_9ku_url($url);
                
                if (!empty($songs)) {
                    return $songs;
                }
                
                $retry_count++;
                if ($retry_count <= $max_retries) {
                    sleep(1); // 等待1秒后重试
                }
                
            } catch (Exception $e) {
                $retry_count++;
                if ($retry_count <= $max_retries) {
                    sleep(1); // 等待1秒后重试
                } else {
                    throw $e; // 重试次数用完，抛出异常
                }
            }
        }
        
        return [];
    }

    /**
     * 格式化歌曲数据
     */
    private function format_song_data($song) {
        $url = $song['url'] ?? '';
        
        // 调试：记录原始URL
        error_log("format_song_data - 原始URL: " . $url);

        // 检查URL是否为空
        if (empty($url)) {
            error_log("format_song_data - URL为空，无法处理");
            return [
                'name' => $song['name'] ?: '未知歌曲',
                'artist' => $song['artist'] ?: '未知艺术家',
                'url' => '',
                'cover' => $song['cover'] ?: get_template_directory_uri() . '/images/album.svg',
                'lrc' => $song['lrc'] ?: '',
                'type' => 'audio/mpeg'
            ];
        }

        // 智能URL处理
        if (strpos($url, '.mp3') === false) {
            // 如果是9ku的音频服务器，自动添加.mp3扩展名
            if (strpos($url, 'music.jsbaidu.com') !== false || 
                strpos($url, '9ku.com') !== false ||
                strpos($url, '/down/') !== false) {
                $url .= '.mp3';
                error_log("format_song_data - 添加.mp3扩展名: " . $url);
            }
        }

        // 如果是相对URL，转换为绝对URL
        if (strpos($url, 'http') !== 0) {
            // 处理不同的相对URL格式
            if (strpos($url, '//') === 0) {
                $url = 'https:' . $url;
            } else {
                $url = 'https://www.9ku.com' . (strpos($url, '/') === 0 ? '' : '/') . $url;
            }
            error_log("format_song_data - 转换为绝对URL: " . $url);
        }

        // 确保使用HTTPS
        $url = preg_replace('/^http:/', 'https:', $url);
        
        // 调试：记录最终URL
        error_log("format_song_data - 最终URL: " . $url);

        // 直接返回直链，并依赖 <meta name="referrer" content="no-referrer"> 规避防盗链
        return [
            'name' => $song['name'] ?: '未知歌曲',
            'artist' => $song['artist'] ?: '未知艺术家',
            'url' => $url,
            'cover' => $song['cover'] ?: get_template_directory_uri() . '/images/album.svg',
            'lrc' => $song['lrc'] ?: '',
            'type' => 'audio/mpeg'
        ];
    }

    /**
     * AJAX处理器：加载单首9ku歌曲（优化版）
     */
    public function ajax_load_single_song() {
        $debug = ['ts_start' => microtime(true)];
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'load_single_song')) {
            wp_send_json_error(['message' => '安全验证失败', 'debug' => $debug]);
            return;
        }

        $url = sanitize_text_field($_POST['url'] ?? '');
        $page_url = sanitize_text_field($_POST['page_url'] ?? '');
        $index = intval($_POST['index'] ?? 0);
        $debug['input'] = compact('url','page_url','index');

        // 验证URL
        if (empty($url) || !preg_match('/^https?:\/\/.*9ku\.com.*(?:laoge|play)/', $url)) {
            $debug['reason'] = 'invalid_url';
            error_log('load_single_song invalid url: ' . $url);
            wp_send_json_error(['message' => '无效的9ku URL', 'debug' => $debug]);
            return;
        }

        // 优先使用 page_url 的缓存键，确保不同页链接分别缓存
        $cache_key_base = !empty($page_url) ? $page_url : ($url . '_' . $index);
        $cache_key = '9ku_song_v2_' . md5($cache_key_base);
        $cached_song = get_transient($cache_key);
        $debug['cache_key'] = $cache_key;
        $debug['cache_hit'] = $cached_song !== false;
        
        if ($cached_song !== false) {
            $debug['ts_end'] = microtime(true);
            $payload = $cached_song;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $payload = ['song' => $cached_song, 'debug' => $debug];
            }
            wp_send_json_success($payload);
            return;
        }

        try {
            $debug['stage'] = 'resolve_single';
            // 单首解析：优先用 page_url 直接解析，否则按索引
            if (!empty($page_url) && preg_match('/^https?:\/\/.*9ku\.com\/play\/\d+\.htm$/', $page_url)) {
                $single = $this->music_manager->get_single_song_by_page_url($page_url);
                $debug['path'] = 'by_page_url';
            } else {
                $single = $this->music_manager->get_single_song_from_9ku($url, $index);
                $debug['path'] = 'by_index';
            }

            if (empty($single) || empty($single['url'])) {
                $debug['reason'] = 'empty_single_or_url';
                error_log('load_single_song empty for index ' . $index . ' page_url=' . $page_url);
                wp_send_json_error(['message' => '无法获取指定歌曲数据，请检查索引是否正确', 'debug' => $debug]);
                return;
            }

            // 直链封装
            $formatted_song = $this->format_song_data($single);
            $debug['result'] = [
                'name' => $formatted_song['name'] ?? '',
                'artist' => $formatted_song['artist'] ?? '',
                'has_url' => !empty($formatted_song['url']),
                'type' => $formatted_song['type'] ?? ''
            ];

            // 缓存单首歌曲数据30分钟
            set_transient($cache_key, $formatted_song, 30 * MINUTE_IN_SECONDS);
            $debug['ts_end'] = microtime(true);

            // 简化响应格式，直接返回歌曲数据，避免嵌套结构
            $response_data = [
                'success' => true,
                'data' => $formatted_song, // 主要数据放在data字段
                'song' => $formatted_song, // 兼容旧格式
                'message' => '单曲加载成功',
                // 直接添加歌曲字段，避免嵌套
                'name' => $formatted_song['name'] ?? '未知歌曲',
                'artist' => $formatted_song['artist'] ?? '未知艺术家',
                'url' => $formatted_song['url'] ?? '',
                'cover' => $formatted_song['cover'] ?? '',
                'type' => $formatted_song['type'] ?? 'audio/mpeg'
            ];
            
            // 调试：记录响应格式
            error_log('单曲响应数据: ' . json_encode($response_data, JSON_UNESCAPED_UNICODE));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $response_data['debug'] = $debug;
            }

            wp_send_json($response_data);

        } catch (Exception $e) {
            $debug['error'] = $e->getMessage();
            error_log('AJAX加载单首9ku歌曲失败: ' . $e->getMessage());
            
            // 提供降级方案
            wp_send_json_error(['message' => '加载失败：' . $e->getMessage(), 'debug' => $debug]);
        }
    }

    /**
     * 保留空壳以兼容旧缓存命中时的请求（立即 410）
     */
    public function ajax_proxy_9ku_mp3() {
        // 立即返回 410，告知前端代理已废弃（正常路径不应再调用到这里）
        status_header(410);
        exit('Proxy deprecated, use direct link with no-referrer');
    }
}

/**
 * 初始化9ku音乐播放器模块
 */
function nineku_music_player_init() {
    return NineKu_Music_Player::get_instance();
}

// 启动模块
add_action('init', 'nineku_music_player_init', 5);

/**
 * 9ku音乐播放器API函数（供外部调用）
 */
function paper_render_9ku_playlist($atts) {
    $player = NineKu_Music_Player::get_instance();
    return $player->render_9ku_music_playlist($atts);
}
