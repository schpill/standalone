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

    class BluzzLib
    {
        private $query = [];

        public function __construct($db = null, $table = null)
        {
            $this->db       = is_null($db) ? SITE_NAME : $db;
            $this->table    = is_null($table) ? 'core' : $table;

            $dir = Config::get('dir.bluzz.store', session_save_path());

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $dir .= DS . Inflector::urlize(Inflector::uncamelize($this->db));

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $this->dir = $dir . DS . Inflector::urlize(Inflector::uncamelize($this->table));

            if (!is_dir($this->dir)) {
                File::mkdir($this->dir);
            }

            $store = lib('redys', ["bluzz.$this->db.$this->table"]);

            Now::set("bluzz.store.$this->db.$this->table", $store);

            $this->cursor = lib('bluzzcursor', [$this]);

            $this->store->setnx('age', time());
        }

        public function __get($k)
        {
            if ($k == 'store') {
                return Now::get("bluzz.store.$this->db.$this->table");
            }

            return isset($this->$k) ? $this->$k : null;
        }

        public function add($row)
        {
            $id = isAke($row, 'id', null);

            if ($id) {
                foreach ($row as $k => $v) {
                    if (fnmatch('*_id', $k) || 'id' == $k) {
                        $v = (int) $v;
                    }

                    $this->store->set("field.$k.$id", $v);

                    $row[$k] = $v;
                }

                $this->store->set("row.$id", $row);

                touch($this->dir . DS . 'age.blizz', time());
            }

            return $this;
        }

        public function create(array $data = [])
        {
            return $this->model($data);
        }

        public function save(array $data, $model = true)
        {
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
            $this->add($data);

            return $model ? $this->model($data) : $data;
        }

        private function update(array $data, $model = true)
        {
            $data['updated_at'] = time();

            $this->delete($data['id']);

            $this->add($data);

            return $model ? $this->model($data) : $data;
        }

        public function delete($id)
        {
            $row = $this->cursor->getRow($id);

            $exists = !is_null($row);

            if ($exists) {
                $keys = $this->store->keys("field.*.$id");

                foreach ($keys as $key) {
                    $this->store->del($key);
                }

                $this->store->del("row.$id");

                $this->store->set('age', time());
            }

            return $exists;
        }

        public function flush()
        {
            File::rmdir($this->dir);

            return $this;
        }

        public function find($id, $model = true)
        {
            $row = $this->cursor->getRow($id);

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
            $data = $this->cursor->select(array_keys($conditions));

            $row = lib('array')->first($data, function ($k, $row) use ($conditions) {
                foreach ($conditions as $k => $v) {
                    if (fnmatch('*_id', $k) || $k == 'id') {
                        $v = (int) $v;
                    }

                    if ($row[$k] !== $v) {
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
            $data = $this->cursor->select(array_keys($conditions));

            $row = lib('array')->first($data, function ($k, $row) use ($conditions) {
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

        public function __call($m, $a)
        {
            if (fnmatch('findBy*', $m)) {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('findBy', '', $m)));

                if (strlen($field) > 0) {
                    return $this->where([$field, '=', current($a)]);
                }
            }

            if (fnmatch('countBy*', $m)) {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('countBy', '', $m)));

                if (strlen($field) > 0) {
                    return $this->where([$field, '=', current($a)])->count();
                }
            }

            if (fnmatch('groupBy*', $m)) {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('groupBy', '', $m)));

                if (strlen($field) > 0) {
                    return $this->groupBy($field);
                }
            }

            if (fnmatch('findOneBy*', $m)) {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('findOneBy', '', $m)));

                if (strlen($field) > 0) {
                    $model = false;

                    if (count($a) == 2) {
                        if (true === end($a)) {
                            $model = true;
                        }
                    }

                    return $this->where([$field, '=', current($a)])->first($model);
                }
            }

            if (fnmatch('firstBy*', $m)) {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('firstBy', '', $m)));

                if (strlen($field) > 0) {
                    $model = false;

                    if (count($a) == 2) {
                        if (true === end($a)) {
                            $model = true;
                        }
                    }

                    return $this->where([$field, '=', current($a)])->first($model);
                }
            }

            if (fnmatch('lastBy*', $m)) {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('lastBy', '', $m)));

                if (strlen($field) > 0) {
                    $model = false;

                    if (count($a) == 2) {
                        if (true === end($a)) {
                            $model = true;
                        }
                    }

                    return $this->where([$field, '=', current($a)])->last($model);
                }
            }

            $this->query[] = func_get_args();

            $res = call_user_func_array([$this->cursor, $m], $a);

            return is_object($res) && $res instanceof BluzzcursorLib ? $this->cursor : $res;
        }

        public function model(array $data = [])
        {
            return loadModel($this, $data);
        }

        public function instanciate($db = null, $table = null)
        {
            return new self($db, $table);
        }

        public function age()
        {
            return $this->store->get('age');
        }

        private function makeId()
        {
            return $this->store->incr('lastid');
        }

        public function db()
        {
            return $this->db;
        }

        public function table()
        {
            return $this->table;
        }

        public function store()
        {
            return Now::get("bluzz.store.$this->db.$this->table");
        }
    }
