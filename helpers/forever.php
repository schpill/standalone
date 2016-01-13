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

    class ForeverLib
    {
        private static $store;

        private static function init()
        {
            if (!isset(self::$store)) {
                self::$store = lib('ephemere', [forever()]);
            }
        }

        public static function __callStatic($m, $a)
        {
            self::init();

            return call_user_func_array([self::$store, $m], $a);
        }

        public function __call($m, $a)
        {
            self::init();

            return call_user_func_array([self::$store, $m], $a);
        }
    }
