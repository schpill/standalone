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

    class MiddlewareLib
    {
        private static $datas = [], $args = [];

        public function set($class, callable $closure, $args = [])
        {
            if (!isset(self::$datas[$class])) {
                self::$datas[$class] = self::$args[$class] = [];
            }

            self::$datas[$class][]    = $closure;
            self::$args[$class][]     = $args;

            return $this;
        }

        public function listen($class)
        {
            $closures = isAke(self::$datas, $class, []);

            if (empty($closures)) {
                $i = 0;

                foreach ($closures as $closure) {
                    if (is_callable($closure)) {
                        $args = isset(self::$args[$class][$i]) ? self::$args[$class][$i] : [];

                        call_user_func_array($closure, $args);
                    }

                    $i++;
                }
            }
        }

        public function boot()
        {
            spl_autoload_register(function ($class) {
                lib('middleware')->listen($class);
            });

            return $this;
        }
    }
