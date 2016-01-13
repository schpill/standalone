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

    use ArrayAccess as AA;

    class MemcacheLib implements AA
    {
        private $file;

        public function __construct()
        {
            $this->file = STORAGE_PATH . DS . 'memcache_' . forever() . '.cache';

            if (!is_file($this->file)) {
                File::put($this->file, $this->serialize([]));
            }
        }

        private function serialize($var)
        {
            return serialize($var);
        }

        private function unserialize($var)
        {
            return unserialize($var);
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

        public function set($k, $v)
        {
            $tab = $this->unserialize(File::read($this->file));

            $tab[$k] = $v;

            File::delete($this->file);

            File::put($this->file, $this->serialize($tab));

            return $this;
        }

        public function get($k, $default = null)
        {
            $tab = $this->unserialize(File::read($this->file));

            return isAke($tab, $k, $default);
        }

        public function has($k)
        {
            $tab = $this->unserialize(File::read($this->file));

            $check = Utils::UUID();

            return $check != isAke($tab, $k, $check);
        }

        public function offsetSet($k, $v)
        {
            return $this->set($k, $v);
        }

        public function offsetGet($k)
        {
            return $this->get($k);
        }

        public function offsetExists($k)
        {
            return $this->has($k);
        }

        public function offsetUnset($k)
        {
            return $this->delete($k);
        }

        public function delete($k)
        {
            $tab = $this->unserialize(File::read($this->file));

            unset($tab[$k]);

            File::delete($this->file);

            File::put($this->file, $this->serialize($tab));

            return $this;
        }

        public function forget($k)
        {
            return $this->delete($k);
        }

        public function remove($k)
        {
            return $this->delete($k);
        }

        public function del($k)
        {
            return $this->delete($k);
        }
    }
