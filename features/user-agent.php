<?php
/**
 * User Agent 解析模块
 * 移植自 XQUserAgent 插件
 * 
 * @package BarePaper
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class Paper_User_Agent {
    
    /**
     * 获取 User Agent 信息 HTML
     * 
     * @param string $ua User Agent 字符串
     * @param string $size 图标大小 (px)
     * @return string
     */
    public static function get_ua_html($ua) {
        // 检查功能是否开启
        if (!Paper_Settings_Manager::is_enabled('paper_wp_theme_settings', 'enable_user_agent')) {
            return '';
        }

        $os = self::get_os($ua);
        $browser = self::get_browser_name($ua);
        
        // 纯文本样式：系统 / 浏览器
        $html = sprintf(
            '<span class="ua-info">' .
            '<span class="ua-text">%s</span>' .
            '<span class="ua-divider"> / </span>' .
            '<span class="ua-text">%s</span>' .
            '</span>',
            esc_html($os['title']),
            esc_html($browser['title'])
        );
        
        return $html;
    }

    /**
     * 获取图标 URL
     */
    private static function get_icon_url($type, $name) {
        $base_url = get_template_directory_uri() . '/assets/images/ua/';
        // 检查文件是否存在（可选，为了性能可以跳过，直接返回 URL）
        // 这里假设图标都存在
        return $base_url . $type . '/' . $name . '.svg';
    }

    /**
     * 获取操作系统信息
     */
    public static function get_os($ua) {
        $version = null;
        $code = null;
        $title = 'Other System';

        if (preg_match('/Windows/i', $ua) || preg_match('/WinNT/i', $ua) || preg_match('/Win32/i', $ua)) {
            $title = 'Windows';
            if (preg_match('/Windows NT 11.0/i', $ua) || preg_match('/Windows NT 6.4/i', $ua)) {
                $version = '11';
                $code = 'Windows-10'; // 使用 Win10 图标
            } elseif (preg_match('/Windows NT 10.0/i', $ua) || preg_match('/Windows NT 6.4/i', $ua)) {
                $version = '10';
                $code = 'Windows-10';
            } elseif (preg_match('/Windows NT 6.3/i', $ua)) {
                $version = '8.1';
                $code = 'Windows-8';
            } elseif (preg_match('/Windows NT 6.2/i', $ua)) {
                $version = '8';
                $code = 'Windows-8';
            } elseif (preg_match('/Windows NT 6.1/i', $ua)) {
                $version = '7';
                $code = 'Windows-7'; // 假设有 Win7 图标，如果没有则回退
            } elseif (preg_match('/Windows NT 6.0/i', $ua)) {
                $version = 'Vista';
            } elseif (preg_match('/Windows NT 5.2 x64/i', $ua)) {
                $version = 'XP'; 
            } elseif (preg_match('/Windows NT 5.2/i', $ua)) {
                $version = 'Server 2003';
            } elseif (preg_match('/Windows NT 5.1/i', $ua) || preg_match('/Windows XP/i', $ua)) {
                $version = 'XP';
            } elseif (preg_match('/Windows NT 5.01/i', $ua)) {
                $version = '2000 (SP1)';
            } elseif (preg_match('/Windows NT 5.0/i', $ua) || preg_match('/Windows NT5/i', $ua) || preg_match('/Windows 2000/i', $ua)) {
                $version = '2000';
            } else {
                $code = 'Windows';
            }
            
            if (!$code) $code = 'Windows';

        } elseif (stripos($ua, "Android")!==false && stripos($ua, "HarmonyOS")!==false) {
            $title = 'HarmonyOS';
            $code = 'HarmonyOS'; // 需确认是否有此图标
        } elseif (preg_match('/Android/i', $ua)) {
            $title = 'Android';
            $code = 'Android';
            if (preg_match('/Android[\ |\/]?([.0-9a-zA-Z]+)/i', $ua, $regmatch)) {
                $version = $regmatch[1];
            }
        } elseif (preg_match('/Mac/i', $ua) || preg_match('/Darwin/i', $ua)) {
            $title = 'Mac OS X';
            $code = 'Apple';
            if (preg_match('/Mac OS X/i', $ua) || preg_match('/Mac OSX/i', $ua)) {
                if (preg_match('/iPhone/i', $ua)) {
                    $title = 'iOS';
                    $version = substr($ua, strpos(strtolower($ua), strtolower('iPhone OS')) + 10);
                    $version = substr($version, 0, strpos($version, 'l') - 1);
                } elseif (preg_match('/iPad/i', $ua)) {
                    $title = 'iOS';
                    $version = substr($ua, strpos(strtolower($ua), strtolower('CPU OS')) + 7);
                    $version = substr($version, 0, strpos($version, 'l') - 1);
                } elseif (preg_match('/Mac OS X/i', $ua)) {
                    $version = substr($ua, strpos(strtolower($ua), strtolower('OS X')) + 5);
                    $version = substr($version, 0, strpos($version, ')'));
                } else {
                    $version = substr($ua, strpos(strtolower($ua), strtolower('OSX')) + 4);
                    $version = substr($version, 0, strpos($version, ')'));
                }
                if (strpos($version, ';') > -1) {
                    $version = substr($version, 0, strpos($version, ';'));
                }
                $version = str_replace('_', '.', $version);
            } elseif (preg_match('/Darwin/i', $ua)) {
                $title = 'Mac OS Darwin';
            } else {
                $title = 'Macintosh';
            }
        } elseif (preg_match('/[^A-Za-z]Arch/i', $ua)) {
            $title = 'Arch Linux';
            $code = 'Arch-Linux';
        } elseif (preg_match('/BlackBerry/i', $ua)) {
            $title = 'BlackBerryOS';
            $code = 'BlackBerry';
        } elseif (preg_match('/CentOS/i', $ua)) {
            $title = 'CentOS';
            $code = 'CentOS';
            if (preg_match('/.el([.0-9a-zA-Z]+).centos/i', $ua, $regmatch)) {
                $version = $regmatch[1];
            }
        } elseif (preg_match('/CrOS/i', $ua)) {
            $title = 'Google Chrome OS';
            $code = 'Chrome-OS';
        } elseif (preg_match('/Debian/i', $ua)) {
            $title = 'Debian GNU/Linux';
            $code = 'Debian';
        } elseif (preg_match('/Fedora/i', $ua)) {
            $title = 'Fedora';
            $code = 'Fedora';
            if (preg_match('/.fc([.0-9a-zA-Z]+)/i', $ua, $regmatch)) {
                $version = $regmatch[1];
            }
        } elseif (preg_match('/UOS/i', $ua)) {
            $title = '统信UOS';
            $code = 'Uos';
        } elseif (preg_match('/FreeBSD/i', $ua)) {
            $title = 'FreeBSD';
            $code = 'FreeBSD';
        } elseif (preg_match('/OpenBSD/i', $ua)) {
            $title = 'OpenBSD';
            $code = 'OpenBSD';
        } elseif (preg_match('/Oracle/i', $ua)) {
            $title = 'Oracle';
            $code = 'Oracle-Linux';
        } elseif (preg_match('/Red\ Hat/i', $ua) || preg_match('/RedHat/i', $ua)) {
            $title = 'Red Hat';
            $code = 'Red-Hat';
        } elseif (preg_match('/Solaris/i', $ua) || preg_match('/SunOS/i', $ua)) {
            $title = 'Solaris';
            $code = 'Solaris';
        } elseif (preg_match('/Ubuntu/i', $ua)) {
            $title = 'Ubuntu';
            $code = 'Ubuntu';
            if (preg_match('/Ubuntu[\/|\ ]([.0-9]+[.0-9a-zA-Z]+)/i', $ua, $regmatch)) {
                $version = $regmatch[1];
            }
        } elseif (preg_match('/Linux/i', $ua)) {
            $title = 'GNU/Linux';
            $code = 'Linux';
        } else {
            $title = 'Other System';
            $code = 'Others';
        }

        if (is_null($code)) {
            $code = 'Others';
        }
        
        // Append version to title
        if (isset($version) && $version != '') {
            $title .= " $version";
        }
        
        return ['code' => $code, 'title' => $title];
    }

    /**
     * 获取浏览器版本
     */
    public static function get_browser_version($ua, $title){
        if($title=="QQ"||$title=="AlipayClient"){
          preg_match('/' . $title . '\/(\d+.\d+.\d+)/i', $ua, $regmatch);    
        }elseif($title=="MobileLenovoBrowser"||$title=="SamsungBrowser"){
          preg_match('/' . $title . '\/([\d.]+)/i', $ua, $regmatch);    
        }else{
          preg_match('/' . $title . '\/([\d+]+)/i', $ua, $regmatch); 
        }
        $version = (empty($regmatch[1])) ? '' : $regmatch[1];
        return $version;
    }
    
    /**
     * 获取浏览器名称
     */
    public static function get_browser_name($ua){
        $version = '';
        $code = null;
        $title = 'Other Browser';

        if (preg_match('/360se/i', $ua)) {
            $title = '360 安全浏览器';
            $code = '360';
        } elseif (preg_match('/baidubrowser/i', $ua) || preg_match('/\ Spark/i', $ua)) {
            $title = '百度浏览器';
            $version = self::get_browser_version($ua, 'Browser');
            $code = 'BaiduBrowser';
        } elseif (preg_match('/SE\ /i', $ua) && preg_match('/MetaSr/i', $ua)) {
            $title = '搜狗高速浏览器';
            $code = 'Sogou-Explorer';
        } elseif (preg_match('#QQ/([a-zA-Z0-9.]+)#i', $ua)) {
            $title = '手机QQ';
            $version = self::get_browser_version($ua, 'QQ');
            $code = 'qq';
        } elseif (preg_match('/baiduboxapp/i', $ua)) {
            $title = '百度APP';
            $version = self::get_browser_version($ua, 'baiduboxapp');
            $code = 'baidu';
        } elseif (preg_match('/QQBrowser/i', $ua) || preg_match('/MQQBrowser/i', $ua)) {
            $title = 'QQ 浏览器';
            $version = self::get_browser_version($ua, 'QQBrowser');
            $code = 'QQBrowser';
        } elseif (preg_match('/chromeframe/i', $ua)) {
            $title = 'Google Chrome Frame';
            $version = self::get_browser_version($ua, 'chromeframe');
            $code = 'Chrome';
        } elseif (preg_match('/Chromium/i', $ua)) {
            $title = 'Chromium';
            $version = self::get_browser_version($ua, 'Chromium');
            $code = 'Chrome';
        } elseif (preg_match('/CrMo/i', $ua)) {
            $title = 'Google Chrome Mobile';
            $version = self::get_browser_version($ua, 'CrMo');
            $code = 'Chrome';
        } elseif (preg_match('/CriOS/i', $ua)) {
            $title = 'Google Chrome for iOS';
            $version = self::get_browser_version($ua, 'CriOS');
            $code = 'Chrome';
        } elseif (preg_match('/Quark/i', $ua) || preg_match('/\ QuarkPC/i', $ua)) {
            $title = 'Quark浏览器';
            $version = self::get_browser_version($ua, 'Quark');
            $code = 'browser';
        } elseif (preg_match('/Maxthon/i', $ua)) {
            $title = '傲游浏览器';
            $version = self::get_browser_version($ua, 'Maxthon');
            $code = 'Maxthon';
        } elseif (preg_match('/HeyTapBrowser/i', $ua)) {
            $title = '欢太浏览器';
            $version = self::get_browser_version($ua, 'HeyTapBrowser');
            $code = 'OPPO-Browser';
        } elseif (preg_match('/ViVOBrowser/i', $ua)) {
            $title = 'ViVO浏览器';
            $version = self::get_browser_version($ua, 'ViVOBrowser');
            $code = 'vivo';
        } elseif (preg_match('/HuaweiBrowser/i', $ua)) {
            $title = '华为浏览器';
            $version = self::get_browser_version($ua, 'HuaweiBrowser');
            $code = 'huawei';
        } elseif (preg_match('/MiuiBrowser/i', $ua)) {
            $title = '小米浏览器';
            $version = self::get_browser_version($ua, 'MiuiBrowser');
            $code = 'MIUI-Browser';
        } elseif (preg_match('/MobileLenovoBrowser/i', $ua)) {
            $title = 'Lenovo Browser';
            $version = self::get_browser_version($ua, 'MobileLenovoBrowser');
            $code = 'Lenovobrowser';
        } elseif (preg_match('/SamsungBrowser/i', $ua)) {
            $title = 'Samsung Browser';
            $version = self::get_browser_version($ua, 'SamsungBrowser');
            $code = 'samsung';
        } elseif (preg_match('/TheWorld/i', $ua)) {
            $title = '世界之窗浏览器';
            $code = 'TheWorld';
        } elseif (preg_match('/UBrowser/i', $ua)) {
            $title = 'UC 浏览器';
            $version = self::get_browser_version($ua, 'UBrowser');
            $code = 'UC';
        } elseif (preg_match('/UCBrowser/i', $ua)) {
            $title = 'UC 浏览器';
            $version = self::get_browser_version($ua, 'UCBrowser');
            $code = 'UC';
        } elseif (preg_match('/UC\ Browser/i', $ua)) {
            $title = 'UC 浏览器';
            $version = self::get_browser_version($ua, 'UC Browser');
            $code = 'UC';
        } elseif (preg_match('/UCWEB/i', $ua)) {
            $title = 'UC 浏览器';
            $version = self::get_browser_version($ua, 'UCWEB');
            $code = 'UC';
        } elseif (preg_match('/BlackBerry/i', $ua)) {
            $title = 'BlackBerry';
            $code = 'BlackBerry';
        } elseif (preg_match('/Coast/i', $ua)) {
            $title = 'Coast';
            $version = self::get_browser_version($ua, 'Coast');
            $code = 'Opera';
        } elseif (preg_match('/IEMobile/i', $ua)) {
            $title = 'IE Mobile';
            $code = 'IE';
        } elseif (preg_match('/LG Browser/i', $ua)) {
            $title = 'LG Web Browser';
            $version = self::get_browser_version($ua, 'Browser');
            $code = 'LG';
        } elseif (preg_match('/Navigator/i', $ua)) {
            $title = 'Netscape Navigator';
            $code = 'Netscape';
        } elseif (preg_match('/Netscape/i', $ua)) {
            $title = 'Netscape';
            $code = 'Netscape';
        } elseif (preg_match('/Nintendo 3DS/i', $ua)) {
            $title = 'Nintendo 3DS';
            $code = 'Nintendo';
        } elseif (preg_match('/NintendoBrowser/i', $ua)) {
            $title = 'Nintendo Browser';
            $version = self::get_browser_version($ua, 'Browser');
            $code = 'Nintendo';
        } elseif (preg_match('/NokiaBrowser/i', $ua)) {
            $title = 'Nokia Browser';
            $version = self::get_browser_version($ua, 'Browser');
            $code = 'Nokia';
        } elseif (preg_match('/MicroMessenger/i', $ua)) {
            $title = '手机微信';
            $version = self::get_browser_version($ua, 'MicroMessenger');
            $code = 'WeChat';
        } elseif (preg_match('/AlipayClient/i', $ua)) {
            $title = '支付宝';
            $version = self::get_browser_version($ua, 'AlipayClient');
            $code = 'Alipay';
        } elseif (preg_match('/Opera Mini/i', $ua)) {
            $title = 'Opera Mini';
            $code = 'Opera';
        } elseif (preg_match('/Opera Mobi/i', $ua)) {
            $title = 'Opera Mobile';
            $code = 'Opera';
        } elseif (preg_match('/Opera/i', $ua) || preg_match('/OPR/i', $ua)) {
            $title = 'Opera';
            $code = 'Opera';
            if (preg_match('/Version/i', $ua)) {
                $version = self::get_browser_version($ua, 'Version');
            } elseif (preg_match('/OPR/i', $ua)) {
                $version = self::get_browser_version($ua, 'OPR');
            } else {
                $version = self::get_browser_version($ua, 'Opera');
            }
        } elseif (preg_match('/PlayStation\ 4/i', $ua)) {
            $title = 'PS4 Web Browser';
            $code = 'PS4';
        } elseif (preg_match('/SEMC-Browser/i', $ua)) {
            $title = 'SEMC Browser';
            $version = self::get_browser_version($ua, 'SEMC-Browser');
            $code = 'Sony';
        } elseif (preg_match('/Series60/i', $ua) && !preg_match('/Symbian/i', $ua)) {
            $title = 'Nokia S60';
            $version = self::get_browser_version($ua, 'Series60');
            $code = 'Nokia';
        } elseif (preg_match('/TencentTraveler/i', $ua)) {
            $title = 'TT 浏览器';
            $version = self::get_browser_version($ua, 'TencentTraveler');
            $code = 'QQBrowser';
        } elseif (preg_match('/EdgA/i', $ua) || preg_match('/Edg/i', $ua) || preg_match('/Edge/i', $ua)) {
            $title = 'Microsoft Edge';
            $version = self::get_browser_version($ua, 'Edge');
            $code = 'Edge';
        } elseif (preg_match('/Chrome/i', $ua)) {
            $title = 'Google Chrome';
            $version = self::get_browser_version($ua, 'Chrome');
            $code = 'Chrome';
        } elseif (preg_match('/Safari/i', $ua) && !preg_match('/Nokia/i', $ua)) {
            $title = 'Safari';
            $code = 'Safari';
            if (preg_match('/Version/i', $ua)) {
                $version = self::get_browser_version($ua, 'Version');
            }
        } elseif (preg_match('/Firefox/i', $ua)) {
            $title = 'Firefox';
            $version = self::get_browser_version($ua, 'Firefox');
            $code = 'Firefox';
        } elseif (preg_match('/MSIE/i', $ua) || preg_match('/Trident/i', $ua)) {
            $title = 'Internet Explorer';
            $code = 'IE';
            if (preg_match('/\ rv:([.0-9a-zA-Z]+)/i', $ua)) {
                $version = self::get_browser_version($ua, ' rv');
            } else {
                $version = self::get_browser_version($ua, 'MSIE');
            }
        } elseif (preg_match('/Mozilla/i', $ua)) {
            $title = 'Mozilla';
            $code = 'Mozilla';
        } else {
            $title = 'Other Browser';
            $code = 'Others';
        }

        if (is_null($code)) {
            $code = 'Others';
        }
        
        if ($version != '') {
            $title .= " $version";
        }

        return ['code' => $code, 'title' => $title];
    }
}
