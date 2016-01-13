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

    namespace Raw;

    use Thin\Inflector;
    use Thin\Arrays;

    class Querystatic
    {
        private static $i;

        public static function __callStatic($method, $args)
        {
            if (!isset(self::$i)) {
                self::$i = new Query();
            }

            return call_user_func_array([self::$i, $method], $args);
        }
    }
