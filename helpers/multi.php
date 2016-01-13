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

    class MultiLib implements \ArrayAccess
    {
        private $data = [];

        public function __clone()
        {
            foreach ($this->data as $key => $value) {
                if ($value instanceof self) {
                    $this[$key] = clone $value;
                }
            }
        }

        public function __construct(array $data = [])
        {
            foreach ($data as $key => $value) {
                $this[$key] = $value;
            }
        }

        public function offsetSet($offset, $data)
        {
            if (is_array($data)) {
                $data = new self($data);
            }

            if ($offset === null) {
                $this->data[] = $data;
            } else {
                $this->data[$offset] = $data;
            }
        }

        public function toArray()
        {
            $data = $this->data;

            foreach ($data as $key => $value) {
                if ($value instanceof self) {
                    $data[$key] = $value->toArray();
                }
            }

            return $data;
        }

        public function toJson()
        {
            return json_encode($this->toArray(), JSON_PRETTY_PRINT);
        }

        public function offsetGet($offset)
        {
            return $this->data[$offset];
        }

        public function offsetExists($offset)
        {
            return isset($this->data[$offset]);
        }

        public function offsetUnset($offset)
        {
            unset($this->data);
        }
    }
