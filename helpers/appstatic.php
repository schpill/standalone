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

    class AppstaticLib
    {
        public static function __callStatic($m, $a)
        {
            return call_user_func_array([lib('app')->getInstance(), $m], $a);
        }
    }
