/**
 * ===========================================
 * 编辑器豆瓣书籍模块
 * ===========================================
 *
 * 📚 功能说明
 *   - 豆瓣书籍搜索和导入
 *   - 书籍信息展示和插入
 *   - 与豆瓣API的交互
 *   - 自动生成书籍短代码
 *
 * 📋 核心功能
 *   - showModal(): 显示书籍导入Modal
 *   - insertBook(): 处理书籍插入
 *   - fetchBookInfo(): 获取豆瓣书籍信息
 *   - parseBookInfo(): 解析书籍数据
 *
 * 🔄 工作流程
 *   1. 用户输入豆瓣书籍链接
 *   2. 选择阅读状态
 *   3. 点击插入按钮
 *   4. 自动获取书籍信息
 *   5. 生成并插入短代码
 *
 * 🔗 依赖关系
 *   - jQuery (必需)
 *   - editor-modal.js (必需)
 *   - 豆瓣API (外部服务)
 *
 * 📁 文件位置
 *   assets/js/editor-doubanbook.js
 *
 * @author wangdaodao
 * @version 1.0.0
 * @date 2025-10-23
 */

(function($) {
    'use strict';

    // 豆瓣书籍Modal内容
    const doubanBookModalContent = `
        <div class="editor-douban-container">
            <div class="editor-douban-tabs">
                <button class="editor-douban-tab active" data-tab="single">单本书籍</button>
                <button class="editor-douban-tab" data-tab="batch">批量导入</button>
            </div>

            <div class="editor-douban-tab-content" data-tab="single">
                <div class="editor-douban-info">
                    <p>输入豆瓣书籍链接，系统将自动获取书籍信息并生成短代码。</p>
                    <div class="editor-douban-form">
                        <input type="url" id="editor-douban-url" placeholder="请输入豆瓣书籍链接" style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <select id="editor-douban-status" style="width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="reading">正在阅读</option>
                            <option value="read">已读</option>
                            <option value="want">想读</option>
                        </select>
                        <button id="editor-douban-insert-btn" class="button button-primary" style="width: 100%;">插入书籍</button>
                    </div>
                </div>
            </div>

            <div class="editor-douban-tab-content" data-tab="batch" style="display: none;">
                <div class="editor-douban-info">
                    <p>输入豆瓣用户主页链接，批量获取用户的读书数据。</p>
                    <div class="editor-douban-form">
                        <input type="url" id="editor-douban-user-url" placeholder="请输入豆瓣用户主页链接 (如: https://book.douban.com/people/用户名/)" style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <select id="editor-douban-batch-status" style="width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="all">全部书籍</option>
                            <option value="reading">在读</option>
                            <option value="read">已读</option>
                            <option value="want">想读</option>
                        </select>
                        <button id="editor-douban-batch-fetch-btn" class="button button-secondary" style="width: 100%;">获取书籍列表</button>
                        <div id="editor-douban-batch-results" style="display: none; margin-top: 15px;">
                            <div id="editor-douban-batch-list"></div>
                            <button id="editor-douban-batch-insert-btn" class="button button-primary" style="width: 100%; margin-top: 10px; display: none;">插入选中书籍</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // 豆瓣书籍功能管理器
    window.EditorDoubanBook = {
        /**
         * 显示豆瓣书籍Modal
         */
        showModal: function() {
            EditorModal.show('editor-douban-modal', '插入豆瓣书籍', doubanBookModalContent);
        },

        /**
         * 插入书籍
         */
        insertBook: function() {
            const doubanUrl = $('#editor-douban-url').val().trim();
            const bookStatus = $('#editor-douban-status').val();

            if (!doubanUrl) {
                EditorModal.showStatus('editor-douban-modal', '请输入豆瓣书籍链接', 'error');
                return;
            }

            if (!doubanUrl.includes('douban.com')) {
                EditorModal.showStatus('editor-douban-modal', '请输入有效的豆瓣书籍链接', 'error');
                return;
            }

            // 显示加载状态
            EditorModal.showStatus('editor-douban-modal', '正在获取书籍信息...', 'loading');
            $('#editor-douban-insert-btn').prop('disabled', true).text('获取中...');

            // 获取书籍信息
            EditorDoubanBook.fetchBookInfo(doubanUrl)
                .then(bookInfo => {
                    const escapeAttr = (value) => String(value).replace(/"/g, '\\"');
                    const content = `[book url="${escapeAttr(doubanUrl)}" title="${escapeAttr(bookInfo.title)}" image="${escapeAttr(bookInfo.image)}" rating="${escapeAttr(bookInfo.rating)}" status="${escapeAttr(bookStatus)}"]`;

                    EditorCore.insertContent(content);
                    EditorModal.showStatus('editor-douban-modal', '书籍已成功插入！', 'success');

                    // 延迟关闭modal
                    setTimeout(function() {
                        EditorModal.hide('editor-douban-modal');
                    }, 1500);
                })
                .catch(error => {
                    console.error('豆瓣导入失败:', error);
                    EditorModal.showStatus('editor-douban-modal', `获取失败：${error.message}`, 'error');
                })
                .finally(() => {
                    $('#editor-douban-insert-btn').prop('disabled', false).text('插入书籍');
                });
        },

        /**
         * 获取书籍信息
         */
        fetchBookInfo: function(doubanUrl) {
            return new Promise((resolve, reject) => {
                const proxyUrl = 'https://api.allorigins.win/get?url=' + encodeURIComponent(doubanUrl);

                fetch(proxyUrl)
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        return response.json();
                    })
                    .then(data => {
                        const html = data.contents || data;
                        const bookInfo = EditorDoubanBook.parseBookInfo(html, doubanUrl);
                        resolve(bookInfo);
                    })
                    .catch(error => {
                        reject(new Error('获取书籍信息失败：' + error.message));
                    });
            });
        },

        /**
         * 解析书籍信息
         */
        parseBookInfo: function(html, originalUrl) {
            const bookInfo = { title: '', image: '', rating: 5, url: originalUrl };

            try {
                // 解析标题
                const titleMatch = html.match(/<title>([^<]+)<\/title>/);
                if (titleMatch) {
                    bookInfo.title = titleMatch[1].replace(' (豆瓣)', '').trim();
                }

                // 解析图片
                const imageMatch = html.match(/property="og:image" content="([^"]+)"/);
                if (imageMatch) {
                    bookInfo.image = imageMatch[1];
                }

                // 解析评分
                const ratingMatch = html.match(/"v:average">\s*([\d.]+)/);
                if (ratingMatch) {
                    const rating = parseFloat(ratingMatch[1]);
                    if (rating >= 9.0) bookInfo.rating = 5;
                    else if (rating >= 8.0) bookInfo.rating = 4;
                    else if (rating >= 6.0) bookInfo.rating = 3;
                    else if (rating >= 4.0) bookInfo.rating = 2;
                    else bookInfo.rating = 1;
                }
            } catch (error) {
                console.error('解析豆瓣信息时出错:', error);
            }

            return bookInfo;
        },

        /**
         * 批量获取书籍列表
         */
        fetchBatchBooks: function() {
            const userUrl = $('#editor-douban-user-url').val().trim();
            const batchStatus = $('#editor-douban-batch-status').val();

            if (!userUrl) {
                EditorModal.showStatus('editor-douban-modal', '请输入豆瓣用户主页链接', 'error');
                return;
            }

            if (!userUrl.includes('book.douban.com/people/')) {
                EditorModal.showStatus('editor-douban-modal', '请输入有效的豆瓣用户主页链接', 'error');
                return;
            }

            // 显示加载状态
            EditorModal.showStatus('editor-douban-modal', '正在获取书籍列表...', 'loading');
            $('#editor-douban-batch-fetch-btn').prop('disabled', true).text('获取中...');

            // 构造请求URL
            let requestUrls = [];
            if (batchStatus === 'all') {
                // 获取所有状态的书籍
                const username = userUrl.match(/people\/([^\/]+)/)[1];
                requestUrls = [
                    `https://book.douban.com/people/${username}/do`,
                    `https://book.douban.com/people/${username}/collect`,
                    `https://book.douban.com/people/${username}/wish`
                ];
            } else {
                // 获取特定状态的书籍
                const statusMap = {
                    'reading': 'do',
                    'read': 'collect',
                    'want': 'wish'
                };
                const username = userUrl.match(/people\/([^\/]+)/)[1];
                requestUrls = [`https://book.douban.com/people/${username}/${statusMap[batchStatus]}`];
            }

            // 获取书籍列表
            EditorDoubanBook.fetchBooksFromPages(requestUrls, batchStatus)
                .then(books => {
                    EditorDoubanBook.displayBookList(books);
                    EditorModal.showStatus('editor-douban-modal', `成功获取 ${books.length} 本书籍`, 'success');
                })
                .catch(error => {
                    console.error('批量获取书籍失败:', error);
                    EditorModal.showStatus('editor-douban-modal', `获取失败：${error.message}。请检查网络连接或稍后重试。`, 'error');

                    // 失败后允许重新获取，清空并隐藏结果区域
                    $('#editor-douban-batch-list').empty();
                    $('#editor-douban-batch-results').hide();
                    $('#editor-douban-batch-insert-btn').hide();

                    // 确保按钮可以重新点击
                    $('#editor-douban-batch-fetch-btn').prop('disabled', false).text('重新获取书籍列表');
                })
                .finally(() => {
                    // 最终确保按钮状态正确
                    console.log('最终重置获取按钮状态');
                    if (!$('#editor-douban-batch-fetch-btn').prop('disabled')) {
                        $('#editor-douban-batch-fetch-btn').text('获取书籍列表');
                    }
                });
        },

        /**
         * 从多个页面获取书籍
         */
        fetchBooksFromPages: function(urls, batchStatus) {
            const promises = urls.map(url => EditorDoubanBook.fetchBooksFromPage(url, batchStatus));

            // 使用 Promise.allSettled 让失败的请求不影响成功的请求
            return Promise.allSettled(promises).then(results => {
                const successfulResults = results
                    .filter(result => result.status === 'fulfilled')
                    .map(result => result.value);

                const failedResults = results.filter(result => result.status === 'rejected');

                // 如果有失败的请求，记录警告但不中断整个操作
                if (failedResults.length > 0) {
                    console.warn(`${failedResults.length} 个页面获取失败，但将继续处理成功的页面`);
                    failedResults.forEach((result, index) => {
                        console.warn(`页面 ${urls[index]} 获取失败:`, result.reason);
                    });
                }

                return successfulResults.flat();
            });
        },

        /**
         * 从单个页面获取书籍
         */
        fetchBooksFromPage: function(pageUrl, batchStatus) {
            console.log('批量导入：开始获取页面', pageUrl);

            return new Promise((resolve, reject) => {
                // 使用和单本导入完全相同的逻辑
                const proxyUrl = 'https://api.allorigins.win/get?url=' + encodeURIComponent(pageUrl);
                console.log('批量导入：代理URL', proxyUrl);

                fetch(proxyUrl)
                    .then(response => {
                        console.log('批量导入：代理响应状态', response.status, response.statusText);
                        if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        return response.json();
                    })
                    .then(data => {
                        console.log('批量导入：收到数据，长度', data.contents ? data.contents.length : 'unknown');
                        const html = data.contents || data;
                        const books = EditorDoubanBook.parseBooksFromPage(html, batchStatus, pageUrl);
                        console.log('批量导入：解析到书籍数量', books.length);
                        resolve(books);
                    })
                    .catch(error => {
                        console.error('批量导入：获取页面失败', pageUrl, error);
                        reject(new Error('获取页面失败：' + error.message));
                    });
            });
        },

        /**
         * 从页面HTML解析书籍列表
         */
        parseBooksFromPage: function(html, batchStatus, pageUrl) {
            console.log('批量导入：开始解析页面，HTML长度', html.length, '页面URL', pageUrl);
            const books = [];

            try {
                // 根据页面URL判断阅读状态
                let status = 'read'; // 默认已读
                if (pageUrl.includes('/do')) {
                    status = 'reading';
                } else if (pageUrl.includes('/wish')) {
                    status = 'want';
                } else if (pageUrl.includes('/collect')) {
                    status = 'read';
                }

                // 如果是指定状态，使用指定的状态
                if (batchStatus !== 'all') {
                    status = batchStatus;
                }

                console.log('批量导入：页面状态判断为', status, 'batchStatus:', batchStatus);

                // 直接使用策略3进行宽松匹配
                console.log('批量导入：使用策略3进行宽松匹配');

                // 策略3：查找完整的书籍项（包含链接和图片的组合）
                const bookItemRegex = /<a[^>]*href="(https:\/\/book\.douban\.com\/subject\/\d+\/[^"]*)"[^>]*>.*?<img[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*><\/a>/g;

                const bookItems = [];
                let match;
                while ((match = bookItemRegex.exec(html)) !== null) {
                    // 确保图片URL是完整的豆瓣图片地址
                    let imageUrl = match[2];
                    if (imageUrl && !imageUrl.startsWith('http')) {
                        // 如果是相对路径，添加豆瓣图片域名前缀
                        imageUrl = 'https://img1.doubanio.com' + (imageUrl.startsWith('/') ? '' : '/') + imageUrl;
                    } else if (imageUrl && imageUrl.includes('doubanio.com') && !imageUrl.startsWith('http')) {
                        // 如果包含doubanio.com但没有http前缀，添加https
                        imageUrl = 'https:' + imageUrl;
                    }

                    // 如果图片URL有效，添加代理前缀
                    if (imageUrl && imageUrl.includes('doubanio.com')) {
                        imageUrl = 'https://images.weserv.nl/?url=' + encodeURIComponent(imageUrl);
                    }

                    bookItems.push({
                        url: match[1],
                        image: imageUrl,
                        title: match[3] || '未知标题'
                    });
                }

                console.log('批量导入：策略3 - 找到完整书籍项:', bookItems.length);
                console.log('批量导入：策略3 - 书籍项样本:', bookItems.slice(0, 3));

                // 处理找到的完整书籍项
                bookItems.forEach((book, index) => {
                    console.log('批量导入：策略3处理完整书籍项', index + 1, book.title, book.url);

                    // 跳过重复的书籍
                    const isDuplicate = books.some(existingBook => existingBook.url === book.url);
                    if (!isDuplicate) {
                        books.push({
                            url: book.url,
                            title: book.title,
                            image: book.image,
                            status: status,
                            rating: 5
                        });
                    }
                });

                // 如果没找到完整书籍项，尝试分别提取链接和图片进行配对
                if (books.length === 0) {
                    console.log('批量导入：策略3 - 未找到完整书籍项，尝试分别提取');

                    const looseLinkRegex = /https:\/\/book\.douban\.com\/subject\/\d+\//g;
                    const looseImgRegex = /https:\/\/img\d*\.doubanio\.com\/.*?\/subject\/.*?\/s\d+\.jpg/g;

                    const looseLinks = html.match(looseLinkRegex) || [];
                    const looseImgs = html.match(looseImgRegex) || [];

                    console.log('批量导入：策略3 - 分离链接:', looseLinks.length, '分离图片:', looseImgs.length);

                    // 取较小值进行配对
                    const minCount = Math.min(looseLinks.length, looseImgs.length);
                    console.log('批量导入：策略3 - 配对数量:', minCount);

                    for (let i = 0; i < minCount; i++) {
                        const bookUrl = looseLinks[i];
                        const originalBookImage = looseImgs[i]; // 保存原始图片URL用于查找

                        // 为图片URL添加代理前缀（与第一个方法保持一致）
                        let bookImage = originalBookImage;
                        if (bookImage && bookImage.includes('doubanio.com')) {
                            bookImage = 'https://images.weserv.nl/?url=' + encodeURIComponent(bookImage);
                        }

                        // 尝试从图片附近的文本提取标题（使用原始图片URL查找）
                        let bookTitle = '未知标题';
                        const imgIndex = html.indexOf(originalBookImage);
                        if (imgIndex > 0) {
                            // 在图片前后100个字符内查找可能的标题文本
                            const contextStart = Math.max(0, imgIndex - 100);
                            const contextEnd = Math.min(html.length, imgIndex + 200);
                            const context = html.substring(contextStart, contextEnd);

                            // 查找可能的标题模式
                            const titleMatch = context.match(/alt="([^"]*)"/) || context.match(/title="([^"]*)"/);
                            if (titleMatch) {
                                bookTitle = titleMatch[1];
                            }
                        }

                        console.log('批量导入：策略3配对书籍', i + 1, bookTitle, bookUrl);

                        // 跳过重复的书籍
                        const isDuplicate = books.some(existingBook => existingBook.url === bookUrl);
                        if (!isDuplicate) {
                            books.push({
                                url: bookUrl,
                                title: bookTitle,
                                image: bookImage,
                                status: status,
                                rating: 5
                            });
                        }
                    }
                }

                console.log('批量导入：最终书籍数量', books.length);

            } catch (error) {
                console.error('批量导入：解析书籍列表时出错:', error);
            }

            return books;
        },

        /**
         * 显示书籍列表
         */
        displayBookList: function(books) {
            const $list = $('#editor-douban-batch-list');
            $list.empty();

            if (books.length === 0) {
                $list.html('<p style="text-align: center; color: #666;">未找到书籍</p>');
                return;
            }

            // 按状态分组显示
            const statusGroups = {
                reading: { label: '在读', books: [] },
                read: { label: '已读', books: [] },
                want: { label: '想读', books: [] }
            };

            books.forEach(book => {
                if (statusGroups[book.status]) {
                    statusGroups[book.status].books.push(book);
                }
            });

            // 生成HTML
            let html = '';
            Object.keys(statusGroups).forEach(status => {
                const group = statusGroups[status];
                if (group.books.length > 0) {
                    html += `<h4 style="margin: 20px 0 10px 0; color: #495057; border-bottom: 1px solid #dee2e6; padding-bottom: 5px;">${group.label} (${group.books.length}本)</h4>`;
                    html += '<div class="editor-douban-book-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">';

                    group.books.forEach(book => {
                        html += `
                            <div class="editor-douban-book-item" style="border: 1px solid #dee2e6; border-radius: 8px; padding: 10px; background: #f8f9fa;">
                                <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" class="editor-douban-book-checkbox" data-book='${JSON.stringify(book).replace(/'/g, "'")}' style="margin-top: 2px;">
                                    <div style="flex: 1;">
                                        <img src="${book.image}" alt="${book.title}" style="width: 40px; height: 60px; object-fit: cover; border-radius: 4px; margin-bottom: 5px;">
                                        <div style="font-size: 12px; line-height: 1.3; color: #495057;">${book.title}</div>
                                    </div>
                                </label>
                            </div>
                        `;
                    });

                    html += '</div>';
                }
            });

            $list.html(html);
            $('#editor-douban-batch-results').show();
            $('#editor-douban-batch-insert-btn').show();
        },

        /**
         * 插入选中的书籍
         */
        insertSelectedBooks: function() {
            const selectedBooks = $('.editor-douban-book-checkbox:checked');

            if (selectedBooks.length === 0) {
                EditorModal.showStatus('editor-douban-modal', '请先选择要插入的书籍', 'error');
                return;
            }

            let content = '';
            selectedBooks.each(function() {
                const bookData = $(this).data('book');
                const escapeAttr = (value) => String(value).replace(/"/g, '\\"');
                content += `[book url="${escapeAttr(bookData.url)}" title="${escapeAttr(bookData.title)}" image="${escapeAttr(bookData.image)}" rating="${escapeAttr(bookData.rating)}" status="${escapeAttr(bookData.status)}"]\n\n`;
            });

            EditorCore.insertContent(content.trim());
            EditorModal.showStatus('editor-douban-modal', `已插入 ${selectedBooks.length} 本书籍`, 'success');

            // 延迟关闭modal
            setTimeout(function() {
                EditorModal.hide('editor-douban-modal');
            }, 1500);
        }
    };

    // 标签页切换
    $(document).on('click', '.editor-douban-tab', function(e) {
        e.preventDefault();
        const tabName = $(this).data('tab');

        // 切换标签页按钮状态
        $('.editor-douban-tab').removeClass('active');
        $(this).addClass('active');

        // 切换内容区域
        $('.editor-douban-tab-content').hide();
        $(`.editor-douban-tab-content[data-tab="${tabName}"]`).show();
    });

    // 单本书籍插入
    $(document).on('click', '#editor-douban-insert-btn', function(e) {
        e.preventDefault();
        EditorDoubanBook.insertBook();
    });

    // 批量获取书籍列表
    $(document).on('click', '#editor-douban-batch-fetch-btn', function(e) {
        e.preventDefault();
        EditorDoubanBook.fetchBatchBooks();
    });

    // 批量插入选中书籍
    $(document).on('click', '#editor-douban-batch-insert-btn', function(e) {
        e.preventDefault();
        EditorDoubanBook.insertSelectedBooks();
    });

    console.log('编辑器豆瓣书籍模块已加载');

})(jQuery);
