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

    class NowstaticLib
    {
        private static $instances = [];

        public static function __callStatic($m, $a)
        {
            return call_user_func_array([lib('now'), $m], $a);
        }

        public static function instance($ns = 'core')
        {
            $i = isAke(self::$instances, $ns, false);

            if (!$i) {
                self::$instances[$ns] = $i = lib('now', [$ns]);
            }

            return $i;
        }
    }
