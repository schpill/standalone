<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2016 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */

    namespace Thin;

    class FlashLib
    {
        public function set($k, $v)
        {
            $k = 'flash.' . $k;

            Now::set($k, $v);

            return $this;
        }

        public function get($k, $d = null)
        {
            $k = 'flash.' . $k;

            return Now::get($k, $d);
        }

        public function has($k)
        {
            $k = 'flash.' . $k;

            return Now::has($k);
        }

        public function del($k)
        {
            $k = 'flash.' . $k;

            return Now::delrte($k);
        }

        public function delete($k)
        {
            $k = 'flash.' . $k;

            return Now::delrte($k);
        }

        public function remove($k)
        {
            $k = 'flash.' . $k;

            return Now::delrte($k);
        }

        public function forget($k)
        {
            $k = 'flash.' . $k;

            return Now::delrte($k);
        }

        public function __call($m, $a)
        {
            return $this->get($m);
        }
    }
