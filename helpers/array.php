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

    use SplFixedArray;

    class ArrayLib
    {
        private static $resources = [];

        public function fixed(array $array = [])
        {
            return SplFixedArray::fromArray($array);
        }

        public function makeResourceWithName($name, array $array = [])
        {
            $resource = fopen("php://memory", 'r+');
            fwrite($resource, serialize($array));

            self::$resources[$name] = $resource;

            return $this;
        }

        public function makeFromResourceName($name, $default = [], $unserialize = true)
        {
            $resource = isAke(self::$resources, $name, false);

            if (is_resource($resource)) {
                rewind($resource);

                $cnt = [];

                while (!feof($resource)) {
                    $cnt[] = fread($resource, 1024);
                }

                $data = implode('', $cnt);

                return $unserialize ? unserialize($data) : $data;
            }

            return $unserialize ? $default : serialize($default);
        }

        public function makeResource(array $array = [])
        {
            $resource = fopen("php://memory", 'r+');
            fwrite($resource, serialize($array));

            return $resource;
        }

        public function makeFromResource($resource, $default = [], $unserialize = true)
        {
            if (is_resource($resource)) {
                rewind($resource);

                $cnt = [];

                while (!feof($resource)) {
                    $cnt[] = fread($resource, 1024);
                }

                $data = implode('', $cnt);

                return $unserialize ? unserialize($data) : $data;
            }

            return $unserialize ? $default : serialize($default);
        }

        /*
            Retrieve value from a nested structure using a list of keys:

            $users = [
                ['name' => 'Igor Wiedler'],
                ['name' => 'Jane Doe'],
                ['name' => 'Acme Inc'],
            ];

            $name = lib('array')->getIn($users, [1, 'name']);
            'Jane Doe'

            Non existent keys return null:

            $data = ['foo' => 'bar'];

            $baz = lib('array')->getIn($data, ['baz']);
            null

            You can provide a default value that will be used instead of null:

            $data = ['foo' => 'bar'];

            $baz = lib('array')->getIn($data, ['baz'], 'qux');
            'qux'
        */

        function getIn(array $array, array $keys, $default = null)
        {
            if (!$keys) {
                return $array;
            }

            // This is a micro-optimization, it is fast for non-nested keys, but fails for null values
            if (count($keys) === 1 && isset($array[$keys[0]])) {
                return $array[$keys[0]];
            }

            $current = $array;

            foreach ($keys as $key) {
                if (!is_array($current) || !isAke($key, $current, false)) {
                    return $default;
                }

                $current = $current[$key];
            }

            return $current;
        }

        /*
            Apply a function to the value at a particular location in a nested structure:

            $data = ['foo' => ['answer' => 42]];
            $inc = function ($x) {
                return $x + 1;
            };

            $new = lib('array')->updateIn($data, ['foo', 'answer'], $inc);
            ['foo' => ['answer' => 43]]

            You can variadically provide additional arguments for the function:

            $data = ['foo' => 'bar'];
            $concat = function ($args) {
                return implode('', func_get_args());
            };

            $new = lib('array')->updateIn($data, ['foo'], $concat, ' is the ', 'best');
            ['foo' => 'bar is the best']
        */

        function updateIn(array $array, array $keys, Closure $f /* , $args... */)
        {
            $args = array_slice(func_get_args(), 3);

            if (!$keys) {
                return $array;
            }

            $current = &$array;

            foreach ($keys as $key) {
                if (!is_array($current) || !isAke($key, $current)) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            'Did not find path %s in structure %s',
                            json_encode($keys),
                            json_encode($array)
                        )
                    );
                }

                $current = &$current[$key];
            }

            $current = call_user_func_array(
                $f,
                array_merge(
                    [$current],
                    $args
                )
            );

            return $array;
        }

        /*
            Set a value at a particular location:

            $data = ['foo' => 'bar'];

            $new = lib('array')->assocIn($data, ['foo'], 'baz');
            ['foo' => 'baz']

            It will also set the value if it does not exist yet:
            $data = [];

            $new = lib('array')->assocIn($data, ['foo', 'bar'], 'baz');
            ['foo' => ['bar' => 'baz']]
        */

        function assocIn(array $array, array $keys, $value)
        {
            if (!$keys) {
                return $array;
            }

            $current = &$array;

            foreach ($keys as $key) {
                if (!is_array($current)) {
                    $current = [];
                }

                $current = &$current[$key];
            }

            $current = $value;

            return $array;
        }

        public function add($array, $key, $value)
        {
            if (is_null($this->get($array, $key))) {
                $this->set($array, $key, $value);
            }

            return $array;
        }

        public function build($array, callable $callback)
        {
            $results = [];

            foreach ($array as $key => $value) {
                list($innerKey, $innerValue) = call_user_func($callback, $key, $value);

                $results[$innerKey] = $innerValue;
            }

            return $results;
        }

        public function collapse($array)
        {
            $results = [];

            foreach ($array as $values) {
                if ($values instanceof CollectionLib) {
                    $values = $values->all();
                }

                $results = array_merge($results, $values);
            }

            return $results;
        }

        public function divide($array)
        {
            return [array_keys($array), array_values($array)];
        }

        public function dot($array, $prepend = '')
        {
            $results = [];

            foreach ($array as $key => $value) {
                if ($this->is($value)) {
                    $results = array_merge($results, $this->dot($value, $prepend.$key.'.'));
                } else {
                    $results[$prepend.$key] = $value;
                }
            }

            return $results;
        }

        public function except($array, $keys)
        {
            $this->forget($array, $keys);

            return $array;
        }

        public function fetch($array, $key)
        {
            foreach (explode('.', $key) as $segment) {
                $results = [];

                foreach ($array as $value) {
                    if (array_key_exists($segment, $value = (array) $value)) {
                        $results[] = $value[$segment];
                    }
                }

                $array = array_values($results);
            }

            return array_values($results);
        }

        public function first($array, callable $callback, $default = null)
        {
            foreach ($array as $key => $value) {
                if (call_user_func($callback, $key, $value)) {
                    return $value;
                }
            }

            return value($default);
        }

        public function last($array, callable $callback, $default = null)
        {
            return $this->first(array_reverse($array), $callback, $default);
        }

        public function flatten($array)
        {
            $return = [];

            array_walk_recursive($array, function($x) use (&$return) { $return[] = $x; });

            return $return;
        }

        public static function forget(&$array, $keys)
        {
            $original =& $array;

            foreach ((array) $keys as $key) {
                $parts = explode('.', $key);

                while (count($parts) > 1) {
                    $part = array_shift($parts);

                    if (isset($array[$part]) && is_array($array[$part])) {
                        $array =& $array[$part];
                    }
                }

                unset($array[array_shift($parts)]);

                // clean up after each pass
                $array =& $original;
            }
        }

        public function get($array, $key, $default = null)
        {
            if (is_null($key)) {
                return $array;
            }

            if (isset($array[$key])) {
                return $array[$key];
            }

            foreach (explode('.', $key) as $segment) {
                if (!$this->is($array) || !array_key_exists($segment, $array)) {
                    return value($default);
                }

                $array = $array[$segment];
            }

            return $array;
        }

        public function has($array, $key)
        {
            if (empty($array) || is_null($key)) {
                return false;
            }

            if (array_key_exists($key, $array)) {
                return true;
            }

            foreach (explode('.', $key) as $segment) {
                if (!$this->is($array) || ! array_key_exists($segment, $array)) {
                    return false;
                }

                $array = $array[$segment];
            }

            return true;
        }

        public function only($array, $keys)
        {
            return array_intersect_key($array, array_flip((array) $keys));
        }

        public function pluck($array, $value, $key = null)
        {
            $results = [];

            foreach ($array as $item) {
                $itemValue = $this->data_get($item, $value);

                if (is_null($key)) {
                    $results[] = $itemValue;
                } else {
                    $itemKey = $this->data_get($item, $key);

                    $results[$itemKey] = $itemValue;
                }
            }

            return $results;
        }

        public function pull(&$array, $key, $default = null)
        {
            $value = $this->get($array, $key, $default);

            $this->forget($array, $key);

            return $value;
        }

        public function set(&$array, $key, $value)
        {
            if (is_null($key)) {
                return $array = $value;
            }

            $keys = explode('.', $key);

            while (count($keys) > 1) {
                $key = array_shift($keys);

                if (!isset($array[$key]) || !$this->is($array[$key])) {
                    $array[$key] = [];
                }

                $array =& $array[$key];
            }

            $array[array_shift($keys)] = $value;

            return $array;
        }

        public function sort($array, callable $callback)
        {
            return coll($array)->sortBy($callback)->all();
        }

        public function where($array, callable $callback)
        {
            $filtered = [];

            foreach ($array as $key => $value) {
                if (call_user_func($callback, $key, $value)) {
                    $filtered[$key] = $value;
                }
            }

            return $filtered;
        }

        public function data_get($target, $key, $default = null)
        {
            if (is_null($key)) {
                return $target;
            }

            foreach (explode('.', $key) as $segment) {
                if ($this->is($target)) {
                    if (!array_key_exists($segment, $target)) {
                        return value($default);
                    }

                    $target = $target[$segment];
                } elseif ($target instanceof \ArrayAccess) {
                    if (!isset($target[$segment])) {
                        return value($default);
                    }

                    $target = $target[$segment];
                } elseif (is_object($target)) {
                    if (!isset($target->{$segment})) {
                        return value($default);
                    }

                    $target = $target->{$segment};
                } else {
                    return value($default);
                }
            }

            return $target;
        }

        public function iterator(array $array = [])
        {
            foreach ($array as $row) {
                yield $row;
            }
        }

        public function isEmpty($misc)
        {
            foreach ($misc as $row) {
                return false;
            }

            return true;
        }

        public function isNotEmpty($misc)
        {
            return !$this->isEmpty($misc);
        }

        /**
         * Tests if an array is associative or not.
         *
         *     // Returns TRUE
         *     $this->isAssoc(array('username' => 'john.doe'));
         *
         *     // Returns false
         *     $this->isAssoc('foo', 'bar');
         *
         * @param   array   $array  array to check
         * @return  boolean
         */

        public function isAssoc(array $array)
        {
            // Keys of the array
            $keys = array_keys($array);

            // If the array keys of the keys match the keys, then the array must
            // not be associative (e.g. the keys array looked like {0:0, 1:1...}).
            return array_keys($keys) !== $keys;
        }

        public function keys($misc)
        {
            if ($this->is($misc)) {
                return array_keys($misc);
            }

            return [];
        }

        public function values($misc)
        {
            if ($this->is($misc)) {
                return array_values($misc);
            }

            return [];
        }

        public function is($misc)
        {
            if (is_array($misc)) {
                // Definitely an array
                return true;
            } else {
                // Possibly a Traversable object, functionally the same as an array
                return (is_object($misc) && $misc instanceof \Traversable);
            }
        }

        public function in($needle, $misc)
        {
            if ($this->is($misc)) {
                // Definitely an array
                return in_array($needle, $misc);
            }

            return false;
        }

        public function exists($key, array $search)
        {
            return array_key_exists($key, $search);
        }
    }
