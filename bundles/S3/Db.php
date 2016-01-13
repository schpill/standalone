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

    use Thin\Alias;
    use Thin\Arrays;
    use Thin\Config as Conf;
    use Thin\Utils;
    use Thin\File;
    use Thin\Exception;
    use Thin\Instance;
    use Thin\Inflector;
    use Thin\Container;
    use Thin\Keep;
    use Thin\Now;
    use Thin\Light;
    use Thin\Timer;

    class Db
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

        /**
         * [__construct description]
         *
         * @method __construct
         *
         * @param  [type]      $db    [description]
         * @param  [type]      $table [description]
         */
        public function __construct($db, $table)
        {
            $db                 = strtolower($db);
            $table              = strtolower($table);

            $this->db           = $db;
            $this->table        = $table;
            $this->collection   = "$db.$table";

            $store              = new Store($this->collection);
            $this->events       = new Event($this->collection);

            Now::set('S3.store.' . $this->collection, $store);

            $this->getAge();

            lib('facade', ['SplFixedArray', 'S3Array']);
        }

        /**
         * [reset description]
         *
         * @method reset
         *
         * @return [type] [description]
         */
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

            $store              = new Store($this->collection);
            $this->events       = new Event($this->collection);

            Now::set('S3.store.' . $this->collection, $store);

            return $this;
        }

        /**
         * [listen description]
         *
         * @method listen
         *
         * @param  callable $closure [description]
         *
         * @return [type]            [description]
         */
        public function listen(callable $closure)
        {
            $key = sha1(serialize($this->wheres));
            $this->events->listen($key, $closure);

            return $this;
        }

        /**
         * [getAge description]
         *
         * @method getAge
         *
         * @return [type] [description]
         */
        public function getAge()
        {
            $age = $this->store->get('age');

            if (!$age) {
                $age = strtotime('-1 day');
                $this->setAge($age);
            }

            return $age;
        }

        /**
         * [setAge description]
         *
         * @method setAge
         *
         * @param  [type] $age [description]
         */
        public function setAge($age = null)
        {
            $age = is_null($age) ? time() : $age;
            $this->store->set('age', (int) $age);

            return $this;
        }

        /**
         * [age description]
         *
         * @method age
         *
         * @param  [type] $format [description]
         *
         * @return [type]         [description]
         */
        public function age($format = null)
        {
            $format = is_null($format) ? 'd/m/Y H:i:s' : $format;

            return date($format, $this->getAge());
        }

        /**
         * [create description]
         *
         * @method create
         *
         * @param  array  $data [description]
         *
         * @return [type]       [description]
         */
        public function create($data = [])
        {
            return $this->model($data);
        }

        /**
         * [instance description]
         *
         * @method instance
         *
         * @param  [type]   $db    [description]
         * @param  [type]   $table [description]
         *
         * @return [type]          [description]
         */
        public static function instance($db, $table)
        {
            $args   = func_get_args();
            $key    = sha1(serialize($args));
            $has    = Instance::has('S3', $key);

            if (true === $has) {
                return Instance::get('S3', $key);
            } else {
                return Instance::make('S3', $key, new self($db, $table));
            }
        }

        /**
         * [model description]
         *
         * @method model
         *
         * @param  array  $data [description]
         *
         * @return [type]       [description]
         */
        public function model($data = [])
        {
            $db     = $this->db;
            $table  = $this->table;

            $dir = Conf::get('dir.S3.models', APPLICATION_PATH . DS . 'models' . DS . 'S3');

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
                File::put($modelFile, str_replace('##class##', ucfirst(Inflector::lower($db)) . ucfirst(Inflector::lower($table)) . 'S3Model', File::read(__DIR__ . DS . 'dbModel.tpl')));
            }

            $class = '\\Thin\\' . ucfirst(Inflector::lower($db)) . ucfirst(Inflector::lower($table)) . 'S3Model';

            if (!class_exists($class)) {
                require_once $modelFile;
            }

            $model = $this;

            return new $class($model, $data);
        }

        /**
         * [addTuple description]
         *
         * @method addTuple
         *
         * @param  [type]   $id       [description]
         * @param  [type]   $keyTuple [description]
         */
        private function addTuple($id, $keyTuple)
        {
            $this->store->set('tuples.id.' . $id, $keyTuple);
            $this->store->set('tuples.key.' . $keyTuple, $id);

            return $this;
        }

        /**
         * [delTuple description]
         *
         * @method delTuple
         *
         * @param  [type]   $id [description]
         *
         * @return [type]       [description]
         */
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

        /**
         * [tuple description]
         *
         * @method tuple
         *
         * @param  [type] $keyTuple [description]
         *
         * @return [type]           [description]
         */
        private function tuple($keyTuple)
        {
            $id = $this->store->get('tuples.key.' . $keyTuple);

            if ($id) {
                return $id;
            }

            return null;
        }

        /**
         * [analyze description]
         *
         * @method analyze
         *
         * @param  array   $data [description]
         *
         * @return [type]        [description]
         */
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

        /**
         * [permute description]
         *
         * @method permute
         *
         * @param  [type]  $db    [description]
         * @param  [type]  $table [description]
         *
         * @return [type]         [description]
         */
        public function permute($db, $table)
        {
            $this->db           = $db;
            $this->table        = $table;
            $this->collection   = "$db.$table";

            $this->getAge();

            return $this->reset();
        }

        /**
         * [makeId description]
         *
         * @method makeId
         *
         * @return [type] [description]
         */
        public function makeId()
        {
            $id = $this->store->incr('id');

            $this->store->hdel('ids', $id);
            $this->store->hset('ids', $id, true);

            return $id;
        }

        /**
         * [lastInsertId description]
         *
         * @method lastInsertId
         *
         * @return [type]       [description]
         */
        public function lastInsertId()
        {
            return $this->store->get('id');
        }

        /**
         * [__destruct description]
         *
         * @method __destruct
         */
        public function __destruct()
        {
            $this->reset();
        }

        /**
         * [findOne description]
         *
         * @method findOne
         *
         * @param  [type]  $query  [description]
         * @param  boolean $object [description]
         *
         * @return [type]          [description]
         */
        public function findOne($query, $object = false)
        {
            return $this->where($query)->cursor()->first($object);
        }

        /**
         * [find description]
         *
         * @method find
         *
         * @param  [type]  $id     [description]
         * @param  boolean $object [description]
         *
         * @return [type]          [description]
         */
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

        /**
         * [pivot description]
         *
         * @method pivot
         *
         * @param  [type] $table [description]
         * @param  [type] $id    [description]
         *
         * @return [type]        [description]
         */
        public function pivot($table, $id)
        {
            $cursor = new Cursor(self::instance($this->db, $table));

            return $cursor->getRow($id);
        }

        /**
         * [save description]
         *
         * @method save
         *
         * @param  array  $data [description]
         *
         * @return [type]       [description]
         */
        public function save(array $data)
        {
            $id = isAke($data, 'id', false);

            return !$id ? $this->add($data) : $this->edit($id, $data);
        }

        /**
         * [add description]
         *
         * @method add
         *
         * @param  array $data [description]
         */
        private function add(array $data)
        {
            $keep = $data;

            unset($keep['id']);
            unset($keep['created_at']);
            unset($keep['updated_at']);
            unset($keep['deleted_at']);

            $keyTuple = sha1($this->db . $this->table . serialize($keep));

            $tuple = $this->tuple($keyTuple);

            if (strlen($tuple)) {
                $o = $this->find($tuple);

                if ($o) {
                    return $o;
                }
            }

            $id = $this->makeId();

            $data['id'] = $id;

            if (!isset($data['created_at'])) {
                $data['created_at'] = (int) time();
            }

            if (!isset($data['updated_at'])) {
                $data['updated_at'] = (int) time();
            }

            $data = $this->analyze($data);

            $this->store->hdel('data', $id);
            $this->store->hset('data', $id, $data);

            $this->populateFields($data, $id);

            $this->addTuple($id, $keyTuple);

            $this->setAge();

            return $this->model($data);
        }

        /**
         * [insert description]
         *
         * @method insert
         *
         * @param  array  $data [description]
         *
         * @return [type]       [description]
         */
        public function insert(array $data)
        {
            $keep = $data;

            unset($keep['id']);
            unset($keep['created_at']);
            unset($keep['updated_at']);
            unset($keep['deleted_at']);

            $id = $this->makeId();

            $data['id'] = $id;

            if (!isset($data['created_at'])) {
                $data['created_at'] = (int) time();
            }

            if (!isset($data['updated_at'])) {
                $data['updated_at'] = (int) time();
            }

            $data = $this->analyze($data);

            $this->store->hdel('data', $id);
            $this->store->hset('data', $id, $data);

            $this->populateFields($data, $id);

            $this->setAge();

            return $this->model($data);
        }

        /**
         * [edit description]
         *
         * @method edit
         *
         * @param  [type] $id   [description]
         * @param  array  $data [description]
         *
         * @return [type]       [description]
         */
        private function edit($id, array $data)
        {
            $keep = $data;

            unset($keep['id']);
            unset($keep['created_at']);
            unset($keep['updated_at']);
            unset($keep['deleted_at']);

            $keyTuple = sha1($this->db . $this->table . serialize($keep));

            $tuple = $this->tuple($keyTuple);

            if (strlen($tuple)) {
                $o = $this->find($tuple);

                if ($o) {
                    return $o;
                }
            }

            $data['updated_at'] = (int) time();

            $this->store->hdel('data', $id);
            $this->store->hset('data', $id, $this->analyze($data));

            $this->populateFields($data, $id);

            $this->delTuple($id);
            $this->addTuple($id, $keyTuple);

            $this->setAge();

            return $this->find($id);
        }

        /**
         * [delete description]
         *
         * @method delete
         *
         * @param  [type] $id [description]
         *
         * @return [type]     [description]
         */
        public function delete($id)
        {
            $data = $this->store->hget('data', $id);
            $this->store->hdel('ids', $id);

            if ($data) {
                $this->delTuple($id);
                $data = $data;
                $this->store->hdel('data', $id);

                $this->depopulateFields($data, $id);

                $this->setAge();

                return true;
            }

            return false;
        }

        /**
         * [populateFields description]
         *
         * @method populateFields
         *
         * @param  [type]         $data [description]
         * @param  [type]         $id   [description]
         *
         * @return [type]               [description]
         */
        private function populateFields($data, $id)
        {
            $fields = array_keys($data);

            $this->store->del("fields.$id");
            $this->store->set("fields.$id", $fields);

            foreach ($data as $k => $v) {
                $this->store->hdel('row.' . $id, $k);

                if (!is_array($v) && !is_object($v)) {
                    if (is_bool($v)) {
                        $v = $v ? 1 : 0;
                    }
                } else {
                    $v = (array) $v;
                }

                $type = gettype($v);

                $meta = $this->store->has('meta.' . $k);

                if (!$meta) {
                    $this->store->set('meta.' . $k, $type);
                }

                if (fnmatch('*_id', $k)) {
                    $v = (int) $v;
                }

                $this->store->hdel($type . '.' . $id, $k);
                $this->store->hset($type . '.' . $id, $k, $v);

                $this->store->hset('row.' . $id, $k, $v);
            }

            return $this;
        }

        /**
         * [depopulateFields description]
         *
         * @method depopulateFields
         *
         * @param  [type]           $data [description]
         * @param  [type]           $id   [description]
         *
         * @return [type]                 [description]
         */
        private function depopulateFields($data, $id)
        {
            foreach ($data as $k => $v) {
                $type = $this->store->get('meta.' . $k, 'string');
                $this->store->hdel($type . '.' . $id, $k);
            }

            $this->store->del('fields.' . $id);
            $this->store->del('row.' . $id);

            return $this;
        }

        /**
         * [where description]
         *
         * @method where
         *
         * @param  array  $condition [description]
         * @param  string $op        [description]
         *
         * @return [type]            [description]
         */
        public function where($condition = [], $op = 'AND')
        {
            $check = isAke($this->wheres, sha1(serialize(func_get_args())), false);

            if (!$check) {
                if (!empty($condition)) {
                    if (!is_array($condition)) {
                        $condition  = str_replace(
                            [' LIKE START ', ' LIKE END ', ' NOT LIKE ', ' NOT IN ', ' NOT BETWEEN '],
                            [' LIKESTART ', ' LIKEEND ', ' NOTLIKE ', ' NOTIN ', ' NOT BETWEEN '],
                            $condition
                        );

                        if (fnmatch('* = *', $condition)) {
                            list($field, $value) = explode(' = ', $condition, 2);
                            $operand = '=';
                        } elseif (fnmatch('* < *', $condition)) {
                            list($field, $value) = explode(' < ', $condition, 2);
                            $operand = '<';
                        } elseif (fnmatch('* > *', $condition)) {
                            list($field, $value) = explode(' > ', $condition, 2);
                            $operand = '>';
                        } elseif (fnmatch('* <= *', $condition)) {
                            list($field, $value) = explode(' <= ', $condition, 2);
                            $operand = '<=';
                        } elseif (fnmatch('* >= *', $condition)) {
                            list($field, $value) = explode(' >= ', $condition, 2);
                            $operand = '>=';
                        } elseif (fnmatch('* LIKESTART *', $condition)) {
                            list($field, $value) = explode(' LIKESTART ', $condition, 2);
                            $operand = 'LIKESTART';
                        } elseif (fnmatch('* LIKEEND *', $condition)) {
                            list($field, $value) = explode(' LIKEEND ', $condition, 2);
                            $operand = 'LIKEEND';
                        } elseif (fnmatch('* NOTLIKE *', $condition)) {
                            list($field, $value) = explode(' NOTLIKE ', $condition, 2);
                            $operand = 'NOTLIKE';
                        } elseif (fnmatch('* LIKE *', $condition)) {
                            list($field, $value) = explode(' LIKE ', $condition, 2);
                            $operand = 'LIKE';
                        } elseif (fnmatch('* IN *', $condition)) {
                            list($field, $value) = explode(' IN ', $condition, 2);
                            $operand = 'IN';
                        } elseif (fnmatch('* NOTIN *', $condition)) {
                            list($field, $value) = explode(' NOTIN ', $condition, 2);
                            $operand = 'NOTIN';
                        } elseif (fnmatch('* NOTBETWEEN *', $condition)) {
                            list($field, $value) = explode(' NOTBETWEEN ', $condition, 2);
                            $operand = 'NOTIN';
                        } elseif (fnmatch('* != *', $condition)) {
                            list($field, $value) = explode(' != ', $condition, 2);
                            $operand = '!=';
                        } elseif (fnmatch('* <> *', $condition)) {
                            list($field, $value) = explode(' <> ', $condition, 2);
                            $operand = '<>';
                        }

                        $condition = [$field, $operand, $value];
                    }

                    if (strtoupper($op) == 'AND') {
                        $op = '&&';
                    } elseif (strtoupper($op) == 'OR') {
                        $op = '||';
                    } elseif (strtoupper($op) == 'XOR') {
                        $op = '|';
                    }

                    $this->wheres[sha1(serialize(func_get_args()))] = [$condition, $op];
                }
            }

            return $this;
        }

        /**
         * [order description]
         *
         * @method order
         *
         * @param  [type] $fieldOrder     [description]
         * @param  string $orderDirection [description]
         *
         * @return [type]                 [description]
         */
        public function order($fieldOrder, $orderDirection = 'ASC')
        {
            $this->orders[$fieldOrder] = $orderDirection;

            return $this;
        }

        /**
         * [select description]
         *
         * @method select
         *
         * @param  [type] $what [description]
         *
         * @return [type]       [description]
         */
        public function select($what)
        {
            /* polymorphism */
            if (func_num_args() == 1) {
                if (is_string($what)) {
                    if (fnmatch('*,*', $what)) {
                        $what = str_replace(' ', '', $what);
                        $what = explode(',', $what);
                    }
                }

                if (is_array($what)) {
                    foreach ($what as $seg) {
                        if (!in_array($seg, $this->selects)) {
                            $this->selects[] = $seg;
                        }
                    }
                } else {
                    if (!in_array($what, $this->selects)) {
                        $this->selects[] = $what;
                    }
                }
            } else {
                $fields = func_get_args();

                foreach ($fields as $field) {
                    if (!in_array($field, $this->selects)) {
                        $this->selects[] = $field;
                    }
                }
            }

            return $this;
        }

        /**
         * [timestamps description]
         *
         * @method timestamps
         *
         * @return [type]     [description]
         */
        public function timestamps()
        {
            return $this->select('created_at,updated_at');
        }

        /**
         * [all description]
         *
         * @method all
         *
         * @param  boolean $object [description]
         *
         * @return [type]          [description]
         */
        public function all($object = false)
        {
            return $object ? $this->reset()->cursor()->toObject() : $this->reset()->cursor()->toArray();
        }

        /**
         * [cursor description]
         *
         * @method cursor
         *
         * @param  [type] $what [description]
         *
         * @return [type]       [description]
         */
        public function cursor($what = null)
        {
            return $what instanceof Db ? new Cursor($what) : new Cursor($this);
        }

        /**
         * [models description]
         *
         * @method models
         *
         * @param  [type] $what [description]
         *
         * @return [type]       [description]
         */
        public function models($what = null)
        {
            return $what instanceof Db ? new Models($what) : new Models($this);
        }

        /**
         * [limit description]
         *
         * @method limit
         *
         * @param  [type]  $limit  [description]
         * @param  integer $offset [description]
         *
         * @return [type]          [description]
         */
        public function limit($limit, $offset = 0)
        {
            if (null !== $limit) {
                if (!is_numeric($limit) || $limit != (int) $limit) {
                    throw new \InvalidArgumentException('The limit is not valid.');
                }

                $limit = (int) $limit;
            }

            if (null !== $offset) {
                if (!is_numeric($offset) || $offset != (int) $offset) {
                    throw new \InvalidArgumentException('The offset is not valid.');
                }

                $offset = (int) $offset;
            }

            $this->limit    = $limit;
            $this->offset   = $offset;

            return $this;
        }

        /**
         * [offset description]
         *
         * @method offset
         *
         * @param  integer $offset [description]
         *
         * @return [type]          [description]
         */
        public function offset($offset = 0)
        {
            if (null !== $offset) {
                if (!is_numeric($offset) || $offset != (int) $offset) {
                    throw new \InvalidArgumentException('The offset is not valid.');
                }

                $offset = (int) $offset;
            }

            $this->offset = $offset;

            return $this;
        }

        /**
         * [findFirstBy description]
         *
         * @method findFirstBy
         *
         * @param  [type]      $field  [description]
         * @param  [type]      $value  [description]
         * @param  boolean     $object [description]
         *
         * @return [type]              [description]
         */
        public function findFirstBy($field, $value, $object = false)
        {
            return $this->where([$field, '=', $value])->get()->first($object);
        }

        /**
         * [findAndModify description]
         *
         * @method findAndModify
         *
         * @param  [type]        $where  [description]
         * @param  array         $update [description]
         *
         * @return [type]                [description]
         */
        public function findAndModify($where, array $update)
        {
            unset($update['id']);
            $where = is_numeric($where) ? ['id', '=', $where] : $where;

            $cursor = $this->where($where)->get();

            $collection = [];

            if ($cursor->count() > 0) {
                foreach ($cursor as $row) {
                    $id = isAke($row, 'id', 0);

                    if ($id > 0) {
                        $data = array_merge($row, $update);
                        $this->model($data)->save();
                        array_push($collection, $data);
                    }
                }
            }

            return $collection;
        }

        /**
         * [refresh description]
         *
         * @method refresh
         *
         * @return [type]  [description]
         */
        public function refresh()
        {
            return $this->setAge();
        }

        /**
         * [flush description]
         *
         * @method flush
         *
         * @return [type] [description]
         */
        public function flush()
        {
            $cursor = $this->get();

            if ($cursor->count() > 0) {
                while ($row = $cursor->model()) {
                    $row->delete();
                }
            }

            return $this->setAge();
        }

        /**
         * [drop description]
         *
         * @method drop
         *
         * @return [type] [description]
         */
        public function drop()
        {
            $dir = $this->store->getDir();

            File::rmdir($dir);

            return true;
        }

        /**
         * [rename description]
         *
         * @method rename
         *
         * @param  [type] $to [description]
         *
         * @return [type]     [description]
         */
        public function rename($to)
        {
            $dir = $this->store->getDir();

            if (fnmatch('*.*', $to)) {
                list($newDb, $newTable) = explode('.', $to, 2);
            } else {
                $newDb = SITE_NAME;
                $newTable = $to;
            }

            $new = new self($newDb, $newTable);

            $newDir = $new->store->getDir();

            File::mvdir($dir, $newDir);

            return $new;
        }

        /**
         * [fieldsRow description]
         *
         * @method fieldsRow
         *
         * @return [type]    [description]
         */
        public function fieldsRow()
        {
            $first  = with(new self($this->db, $this->table))->get()->first();

            if ($first) {
                $fields = array_keys($first);

                unset($fields['id']);
                unset($fields['created_at']);
                unset($fields['updated_at']);
                unset($fields['deleted_at']);

                return $fields;
            } else {
                return [];
            }
        }

        /**
         * [timestamp description]
         *
         * @method timestamp
         *
         * @param  [type]    $date [description]
         *
         * @return [type]          [description]
         */
        public function timestamp($date)
        {
            return ts($date);
        }

        /**
         * [__toString description]
         *
         * @method __toString
         *
         * @return string     [description]
         */
        public function __toString()
        {
            return "$this->db::$this->table";
        }

        /**
         * [toObjects description]
         *
         * @method toObjects
         *
         * @param  array     $rows [description]
         *
         * @return [type]          [description]
         */
        public function toObjects(array $rows)
        {
            $collection = [];

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $collection[] = $this->model($row);
                }
            }

            return $collection;
        }

        /**
         * [getTime description]
         *
         * @method getTime
         *
         * @return [type]  [description]
         */
        private function getTime()
        {
            $time = microtime();
            $time = explode(' ', $time, 2);

            return end($time) + current($time);
        }

        /**
         * [lock description]
         *
         * @method lock
         *
         * @param  string $action [description]
         *
         * @return [type]         [description]
         */
        public function lock($action = 'write')
        {
            if (true === $this->cacheEnabled) {
                $key = "lock.$action";

                $this->store->set($key, time());
            }

            return $this;
        }

        /**
         * [unlock description]
         *
         * @method unlock
         *
         * @param  string $action [description]
         *
         * @return [type]         [description]
         */
        public function unlock($action = 'write')
        {
            if (true === $this->cacheEnabled) {
                $key = "lock.$action";

                $this->store->del($key);
            }

            return $this;
        }

        /**
         * [freeze description]
         *
         * @method freeze
         *
         * @return [type] [description]
         */
        public function freeze()
        {
            return $this->lock('read')->lock('write');
        }

        /**
         * [unfreeze description]
         *
         * @method unfreeze
         *
         * @return [type]   [description]
         */
        public function unfreeze()
        {
            return $this->unlock('read')->unlock('write');
        }

        /**
         * [__call description]
         *
         * @method __call
         *
         * @param  [type] $fn   [description]
         * @param  [type] $args [description]
         *
         * @return [type]       [description]
         */
        public function __call($fn, $args)
        {
            $method = substr($fn, 0, strlen('findLastBy'));
            $object = Inflector::uncamelize(lcfirst(substr($fn, strlen('findLastBy'))));

            if (strlen($fn) > strlen('findLastBy')) {
                if ('findLastBy' == $method) {
                    $obj = count($args) == 2 ? $args[1] : true;

                    if (!is_bool($obj)) {
                        $obj = true;
                    }

                    return $this->where([$object, '=', current($args)])->cursor()->last($obj);
                }
            }

            $method = substr($fn, 0, strlen('findFirstBy'));
            $object = Inflector::uncamelize(lcfirst(substr($fn, strlen('findFirstBy'))));

            if (strlen($fn) > strlen('findFirstBy')) {
                if ('findFirstBy' == $method) {
                    $obj = count($args) == 2 ? $args[1] : true;

                    if (!is_bool($obj)) {
                        $obj = true;
                    }

                    return $this->findFirstBy($object, current($args), $obj);
                }
            }

            $method = substr($fn, 0, strlen('findOneBy'));
            $object = Inflector::uncamelize(lcfirst(substr($fn, strlen('findOneBy'))));

            if (strlen($fn) > strlen('findOneBy')) {
                if ('findOneBy' == $method) {
                    $obj = count($args) == 2 ? $args[1] : false;

                    if (!is_bool($obj)) {
                        $obj = false;
                    }

                    return $this->findOneBy($object, current($args), $obj);
                }
            }

            $method = substr($fn, 0, strlen('orderBy'));
            $object = Inflector::uncamelize(lcfirst(substr($fn, strlen('orderBy'))));

            if (strlen($fn) > strlen('orderBy')) {
                if ('orderBy' == $method) {
                    $fields = $this->fieldsRow();

                    if (!in_array($object, $fields) && 'id' != $object) {
                        $object = in_array($object . '_id', $fields) ? $object . '_id' : $object;
                    }

                    $direction = !empty($args) ? current($args) : 'ASC';

                    return $this->order($object, $direction);
                } elseif ('groupBy' == $method) {
                    $fields = $this->fieldsRow();

                    if (!in_array($object, $fields)) {
                        $object = in_array($object . '_id', $fields) ? $object . '_id' : $object;
                    }

                    return $this->get()->groupBy($object);
                }
            }

            $method = substr($fn, 0, strlen('where'));
            $object = Inflector::uncamelize(lcfirst(substr($fn, strlen('where'))));

            if (strlen($fn) > strlen('where')) {
                if ('where' == $method) {
                    return $this->where([$object, '=', current($args)]);
                }
            }

            $method = substr($fn, 0, strlen('sortBy'));
            $object = Inflector::uncamelize(lcfirst(substr($fn, strlen('sortBy'))));

            if (strlen($fn) > strlen('sortBy')) {
                if ('sortBy' == $method) {
                    $fields = $this->fieldsRow();

                    if (!in_array($object, $fields) && 'id' != $object) {
                        $object = in_array($object . '_id', $fields) ? $object . '_id' : $object;
                    }

                    $direction = !empty($args) ? current($args) : 'ASC';

                    return $this->order($object, $direction);
                } elseif ('findBy' == $method) {
                    $obj = count($args) == 2 ? $args[1] : false;

                    if (!is_bool($obj)) {
                        $obj = false;
                    }

                    return $this->findBy($object, current($args), false, $obj);
                }
            }

            $model = $this->model();
            $scope = lcfirst(Inflector::camelize('scope_' . Inflector::uncamelize($fn)));

            if (method_exists($model, $scope)) {
                $db = clone $this;
                $db->reset();

                return call_user_func_array([$model, $scope], [$db]);
            } else {
                $scopes = $this->store->get('scopes', []);
                $scope = Inflector::uncamelize($fn);

                $closure = isAke($scopes, $scope, false);

                if ($closure) {
                    eval('$closure = ' . $closure . ';');

                    if (is_callable($closure)) {
                        $db = clone $this;
                        $db->reset();

                        return call_user_func_array($closure, [$db]);
                    }
                }
            }

            if (method_exists($model, $fn)) {
                return call_user_func_array([$model, $fn], $args);
            }

            throw new Exception("Method '$fn' is unknown.");
        }

        /**
         * [__callStatic description]
         *
         * @method __callStatic
         *
         * @param  [type]       $fn   [description]
         * @param  [type]       $args [description]
         *
         * @return [type]             [description]
         */
        public static function __callStatic($fn, $args)
        {
            $method     = Inflector::uncamelize($fn);
            $tab        = explode('_', $method);
            $table      = array_shift($tab);
            $function   = implode('_', $tab);
            $function   = lcfirst(Inflector::camelize($function));
            $instance   = self::instance(SITE_NAME, $table);

            return call_user_func_array([$instance, $function], $args);
        }

        /**
         * [post description]
         *
         * @method post
         *
         * @param  boolean $save [description]
         *
         * @return [type]        [description]
         */
        public function post($save = false)
        {
            return !$save ? $this->create($_POST) : $this->create($_POST)->save();
        }

        /**
         * [in description]
         *
         * @method in
         *
         * @param  [type] $ids     [description]
         * @param  [type] $field   [description]
         * @param  string $op      [description]
         * @param  array  $results [description]
         *
         * @return [type]          [description]
         */
        public function in($ids, $field = null, $op = 'AND', $results = [])
        {
            /* polymorphism */
            $ids = !is_array($ids)
            ? strstr($ids, ',')
                ? explode(',', str_replace(' ', '', $ids))
                : [$ids]
            : $ids;

            $field = is_null($field) ? 'id' : $field;

            return $this->where([$field, 'IN', implode(',', $ids)], $op, $results);
        }

        /**
         * [notIn description]
         *
         * @method notIn
         *
         * @param  [type] $ids     [description]
         * @param  [type] $field   [description]
         * @param  string $op      [description]
         * @param  array  $results [description]
         *
         * @return [type]          [description]
         */
        public function notIn($ids, $field = null, $op = 'AND', $results = [])
        {
            /* polymorphism */
            $ids = !is_array($ids)
            ? strstr($ids, ',')
                ? explode(',', str_replace(' ', '', $ids))
                : [$ids]
            : $ids;

            $field = is_null($field) ? 'id' : $field;

            return $this->where([$field, 'NOT IN', implode(',', $ids)], $op, $results);
        }

        /**
         * [like description]
         *
         * @method like
         *
         * @param  [type] $field   [description]
         * @param  [type] $str     [description]
         * @param  string $op      [description]
         * @param  array  $results [description]
         *
         * @return [type]          [description]
         */
        public function like($field, $str, $op = 'AND', $results = [])
        {
            return $this->where([$field, 'LIKE', $str], $op, $results);
        }

        /**
         * [likeStart description]
         *
         * @method likeStart
         *
         * @param  [type]    $field   [description]
         * @param  [type]    $str     [description]
         * @param  string    $op      [description]
         * @param  array     $results [description]
         *
         * @return [type]             [description]
         */
        public function likeStart($field, $str, $op = 'AND', $results = [])
        {
            return $this->where([$field, 'LIKE', $str . '%'], $op, $results);
        }

        /**
         * [startsWith description]
         *
         * @method startsWith
         *
         * @param  [type]     $field   [description]
         * @param  [type]     $str     [description]
         * @param  string     $op      [description]
         * @param  array      $results [description]
         *
         * @return [type]              [description]
         */
        public function startsWith($field, $str, $op = 'AND', $results = [])
        {
            return $this->where([$field, 'LIKE', $str . '%'], $op, $results);
        }

        /**
         * [endsWith description]
         *
         * @method endsWith
         *
         * @param  [type]   $field   [description]
         * @param  [type]   $str     [description]
         * @param  string   $op      [description]
         * @param  array    $results [description]
         *
         * @return [type]            [description]
         */
        public function endsWith($field, $str, $op = 'AND', $results = [])
        {
            return $this->where([$field, 'LIKE', '%' . $str], $op, $results);
        }

        /**
         * [likeEnd description]
         *
         * @method likeEnd
         *
         * @param  [type]  $field   [description]
         * @param  [type]  $str     [description]
         * @param  string  $op      [description]
         * @param  array   $results [description]
         *
         * @return [type]           [description]
         */
        public function likeEnd($field, $str, $op = 'AND', $results = [])
        {
            return $this->where([$field, 'LIKE', '%' . $str], $op, $results);
        }

        /**
         * [notLike description]
         *
         * @method notLike
         *
         * @param  [type]  $field   [description]
         * @param  [type]  $str     [description]
         * @param  string  $op      [description]
         * @param  array   $results [description]
         *
         * @return [type]           [description]
         */
        public function notLike($field, $str, $op = 'AND', $results = [])
        {
            return $this->where([$field, 'NOT LIKE', $str], $op, $results);
        }

        /**
         * [pk description]
         *
         * @method pk
         *
         * @return [type] [description]
         */
        public function pk()
        {
            return 'id';
        }

        /**
         * [findOneBy description]
         *
         * @method findOneBy
         *
         * @param  [type]    $field  [description]
         * @param  [type]    $value  [description]
         * @param  boolean   $object [description]
         *
         * @return [type]            [description]
         */
        public function findOneBy($field, $value, $object = false)
        {
            return $this->findBy($field, $value, true, $object);
        }

        /**
         * [findOrFail description]
         *
         * @method findOrFail
         *
         * @param  [type]     $id     [description]
         * @param  boolean    $object [description]
         *
         * @return [type]             [description]
         */
        public function findOrFail($id, $object = true)
        {
            if (!is_null($item = $this->find($id, $object))) {
                return $item;
            }

            throw new Exception("Row '$id' in '$this->table' is unknown.");
        }

        /**
         * [findBy description]
         *
         * @method findBy
         *
         * @param  [type]  $field  [description]
         * @param  [type]  $value  [description]
         * @param  boolean $one    [description]
         * @param  boolean $object [description]
         *
         * @return [type]          [description]
         */
        public function findBy($field, $value, $one = false, $object = false)
        {
            $cursor = $this->where([$field, '=', $value])->get();

            if ($cursor->count() > 0 && true === $one) {
                return $object ? $this->model(current($res)) : current($res);
            }

            if ($cursor->count() < 1 && true === $one && true === $object) {
                return null;
            }

            return $res;
        }

        /**
         * [first description]
         *
         * @method first
         *
         * @param  boolean $object [description]
         *
         * @return [type]          [description]
         */
        public function first($object = false)
        {
            return $this->get()->first($object);
        }

        /**
         * [firstOrFail description]
         *
         * @method firstOrFail
         *
         * @param  boolean     $object [description]
         *
         * @return [type]              [description]
         */
        public function firstOrFail($object = true)
        {
            if (!is_null($item = $this->first($object))) {
                return $item;
            }

            throw new Exception("Row '$id' in '$this->table' is unknown.");
        }

        /**
         * [last description]
         *
         * @method last
         *
         * @param  boolean $object [description]
         *
         * @return [type]          [description]
         */
        public function last($object = false)
        {
            return $this->get()->last($object);
        }

        /**
         * [count description]
         *
         * @method count
         *
         * @return [type] [description]
         */
        public function count()
        {
            return $this->get()->count();
        }

        /**
         * [only description]
         *
         * @method only
         *
         * @param  [type] $field   [description]
         * @param  [type] $default [description]
         *
         * @return [type]          [description]
         */
        public function only($field, $default = null)
        {
            $row = $this->first(true);

            return $row ? $row->$field : $default;
        }

        /**
         * [replace description]
         *
         * @method replace
         *
         * @param  array   $compare [description]
         * @param  array   $update  [description]
         *
         * @return [type]           [description]
         */
        public function replace($compare = [], $update = [])
        {
            $instance = $this->firstOrCreate($compare);

            return $instance->hydrate($update)->save();
        }

        /**
         * [copy description]
         *
         * @method copy
         *
         * @param  [type] $where   [description]
         * @param  array  $newArgs [description]
         *
         * @return [type]          [description]
         */
        public function copy($where, array $newArgs)
        {
            $db     = self::instance($this->db, $this->table);
            $cursor = $db->query($where)->get();

            if ($cursor->count() > 0) {
                foreach ($cursor as $row) {
                    unset($row['id']);
                    unset($row['created_at']);
                    unset($row['updated_at']);

                    $db->create(
                        array_merge(
                            $row,
                            $newArgs
                        )
                    )->save();
                }
            }

            return $this;
        }

        /**
         * [firstOrNew description]
         *
         * @method firstOrNew
         *
         * @param  array      $tab [description]
         *
         * @return [type]          [description]
         */
        public function firstOrNew($tab = [])
        {
            return $this->firstOrCreate($tab, false);
        }

        /**
         * [firstOrCreate description]
         *
         * @method firstOrCreate
         *
         * @param  array         $tab  [description]
         * @param  boolean       $save [description]
         *
         * @return [type]              [description]
         */
        public function firstOrCreate($tab = [], $save = true)
        {
            if (!empty($tab)) {
                foreach ($tab as $key => $value) {
                    $this->where([$key, '=', $value]);
                }

                $first = $this->cursor()->first(true);

                if ($first) {
                    return $first;
                }
            }

            $item = $this->create($tab);

            return false === $save ? $item : $item->save();
        }

        /**
         * [between description]
         *
         * @method between
         *
         * @param  [type]  $field [description]
         * @param  [type]  $min   [description]
         * @param  [type]  $max   [description]
         *
         * @return [type]         [description]
         */
        public function between($field, $min, $max)
        {
            return $this->where([$field, '>=', $min])->where([$field, '<=', $max]);

            return $this;
        }

        /**
         * [query description]
         *
         * @method query
         *
         * @param  [type] $sql [description]
         *
         * @return [type]      [description]
         */
        public function query($sql)
        {
            if (strstr($sql, ' && ')) {
                $segs = explode(' && ', $sql);

                foreach ($segs as $seg) {
                    $this->where($seg);
                    $sql = str_replace($seg . ' && ', '', $sql);
                }
            }

            if (strstr($sql, ' || ')) {
                $segs = explode(' || ', $sql);

                foreach ($segs as $seg) {
                    $this->where($seg, 'OR');
                    $sql = str_replace($seg . ' || ', '', $sql);
                }
            }

            if (!empty($sql)) {
                $this->where($sql);
            }

            return $this;
        }

        /**
         * [destroy description]
         *
         * @method destroy
         *
         * @param  [type]  $what [description]
         *
         * @return [type]        [description]
         */
        public function destroy($what)
        {
            /* polymorphism */
            if (func_num_args() == 1) {
                if (is_string($what)) {
                    if (fnmatch('*,*', $what)) {
                        $what = str_replace(' ', '', $what);
                        $what = explode(',', $what);
                    } else {
                        $what = [$what];
                    }
                }
            } else {
                $what = func_get_args();
            }

            if (is_array($what)) {
                foreach ($what as $id) {
                    $obj = $this->find((int) $id);

                    if ($obj) {
                        $obj->delete();
                    }
                }
            }

            return $this;
        }

        /**
         * [join description]
         *
         * @method join
         *
         * @param  [type] $table [description]
         * @param  [type] $field [description]
         * @param  [type] $db    [description]
         *
         * @return [type]        [description]
         */
        public function join($table, $field = null, $db = null)
        {
            $db     = is_null($db)      ? $this->db         : $db;
            $field  = is_null($field)   ? $table . '_id'    : $field;

            $this->joins[] = [$table, $field, $db];

            return $this;
        }

        /**
         * [latest description]
         *
         * @method latest
         *
         * @param  [type] $field [description]
         *
         * @return [type]        [description]
         */
        public function latest($field = null)
        {
            $field = is_null($field) ? 'updated_at' : $field;

            return $this->order($field, 'DESC');
        }

        /**
         * [oldest description]
         *
         * @method oldest
         *
         * @param  [type] $field [description]
         *
         * @return [type]        [description]
         */
        public function oldest($field = null)
        {
            $field = is_null($field) ? 'updated_at' : $field;

            return $this->order($field, 'ASC');
        }

        /**
         * [history description]
         *
         * @method history
         *
         * @return [type]  [description]
         */
        public function history()
        {
            return $this->order('id', 'ASC');
        }

        /**
         * [paginate description]
         *
         * @method paginate
         *
         * @param  integer  $byPage [description]
         * @param  integer  $page   [description]
         * @param  string   $var    [description]
         *
         * @return [type]           [description]
         */
        public function paginate($byPage = 25, $page = 1, $var = 'page')
        {
            $offset     = ($byPage * $page) - $byPage;
            $total      = $this->cursor()->count();
            $last       = ceil($total / $byPage);
            $paginator  = lib('paginator', [[], $page, $total, $byPage, $last, $var]);

            $start  = ($byPage * $page) - ($byPage - 1);
            $end    = $byPage * $page;

            $end    = $end > $total ? $total : $end;

            $data       = $this->limit($byPage, $offset)->cursor();
            $pagination = $paginator->links();

            return [
                'data'          => $data,
                'pagination'    => $pagination,
                'page'          => $page,
                'total'         => $total,
                'offset'        => $offset,
                'last'          => $last,
                'start'         => $start,
                'end'           => $end
            ];
        }

        /**
         * [multiQuery description]
         *
         * @method multiQuery
         *
         * @param  array      $queries [description]
         *
         * @return [type]              [description]
         */
        public function multiQuery(array $queries)
        {
            foreach ($queries as $query) {
                $count = count($query);

                switch ($count) {
                    case 4:
                        list($field, $op, $value, $operand) = $query;
                        break;
                    case 3:
                        list($field, $op, $value) = $query;
                        $operand = 'AND';
                        break;
                }

                $this->where([$field, $op, $value], $operand);
            }

            return $this;
        }

        /**
         * [through description]
         *
         * @method through
         *
         * @return [type]  [description]
         */
        public function through()
        {
            $args = func_get_args();

            $t2 = array_pop($args);
            $t1 = array_pop($args);

            $where = $args;

            if (!fnmatch('*.*', $t1)) {
                $database = $this->db;
            } else {
                list($database, $t1) = explode('.', $t1, 2);
            }

            $where = empty($where) ? [['id', '>', 0]] : $where;

            $db1 = self::instance($database, $t1);

            $fk = $this->table . '_id';

            $rows = $this->multiQuery($where)->cursor();

            $ids = [];

            foreach ($rows as $row) {
                $ids[] = $row['id'];
            }

            $sub = $db1->where([$fk, 'IN', implode(',', $ids)])->cursor();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $t1 . '_id';

            if (!fnmatch('*.*', $t2)) {
                $database = $this->db;
            } else {
                list($database, $t2) = explode('.', $t2, 2);
            }

            return self::instance($database, $t2)->where([$fk2, 'IN', implode(',', $ids)])->cursor();
        }

        /**
         * [updateOrCreate description]
         *
         * @method updateOrCreate
         *
         * @param  array          $attributes [description]
         * @param  array          $values     [description]
         *
         * @return [type]                     [description]
         */
        public function updateOrCreate(array $attributes, array $values = [])
        {
            return $this->firstOrCreate($attributes)->fill($values)->save();
        }

        /**
         * [findOrNew description]
         *
         * @method findOrNew
         *
         * @param  [type]    $id [description]
         *
         * @return [type]        [description]
         */
        public function findOrNew($id)
        {
            if (!is_null($model = $this->find((int) $id))) {
                return $model;
            }

            return $this->model([]);
        }

        public function transaction(callable $callback)
        {
            $this->beginTransaction();

            // We'll simply execute the given callback within a try / catch block
            // and if we catch any exception we can rollback the transaction
            // so that none of the changes are persisted to the database.
            try {
                $result = $callback($this);

                $this->commit();
            }

            // If we catch an exception, we will roll back so nothing gets messed
            // up in the database. Then we'll re-throw the exception so it can
            // be handled how the developer sees fit for their applications.
            catch (\Exception $e) {
                $this->rollBack();

                throw $e;
            }

            return $result;
        }

        private function beginTransaction()
        {
            ++$this->store->transactions;

            if ($this->store->transactions == 1) {
                $this->store = $this->store->beginTransaction();
            }
        }

        /**
         * Commit the active database transaction.
         *
         * @return void
         */
        public function commit()
        {
            if ($this->store->transactions == 1) {
                $this->store->commit();
            }

            --$this->transactions;
        }

        /**
         * Rollback the active database transaction.
         *
         * @return void
         */
        public function rollBack()
        {
            if ($this->store->transactions == 1) {
                $this->store->transactions = 0;

                $this->store->rollBack();
            } else {
                --$this->transactions;
            }
        }

        /**
         * Get the number of active transactions.
         *
         * @return int
         */
        public function transactionLevel()
        {
            return $this->store->transactions;
        }

        /**
         * [lt description]
         *
         * @method lt
         *
         * @param  [type] $field [description]
         * @param  [type] $value [description]
         *
         * @return [type]        [description]
         */
        public function lt($field, $value)
        {
            return $this->where([$field, '<', $value]);
        }

        /**
         * [gt description]
         *
         * @method gt
         *
         * @param  [type] $field [description]
         * @param  [type] $value [description]
         *
         * @return [type]        [description]
         */
        public function gt($field, $value)
        {
            return $this->where([$field, '>', $value]);
        }

        /**
         * [lte description]
         *
         * @method lte
         *
         * @param  [type] $field [description]
         * @param  [type] $value [description]
         *
         * @return [type]        [description]
         */
        public function lte($field, $value)
        {
            return $this->where([$field, '<=', $value]);
        }

        /**
         * [gte description]
         *
         * @method gte
         *
         * @param  [type] $field [description]
         * @param  [type] $value [description]
         *
         * @return [type]        [description]
         */
        public function gte($field, $value)
        {
            return $this->where([$field, '>=', $value]);
        }

        /**
         * [before description]
         *
         * @method before
         *
         * @param  [type]  $date  [description]
         * @param  boolean $exact [description]
         *
         * @return [type]         [description]
         */
        public function before($date, $exact = true)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $exact ? $this->lt('created_at', $date) : $this->lte('created_at', $date);
        }

        /**
         * [after description]
         *
         * @method after
         *
         * @param  [type]  $date  [description]
         * @param  boolean $exact [description]
         *
         * @return [type]         [description]
         */
        public function after($date, $exact = true)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $exact ? $this->gt('created_at', $date) : $this->gte('created_at', $date);
        }

        /**
         * [when description]
         *
         * @method when
         *
         * @param  [type] $field [description]
         * @param  [type] $op    [description]
         * @param  [type] $date  [description]
         *
         * @return [type]        [description]
         */
        public function when($field, $op, $date)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $this->where([$field, $op, $date]);
        }

        /**
         * [deleted description]
         *
         * @method deleted
         *
         * @return [type]  [description]
         */
        public function deleted()
        {
            return $this->when('deleted_at', '<=', lib('time')->now());
        }

        /**
         * [closure description]
         *
         * @method closure
         *
         * @param  callable $closure [description]
         *
         * @return [type]            [description]
         */
        public function closure(callable $closure)
        {
            $rows = new Rows($this);

            $cursor = $this->cursor();

            foreach ($cursor as $row) {
                $check = call_user_func_array($closure, [$this, $row]);

                if ($check) {
                    $rows->add($row);
                }
            }

            return $rows;
        }

        /**
         * [contains description]
         *
         * @method contains
         *
         * @param  [type]   $field   [description]
         * @param  [type]   $pattern [description]
         *
         * @return [type]            [description]
         */
        public function contains($field, $pattern)
        {
            return $this->where([$field, 'LIKE', "%$pattern%"]);
        }

        /**
         * [view description]
         *
         * @method view
         *
         * @param  [type] $name [description]
         *
         * @return [type]       [description]
         */
        public function view($name)
        {
            return (new View($this, $name))->get();
        }

        /**
         * [makeView description]
         *
         * @method makeView
         *
         * @param  [type]   $name [description]
         *
         * @return [type]         [description]
         */
        public function makeView($name)
        {
            return (new View($this, $name))->make();
        }

        /**
         * [firstByAttributes description]
         *
         * @method firstByAttributes
         *
         * @param  [type]            $attributes [description]
         * @param  boolean           $object     [description]
         *
         * @return [type]                        [description]
         */
        public function firstByAttributes($attributes, $object = false)
        {
            foreach ($attributes as $k => $v) {
                $this->where([$k, '=', $v]);
            }

            return $this->first($object);
        }

        /**
         * [min description]
         *
         * @method min
         *
         * @param  [type] $field [description]
         *
         * @return [type]        [description]
         */
        public function min($field)
        {
            $row = $this->order($field)->cursor()->first();

            if ($row) {
                return isAke($row, $field);
            }

            return 0;
        }

        /**
         * [max description]
         *
         * @method max
         *
         * @param  [type] $field [description]
         *
         * @return [type]        [description]
         */
        public function max($field)
        {
            $row = $this->order($field, 'DESC')->cursor()->first();

            if ($row) {
                return isAke($row, $field);
            }

            return 0;
        }

        /**
         * [sum description]
         *
         * @method sum
         *
         * @param  [type] $field [description]
         *
         * @return [type]        [description]
         */
        public function sum($field)
        {
            $rows = $this->select($field)->get();

            $sum = 0;

            foreach ($rows as $row) {
                $sum += isAke($row, $field, 0);
            }

            return $sum;
        }

        /**
         * [avg description]
         *
         * @method avg
         *
         * @param  [type] $field [description]
         *
         * @return [type]        [description]
         */
        public function avg($field)
        {
            $rows = $this->select($field)->get();

            $sum = 0;

            foreach ($rows as $row) {
                $sum += isAke($row, $field, 0);
            }

            return floatval($sum / $rows->count());
        }

        /**
         * [random description]
         *
         * @method random
         *
         * @param  integer $nb [description]
         *
         * @return [type]      [description]
         */
        public function random($nb = 1)
        {
            return $this->rand($nb);
        }

        /**
         * [rand description]
         *
         * @method rand
         *
         * @param  integer $nb [description]
         *
         * @return [type]      [description]
         */
        public function rand($nb = 1)
        {
            $rows = $this->get()->rand();

            if ($nb == 1) {
                return $rows->first();
            } else {
                $collection = [];

                foreach ($rows as $row) {
                    $collection[] = $row;

                    $nb--;

                    if (0 == $nb) {
                        break;
                    }
                }

                return $collection;
            }
        }

        /**
         * [newQuery description]
         *
         * @method newQuery
         *
         * @return [type]   [description]
         */
        public function newQuery()
        {
            return $this->reset();
        }

        /**
         * [toSql description]
         *
         * @method toSql
         *
         * @return [type] [description]
         */
        public function toSql()
        {
            $sql = 'SELECT ';
            $fks = [];

            if (empty($this->selects)) {
                $sql .= $this->db . '.' . $this->table . '.* ';
            } else {
                foreach ($this->selects as $f) {
                    if (!fnmatch('*.*', $f)) {
                        $sql .= $this->db . '.' . $this->table . ".$f, ";
                    } else {
                        $sql .= "$f, ";

                        if (fnmatch('*.*.*', $f)) {
                            list($fkDb, $fkTable, $fkField) = explode('.', $f, 3);
                        } else {
                            $fkDb = $this->db;
                            list($fkTable, $fkField) = explode('.', $f, 2);
                        }

                        $fk = "$fkDb.$fkTable";

                        if (!in_array($fk, $fks)) {
                            $fks[] = $fk;
                        }
                    }
                }

                $sql = substr($sql, 0, -2) . ' ';
            }

            if (!empty($this->wheres)) {
                foreach ($this->wheres as $wh) {
                    list($c, $o) = $wh;
                    list($f, $b, $v) = $c;

                    if (fnmatch('*.*', $f)) {
                        if (fnmatch('*.*.*', $f)) {
                            list($fkDb, $fkTable, $fkField) = explode('.', $f, 3);
                        } else {
                            $fkDb = $this->db;
                            list($fkTable, $fkField) = explode('.', $f, 2);
                        }

                        $fk = "$fkDb.$fkTable";

                        if (!in_array($fk, $fks)) {
                            $fks[] = $fk;
                        }
                    }
                }
            }

            $sql .= 'FROM ' . $this->db . '.' . $this->table;

            if (!empty($fks)) {
                foreach ($fks as $fk) {
                    $sql .= ", $fk";
                }
            }

            if (!empty($fks)) {
                foreach ($fks as $fk) {
                    list($fkDb, $fkTable) = explode('.', $fk, 2);
                    $sql .= " LEFT JOIN $fkDb.$fkTable ON " . $this->db . '.' . $this->table . '.' . $fkTable . '_id = ' . $fk . '.id';
                }
            }

            if (!empty($this->wheres)) {
                $first = true;

                foreach ($this->wheres as $wh) {
                    list($c, $o) = $wh;
                    list($f, $b, $v) = $c;

                    if ($first) {
                        if (!fnmatch('*.*', $f)) {
                            $f = $this->db . '.' . $this->table . '.' . $f;
                        }

                        $sql .= " WHERE $f $b '$v'";
                        $first = false;
                    } else {
                        $sql .= " $o $f $b '$v'";
                    }
                }
            }

            if (!empty($this->orders)) {
                $first = true;

                foreach ($this->orders as $f => $d) {
                    if ($first) {
                        $sql .= " ORDER BY $f $d";
                        $first = false;
                    } else {
                        $sql .= ", $f $d";
                    }
                }
            }

            return $sql;
        }

        /**
         * [fromSql description]
         *
         * @method fromSql
         *
         * @param  [type]  $sql [description]
         *
         * @return [type]       [description]
         */
        public static function fromSql($sql)
        {
            $select = Utils::cut('SELECT ', ' FROM', $sql);
            $from   = Utils::cut(' FROM ', ' ', $sql);
            $wheres = '';

            if (fnmatch('* WHERE *', $sql)) {
                $wheres = Arrays::last(explode(' WHERE ', $sql));
            }

            $joins  = [];

            if (fnmatch('* JOIN *', $sql)) {
                $segs = explode(' JOIN ', $sql);
                array_shift($segs);

                foreach ($segs as $seg) {
                    $fk = Arrays::first(explode(' ', $seg));

                    if (!in_array($joins, $joins)) {
                        $joins[] = $fk;
                    }
                }
            }

            if (fnmatch('*.*', $from)) {
                list($db, $table) = explode('.', Inflector::lower($from), 2);
            } else {
                $db = SITE_NAME;
                $table = Inflector::lower($from);
            }

            $instance = self::instance($db, $table);

            if (!empty($select) && $select != '*') {
                $selects = explode(',', str_replace(' ', '', Inflector::lower($select)));

                foreach ($selects as $field) {
                    $instance->select($field);
                }
            }

            if (fnmatch('* ORDER BY *', $wheres)) {
                list($wheres, $orders) = explode(' ORDER BY ', $wheres, 2);
            }

            $whs = [$wheres];
            $or = false;

            if (fnmatch('* && *', $wheres)) {
                $whs = explode(' && ', $wheres);
            }

            if (fnmatch('* || *', $wheres)) {
                $whs = explode(' || ', $wheres);
                $or = true;
            }

            foreach ($whs as $wh) {
                list($f, $o, $v) = explode(' ', $wh, 3);

                if ($v[0] == "'") {
                    $v = substr($v, 1);
                }

                if ($v[strlen($v) - 1] == "'") {
                    $v = substr($v, 0, -1);
                }

                if (is_numeric($v)) {
                    if ($v == intval($v)) {
                        $v = (int) $v;
                    }
                }

                if (!$or) {
                    $instance = $instance->where([$f, $o, $v]);
                } else {
                    $instance = $instance->where([$f, $o, $v], 'OR');
                }
            }

            if (isset($orders)) {
                if (fnmatch('*,*', $orders)) {
                    $orders = explode(',', str_replace(', ', ',', $orders));
                } else {
                    $orders = [$orders];
                }

                foreach ($orders as $order) {
                    if (fnmatch('* *', $order)) {
                        list($f, $d) = explode(' ', $order, 2);
                    } else {
                        $f = $order;
                        $d = 'ASC';
                    }

                    $instance = $instance->order($f, $d);
                }
            }

            return $instance;
        }

        /**
         * [custom description]
         *
         * @method custom
         *
         * @param  callable $closure [description]
         *
         * @return [type]            [description]
         */
        public function custom(callable $closure)
        {
            return new Cursor($this, $closure);
        }

        /**
         * [get description]
         *
         * @method get
         *
         * @param  [type]  $closure [description]
         * @param  boolean $object  [description]
         *
         * @return [type]           [description]
         */
        public function get($closure = null, $object = false)
        {
            return $object ? new Models($this, $closure) : new Cursor($this, $closure);
        }

        public function index($drop = false, $id = null)
        {
            $model = $this->model();

            $methods = get_class_methods($model);

            if (in_array('indices', $methods)) {
                $indices = $model->indices();

                if (!empty($indices)) {
                    $dbIndices = new self('indices', $this->table);

                    if ($drop && is_null($id)) {
                        $dbIndices->drop();
                    }

                    if (is_null($id)) {
                        $rows = $this->get();

                        foreach ($rows as $row) {
                            $indexRow = $dbIndices->firstOrCreate(['object_id' => $row['id']]);

                            foreach ($indices as $index) {
                                $indexRow->$index = $row[$index];
                            }

                            $indexRow->save();
                        }
                    } else {
                        $row = $this->findOrFail($id)->toArray();

                        $indexRow = $dbIndices->firstOrCreate(['object_id' => $row['id']]);

                        foreach ($indices as $index) {
                            $indexRow->$index = $row[$index];
                        }

                        $indexRow->save();
                    }
                }
            }

            return $this;
        }

        /**
         * [reindex description]
         *
         * @method reindex
         *
         * @param  [type]  $id [description]
         *
         * @return [type]      [description]
         */
        public function reindex($id = null)
        {
            return $this->index(true, $id);
        }

        /**
         * [search description]
         *
         * @method search
         *
         * @param  [type]  $val   [description]
         * @param  boolean $exact [description]
         *
         * @return [type]         [description]
         */
        public function search($val, $exact = false)
        {
            $dbIndices = new self('indices', $this->table);

            $rows = $dbIndices->get();

            $ids = [];

            foreach ($rows as $row) {
                foreach ($row as $k => $v) {
                    if (!$exact) {
                        if (fnmatch("*$val*", $v)) {
                            $ids[] = $row['object_id'];
                            break;
                        }
                    } else {
                        if ($val == $v) {
                            $ids[] = $row['object_id'];
                            break;
                        }
                    }
                }
            }

            return !empty($ids) ? $this->where(['id', 'IN', implode(',', $ids)]) : $this;
        }

        /**
         * [duplicate description]
         *
         * @method duplicate
         *
         * @param  [type]    $to [description]
         *
         * @return [type]        [description]
         */
        public function duplicate($to)
        {
            $dir = $this->store->getDir();

            if (fnmatch('*.*', $to)) {
                list($newDb, $newTable) = explode('.', $to, 2);
            } else {
                $newDb = $this->db;
                $newTable = $to;
            }

            $new = new self($newDb, $newTable);

            $newDir = $new->store->getDir();

            File::cpdir($dir, $newDir);

            return $new;
        }

        /**
         * [copyTable description]
         *
         * @method copyTable
         *
         * @param  [type]    $to [description]
         *
         * @return [type]        [description]
         */
        public function copyTable($to)
        {
            if (fnmatch('*.*', $to)) {
                list($newDb, $newTable) = explode('.', $to, 2);
            } else {
                $newDb = $this->db;
                $newTable = $to;
            }

            $new = new self($newDb, $newTable);

            $rows = $this->cursor();

            foreach ($rows as $row) {
                unset($row['id']);
                $new->firstOrCreate($row);
            }

            return $new;
        }

        /**
         * [day description]
         *
         * @method day
         *
         * @param  string $field [description]
         *
         * @return [type]        [description]
         */
        public function day($field = 'created_at')
        {
            return $this->custom(function ($row) use ($field) {
                $row['day_' . $field] = date('j', isAke($row, $field, time()));

                return $row;
            });
        }

        /**
         * [month description]
         *
         * @method month
         *
         * @param  string $field [description]
         *
         * @return [type]        [description]
         */
        public function month($field = 'created_at')
        {
            return $this->custom(function ($row) use ($field) {
                $row['month_' . $field] = date('n', isAke($row, $field, time()));

                return $row;
            });
        }

        /**
         * [year description]
         *
         * @method year
         *
         * @param  string $field [description]
         *
         * @return [type]        [description]
         */
        public function year($field = 'created_at')
        {
            return $this->custom(function ($row) use ($field) {
                $row['year_' . $field] = date('Y', isAke($row, $field, time()));

                return $row;
            });
        }

        /**
         * [second description]
         *
         * @method second
         *
         * @param  string $field [description]
         *
         * @return [type]        [description]
         */
        public function second($field = 'created_at')
        {
            return $this->custom(function ($row) use ($field) {
                $row['second_' . $field] = date('s', isAke($row, $field, time()));

                return $row;
            });
        }

        /**
         * [minute description]
         *
         * @method minute
         *
         * @param  string $field [description]
         *
         * @return [type]        [description]
         */
        public function minute($field = 'created_at')
        {
            return $this->custom(function ($row) use ($field) {
                $row['minute_' . $field] = date('i', isAke($row, $field, time()));

                return $row;
            });
        }

        /**
         * [hour description]
         *
         * @method hour
         *
         * @param  string $field [description]
         *
         * @return [type]        [description]
         */
        public function hour($field = 'created_at')
        {
            return $this->custom(function ($row) use ($field) {
                $row['hour_' . $field] = date('H', isAke($row, $field, time()));

                return $row;
            });
        }

        /**
         * [now description]
         *
         * @method now
         *
         * @return [type] [description]
         */
        public function now()
        {
            return date("Y-m-d H:i:s");
        }

        /**
         * [curdate description]
         *
         * @method curdate
         *
         * @return [type]  [description]
         */
        public function curdate()
        {
            return date("Y-m-d");
        }

        /**
         * [char_length description]
         *
         * @method char_length
         *
         * @param  [type]      $field [description]
         *
         * @return [type]             [description]
         */
        public function char_length($field)
        {
            return $this->custom(function ($row) use ($field) {
                $row['length_' . $field] = mb_strlen(isAke($row, $field, null));

                return $row;
            });
        }

        /**
         * [md5 description]
         *
         * @method md5
         *
         * @param  [type] $field [description]
         *
         * @return [type]        [description]
         */
        public function md5($field)
        {
            return $this->custom(function ($row) use ($field) {
                $row['md5_' . $field] = md5(isAke($row, $field, null));

                return $row;
            });
        }

        /**
         * [sha1 description]
         *
         * @method sha1
         *
         * @param  [type] $field [description]
         *
         * @return [type]        [description]
         */
        public function sha1($field)
        {
            return $this->custom(function ($row) use ($field) {
                $row['sha1_' . $field] = sha1(isAke($row, $field, null));

                return $row;
            });
        }

        /**
         * [randomize description]
         *
         * @method randomize
         *
         * @return [type]    [description]
         */
        public function randomize()
        {
            return mt_rand(0, 1);
        }

        /**
         * [substring description]
         *
         * @method substring
         *
         * @param  [type]    $field [description]
         * @param  [type]    $pos   [description]
         * @param  [type]    $len   [description]
         *
         * @return [type]           [description]
         */
        public function substring($field, $pos, $len = null)
        {
            return $this->custom(function ($row) use ($field, $pos, $len) {
                $row['substring_' . $field] = substr(isAke($row, $field, null), $pos, $len);

                return $row;
            });
        }

        /**
         * [dateformat description]
         *
         * @method dateformat
         *
         * @param  [type]     $date   [description]
         * @param  [type]     $format [description]
         *
         * @return [type]             [description]
         */
        public function dateformat($date, $format)
        {
            $mysql_php_dateformats = ['%a' => 'D', '%b' => 'M', '%c' => 'n', '%D' => 'jS', '%d' => 'd', '%e' => 'j', '%H' => 'H', '%h' => 'h', '%I' => 'h', '%i' => 'i', '%j' => 'z', '%k' => 'G', '%l' => 'g', '%M' => 'F', '%m' => 'm', '%p' => 'A', '%r' => 'h:i:s A', '%S' => 's', '%s' => 's', '%T' => 'H:i:s', '%U' => 'W', '%u' => 'W', '%V' => 'W', '%v' => 'W', '%W' => 'l', '%w' => 'w', '%X' => 'Y', '%x' => 'o', '%Y' => 'Y', '%y' => 'y'];

            $t      = strtotime($date);
            $format = strtr($format, $mysql_php_dateformats);
            $output = date($format, $t);

            return $output;
        }

        /**
         * [date_add description]
         *
         * @method date_add
         *
         * @param  [type]   $date     [description]
         * @param  [type]   $interval [description]
         *
         * @return [type]             [description]
         */
        public function date_add($date, $interval)
        {
            $interval = lib('time')->deriveInterval($interval);

            switch (strtolower($date)) {
                case "curdate()":
                    $objDate   = new \Datetime($this->curdate());
                    $objDate->add(new \DateInterval($interval));
                    $returnval = $objDate->format("Y-m-d");
                    break;
                case "now()":
                    $objDate   = new \Datetime($this->now());
                    $objDate->add(new \DateInterval($interval));
                    $returnval = $objDate->format("Y-m-d H:i:s");
                    break;
                default:
                    $objDate   = new \Datetime($date);
                    $objDate->add(new \DateInterval($interval));
                    $returnval = $objDate->format("Y-m-d H:i:s");
            }

            return $returnval;
        }

        /**
         * [date_sub description]
         *
         * @method date_sub
         *
         * @param  [type]   $date     [description]
         * @param  [type]   $interval [description]
         *
         * @return [type]             [description]
         */
        public function date_sub($date, $interval)
        {
            $interval = lib('time')->deriveInterval($interval);

            switch (strtolower($date)) {
                case "curdate()":
                    $objDate   = new \Datetime(date("Y-m-d"));
                    $objDate->sub(new \DateInterval($interval));
                    $returnval = $objDate->format("Y-m-d");
                    break;
                case "now()":
                    $objDate   = new \Datetime(date("Y-m-d H:i:s"));
                    $objDate->sub(new \DateInterval($interval));
                    $returnval = $objDate->format("Y-m-d H:i:s");
                    break;
                default:
                    $objDate   = new \Datetime($date);
                    $objDate->sub(new \DateInterval($interval));
                    $returnval = $objDate->format("Y-m-d H:i:s");
            }

            return $returnval;
        }

        /**
         * [date description]
         *
         * @method date
         *
         * @param  [type] $date [description]
         *
         * @return [type]       [description]
         */
        public function date($date)
        {
            return date("Y-m-d", strtotime($date));
        }

        /**
         * [isnull description]
         *
         * @method isnull
         *
         * @param  [type] $field [description]
         *
         * @return [type]        [description]
         */
        public function isnull($field)
        {
            return $this->custom(function ($row) use ($field) {
                $row['isnull_' . $field] = is_null(isAke($row, $field, null));

                return $row;
            });
        }

        /**
         * [regexp description]
         *
         * @method regexp
         *
         * @param  [type] $field   [description]
         * @param  [type] $pattern [description]
         *
         * @return [type]          [description]
         */
        public function regexp($field, $pattern)
        {
            return $this->custom(function ($row) use ($field, $pattern) {
                $pattern = str_replace('/', '\/', $pattern);
                $pattern = "/" . $pattern . "/i";
                $res = preg_match($pattern, isAke($row, $field, null));
                $row['regexp_' . $field] = $res;

                return $row;
            });
        }

        /**
         * [concat description]
         *
         * @method concat
         *
         * @return [type] [description]
         */
        public function concat()
        {
            $fields = func_get_args();

            return $this->custom(function ($row) use ($fields) {
                $val = '';

                foreach ($fields as $field) {
                    $val .= isAke($row, $field, '');
                }

                $row['concat'] = $val;

                return $row;
            });
        }

        /**
         * [datediff description]
         *
         * @method datediff
         *
         * @param  [type]   $start [description]
         * @param  [type]   $end   [description]
         *
         * @return [type]          [description]
         */
        public function datediff($start, $end)
        {
            $startDate = new \DateTime($start);
            $endDate   = new \DateTime($end);
            $interval  = $endDate->diff($startDate, false);

            return $interval->format('%r%a');
        }

        /**
         * [utc_date description]
         *
         * @method utc_date
         *
         * @return [type]   [description]
         */
        public function utc_date()
        {
            return gmdate('Y-m-d', time());
        }

        /**
         * [utc_time description]
         *
         * @method utc_time
         *
         * @return [type]   [description]
         */
        public function utc_time()
        {
            return gmdate('H:i:s', time());
        }

        /**
         * [utc_timestamp description]
         *
         * @method utc_timestamp
         *
         * @return [type]        [description]
         */
        public function utc_timestamp()
        {
            return gmdate('Y-m-d H:i:s', time());
        }

        /**
         * [mutable description]
         *
         * @method mutable
         *
         * @param  array   $config [description]
         *
         * @return [type]          [description]
         */
        public function mutable(array $config)
        {
            return new Models($this, function ($model) use ($config) {
                foreach ($config as $fn => $cb) {
                    if (is_callable($cb)) {
                        $model->$fn = $cb;
                    }
                }

                return $model;
            });
        }

        /**
         * [__get description]
         *
         * @method __get
         *
         * @param  [type] $k [description]
         *
         * @return [type]    [description]
         */
        public function __get($k)
        {
            if ($k == 'store') {
                return Now::get('S3.store.' . $this->collection);
            } else {
                return isset($this->$k) ? $this->$k : null;
            }
        }

        /**
         * [lastCreated description]
         *
         * @method lastCreated
         *
         * @param  boolean     $object [description]
         *
         * @return [type]              [description]
         */
        public function lastCreated($object = false)
        {
            return $this->order('id', 'DESC')->cursor()->first($object);
        }

        /**
         * [firstCreated description]
         *
         * @method firstCreated
         *
         * @param  boolean      $object [description]
         *
         * @return [type]               [description]
         */
        public function firstCreated($object = false)
        {
            return $this->order('id')->cursor()->first($object);
        }

        /**
         * [getSchema description]
         *
         * @method getSchema
         *
         * @return [type]    [description]
         */
        public function getSchema()
        {
            $row = $this->cursor()->first();

            if (!$row) {
                return [
                    'id' => 'primary key integer'
                ];
            }

            $fields = [];

            foreach ($row as $k => $v) {
                $type = gettype($v);

                if (strlen($v) > 255 && $type == 'string') {
                    $type = 'text';
                }

                $fields[$k] = $type;
            }

            $collection = [];

            $collection['id'] = 'primary key integer';

            ksort($fields);

            foreach ($fields as $k => $v) {
                if (fnmatch('*_id', $k)) {
                    $collection[$k] = 'foreign key integer';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*ed_at', $k)) {
                    $collection[$k] = 'timestamp integer';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*tel*', $k)) {
                    $collection[$k] = 'phone string';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*phone*', $k)) {
                    $collection[$k] = 'phone string';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*mobile*', $k)) {
                    $collection[$k] = 'phone string';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*cellular*', $k)) {
                    $collection[$k] = 'phone string';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*fax*', $k)) {
                    $collection[$k] = 'phone string';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*mail*', $k) && fnmatch('*@*', $v)) {
                    $collection[$k] = 'email string';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*courriel*', $k) && fnmatch('*@*', $v)) {
                    $collection[$k] = 'email string';
                }
            }

            foreach ($fields as $k => $v) {
                if (!isset($collection[$k])) {
                    $collection[$k] = $v;
                }
            }

            return $collection;
        }

        /**
         * [with description]
         *
         * @method with
         *
         * @param  [type]  $what   [description]
         * @param  boolean $object [description]
         *
         * @return [type]          [description]
         */
        public function with($what, $object = false)
        {
            $collection = $ids = $foreigns = $foreignsCo = [];

            if (is_string($what)) {
                if (fnmatch('*,*', $what)) {
                    $what = explode(',', str_replace(' ', '', $what));
                }

                $res = $this->get(null, $object);
            } elseif (is_array($what)) {
                foreach ($what as $key => $closure) {
                    $what = $key;

                    break;
                }

                if (fnmatch('*,*', $what)) {
                    $what = str_replace(' ', '', $what);
                    $what = explode(',', $what);
                }

                $db     = $this;
                call_user_func_array($closure, [$db]);
                $res    = $db->get(null, $object);
            }

            if ($res->count() > 0) {
                foreach ($res as $r) {
                    if (is_object($r)) {
                        $row = $r->toArray();
                    } else {
                        $row = $r;
                    }

                    if (is_string($what)) {
                        $value = isAke($row, $what . '_id', false);

                        if (false !== $value) {
                            if (!in_array($value, $ids)) {
                                array_push($ids, $value);
                            }
                        }
                    } elseif (is_array($what)) {
                        foreach ($what as $fk) {
                            if (!isset($ids[$fk])) {
                                $ids[$fk] = [];
                            }

                            $value = isAke($row, $fk . '_id', false);

                            if (false !== $value) {
                                if (!in_array($value, $ids[$fk])) {
                                    array_push($ids[$fk], $value);
                                }
                            }
                        }
                    }
                }

                if (!empty($ids)) {
                    if (is_string($what)) {
                        $db = Db::instance($this->db, $what);

                        $foreigns = $db->where(['id', 'IN', implode(',', $ids)])->get(null, $object);

                        if ($foreigns->count() > 0) {
                            foreach ($foreigns as $foreign) {
                                $id = $object ? (int) $foreign->id : (int) $foreign['id'];
                                $foreignsCo[$id] = $foreign;
                            }
                        }
                    } elseif (is_array($what)) {
                        foreach ($what as $fk) {
                            $idsFk = $ids[$fk];

                            $db = Db::instance($this->db, $fk);

                            $foreigns = $db->where(['id', 'IN', implode(',', $idsFk)])->get(null, $object);

                            if ($foreigns->count() > 0) {
                                foreach ($foreigns as $foreign) {
                                    $id = $object ? $foreign->id : $foreign['id'];
                                    $foreignsCo[$fk][$id] = $foreign;
                                }
                            }
                        }
                    }

                    if (!empty($foreignsCo)) {
                        if (is_string($what)) {
                            $whatId = $what . '_id';

                            foreach ($res as $r) {
                                if (is_object($r)) {
                                    if (isset($r->$whatId)) {
                                        if (isset($foreignsCo[$r->$whatId])) {
                                            $r->$what = $foreignsCo[$r->$whatId];
                                        }
                                    }
                                } else {
                                    if (isset($r[$whatId])) {
                                        if (isset($foreignsCo[$r[$whatId]])) {
                                            $r[$what] = $foreignsCo[$r[$whatId]];
                                        }
                                    }
                                }

                                array_push($collection, $r);
                            }
                        } elseif (is_array($what)) {
                            foreach ($res as $r) {
                                foreach ($what as $fk) {
                                    $fkId = $fk . '_id';

                                    if (is_object($r)) {
                                        if (isset($r->$fkId)) {
                                            if (isset($foreignsCo[$fk])) {
                                                if (isset($foreignsCo[$fk][$r->$fkId])) {
                                                    $r->$fk = $foreignsCo[$fk][$r->$fkId];
                                                }
                                            }
                                        }
                                    } else {
                                        if (isset($r[$fkId])) {
                                            if (isset($foreignsCo[$fk])) {
                                                if (isset($foreignsCo[$fk][$r[$fkId]])) {
                                                    $r[$fk] = $foreignsCo[$fk][$r[$fkId]];
                                                }
                                            }
                                        }
                                    }
                                }

                                array_push($collection, $r);
                            }
                        }
                    }
                }
            }

            return $collection;
        }

        /**
         * [firstWhere description]
         *
         * @method firstWhere
         *
         * @param  array      $where  [description]
         * @param  boolean    $object [description]
         *
         * @return [type]             [description]
         */
        public function firstWhere(array $where, $object = true)
        {
            return $this->where($where)->first($object);
        }

        /**
         * [lastWhere description]
         *
         * @method lastWhere
         *
         * @param  array     $where  [description]
         * @param  boolean   $object [description]
         *
         * @return [type]            [description]
         */
        public function lastWhere(array $where, $object = true)
        {
            return $this->where($where)->last($object);
        }

        /**
         * [lookfor description]
         *
         * @method lookfor
         *
         * @param  array   $criterias [description]
         * @param  boolean $cursor    [description]
         *
         * @return [type]             [description]
         */
        public function lookfor(array $criterias, $cursor = false)
        {
            foreach ($criterias as $field => $value) {
                $this->where([$field, '=', $value]);
            }

            return $cursor ? $this->cursor() : $this;
        }

        /**
         * [q description]
         *
         * @method q
         *
         * @return [type] [description]
         */
        public function q()
        {
            $conditions = array_chunk(func_get_args(), 3);

            foreach ($conditions as $condition) {
                $this->where($condition);
            }

            return $this;
        }

        /**
         * [insertWithId description]
         * @param  array   $data         [description]
         * @param  boolean $returnObject [description]
         * @return [type]                [description]
         */
        public function insertWithId(array $data, $returnObject = false)
        {
            if (!isset($data['id'])) {
                $id = $data['id'] = $this->makeId();
            } else {
                $id = $data['id'];

                if (is_numeric($id)) {
                    $this->store->set('id', $id);

                    $this->store->hdel('ids', $id);
                    $this->store->hset('ids', $id, true);
                } else {
                    $data['collection_id'] = $data['id'];
                    $id = $data['id'] = $this->makeId();
                }
            }

            if (!isset($data['created_at'])) {
                $data['created_at'] = time();
            }

            if (!isset($data['updated_at'])) {
                $data['updated_at'] = time();
            }

            $data = $this->analyze($data);

            $this->store->hdel('data', $id);
            $this->store->hset('data', $id, $data);

            $this->populateFields($data, $id);

            $this->setAge();

            return $returnObject ? $this->model($data) : $data;
        }

        public function pluck($field, $default = null)
        {
            $tab = $this->first();

            if (is_string($field)) {
                return isAke($tab, $field, $default);
            } elseif (is_array($field)) {
                $collection = [];

                foreach ($field as $f) {
                    $collection[$f] = isAke($tab, $f, null);
                }

                return $collection;
            }

            return $default;
        }
    }
