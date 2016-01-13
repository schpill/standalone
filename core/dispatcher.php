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

    class MiddlewareCore
    {
        public function set($k, callable $c)
        {
            dispatch($k, $c);

            return $this;
        }

        public function get($k, $d = null)
        {
            return dispatch($k, null, $d);
        }

        public function forget($k)
        {
            $k = 'helpers.commands.' . $k;

            core('registry')->delete($k);
        }

        public function fire($k, $args = [], $d = null)
        {
            $c = $this->get($k, $d);

            return call_user_func_array($c, $args);
        }
    }
