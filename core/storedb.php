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

    use SplFixedArray;

    class StoredbCore
    {
        private $query = [], $resource, $store, $db, $table, $res, $write = false;

        public function __construct($db = null, $table = null)
        {
            $this->db       = is_null($db) ? SITE_NAME : $db;
            $this->table    = is_null($table) ? 'core' : $table;

            $this->store    = core('store', ['dbs.' . $this->db . '.' . $this->table]);

            $this->iterator();
        }

        public function instanciate($db = null, $table = null)
        {
            return new self($db, $table);
        }

        public function age()
        {
            return $this->store->get('age', strtotime('yesterday'));
        }

        public function __destruct()
        {
            if (true === $this->write) {
                $this->store->set('age', time());
                $this->store->hremove('rows');

                foreach ($this->collection() as $row) {
                    $this->store->hset('rows', $row['id'], $row);
                }
            }
        }

        public function collection($fixed = true)
        {
            $cursor = lib('array')->makeFromResource($this->resource);
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            return $fixed ? SplFixedArray::fromArray($cursor) : $cursor;
        }

        private function makeResource($cursor)
        {
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $this->resource = lib('array')->makeResource($cursor);
        }

        public function iterator($data = null)
        {
            $data = is_null($data) ? $this->store->hvals('rows') : $data;
            $this->makeResource($data);
        }

        public function add($row)
        {
            $this->write = true;

            $collection = $this->collection(false);

            $collection[] = $row;

            $this->iterator($collection);

            return $this;
        }

        public function create(array $data = [])
        {
            return $this->model($data);
        }

        public function save(array $data, $model = true)
        {
            $this->write = true;

            $id = isAke($data, 'id', null);

            if ($id) {
                return $this->update($data, $model);
            }

            $data['id']         = $this->makeId();
            $data['created_at'] = $data['updated_at'] = time();

            return $this->insert($data, $model);
        }

        private function insert(array $data, $model = true)
        {
            $this->write = true;

            $this->add($data);

            return $model ? $this->model($data) : $data;
        }

        private function update(array $data, $model = true)
        {
            $this->write = true;

            $data['updated_at'] = time();

            $this->delete($data['id']);

            $this->add($data);

            return $model ? $this->model($data) : $data;
        }

        public function delete($id)
        {
            $this->write = true;

            $newCollection = [];

            $exists = false;

            foreach ($this->collection() as $row) {
                if ($row['id'] != $id) {
                    $newCollection[] = $row;
                } else {
                    $exists = true;
                }
            }

            $this->iterator($newCollection);

            return $exists;
        }

        public function flush()
        {
            $this->write = true;

            $this->store->hremove('rows');
            $this->store->del('age');
            $this->store->del('ids');

            $this->iterator([]);

            return $this;
        }

        public function find($id, $model = true)
        {
            $row = coll($this->collection())->where(['id', '=', $id])->first();

            if ($row) {
                return $model ? $this->model($row) : $row;
            }

            return null;
        }

        public function findOrFail($id, $model = true)
        {
            $row = $this->find($id, false);

            if (!$row) {
                throw new Exception("The row $id does not exist.");
            } else {
                return $model ? $this->model($row) : $row;
            }
        }

        public function firstOrCreate($conditions)
        {
            $row = lib('array')->first($this->collection(), function ($k, $row) use ($conditions) {
                if (!isset($row['id'])) {
                    return false;
                }

                foreach ($conditions as $k => $v) {
                    if ($row[$k] != $v) {
                        return false;
                    }
                }

                return true;
            }, null);

            if (!$row) {
                return $this->save($conditions, true);
            } else {
                return $this->model($row);
            }
        }

        public function firstOrNew($conditions)
        {
            $row = lib('array')->first($this->collection(), function ($row) use ($conditions) {
                foreach ($conditions as $k => $v) {
                    if ($row[$k] != $v) {
                        return false;
                    }
                }

                return true;
            }, null);

            if (!$row) {
                return $this->model($conditions);
            } else {
                return $this->model($row);
            }
        }

        public function first($model = false)
        {
            if (!$this->res) {
                $this->res = coll($this->collection());
            }

            $row = $this->res->first();

            return $model ? $this->model($row) : $row;
        }

        public function last($model = false)
        {
            if (!$this->res) {
                $this->res = coll($this->collection());
            }

            $row = $this->res->last();

            return $model ? $this->model($row) : $row;
        }

        public function count()
        {
            if (!$this->res) {
                $this->res = coll($this->collection());
            }

            $last = $this->res;

            $count = $this->res->count();

            $this->res = $last;

            return $count;
        }

        public function __call($m, $a)
        {
            $this->query[] = func_get_args();

            if ($m != 'or') {
                $i = $this->res instanceof CollectionLib ? $this->res : coll($this->collection());

                $this->res = call_user_func_array([$i, $m], $a);

                return is_object($this->res) ? $this : $this->res;
            } else {
                if (!$this->res instanceof CollectionLib) {
                    throw new Exception("You must have at leat one more query before to call an or query.");
                } else {
                    $key        = sha1(serialize($this->query) . $this->db . $this->table);
                    $res        = array_values($this->res->toArray());

                    $i          = coll($this->collection());

                    $results    = call_user_func_array([$i, 'where'], $a);

                    $merged = $this->merge($key, array_merge(
                        $res,
                        array_values(
                            $results->toArray()
                        )
                    ));

                    $this->res = coll($merged);

                    return $this;
                }
            }
        }

        private function merge($key, $data = [])
        {
            return core('cache', ['storedb'])->aged($key, function () use ($data) {
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
            }, $this->age());
        }

        public function get($model = false)
        {
            if (!isset($this->res)) {
                $this->res = coll($this->collection());
            }

            return $model ? $this->models(array_values($this->res->toArray())) : lib('array')->iterator(array_values($this->res->toArray()));
        }

        public function toArray($model = false)
        {
            if (!isset($this->res)) {
                $this->res = coll($this->collection());
            }

            if (!$model) {
                return array_values($this->res->toArray());
            } else {
                $collection = [];

                foreach ($this->res as $row) {
                    $collection[] = $this->model($row);
                }

                return $collection;
            }
        }

        private function makeId()
        {
            return (int) $this->store->incr('ids');
        }

        public function models($rows = null)
        {
            if (!isset($this->res)) {
                $this->res = coll($this->collection());
            }

            $rows = is_null($rows) ? $this->res : $rows;

            foreach ($rows as $row) {
                yield $this->model($row);
            }
        }

        public function model(array $data = [])
        {
            return lib('model', [$this, $data]);
        }

        public function db()
        {
            return $this->db;
        }

        public function table()
        {
            return $this->table;
        }
    }
