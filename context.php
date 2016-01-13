<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2015 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */

    namespace Thin;

    define('NL', "\n");
    define('HTTPS', true);
    define('THINSTART', microtime());

    forever();
    container();

    $_SERVER['REQUEST_URI'] = isAke($_SERVER, 'REQUEST_URI', '/');

    date_default_timezone_set(Config::get('application.timezone', 'Europe/Paris'));
    setlocale(LC_ALL, Config::get('application.lc.all', 'fr_FR.UTF-8'));

    require_once __DIR__ . DS . 'helpers/functions.php';
    require_once __DIR__ . DS . 'helpers.php';
    require_once __DIR__ . DS . 'facades.php';

    if (Arrays::exists('SERVER_NAME', $_SERVER)) {
        $protocol = 'http';

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
            $protocol .= 's';
        }

        container()->setProtocol($protocol);

        $urlSite = "$protocol://" . $_SERVER["SERVER_NAME"] . "/";

        if (strstr($urlSite, '//')) {
            $urlSite = str_replace('//', '/', $urlSite);
            $urlSite = str_replace($protocol . ':/', $protocol . '://', $urlSite);
        }

        if (Inflector::upper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $tab = explode('\\', $urlSite);
            $r = '';

            foreach ($tab as $c => $v) {
                $r .= $v;
            }

            $r = str_replace('//', '/', $r);
            $r = str_replace($protocol . ':/', $protocol . '://', $r);
            $urlSite = $r;
        }

        $base_uri = substr(str_replace('public/index.php', '', $_SERVER['SCRIPT_NAME']), 1);

        if (substr($_SERVER['REQUEST_URI'], 0, strlen($base_uri) + 1) != '/' . $base_uri) {
            $base_uri = '';
        }

        Config::set('application.base_uri', $base_uri);

        $application = Config::get('application');
        $urlSite    .= $base_uri;

        Utils::set("urlsite", $urlSite);
        define('URLSITE', $urlSite);
        container()->setUrlsite(URLSITE);
        context()->setIsCli(false)->setUrlsite(URLSITE);
    } else {
        context()->setIsCli(true);
    }

    if (false === CLI) {
        $flood = new Flood;
        // $flood->check();
    }

    context('url')->actual(function () {
        $protocol = 'http';

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
            $protocol = 'https';
        }

        $host       = $_SERVER['HTTP_HOST'];
        $request    = $_SERVER['REQUEST_URI'];

        return $protocol . '://' . $host . $request;
    });

    spl_autoload_register(function ($class) {
        if (!class_exists($class) && fnmatch('*_*', $class) && fnmatch('Thin*', $class)) {
            if (fnmatch('*_Db', $class)) {
                $newClass   = str_replace('Thin\\', '', $class);
                $code       = 'namespace Thin; class ' . $newClass . ' extends \\Raw\\Entityalias {}';
                eval($code);
            } else {
                $newClass   = str_replace('Thin\\', '', $class);
                $code       = 'namespace Thin; class ' . $newClass . ' extends \\Raw\\Modelalias {}';
                eval($code);
            }
        }
    });
