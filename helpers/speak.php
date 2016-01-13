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

    use ArrayAccess as AA;

    class SpeakLib implements AA
    {
        private $ns, $data = [];
        private static $instances = [];

        public function __construct($ns = 'core')
        {
            if (!isset($this->data[$ns])) {
                $this->data[$ns] = [];
            }

            $this->ns = $ns;
        }

        public static function getInstance($ns = 'core')
        {
            $i = isAke(self::$instances, $ns, false);

            if (!$i) {
                $i = new self($ns);
                self::$instances[$ns] = $i;
            }

            return $i;
        }

        public function set($k, callable $v)
        {
            $this->data[$this->ns][$k] = $v;

            return $this;
        }

        public function __set($k, $v)
        {
            return $this->set($k, $v);
        }

        public function offsetSet($k, $v)
        {
            return $this->set($k, $v);
        }

        public function get($k, $default = null)
        {
            return isAke($this->data[$this->ns], $k, $default);
        }

        public function __get($k)
        {
            return $this->get($k);
        }

        public function offsetGet($k)
        {
            return $this->get($k);
        }

        public function has($k)
        {
            $check = Utils::UUID();

            return $check != isAke($this->data[$this->ns], $k, $check);
        }

        public function __isset($k)
        {
            return $this->has($k);
        }

        public function offsetExists($k)
        {
            return $this->has($k);
        }

        public function delete($k)
        {
            unset($this->data[$this->ns][$k]);

            return $this;
        }

        public function del($k)
        {
            return $this->delete($k);
        }

        public function forget($k)
        {
            return $this->delete($k);
        }

        public function remove($k)
        {
            return $this->delete($k);
        }

        public function __unset($k)
        {
            return $this->delete($k);
        }

        public function offsetUnset($k)
        {
            return $this->delete($k);
        }

        public function __call($m, $a)
        {
            $closure = $this->get($m);

            if ($closure) {
                if (is_callable($closure)) {
                    return call_user_func_array($closure, $a);
                }
            }
        }
    }
