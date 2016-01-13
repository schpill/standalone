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

    use Countable;
    use IteratorAggregate;
    use ArrayAccess as AA;
    use ArrayIterator as AI;

    class RedmeLib implements AA, Countable, IteratorAggregate
    {
        private $db;

        public function __construct()
        {
            $this->db = lib('redys', ['me.' . forever()]);
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
            $this->db->hset('data', $k, serialize($v));

            return $this;
        }

        public function get($k, $default = null)
        {
            $value = $this->db->hget('data', $k);

            return $value ? unserialize($value) : $default;
        }

        public function has($k)
        {
            $value = $this->db->hexists('data', $k);

            return !empty($value);
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
            $this->db->hdel('data', $k);

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

        public function clear()
        {
            $this->db->del('data');

            return $this;
        }

        public function all()
        {
            $collection = [];
            $keys       = $this->db->hkeys('data');

            foreach ($keys as $key) {
                $collection[$key] = unserialize($this->db->hget('data', $key));
            }

            return $collection;
        }

        public function keys()
        {
            return $this->db->hkeys('data');
        }

        public function count()
        {
            return $this->db->hlen('data');
        }

        public function replace($items)
        {
            foreach ($items as $key => $value) {
                $this->set($key, $value);
            }

            return $this;
        }

        public function getIterator()
        {
            return new AI($this->all());
        }
    }
