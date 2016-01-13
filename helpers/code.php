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
    use Raw\Store;

    class CodeLib implements AA
    {
        private $db;

        public function __construct()
        {
            $this->db = new Store('core.code');
        }

        /**
         *
         * @method __set
         *
         * @param  [type]
         * @param  [type]
         */
        public function __set($k, $v)
        {
            if (is_callable($v)) {
                $v = lib('closure')->extract($v);
                $this->db->set($k, $v);
            }

            return $this;
        }

        /**
         *
         * @method __get
         *
         * @param  [type]
         *
         * @return [type]
         */
        public function __get($k)
        {
            if ($this->db->has($k)) {
                $val = $this->db->get($k);
                $closure = eval('return ' . $val . ';');

                return $closure;
            }

            return null;
        }

        /**
         *
         * @method __isset
         *
         * @param  [type]
         *
         * @return boolean
         */
        public function __isset($k)
        {
            return $this->db->has($k);
        }

        /**
         *
         * @method __unset
         *
         * @param  [type]
         */
        public function __unset($k)
        {
            $this->db->del($k);

            return $this;
        }

        public function set($k, callable $v)
        {
            $v = lib('closure')->extract($v);
            $this->db->set($k, $v);

            return $this;
        }

        public function offsetSet($k, $v)
        {
            if (is_callable($v)) {
                $v = lib('closure')->extract($v);
                $this->db->set($k, $v);
            }

            return $this;
        }

        public function get($k)
        {
            if ($this->db->has($k)) {
                $val = $this->db->get($k);
                eval('$closure = ' . $val . ';');

                return $closure;
            }

            return null;
        }

        public function offsetGet($k)
        {
            if ($this->db->has($k)) {
                $val = $this->db->get($k);
                eval('$closure = ' . $val . ';');

                return $closure;
            }

            return null;
        }

        public function has($k)
        {
            return $this->db->has($k);
        }

        public function offsetExists($k)
        {
            return $this->db->has($k);
        }

        public function offsetUnset($k)
        {
            $this->db->del($k);

            return $this;
        }

        public function delete($k)
        {
            $this->db->del($k);

            return $this;
        }

        public function del($k)
        {
            $this->db->del($k);

            return $this;
        }

        public function remove($k)
        {
            $this->db->del($k);

            return $this;
        }

        public function fire($k, $args = [])
        {
            if ($this->has($k)) {
                $cb = $this->get($k);

                if (is_callable($cb)) {
                    return call_user_func_array($cb, $args);
                }
            }
        }

        public function store($k, callable $v)
        {
            return $this->set($k, $v);
        }
    }
