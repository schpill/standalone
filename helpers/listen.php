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

    class ListenLib
    {
        private $ns;
        private static $datas = [];

        public function getInstance($ns = 'core')
        {
            if (!isset(self::$datas[$ns])) {
                self::$datas[$ns] = [];
            }

            $this->ns = $ns;

            return $this;
        }

        public function broadcast($event, $args = [], $cb = null)
        {
            $closure = isAke(self::$datas[$this->ns], $event, false);

            if (is_callable($closure)) {
                $result = call_user_func_array($closure, $args);

                if (is_callable($cb)) {
                    return call_user_func_array($closure, [$result]);
                }

                return $result;
            }

            return false;
        }

        public function on($event, callable $cb)
        {
            self::$datas[$this->ns][$event] = $cb;

            return $this;
        }
    }
