/**
 * 音乐播放统计追踪器
 * 
 * 功能：
 * - 追踪APlayer播放器的播放行为
 * - 记录播放时长和次数
 * - 支持自定义音乐和平台音乐
 * - 自动上报统计数据到后端
 * 
 * @author wangdaodao
 * @version 1.0.0
 * @date 2025-10-31
 */

(function($) {
    'use strict';

    /**
     * 音乐统计追踪器类
     */
    class MusicStatsTracker {
        constructor() {
            this.players = [];
            this.currentTrack = null;
            this.playStartTime = null;
            this.accumulatedTime = 0;
            this.reportThreshold = 10; // 累计10秒后上报
            this.reportTimer = null;
            this.isPageVisible = true;
            this.isFirstReport = true; // 标记是否是第一次上报
            
            this.init();
        }

        init() {
            // 等待页面加载完成
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setupTrackers());
            } else {
                this.setupTrackers();
            }

            // 监听页面可见性变化
            this.setupVisibilityHandler();

            // 监听页面卸载
            this.setupUnloadHandler();
            
            // 监听 Meting.js 播放器就绪事件
            const tracker = this;
            document.addEventListener('meting-player-ready', (event) => {
                const { player, element } = event.detail;
                if (player && !tracker.players.includes(player)) {
                    tracker.trackAPlayer(player);
                    if (element) {
                        element.dataset.tracked = 'true';
                    }
                }
            });
        }

        /**
         * 设置所有播放器的追踪
         */
        setupTrackers() {
            // 首先设置全局audio元素监听器（最重要）
            this.setupGlobalAudioListener();

            // 立即检测一次
            this.detectAndTrackPlayers();

            // 使用定时器轮询检测播放器，因为播放器可能是异步加载的
            let attemptCount = 0;
            const checkInterval = setInterval(() => {
                attemptCount++;
                this.detectAndTrackPlayers();

                // 如果检测到播放器，可以停止轮询（或继续检测新播放器）
                if (this.players.length > 0) {
                    // 继续检测，因为可能有多个播放器
                    // clearInterval(checkInterval);
                }
            }, 1000);

            // 60秒后停止轮询（给Meting.js更多时间初始化）
            setTimeout(() => {
                clearInterval(checkInterval);

                // 最后再检查一次平台音乐容器，看看是否有遗漏的
                const platformContainers = document.querySelectorAll('.aplayer[data-server][data-id]');
                platformContainers.forEach((element, index) => {
                    if (!element.dataset.tracked && element.aplayer) {
                        this.trackAPlayer(element.aplayer);
                        element.dataset.tracked = 'true';
                    }
                });
            }, 60000);
        }

        /**
         * 设置全局audio元素监听器
         */
        setupGlobalAudioListener() {
            // 监听整个document的DOM变化，捕获所有新创建的audio元素
            const audioObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // 检查直接添加的audio元素
                            if (node.tagName === 'AUDIO') {
                                this.handleNewAudioElement(node);
                            }
                            // 检查新添加元素内部的audio元素
                            else if (node.querySelectorAll) {
                                const audioElements = node.querySelectorAll('audio');
                                audioElements.forEach(audio => {
                                    this.handleNewAudioElement(audio);
                                });
                            }
                        }
                    });
                });
            });

            // 开始监听
            audioObserver.observe(document.body, {
                childList: true,
                subtree: true
            });

            // 同时定期扫描页面上所有未追踪的audio元素
            const scanInterval = setInterval(() => {
                const allAudios = document.querySelectorAll('audio');
                allAudios.forEach(audio => {
                    if (!audio.dataset.tracked) {
                        this.handleNewAudioElement(audio);
                    }
                });
            }, 500);

            // 5分钟后停止定期扫描
            setTimeout(() => {
                clearInterval(scanInterval);
            }, 300000);
        }

        /**
         * 处理新发现的audio元素
         */
        handleNewAudioElement(audio) {
            if (audio.dataset.tracked) {
                return;
            }

            // 检查是否在平台音乐容器内
            const container = audio.closest('.aplayer');
            if (container && container.hasAttribute('data-server')) {
                this.trackPlatformAudio(audio, container);
                audio.dataset.tracked = 'true';
                container.dataset.tracked = 'true';
            } else {
                // 检查是否是其他类型的音乐播放器
                const musicContainer = audio.closest('.music-player-container') ||
                                      audio.closest('.aplayer') ||
                                      audio.closest('.native-audio-container');

                if (musicContainer) {
                    if (musicContainer.classList.contains('native-audio-container')) {
                        this.trackNativeAudio(audio);
                    } else {
                        // 可能是平台音乐或其他类型的播放器
                        this.trackPlatformAudio(audio, musicContainer);
                    }
                    audio.dataset.tracked = 'true';
                    musicContainer.dataset.tracked = 'true';
                }
            }
        }

        /**
         * 检测并追踪播放器
         */
        detectAndTrackPlayers() {
            let foundCount = 0;

            // 检测所有包含 aplayer 类的元素
            const aplayerElements = document.querySelectorAll('.aplayer');

            aplayerElements.forEach((element, index) => {
                // 跳过已追踪的
                if (element.dataset.tracked) {
                    return;
                }

                // 检查是否是平台音乐容器（通过 data-server 和 data-id 识别）
                const hasServer = element.hasAttribute('data-server') || element.dataset.server;
                const hasId = element.hasAttribute('data-id') || element.dataset.id;
                const isPlatformContainer = hasServer && hasId;

                // 检查是否有 aplayer 实例
                if (element.aplayer) {
                    this.trackAPlayer(element.aplayer);
                    element.dataset.tracked = 'true';
                    foundCount++;
                } else {
                    // 对于所有 .aplayer 元素，无论是否有实例，都尝试监听 audio 元素
                    // 查找容器内的 audio 元素
                    let audio = element.querySelector('audio');

                    if (audio) {
                        this.trackPlatformAudio(audio, element);
                        element.dataset.tracked = 'true';
                        foundCount++;
                    } else {
                        // 如果还没有 audio 元素，使用MutationObserver监听DOM变化
                        const observer = new MutationObserver((mutations) => {
                            mutations.forEach((mutation) => {
                                mutation.addedNodes.forEach((node) => {
                                    if (node.nodeType === Node.ELEMENT_NODE) {
                                        // 检查新添加的元素是否是audio
                                        let newAudio = null;
                                        if (node.tagName === 'AUDIO') {
                                            newAudio = node;
                                        } else if (node.querySelector) {
                                            newAudio = node.querySelector('audio');
                                        }

                                        if (newAudio && !newAudio.dataset.tracked) {
                                            observer.disconnect(); // 停止监听
                                            this.trackPlatformAudio(newAudio, element);
                                            element.dataset.tracked = 'true';
                                            foundCount++;
                                        }
                                    }
                                });
                            });
                        });

                        // 开始监听子元素变化
                        observer.observe(element, {
                            childList: true,
                            subtree: true
                        });

                        // 同时定期检查（作为备用方案）
                        const checkInterval = setInterval(() => {
                            const audioElement = element.querySelector('audio');
                            if (audioElement && !audioElement.dataset.tracked) {
                                clearInterval(checkInterval);
                                observer.disconnect();
                                this.trackPlatformAudio(audioElement, element);
                                element.dataset.tracked = 'true';
                                foundCount++;
                            }
                        }, 1000);

                        // 60秒后停止监听
                        setTimeout(() => {
                            clearInterval(checkInterval);
                            observer.disconnect();
                        }, 60000);
                    }
                }
            });

            // 检测Meting.js初始化的APlayer (平台音乐)
            const metingElements = document.querySelectorAll('meting-js');

            metingElements.forEach((metingElement, index) => {
                if (!metingElement.dataset.tracked) {
                    // 查找内部的.aplayer元素
                    const aplayerElement = metingElement.querySelector('.aplayer');
                    if (aplayerElement) {
                        if (aplayerElement.aplayer) {
                            this.trackAPlayer(aplayerElement.aplayer);
                            metingElement.dataset.tracked = 'true';
                            aplayerElement.dataset.tracked = 'true';
                            foundCount++;
                        }
                    }
                }
            });

            // 特别检测：查找可能已经被Meting.js转换的平台音乐播放器
            // 这些播放器可能没有meting-js父元素，但有data-server属性
            const potentialPlatformPlayers = document.querySelectorAll('.aplayer[data-server]');

            potentialPlatformPlayers.forEach((element, index) => {
                if (!element.dataset.tracked) {
                    if (element.aplayer) {
                        // 标记为平台音乐播放器并追踪
                        element.aplayer._isPlatformPlayer = true;
                        this.trackAPlayer(element.aplayer);
                        element.dataset.tracked = 'true';
                        foundCount++;
                    } else {
                        // 为还没有实例的平台音乐播放器设置监听器
                        const observer = new MutationObserver((mutations) => {
                            mutations.forEach((mutation) => {
                                // 检查是否有新的子元素（比如audio元素）
                                mutation.addedNodes.forEach((node) => {
                                    if (node.nodeType === Node.ELEMENT_NODE) {
                                        if (node.tagName === 'AUDIO') {
                                            observer.disconnect();
                                            clearInterval(checkInterval);
                                            this.trackPlatformAudio(node, element);
                                            element.dataset.tracked = 'true';
                                        }
                                        // 也检查是否有aplayer实例
                                        else if (element.aplayer && !element.dataset.tracked) {
                                            observer.disconnect();
                                            clearInterval(checkInterval);
                                            element.aplayer._isPlatformPlayer = true;
                                            this.trackAPlayer(element.aplayer);
                                            element.dataset.tracked = 'true';
                                        }
                                    }
                                });

                                // 检查属性变化
                                if (mutation.type === 'attributes' && mutation.attributeName === 'data-tracked') {
                                    if (element.dataset.tracked) {
                                        observer.disconnect();
                                        clearInterval(checkInterval);
                                    }
                                }
                            });
                        });

                        // 监听子元素和属性变化
                        observer.observe(element, {
                            childList: true,
                            subtree: true,
                            attributes: true,
                            attributeFilter: ['data-tracked']
                        });

                        // 同时定期检查 - 更频繁一些
                        const checkInterval = setInterval(() => {
                            // 首先检查是否有audio元素
                            const audioElement = element.querySelector('audio');
                            if (audioElement && !audioElement.dataset.tracked) {
                                clearInterval(checkInterval);
                                observer.disconnect();
                                this.trackPlatformAudio(audioElement, element);
                                element.dataset.tracked = 'true';
                                return;
                            }

                            // 然后检查是否有aplayer实例
                            if (element.aplayer && !element.dataset.tracked) {
                                clearInterval(checkInterval);
                                observer.disconnect();
                                element.aplayer._isPlatformPlayer = true;
                                this.trackAPlayer(element.aplayer);
                                element.dataset.tracked = 'true';
                                return;
                            }

                            // 最后尝试强制初始化（如果Meting.js可能需要手动触发）
                            if (!element.dataset.tracked && typeof Meting !== 'undefined') {
                                try {
                                    // 手动触发Meting.js初始化（如果可能的话）
                                    if (window.Meting && typeof window.Meting.init === 'function') {
                                        window.Meting.init();
                                    }
                                } catch (e) {
                                    // 初始化失败，忽略
                                }
                            }
                        }, 200); // 每200ms检查一次，更频繁

                        // 45秒后停止监听（给更多时间）
                        setTimeout(() => {
                            clearInterval(checkInterval);
                            observer.disconnect();
                        }, 45000);
                    }
                }
            });

            // 检测自定义APlayer实例数组
            if (window.APlayer && window.APlayer.instances) {
                window.APlayer.instances.forEach((player, index) => {
                    if (!this.players.includes(player)) {
                        this.trackAPlayer(player);
                        foundCount++;
                    }
                });
            }

            // 检测原生audio标签
            const nativeAudios = document.querySelectorAll('.native-audio-container audio');
            nativeAudios.forEach((audio, index) => {
                if (!audio.dataset.tracked) {
                    this.trackNativeAudio(audio);
                    audio.dataset.tracked = 'true';
                    foundCount++;
                }
            });
        }

        /**
         * 追踪Meting.js播放器
         */
        trackMetingPlayer(element) {
            // Meting.js会在元素上创建aplayer属性
            const checkPlayer = setInterval(() => {
                if (element.aplayer) {
                    clearInterval(checkPlayer);
                    this.trackAPlayer(element.aplayer);
                }
            }, 500);

            // 5秒后停止检测
            setTimeout(() => clearInterval(checkPlayer), 5000);
        }

        /**
         * 追踪APlayer实例
         */
        trackAPlayer(player) {
            if (this.players.includes(player)) {
                return;
            }

            this.players.push(player);

            // 监听播放事件
            player.on('play', () => {
                // 获取当前播放的歌曲信息
                let songInfo = this.getSongInfo(player);

                if (!songInfo) {
                    // 对于平台音乐，歌曲信息可能还没有加载完成，延迟500ms再试
                    setTimeout(() => {
                        songInfo = this.getSongInfo(player);
                        if (songInfo) {
                            this.startTracking(songInfo, player);
                        }
                    }, 500);
                    return;
                }

                this.startTracking(songInfo, player);
            });

            // 监听暂停事件
            player.on('pause', () => {
                this.pauseTracking();
            });

            // 监听结束事件
            player.on('ended', () => {
                this.stopTracking(true);
            });

            // 监听切歌事件
            player.on('listswitch', () => {
                this.stopTracking(false);
            });
        }

        /**
         * 追踪平台音乐的audio元素
         */
        trackPlatformAudio(audio, containerElement) {
            if (this.players.includes(audio)) {
                return;
            }

            this.players.push(audio);

            // 获取平台信息
            const server = containerElement.getAttribute('data-server') ||
                          (containerElement.dataset && containerElement.dataset.server) ||
                          'netease';
            const songId = containerElement.getAttribute('data-id') ||
                          (containerElement.dataset && containerElement.dataset.id) ||
                          '';

            // 监听播放事件
            audio.addEventListener('play', () => {
                // 从DOM获取歌曲信息
                const titleEl = containerElement.querySelector('.aplayer-title');
                const authorEl = containerElement.querySelector('.aplayer-author');

                const songInfo = {
                    name: titleEl ? titleEl.textContent.trim() : '未知歌曲',
                    artist: authorEl ? authorEl.textContent.replace(/^-\s*/, '').trim() : '',
                    id: songId || 'platform_' + this.generateHash(audio.src),
                    url: audio.currentSrc || audio.src || ''
                };

                this.startTracking(songInfo, { audio, _type: 'platform', _container: containerElement });
            });

            // 监听暂停事件
            audio.addEventListener('pause', () => {
                this.pauseTracking();
            });

            // 监听结束事件
            audio.addEventListener('ended', () => {
                this.stopTracking(true);
            });

            // 监听加载新歌曲（切歌）
            audio.addEventListener('loadstart', () => {
                this.stopTracking(false);
            });
        }

        /**
         * 追踪原生audio标签
         */
        trackNativeAudio(audio) {
            const container = audio.closest('.native-audio-container');
            const songName = container?.querySelector('.music-name')?.textContent || '未知歌曲';
            const songArtist = container?.querySelector('.music-artist')?.textContent?.replace(' - ', '') || '';

            // 监听播放事件
            audio.addEventListener('play', () => {
                const songInfo = {
                    name: songName,
                    artist: songArtist,
                    id: 'native_' + this.generateHash(audio.src)
                };
                this.startTracking(songInfo, audio);
            });

            // 监听暂停事件
            audio.addEventListener('pause', () => {
                this.pauseTracking();
            });

            // 监听结束事件
            audio.addEventListener('ended', () => {
                this.stopTracking(true);
            });
        }

        /**
         * 开始追踪
         */
        startTracking(songInfo, player) {
            // 如果正在播放其他歌曲，先停止追踪
            if (this.currentTrack && this.currentTrack.id !== this.getSongId(songInfo, player)) {
                this.stopTracking(false);
            }

            const songId = this.getSongId(songInfo, player);
            const songType = this.getSongType(songInfo, player);
            const platform = this.getPlatform(player);

            this.currentTrack = {
                id: songId,
                name: songInfo.name || '未知歌曲',
                artist: songInfo.artist || '',
                type: songType,
                platform: platform
            };

            this.playStartTime = Date.now();
            this.accumulatedTime = 0;
            this.isFirstReport = true; // 新播放，标记为第一次上报

            // 启动定时上报
            this.startReportTimer();
        }

        /**
         * 暂停追踪
         */
        pauseTracking() {
            if (!this.currentTrack || !this.playStartTime) {
                return;
            }

            // 累计播放时长
            const elapsed = Math.floor((Date.now() - this.playStartTime) / 1000);
            this.accumulatedTime += elapsed;
            this.playStartTime = null;

            // 停止定时上报
            this.stopReportTimer();
        }

        /**
         * 停止追踪
         */
        stopTracking(ended = false) {
            if (!this.currentTrack) {
                return;
            }

            // 如果正在播放，先累计时长
            if (this.playStartTime) {
                const elapsed = Math.floor((Date.now() - this.playStartTime) / 1000);
                this.accumulatedTime += elapsed;
            }

            // 上报统计数据
            if (this.accumulatedTime > 0) {
                this.reportStats();
            }

            // 重置状态
            this.currentTrack = null;
            this.playStartTime = null;
            this.accumulatedTime = 0;
            this.isFirstReport = true; // 重置为下次播放做准备
            this.stopReportTimer();
        }

        /**
         * 启动定时上报
         */
        startReportTimer() {
            this.stopReportTimer();
            
            this.reportTimer = setInterval(() => {
                if (this.playStartTime && this.isPageVisible) {
                    const elapsed = Math.floor((Date.now() - this.playStartTime) / 1000);
                    const totalTime = this.accumulatedTime + elapsed;

                    // 如果累计时长超过阈值，上报并重置
                    if (totalTime >= this.reportThreshold) {
                        this.accumulatedTime = totalTime;
                        this.reportStats();
                        
                        // 重置起始时间，标记后续不是第一次上报
                        this.playStartTime = Date.now();
                        this.accumulatedTime = 0;
                        this.isFirstReport = false; // 已经上报过一次，后续不是第一次
                    }
                }
            }, 5000); // 每5秒检查一次
        }

        /**
         * 停止定时上报
         */
        stopReportTimer() {
            if (this.reportTimer) {
                clearInterval(this.reportTimer);
                this.reportTimer = null;
            }
        }

        /**
         * 上报统计数据
         */
        reportStats() {
            if (!this.currentTrack || this.accumulatedTime < 1) {
                return;
            }

            const data = {
                action: 'record_music_play',
                nonce: musicStatsData.nonce,
                song_id: this.currentTrack.id,
                song_name: this.currentTrack.name,
                song_artist: this.currentTrack.artist,
                song_type: this.currentTrack.type,
                platform: this.currentTrack.platform,
                duration: this.accumulatedTime,
                is_first_report: this.isFirstReport ? 'true' : 'false'
            };

            $.ajax({
                url: musicStatsData.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    // 上报成功，静默处理
                },
                error: (xhr, status, error) => {
                    // 上报失败，静默处理
                }
            });
        }

        /**
         * 获取歌曲信息
         */
        getSongInfo(player) {
            const audio = player.audio;
            const list = player.list;

            // 0. 优先从播放器实例的_platformData获取（我们手动创建的平台音乐）
            if (player._platformData && player._platformData.songs) {
                const currentIndex = list ? list.index : 0;
                const currentSong = player._platformData.songs[currentIndex];

                if (currentSong) {
                    const songInfo = {
                        name: currentSong.name || '未知歌曲',
                        artist: currentSong.artist || '未知艺术家',
                        id: player._platformData.id || currentSong.id || '',
                        url: currentSong.url || (audio && audio.currentSrc ? audio.currentSrc : ''),
                        server: player._platformData.server,
                        type: player._platformData.type
                    };
                    return songInfo;
                } else {
                    const fallbackSong = player._platformData.songs[0];
                    if (fallbackSong) {
                        const songInfo = {
                            name: fallbackSong.name || '未知歌曲',
                            artist: fallbackSong.artist || '未知艺术家',
                            id: player._platformData.id || fallbackSong.id || '',
                            url: fallbackSong.url || (audio && audio.currentSrc ? audio.currentSrc : ''),
                            server: player._platformData.server,
                            type: player._platformData.type
                        };
                        return songInfo;
                    }
                }
            }

            // 1. 尝试从player.list.audios获取（标准APlayer方式）
            if (list && list.audios && list.audios[player.list.index]) {
                const songInfo = list.audios[player.list.index];
                return songInfo;
            }

            // 2. 尝试从audio对象获取
            if (audio && (audio.title || audio.name)) {
                const songInfo = {
                    name: audio.title || audio.name || '未知歌曲',
                    artist: audio.artist || audio.author || '',
                    id: audio.id || '',
                    url: audio.url || audio.src || ''
                };
                return songInfo;
            }

            // 3. 尝试从DOM获取（平台音乐专用）
            if (player.container) {
                const titleEl = player.container.querySelector('.aplayer-title');
                const authorEl = player.container.querySelector('.aplayer-author');

                if (titleEl || authorEl) {
                    // 获取平台ID
                    let songId = '';
                    const container = player.container;

                    // 从各种可能的地方获取ID
                    if (container.hasAttribute('data-id')) {
                        songId = container.getAttribute('data-id');
                    } else if (container.dataset && container.dataset.id) {
                        songId = container.dataset.id;
                    } else {
                        // 从meting-js父元素获取
                        const metingElement = container.closest('meting-js');
                        if (metingElement) {
                            songId = metingElement.getAttribute('id') || metingElement.getAttribute('data-id') || '';
                        }
                    }

                    const songInfo = {
                        name: titleEl ? titleEl.textContent.trim() : '未知歌曲',
                        artist: authorEl ? authorEl.textContent.replace(/^-\s*/, '').trim() : '',
                        id: songId,
                        url: audio && audio.currentSrc ? audio.currentSrc : ''
                    };
                    return songInfo;
                }
            }

            return null;
        }

        /**
         * 获取歌曲ID
         */
        getSongId(songInfo, player) {
            // 如果有平台ID，使用平台ID
            if (songInfo.id) {
                return songInfo.id.toString();
            }

            // 自定义音乐，使用名称+艺术家的hash
            const str = (songInfo.name || '') + (songInfo.artist || '');
            return 'custom_' + this.generateHash(str);
        }

        /**
         * 获取歌曲类型
         */
        getSongType(songInfo, player) {
            // 检查是否是平台音乐
            // 1. 如果有平台ID（不是custom_开头）
            if (songInfo.id && !songInfo.id.toString().startsWith('custom_') && !songInfo.id.toString().startsWith('native_')) {
                return 'platform';
            }

            // 2. 检查播放器容器是否在 meting-js 内
            if (player && player.container) {
                const metingElement = player.container.closest('meting-js');
                if (metingElement) {
                    return 'platform';
                }

                // 检查是否是平台音乐容器（通过 data-server 和 data-id 识别）
                const container = player.container;
                const hasServer = container.hasAttribute('data-server') || (container.dataset && container.dataset.server);
                const hasId = container.hasAttribute('data-id') || (container.dataset && container.dataset.id);

                if (hasServer && hasId) {
                    return 'platform';
                }

                // 检查父容器（可能是 .meting-player-container）
                const metingContainer = container.closest('.meting-player-container');
                if (metingContainer) {
                    const innerAplayer = metingContainer.querySelector('.aplayer[data-server]');
                    if (innerAplayer) {
                        return 'platform';
                    }
                }
            }

            // 3. 检查songInfo中是否包含平台标识
            if (songInfo.url && (songInfo.url.includes('music.163.com') || songInfo.url.includes('qq.com'))) {
                return 'platform';
            }

            // 4. 特别检查：如果播放器是通过Meting.js创建的
            if (player && player._metingCreated) {
                return 'platform';
            }

            return 'custom';
        }

        /**
         * 获取平台
         */
        getPlatform(player) {
            let platform = '';

            // 尝试从容器元素获取平台信息
            if (player && player.container) {
                // 1. 检查当前容器（.aplayer）的 data-server 属性
                const container = player.container;
                if (container.hasAttribute('data-server')) {
                    platform = container.getAttribute('data-server');
                    return platform;
                }
                if (container.dataset && container.dataset.server) {
                    platform = container.dataset.server;
                    return platform;
                }

                // 2. 检查父级 meting-js 元素
                const metingElement = container.closest('meting-js');
                if (metingElement) {
                    platform = metingElement.getAttribute('server') || 'netease';
                    return platform;
                }

                // 3. 检查 .aplayer 容器的 data-server 属性（向上查找）
                const aplayerElement = container.closest('.aplayer');
                if (aplayerElement) {
                    if (aplayerElement.hasAttribute('data-server')) {
                        platform = aplayerElement.getAttribute('data-server');
                        return platform;
                    }
                    if (aplayerElement.dataset && aplayerElement.dataset.server) {
                        platform = aplayerElement.dataset.server;
                        return platform;
                    }
                }

                // 4. 查找是否在 .meting-player-container 内
                const metingContainer = container.closest('.meting-player-container');
                if (metingContainer) {
                    const innerAplayer = metingContainer.querySelector('.aplayer[data-server]');
                    if (innerAplayer) {
                        platform = innerAplayer.getAttribute('data-server') ||
                                   (innerAplayer.dataset && innerAplayer.dataset.server) ||
                                   'netease';
                        return platform;
                    }
                }
            }

            // 5. 尝试从player.list获取（可能包含平台信息）
            if (player && player.list && player.list.audios && player.list.audios.length > 0) {
                const firstAudio = player.list.audios[0];
                if (firstAudio.url) {
                    if (firstAudio.url.includes('music.163.com')) {
                        platform = 'netease';
                        return platform;
                    } else if (firstAudio.url.includes('qq.com')) {
                        platform = 'tencent';
                        return platform;
                    }
                }
            }

            return '';
        }

        /**
         * 生成简单hash
         */
        generateHash(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                const char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return Math.abs(hash).toString(36);
        }

        /**
         * 设置页面可见性处理
         */
        setupVisibilityHandler() {
            document.addEventListener('visibilitychange', () => {
                this.isPageVisible = !document.hidden;
                
                if (document.hidden) {
                    // 页面隐藏时暂停追踪
                    this.pauseTracking();
                } else {
                    // 页面显示时，如果正在播放，恢复追踪
                    if (this.currentTrack && this.players.some(p => !p.audio.paused)) {
                        this.playStartTime = Date.now();
                    }
                }
            });
        }

        /**
         * 设置页面卸载处理
         */
        setupUnloadHandler() {
            window.addEventListener('beforeunload', () => {
                // 页面卸载前上报数据
                this.stopTracking(false);
            });
        }
    }

// 初始化追踪器
if (typeof musicStatsData !== 'undefined') {
    new MusicStatsTracker();
} else {
    // 如果musicStatsData未定义，提供默认值
    window.musicStatsData = {
        ajaxUrl: typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php',
        nonce: ''
    };
    new MusicStatsTracker();
}

})(jQuery);
