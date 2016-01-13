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

    class DiLib implements \ArrayAccess, \Countable
    {
        private static $datas = [];
        private static $instance;

        public function instance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self;
            }

            return self::$instance;
        }

        public function __set($key, $value)
        {
            self::$datas[$key] = $value;

            return $this;
        }

        public function offsetSet($key, $value)
        {
            self::$datas[$key] = $value;

            return $this;
        }

        public function __get($key)
        {
            $val = isAke(self::$datas, $key, null);

            if (is_callable($val)) {
                return $val(self::$instance);
            }

            return $val;
        }

        public function count()
        {
            return count(self::$datas);
        }

        public function offsetGet($key)
        {
            $val = isAke(self::$datas, $key, null);

            if (is_callable($val)) {
                return $val(self::$instance);
            }

            return $val;
        }

        public function __isset($key)
        {
            $dummy = sha1(__file__);

            return $dummy !== isAke(self::$datas, $key, $dummy);
        }

        public function has($key)
        {
            $dummy = sha1(__file__);

            return $dummy !== isAke(self::$datas, $key, $dummy);
        }


        public function offsetExists($key)
        {
            $dummy = sha1(__file__);

            return $dummy !== isAke(self::$datas, $key, $dummy);
        }

        public function __unset($key)
        {
            unset(self::$datas[$key]);

            return $this;
        }

        public function offsetUnset($key)
        {
            unset(self::$datas[$key]);

            return $this;
        }

        public function put($key, $value)
        {
            self::$datas[$key] = $value;

            return $this;
        }

        public function set($key, $value)
        {
            self::$datas[$key] = $value;

            return $this;
        }

        public function share(\Closure $closure, $args = [])
        {
            return function($container) use ($closure, $args) {
                // We'll simply declare a static variable within the Closure and if it has
                // not been set we will execute the given Closure to resolve this value
                // and return it back to these consumers of the method as an instance.
                static $object;

                if (is_null($object)) {
                    $params = [$container];
                    $params = array_merge($params, $args);
                    $object = call_user_func_array($closure, $params);
                }

                return $object;
            };
        }

        public function extend($key, \Closure $closure)
        {
            $val = isAke(self::$datas, $key, false);

            if (!is_callable($val)) {
                throw new Exception("Type {$key} is not bound.");
            }

            $newVal = function($container) use ($val, $closure) {
                return $closure($val($container), $container);
            };

            self::$datas[$key] = $newVal;

            return $this;
        }

        public function get($key, $default = null)
        {
            $val = isAke(self::$datas, $key, $default);

            if (is_callable($val)) {
                return $val(self::$instance);
            }

            return $val;
        }

        public function forget($key)
        {
            unset(self::$datas[$key]);

            return $this;
        }

        public function __call($m, $a)
        {
            if (fnmatch('get*', $m)) {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($m, 3)));
                $key                = Inflector::lower($uncamelizeMethod);
                $args               = [$key];

                if (!empty($a)) {
                    $args[] = current($a);
                }

                return call_user_func_array([$this, 'get'], $args);
            } elseif (fnmatch('set*', $m)) {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($m, 3)));
                $key                = Inflector::lower($uncamelizeMethod);
                $args               = [$key];
                $args[]             = current($a);

                return call_user_func_array([$this, 'set'], $args);
            } elseif (fnmatch('forget*', $m)) {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($m, 6)));
                $key                = Inflector::lower($uncamelizeMethod);
                $args               = [$key];

                return call_user_func_array([$this, 'forget'], $args);
            }
        }
    }
