/**
 * 自定义APlayer播放器 - 类似APlayer外观的HTML5音频播放器（优化版）
 * 完全基于原生HTML5 audio元素，无第三方依赖
 *
 * 功能特性：
 * - 类似APlayer的外观设计，通过JS自动注入CSS
 * - 完整的播放控制功能（已优化）
 * - 播放列表支持（含分页），布局已修复
 * - 进度条拖拽和音量控制
 * - 播放状态记忆
 * - 性能优化（requestAnimationFrame）
 *
 * @version 2.2.0
 * @date 2025-10-27
 */
(function(window, document) {
    'use strict';

    // 播放器所需CSS - 精简版，减少内存占用
    const playerCSS = `
        .custom-aplayer{background:#fff;border-radius:4px;box-shadow:0 2px 6px rgba(0,0,0,0.15);overflow:hidden;font-family:Arial,sans-serif;position:relative}
        .custom-aplayer.loading{opacity:0.7;pointer-events:none}
        .custom-aplayer.loading::after{content:'';position:absolute;top:50%;left:50%;width:20px;height:20px;margin:-10px 0 0 -10px;border:2px solid #f3f3f3;border-top:2px solid #007cba;border-radius:50%;animation:spin 1s linear infinite;z-index:10}
        @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
        .aplayer-body{display:flex;height:66px;align-items:center}
        .aplayer-pic{width:66px;height:66px;display:flex;align-items:center;justify-content:center;position:relative;flex-shrink:0}
        .aplayer-pic img{width:100%;height:100%;object-fit:cover;border-radius:4px}
        .aplayer-pic .aplayer-button{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:28px;height:28px;background:rgba(0,0,0,0.5);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;opacity:0;transition:opacity 0.3s}
        .aplayer-pic:hover .aplayer-button{opacity:1}
        .aplayer-pic .aplayer-button .aplayer-icon-play,.aplayer-pic .aplayer-button .aplayer-icon-pause{border:none;width:0;height:0;background:transparent}
        .aplayer-pic .aplayer-button .aplayer-icon-play{border-left:8px solid #fff;border-top:6px solid transparent;border-bottom:6px solid transparent;margin-left:3px}
        .aplayer-pic .aplayer-button .aplayer-icon-pause{width:10px;height:10px;border-left:3px solid #fff;border-right:3px solid #fff}
        .aplayer-info{flex:1;display:flex;flex-direction:column;justify-content:center;padding:0 10px;overflow:hidden}
        .aplayer-music{display:flex;align-items:center}
        .aplayer-music .aplayer-icon-play,.aplayer-music .aplayer-icon-pause{width:32px;height:32px;cursor:pointer;margin-right:12px}
        .aplayer-text{flex:1;overflow:hidden}
        .aplayer-title-author{display:flex;align-items:center}
        .aplayer-title,.aplayer-author{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .aplayer-title{font-size:14px;color:#333;font-weight:500;margin-right:8px}
        .aplayer-author{font-size:12px;color:#666}
        .aplayer-controller{margin-top:4px}
        .aplayer-bar-wrap{position:relative;height:3px;background:#e1e8ed;border-radius:1.5px;cursor:pointer}
        .aplayer-bar{position:absolute;left:0;top:0;height:100%;background:#007cba;border-radius:1.5px;width:0%;transition:width 0.1s ease}
        .aplayer-time{display:flex;justify-content:space-between;font-size:11px;color:#666;margin-top:4px}
        .aplayer-control{display:flex;align-items:center;gap:8px;padding-right:12px}
        .aplayer-control .aplayer-icon{width:24px;height:24px;cursor:pointer;opacity:0.6;transition:opacity 0.2s}
        .aplayer-control .aplayer-icon:hover{opacity:1}
        .aplayer-control .aplayer-icon.active{opacity:1;color:#007cba}
        .aplayer-volume{display:flex;align-items:center;gap:4px}
        .aplayer-volume-bar-wrap{width:60px;height:3px;background:#e1e8ed;border-radius:1.5px;cursor:pointer;position:relative}
        .aplayer-volume-bar{position:absolute;left:0;top:0;height:100%;background:#007cba;border-radius:1.5px;width:70%}
        .aplayer-volume-icon{width:16px;height:16px;cursor:pointer;opacity:.6;transition:opacity .2s}
        .aplayer-volume-icon:hover{opacity:1}
        .aplayer-mode-icon{width:20px;height:20px;cursor:pointer;opacity:.6;transition:opacity .2s}
        .aplayer-mode-icon:hover{opacity:1}
        .aplayer-list{max-height:270px;overflow-y:auto;border-top:1px solid #e1e8ed;display:none}
        .aplayer-list-item{padding:8px 12px;border-bottom:1px solid #f5f5f5;cursor:pointer;transition:background .2s;display:flex;align-items:center}
        .aplayer-list-item:hover{background:#f0f0f0}
        .aplayer-list-light{background:#e6f7ff!important}
        .aplayer-list-index{width:18px;text-align:center;color:#666;font-size:11px;margin-right:8px;flex-shrink:0}
        .aplayer-list-music{display:flex;justify-content:space-between;align-items:center;flex:1;min-width:0}
        .aplayer-list-title{font-size:13px;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;margin-right:8px}
        .aplayer-list-author{font-size:12px;color:#666;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex-shrink:0;max-width:80px}
        .aplayer-list-pagination{padding:10px;text-align:center;border-top:1px solid #f5f5f5}
        .aplayer-page-btn{margin:0 5px;padding:4px 10px;border:1px solid #ddd;background:#fff;cursor:pointer;border-radius:3px;font-size:12px}
        .aplayer-page-btn:hover{background:#f5f5f5}
        .aplayer-page-btn:disabled{opacity:.5;cursor:not-allowed}
        .aplayer-error{padding:10px;text-align:center;color:#f00;font-size:12px}
        .aplayer-loading{padding:10px;text-align:center;color:#666;font-size:12px}
    `;

    // 播放模式常量
    const PLAY_MODE = {
        ORDER: 'order',      // 顺序播放
        LOOP: 'loop',        // 列表循环
        SHUFFLE: 'shuffle',  // 随机播放
        SINGLE: 'single'     // 单曲循环
    };

    class CustomAPlayer {
        constructor(options) {
            this.options = Object.assign({
                startIndex: 0,
                autoplay: true,
                volume: 0.7,
                songs: [],
                pageSize: 50,
                remember: true,         // 是否记住播放状态
                loadSong: null,         // 歌曲加载函数，用于按需加载单首歌曲
                loadPlaylist: null,     // 播放列表加载函数，用于按需加载整个列表
                batchSize: 10           // 批量加载大小，默认10首
            }, options);

            this.container = document.getElementById(this.options.containerId);
            if (!this.container) {
                console.error('CustomAPlayer: Container not found');
                return;
            }

            // 初始化播放器状态
            this.songs = this.options.songs;
            this.currentIndex = this.options.startIndex;
            this.isPlaying = false;
            this.showList = false;
            this.currentPage = 0;
            this.pageSize = this.options.pageSize;
            this.volume = this.options.volume;
            this.isMuted = false;
            this.animationFrameId = null;
            this.playMode = PLAY_MODE.ORDER; // 默认顺序播放
            this.batchSize = this.options.batchSize || 10; // 批量大小
            this.loadedCount = 0; // 已加载的歌曲数量
            this.isLoadingMore = false; // 是否正在加载更多歌曲

            // 加载记忆状态
            if (this.options.remember) {
                this._loadState();
            }

            this.elements = {};
            this._init();
        }

        _init() {
            this._injectCSS();
            this._render();
            this._bindEvents();
            this._updateDisplay();
            this._updateModeIcon(); // 初始化播放模式图标
            this._applySavedState(); // 应用保存的状态到UI

            // 不再自动加载播放列表，等待用户点击播放时再加载
            // 只有在有预设歌曲数据时才自动播放
            if (this.options.autoplay && this.songs.length > 0) {
                setTimeout(() => this.play(), 200);
            }
        }

        _injectCSS() {
            const styleId = 'custom-aplayer-style';
            if (!document.getElementById(styleId)) {
                const style = document.createElement('style');
                style.id = styleId;
                style.innerHTML = playerCSS;
                document.head.appendChild(style);
            }
        }

        _render() {
            this.container.innerHTML = '';
            const player = this._createElement('div', 'custom-aplayer');

            const initialSong = this.songs[this.currentIndex] || { name: '未加载', artist: '', cover: '', url: '' };
            // 使用与PHP一致的默认封面路径
            const defaultCover = '/wp-content/themes/barepaper-v7.0.0/images/album.svg';

            player.innerHTML = `
                <div class="aplayer-body">
                    <div class="aplayer-pic" data-action="togglePlay">
                        <img src="${initialSong.cover || defaultCover}" alt="专辑封面">
                        <div class="aplayer-button">
                            <div class="aplayer-icon-play"></div>
                        </div>
                    </div>
                    <div class="aplayer-info">
                        <div class="aplayer-music">
                            <div class="aplayer-icon aplayer-icon-play" title="播放/暂停" data-action="togglePlay">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                            </div>
                            <div class="aplayer-text">
                                <div class="aplayer-title-author">
                                    <span class="aplayer-title">${initialSong.name}</span>
                                    <span class="aplayer-author">${initialSong.artist}</span>
                                </div>
                                <div class="aplayer-controller">
                                    <div class="aplayer-bar-wrap" data-action="seek">
                                        <div class="aplayer-bar"></div>
                                    </div>
                                    <div class="aplayer-time">
                                        <span class="aplayer-ptime">00:00</span>
                                        <span class="aplayer-dtime">00:00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="aplayer-control">
                        <div class="aplayer-volume">
                            <div class="aplayer-volume-icon" data-action="toggleMute" title="音量">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"></path></svg>
                            </div>
                            <div class="aplayer-volume-bar-wrap" data-action="setVolume">
                                <div class="aplayer-volume-bar"></div>
                            </div>
                        </div>
                        <div class="aplayer-icon aplayer-icon-prev" title="上一首" data-action="playPrev">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"></path></svg>
                        </div>
                         <div class="aplayer-icon aplayer-icon-play" title="播放/暂停" data-action="togglePlay">
                             <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                        </div>
                        <div class="aplayer-icon aplayer-icon-next" title="下一首" data-action="playNext">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"></path></svg>
                        </div>
                        <div class="aplayer-mode-icon" data-action="toggleMode" title="播放模式">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"></path></svg>
                        </div>
                        <div class="aplayer-icon aplayer-icon-list" title="切换列表" data-action="toggleList">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"></path></svg>
                        </div>
                    </div>
                </div>
                <div class="aplayer-list"></div>
                <audio preload="metadata" src="${initialSong.url}"></audio>
            `;

            this.container.appendChild(player);

            // 缓存元素
            this.elements = {
                player,
                audio: player.querySelector('audio'),
                picImg: player.querySelector('.aplayer-pic img'),
                picButtonIcon: player.querySelector('.aplayer-pic .aplayer-button div'),
                playBtnIcon: player.querySelector('.aplayer-music .aplayer-icon-play'),
                controlPlayBtn: player.querySelector('.aplayer-control .aplayer-icon-play'),
                listBtn: player.querySelector('.aplayer-icon-list'),
                modeBtn: player.querySelector('.aplayer-mode-icon'),
                volumeIcon: player.querySelector('.aplayer-volume-icon'),
                volumeBar: player.querySelector('.aplayer-volume-bar'),
                progressBar: player.querySelector('.aplayer-bar'),
                currentTimeEl: player.querySelector('.aplayer-ptime'),
                durationEl: player.querySelector('.aplayer-dtime'),
                titleEl: player.querySelector('.aplayer-title'),
                authorEl: player.querySelector('.aplayer-author'),
                listContainer: player.querySelector('.aplayer-list'),
            };

            // 设置初始音量
            this._setVolume(this.volume);
        }

        _bindEvents() {
            const { audio, player, listContainer } = this.elements;

            // 使用事件委托处理所有播放器控制
            player.addEventListener('click', (e) => {
                const target = e.target.closest('[data-action]');
                if (!target) return;

                const action = target.dataset.action;
                switch (action) {
                    case 'togglePlay': this.togglePlay(); break;
                    case 'playPrev': this.playPrev(); break;
                    case 'playNext': this.playNext(); break;
                    case 'toggleList': this.toggleList(); break;
                    case 'toggleMute': this.toggleMute(); break;
                    case 'toggleMode': this.toggleMode(); break;
                    case 'seek': this.seek(e); break;
                    case 'setVolume': this.setVolume(e); break;
                }
            });

            // 列表的事件委托
            listContainer.addEventListener('click', (e) => {
                const item = e.target.closest('.aplayer-list-item');
                const pageBtn = e.target.closest('.aplayer-page-btn');

                if (item) {
                    this.playSong(parseInt(item.dataset.index, 10));
                }
                if (pageBtn) {
                    this.currentPage = parseInt(pageBtn.dataset.page, 10);
                    this._renderSongList();
                }
            });

            // 音频事件
            audio.addEventListener('loadedmetadata', () => this._onLoadedMetadata());
            audio.addEventListener('timeupdate', () => this._updateProgress());
            audio.addEventListener('ended', () => this._onEnded());
            audio.addEventListener('play', () => this._onPlay());
            audio.addEventListener('pause', () => this._onPause());
            audio.addEventListener('error', (e) => this._onError(e));
            audio.addEventListener('canplay', () => this._onCanPlay());

            // 窗口事件（保存状态）
            window.addEventListener('beforeunload', () => this._saveState());

            // 触摸事件支持
            this._bindTouchEvents();
        }

        async play() {
            if (!this.songs.length) return;
            const playPromise = this.elements.audio.play();
            if (playPromise !== undefined) {
                playPromise.catch(error => {
                    console.error("自动播放失败，需要用户交互:", error);
                    this._onPause();
                });
            }
        }

        pause() { this.elements.audio.pause(); }

        async togglePlay() {
            if (this.isPlaying) {
                this.pause();
            } else {
                // 如果还没有加载播放列表，先加载
                if (this.options.loadPlaylist && this.songs.length === 0) {
                    try {
                        this.elements.player.classList.add('loading');
                        this.elements.titleEl.textContent = '正在加载音乐列表...';
                        this.elements.authorEl.textContent = '请稍候';

                        const playlist = await this.options.loadPlaylist();
                        if (playlist && Array.isArray(playlist) && playlist.length > 0) {
                            this.songs = playlist;
                            this.currentIndex = 0; // 确保从第一首开始
                            this._updateDisplay();
                            this._renderSongList();

                            // 预加载前几首歌曲
                            this._preloadNextSongs(0, 3);

                            // 显示加载完成信息
                            this.elements.titleEl.textContent = `已加载 ${playlist.length} 首歌曲`;
                            this.elements.authorEl.textContent = '准备播放';

                            // 短暂延迟后开始播放
                            setTimeout(() => {
                                this.elements.player.classList.remove('loading');
                                this.play();
                            }, 500);
                            return;
                        } else {
                            throw new Error('未获取到歌曲数据');
                        }
                    } catch (error) {
                        console.error('加载播放列表失败:', error);
                        this.elements.titleEl.textContent = '加载失败';
                        this.elements.authorEl.textContent = '请检查网络或重试';
                        this.elements.player.classList.remove('loading');
                        
                        // 提供重试机制
                        setTimeout(() => {
                            this.elements.titleEl.textContent = '点击重试';
                            this.elements.authorEl.textContent = '点击播放按钮重试加载';
                        }, 2000);
                        return;
                    }
                }
                this.play();
            }
        }
        playPrev() { this.playSong(this._getPrevIndex()); }
        playNext() { this.playSong(this._getNextIndex()); }

        async playSong(index) {
            if (index < 0 || index >= this.songs.length || this.currentIndex === index) return;

            const song = this.songs[index];
            if (!song) return;

            // 检查歌曲是否需要加载
            if (this.options.loadSong && !song.url) {
                try {
                    this.elements.player.classList.add('loading');
                    this.elements.titleEl.textContent = '正在加载...';
                    this.elements.authorEl.textContent = song.artist || '';

                    const loadedSong = await this.options.loadSong(song, index);
                    if (loadedSong && loadedSong.url) {
                        this.songs[index] = { ...song, ...loadedSong };
                    } else {
                        throw new Error('加载失败');
                    }
                } catch (error) {
                    console.error('加载歌曲失败:', error);
                    this.elements.player.classList.remove('loading');
                    this.elements.titleEl.textContent = '加载失败';
                    this.elements.authorEl.textContent = '请重试';
                    return;
                }
            }

            this.currentIndex = index;
            this._updateDisplay();
            this.play();
        }

        seek(event) {
            const wrap = event.currentTarget; // The element with data-action="seek"
            const rect = wrap.getBoundingClientRect();
            const percent = (event.clientX - rect.left) / rect.width;
            this.elements.audio.currentTime = percent * this.elements.audio.duration;
        }

        toggleList() {
            this.showList = !this.showList;
            if (this.showList) {
                this._renderSongList();
                this.elements.listContainer.style.display = 'block';
                this.elements.listBtn.style.opacity = '1';
            } else {
                this.elements.listContainer.style.display = 'none';
                this.elements.listBtn.style.opacity = '0.6';
            }
        }

        _onPlay() {
            this.isPlaying = true;
            this.elements.player.classList.add('playing');
            this.elements.playBtnIcon.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"></path></svg>';
            this.elements.picButtonIcon.className = 'aplayer-icon-pause';

            // 播放开始时，预加载下一首歌曲
            this._preloadNextSong();
        }

        _onPause() {
            this.isPlaying = false;
            this.elements.player.classList.remove('playing');
            this.elements.playBtnIcon.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>';
            this.elements.picButtonIcon.className = 'aplayer-icon-play';
        }

        _onLoadedMetadata() { this.elements.durationEl.textContent = this._formatTime(this.elements.audio.duration); }
        _onTimeUpdate() {
            const { audio, progressBar, currentTimeEl } = this.elements;
            if (isFinite(audio.duration)) {
                const percent = (audio.currentTime / audio.duration) * 100;
                progressBar.style.width = `${percent}%`;
            }
            currentTimeEl.textContent = this._formatTime(audio.currentTime);
        }

        _updateDisplay() {
            const song = this.songs[this.currentIndex];
            if (!song) return;

            const { titleEl, authorEl, picImg, audio } = this.elements;
            titleEl.textContent = song.name;
            authorEl.textContent = song.artist;
            // 使用与PHP一致的默认封面路径
            picImg.src = song.cover || '/wp-content/themes/barepaper-v7.0.0/images/album.svg';

            if (decodeURI(audio.src) !== song.url) {
                audio.src = song.url;
            }

            if (this.showList) this._updateListHighlight();
        }

        _updateListHighlight() {
            this.elements.listContainer.querySelectorAll('.aplayer-list-item').forEach(item => {
                item.classList.toggle('aplayer-list-light', parseInt(item.dataset.index, 10) === this.currentIndex);
            });
        }

        _renderSongList() {
            const startIndex = this.currentPage * this.pageSize;
            const endIndex = Math.min(startIndex + this.pageSize, this.songs.length);
            const currentSongs = this.songs.slice(startIndex, endIndex);

            let listHtml = currentSongs.map((song, index) => {
                const globalIndex = startIndex + index;
                return `
                    <div class="aplayer-list-item" data-index="${globalIndex}">
                        <div class="aplayer-list-index">${globalIndex + 1}</div>
                        <div class="aplayer-list-music">
                            <div class="aplayer-list-title">${song.name}</div>
                            <div class="aplayer-list-author">${song.artist}</div>
                        </div>
                    </div>`;
            }).join('');

            if (this.songs.length > this.pageSize) {
                const totalPages = Math.ceil(this.songs.length / this.pageSize);
                let paginationHtml = '<div class="aplayer-list-pagination">';
                paginationHtml += `<button class="aplayer-page-btn" data-page="${this.currentPage - 1}" ${this.currentPage === 0 ? 'disabled' : ''}>上一页</button>`;
                paginationHtml += `<span>${this.currentPage + 1} / ${totalPages}</span>`;
                paginationHtml += `<button class="aplayer-page-btn" data-page="${this.currentPage + 1}" ${this.currentPage === totalPages - 1 ? 'disabled' : ''}>下一页</button>`;
                paginationHtml += '</div>';
                listHtml += paginationHtml;
            }

            this.elements.listContainer.innerHTML = listHtml;
            this._updateListHighlight();
        }

        _formatTime(seconds) {
            if (isNaN(seconds)) return '00:00';
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        _createElement(tag, className) {
            const element = document.createElement(tag);
            if (className) element.className = className;
            return element;
        }

        // 音量控制方法
        toggleMute() {
            this.isMuted = !this.isMuted;
            this._setVolume(this.isMuted ? 0 : this.volume);
            this._updateVolumeIcon();
        }

        setVolume(event) {
            const wrap = event.currentTarget;
            const rect = wrap.getBoundingClientRect();
            const percent = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
            this.volume = percent;
            this.isMuted = percent === 0;
            this._setVolume(percent);
            this._updateVolumeIcon();
        }

        _setVolume(volume) {
            this.elements.audio.volume = volume;
            this.elements.volumeBar.style.width = `${volume * 100}%`;
        }

        _updateVolumeIcon() {
            const icon = this.elements.volumeIcon;
            if (this.isMuted || this.volume === 0) {
                icon.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"></path></svg>';
            } else if (this.volume < 0.5) {
                icon.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM12 4L9.91 6.09 12 8.18V4z M4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3z"></path></svg>';
            } else {
                icon.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"></path></svg>';
            }
        }

        // 播放模式控制
        toggleMode() {
            const modes = Object.values(PLAY_MODE);
            const currentIndex = modes.indexOf(this.playMode);
            this.playMode = modes[(currentIndex + 1) % modes.length];
            this._updateModeIcon();
            this._saveState();
        }

        _updateModeIcon() {
            const icon = this.elements.modeBtn;
            switch (this.playMode) {
                case PLAY_MODE.ORDER:
                    icon.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"></path></svg>';
                    icon.title = '顺序播放';
                    break;
                case PLAY_MODE.LOOP:
                    icon.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4zm-4-2V9h2v6h-2z"></path></svg>';
                    icon.title = '列表循环';
                    break;
                case PLAY_MODE.SHUFFLE:
                    icon.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"></path></svg>';
                    icon.title = '随机播放';
                    break;
                case PLAY_MODE.SINGLE:
                    icon.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4zm-4-2V9h2v6h-2z"></path></svg>';
                    icon.title = '单曲循环';
                    break;
            }
        }

        // 获取下一首歌曲索引（根据播放模式）
        _getNextIndex() {
            switch (this.playMode) {
                case PLAY_MODE.SHUFFLE:
                    return Math.floor(Math.random() * this.songs.length);
                case PLAY_MODE.SINGLE:
                    return this.currentIndex;
                case PLAY_MODE.LOOP:
                default:
                    return (this.currentIndex + 1) % this.songs.length;
            }
        }

        // 获取上一首歌曲索引（根据播放模式）
        _getPrevIndex() {
            switch (this.playMode) {
                case PLAY_MODE.SHUFFLE:
                    return Math.floor(Math.random() * this.songs.length);
                case PLAY_MODE.SINGLE:
                    return this.currentIndex;
                case PLAY_MODE.LOOP:
                default:
                    return (this.currentIndex - 1 + this.songs.length) % this.songs.length;
            }
        }

        // 预加载下一首歌曲
        async _preloadNextSong() {
            if (!this.options.loadSong) return;
            
            const nextIndex = this._getNextIndex();
            if (nextIndex === this.currentIndex) return;
            
            const nextSong = this.songs[nextIndex];
            if (!nextSong || nextSong.url) return; // 如果已经有URL，不需要预加载
            
            try {
                const loadedSong = await this.options.loadSong(nextSong, nextIndex);
                if (loadedSong && loadedSong.url) {
                    this.songs[nextIndex] = { ...nextSong, ...loadedSong };
                    console.log(`预加载下一首歌曲成功: ${loadedSong.name}`);
                }
            } catch (error) {
                console.warn(`预加载下一首歌曲失败: ${error.message}`);
            }
        }

        // 预加载多首歌曲
        async _preloadNextSongs(startIndex, count = 3) {
            if (!this.options.loadSong) return;
            
            const preloadPromises = [];
            for (let i = 1; i <= count; i++) {
                const index = (startIndex + i) % this.songs.length;
                const song = this.songs[index];
                if (song && !song.url) {
                    preloadPromises.push(this._preloadSingleSong(song, index));
                }
            }
            
            if (preloadPromises.length > 0) {
                Promise.allSettled(preloadPromises).then(results => {
                    let successCount = 0;
                    results.forEach((result) => {
                        if (result.status === 'fulfilled') {
                            successCount++;
                        }
                    });
                    console.log(`预加载完成: ${successCount}/${preloadPromises.length} 首歌曲成功`);
                });
            }
        }

        // 预加载单首歌曲
        async _preloadSingleSong(song, index) {
            try {
                const loadedSong = await this.options.loadSong(song, index);
                if (loadedSong && loadedSong.url) {
                    this.songs[index] = { ...song, ...loadedSong };
                    return true;
                }
            } catch (error) {
                console.warn(`预加载歌曲失败 (索引 ${index}): ${error.message}`);
                return false;
            }
        }

        // 检查是否需要批量加载更多歌曲
        async _checkAndLoadMoreSongs() {
            if (this.isLoadingMore || !this.options.loadPlaylist) return;

            // 当播放到第10、20、30...首时，批量加载下10首
            const currentBatch = Math.floor(this.currentIndex / this.batchSize) + 1;
            const shouldLoadMore = (this.currentIndex + 1) % this.batchSize === 0;

            if (shouldLoadMore && this.songs.length <= currentBatch * this.batchSize) {
                this.isLoadingMore = true;

                try {
                    console.log(`正在批量加载第${currentBatch + 1}批歌曲...`);
                    const moreSongs = await this.options.loadPlaylist();

                    if (moreSongs && Array.isArray(moreSongs) && moreSongs.length > 0) {
                        // 合并新歌曲到现有列表
                        this.songs = [...this.songs, ...moreSongs];
                        this._renderSongList(); // 重新渲染列表
                        console.log(`批量加载完成，当前共${this.songs.length}首歌曲`);
                    }
                } catch (error) {
                    console.warn('批量加载更多歌曲失败:', error);
                } finally {
                    this.isLoadingMore = false;
                }
            }
        }

        // 应用保存的状态到UI
        _applySavedState() {
            // 应用音量设置
            this._setVolume(this.volume);
            this._updateVolumeIcon();

            // 应用播放模式图标
            this._updateModeIcon();

            // 如果有保存的播放状态，设置播放按钮样式
            // 注意：这里不自动开始播放，只是设置UI状态
            if (this.isPlaying) {
                this.elements.player.classList.add('playing');
                this.elements.playBtnIcon.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"></path></svg>';
                this.elements.picButtonIcon.className = 'aplayer-icon-pause';
            } else {
                this.elements.player.classList.remove('playing');
                this.elements.playBtnIcon.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>';
                this.elements.picButtonIcon.className = 'aplayer-icon-play';
            }
        }



        // 触摸事件支持
        _bindTouchEvents() {
            const progressWrap = this.elements.player.querySelector('.aplayer-bar-wrap');
            const volumeWrap = this.elements.player.querySelector('.aplayer-volume-bar-wrap');

            // 触摸进度条
            progressWrap.addEventListener('touchstart', (e) => {
                e.preventDefault();
                this._handleTouch(e, progressWrap, (percent) => {
                    this.elements.audio.currentTime = percent * this.elements.audio.duration;
                });
            });

            // 触摸音量条
            volumeWrap.addEventListener('touchstart', (e) => {
                e.preventDefault();
                this._handleTouch(e, volumeWrap, (percent) => {
                    this.volume = percent;
                    this.isMuted = percent === 0;
                    this._setVolume(percent);
                    this._updateVolumeIcon();
                });
            });
        }

        _handleTouch(event, element, callback) {
            const touch = event.touches[0];
            const rect = element.getBoundingClientRect();
            const percent = Math.max(0, Math.min(1, (touch.clientX - rect.left) / rect.width));
            callback(percent);
        }

        // 性能优化：使用requestAnimationFrame更新进度
        _updateProgress() {
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId);
            }

            this.animationFrameId = requestAnimationFrame(() => {
                const { audio, progressBar, currentTimeEl } = this.elements;
                if (isFinite(audio.duration)) {
                    const percent = (audio.currentTime / audio.duration) * 100;
                    progressBar.style.width = `${percent}%`;
                }
                currentTimeEl.textContent = this._formatTime(audio.currentTime);
            });
        }

        // 音频事件处理
        _onEnded() {
            // 检查是否需要批量加载更多歌曲
            this._checkAndLoadMoreSongs();

            // 根据播放模式自动播放下一首
            const nextIndex = this._getNextIndex();
            this.playSong(nextIndex);
        }

        _onError(event) {
            console.error('音频播放错误:', event);
            this.elements.player.classList.add('error');
            
            // 显示错误信息并提供重试选项
            this.elements.titleEl.textContent = '播放失败';
            this.elements.authorEl.textContent = '点击重试';
            
            // 添加重试机制
            const currentSong = this.songs[this.currentIndex];
            if (currentSong && this.options.loadSong) {
                // 设置重试点击事件
                this.elements.titleEl.style.cursor = 'pointer';
                this.elements.titleEl.onclick = () => {
                    this._retryLoadSong(currentSong, this.currentIndex);
                };
            }
        }

        // 重试加载歌曲
        async _retryLoadSong(song, index) {
            try {
                this.elements.player.classList.add('loading');
                this.elements.titleEl.textContent = '正在重试加载...';
                this.elements.authorEl.textContent = '请稍候';
                
                const loadedSong = await this.options.loadSong(song, index);
                if (loadedSong && loadedSong.url) {
                    this.songs[index] = { ...song, ...loadedSong };
                    this._updateDisplay();
                    this.play();
                } else {
                    throw new Error('重试加载失败');
                }
            } catch (error) {
                console.error('重试加载失败:', error);
                this.elements.titleEl.textContent = '重试失败';
                this.elements.authorEl.textContent = '请检查网络连接';
            } finally {
                this.elements.player.classList.remove('loading');
                // 移除点击事件
                this.elements.titleEl.style.cursor = '';
                this.elements.titleEl.onclick = null;
            }
        }

        _onCanPlay() {
            this.elements.player.classList.remove('loading', 'error');
        }

        // 状态记忆
        _loadState() {
            try {
                const state = JSON.parse(localStorage.getItem('custom-aplayer-state') || '{}');
                if (state.volume !== undefined) {
                    this.volume = state.volume;
                }
                if (state.isMuted !== undefined) {
                    this.isMuted = state.isMuted;
                }
                if (state.playMode && Object.values(PLAY_MODE).includes(state.playMode)) {
                    this.playMode = state.playMode;
                }
                if (state.currentIndex !== undefined && state.currentIndex >= 0 && state.currentIndex < this.songs.length) {
                    this.currentIndex = state.currentIndex;
                }
                // 恢复播放状态（但不自动播放）
                if (state.isPlaying !== undefined) {
                    this.isPlaying = state.isPlaying;
                }
            } catch (e) {
                console.warn('加载播放器状态失败:', e);
            }
        }

        _saveState() {
            if (!this.options.remember) return;

            try {
                const state = {
                    volume: this.volume,
                    isMuted: this.isMuted,
                    playMode: this.playMode,
                    currentIndex: this.currentIndex,
                    isPlaying: this.isPlaying,
                    timestamp: Date.now()
                };
                localStorage.setItem('custom-aplayer-state', JSON.stringify(state));
            } catch (e) {
                console.warn('保存播放器状态失败:', e);
            }
        }

        // 销毁播放器
        destroy() {
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId);
            }

            // 移除事件监听器
            window.removeEventListener('beforeunload', this._saveState);

            // 停止播放
            this.pause();
            this.elements.audio.src = '';

            // 清空容器
            this.container.innerHTML = '';
        }
    }

    window.initCustomAPlayer = function(config) {
        return new CustomAPlayer(config);
    };

})(window, document);
