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

    use Raw\Store as RStore;

    class StoreLib
    {
        private $store;

        private static $i;

        public function __construct($ns = 'core.cache')
        {
            $this->store = new RStore($ns);
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->store, $m], $a);
        }

        public static function __callStatic($m, $a)
        {
            if (!isset(self::$i)) {
                self::$i = new self;
            }

            return call_user_func_array([static::$i, $m], $a);
        }
    }
