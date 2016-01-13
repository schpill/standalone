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

    class SoulLib implements \ArrayAccess
    {
        private static $data = [];
        private $ns;

        public function __construct($ns = 'core')
        {
            $this->ns = $ns;

            if (!isset(self::$data[$ns])) {
                self::$data[$ns] = [];
            }
        }

        public function __set($k, $v)
        {
            self::$data[$this->ns][$k] = $v;

            return $this;
        }

        public function __get($k)
        {
            if (isset(self::$data[$this->ns][$k])) {
                return self::$data[$this->ns][$k];
            }
        }

        public function __isset($k)
        {
            return isset(self::$data[$this->ns][$k]);
        }

        public function __unset($k)
        {
            unset(self::$data[$this->ns][$k]);

            return $this;
        }

        public function set($k, $v)
        {
            self::$data[$this->ns][$k] = $v;

            return $this;
        }

        public function offsetSet($k, $v)
        {
            self::$data[$this->ns][$k] = $v;

            return $this;
        }

        public function get($k, $default = null)
        {
            if (isset(self::$data[$this->ns][$k])) {
                return self::$data[$this->ns][$k];
            }

            return $default;
        }

        public function offsetGet($k)
        {
            if (isset(self::$data[$this->ns][$k])) {
                return self::$data[$this->ns][$k];
            }
        }

        public function has($k)
        {
            return isset(self::$data[$this->ns][$k]);
        }

        public function offsetExists($k)
        {
            return isset(self::$data[$this->ns][$k]);
        }

        public function offsetUnset($k)
        {
            unset(self::$data[$this->ns][$k]);

            return $this;
        }

        public function delete($k)
        {
            unset(self::$data[$this->ns][$k]);

            return $this;
        }

        public function forget($k)
        {
            unset(self::$data[$this->ns][$k]);

            return $this;
        }

        public function remove($k)
        {
            unset(self::$data[$this->ns][$k]);

            return $this;
        }

        public function del($k)
        {
            unset(self::$data[$this->ns][$k]);

            return $this;
        }

        public function __call($m, $a)
        {
            if ($this->has($m)) {
                $callable = $this->get($m);

                if (is_callable($callable)) {
                    return call_user_func_array($callable, $a);
                } else {
                    throw new Exception("The method $m is not yet implemented.");
                }
            } else {
                return $this->set($m, current($a));
            }
        }
    }
