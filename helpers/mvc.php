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

    class MvcLib
    {
        private $route, $path;

        public function __construct($route)
        {
            if (!$route instanceof Container) {
                throw new Exception("This class needs a container injection.");
            }

            if (!defined('ROOT')) {
                throw new Exception("ROOT must be defined.");
            }

            $this->route    = $route;
            $this->path     = ROOT;
        }

        public static function instance($route)
        {
            $key    = sha1($route->controller . $route->action);
            $has    = Instance::has('libMVC', $key);

            if (true === $has) {
                return Instance::get('libMVC', $key);
            } else {
                return Instance::make('libMVC', $key, new self($route));
            }
        }

        public function dispatch()
        {
            $controller = $this->route->controller;
            $action     = $this->route->action;
            $layout     = $this->route->layout;

            $controller = empty($controller)    ? 'default' : $controller;
            $action     = empty($action)        ? 'index'   : $action;
            $layout     = empty($layout)        ? 'default' : $layout;

            if (strstr($action, '-')) {
                $words = explode('-', $action);
                $newAction = '';

                for ($i = 0; $i < count($words); $i++) {
                    $word = trim($words[$i]);

                    if ($i > 0) {
                        $word = ucfirst($word);
                    }

                    $newAction .= $word;
                }

                $action = $newAction;
            }

            $actionName = $action . 'Action';

            $layoutFile = $this->path . DS . 'mvc' . DS . 'views' . DS . 'layouts'  . DS . $layout . '.php';

            if (false === File::exists($layoutFile)) {
                is404();
            }

            $controllerFile = $this->path . DS . 'mvc' . DS . 'controllers' . DS . ucfirst(Inflector::lower($controller)) . '.php';

            $tplFile = $this->path . DS . 'mvc' . DS . 'views' . DS . ucfirst(Inflector::lower($controller)) . DS . $action . '.phtml';

            $view = false;

            if (false === File::exists($controllerFile)) {
                is404();
            }

            lib('controller');

            require_once $controllerFile;

            $class = 'Thin\\'. ucfirst(Inflector::lower($controller)) . 'Mvc';

            $i = new $class;

            if (!method_exists($i, $actionName)) {
                is404();
            }

            if (File::exists($tplFile)) {
                $view = true;
                $i->view = lib('view', $tplFile);
            }

            if (method_exists($i, 'before')) {
                $i->before();
            }

            $i->$actionName();

            if (method_exists($i, 'after')) {
                $i->after();
            }

            $pageContent = $i->view->render();

            $isAjax = $this->route->getIsAjax();

            if (is_null($isAjax) || false === $isAjax) {
                $route  = $this->route;
                $router = new Frontroute;

                require(ROOT . '/includes/config/config.php');

                require($layoutFile);
            } else {
                $user = session('user')->getUser();

                if ($user) {
                    $SESSID = $user['id'];
                } else {
                    $SESSID = 0;
                }

                if ($isVendeur = $this->route->getIsVendeur()) {
                    if(empty($_REQUEST['p'])) {
                        $_REQUEST['p'] = '/';
                    }

                    $STATUS = 0;

                    if ($SESSID) {
                        $STATUS = 1;

                        if (!empty($user['status'])) {
                            $STATUS = $user['status'];
                        }
                    }

                    $minstatus = $route->status;

                    if (empty($minstatus)) {
                        $minstatus = 0;
                    }

                    if ($STATUS < $minstatus) {
                        $_REQUEST['p'] = '/';
                    }
                }

                $json           = [];
                $json['ok']     = 1;
                $json['sessid'] = $SESSID;
                $title          = $i->view->title;
                $UNIVERS        = $i->view->univers;
                $PAGE_REQUEST   = $_REQUEST['p'];

                if (isset($title)) {
                    $json['title'] = $title;
                } else {
                    $json['title'] = '';
                }

                $json['html']       = $pageContent;
                $json['p']          = $PAGE_REQUEST;
                $json['univers']    = empty($UNIVERS) ? '' : $UNIVERS;
                $json['nav']        = null;

                if (session('user')->getUnivers() != $UNIVERS) {
                    ob_start();
                    include(ROOT . '/includes/navbar.php');
                    $json['nav'] = ob_get_clean();
                }

                session('user')->setUnivers($UNIVERS);

                $json['exec']['time']   = number_format(\Dbjson\Dbjson::$duration, 6);
                $json['exec']['nb']     = \Dbjson\Dbjson::$queries;

                header('Content-type:application/json');
                echo json_encode($json);
            }
        }
    }
