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

    class DynLib
    {
        private $native, $events = [];

        public function __construct($native = null)
        {
            $this->native = $native;
        }

        public function fn($m, callable $c)
        {
            $this->events[$m] = $c;

            return $this;
        }

        public function extend($m, callable $c)
        {
            return $this->fn($m, $c);
        }

        public function macro($m, callable $c)
        {
            return $this->fn($m, $c);
        }

        public function getNative()
        {
            return $this->native;
        }

        public function __call($m, $a)
        {
            $c = isAke($this->events, $m, null);

            if ($c) {
                if (is_callable($c)) {
                    $args = array_merge($a, [$this]);

                    return call_user_func_array($c, $args);
                }
            } else {
                if (fnmatch('_*', $m)) {
                    $m = strrev(substr(strtolower($m), 1));

                    return $this->fn($m, current($a));
                } else {
                    if (!is_null($this->native)) {
                        return call_user_func_array([$this->native, $m], $a);
                    }
                }
            }

            return null;
        }
    }
