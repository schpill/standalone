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

    use Phalcon\Tag;

    class HtmlLib
    {
        public function __call($method, $args)
        {
            return call_user_func_array([with(new Tag), $method], $args);
        }
    }
