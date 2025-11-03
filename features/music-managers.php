<?php
/**
 * 音乐管理器类集合
 * 包含 MusicManager, MusicPlaylistManager, MusicStatsManager
 *
 * @version 1.0.0
 * @date 2025-10-25
 */

if (!defined('ABSPATH')) exit;

/**
 * 音乐管理器 - 统一管理音乐播放器和歌单功能
 */
class MusicManager {

    private $playlist_manager;
    private $nineku_source_manager;

    public function __construct() {
        $this->playlist_manager = new MusicPlaylistManager();
        $this->nineku_source_manager = new Music9kuSourceManager();
    }

    /**
     * 从9ku.com获取歌曲列表
     */
    public function get_songs_from_9ku_url($url) {
        // Basic validation for 9ku playlist URL
        if (strpos($url, '9ku.com/laoge/') !== false || strpos($url, '9ku.com/music/') !== false) {
            return $this->nineku_source_manager->get_playlist_songs_from_url($url);
        }
        return false;
    }

    /**
     * 获取9ku单首歌曲（按索引）
     */
    public function get_single_song_from_9ku($playlist_url, $index) {
        return $this->nineku_source_manager->get_single_song_from_playlist($playlist_url, $index);
    }

    public function get_single_song_by_page_url($song_page_url) {
        return $this->nineku_source_manager->get_single_song_by_page_url($song_page_url);
    }

    /**
     * 创建或更新自定义歌单
     */
    public function create_playlist($name, $songs = [], $description = '') {
        return $this->playlist_manager->create_playlist($name, $songs, $description);
    }

    /**
     * 获取歌单列表
     */
    public function get_playlists($user_id = null) {
        return $this->playlist_manager->get_playlists($user_id);
    }

    /**
     * 获取歌单详情
     */
    public function get_playlist($playlist_id) {
        return $this->playlist_manager->get_playlist($playlist_id);
    }

    /**
     * 添加歌曲到歌单
     */
    public function add_song_to_playlist($playlist_id, $song_data) {
        return $this->playlist_manager->add_song($playlist_id, $song_data);
    }

    /**
     * 从歌单删除歌曲
     */
    public function remove_song_from_playlist($playlist_id, $song_id) {
        return $this->playlist_manager->remove_song($playlist_id, $song_id);
    }

    /**
     * 删除歌单
     */
    public function delete_playlist($playlist_id) {
        return $this->playlist_manager->delete_playlist($playlist_id);
    }

    /**
     * 导出歌单数据
     */
    public function export_playlist($playlist_id, $format = 'json') {
        return $this->playlist_manager->export_playlist($playlist_id, $format);
    }

    /**
     * 导入歌单数据
     */
    public function import_playlist($data, $format = 'json') {
        return $this->playlist_manager->import_playlist($data, $format);
    }
}

/**
 * 9ku音乐源管理器 - 负责从9ku.com抓取音乐信息
 */
class Music9kuSourceManager {

