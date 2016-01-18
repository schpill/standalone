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

    class SessionLib
    {
        /**
         * [$_name description]
         * @var [type]
         */
        private $_name;

        /**
         * [__construct description]
         * @param [type] $name [description]
         */
        public function __construct($name = null)
        {
            $name = is_null($name) ? 'core' : $name;

            $this->_name = Inflector::urlize($name, '.');

            $this->check();
        }

        /**
         * Makes sure the session is initialized
         *
         * @return void
         */
        private function check()
        {
            if (session_id() == '') {
                session_start();
            }

            if (!isset($_SESSION['infos_' . session_id()])) {
                $_SESSION['infos_' . session_id()] = [];
            }

            if (!isset($_SESSION['infos_' . session_id()][$this->_name])) {
                $_SESSION['infos_' . session_id()][$this->_name]            = [];
                $_SESSION['infos_' . session_id()][$this->_name]['start']   = time();
                $_SESSION['infos_' . session_id()][$this->_name]['end']     = time() + Config::get('session.duration', 3600);
            } else {
                if (isset($_SESSION['infos_' . session_id()][$this->_name]['end'])) {
                    if (time() > $_SESSION['infos_' . session_id()][$this->_name]['end']) {
                        session_destroy();

                        return new self($this->_name);
                    } else {
                        $_SESSION['infos_' . session_id()][$this->_name]['end']     = time() + Config::get('session.duration', 3600);
                    }
                }
            }
        }

        /**
         * [starting description]
         * @return [type] [description]
         */
        public function starting()
        {
            if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                return $_SESSION['infos_' . session_id()][$this->_name]['start'];
            }

            return null;
        }

        /**
         * [ending description]
         * @return [type] [description]
         */
        public function ending()
        {
            if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                return $_SESSION['infos_' . session_id()][$this->_name]['end'];
            }

            return null;
        }

        /**
         * [__get description]
         * @param  [type] $key [description]
         * @return [type]      [description]
         */
        public function __get($key)
        {
            $this->check();

            return isAke($_SESSION, $this->name . '.' . $key, null);
        }

        /**
         * [__set description]
         * @param [type] $key   [description]
         * @param [type] $value [description]
         */
        public function __set($key, $value)
        {
            $this->check();

            if ($key == '_name') {
                $this->_name = $value;

                return $this;
            }

            $_SESSION[$this->_name . '.' . $key] = $value;

            if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                $_SESSION['infos_' . session_id()][$this->_name]['end'] = time() + Config::get('session.duration', 3600);
            }

            return $this;
        }

        /**
         * [__isset description]
         * @param  [type]  $key [description]
         * @return boolean      [description]
         */
        public function __isset($key)
        {
            $this->check();

            $dummy = sha1(__file__);

            return $dummy !== isAke($_SESSION, $this->_name . '.' . $key, $dummy);
        }

        /**
         * [__unset description]
         * @param [type] $key [description]
         */
        public function __unset($key)
        {
            $this->check();

            unset($_SESSION[$this->_name . '.' . $key]);

            if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                $_SESSION['infos_' . session_id()][$this->_name]['end'] = time() + Config::get('session.duration', 3600);
            }

            return $this;
        }

        /**
         * [get description]
         * @param  [type] $key     [description]
         * @param  [type] $default [description]
         * @return [type]          [description]
         */
        public function get($key, $default = null)
        {
            $this->check();

            return isAke($_SESSION, $this->_name . '.' . $key, $default);
        }

        public function has($key)
        {
            $this->check();

            $check = sha1(__file__);

            return $check != isAke($_SESSION, $this->_name . '.' . $key, $check);
        }

        public function getOr($key, callable $c)
        {
            if (!$this->has($key)) {
                $value = $c();

                $this->set($key, $value);

                return $value;
            }

            return $this->get($k);
        }

        /**
         * [put description]
         * @param  [type] $key   [description]
         * @param  [type] $value [description]
         * @return [type]        [description]
         */
        public function put($key, $value)
        {
            $this->check();

            $_SESSION[$this->_name . '.' . $key] = $value;

            if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                $_SESSION['infos_' . session_id()][$this->_name]['end'] = time() + Config::get('session.duration', 3600);
            }

            return $this;
        }

        /**
         * [set description]
         * @param [type] $key   [description]
         * @param [type] $value [description]
         */
        public function set($key, $value)
        {
            return $this->put($key, $value);
        }

        /**
         * [flash description]
         * @param  [type] $key [description]
         * @param  [type] $val [description]
         * @return [type]      [description]
         */
        public function flash($key, $val = null)
        {
            $this->check();
            $key = "flash_{$key}";

            if ($val != null) {
                $this->set($key, $val);

                if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                    $_SESSION['infos_' . session_id()][$this->_name]['end'] = time() + Config::get('session.duration', 3600);
                }
            } else {
                $val = $this->get($key);
                $this->remove($key);
            }

            return $val != null ? $this : $val;
        }

        /**
         * [__call description]
         * @param  [type] $m [description]
         * @param  [type] $a [description]
         * @return [type]    [description]
         */
        public function __call($m, $a)
        {
            $this->check();

            if (fnmatch('get*', $m)) {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($m, 3)));
                $key                = Inflector::lower($uncamelizeMethod);
                $args               = [$key];

                if (!empty($a)) {
                    $args[] = current($a);
                }

                return call_user_func_array([$this, 'get'], $args);
            } elseif (fnmatch('set*', $m)) {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($m, 3)));
                $key                = Inflector::lower($uncamelizeMethod);

                return $this->set($key, current($a));
            } elseif (fnmatch('forget*', $m)) {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($m, 6)));
                $key                = Inflector::lower($uncamelizeMethod);
                $args               = [$key];

                return call_user_func_array([$this, 'erase'], $args);
            }
        }

        /**
         * [forget description]
         * @param  [type] $key [description]
         * @return [type]      [description]
         */
        public function forget($key)
        {
            return $this->erase($key);
        }

        /**
         * [remove description]
         * @param  [type] $key [description]
         * @return [type]      [description]
         */
        public function remove($key)
        {
            return $this->erase($key);
        }

        /**
         * [erase description]
         * @param  [type] $key [description]
         * @return [type]      [description]
         */
        public function erase($key = null)
        {
            $this->check();

            if (!empty($key)) {
                unset($_SESSION[$this->_name . '.' . $key]);
            } else {
                foreach ($_SESSION as $k => $v) {
                    if (fnmatch($this->_name . '.*', $k)) {
                        unset($_SESSION[$k]);
                    }
                }
            }

            if (isset($_SESSION['infos_' . session_id()][$this->_name])) {
                $_SESSION['infos_' . session_id()][$this->_name]['end'] = time() + Config::get('session.duration', 3600);
            }

            return $this;
        }

        /**
         * Get session id.
         *
         * @return string
         */
        public function getSessionId()
        {
            return session_id();
        }

        /**
         * [regenerate description]
         * @return [type] [description]
         */
        public function regenerate()
        {
            return session_regenerate_id();
        }

        /**
         * Destroy the session.
         *
         * @return boolean
         */
        public function destroy()
        {
            return session_destroy();
        }

        /**
         * Get a new, random session ID.
         *
         * @return string
         */
        protected function generateSessionId()
        {
            return sha1(
                uniqid('', true) .
                Utils::token() .
                microtime(true)
            );
        }

        /**
         * Determine if this is a valid session ID.
         *
         * @param  string  $id
         * @return bool
         */
        public function isValidId($id)
        {
            return is_string($id) && preg_match('/^[a-f0-9]{40}$/', $id);
        }

        /**
         * [fill description]
         * @param  array  $data [description]
         * @return [type]       [description]
         */
        public function fill(array $data)
        {
            foreach ($data as $k => $v) {
                $this->set($k, $v);
            }

            return $this;
        }

        /**
         * [fillInfos description]
         * @param  array  $data [description]
         * @return [type]       [description]
         */
        public function fillInfos(array $data)
        {
            foreach ($data as $k => $v) {
                if ($k != 'start' && $k != 'end') {
                    $_SESSION['infos_' . session_id()][$this->_name][$k] = $v;
                }
            }

            return $this;
        }

        /**
         * [addInfo description]
         * @param [type] $k [description]
         * @param [type] $v [description]
         */
        public function addInfo($k, $v)
        {
            if ($k != 'start' && $k != 'end') {
                $_SESSION['infos_' . session_id()][$this->_name][$k] = $v;
            }

            return $this;
        }

        /**
         * [retrieveInfo description]
         * @param  [type] $k [description]
         * @param  [type] $v [description]
         * @return [type]    [description]
         */
        public function retrieveInfo($k, $v = null)
        {
            return isAke($_SESSION['infos_' . session_id()][$this->_name], $k, $v);
        }

        /**
         * [endAt description]
         * @param  [type] $time [description]
         * @return [type]       [description]
         */
        public function endAt($time)
        {
            if ($time instanceof TimeLib) {
                $time = $time->timestamp;
            }

            $_SESSION['infos_' . session_id()][$this->_name]['end'] = $time;
        }
    }
