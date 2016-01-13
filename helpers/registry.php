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

    class RegistryLib implements \ArrayAccess, \Countable
    {
        private static $datas = [];
        private $ns;

        public function __construct($ns = null)
        {
            $ns         = is_null($ns) ? 'core' : $ns;
            $this->ns   = $ns;

            if (!isset(self::$datas[$ns])) {
                self::$datas[$ns] = [];
            }
        }

        public function __set($key, $value)
        {
            $key = Inflector::urlize($key, '.');
            self::$datas[$this->ns][$key] = $value;

            return $this;
        }

        public function offsetSet($key, $value)
        {
            $key = Inflector::urlize($key, '.');
            self::$datas[$this->ns][$key] = $value;

            return $this;
        }

        public function __get($key)
        {
            $key = Inflector::urlize($key, '.');

            return isAke(self::$datas[$this->ns], $key, null);
        }

        public function count()
        {
            return count(self::$datas[$this->ns]);
        }

        public function offsetGet($key)
        {
            $key = Inflector::urlize($key, '.');

            return isAke(self::$datas[$this->ns], $key, null);
        }

        public function __isset($key)
        {
            $key = Inflector::urlize($key, '.');

            $dummy = sha1(__file__);

            return $dummy !== isAke(self::$datas[$this->ns], $key, $dummy);
        }

        public function has($key)
        {
            $key = Inflector::urlize($key, '.');

            $dummy = sha1(__file__);

            return $dummy !== isAke(self::$datas[$this->ns], $key, $dummy);
        }


        public function offsetExists($key)
        {
            $key = Inflector::urlize($key, '.');

            $dummy = sha1(__file__);

            return $dummy !== isAke(self::$datas[$this->ns], $key, $dummy);
        }

        public function __unset($key)
        {
            $key = Inflector::urlize($key, '.');

            unset(self::$datas[$this->ns][$key]);

            return $this;
        }

        public function offsetUnset($key)
        {
            $key = Inflector::urlize($key, '.');

            unset(self::$datas[$this->ns][$key]);

            return $this;
        }

        public function set($key, $value)
        {
            $key = Inflector::urlize($key, '.');
            self::$datas[$this->ns][$key] = $value;

            return $this;
        }

        public function get($key, $default = null)
        {
            $key = Inflector::urlize($key, '.');

            return isAke(self::$datas[$this->ns], $key, $default);
        }

        public function forget($key)
        {
            $key = Inflector::urlize($key, '.');

            unset(self::$datas[$this->ns][$key]);

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
