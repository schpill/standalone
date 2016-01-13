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

    class WebservicesLib
    {
        private $method, $token, $db;

        public function __construct()
        {
            if (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_METHOD'])) {
                if (!fnmatch('/webservices/*', $_SERVER['REQUEST_URI'])) {
                    return;
                }

                header("Access-Control-Allow-Origin: *");

                $this->method = isAke($_SERVER, 'REQUEST_METHOD', 'GET');
                $uri = str_replace('/webservices/', '', isAke($_SERVER, 'REQUEST_URI', '/webservices/'));
                $this->dispatch($uri);
            }
        }

        public function dispatch($uri)
        {
            if (fnmatch('*/*/*', $uri) && !fnmatch('*/*/*/*', $uri)) {
                list($token, $controller, $action) = explode('/', $uri);

                if (strstr($action, '?')) {
                    list($action, $query) = explode('?', $action, 2);
                    $query = urldecode($query);

                    parse_str($query, $query);

                    foreach ($query as $k => $v) {
                        $_REQUEST[$k] = $v;
                    }
                }

                $controller = Inflector::lower($controller);
                $action     = Inflector::lower($action);

                $dir = Config::get('webservices.dir', APPLICATION_PATH . DS . 'webservices');

                if (is_dir($dir)) {
                    $acl = $dir . DS . 'acl.php';

                    if (is_file($acl)) {
                        $acl = include($acl);

                        $userrights = isAke($acl, $token, []);

                        $controllerRights = isAke($userrights, $controller, []);

                        if (in_array($action, $controllerRights)) {
                            $file = $dir . DS . $controller . '.php';

                            if (is_file($file)) {
                                require_once $file;

                                $class = 'Thin\\' . Inflector::camelize($controller . '_webservice');

                                $instance = lib('caller')->make($class);

                                $methods = get_class_methods($instance);

                                if (in_array('init', $methods)) {
                                    $instance->init();
                                }

                                if (in_array('boot', $methods)) {
                                    $instance->boot();
                                }

                                if (in_array($action, $methods)) {
                                    return $instance->$action();
                                }
                            }
                        }
                    }
                }
            }

            Api::forbidden();
        }
    }
