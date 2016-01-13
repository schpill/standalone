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

    class CustomLib
    {
        private $__ns;
        private static $__events = [];

        public function __construct($ns)
        {
            if (!isset(self::$__events[$ns])) {
                self::$__events[$ns] = [];
            }

            $this->__ns = $ns;
        }

        public function __call($m, $a)
        {
            if (fnmatch('set*', $m)) {
                $k = Inflector::lower(Inflector::uncamelize(lcfirst(substr($m, 3))));
                $v = empty($a) ? true : current($a);

                self::$__events[$this->__ns][$k] = $v;

                return $this;
            } elseif (fnmatch('get*', $m)) {
                $k = Inflector::lower(Inflector::uncamelize(lcfirst(substr($m, 3))));

                if (isset(self::$__events[$this->__ns][$k])) {
                    return self::$__events[$this->__ns][$k];
                }

                $default = empty($a) ? null : current($args);

                return $default;
            } elseif (fnmatch('has*', $m)) {
                $k = Inflector::lower(Inflector::uncamelize(lcfirst(substr($m, 3))));

                return isset(self::$__events[$this->__ns][$k]);
            } elseif (fnmatch('clear*', $m)) {
                $k = Inflector::lower(Inflector::uncamelize(lcfirst(substr($m, 5))));

                unset(self::$__events[$this->__ns][$k]);

                return $this;
            } else {
                if (isset(self::$__events[$this->__ns][$m])) {
                    $v = self::$__events[$this->__ns][$m];

                    if (is_callable($v)) {
                        return call_user_func_array($v, $a);
                    } else {
                        return $v;
                    }
                }
            }
        }

        public function __set($k, $v)
        {
            self::$__events[$this->__ns][$k] = $v;

            return $this;
        }

        public function __get($k)
        {
            if (isset(self::$__events[$this->__ns][$k])) {
                return self::$__events[$this->__ns][$k];
            }

            return null;
        }

        public function __isset($k)
        {
            return isset(self::$__events[$this->__ns][$k]);
        }

        public function __unset($k)
        {
            unset(self::$__events[$this->__ns][$k]);

            return $this;
        }

        public function set($k, $v)
        {
            self::$__events[$this->__ns][$k] = $v;

            return $this;
        }

        public function get($k, $default = null)
        {
            if (isset(self::$__events[$this->__ns][$k])) {
                return self::$__events[$this->__ns][$k];
            }

            return $default;
        }

        public function has($k)
        {
            return isset(self::$__events[$this->__ns][$k]);
        }

        public function clear($k)
        {
            unset(self::$__events[$this->__ns][$k]);

            return $this;
        }
    }
