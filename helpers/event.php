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

    class EventLib
    {
        public function set($event, callable $closure)
        {
            $events = Now::get('core.events', []);
            $events[$event] = $closure;
            Now::set('core.events', $events);

            return $this;
        }

        public function get($event, $default = null)
        {
            $events = Now::get('core.events', []);

            $closure = isAke($events, $event, null);

            if ($closure) {
                return $closure;
            }

            return $default;
        }

        public function has($event)
        {
            $events = Now::get('core.events', []);

            $closure = isAke($events, $event, null);

            if ($closure) {
                return true;
            }

            return false;
        }

        public function remove($event)
        {
            $events = Now::get('core.events', []);

            $closure = isAke($events, $event, null);

            if ($closure) {
                unset($events[$event]);
                Now::set('core.events', $events);

                return true;
            }

            return false;
        }

        public function fire($event, $parameters = [])
        {
            $events = Now::get('core.events', []);

            $closure = isAke($events, $event, null);

            if ($closure) {
                return call_user_func_array($closure, $parameters);
            }

            return null;
        }
    }
