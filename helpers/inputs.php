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

    class InputsLib
    {
        private function inputs()
        {
            return array_merge(
                $_POST,
                $_GET,
                $_REQUEST
            );
        }

        public function get($key, $default = null)
        {
            return isAke(
                $this->inputs(),
                $key,
                $default
            );
        }

        public function set($key, $value)
        {
            $_REQUEST[$key] = $value;

            return $this;
        }

        public function has($key)
        {
            $check = Utils::UUID();

            return isAke(
                $this->inputs(),
                $key,
                $check
            ) !== $check;
        }

        public function del($key)
        {
            return $this->delete($key);
        }

        public function forget($key)
        {
            return $this->delete($key);
        }

        public function remove($key)
        {
            return $this->delete($key);
        }

        public function delete($key)
        {
            unset($_REQUEST[$key]);

            return $this;
        }

        public function file($key)
        {
            return upload($key);
        }

        public function __set($k, $v)
        {
            return $this->set($k, $v);
        }

        public function __get($k)
        {
            return $this->get($k);
        }

        public function __isset($k)
        {
            return $this->has($k);
        }

        public function __unset($k)
        {
            return $this->delete($k);
        }

        public function __call($m, $a)
        {
            if (fnmatch('get*', $m)) {
                $key = Inflector::uncamelize(substr($m, 3));
                $default = empty($a) ? null : current($a);

                return $this->get($key, $default);
            } elseif (fnmatch('set*', $m)) {
                $key = Inflector::uncamelize(substr($m, 3));

                return $this->set($key, current($a));

            } elseif (fnmatch('has*', $m)) {
                $key = Inflector::uncamelize(substr($m, 3));

                return $this->has($key);

            } elseif (fnmatch('del*', $m)) {
                $key = Inflector::uncamelize(substr($m, 3));

                return $this->remove($key);
            } else {
                return empty($a) ? $this->get($m) : $this->set($m, current($a));
            }
        }
    }
