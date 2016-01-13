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

    class TranslatestaticLib
    {
        public static $instance;

        public static function __callStatic($f, $a)
        {
            $i = is_null(static::$instance) ? static::$instance = lib('translate') : static::$instance;

            return call_user_func_array([$i, $f], $a);
        }
    }
