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

    namespace Way;

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
    use Thin\Light;
    use Thin\Timer;

    class Db
    {
        public $db, $table, $collection, $results, $cnx, $limit, $offset, $groupBy, $cacheClient, $store, $events;
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

            $this->store        = lib('redys', [$this->collection]);
            $this->events       = new Event($this->collection);

            $this->getAge();

            lib('facade', ['SplFixedArray', 'WayArray']);
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

            $this->store        = lib('redys', [$this->collection]);
            $this->events       = new Event($this->collection);

            return $this;
        }

        public function listen(callable $closure)
        {
            $key = sha1(serialize($this->wheres));
            $this->events->listen($key, $closure);

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
            $has    = Instance::has('Box', $key);

            if (true === $has) {
                return Instance::get('Box', $key);
            } else {
                return Instance::make('Box', $key, new self($db, $table));
            }
        }

        public function model($data = [])
        {
            $db     = $this->db;
            $table  = $this->table;

            $modelFile = APPLICATION_PATH . DS . 'models' . DS . 'Way' . DS . 'models' . DS . Inflector::lower($db) . DS . ucfirst(Inflector::lower($table)) . '.php';

            if (!is_dir(APPLICATION_PATH . DS . 'models' . DS . 'Way')) {
                File::mkdir(APPLICATION_PATH . DS . 'models' . DS . 'Way');
            }

            if (!is_dir(APPLICATION_PATH . DS . 'models' . DS . 'Way' . DS . 'models')) {
                File::mkdir(APPLICATION_PATH . DS . 'models' . DS . 'Way' . DS . 'models');
            }

            if (!is_dir(APPLICATION_PATH . DS . 'models' . DS . 'Way' . DS . 'models' . DS . Inflector::lower($db))) {
                File::mkdir(APPLICATION_PATH . DS . 'models' . DS . 'Way' . DS . 'models' . DS . Inflector::lower($db));
            }

            if (!File::exists($modelFile)) {
                File::put($modelFile, str_replace('##class##', ucfirst(Inflector::lower($db)) . ucfirst(Inflector::lower($table)) . 'WayModel', File::read(__DIR__ . DS . 'dbModel.tpl')));
            }

            $class = '\\Thin\\' . ucfirst(Inflector::lower($db)) . ucfirst(Inflector::lower($table)) . 'WayModel';

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

                $meta = $this->store->get('meta.' . $k);

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

        public function order($fieldOrder, $orderDirection = 'ASC')
        {
            $this->orders[$fieldOrder] = $orderDirection;

            return $this;
        }

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

        public function timestamps()
        {
            return $this->select('created_at,updated_at');
        }

        public function all($object = false)
        {
            return $object ? $this->reset()->cursor()->toObject() : $this->reset()->cursor()->toArray();
        }

        public function get()
        {
            return $this->cursor();
        }

        public function cursor($what = null)
        {
            return $what instanceof Db ? new Cursor($what) : new Cursor($this);
        }

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

        public function findFirstBy($field, $value, $object = false)
        {
            return $this->where([$field, '=', $value])->get()->first($object);
        }

        public function findAndModify($where, array $update)
        {
            unset($update['id']);
            $where = is_numeric($where) ? ['id', '=', $where] : $where;

            $cursor = $this->where($where)->get();

            $collection = [];

            if ($cursor->count() > 0) {
                while ($row = $cursor->fetch()) {
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

        public function refresh()
        {
            return $this->setAge();
        }

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

        public function drop()
        {
            $keys = $this->store->keys('*');

            foreach ($keys as $key) {
                $this->store->del($key);
            }

            return $this;
        }

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

        public function timestamp($date)
        {
            return ts($date);
        }

        public function __toString()
        {
            return "$this->db::$this->table";
        }

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

        private function getTime()
        {
            $time = microtime();
            $time = explode(' ', $time, 2);

            return end($time) + current($time);
        }

        public function lock($action = 'write')
        {
            if (true === $this->cacheEnabled) {
                $key = "lock.$action";

                $this->store->set($key, time());
            }

            return $this;
        }

        public function unlock($action = 'write')
        {
            if (true === $this->cacheEnabled) {
                $key = "lock.$action";

                $this->store->del($key);
            }

            return $this;
        }

        public function freeze()
        {
            return $this->lock('read')->lock('write');
        }

        public function unfreeze()
        {
            return $this->unlock('read')->unlock('write');
        }

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
                $args[] = $db;

                return call_user_func_array([$model, $scope], $args);
            }

            throw new Exception("Method '$fn' is unknown.");
        }

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

        public function post($save = false)
        {
            return !$save ? $this->create($_POST) : $this->create($_POST)->save();
        }

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

        public function like($field, $str, $op = 'AND', $results = [])
        {
            return $this->where([$field, 'LIKE', $str], $op, $results);
        }

        public function likeStart($field, $str, $op = 'AND', $results = [])
        {
            return $this->where([$field, 'LIKE', $str . '%'], $op, $results);
        }

        public function startsWith($field, $str, $op = 'AND', $results = [])
        {
            return $this->where([$field, 'LIKE', $str . '%'], $op, $results);
        }

        public function endsWith($field, $str, $op = 'AND', $results = [])
        {
            return $this->where([$field, 'LIKE', '%' . $str], $op, $results);
        }

        public function likeEnd($field, $str, $op = 'AND', $results = [])
        {
            return $this->where([$field, 'LIKE', '%' . $str], $op, $results);
        }

        public function notLike($field, $str, $op = 'AND', $results = [])
        {
            return $this->where([$field, 'NOT LIKE', $str], $op, $results);
        }

        public function pk()
        {
            return 'id';
        }

        public function findOneBy($field, $value, $object = false)
        {
            return $this->findBy($field, $value, true, $object);
        }

        public function findOrFail($id, $object = true)
        {
            if (!is_null($item = $this->find($id, $object))) {
                return $item;
            }

            throw new Exception("Row '$id' in '$this->table' is unknown.");
        }

        public function findBy($field, $value, $one = false, $object = false)
        {
            $cursor = $this->where([$field, '=', $value])->get();

            if ($cursor->count() > 0 && true === $one) {
                return $object ? $this->model(current($res)) : current($res);
            }

            if ($cursor->count() < 1 && true === $one && true === $object) {
                return null;
            }

            return true === $object ? lib('collection', [$res]) : $res;
        }

        public function first($object = false)
        {
            return $this->get()->first($object);
        }

        public function firstOrFail($object = true)
        {
            if (!is_null($item = $this->first($object))) {
                return $item;
            }

            throw new Exception("Row '$id' in '$this->table' is unknown.");
        }

        public function last($object = false)
        {
            return $this->get()->last($object);
        }

        public function count()
        {
            return $this->get()->count();
        }

        public function only($field, $default = null)
        {
            $row = $this->first(true);

            return $row ? $row->$field : $default;
        }

        public function replace($compare = [], $update = [])
        {
            $instance = $this->firstOrCreate($compare);

            return $instance->hydrate($update)->save();
        }

        public function copy($where, array $newArgs)
        {
            $db     = self::instance($this->db, $this->table);
            $cursor = $db->query($where)->get();

            if ($cursor->count() > 0) {
                while ($row = $cursor->fetch()) {
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

        public function firstOrNew($tab = [])
        {
            return $this->firstOrCreate($tab, false);
        }

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

        public function between($field, $min, $max)
        {
            return $this->where([$field, '>=', $min])->where([$field, '<=', $max]);
        }

        public function rand()
        {
            return $this->get()->rand();
        }

        public function random()
        {
            return $this->get()->rand();
        }

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

        public function destroy($what)
        {
            /* polymorphism */
            if (func_num_args() == 1) {
                if (is_string($what)) {
                    if (fnmatch('*,*', $what)) {
                        $what = str_replace(' ', '', $what);
                        $what = explode(',', $what);
                    }
                }
            } else {
                $what = func_get_args();
            }

            if (is_array($what)) {
                foreach ($what as $seg) {
                    $obj = $this->find((int) $seg);

                    if ($obj) {
                        $obj->delete();
                    }
                }
            }

            return $this;
        }

        public function join($table, $field = null, $db = null)
        {
            $db     = is_null($db)      ? $this->db         : $db;
            $field  = is_null($field)   ? $table . '_id'    : $field;

            $this->joins[] = [$table, $field, $db];

            return $this;
        }

        public function latest($field = null)
        {
            $field = is_null($field) ? 'updated_at' : $field;

            return $this->order($field, 'DESC');
        }

        public function oldest($field = null)
        {
            $field = is_null($field) ? 'updated_at' : $field;

            return $this->order($field, 'ASC');
        }

        public function history()
        {
            return $this->order('id', 'ASC');
        }

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

        public function updateOrCreate(array $attributes, array $values = [])
        {
            return $this->firstOrCreate($attributes)->fill($values)->save();
        }

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
    }
