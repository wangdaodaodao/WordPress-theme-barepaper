(function () {
  'use strict';

  // 立即显示第一张图片，防止加载闪烁
  var albums = document.querySelectorAll('.sidebar-album');
  if (!albums.length) {
    return;
  }

  // 立即设置第一张图片为激活状态
  albums.forEach(function(album) {
    var items = album.querySelectorAll('.sidebar-album__item');
    if (items.length > 0) {
      items[0].classList.add('sidebar-album__item--active');
    }
  });

  var reduceMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  var prefersReducedMotion = reduceMotionQuery ? reduceMotionQuery.matches : false;

  // 延迟执行动画初始化，确保页面内容先显示
  setTimeout(function() {
    albums.forEach(function (album, albumIndex) {
    var viewport = album.querySelector('.sidebar-album__viewport');
    var items = album.querySelectorAll('.sidebar-album__item');
    var prevBtn = album.querySelector('.sidebar-album__nav--prev');
    var nextBtn = album.querySelector('.sidebar-album__nav--next');
    var indicatorContainer = album.querySelector('.sidebar-album__indicators');

    if (!viewport || !items.length) {
      return;
    }

    var total = items.length;
    var activeIndex = 0;
    var autoPlayTimer = null;
    var autoPlayEnabled = !prefersReducedMotion && album.getAttribute('data-autoplay') === 'true';
    var interval = parseInt(album.getAttribute('data-interval'), 10) || 4000;
    
    // 只使用淡入淡出效果
    var setAnimationEffect = function() {
      // 确保只使用淡入淡出效果
      album.classList.add('sidebar-album--fade');
    };

    if (total <= 1) {
      album.classList.add('sidebar-album--single');
      if (prevBtn) {
        prevBtn.disabled = true;
      }
      if (nextBtn) {
        nextBtn.disabled = true;
      }
    }

    items.forEach(function (item, index) {
      if (!item.id) {
        item.id = 'sidebar-album-' + albumIndex + '-' + index;
      }
      item.setAttribute('aria-hidden', index === activeIndex ? 'false' : 'true');
    });

    var setActive = function (index, options) {
      var opts = options || {};
      if (index < 0) {
        activeIndex = total - 1;
      } else if (index >= total) {
        activeIndex = 0;
      } else {
        activeIndex = index;
      }

      // 只使用淡入淡出效果
      setAnimationEffect();

      // 重置transform，使用CSS类控制
      viewport.style.transform = 'translateX(0)';

      // 确保所有图片都可见但只有激活的图片显示
      items.forEach(function (item, itemIndex) {
        var isActive = itemIndex === activeIndex;
        item.classList.toggle('sidebar-album__item--active', isActive);
        item.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        
        // 确保所有图片都有基本样式，防止空白
        item.style.display = 'block';
        item.style.position = 'absolute';
        item.style.top = '0';
        item.style.left = '0';
        item.style.width = '100%';
        item.style.height = '100%';
        
        // 强制设置图片可见性，防止随机空白
        if (isActive) {
          item.style.opacity = '1';
          item.style.visibility = 'visible';
          item.style.zIndex = '2';
        } else {
          item.style.opacity = '0';
          item.style.visibility = 'hidden';
          item.style.zIndex = '1';
        }
      });

      if (indicatorContainer) {
        var indicators = indicatorContainer.querySelectorAll('.sidebar-album__indicator');
        indicators.forEach(function (indicator, indicatorIndex) {
          var isActive = indicatorIndex === activeIndex;
          indicator.setAttribute('aria-selected', isActive ? 'true' : 'false');
          indicator.setAttribute('tabindex', isActive ? '0' : '-1');
        });
      }

      // 只在明确设置forceHover时才添加hover类
      if (opts.forceHover) {
        album.classList.add('sidebar-album--hover');
      } else if (opts.forceHover === false) {
        album.classList.remove('sidebar-album--hover');
      }

      if (opts.focusItem) {
        var activeLink = items[activeIndex].querySelector('a');
        if (activeLink) {
          try {
            activeLink.focus({ preventScroll: true });
          } catch (error) {
            activeLink.focus();
          }
        }
      }
    };

    var goToPrev = function (options) {
      setActive(activeIndex - 1, options);
    };

    var goToNext = function (options) {
      setActive(activeIndex + 1, options);
    };

    if (indicatorContainer) {
      for (var i = 0; i < total; i++) {
        var indicatorButton = document.createElement('button');
        indicatorButton.type = 'button';
        indicatorButton.className = 'sidebar-album__indicator';
        indicatorButton.setAttribute('role', 'tab');
        indicatorButton.setAttribute('aria-label', '切换至第 ' + (i + 1) + ' 张');
        indicatorButton.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false');
        indicatorButton.setAttribute('tabindex', i === activeIndex ? '0' : '-1');

        indicatorButton.addEventListener('mouseenter', function () {
          album.classList.add('sidebar-album--hover');
        });

        indicatorButton.addEventListener('click', function (event) {
          var target = event.currentTarget;
          var index = Array.prototype.indexOf.call(indicatorContainer.children, target);
          if (index > -1) {
            setActive(index, { focusItem: true });
            resetAutoPlay();
          }
        });

        indicatorButton.addEventListener('keydown', function (event) {
          if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
            event.preventDefault();
            goToPrev({ focusItem: true });
            resetAutoPlay();
          } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
            event.preventDefault();
            goToNext({ focusItem: true });
            resetAutoPlay();
          }
        });

        indicatorContainer.appendChild(indicatorButton);
      }
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        goToPrev({ focusItem: true, forceHover: true });
        resetAutoPlay();
      });
      prevBtn.addEventListener('mouseenter', function () {
        album.classList.add('sidebar-album--hover');
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        goToNext({ focusItem: true, forceHover: true });
        resetAutoPlay();
      });
      nextBtn.addEventListener('mouseenter', function () {
        album.classList.add('sidebar-album--hover');
      });
    }

    var pauseAutoPlay = function () {
      if (autoPlayTimer) {
        clearInterval(autoPlayTimer);
        autoPlayTimer = null;
      }
    };

    var startAutoPlay = function () {
      if (!autoPlayEnabled || total <= 1) {
        return;
      }
      pauseAutoPlay();
      autoPlayTimer = setInterval(function () {
        goToNext();
      }, interval);
    };

    var resetAutoPlay = function () {
      pauseAutoPlay();
      startAutoPlay();
    };

    // 始终监听悬停事件，无论是否启用自动播放
    album.addEventListener('mouseenter', function () {
      album.classList.add('sidebar-album--hover');
      if (autoPlayEnabled) {
        pauseAutoPlay();
      }
    });
    
    album.addEventListener('mouseleave', function () {
      album.classList.remove('sidebar-album--hover');
      if (autoPlayEnabled) {
        startAutoPlay();
      }
    });
    
    album.addEventListener('focusin', function () {
      album.classList.add('sidebar-album--hover');
      if (autoPlayEnabled) {
        pauseAutoPlay();
      }
    });
    
    album.addEventListener('focusout', function (event) {
      if (!album.contains(event.relatedTarget)) {
        album.classList.remove('sidebar-album--hover');
        if (autoPlayEnabled) {
          startAutoPlay();
        }
      }
    });

    if (reduceMotionQuery) {
      reduceMotionQuery.addEventListener('change', function (event) {
        prefersReducedMotion = event.matches;
        autoPlayEnabled = !prefersReducedMotion && album.getAttribute('data-autoplay') === 'true';
        if (!autoPlayEnabled) {
          pauseAutoPlay();
        } else {
          startAutoPlay();
        }
      });
    }

    setActive(0);
    startAutoPlay();
  });
  }, 100); // 延迟100ms执行，确保页面内容先显示
})();
