<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2016 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */

    namespace Thin;

    class RoutesLib
    {
        private $routes = [], $group, $middleware, $cb404, $route;

        public function boot($cb404 = null)
        {
            if (is_null($cb404)) {
                $this->cb404 = function () {
                    return ['static', 'is404', true];
                };
            } else {
                $this->cb404 = $cb404;
            }

            list($controllerName, $action, $render) = $this->init();

            Now::set('request.controller', $controllerName);
            Now::set('request.action', $action);

            $controllerFile = path('module') . DS . 'controllers' . DS . $controllerName . '.php';

            if (!is_file($controllerFile)) {
                $controllerFile = path('module') . DS . 'controllers' . DS . 'static.php';
                $action = 'is404';
            }

            if (!is_file($controllerFile)) {
                if (isset($this->cb404) && is_callable($this->cb404)) {
                    Now::set('page404', true);

                    return call_user_func($this->cb404);
                } else {
                    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');

                    die();
                }
            }

            require_once $controllerFile;

            $class      = '\\Thin\\' . ucfirst(Inflector::lower($controllerName)) . 'Controller';

            $actions    = get_class_methods($class);
            $father     = get_parent_class($class);

            if ($father == 'Thin\FrontController') {
                $a = $action;
                $method = $this->getMethod();

                $action = Inflector::lower($method) . ucfirst(
                    Inflector::camelize(
                        strtolower($action)
                    )
                );

                $controller         = new $class;
                $controller->_name  = $controllerName;
                $controller->action = $a;

                if (in_array('boot', $actions)) {
                    $controller->boot();
                }

                if (in_array($action, $actions)) {
                    $controller->$action();
                } else {
                    Clipp::is404();
                }

                if (in_array('unboot', $actions)) {
                    $controller->unboot();
                }
            } else {
                $controller = new $class($action);
            }

            if (true === $render) {
                $this->render($controller, Now::get('page404', false));
            }
        }

        private function render($controller, $is404 = false)
        {
            $response = thin('response');

            if (!$is404) $response->setStatusCode(200, 'OK');
            else $response->setStatusCode(404, 'Not Found');

            ob_start();

            $response->sendHeaders()
            ->setContent(
                $this->html($controller)
            )->send();

            $html = ob_get_contents();

            ob_end_clean();

            die($html . '<!-- generated in ' . Timer::get() . ' s. -->');
        }

        private function html($controller)
        {
            $tpl = path('module') . DS . 'views' . DS . $controller->_name . DS . $controller->action . '.phtml';

            if (File::exists($tpl)) {
                $content = File::read($tpl);

                $content = str_replace(
                    '$this->partial(\'',
                    'lib("routes")->partial($controller, \'' . path('module') . DS . 'views' . DS . 'partials' . DS,
                    $content
                );

                $controller->app = static::$data;

                $content = str_replace(
                    '$this->',
                    '$controller->',
                    $content
                );

                $content = str_replace(['%%=', '%%'], ['<?php echo ', '; ?>'], $content);

                $content = lib('lang')->check($controller->_name . '.' . $controller->action, $content);

                $file = Config::get('app.module.dirstorage') . DS . 'cache' . DS . sha1($content) . '.display';

                File::put($file, $content);

                ob_start();

                include $file;

                $html = ob_get_contents();

                ob_end_clean();

                File::delete($file);

                return $html;
            } else {
                return '<h1>Error 404</h1>';
            }
        }

        public function partial($controller, $partial)
        {
            if (File::exists($partial)) {
                $content = File::read($partial);

                $content = str_replace(
                    '$this->partial(\'',
                    'lib("routes")->partial($controller, \'' . path('module') . DS . 'views' . DS . 'partials' . DS,
                    $content
                );

                $content = str_replace(
                    '$this->',
                    '$controller->',
                    $content
                );

                $content = str_replace(['%%=', '%%'], ['<?php echo ', '; ?>'], $content);

                $tab        = explode(DS, $partial);
                $last       = str_replace('.phtml', '', array_pop($tab));
                $beforeLast = array_pop($tab);
                $partialKey = "$beforeLast.$last";

                $content = lib('lang')->check($partialKey, $content);

                $file = Config::get('app.module.dirstorage') . DS . 'cache' . DS . sha1($content) . '.display';

                File::put($file, $content);

                ob_start();

                include $file;

                $html = ob_get_contents();

                ob_end_clean();

                File::delete($file);

                echo $html;
            } else {
                echo '';
            }
        }

        public function init()
        {
            $routes = path('module') . DS . 'config' . DS . 'routes.php';

            if (File::exists($routes)) {
                $router = include $routes;

                call_user_func_array($router, [$this]);

                $back = $this->exec();

                if (count($back) == 2) {
                    $back[] = true;
                }

                return $back;
            } else {
                if (isset($this->cb404) && is_callable($this->cb404)) {
                    Now::set('page404', true);

                    return call_user_func($this->cb404);
                } else {
                    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');

                    die();
                }
            }
        }

        public function __construct()
        {
            $this->group = 'default';
        }

        public function group($group = 'default', $middleware = null)
        {
            $this->group        = $group;
            $this->middleware   = $middleware;
        }

        public function add($method, $pattern, callable $c, $middleware = null)
        {
            $pattern = $this->baseRoute . '/' . trim($pattern, '/');
            $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

            $methods = 'GET';

            switch ($method) {
                case 'get':
                    $methods = 'GET';
                    break;
                case 'post':
                    $methods = 'POST';
                    break;
                case 'web':
                case 'getpost':
                case 'getPost':
                case 'postget':
                case 'postGet':
                    $methods = 'GET|POST';
                    break;
                case 'put':
                    $methods = 'PUT';
                    break;
                case 'delete':
                    $methods = 'DELETE';
                    break;
                case 'options':
                    $methods = 'OPTIONS';
                    break;
                case 'patch':
                    $methods = 'PATCH';
                    break;
                case 'head':
                    $methods = 'HEAD';
                    break;
                case 'all':
                case 'any':
                    $methods = 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD';
                    break;
            }

            if ($this->group != 'default') {
                $middleware = $this->middleware;
            }

            foreach (explode('|', $methods) as $method) {
                $this->routes[$method][] = [
                    'group'         => $this->group,
                    'pattern'       => $pattern,
                    'callback'      => $callback,
                    'middleware'    => $middleware
                ];
            }

            return $this;
        }

        public function getHeaders()
        {
            if (function_exists('getallheaders')) {
                return getallheaders();
            }

            $headers = [];

            foreach ($_SERVER as $name => $value) {
                if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
                    $headers[str_replace([' ', 'Http'], ['-', 'HTTP'], ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }

            return $headers;
        }

        public function getMethod()
        {
            $method = isAke($_SERVER, 'REQUEST_METHOD', 'GET');

            if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
                ob_start();
                $method = 'GET';
            } elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $headers = $this->getHeaders();

                if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], ['PUT', 'DELETE', 'PATCH'])) {
                    $method = isAke($headers, 'X-HTTP-Method-Override', 'PUT');
                }
            }

            return $method;
        }

        public function getUri()
        {
            $uri = substr($_SERVER['REQUEST_URI'], strlen($this->baseRoute));

            if (strstr($uri, '?')) {
                $uri = substr($uri, 0, strpos($uri, '?'));
            }

            $uri = '/' . trim($uri, '/');

            return $uri;
        }

        public function exec()
        {
            $method = $this->getMethod();

            $routes = [];

            $found  = 0;
            $routes = isAke($this->routes, $method, []);

            if (!empty($routes)) {
                $found = $this->analyze($routes);
            }

            if ($found < 1) {
                if (isset($this->cb404) && is_callable($this->cb404)) {
                    Now::set('page404', true);

                    return call_user_func($this->cb404);
                } else {
                    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');

                    die();
                }
            } else {
                if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
                    ob_end_clean();
                }

                return $this->route;
            }
        }

        private function analyze($routes, $quit = true)
        {
            $found = 0;

            $uri = $this->getUri();

            foreach ($routes as $route) {
                if (preg_match_all('#^' . $route['pattern'] . '$#', $uri, $matches, PREG_OFFSET_CAPTURE)) {
                    $matches = array_slice($matches, 1);

                    $params = array_map(function ($match, $index) use ($matches) {
                        if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                            return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                        } else {
                            return isset($match[0][0]) ? trim($match[0][0], '/') : null;
                        }
                    }, $matches, array_keys($matches));

                    if ($quit) {
                        $middleware = isAke($route, 'middleware', null);

                        if ($middleware) {
                            $middleware = middleware($middleware);

                            if (is_callable($middleware)) {
                                $middleware($_REQUEST);
                            }
                        }

                        $this->route = call_user_func_array($route['callback'], $params);
                    } else {
                        call_user_func_array($route['callback'], $params);
                    }

                    $found++;

                    if ($quit) {
                        break;
                    }
                }
            }

            return $found;
        }

        public function run()
        {
            return $this->exec();
        }

        public function __call($m, $a)
        {
            if ($m == 'default') {
                return $this->group();
            } else {
                $args = array_merge([$m], $a);

                return call_user_func_array([$this, 'add'], $args);
            }
        }
    }
