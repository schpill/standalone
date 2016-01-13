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

    use Closure;

    class ObserverLib
    {
        private $object, $token;
        private $hooks = [];

        public function __construct($object)
        {
            $this->object   = $object;
            $this->token    = Utils::token();

            $this->hooks[$this->token] = [];
        }

        public function set($event, $action)
        {
            if (!is_callable($action) || !$action instanceof Closure) {
                throw new Exception("The observer's set method requires a valid callback method.");
            }

            $this->hooks[$this->token][$event] = $action;

            return $this;
        }

        public function remove($event)
        {
            unset($this->hooks[$this->token][$event]);

            return $this;
        }

        public function forget($event)
        {
            return $this->remove($event);
        }

        public function get($event, $default = null)
        {
            return isAke($this->hooks[$this->token], $event, $default);
        }

        public function has($event)
        {
            $now = time();

            $callback = $this->get($event, $now);

            return $callback != $now;
        }

        public function fire($event, $args = [], $returnRes = false)
        {
            return $this->run($event, $args, $returnRes);
        }

        public function run($event, $args = [], $returnRes = false)
        {
            $cb = $this->get($event, false);

            if (false !== $cb && is_callable($cb)) {
                $args = array_merge([$this->object], $args);

                $res = call_user_func_array($cb, $args);

                if (true === $returnRes) {
                    return $res;
                }
            } else {
                throw new Exception("Event $event does not exist.");
            }

            return $this;
        }

        public function __call($method, $args)
        {
            return $this->run($method, $args);
        }
    }
