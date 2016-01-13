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

    namespace S3;

    class Staticstore
    {
        public static $i;

        public static function __callStatic($m, $a)
        {
            if (!isset(self::$i)) {
                self::$i = new Store;
            }

            return call_user_func_array([static::$i, $m], $a);
        }
    }
