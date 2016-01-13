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

    class MyconfstaticLib
    {
        private static $instances = [];

        public static function __callStatic($m, $a)
        {
            return call_user_func_array([lib('myconf'), $m], $a);
        }

        public static function instance($ns = 'core')
        {
            $i = isAke(self::$instances, $ns, false);

            if (!$i) {
                $i = lib('myconf', [$ns]);
                self::$instances[$ns] = $i;
            }

            return $i;
        }
    }
