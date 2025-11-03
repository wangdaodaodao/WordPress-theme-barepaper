(function() {
    'use strict';

    /**
     * 高级懒加载系统
     * 支持Intersection Observer API，提供更好的性能和用户体验
     */
    class AdvancedLazyLoad {
        constructor() {
            this.observer = null;
            this.config = {
                rootMargin: '50px 0px',
                threshold: 0.1
            };
            this.init();
        }

        init() {
            // 检查浏览器是否支持Intersection Observer
            if ('IntersectionObserver' in window) {
                this.setupObserver();
            } else {
                // 降级方案：直接加载所有图片
                this.loadAllImages();
            }
        }

        setupObserver() {
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadImage(entry.target);
                        this.observer.unobserve(entry.target);
                    }
                });
            }, this.config);

            // 观察所有需要懒加载的图片
            const lazyImages = document.querySelectorAll('[data-lazy="true"], img[loading="lazy"]');
            lazyImages.forEach(img => {
                this.observer.observe(img);
            });
        }

        loadImage(img) {
            const originalSrc = img.getAttribute('data-original') || img.src;
            const smallSrc = img.getAttribute('data-thumbnail');
            
            // 如果已经有小图，先加载小图，再加载大图
            if (smallSrc && smallSrc !== originalSrc) {
                this.loadWithPlaceholder(img, smallSrc, originalSrc);
            } else {
                img.src = originalSrc;
            }
            
            // 移除懒加载属性
            img.removeAttribute('data-lazy');
            img.removeAttribute('data-original');
            img.removeAttribute('data-thumbnail');
        }

        loadWithPlaceholder(img, placeholderSrc, originalSrc) {
            // 先加载占位图
            const placeholder = new Image();
            placeholder.onload = () => {
                img.src = placeholderSrc;
                
                // 延迟加载原图
                setTimeout(() => {
                    const original = new Image();
                    original.onload = () => {
                        img.src = originalSrc;
                        img.classList.add('lazy-loaded');
                    };
                    original.src = originalSrc;
                }, 100);
            };
            placeholder.src = placeholderSrc;
        }

        loadAllImages() {
            const lazyImages = document.querySelectorAll('[data-lazy="true"], img[loading="lazy"]');
            lazyImages.forEach(img => {
                const originalSrc = img.getAttribute('data-original') || img.src;
                img.src = originalSrc;
                img.removeAttribute('data-lazy');
                img.removeAttribute('data-original');
            });
        }

        // 手动触发图片加载（用于特殊场景）
        loadImageNow(img) {
            this.loadImage(img);
        }

        // 销毁观察器
        destroy() {
            if (this.observer) {
                this.observer.disconnect();
            }
        }
    }

    // 初始化懒加载系统
    document.addEventListener('DOMContentLoaded', function() {
        window.advancedLazyLoad = new AdvancedLazyLoad();
    });

    /**
     * 图片预加载系统
     */
    class ImagePreloader {
        constructor() {
            this.cache = new Map();
        }

        // 预加载重要图片
        preloadCriticalImages() {
            const criticalImages = [
                // 添加需要预加载的关键图片URL
                '/wp-content/themes/barepaper-v7.0.0/images/logo.svg',
                '/wp-content/themes/barepaper-v7.0.0/images/loading.gif'
            ];

            criticalImages.forEach(url => {
                this.preloadImage(url);
            });
        }

        preloadImage(url) {
            if (!this.cache.has(url)) {
                const img = new Image();
                img.src = url;
                this.cache.set(url, img);
            }
        }
    }

    // 初始化预加载系统
    window.imagePreloader = new ImagePreloader();

})();