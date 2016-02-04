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

    class BlazzLib
    {
        private $query = [], $toWrite = [], $toDelete = [], $resource, $store, $db, $table, $res, $file, $dir, $write = false;

        public function __construct($db = null, $table = null)
        {
            $this->db       = is_null($db) ? SITE_NAME : $db;
            $this->table    = is_null($table) ? 'core' : $table;

            $this->store    = lib('now', ['blazz']);

            $dir = Config::get('dir.flat.store', session_save_path());

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

            if (!is_file($this->dir . DS . 'age.blazz')) {
                File::put($this->dir . DS . 'age.blazz', '');
            }

            $this->cursor = core('cursor', [$this]);
        }

        public function instanciate($db = null, $table = null)
        {
            return new self($db, $table);
        }

        public function age()
        {
            return filemtime($this->dir . DS . 'age.blazz');
        }

        public function __destruct()
        {
            $this->refresh();
        }

        public function refresh()
        {
            if (true === $this->write) {
                if (!empty($this->toDelete)) {
                    foreach ($this->toDelete as $row) {
                        $id = isAke($row, 'id');
                        $file = $this->dir . DS . 'id' . DS . $id . '.blazz';

                        if (!is_dir($this->dir . DS . 'id')) {
                            File::mkdir($this->dir . DS . 'id');
                        }

                        File::delete($file);

                        foreach ($row as $k => $v) {
                            $file = $this->dir . DS . $k . DS . $id . '.blazz';

                            if (!is_dir($this->dir . DS . $k)) {
                                File::mkdir($this->dir . DS . $k);
                            }

                            File::delete($file);
                        }

                        $file = $this->dir . DS . $id . '.blazz';
                        File::delete($file);
                        touch($this->dir . DS . 'age.blazz', time());
                    }
                }

                if (!empty($this->toWrite)) {
                    foreach ($this->toWrite as $row) {
                        $id = isAke($row, 'id');

                        $id = (int) $id;

                        $file = $this->dir . DS . 'id' . DS . $id . '.blazz';

                        if (!is_dir($this->dir . DS . 'id')) {
                            File::mkdir($this->dir . DS . 'id');
                        }

                        File::delete($file);
                        File::put($file, serialize($id));

                        foreach ($row as $k => $v) {
                            if ($k == 'id') {
                                continue;
                            }

                            if (fnmatch ('*_id', $k)) {
                                $v = (int) $v;
                            }

                            if (!is_dir($this->dir . DS . $k)) {
                                File::mkdir($this->dir . DS . $k);
                            }

                            $file = $this->dir . DS . $k . DS . $id . '.blazz';
                            File::delete($file);
                            File::put($file, serialize($v));
                        }

                        $file = $this->dir . DS . $id . '.blazz';
                        File::delete($file);
                        File::put($file, serialize($row));
                        touch($this->dir . DS . 'age.blazz', time());
                    }
                }

                $this->write = false;
            }

            return $this;
        }

        public function add($row)
        {
            $id = isAke($row, 'id', null);

            if ($id) {
                $this->write = true;

                $this->toWrite[] = $row;
            }

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
            $row = $this->cursor->getRow($id);

            $exists = !is_null($row);

            if ($exists) {
                $this->write        = true;
                $this->toDelete[]   = $row;
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

        public function count()
        {
            if (empty($this->query)) {
                return count($this->cursor->ids());
            }

            $count = $this->cursor->count();

            return $count;
        }

        public function __call($m, $a)
        {
            $this->query[] = func_get_args();

            call_user_func_array([$this->cursor, $m], $a);

            return $this->cursor;
        }

        private function makeId()
        {
            $file = $this->dir . DS . 'lastid.blazz';

            if (is_file($file)) {
                $last = File::read($file);
                $new = $last + 1;

                File::delete($file);
                File::put($file, $new);

                return $new;
            }

            File::put($file, 1);

            return 1;
        }

        public function model(array $data = [])
        {
            return loadModel($this, $data);
        }

        public function db()
        {
            return $this->db;
        }

        public function table()
        {
            return $this->table;
        }

        public function dir()
        {
            return $this->dir;
        }

        public function cursor()
        {
            return $this->cursor;
        }
    }
