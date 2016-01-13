<?php
    namespace Thin;

    use Closure;
    use BadMethodCallException;

    trait Iteractor
    {
        protected $_resource, $_count, $_position = 0;

        public function count($return = true)
        {
            if (!isset($this->_count) || is_null($this->_count)) {
                $this->_count = count($this->getIterator());
            }

            return $return ? $this->_count : $this;
        }

        public function rewind()
        {
            $this->_position = 0;
        }

        public function key()
        {
            return $this->_position;
        }

        public function next()
        {
            ++$this->_position;
        }

        public function valid()
        {
            $cursor = $this->getIterator();

            return isset($cursor[$this->_position]);
        }

        public function seek($pos = 0)
        {
            $this->_position = $pos;

            return $this;
        }

        public function one()
        {
            return $this->seek()->current();
        }


        public function makeIterator($data)
        {
            $this->makeResource($data);
        }

        private function makeResource($cursor)
        {
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $this->_resource = lib('array')->makeResource($cursor);
        }

        public function getIterator()
        {
            $cursor = lib('array')->makeFromResource($this->_resource);
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            return \SplFixedArray::fromArray($cursor);
        }

        public function each(callable $closure)
        {
            $row = $this->getNext();

            if ($row) {
                return $closure($row);
            }

            return false;
        }
    }

    trait Macroable
    {
        /**
         * The registered string macros.
         *
         * @var array
         */
        protected static $macros = [];

        /**
         * Register a custom macro.
         *
         * @param  string    $name
         * @param  callable  $macro
         * @return void
         */
        public static function macro($name, callable $macro)
        {
            static::$macros[$name] = $macro;
        }

        /**
         * Checks if macro is registered.
         *
         * @param  string  $name
         * @return bool
         */
        public static function hasMacro($name)
        {
            return isset(static::$macros[$name]);
        }

        /**
         * Dynamically handle calls to the class.
         *
         * @param  string  $method
         * @param  array   $parameters
         * @return mixed
         *
         * @throws \BadMethodCallException
         */
        public static function __callStatic($method, $parameters)
        {
            if (static::hasMacro($method)) {
                if (static::$macros[$method] instanceof Closure) {
                    return call_user_func_array(Closure::bind(static::$macros[$method], null, get_called_class()), $parameters);
                } else {
                    return call_user_func_array(static::$macros[$method], $parameters);
                }
            }

            throw new BadMethodCallException("Method {$method} does not exist.");
        }

        /**
         * Dynamically handle calls to the class.
         *
         * @param  string  $method
         * @param  array   $parameters
         * @return mixed
         *
         * @throws \BadMethodCallException
         */
        public function __call($method, $parameters)
        {
            if (static::hasMacro($method)) {
                if (static::$macros[$method] instanceof Closure) {
                    $args = array_merge([$this], $parameters);

                    return call_user_func_array(static::$macros[$method], $args);
                } else {
                    return call_user_func_array(static::$macros[$method], $parameters);
                }
            }

            throw new BadMethodCallException("Method {$method} does not exist.");
        }

        public function _app($m = null, $a = [])
        {
            if (is_null($m)) {
                return lib('app')->getInstance();
            }

            return lib('app')->getInstance()->make($m, $a);
        }
    }

    trait Setters
    {
        private $storage = [];

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
            $this->storage[$k] = $v;

            return $this;
        }

        public function get($k, $d = null)
        {
            return isAke($this->storage, $k, $d);
        }

        public function has($k)
        {
            $check = sha1(time());

            return $this->get($k, $check) != $check;
        }

        public function fill($data = [], $merge = true)
        {
            $data = $merge ? array_merge($this->storage, $data) : $data;

            $this->storage = $data;

            return $this;
        }

        public function populate($data = [])
        {
            foreach ($data as $k => $v) {
                $this->set($k, $v);
            }

            return $this;
        }

        public function hydrate($data = [])
        {
            return $this->fill($data, false);
        }

        public function incr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old + $by;

            $this->set($k, $new);

            return (int) $new;
        }

        public function increment($k, $by = 1)
        {
            return $this->incr($k, $by);
        }

        public function decr($k, $by = 1)
        {
            $old = $this->get($k, 1);
            $new = $old - $by;

            $this->set($k, $new);

            return (int) $new;
        }

        public function toArray()
        {
            return $this->storage;
        }

        public function toJson()
        {
            return json_encode($this->storage);
        }

        public function __toString()
        {
            return $this->toJson();
        }

        public function __call($m, $a)
        {
            $k = Inflector::uncamelize(substr($m, 3));

            if (fnmatch('get*', $m)) {
                $default = empty($a) ? null : current($a);

                return $this->get($k, $default);
            } elseif (fnmatch('set*', $m)) {
                return $this->set($k, current($a));
            } elseif (fnmatch('has*', $m)) {
                return $this->has($k);
            } else {
                $v = !empty($a) ? current($a) : true;

                return $this->set($m, $v);
            }
        }
    }
