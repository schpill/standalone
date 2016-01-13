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

    class ItemcollectionLib
    {
        private static $collections = [];

        public static function getInstance($ns)
        {
            if (!isset(self::$collections[$ns])) {
                self::$collections[$ns] = lib('myiterator');
            }

            return self::$collections[$ns];
        }
    }
