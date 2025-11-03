(function($) {
    'use strict';

    // 如果QTags对象不存在，则不执行任何操作
    if (typeof QTags === 'undefined') {
        return;
    }

    const DoubanImporter = {
        modalId: 'wdd-book-modal',
        modal: null,

        // 初始化，创建或显示弹窗
        init: function() {
            if (!this.modal) {
                this.createModal();
            }
            this.modal.css('display', 'flex');
        },

        // 创建弹窗HTML并添加到body
        createModal: function() {
            const bookModalContent = `
                <div class="wdd-book-insert">
                    <p><strong>插入豆瓣书籍：</strong></p>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">豆瓣书籍URL：</label>
                        <input type="url" id="douban-url" placeholder="https://book.douban.com/subject/1827702/" style="width: 100%; padding: 5px;" autofocus>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px;">阅读状态：</label>
                        <select id="book-status-select" style="padding: 5px; width: 100%;">
                            <option value="read">已读</option>
                            <option value="reading">在读</option>
                            <option value="wish">想读</option>
                        </select>
                    </div>
                    <button class="button button-primary" id="insert-book-btn" style="width: 100%;">插入书籍</button>
                    <div id="book-status" style="margin-top: 10px; padding: 8px; border-radius: 4px; display: none;"></div>
                </div>
            `;

            const fullModalHtml = `
                <div id="${this.modalId}" class="wdd-modal-wrapper">
                    <div class="wdd-modal-content">
                        <h3>插入书籍信息</h3>
                        <div class="wdd-modal-body">${bookModalContent}</div>
                        <button class="button wdd-modal-close">关闭</button>
                    </div>
                </div>
            `;
            $('body').append(fullModalHtml);
            this.modal = $(`#${this.modalId}`);
            this.bindEvents();
        },

        // 绑定弹窗内的事件
        bindEvents: function() {
            this.modal.on('click', '#insert-book-btn', this.handleInsert.bind(this));
        },

        // 处理插入按钮点击事件
        handleInsert: function(e) {
            const $target = $(e.target);
            const doubanUrl = $('#douban-url').val().trim();
            const bookStatus = $('#book-status-select').val();

            if (!doubanUrl) {
                alert('请输入豆瓣书籍URL');
                return;
            }

            const $status = $('#book-status');
            $status.show().html('<span style="color: #007cba;">正在获取书籍信息...</span>');
            $target.prop('disabled', true).text('处理中...');

            this.fetchBookInfo(doubanUrl)
                .then(bookInfo => {
                    const escapeAttr = (value) => String(value).replace(/"/g, '\"');
                    const content = `[book url="${escapeAttr(doubanUrl)}" title="${escapeAttr(bookInfo.title)}" image="${escapeAttr(bookInfo.image)}" rating="${escapeAttr(bookInfo.rating)}" status="${escapeAttr(bookStatus)}"]`;
                    
                    QTags.insertContent(content);
                    $status.html('<span style="color: #28a745;">书籍已成功插入！</span>');
                    
                    setTimeout(() => {
                        this.modal.hide();
                    }, 1000);
                })
                .catch(error => {
                    console.error('豆瓣导入失败:', error);
                    $status.html(`<span style="color: #dc3545;">获取失败：${error.message}</span>`);
                })
                .finally(() => {
                    $target.prop('disabled', false).text('插入书籍');
                });
        },

        // 从豆瓣URL获取书籍信息
        fetchBookInfo: function(doubanUrl) {
            return new Promise((resolve, reject) => {
                if (!doubanUrl || !doubanUrl.includes('douban.com')) {
                    reject(new Error('请输入有效的豆瓣书籍URL'));
                    return;
                }
                const proxyUrl = 'https://api.allorigins.win/get?url=' + encodeURIComponent(doubanUrl);

                fetch(proxyUrl)
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        return response.json();
                    })
                    .then(data => {
                        const html = data.contents || data;
                        const bookInfo = this.parseBookInfo(html, doubanUrl);
                        resolve(bookInfo);
                    })
                    .catch(error => {
                        console.error('豆瓣导入失败:', error);
                        reject(new Error('获取书籍信息失败：' + error.message));
                    });
            });
        },

        // 解析HTML提取信息
        parseBookInfo: function(html, originalUrl) {
            const bookInfo = { title: '', image: '', rating: 5, url: originalUrl };
            try {
                const titleMatch = html.match(/<title>([^<]+)<\/title>/);
                if (titleMatch) {
                    bookInfo.title = titleMatch[1].replace(' (豆瓣)', '').trim();
                }

                const imageMatch = html.match(/property="og:image" content="([^"]+)"/);
                if (imageMatch) {
                    bookInfo.image = imageMatch[1];
                }

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
        }
    };

    // 将DoubanImporter暴露到全局，以便其他脚本调用
    window.WDDoubanImporter = DoubanImporter;

})(jQuery);
