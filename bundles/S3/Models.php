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

    namespace S3;

    use Countable;
    use Iterator;
    use SplFixedArray;
    use S3Array;
    use Thin\Inflector;
    use Thin\Save;

    class Models implements Countable, Iterator
    {
        private $resource, $closure, $age, $count, $store, $db, $wheres, $cursor, $orders, $selects, $offset, $limit, $joins, $position = 0;

        public function __construct(Db $db, $closure = null)
        {
            $this->db       = $db;
            $this->wheres   = $db->wheres;
            $this->orders   = $db->orders;
            $this->selects  = $db->selects;
            $this->offset   = $db->offset;
            $this->limit    = $db->limit;
            $this->store    = $db->store;
            $this->joins    = $db->joins;
            $this->closure  = $closure;

            $this->age = $db->getAge();

            unset($this->count);

            $this->cursor();
        }

        public function __destruct()
        {
            if (is_resource($this->resource)) {
                fclose($this->resource);
            }

            $this->reset();
        }

        public function count($return = true)
        {
            if (!isset($this->count) || is_null($this->count)) {
                $this->count = count($this->getIterator());
            }

            return $return ? $this->count : $this;
        }

        public function getRow($id)
        {
            $row = $this->store->hget('data', $id, []);

            foreach ($row as $k => $v) {
                if (fnmatch('*_id', $k)) {
                    $fkTable        = str_replace('_id', '', $k);
                    $fkId           = (int) $v;
                    $row[$fkTable]  = $this->pivot($fkTable, $fkId);
                } else {
                    $type = $this->store->get('meta.' . $k, 'string');

                    switch ($type) {
                        case 'unknown type':
                        case 'string':
                            $row[$k] = (string) $v;
                            break;
                        case 'null':
                        case 'NULL':
                            $row[$k] = null;
                            break;
                        case 'object':
                            $row[$k] = (object) $v;
                            break;
                        case 'array':
                            $row[$k] = (array) $v;
                            break;
                        case 'integer':
                        case 'int':
                            $row[$k] = (int) $v;
                            break;
                        case 'double':
                            $row[$k] = (double) $v;
                            break;
                        case 'float':
                            $row[$k] = (float) $v;
                            break;
                        case 'bool':
                        case 'boolean':
                            $row[$k] = (bool) $v;
                            break;
                    }
                }
            }

            return $this->db->model($row);
        }

        public function getFieldValueById($field, $id)
        {
            return $this->store->hget('row.' . $id, $field);
        }

        public function getNext()
        {
            if (isset($this->cursor[$this->position])) {
                $row = $this->getRow($this->cursor[$this->position]);

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

            if (isset($this->cursor[$this->position])) {
                $row = $this->getRow($this->cursor[$this->position]);

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
            $row = $this->seek()->current($model);

            if (!is_null($this->closure)) {
                if (is_callable($this->closure)) {
                    $callable = $this->closure;
                    $row = $callable($row);
                }
            }

            return $row;
        }

        public function current()
        {
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                if (!empty($this->selects)) {
                    $row = [];
                    $row['id'] = $cursor[$this->position];

                    foreach ($this->selects as $field) {
                        $row[$field] = $this->store->hget('row.' . $cursor[$this->position], $field);
                    }

                    if (!is_null($this->closure)) {
                        if (is_callable($this->closure)) {
                            $callable = $this->closure;
                            $row = $callable($row);
                        }
                    }

                    return $this->db->model($row);
                } else {
                    $row = $this->getRow($cursor[$this->position]);

                    if (!is_null($this->closure)) {
                        if (is_callable($this->closure)) {
                            $callable = $this->closure;
                            $row = $callable($row);
                        }
                    }

                    return $row;
                }
            }

            return false;
        }

        public function getIterator()
        {
            $cursor = lib('array')->makeFromResource($this->resource);
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            return SplFixedArray::fromArray($cursor);
        }

        private function makeResource($cursor)
        {
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $this->resource = lib('array')->makeResource($cursor);

            $this->cursor = $this->resource;
        }
        private function setCached($key, $value)
        {
            $key = sha1($key . $this->db->db . $this->db->table);

            Save::set($key . '_' . $this->age, $value);
        }

        private function cached($key)
        {
            $key = sha1($key . $this->db->db . $this->db->table);

            $cached =  Save::get($key . '_' . $this->age);

            if ($cached) {
                return $cached;
            }

            return null;
        }

        public function groupBy($ids, $field)
        {
            $collection = $this->getGroupBy($ids, $field);

            $idselected = [];

            foreach ($collection as $row) {
                $idselected[] = $row['id'];
            }

            return $idselected;
        }

        public function getGroupBy($ids, $field)
        {
            $collection = $this->cached('groupby' . $field . '.' . sha1(serialize($this->wheres)));

            if (!$collection) {
                $collection = [];

                foreach ($ids as $id) {
                    $val = $this->store->hget('row.' . $id, $field);

                    if ($val) {
                        $add    = ['id' => $id, $field => $val];
                        $collection[] = $add;
                    }
                }

                $collection = (new Collection($collection))->groupBy($field)->toArray();

                $this->setCached('groupby' . $field . '.' . sha1(serialize($this->wheres)), $collection);
            }

            return $collection;
        }

        public function sum($field)
        {
            $sum = $this->cached('sum' . $field . '.' . sha1(serialize($this->wheres)));

            if (!$sum) {
                $collection = [];

                foreach ($this->cursor as $id) {
                    $val = $this->store->hget('row.' . $id, $field);

                    if ($val) {
                        $add    = ['id' => $id, $field => $val];
                        $collection[] = $add;
                    }
                }

                $collection = new Collection($collection);

                $sum = $collection->sum($field);

                $this->setCached('sum' . $field . '.' . sha1(serialize($this->wheres)), $sum);
            }

            return $sum;
        }

        public function avg($field)
        {
            $avg = $this->cached('avg' . $field . '.' . sha1(serialize($this->wheres)));

            if (!$avg) {
                $collection = [];

                foreach ($this->cursor as $id) {
                    $val = $this->store->hget('row.' . $id, $field);

                    if ($val) {
                        $add    = ['id' => $id, $field => $val];
                        $collection[] = $add;
                    }
                }

                $collection = new Collection($collection);

                $avg = $collection->avg($field);

                $this->setCached('avg' . $field . '.' . sha1(serialize($this->wheres)), $avg);
            }

            return $avg;
        }

        public function min($field)
        {
            $min = $this->cached('min' . $field . '.' . sha1(serialize($this->wheres)));

            if (!$min) {
                $collection = [];

                foreach ($this->cursor as $id) {
                    $val = $this->store->hget('row.' . $id, $field);

                    if ($val) {
                        $add    = ['id' => $id, $field => $val];
                        $collection[] = $add;
                    }
                }

                $collection = new Collection($collection);

                $min = $collection->min($field);

                $this->setCached('min' . $field . '.' . sha1(serialize($this->wheres)), $min);
            }

            return $min;
        }

        public function max($field)
        {
            $max = $this->cached('max' . $field . '.' . sha1(serialize($this->wheres)));

            if (!$max) {
                $collection = [];

                foreach ($this->cursor as $id) {
                    $val = $this->store->hget('row.' . $id, $field);

                    if ($val) {
                        $add    = ['id' => $id, $field => $val];
                        $collection[] = $add;
                    }
                }

                $collection = new Collection($collection);

                $max = $collection->max($field);

                $this->setCached('max' . $field . '.' . sha1(serialize($this->wheres)), $max);
            }

            return $max;
        }

        public function sortCursor($ids, $field, $direction = 'ASC')
        {
            $idsSorted = $this->cached('sort' . $field . '.' . $direction . '.' . sha1(serialize($this->wheres)));

            if (!$idsSorted) {
                $collection = [];

                foreach ($ids as $id) {
                    $val = $this->store->hget('row.' . $id, $field);

                    if ($val) {
                        $add    = ['id' => $id, $field => $val];
                        $collection[] = $add;
                    }
                }

                $collection = new Collection($collection);

                if ($direction == 'ASC') {
                    $collection = $collection->sortBy($field);
                } else {
                    $collection = $collection->sortByDesc($field);
                }

                $idsSorted = [];

                foreach ($collection as $row) {
                    $idsSorted[] = $row['id'];
                }

                $this->setCached('sort' . $field . '.' . $direction . '.' . sha1(serialize($this->wheres)), $idsSorted);
            }

            return $idsSorted;
        }

        public function multisort($ids, $sorts)
        {
            $fields = $directions = [];

            foreach ($sorts as $f => $d) {
                $fields[]       = $f;
                $directions[]   = $d;
            }

            $idsSorted = $this->cached('multisort' . sha1(serialize($sorts)) . '.' . sha1(serialize($this->wheres)));

            if (!$idsSorted) {
                $sortFunc = function($key, $direction) {
                    return function ($a, $b) use ($key, $direction) {
                        if (!isset($a[$key]) || !isset($b[$key])) {
                            return false;
                        }

                        if ('ASC' == $direction) {
                            return $a[$key] > $b[$key];
                        } else {
                            return $a[$key] < $b[$key];
                        }
                    };
                };

                $collection = [];

                foreach ($ids as $id) {
                    $add        = [];
                    $add['id']  = $id;

                    foreach ($fields as $field) {
                        $val = $this->store->hget('row.' . $id, $field);

                        if ($val) {
                            $add[$field] = $val;
                        }
                    }

                    $collection[] = $add;
                }

                for ($i = 0; $i < count($fields); $i++) {
                    usort(
                        $collection,
                        $sortFunc(
                            $fields[$i],
                            $directions[$i]
                        )
                    );
                }

                $idsSorted = [];

                foreach ($collection as $row) {
                    $idsSorted[] = $row['id'];
                }

                $this->setCached(
                    'multisort' . sha1(serialize($sorts)) . '.' . sha1(serialize($this->wheres)),
                    $idsSorted
                );
            }

            return S3Array::fromArray($idsSorted);
        }

        public function toArray()
        {
            if (empty($this->selects)) {
                return S3Array::fromArray(array_map(function ($row) {
                    $row = $this->getRow($row);


                    if (!is_null($this->closure)) {
                        if (is_callable($this->closure)) {
                            $callable = $this->closure;
                            $row = $callable($row);
                        }
                    }

                    return $row;
                }, iterator_to_array($this->getIterator())));
            } else {
                $fields = $this->selects;

                return S3Array::fromArray(array_map(function ($id) use ($fields) {
                    $row = [];
                    $row['id'] = $id;

                    foreach ($fields as $field) {
                        $row[$field] = $this->store->hget('row.' . $id, $field);
                    }

                    if (!is_null($this->closure)) {
                        if (is_callable($this->closure)) {
                            $callable = $this->closure;
                            $row = $callable($row);
                        }
                    }

                    return $row;
                }, iterator_to_array($this->getIterator())));
            }
        }

        public function fetch($object = false)
        {
            $row = $this->getNext();

            if ($row) {
                return $row;
            }

            $this->reset();

            return false;
        }

        public function first()
        {
            $this->position = 0;
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                if (empty($this->selects)) {
                    $row = $this->getRow($cursor[$this->position]);
                } else {
                    $fullrow = $this->getRow($cursor[$this->position]);
                    $row = [];
                    $row['id'] = isAke($fullrow, 'id');

                    foreach ($this->selects as $field) {
                        $row[$field] = isAke($fullrow, $field);
                    }
                }

                $id = isset($row->id);

                if (!$id) {
                    return null;
                }

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                $this->reset();

                return $row;
            }

            return null;
        }

        public function last()
        {
            $this->position = $this->count - 1;
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                if (empty($this->selects)) {
                    $row = $this->getRow($cursor[$this->position]);
                } else {
                    $fullrow = $this->getRow($cursor[$this->position]);
                    $row = [];
                    $row['id'] = isAke($fullrow, 'id');

                    foreach ($this->selects as $field) {
                        $row[$field] = isAke($fullrow, $field);
                    }
                }

                $id = isset($row->id);

                if (!$id) {
                    return null;
                }

                if (!is_null($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                $this->reset();

                return $row;
            }

            return null;
        }

        public function cursor()
        {
            if (!isset($this->cursor)) {
                $key = 'ids.' . $this->age;
                $ids = $this->store->get($key);

                if (!$ids) {
                    $ids = $this->store->hkeys('ids');
                    $this->store->set($key, $ids);

                    $ids = S3Array::fromArray(iterator_to_array($ids));
                } else {
                    if (is_object($ids)) {
                        $ids = S3Array::fromArray(iterator_to_array($ids));
                    } else {
                        $ids = S3Array::fromArray($ids);
                    }
                }

                if (!empty($this->wheres)) {
                    $this->cursor = $this->whereFactor($ids);
                } else {
                    $this->cursor = $ids;
                }

                if (!empty($this->orders)) {
                    $this->cursor = $this->multisort($ids, $this->orders);
                }

                if (!empty($this->groupBy)) {
                    $this->cursor = $this->groupBy($ids, $this->groupBy);
                }

                if (isset($this->limit)) {
                    $offset = 0;

                    if (isset($this->offset)) {
                        $offset = $this->offset;
                    }

                    $this->cursor = S3Array::fromArray(
                        array_slice(
                            iterator_to_array($this->cursor),
                            $offset,
                            $this->limit
                        )
                    );
                }
            }

            $this->makeResource($this->cursor);

            $this->count();
        }

        public function rand($amount = 1)
        {
            $collection = new Collection($this->getIterator());

            $this->cursor = $collection->random($amount)->toArray();

            $this->makeResource($this->cursor);

            return $this;
        }

        private function whereFactor($ids)
        {
            $first = true;

            foreach ($this->wheres as $where) {
                $condition  = current($where);
                $op         = end($where);

                $whereCursor = [];

                list($field, $operator, $value) = $condition;

                if (fnmatch('*.*', $field)) {
                    $tab = explode('.', $field);

                    if (count($tab) == 2) {
                        $ft = $tab[0];
                        $fk = Db::instance($this->db->db, $ft);
                        $f  = $tab[1];
                    } elseif (count($tab) == 3) {
                        $ft = $tab[1];
                        $fk = Db::instance($tab[0], $ft);
                        $f  = $tab[2];
                    }

                    $datasFk = $this->cached('whereCursor.' . sha1(serialize($condition)));

                    if (!$datasFk) {
                        $idsFk = [];

                        $rows = $fk->select('id')->where([$f, '=', $value])->get();

                        foreach ($rows as $row) {
                            $idsFk[] = $row['id'];
                        }

                        foreach ($ids as $id) {
                            $val = $this->store->hget('row.' . $id, $ft . '_id');

                            if (in_array($val, $idsFk)) {
                                $whereCursor[] = $id;
                            }
                        }

                        $this->setCached('whereCursor.' . sha1(serialize($condition)), $whereCursor);
                    } else {
                        $whereCursor = $datasFk;
                    }
                } else {
                    $whereCursor = $this->makeFieldValues($field, $ids, $operator, $value);
                }

                if (!$first) {
                    if ($op == '&&' || $op == 'AND') {
                        $cursor = array_intersect($cursor, $whereCursor);
                    } elseif ($op == '||' || $op == 'OR') {
                        $cursor = array_merge($cursor, $whereCursor);
                    }
                } else {
                    $first = false;
                    $cursor = $whereCursor;
                }
            }

            return $cursor;
        }

        private function compare($comp, $op, $value)
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

        private function makeFieldValues($field, $ids, $operator, $value)
        {
            $key    = 'queries.' . sha1($field . $operator . serialize($value)) . '.' . $this->age;

            $values = $this->store->get($key);

            if (!$values) {
                $values = [];

                foreach ($ids as $id) {
                    $val    = $this->store->hget('row.' . $id, $field);

                    $check  = $this->compare($val, $operator, $value);

                    if ($check) {
                        $values[] = $id;
                    }
                }

                $this->store->set($key, $values);
            }

            return $values;
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->db, $m], $a);
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

        public function update(array $data)
        {
            $cursor = $this->getIterator();

            foreach ($cursor as $id) {
                $row = $this->db->model($this->getRow($id));

                foreach ($data as $k => $v) {
                    $row->$k = $v;
                }

                $row->save();
            }

            return $this;
        }

        public function delete()
        {
            $cursor = $this->getIterator();

            foreach ($cursor as $id) {
                $row = $this->db->model($this->getRow($id));

                $row->delete();
            }

            return $this;
        }

        public function pivot($table, $id)
        {
            $cursor = new self(Db::instance($this->db->db, $table));

            $row = $cursor->getRow($id);

            if (!is_null($this->closure)) {
                if (is_callable($this->closure)) {
                    $callable = $this->closure;
                    $row = $callable($row);
                }
            }

            return $row;
        }
    }
