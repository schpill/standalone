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

    class ArdbLib
    {
        public $db, $table, $collection, $results, $cnx, $limit, $offset, $groupBy, $cacheClient, $events;
        public $wheres          = [];
        public $selects         = [];
        public $orders          = [];
        public $groupBys        = [];
        public $joins           = [];
        public $totalResults, $transactions = 0;

        private $useCache = true;

        public static $cache = [];

        public function __construct($db, $table)
        {
            $db                 = strtolower($db);
            $table              = strtolower($table);

            $this->db           = $db;
            $this->table        = $table;
            $this->collection   = "$db.$table";

            $store              = lib('arstore', [$this->collection]);

            Now::set('ardb.store.' . $this->collection, $store);

            $this->getAge();

            lib('facade', ['SplFixedArray', 'ArdbArray']);
        }

        public function reset()
        {
            $this->results      = null;
            $this->totalResults = 0;
            $this->transactions = 0;
            $this->selects      = [];
            $this->joinTables   = [];
            $this->wheres       = [];
            $this->groupBys     = [];
            $this->orders       = [];

            $store              = lib('arstore', [$this->collection]);

            Now::set('ardb.store.' . $this->collection, $store);

            return $this;
        }

        public function getAge()
        {
            $age = $this->store->get('age');

            if (!$age) {
                $age = strtotime('-1 day');
                $this->setAge($age);
            }

            return $age;
        }

        public function setAge($age = null)
        {
            $age = is_null($age) ? time() : $age;
            $this->store->set('age', (int) $age);

            return $this;
        }

        public function age($format = null)
        {
            $format = is_null($format) ? 'd/m/Y H:i:s' : $format;

            return date($format, $this->getAge());
        }

        public function create($data = [])
        {
            return $this->model($data);
        }

        public static function instance($db, $table)
        {
            $args   = func_get_args();
            $key    = sha1(serialize($args));
            $has    = Instance::has('ArDb', $key);

            if (true === $has) {
                return Instance::get('ArDb', $key);
            } else {
                return Instance::make('ArDb', $key, new self($db, $table));
            }
        }

        public function model($data = [])
        {
            $db     = $this->db;
            $table  = $this->table;

            $dir = Conf::get('dir.ardb.models', APPLICATION_PATH . DS . 'models' . DS . 'ArDb');

            $modelFile = $dir . DS . 'models' . DS . Inflector::lower($db) . DS . ucfirst(Inflector::lower($table)) . '.php';

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            if (!is_dir($dir . DS . 'models')) {
                File::mkdir($dir . DS . 'models');
            }

            if (!is_dir($dir . DS . 'models' . DS . Inflector::lower($db))) {
                File::mkdir($dir . DS . 'models' . DS . Inflector::lower($db));
            }

            if (!File::exists($modelFile)) {
                File::put($modelFile, str_replace('##class##', ucfirst(Inflector::lower($db)) . ucfirst(Inflector::lower($table)) . 'ArdbModel', File::read(__DIR__ . DS . 'dbModel.tpl')));
            }

            $class = '\\Thin\\' . ucfirst(Inflector::lower($db)) . ucfirst(Inflector::lower($table)) . 'ArdbModel';

            if (!class_exists($class)) {
                require_once $modelFile;
            }

            $model = $this;

            return new $class($model, $data);
        }

        private function addTuple($id, $keyTuple)
        {
            $this->store->set('tuples.id.' . $id, $keyTuple);
            $this->store->set('tuples.key.' . $keyTuple, $id);

            return $this;
        }

        private function delTuple($id)
        {
            $key = $this->store->get('tuples.id.' . $id);

            if ($key) {
                $this->store->del('tuples.key.' . $key);
                $this->store->del('tuples.id.' . $id);

                return true;
            }

            return false;
        }

        private function tuple($keyTuple)
        {
            $id = $this->store->get('tuples.key.' . $keyTuple);

            if ($id) {
                return $id;
            }

            return null;
        }

        private function analyze(array $data)
        {
            $clean = [];

            foreach ($data as $k => $v) {
                if (is_numeric($v) && !fnmatch('*phone*', $k) && !fnmatch('*zip*', $k) && $k != 'phone' && $k != 'zip' && $k != 'siret') {
                    if (fnmatch('*.*', $v) || fnmatch('*,*', $v)) {
                        $v = (float) $v;
                    } else {
                        $v = (int) $v;
                    }
                }

                $clean[$k] = $v;
            }

            return $clean;
        }

        public function permute($db, $table)
        {
            $this->db           = $db;
            $this->table        = $table;
            $this->collection   = "$db.$table";

            $this->getAge();

            return $this->reset();
        }

        public function makeId()
        {
            $id = $this->store->incr('id');

            $this->store->hdel('ids', $id);
            $this->store->hset('ids', $id, true);

            return $id;
        }

        public function lastInsertId()
        {
            return $this->store->get('id');
        }

        public function __destruct()
        {
            $this->reset();
        }

        public function findOne($query, $object = false)
        {
            return $this->where($query)->cursor()->first($object);
        }

        public function find($id, $object = true)
        {
            if (!is_numeric($id)) {
                return null;
            }

            $id     = (int) $id;
            $obj    = $this->store->hget('data', $id);

            if (!$obj) {
                return null;
            }

            foreach ($obj as $k => $v) {
                if (fnmatch('*_id', $k)) {
                    $fkTable        = str_replace('_id', '', $k);
                    $fkId           = (int) $v;
                    $obj[$fkTable]  = $this->pivot($fkTable, $fkId);
                }
            }

            return true === $object ? $this->model($obj) : $obj;
        }

        public function pivot($table, $id)
        {
            $cursor = new Cursor(self::instance($this->db, $table));

            return $cursor->getRow($id);
        }

        public function save(array $data)
        {
            $id = isAke($data, 'id', false);

            return !$id ? $this->add($data) : $this->edit($id, $data);
        }
    }
