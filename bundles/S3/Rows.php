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

    class Rows implements Countable, Iterator
    {
        private $resource, $age, $count, $store, $db, $wheres, $cursor, $orders, $selects, $offset, $limit, $joins, $position = 0;

        public function __construct(Db $db)
        {
            $this->db       = $db;
            $this->store    = $db->store;

            $this->age = $db->getAge();

            unset($this->count);

            $this->resource = curl_init();

            $this->makeResource([]);
        }

        public function __destruct()
        {
            $this->reset();
        }

        public function count($return = false)
        {
            if (!isset($this->count) || is_null($this->count)) {
                $this->count = count($this->getIterator());
            }

            return $this->count;
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

            return $row;
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
                if (!empty($this->selects)) {
                    $row = [];
                    $row['id'] = $cursor[$this->position];

                    foreach ($this->selects as $field) {
                        $row[$field] = $this->store->hget('row.' . $cursor[$this->position], $field);
                    }

                    return $model ? $this->db->model($row) : $row;
                } else {
                    $row = $this->getRow($cursor[$this->position]);

                    return $model ? $this->db->model($row) : $row;
                }
            }

            return false;
        }

        public function getIterator()
        {
            $cursor = unserialize(
                curl_getinfo(
                    $this->resource,
                    CURLINFO_PRIVATE
                )
            );

            if (is_array($cursor)) {
                $cursor = array_values($cursor);
            } elseif (is_object($cursor)) {
                $cursor = array_values(iterator_to_array($cursor));
            }

            return SplFixedArray::fromArray($cursor);
        }

        public function getCursor()
        {
            $cursor = unserialize(
                curl_getinfo(
                    $this->resource,
                    CURLINFO_PRIVATE
                )
            );

            if (is_array($cursor)) {
                $cursor = array_values($cursor);
            } elseif (is_object($cursor)) {
                $cursor = array_values(iterator_to_array($cursor));
            }

            return $cursor;
        }

        public function add($row)
        {
            $id = isAke($row, 'id', false);

            if ($id) {
                $cursor = $this->getCursor();

                $cursor[] = $id;

                $this->makeResource($cursor);
            }

            return $this;
        }

        private function makeResource($cursor)
        {
            curl_setopt(
                $this->resource,
                CURLOPT_PRIVATE,
                serialize($cursor)
            );

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

                $collection = with(new Collection($collection))->groupBy($field)->toArray();

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
                    return $this->getRow($row);
                }, iterator_to_array($this->getIterator())));
            } else {
                $fields = $this->selects;

                return S3Array::fromArray(array_map(function ($id) use ($fields) {
                    $row = [];
                    $row['id'] = $id;

                    foreach ($fields as $field) {
                        $row[$field] = $this->store->hget('row.' . $id, $field);
                    }

                    return $row;
                }, iterator_to_array($this->getIterator())));
            }
        }

        public function toObject()
        {
            if (empty($this->selects)) {
                return S3Array::fromArray(array_map(function ($row) {
                    return $this->db->model($this->getRow($row));
                }, iterator_to_array($this->getIterator())));
            } else {
                $fields = $this->selects;

                return S3Array::fromArray(array_map(function ($id) use ($fields) {
                    $row = [];
                    $row['id'] = $id;

                    foreach ($fields as $field) {
                        $row[$field] = $this->store->hget('row.' . $id, $field);
                    }

                    return $this->db->model($row);
                }, iterator_to_array($this->getIterator())));
            }
        }

        public function toJson()
        {
            return json_encode($this->toArray());
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

        public function first($object = false)
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

                $id = isAke($row, 'id', false);

                if (!$id) {
                    return null;
                }

                $this->reset();

                return $object ? $this->db->model($row) : $row;
            }

            return null;
        }

        public function last($object = false)
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

                $id = isAke($row, 'id', false);

                if (!$id) {
                    return null;
                }

                $this->reset();

                return $object ? $this->db->model($row) : $row;
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

            return $cursor->getRow($id);
        }
    }
