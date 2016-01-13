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

    class ThrottleLib
    {
        private static $instances   = [];
        private $resource, $limit, $lifetime;

        public function get($resource, $limit = 50, $lifetime = 30)
        {
            $instance = isAke(self::$instances, $resource, false);

            if (!$instance) {
                $instance = new self;

                $instance->resource = $resource;
                $instance->limit    = $resource;
                $instance->lifetime = $lifetime;

                self::$instances[$resource] = $instance;

                Own::set($resource, 0);
                Own::set($resource . '.time', time() + $lifetime);
            }

            return $instance;
        }

        public function incr($by = 1)
        {
            $this->evaluate();
            Own::incr($this->resource, $by);
        }

        public function decr($by = 1)
        {
            $this->evaluate();
            Own::decr($this->resource, $by);
        }

        public function check()
        {
            $check = Own::get($this->resource, 0) <= $this->limit;

            $this->evaluate();

            return $check;
        }

        public function clear()
        {
            Own::set($resource . '.time', time() + $this->lifetime);

            return Own::set($this->resource, 0);
        }

        public function attempt($by = 1)
        {
            $this->incr($by);

            return $this->check();
        }

        private function evaluate()
        {
            $when = Own::get($this->resource . '.time');

            if ($when < time()) {
                Own::set($resource . '.time', time() + $this->lifetime);

                return Own::set($this->resource, 0);
            }
        }
    }
