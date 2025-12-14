/**
 * WordPress 主题特效核心功能
 * 包含主题切换、丝带背景特效、鼠标点击特效
 */

(function () {
  'use strict';

  // 格式化时间差为可读字符串
  function formatRuntime(seconds) {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);

    let result = '';
    if (days > 0) {
      result += days + '天';
    }
    if (hours > 0 || days > 0) {
      result += hours + '小时';
    }
    if (minutes > 0 || hours > 0 || days > 0) {
      result += minutes + '分';
    }
    result += secs + '秒';

    return result;
  }

  // 更新运行时间显示
  function updateRuntime() {
    const runtimeElement = document.getElementById('site-runtime');
    if (!runtimeElement) return;

    const startTimestamp = parseInt(runtimeElement.dataset.start);
    if (!startTimestamp || isNaN(startTimestamp)) {
      runtimeElement.textContent = '数据错误';
      return;
    }

    // 计算运行秒数
    const currentTimestamp = Math.floor(Date.now() / 1000);
    const runtimeSeconds = currentTimestamp - startTimestamp;

    if (runtimeSeconds < 0) {
      runtimeElement.textContent = '时间异常';
      return;
    }

    // 更新显示
    runtimeElement.textContent = formatRuntime(runtimeSeconds);
  }

  // 页面加载完成后启动计时器
  document.addEventListener('DOMContentLoaded', function () {
    const runtimeElement = document.getElementById('site-runtime');
    if (runtimeElement) {
      // 立即更新一次
      updateRuntime();
      // 每秒更新一次
      setInterval(updateRuntime, 1000);
    }
  });
})();

