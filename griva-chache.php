<?php
/**
* Plugin Name: GRIVA WordPress Frontend Cache for guests
* Plugin URI: http://tech.griva.group/frontend-guests-cache
* Description: Плагин предназначен для простого кеширования страниц
* Author: Vasily Grigoriev
* Author URI: https://grigoriev.site/
* Network: true
* Version: 0.1.0
*/

class grivaFrontendCache
{
    /**
     * Относительный путь к папке хранения кэша (относительно корневого каталога сайта)
     * !! В конце строки не должно быть символов разделителя директорий
     * @var string
     */
    private $cacheDir = "wp-content/cache";

    /**
     * Время хранения файла кэша в секундах
     * @var int
     */
    private $cacheTime = 3600;

    public function __construct()
    {
        if (!$this->cahceEnabled()) return;
        $this->handler();
    }

    private function handler()
    {
        $currentUrl = $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $hashUrl = md5($currentUrl);
        $file = $this->getFilePath($hashUrl);
        $isUser = $this->isLoggedInUser();
        $renew = false;

        // Проверяем - существует ли кэш-файл URL и не истек ли срок его дествия
        if (!file_exists($file) || empty(file_get_contents($file))) $renew = true;
        elseif (file_exists($file) && time() - filemtime($file) > $cacheTime) $renew = true;

        if ($renew && !$isUser) {
            ob_start(function ($buffer) use ($file) {
                file_put_contents($file, $buffer);
                return $buffer;
            });

            add_action('shutdown', function() {
                if (!empty(ob_list_handlers())) {
                    ob_end_flush();
                }
            }, 100);
        } elseif (!$renew && !$isUser) {
            print file_get_contents($file); die;
        } else {
            if (file_exists($file)) unlink($file);
        }
    }

    /**
     * Функция возвращает путь к файлу кеша на основе его имени.
     * Если подпапок не создано, то они попутно создаются
     * @param string $hashUrl Идентификатор файла с кэшем
     * @return string
     */
    private function getFilePath($hashUrl)
    {
        $cacheLayers = $this->getLayers($hashUrl);
        $absoluteCacheDir = ABSPATH.$this->cacheDir;

        // Создаем основную папку, если не создана
        if (!is_dir($absoluteCacheDir)) mkdir($absoluteCacheDir);

        foreach ($cacheLayers as $layer) {
            $currentDir = $absoluteCacheDir.DIRECTORY_SEPARATOR.$layer;
            if (is_writable($absoluteCacheDir) && !is_dir($currentDir)) mkdir($currentDir);
            $absoluteCacheDir = $currentDir;
        }
        $cacheFile = $absoluteCacheDir.DIRECTORY_SEPARATOR.$hashUrl.".cache";
        return $cacheFile;
    }

    /**
     * Возращает массив названия подпапок, предназначеных для организации кэша,
     * выведенного из идентификатора файла кэша
     * @param string $hashUrl Идентификатор файла кэша
     * @return array Массив с название подпапок
     */
    private function getLayers($hashUrl)
    {
        $firstLayer = substr($hashUrl, 0, 2);
        $secondLayer = substr($hashUrl, 2, 2);
        return [$firstLayer, $secondLayer];
    }

    /**
     * Метод проверяет все возможные случаи, когда жесткое кеширование страниц не целесообразно
     * @return bool
     */
    private function cahceEnabled()
    {
        $isAuth = $this->isAuthPages();
        $isAdmin = is_admin();
        $isAjax = defined('DOING_AJAX') && DOING_AJAX;
        $isPost = !empty($_POST);
        $isOffCache = $isAuth || $isAdmin || $isAjax || $isPost;
        return !$isOffCache;
    }

    /**
     * Небольшая функция, позволяющая опеределить, указывает ли текущий URL
     * на страницу регистрации или страницу входа на сайт.
     * @return bool
     */
    private function isAuthPages()
    {
        $absPath = str_replace(['\\','/'], DIRECTORY_SEPARATOR, ABSPATH);
        $isRegistrationPage = in_array($ABSPATH_MY.'wp-register.php', get_included_files());
        $isLoginPageByFiles = in_array($ABSPATH_MY.'wp-login.php', get_included_files());
        $isLoginPageByGlobals = isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php';
        $isLoginPageBySelf = $_SERVER['PHP_SELF']== '/wp-login.php';
        $isLoginPage = $isLoginPageByFiles || $isLoginPageByGlobals || $isLoginPageBySelf;
        return $isLoginPage || $isRegistrationPage;
    }

    /**
     * Проверяет наличие cookie авторизованного пользователя, т.к. другого варианта
     * на данной стадии инициализации WP просто нет
     *
     * @return bool
     */
    private function isLoggedInUser()
    {
        $isLoggedIn = false;
        foreach ($_COOKIE as $key => $value) {
            if (preg_match("/^wordpress_logged_in_/i", $key)) {
                $isLoggedIn = true;
            }
        }
        return $isLoggedIn;
    }
}

new grivaFrontendCache();