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

    class HookLib
    {
        public function set($hook, callable $closure)
        {
            $hooks = Now::get('core.hooks', []);
            $hooks[$hook] = $closure;
            Now::set('core.hooks', $hooks);

            return $this;
        }

        public function get($hook, $default = null)
        {
            $hooks = Now::get('core.hooks', []);

            $closure = isAke($hooks, $hook, null);

            if ($closure) {
                return $closure;
            }

            return $default;
        }

        public function has($hook)
        {
            $hooks = Now::get('core.hooks', []);

            $closure = isAke($hooks, $hook, null);

            if ($closure) {
                return true;
            }

            return false;
        }

        public function remove($hook)
        {
            $hooks = Now::get('core.hooks', []);

            $closure = isAke($hooks, $hook, null);

            if ($closure) {
                unset($hooks[$hook]);
                Now::set('core.hooks', $hooks);

                return true;
            }

            return false;
        }

        public function fire($hook, $parameters = [])
        {
            $hooks = Now::get('core.hooks', []);

            $closure = isAke($hooks, $hook, null);

            if ($closure) {
                return call_user_func_array($closure, $parameters);
            }

            return null;
        }
    }