(function () {
  'use strict';

  // 主题管理器
  const ThemeManager = {
    STORAGE_KEY: 'barepaper_theme_preference',

    // 获取主题设置
    getThemeSettings() {
      if (typeof window.paperWpSettings !== 'undefined') {
        return {
          enableThemeSwitch: true, // 始终启用
          themeMode: window.paperWpSettings.theme_mode || 'auto'
        };
      }
      return {
        enableThemeSwitch: true,
        themeMode: 'auto'
      };
    },

    // 获取主题模式
    getThemeMode() {
      const settings = this.getThemeSettings();
      return settings.themeMode;
    },

    // 初始化
    init() {
      // 获取后台设置的主题模式
      const themeMode = this.getThemeMode();

      // 根据后台设置决定行为
      if (themeMode === 'auto') {
        // auto模式:允许用户自由切换
        // 检查是否有用户保存的偏好
        let userPreference = null;
        try {
          userPreference = localStorage.getItem(this.STORAGE_KEY);
        } catch (e) { }

        if (userPreference && (userPreference === 'light' || userPreference === 'dark')) {
          // 使用用户偏好
          this.setTheme(userPreference, false);
        } else {
          // 没有用户偏好,跟随系统主题
          this.setupAutoFollow();
        }
      } else {
        // 固定主题模式(light/dark):强制使用后台设置
        // 清除用户偏好,确保后台设置生效
        try {
          localStorage.removeItem(this.STORAGE_KEY);
        } catch (e) { }

        // 强制应用后台设置的主题
        this.setTheme(themeMode, false);
      }

      // 初始化按钮功能
      this.initToggleButton();
    },

    // 设置自动跟随系统主题
    setupAutoFollow() {
      if (window.matchMedia) {
        // 监听系统主题变化
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
          this.autoSwitchToSystemTheme();
        });
        // 初始时应用系统主题
        this.autoSwitchToSystemTheme();
      }
    },

    // 自动切换到系统主题
    autoSwitchToSystemTheme() {
      if (window.matchMedia) {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const systemTheme = prefersDark ? 'dark' : 'light';
        this.setTheme(systemTheme, false); // 不保存到localStorage，因为这是自动切换
      }
    },

    // 设置主题
    setTheme(theme, save = true) {
      const root = document.documentElement;

      // 批量DOM操作，提高性能
      const changes = {};

      if (theme === 'auto') {
        // 自动模式：移除 data-theme，让 CSS 媒体查询生效
        if (root.hasAttribute('data-theme')) {
          changes.removeAttribute = 'data-theme';
        }
      } else {
        // 手动模式：设置 data-theme
        changes.setAttribute = { name: 'data-theme', value: theme };
      }

      // 应用DOM变化
      if (changes.removeAttribute) {
        root.removeAttribute(changes.removeAttribute);
      }
      if (changes.setAttribute) {
        root.setAttribute(changes.setAttribute.name, changes.setAttribute.value);
      }

      // 保存偏好
      if (save) {
        try {
          localStorage.setItem(this.STORAGE_KEY, theme);
        } catch (e) {
          // localStorage不可用时静默失败
        }
      }

      // 清除缓存的当前主题
      this._currentTheme = undefined;

      // 触发自定义事件
      window.dispatchEvent(
        new CustomEvent('themechange', {
          detail: { theme },
        })
      );
    },

    // 获取当前主题 - 缓存结果提高性能
    getCurrentTheme() {
      // 使用内存缓存,避免重复计算
      if (this._currentTheme !== undefined) {
        return this._currentTheme;
      }

      // 检查后台设置的主题模式
      const themeMode = this.getThemeMode();

      // 如果后台设置为固定主题(light/dark),直接返回后台设置
      if (themeMode === 'light' || themeMode === 'dark') {
        this._currentTheme = themeMode;
        return themeMode;
      }

      // auto模式:检查localStorage中的用户偏好
      try {
        const saved = localStorage.getItem(this.STORAGE_KEY);
        if (saved && (saved === 'light' || saved === 'dark')) {
          this._currentTheme = saved;
          return saved;
        }
      } catch (e) {
        // localStorage不可用
      }

      // 检查DOM属性
      const root = document.documentElement;
      const dataTheme = root.getAttribute('data-theme');
      if (dataTheme && (dataTheme === 'light' || dataTheme === 'dark')) {
        this._currentTheme = dataTheme;
        return dataTheme;
      }

      // 默认返回light
      this._currentTheme = 'light';
      return 'light';
    },

    // 切换主题（核心方法）
    toggleTheme() {
      // 检查后台设置
      const themeMode = this.getThemeMode();

      // 如果后台设置为固定主题,不允许切换
      if (themeMode === 'light' || themeMode === 'dark') {
        return themeMode; // 返回当前主题,不做切换
      }

      // auto模式:允许切换
      const current = this.getCurrentTheme();
      let next;

      // 只在浅色和深色之间切换
      switch (current) {
        case 'light':
          next = 'dark';
          break;
        case 'dark':
        default:
          next = 'light';
          break;
      }

      // 用户手动切换时,保存到localStorage
      this.setTheme(next, true);
      return next; // 返回新主题，方便调用方知道切换结果
    },

    // 初始化主题切换按钮
    initToggleButton() {
      // 等待DOM加载完成
      document.addEventListener('DOMContentLoaded', () => {
        const toggleBtn = document.getElementById('theme-toggle-emoji');

        if (toggleBtn) {
          // 初始化emoji显示
          this.updateButtonEmoji(toggleBtn);

          // 绑定点击事件
          toggleBtn.addEventListener('click', () => {
            this.toggleTheme();
            this.updateButtonEmoji(toggleBtn);
          });

          // 监听主题变化
          window.addEventListener('themechange', () => {
            this.updateButtonEmoji(toggleBtn);
          });
        }
      });
    },

    // 更新按钮emoji
    updateButtonEmoji(button) {
      const theme = this.getCurrentTheme();
      switch (theme) {
        case 'light':
          button.textContent = '🌙';
          button.title = '当前：浅色主题（点击切换到深色）';
          break;
        case 'dark':
          button.textContent = '☀️';
          button.title = '当前：深色主题（点击切换到浅色）';
          break;
        default:
          button.textContent = '🌙';
          button.title = '当前：浅色主题（点击切换）';
          break;
      }
    }
  };

  // 初始化主题管理器
  // 使用立即执行函数确保在脚本加载时就初始化,避免闪烁
  if (document.readyState === 'loading') {
    // 如果DOM还在加载,等待DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function () {
      ThemeManager.init();
    });
  } else {
    // 如果DOM已经加载完成,立即初始化
    ThemeManager.init();
  }

  // 暴露全局接口
  window.ThemeManager = ThemeManager;
})();





/**
 * 网站运行时间实时计时器
 * 每秒更新显示网站运行时间
 */