    private function fetch_html_content($url, $referer = null, $headers = []) {
        // 检查cURL是否可用
        if (!function_exists('curl_init')) {
            error_log("cURL is not available");
            return false;
        }

        $ch = curl_init();
        if (!$ch) {
            error_log("Failed to initialize cURL");
            return false;
        }

        // 默认头部信息以提高兼容性
        $default_headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Connection: keep-alive',
            'Accept-Encoding: gzip, deflate, br'
        ];
        $send_headers = array_merge($default_headers, $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
            CURLOPT_HTTPHEADER => $send_headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '', // 自动处理压缩
            CURLOPT_HEADER => false,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_COOKIEFILE => '', // 启用cookie支持
            CURLOPT_COOKIEJAR => '',
        ]);

        if (!empty($referer)) {
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }

        $output = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            error_log("cURL error for URL $url: $error_msg");
            curl_close($ch);
            return false;
        }

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode >= 200 && $httpcode < 300) {
            return $output;
        } else {
            error_log("Failed to fetch URL: $url with HTTP code $httpcode");
            return false;
        }
    }

    private function parse_9ku_playlist($playlist_url) {
        $html = $this->fetch_html_content($playlist_url);
        if (!$html) {
            error_log("Failed to fetch playlist HTML from: $playlist_url");
            return [];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // 禁用libxml错误显示
        @$dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        $song_urls = [];

        // 策略1：尝试多种选择器组合
        $selectors = [
            "//ol[starts-with(@id, 'f')]//li//a[contains(@href, '/play/')]",
            "//div[contains(@class, 'songList')]//a[contains(@href, '/play/')]",
            "//ul[contains(@class, 'list')]//a[contains(@href, '/play/')]",
            "//table//a[contains(@href, '/play/')]",
            "//a[contains(@href, '/play/')]"
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                foreach ($nodes as $node) {
                    $href = $node->getAttribute('href');
                    if (preg_match('#/play/\\d+\\.htm#', $href)) {
                        $full_url = (strpos($href, 'http') === 0) ? $href : 'https://www.9ku.com' . $href;
                        if (!in_array($full_url, $song_urls)) {
                            $song_urls[] = $full_url;
                        }
                    }
                }
                if (!empty($song_urls)) break; // 找到有效链接就停止
            }
        }

        // 策略2：正则提取所有可能的播放链接
        if (empty($song_urls)) {
            $patterns = [
                '#/play/\\d+\\.htm#',
                '#https?://[^\"\']*9ku\\.com/play/\\d+\\.htm#',
                '#play/\\d+\\.htm#'
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    foreach ($matches[0] as $match) {
                        if (strpos($match, 'http') === 0) {
                            $full_url = $match;
                        } else {
                            $full_url = 'https://www.9ku.com' . (strpos($match, '/') === 0 ? '' : '/') . $match;
                        }
                        if (!in_array($full_url, $song_urls)) {
                            $song_urls[] = $full_url;
                        }
                    }
                }
            }
        }

        // 策略3：尝试从JSON数据中提取（如果页面包含JSON数据）
        if (empty($song_urls) && preg_match_all('#\\[.*?\\]#', $html, $json_matches)) {
            foreach ($json_matches[0] as $json_str) {
                $data = json_decode($json_str, true);
                if (is_array($data)) {
                    array_walk_recursive($data, function($value) use (&$song_urls) {
                        if (is_string($value) && preg_match('#/play/\\d+\\.htm#', $value)) {
                            $full_url = (strpos($value, 'http') === 0) ? $value : 'https://www.9ku.com' . $value;
                            if (!in_array($full_url, $song_urls)) {
                                $song_urls[] = $full_url;
                            }
                        }
                    });
                }
            }
        }

        error_log("Found " . count($song_urls) . " song URLs from playlist: $playlist_url");
        return array_values(array_unique($song_urls));
    }

    private function parse_9ku_song_page($song_page_url) {
        $html = $this->fetch_html_content($song_page_url, 'https://www.9ku.com/');
        if (!$html) {
            return false;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $song_data = [
            'name' => '',
            'artist' => '',
            'cover' => '',
            'download_page_url' => '',
            'url' => ''
        ];

        // 标题尝试：playingTit > h1, og:title, h1, title
        $node = $xpath->query("//div[contains(@class,'playingTit')]//h1");
        if ($node->length > 0) {
            $song_data['name'] = trim($node->item(0)->textContent);
        }
        if (!$song_data['name']) {
            $meta = $xpath->query("//meta[@property='og:title']");
            if ($meta->length > 0) $song_data['name'] = trim($meta->item(0)->getAttribute('content'));
        }
        if (!$song_data['name']) {
            $h1 = $xpath->query("//h1");
            if ($h1->length > 0) $song_data['name'] = trim($h1->item(0)->textContent);
        }
        if (!$song_data['name']) {
            if (preg_match('#<title>(.*?)</title>#is', $html, $m)) {
                $song_data['name'] = trim(html_entity_decode(strip_tags($m[1])));
            }
        }

        // 艺人尝试：playingTit > h2/a, og:music:artist, meta[name=author]
        $node = $xpath->query("//div[contains(@class,'playingTit')]//h2//a");
        if ($node->length > 0) {
            $song_data['artist'] = trim($node->item(0)->textContent);
        }
        if (!$song_data['artist']) {
            $meta = $xpath->query("//meta[@property='og:music:artist']");
            if ($meta->length > 0) $song_data['artist'] = trim($meta->item(0)->getAttribute('content'));
        }
        if (!$song_data['artist']) {
            $meta = $xpath->query("//meta[@name='author']");
            if ($meta->length > 0) $song_data['artist'] = trim($meta->item(0)->getAttribute('content'));
        }

        // 封面尝试：og:image, .playingImg img
        $meta_img = $xpath->query("//meta[@property='og:image']");
        if ($meta_img->length > 0) {
            $song_data['cover'] = $meta_img->item(0)->getAttribute('content');
        }
        if (!$song_data['cover']) {
            $img = $xpath->query("//div[contains(@class,'playing')]//img");
            if ($img->length > 0) $song_data['cover'] = $img->item(0)->getAttribute('src');
        }
        if ($song_data['cover'] && strpos($song_data['cover'], 'http') !== 0) {
            $song_data['cover'] = 'https://www.9ku.com' . (strpos($song_data['cover'], '/') === 0 ? '' : '/') . $song_data['cover'];
        }

        // 下载页链接尝试：a.down, a[href*="/down/"]，以及页面脚本中的下载地址
        $download = $xpath->query("//a[contains(@class,'down')]");
        if ($download->length > 0) {
            $href = $download->item(0)->getAttribute('href');
            if (strpos($href, 'http') === 0) {
                $song_data['download_page_url'] = $href;
            } elseif (strpos($href, '//') === 0) {
                $song_data['download_page_url'] = 'https:' . $href;
            } else {
                $song_data['download_page_url'] = 'https://www.9ku.com' . (strpos($href, '/') === 0 ? '' : '/') . $href;
            }
        }
        if (!$song_data['download_page_url']) {
            $download2 = $xpath->query("//a[contains(@href,'/down/')]");
            if ($download2->length > 0) {
                $href = $download2->item(0)->getAttribute('href');
                $song_data['download_page_url'] = (strpos($href, 'http') === 0) ? $href : 'https://www.9ku.com' . (strpos($href, '/') === 0 ? '' : '/') . $href;
            }
        }

        // 回退：若无下载页，尝试由 /play/{id}.htm 推断 /down/{id}.htm
        if (!$song_data['download_page_url']) {
            if (preg_match('#/play/(\d+)\.htm#', $song_page_url, $m)) {
                $song_data['download_page_url'] = 'https://www.9ku.com/down/' . $m[1] . '.htm';
            }
        }

        // 直接音频：有些页面可能内嵌 <audio>/<source> 直链
        if (!$song_data['url']) {
            $audioSrc = $xpath->query("//audio/@src | //source[@type='audio/mpeg']/@src | //source[contains(@src,'.mp3')]/@src");
            if ($audioSrc->length > 0) {
                $song_data['url'] = $this->normalize_9ku_url($audioSrc->item(0)->nodeValue);
            }
        }

        return $song_data;
    }

    private function parse_9ku_download_page($download_page_url) {
        $html = $this->fetch_html_content($download_page_url, 'https://www.9ku.com/');
        if (!$html) {
            return false;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        // 方案 1：隐藏的 .mp3 链接（老结构）
        $node = $xpath->query("//a[contains(@href, '.mp3') and contains(@style,'display:none')]");
        if ($node->length > 0) {
            $href = $node->item(0)->getAttribute('href');
            return $this->normalize_9ku_url($href);
        }

        // 方案 2：任意含 .mp3 的 a 链接
        $node = $xpath->query("//a[contains(@href, '.mp3')]");
        if ($node->length > 0) {
            $href = $node->item(0)->getAttribute('href');
            return $this->normalize_9ku_url($href);
        }

        // 方案 3：页面内脚本变量中包含 mp3 链接
        if (preg_match('#https?://[^\"\']+\.mp3#i', $html, $m)) {
            return $this->normalize_9ku_url($m[0]);
        }

        return false;
    }

    private function normalize_9ku_url($href) {
        if (!$href) return false;
        // 协议与域名补全
        if (strpos($href, '//') === 0) {
            $href = 'https:' . $href;
        } elseif (strpos($href, 'http') !== 0) {
            $href = 'https://www.9ku.com' . (strpos($href, '/') === 0 ? '' : '/') . $href;
        }
        // 统一使用 https
        $href = preg_replace('#^http://#i', 'https://', $href);
        return $href;
    }

    /**
     * Public method to get a list of songs from a 9ku playlist URL.
     * Returns an array of song data compatible with MusicPlaylistManager.
     */
    public function get_playlist_songs_from_url($playlist_url) {
        // 仅解析播放列表页，返回轻量占位数据，避免批量抓取单曲导致超时。
        error_log("Starting to parse 9ku playlist: $playlist_url");
        $song_page_urls = $this->parse_9ku_playlist($playlist_url);
        error_log("Found " . count($song_page_urls) . " song URLs");

        if (empty($song_page_urls)) {
            error_log("No song URLs found, returning empty array");
            return [];
        }

        // 仅返回占位条目，单曲详细数据由按需接口获取
        $max_songs = min(200, count($song_page_urls));
        $songs = [];
        for ($i = 0; $i < $max_songs; $i++) {
            $songs[] = [
                'url' => '',           // 延后加载
                'name' => '第' . ($i + 1) . '首',
                'artist' => '',
                'cover' => '',
                'lrc' => '',
                'page_url' => $song_page_urls[$i]
            ];
        }
        return $songs;
    }
    /**
     * 按索引解析单首歌曲：从歌单页取 URL，再解析歌曲页与下载页
     */
    public function get_single_song_from_playlist($playlist_url, $index) {
        $song_page_urls = $this->parse_9ku_playlist($playlist_url);
        if (empty($song_page_urls)) return false;

        $index = max(0, min($index, count($song_page_urls) - 1));
        $song_page_url = array_values($song_page_urls)[$index];
        return $this->get_single_song_by_page_url($song_page_url);
    }

    public function get_single_song_by_page_url($song_page_url) {
        $song_data = $this->parse_9ku_song_page($song_page_url);
        if (!$song_data) return false;

        if (empty($song_data['url']) && !empty($song_data['download_page_url'])) {
            $mp3 = $this->parse_9ku_download_page($song_data['download_page_url']);
            if ($mp3) $song_data['url'] = $mp3;
        }
        if (empty($song_data['url'])) return false;

        return [
            'url' => $song_data['url'],
            'name' => $song_data['name'] ?: '未知歌曲',
            'artist' => $song_data['artist'] ?: '未知艺术家',
            'cover' => $song_data['cover'] ?: '',
            'lrc' => ''
        ];
    }
}

/**
 * 音乐歌单管理器 - 管理自定义歌单的创建、编辑和播放
 */
class MusicPlaylistManager {

    private $playlists_table;
    private $playlist_songs_table;
    private $playlist_tags_table;

    public function __construct() {
        global $wpdb;
        $this->playlists_table = $wpdb->prefix . 'music_playlists';
        $this->playlist_songs_table = $wpdb->prefix . 'music_playlist_songs';
        $this->playlist_tags_table = $wpdb->prefix . 'music_playlist_tags';
        $this->create_playlist_tables();
    }

    /**
     * 创建歌单数据表
     */
    private function create_playlist_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 歌单表
        $sql1 = "CREATE TABLE {$this->playlists_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            user_id bigint(20) UNSIGNED DEFAULT 0,
            cover_url varchar(500) DEFAULT '',
            is_public tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_public (is_public)
        ) $charset_collate;";

        // 歌单歌曲表
        $sql2 = "CREATE TABLE {$this->playlist_songs_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            playlist_id mediumint(9) NOT NULL,
            song_url varchar(500) NOT NULL,
            song_name varchar(255) NOT NULL,
            song_artist varchar(255) NOT NULL,
            song_cover varchar(500) DEFAULT '',
            song_lrc text,
            sort_order int(11) DEFAULT 0,
            added_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY playlist_id (playlist_id),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        // 歌单标签表
        $sql3 = "CREATE TABLE {$this->playlist_tags_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            playlist_id mediumint(9) NOT NULL,
            tag_name varchar(100) NOT NULL,
            PRIMARY KEY (id),
            KEY playlist_id (playlist_id),
            KEY tag_name (tag_name)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
    }

    /**
     * 创建新歌单
     */
    public function create_playlist($name, $songs = [], $description = '', $tags = []) {
        global $wpdb;

        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;

        // 插入歌单基本信息
        $result = $wpdb->insert($this->playlists_table, [
            'name' => sanitize_text_field($name),
            'description' => sanitize_textarea_field($description),
            'user_id' => $user_id,
            'is_public' => 1
        ]);

        if (!$result) {
            return false;
        }

        $playlist_id = $wpdb->insert_id;

        // 添加歌曲到歌单
        if (!empty($songs)) {
            foreach ($songs as $index => $song) {
                $this->add_song($playlist_id, array_merge($song, ['sort_order' => $index]));
            }
        }

        // 添加标签
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $wpdb->insert($this->playlist_tags_table, [
                    'playlist_id' => $playlist_id,
                    'tag_name' => sanitize_text_field($tag)
                ]);
            }
        }

        return $playlist_id;
    }

    /**
     * 获取歌单列表
     */
    public function get_playlists($user_id = null, $limit = 20, $offset = 0) {
        global $wpdb;

        $where = '';
        if ($user_id !== null) {
            $where = $wpdb->prepare('WHERE user_id = %d', $user_id);
        }

        $sql = $wpdb->prepare(
            "SELECT p.*, COUNT(ps.id) as song_count
            FROM {$this->playlists_table} p
            LEFT JOIN {$this->playlist_songs_table} ps ON p.id = ps.playlist_id
            $where
            GROUP BY p.id
            ORDER BY p.updated_at DESC
            LIMIT %d OFFSET %d",
            $limit, $offset
        );

        return $wpdb->get_results($sql);
    }

    /**
     * 获取歌单详情
     */
    public function get_playlist($playlist_id) {
        global $wpdb;

        // 获取歌单基本信息
        $playlist = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->playlists_table} WHERE id = %d",
            $playlist_id
        ));

        if (!$playlist) {
            return false;
        }

        // 获取歌单中的歌曲
        $songs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->playlist_songs_table}
            WHERE playlist_id = %d
            ORDER BY sort_order ASC, added_at ASC",
            $playlist_id
        ));

        // 获取歌单标签
        $tags = $wpdb->get_col($wpdb->prepare(
            "SELECT tag_name FROM {$this->playlist_tags_table} WHERE playlist_id = %d",
            $playlist_id
        ));

        $playlist->songs = $songs;
        $playlist->tags = $tags;

        return $playlist;
    }

    /**
     * 添加歌曲到歌单
     */
    public function add_song($playlist_id, $song_data) {
        global $wpdb;

        // 检查歌单是否存在
        $playlist_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->playlists_table} WHERE id = %d",
            $playlist_id
        ));

        if (!$playlist_exists) {
            return false;
        }

        // 检查歌曲是否已存在
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->playlist_songs_table}
            WHERE playlist_id = %d AND song_url = %s",
            $playlist_id, $song_data['url']
        ));

        if ($exists) {
            return false; // 歌曲已存在
        }

        // 获取最大排序值
        $max_order = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(sort_order) FROM {$this->playlist_songs_table} WHERE playlist_id = %d",
            $playlist_id
        ));

        $sort_order = isset($song_data['sort_order']) ? $song_data['sort_order'] : ($max_order + 1);

        // 插入歌曲
        $result = $wpdb->insert($this->playlist_songs_table, [
            'playlist_id' => $playlist_id,
            'song_url' => esc_url_raw($song_data['url']),
            'song_name' => sanitize_text_field($song_data['name'] ?: '未知歌曲'),
            'song_artist' => sanitize_text_field($song_data['artist'] ?: '未知艺术家'),
            'song_cover' => esc_url_raw($song_data['cover'] ?: ''),
            'song_lrc' => $song_data['lrc'] ?: '',
            'sort_order' => $sort_order
        ]);

        if ($result) {
            // 更新歌单修改时间
            $wpdb->update(
                $this->playlists_table,
                ['updated_at' => current_time('mysql')],
                ['id' => $playlist_id]
            );
        }

        return $result;
    }

    /**
     * 从歌单删除歌曲
     */
    public function remove_song($playlist_id, $song_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->playlist_songs_table,
            ['playlist_id' => $playlist_id, 'id' => $song_id],
            ['%d', '%d']
        );

        if ($result) {
            // 更新歌单修改时间
            $wpdb->update(
                $this->playlists_table,
                ['updated_at' => current_time('mysql')],
                ['id' => $playlist_id]
            );
        }

        return $result;
    }

    /**
     * 删除歌单
     */
    public function delete_playlist($playlist_id) {
        global $wpdb;

        // 删除歌单歌曲
        $wpdb->delete($this->playlist_songs_table, ['playlist_id' => $playlist_id], ['%d']);

        // 删除歌单标签
        $wpdb->delete($this->playlist_tags_table, ['playlist_id' => $playlist_id], ['%d']);

        // 删除歌单
        return $wpdb->delete($this->playlists_table, ['id' => $playlist_id], ['%d']);
    }

    /**
     * 导出歌单数据
     */
    public function export_playlist($playlist_id, $format = 'json') {
        $playlist = $this->get_playlist($playlist_id);

        if (!$playlist) {
            return false;
        }

        $export_data = [
            'name' => $playlist->name,
            'description' => $playlist->description,
            'created_at' => $playlist->created_at,
            'songs' => array_map(function($song) {
                return [
                    'name' => $song->song_name,
                    'artist' => $song->song_artist,
                    'url' => $song->song_url,
                    'cover' => $song->song_cover,
                    'lrc' => $song->song_lrc
                ];
            }, $playlist->songs),
            'tags' => $playlist->tags
        ];

        if ($format === 'json') {
            return json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return $export_data;
    }

    /**
     * 导入歌单数据
     */
    public function import_playlist($data, $format = 'json') {
        if ($format === 'json' && is_string($data)) {
            $data = json_decode($data, true);
        }

        if (!is_array($data) || !isset($data['name'])) {
            return false;
        }

        return $this->create_playlist(
            $data['name'],
            $data['songs'] ?? [],
            $data['description'] ?? '',
            $data['tags'] ?? []
        );
    }
}
