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

    class PathCore
    {
        public function set($k, $v)
        {
            path($k, $v);

            return $this;
        }

        public function get($k, $d = null)
        {
            return path($k, null, $d);
        }

        public function forget($k)
        {
            $k = 'helpers.paths.' . $k;

            core('registry')->delete($k);
        }
    }
