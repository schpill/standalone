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

    class EvLib
    {
        protected $listeners = [];

        public function on($event, callable $listener)
        {
            if (!isset($this->listeners[$event])) {
                $this->listeners[$event] = [];
            }

            $this->listeners[$event][] = $listener;

            return $this;
        }

        public function once($event, callable $listener)
        {
            $onceListener = function () use (&$onceListener, $event, $listener) {
                $this->removeListener($event, $onceListener);

                call_user_func_array($listener, func_get_args());
            };

            return $this->on($event, $onceListener);
        }

        public function removeListener($event, callable $listener)
        {
            if (isset($this->listeners[$event])) {
                $index = array_search($listener, $this->listeners[$event], true);

                if (false !== $index) {
                    unset($this->listeners[$event][$index]);
                }
            }

            return $this;
        }

        public function removeAllListeners($event = null)
        {
            if ($event !== null) {
                unset($this->listeners[$event]);
            } else {
                $this->listeners = [];
            }

            return $this;
        }

        public function listeners($event)
        {
            return isset($this->listeners[$event]) ? $this->listeners[$event] : [];
        }

        public function emit($event, array $arguments = [])
        {
            foreach ($this->listeners($event) as $listener) {
                call_user_func_array($listener, $arguments);
            }

            return $this;
        }
    }
