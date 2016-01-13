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

    class RoutingLib
    {
        private $route, $cb404, $baseRoute = '', $routes = [], $befores = [], $afters = [];

        public function boot()
        {
            $uri = $this->getUri();

            list($controllerName, $action, $render) = $this->routes($uri);

            Now::set('controller', $controllerName);
            Now::set('action', $action);
            Now::set('session', session(SITE_NAME));

            $controllerFile = Config::get('mvc.dir', APPLICATION_PATH) . DS . 'controllers' . DS . $controllerName . '.php';

            if (!is_file($controllerFile)) {
                $controllerFile = Config::get('mvc.dir', APPLICATION_PATH) . DS . 'controllers' . DS . 'static.php';
                $action = 404;
            }

            require_once $controllerFile;

            $class = '\\Thin\\'
            . ucfirst(strtolower(SITE_NAME))
            . ucfirst(Inflector::lower($controllerName))
            . 'Controller';

            Now::set('request', (new Object)->populate($_REQUEST));

            $controller = new $class($action);

            if (true === $render) {
                $this->render($controller);
            }
        }

        private function routes($uri)
        {
            if (fnmatch('/api/*', $uri)) {
                return $this->api($uri);
            } else {
                $this->cb404 = function () {
                    return ['static', 404, true];
                };

                $routes = Config::get('mvc.dir', APPLICATION_PATH) . DS . 'config' . DS . 'routes.php';

                if (File::exists($routes)) {
                    require_once $routes;

                    AppRoutes::defines($this);

                    $back = $this->run();

                    if (count($back) == 2) {
                        $back[] = true;
                    }

                    return $back;
                }
            }
        }

        public static function __callStatic($method, $args)
        {
            switch ($method) {
                case 'after':
                case 'before':
                case 'all':
                case 'get':
                case 'post':
                case 'put':
                case 'patch':
                case 'options':
                case 'head':
                    $i = new self();

                    return call_user_func_array([$i, $method], $args);
                default:
                    throw new Exception("Method $method does not exist.");
            }
        }

        public function get($pattern, $cb)
        {
            return $this->match('GET', $pattern, $cb);
        }

        public function post($pattern, $cb)
        {
            return $this->match('POST', $pattern, $cb);
        }

        public function getpost($pattern, $cb)
        {
            return $this->match('GET|POST', $pattern, $cb);
        }

        public function put($pattern, $cb)
        {
            return $this->match('PUT', $pattern, $cb);
        }

        public function delete($pattern, $cb)
        {
            return $this->match('DELETE', $pattern, $cb);
        }

        public function options($pattern, $cb)
        {
            return $this->match('OPTIONS', $pattern, $cb);
        }

        public function patch($pattern, $cb)
        {
            return $this->match('PATCH', $pattern, $cb);
        }

        public function head($pattern, $cb)
        {
            return $this->match('HEAD', $pattern, $cb);
        }

        public function all($pattern, $cb)
        {
            return $this->match('GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD', $pattern, $cb);
        }

        public function match($methods, $pattern, $cb)
        {
            $pattern = $this->baseRoute . '/' . trim($pattern, '/');
            $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

            foreach (explode('|', $methods) as $method) {
                $this->routes[$method][] = array(
                    'pattern'   => $pattern,
                    'cb'        => $cb
                );
            }

            return $this;
        }

        public function after($methods, $pattern, $cb)
        {
            $pattern = $this->baseRoute . '/' . trim($pattern, '/');
            $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

            foreach (explode('|', $methods) as $method) {
                $this->afters[$method][] = array(
                    'pattern'   => $pattern,
                    'cb'        => $cb
                );
            }

            return $this;
        }

        public function setBaseRoute($dir)
        {
            $this->baseRoute = $dir;

            return $this;
        }

        public function before($methods, $pattern, $cb)
        {
            $pattern = $this->baseRoute . '/' . trim($pattern, '/');
            $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

            foreach (explode('|', $methods) as $method) {
                $this->befores[$method][] = array(
                    'pattern'   => $pattern,
                    'cb'        => $cb
                );
            }

            return $this;
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

        public function getHeaders()
        {
            if (function_exists('getallheaders')) {
                return getallheaders();
            }

            $headers = [];

            foreach ($_SERVER as $name => $value) {
                if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
                    $headers[str_replace(array(' ', 'Http'), array('-', 'HTTP'), ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }

            return $headers;
        }

        public function getMethod()
        {
            $method = $_SERVER['REQUEST_METHOD'];

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

        public function run($cb = null)
        {
            $method = $this->getMethod();

            $befores = isAke($this->befores, $method, []);

            if (!empty($befores)) {
                $this->handling($befores, false);
            }

            $found = 0;
            $routes = isAke($this->routes, $method, []);

            if (isset($this->routes[$method])) {
                $found = $this->handling($routes);
            }

            if ($found < 1) {
                if (isset($this->cb404) && is_callable($this->cb404)) {
                    return call_user_func($this->cb404);
                } else {
                    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');

                    die();
                }
            } else {
                if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
                    ob_end_clean();
                }

                $afters = isAke($this->afters, $method, []);

                if (!empty($afters)) {
                    $this->handling($afters, false);
                }

                return $this->route;
            }
        }

        public function handling($routes, $quit = true)
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
                        $this->route = call_user_func_array($route['cb'], $params);
                    } else {
                        call_user_func_array($route['cb'], $params);
                    }

                    $found++;

                    if ($quit) {
                        break;
                    }
                }
            }

            return $found;
        }

        public function render($controller)
        {
            $response = thin('response');

            $response->setStatusCode(200, 'OK')
            ->sendHeaders()
            ->setContent(
                $this->html($controller)
            )->send();

            exit;
        }

        private static function html($controller)
        {
            $tpl = Config::get('mvc.dir', APPLICATION_PATH) . DS . 'views' . DS . $controller->_name . DS . $controller->action . '.phtml';

            if (File::exists($tpl)) {
                $content = File::read($tpl);

                $content = str_replace(
                    '$this->partial(\'',
                    'lib("routing")->partial($controller, \'' . Config::get('mvc.dir', APPLICATION_PATH) . DS . 'views' . DS . 'partials' . DS,
                    $content
                );

                $controller->app = static::$data;

                $content = str_replace(
                    '$this->',
                    '$controller->',
                    $content
                );

                $file = CACHE_PATH . DS . sha1($content) . '.display';

                File::put($file, $content);

                ob_start();

                include $file;

                $html = ob_get_contents();

                ob_end_clean();

                File::delete($file);

                return $html;
            } else {
                return '<h1>Erreur 404</h1>';
            }
        }

        public function partial($controller, $partial)
        {
            if (File::exists($partial)) {
                $content = File::read($partial);

                $content = str_replace(
                    '$this->',
                    '$controller->',
                    $content
                );

                $file = CACHE_PATH . DS . sha1($content) . '.display';

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

        public function is404()
        {
            $response = thin('response');

            $response->setStatusCode(404, 'Not Found')
            ->sendHeaders()
            ->setContent('<h1>Erreur 404</h1>')
            ->send();

            exit;
        }

        private function api($uri)
        {
            $method = Request::method();

            $uri = substr(str_replace('/api/', '/', $uri), 1);

            $tab = explode('/', $uri);

            if (count($tab) < 3) {
                Api::forbidden();
            }

            $module     = current($tab);
            $controller = $tab[1];
            $action     = $tab[2];

            $tab        = array_slice($tab, 3);

            $count      = count($tab);

            if (0 < $count && $count % 2 == 0) {
                for ($i = 0; $i < $count; $i += 2) {
                    $_REQUEST[$tab[$i]] = $tab[$i + 1];
                }
            }

            $file = Config::get('mvc.dir', APPLICATION_PATH) . DS . 'api' . DS . $module . DS . $controller . '.php';

            if (!File::exists($file)) {
                Api::NotFound();
            }

            require_once $file;

            $class = 'Thin\\'. ucfirst($controller) . 'Api';

            $i = new $class;

            $methods = get_class_methods($i);

            $call = strtolower($method) . ucfirst($action);

            if (!in_array($call, $methods)) {
                Api::NotFound();
            }

            if (in_array('init', $methods)) {
                $i->init($call);
            }

            $i->$call();

            if (in_array('after', $methods)) {
                $i->after();
            }
        }

        public function go($page)
        {
            header('Location: /' . $page);
            exit;
        }
    }
