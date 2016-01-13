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
    use Countable;
    use Iterator;
    use ArrayAccess;

    class MyiteratorLib implements Countable, Iterator, ArrayAccess
    {
        private $resource, $closure, $count, $position = 0;

        public function __construct(array $array = [], $closure = null)
        {
            $this->makeResource($array);
            $this->closure = $closure;
        }

        public function getIterator()
        {
            $cursor = lib('array')->makeFromResource($this->resource);

            return is_array($cursor) ? $cursor : iterator_to_array($cursor);
        }

        private function makeResource($cursor)
        {
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $this->resource = lib('array')->makeResource($cursor);

            return $this;
        }

        public function __destruct()
        {
            if (is_resource($this->resource)) {
                fclose($this->resource);
            }
        }

        public function count($return = true)
        {
            if (!isset($this->count) || is_null($this->count)) {
                $cursor         = $this->getIterator();
                $cursor         = is_array($cursor) ? $cursor : iterator_to_array($cursor);
                $this->count    = count($cursor);
            }

            return $return ? $this->count : $this;
        }

        public function one()
        {
            return $this->seek()->current();
        }

        public function current($row = false)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            if (isset($cursor[$this->position])) {
                $row = $cursor[$this->position];

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }
            }

            return $row;
        }

        public function seek($pos = 0)
        {
            $this->position = $pos;

            return $this;
        }

        public function getNext()
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            if (isset($cursor[$this->position])) {
                $row = $cursor[$this->position];

                $this->position++;

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $row;
            }

            return false;
        }

        public function getPrev()
        {
            $this->position--;
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            if (isset($cursor[$this->position])) {
                $row = $cursor[$this->position];

                $this->position++;

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $row;
            }

            return false;
        }

        public function __call($m, $a)
        {
            $cursor     = $this->getIterator();
            $cursor     = is_array($cursor) ? $cursor : iterator_to_array($cursor);
            $collection = lib('collection', [$cursor]);
            $return     = call_user_func_array([$collection, $m], $a);

            return $return instanceof CollectionLib
                ? new self(array_values($return->toArray()), $this->closure)
                : $return;
        }

        public function toArray()
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            return $cursor;
        }

        public function toJson()
        {
            return json_encode($this->toArray());
        }

        public function first()
        {
            $this->position = 0;
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            if (isset($cursor[$this->position])) {
                $row = $cursor[$this->position];

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $row;
            }

            return null;
        }

        public function last($object = false)
        {
            $this->position = $this->count() - 1;
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            if (isset($cursor[$this->position])) {
                $row = $cursor[$this->position];

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $row;
            }

            return null;
        }

        public function rewind()
        {
            $this->position = 0;
        }

        public function key()
        {
            return $this->position;
        }

        public function next()
        {
            ++$this->position;
        }

        public function valid()
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            return isset($cursor[$this->position]);
        }

        public function getCachingIterator($flags = \CachingIterator::CALL_TOSTRING)
        {
            return new \CachingIterator(new \ArrayIterator($this->getIterator()), $flags);
        }

        public function put($key, $row)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $cursor[$key] = $row;

            $this->makeResource($cursor);

            return $this;
        }

        public function append($row)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $cursor[] = $row;

            $this->makeResource($cursor);

            return $this;
        }

        public function prepend($row)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            array_unshift($cursor, $row);

            $this->makeResource($cursor);

            return $this;
        }

        public function pull($key, $default = null)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            return array_pull($cursor, $key, $default);
        }

        public function find($key, $default = null)
        {
            return $this->pull($key, $default);
        }

        public function pop()
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $row = array_pop($cursor);

            $this->makeResource($cursor);

            return $row;
        }

        public function shift()
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $row = array_shift($cursor);

            $this->makeResource($cursor);

            return $row;
        }

        public function shuffle()
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            shuffle($cursor);

            $this->makeResource($cursor);

            return $this;
        }

        public function slice($offset, $length = null, $preserveKeys = false)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $cursor = array_slice($cursor, $offset, $length, $preserveKeys);

            $this->makeResource($cursor);

            return $this;
        }

        public function splice($offset, $length = 0, $replacement = [])
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $cursor = array_splice($cursor, $offset, $length, $replacement);

            $this->makeResource($cursor);

            return $this;
        }

        public function transform(callable $callback)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $cursor = array_map($callback, $cursor);

            $this->makeResource($cursor);

            return $this;
        }

        public function random($amount = 1)
        {
            if ($this->count() < 1) {
                return [];
            }

            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $keys   = array_rand($cursor, $amount);

            return is_array($keys) ? array_intersect_key($cursor, array_flip($keys)) : $cursor[$keys];
        }

        public function reduce(callable $callback, $initial = null)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $cursor =  array_reduce($cursor, $callback, $initial);

            $this->makeResource($cursor);

            return $this;
        }

        public function reverse()
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $cursor = array_reverse($cursor);

            $this->makeResource($cursor);

            return $this;
        }

        public function add($row)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $cursor[] = $row;

            $this->makeResource($cursor);

            return $this;
        }

        public function where(callable $closure)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $collection = [];

            foreach ($cursor as $row) {
                $check = $closure($row, $this);

                if ($check) {
                    $collection[] = $row;
                }
            }

            return new self($collection);
        }

        public function offsetExists($k)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            return isset($cursor[$k]);
        }

        public function offsetGet($k)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            return isset($cursor[$k]) ? $cursor[$k] : null;
        }

        public function offsetSet($k, $v)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $cursor[] = $v;

            $this->makeResource($cursor);

            return $this;
        }

        public function offsetUnset($k)
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            unset($cursor[$k]);

            $this->makeResource($cursor);

            return $this;
        }

        public function compare($comp, $op, $value)
        {
            $res = false;

            if (strlen($comp) && strlen($op) && !empty($value)) {
                if (is_numeric($comp)) {
                    if (fnmatch('*,*', $comp) || fnmatch('*.*', $comp)) {
                        $comp = floatval($comp);
                    } else {
                        $comp = intval($comp);
                    }
                }

                if (is_numeric($value)) {
                    if (fnmatch('*,*', $value) || fnmatch('*.*', $value)) {
                        $value = floatval($value);
                    } else {
                        $value = intval($value);
                    }
                }

                switch ($op) {
                    case '=':
                        $res = sha1($comp) == sha1($value);
                        break;

                    case '=i':
                        $comp   = Inflector::lower(Inflector::unaccent($comp));
                        $value  = Inflector::lower(Inflector::unaccent($value));
                        $res    = sha1($comp) == sha1($value);
                        break;

                    case '>=':
                        $res = $comp >= $value;
                        break;

                    case '>':
                        $res = $comp > $value;
                        break;

                    case '<':
                        $res = $comp < $value;
                        break;

                    case '<=':
                        $res = $comp <= $value;
                        break;

                    case '<>':
                    case '!=':
                        $res = sha1($comp) != sha1($value);
                        break;

                    case 'LIKE':
                        $value  = str_replace("'", '', $value);
                        $value  = str_replace('%', '*', $value);

                        $res    = fnmatch($value, $comp);
                        break;

                    case 'NOT LIKE':
                    case 'NOTLIKE':
                        $value  = str_replace("'", '', $value);
                        $value  = str_replace('%', '*', $value);

                        $check  = fnmatch($value, $comp);

                        $res    = !$check;
                        break;


                    case 'BETWEEN':
                        $res = $comp >= $value[0] && $comp <= $value[1];
                        break;

                    case 'NOT BETWEEN':
                    case 'NOTBETWEEN':
                        $res = $comp < $value[0] || $comp > $value[1];
                        break;

                    case 'LIKE START':
                    case 'LIKESTART':
                        $value = str_replace(["'", '%'], '', $value);

                        $res    = (substr($comp, 0, strlen($value)) === $value);
                        break;

                    case 'LIKE END':
                    case 'LIKEEND':
                        $value = str_replace(["'", '%'], '', $value);

                        if (!strlen($comp)) {
                            $res = true;
                        }

                        $res = (substr($comp, -strlen($value)) === $value);
                        break;

                    case 'IN':
                        $value      = str_replace('(', '', $value);
                        $value      = str_replace(')', '', $value);
                        $tabValues  = explode(',', $value);
                        $res        = in_array($comp, $tabValues);
                        break;

                    case 'NOT IN':
                    case 'NOTIN':
                        $value      = str_replace('(', '', $value);
                        $value      = str_replace(')', '', $value);
                        $tabValues  = explode(',', $value);
                        $res        = !in_array($comp, $tabValues);
                        break;
                }
            }

            return $res;
        }
    }
