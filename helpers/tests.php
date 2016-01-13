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

    class TestsLib
    {
        public $assert;

        public function __construct()
        {
            $this->assert = lib('test');
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->assert, $m], $a);
        }
    }
