<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2016 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */

    namespace Thin;

    use Countable;
    use Iterator;
    use SplFixedArray;

    class CursorCore implements Countable, Iterator
    {
        private $resource, $db, $position, $closure;

        public function __construct($db, callable $closure = null)
        {
            $this->dir = $db->dir();

            $this->resource = lib('array')->makeResource($this->ids());

            $this->db = $db;
            $this->position = 0;

            $this->closure = $closure;

            $this->count();
        }

        public function reset()
        {
            $this->position = 0;
            $this->resource = null;
            $this->db = null;
            $this->closure = null;

            return $this;
        }

        public function ids()
        {
            $dir = $this->dir . DS . 'id';

            if (is_dir($dir)) {
                $ids = [];

                $rows = glob($dir . DS . '*.blazz');

                foreach ($rows as $row) {
                    $id = str_replace([$dir . DS, '.blazz'], '', $row);

                    $ids[] = (int) $id;
                }

                return $ids;
            }

            return [];
        }

        public function __destruct()
        {
            if (is_resource($this->resource)) {
                fclose($this->resource);
            }

            $this->reset();
        }

        public function getIterator()
        {
            $cursor = lib('array')->makeFromResource($this->resource);
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            return SplFixedArray::fromArray($cursor);
        }

        public function count($return = true)
        {
            if (!isset($this->count) || is_null($this->count)) {
                $this->count = count($this->getIterator());
            }

            return $return ? $this->count : $this;
        }

        public function select($fields = null)
        {
            $data = [];

            if (is_null($fields)) {
                $fields = $this->fields();
            }

            if (is_string($fields)) {
                $fields = [$fields];
            }

            if (!in_array('id', $fields)) {
                $fields[] = 'id';
            }

            foreach ($this->getIterator() as $id) {
                $data[$id] = [];

                foreach ($fields as $field) {
                    $data[$id][$field] = $this->getFieldValueById($field, $id);
                }
            }

            return $data;
        }

        public function fields()
        {
            $fields = [];

            $files  = glob($this->dir . DS . '*');

            foreach ($files as $file) {
                if (is_dir($file) && !fnmatch('_*', $file)) {
                    $field = str_replace([$this->dir . DS], '', $file);

                    $fields[] = $field;
                }
            }

            return $fields;
        }

        public function getRow($id)
        {
            $row = null;

            $file = $this->dir . DS . $id . '.blazz';

            if (file_exists($file)) {
                $row = unserialize(File::read($file));

                foreach ($row as $k => $v) {
                    if (fnmatch('*_id', $k)) {
                        $fkTable        = str_replace('_id', '', $k);
                        $fkId           = (int) $v;
                        $row[$fkTable]  = $this->db->instanciate($this->db->db(), $fkTable)->find((int) $fkId);
                    }
                }
            }

            return $row;
        }

        public function getFieldValueById($field, $id, $d = null)
        {
            $file = $this->dir . DS . $field . DS . $id . '.blazz';

            if (file_exists($file)) {
                return unserialize(File::read($file));
            }

            return $d;
        }

        public function getNext()
        {
            if (isset($this->resource[$this->position])) {
                $row = $this->getRow($this->resource[$this->position]);

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

            if (isset($this->resource[$this->position])) {
                $row = $this->getRow($this->resource[$this->position]);

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

        public function seek($pos = 0)
        {
            $this->position = $pos;

            return $this;
        }

        public function one($model = false)
        {
            return $this->seek()->current($model);
        }

        public function current($model = false)
        {
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                $row = $this->getRow($cursor[$this->position]);

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $model ? $this->db->model($row) : $row;
            }

            return false;
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

            return isset($cursor[$this->position]);
        }

        public function each(callable $closure)
        {
            $row = $this->getNext();

            if ($row) {
                return $closure($row);
            }

            $this->reset();

            return false;
        }

        public function sum($field)
        {
            return coll($this->select($field))->sum($field);
        }

        public function min($field)
        {
            return coll($this->select($field))->min($field);
        }

        public function max($field)
        {
            return coll($this->select($field))->max($field);
        }

        public function avg($field)
        {
            return coll($this->select($field))->avg($field);
        }

        public function multisort($criteria)
        {
            $results = coll($this->select(array_keys($criteria)))->multisort($criteria);

            $this->resource = lib('array')->makeResource(array_values($results->fetch('id')->toArray()));

            $this->count = count($this->getIterator());
        }

        public function sortBy($field)
        {
            $results = coll($this->select($field))->sortBy($field);

            $this->resource = lib('array')->makeResource(array_values($results->fetch('id')->toArray()));

            $this->count = count($this->getIterator());
        }

        public function sortByDesc($field)
        {
            $results = coll($this->select($field))->sortByDesc($field);

            $this->resource = lib('array')->makeResource(array_values($results->fetch('id')->toArray()));

            $this->count = count($this->getIterator());
        }

        public function where($key, $operator = null, $value = null)
        {
            if (func_num_args() == 1) {
                if (is_array($key)) {
                    list($key, $operator, $value) = $key;
                    $operator = strtolower($operator);
                }
            }

            if (func_num_args() == 2) {
                list($value, $operator) = [$operator, '='];
            }

            $collection = coll($this->select($key));

            $results = $collection->filter(function($item) use ($key, $operator, $value) {
                $item = (object) $item;
                $actual = isset($item->{$key}) ? $item->{$key} : null;

                $insensitive = in_array($operator, ['=i', 'like i', 'not like i']);

                if ((!is_array($actual) || !is_object($actual)) && $insensitive) {
                    $actual = Inflector::lower(Inflector::unaccent($actual));
                }

                if ((!is_array($value) || !is_object($value)) && $insensitive) {
                    $value  = Inflector::lower(Inflector::unaccent($value));
                }

                if ($insensitive) {
                    $operator = str_replace(['=i', 'like i'], ['=', 'like'], $operator);
                }

                if ($key == 'id' || fnmatch('*_id', $key) && is_numeric($actual)) {
                    $actual = (int) $actual;
                }

                switch ($operator) {
                    case '<>':
                    case '!=':
                        return sha1(serialize($actual)) != sha1(serialize($value));
                    case '>':
                        return $actual > $value;
                    case '<':
                        return $actual < $value;
                    case '>=':
                        return $actual >= $value;
                    case '<=':
                        return $actual <= $value;
                    case 'between':
                        return $actual >= $value[0] && $actual <= $value[1];
                    case 'not between':
                        return $actual < $value[0] || $actual > $value[1];
                    case 'in':
                        return in_array($actual, $value);
                    case 'not in':
                        return !in_array($actual, $value);
                    case 'like':
                        $value  = str_replace("'", '', $value);
                        $value  = str_replace('%', '*', $value);

                        return fnmatch($value, $actual);
                    case 'not like':
                        $value  = str_replace("'", '', $value);
                        $value  = str_replace('%', '*', $value);

                        $check  = fnmatch($value, $actual);

                        return !$check;
                    case 'is':
                        return is_null($actual);
                    case 'is not':
                        return !is_null($actual);
                    case '=':
                    default:
                        return sha1(serialize($actual)) == sha1(serialize($value));
                }
            });

            $this->resource = lib('array')->makeResource(array_values($results->fetch('id')->toArray()));

            $this->count = count($this->getIterator());
        }

        public function toArray($fields = null)
        {
            $fields = is_null($fields) ? $this->select() : $fields;

            if (!in_array('id', $fields)) {
                $fields[] = 'id';
            }

            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $cursor = (array) $cursor;

            return SplFixedArray::fromArray(array_map(function ($id) use ($fields) {
                $row = [];
                $row['id'] = $id;

                foreach ($fields as $field) {
                    $row[$field] = $this->getFieldValueById($field, $id);
                }

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $row;
            }, $cursor));
        }

        public function toModels($fields = null)
        {
            $fields = is_null($fields) ? $this->select() : $fields;

            if (!in_array('id', $fields)) {
                $fields[] = 'id';
            }

            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $cursor = (array) $cursor;

            return SplFixedArray::fromArray(array_map(function ($id) use ($fields) {
                $row = [];
                $row['id'] = $id;

                foreach ($fields as $field) {
                    $row[$field] = $this->getFieldValueById($field, $id);
                }

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $this->db->model($row);
            }, $cursor));
        }

        public function get($object = false)
        {
            return $this;
            $row = $this->getNext();wvd($this->db->db());

            if ($row) {
                return $object ? $this->db->model($row) : $row;
            }

            $this->reset();

            return false;
        }

        public function fetch($object = false)
        {
            $row = $this->getNext();

            if ($row) {
                return $object ? $this->db->model($row) : $row;
            }

            $this->reset();

            return false;
        }

        public function model()
        {
            $row = $this->getNext();

            if ($row) {
                $id = isAke($row, 'id', false);

                return false !== $id ? $this->db->model($row) : false;
            }

            $this->reset();

            return false;
        }

        public function first($object = false, $fields = null)
        {
            $this->position = 0;
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                if (empty($fields)) {
                    $row = $this->getRow($cursor[$this->position]);
                } else {
                    if (!in_array('id', $fields)) {
                        $fields[] = 'id';
                    }

                    $fullrow = $this->getRow($cursor[$this->position]);
                    $row = [];
                    $row['id'] = isAke($fullrow, 'id');

                    foreach ($fields as $field) {
                        $row[$field] = isAke($fullrow, $field);
                    }
                }

                $id = isAke($row, 'id', false);

                if (!$id) {
                    return null;
                }

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                $return = $object ? $this->db->model($row) : $row;

                $this->reset();

                return $return;
            }

            return null;
        }

        public function last($object = false, $fields = null)
        {
            $this->position = $this->count - 1;
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                if (empty($fields)) {
                    $row = $this->getRow($cursor[$this->position]);
                } else {
                    if (!in_array('id', $fields)) {
                        $fields[] = 'id';
                    }

                    $fullrow = $this->getRow($cursor[$this->position]);
                    $row = [];
                    $row['id'] = isAke($fullrow, 'id');

                    foreach ($fields as $field) {
                        $row[$field] = isAke($fullrow, $field);
                    }
                }

                $id = isAke($row, 'id', false);

                if (!$id) {
                    return null;
                }

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                $return = $object ? $this->db->model($row) : $row;

                $this->reset();

                return $return;
            }

            return null;
        }

        private function merge($key, $data = [])
        {
            return fmr('cursor')->aged($key, function () use ($data) {
                $merged = $ids = [];

                foreach ($data as $row) {
                    $id = isAke($row, 'id', null);

                    if ($id) {
                        if (!in_array($id, $ids)) {
                            $ids[] = $id;
                            $merged[] = $row;
                        }
                    }
                }

                return $merged;
            }, $this->db->age());
        }

        public function __call($m, $a)
        {
            if ($m != 'or') {
                $collection = coll($this->select());

                $results = call_user_func_array([$collection, $m], $a);

                if (is_object($results)) {
                    $this->resource = lib('array')->makeResource(array_values($results->fetch('id')->toArray()));

                    $this->count = count($this->getIterator());

                    return $this;
                } else {
                    return $results;
                }
            } else {
                $oldIds = (array) $this->getIterator();
                call_user_func_array([$this, 'where'], $a);
                $intersect = array_intersect($oldIds, (array) $this->getIterator());

                $this->resource = lib('array')->makeResource(array_values($intersect));

                $this->count = count($this->getIterator());
            }
        }
    }
