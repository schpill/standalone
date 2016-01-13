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

    namespace Nosql;

    class Event
    {
        private $collection;
        private static $events = [];

        public function __construct($collection)
        {
            $this->collection = $collection;

            if (isset(self::$events[$this->collection])) {
                self::$events[$this->collection] = [];
            }
        }

        public function __set($k, $v)
        {
            self::$events[$this->collection][$k] = $v;

            return $this;
        }

        public function __get($k)
        {
            return isset(self::$events[$this->collection][$k]) ? self::$events[$this->collection][$k] : null;
        }

        public function __isset($k)
        {
            if (isset(self::$events[$this->collection][$k])) {
                return is_callable(self::$events[$this->collection][$k]);
            }

            return false;
        }

        public function __unset($k)
        {
            unset(self::$events[$this->collection][$k]);

            return $this;
        }

        public function listen($k, callable $v)
        {
            self::$events[$this->collection][$k] = $v;

            return $this;
        }

        public function fire($k, $args = [])
        {
            if (isset(self::$events[$this->collection][$k])) {
                if (is_callable(self::$events[$this->collection][$k])) {
                    $closure = self::$events[$this->collection][$k];

                    return call_user_func_array($closure, $args);
                }
            }
        }

        public function unlisten($k)
        {
            unset(self::$events[$this->collection][$k]);

            return $this;
        }
    }
