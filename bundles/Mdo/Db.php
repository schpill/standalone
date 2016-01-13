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

    namespace Mdo;

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
        public $query;

        public static $cache = [];

        public function __construct($db, $table)
        {
            $db                 = strtolower($db);
            $table              = strtolower($table);

            $this->db           = $db;
            $this->table        = $table;
            $this->collection   = "$db.$table";

            $this->store        = new Motor($db, $table);
            $this->events       = new Event($this->collection);

            $this->getAge();

            lib('facade', ['SplFixedArray', 'MdoArray']);
        }

        public function reset()
        {
            $this->query        = null;
            $this->results      = null;
            $this->totalResults = 0;
            $this->transactions = 0;
            $this->selects      = [];
            $this->joinTables   = [];
            $this->wheres       = [];
            $this->groupBys     = [];
            $this->orders       = [];

            $this->store        = new Motor($this->db, $this->table);
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
            $has    = Instance::has('Mdo', $key);

            if (true === $has) {
                return Instance::get('Mdo', $key);
            } else {
                return Instance::make('Mdo', $key, new self($db, $table));
            }
        }

        public function model($data = [])
        {
            $db     = $this->db;
            $table  = $this->table;

            $dir = Conf::get('dir.mdo.models', APPLICATION_PATH . DS . 'models' . DS . 'Mdo');

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
                File::put($modelFile, str_replace('##class##', ucfirst(Inflector::lower($db)) . ucfirst(Inflector::lower($table)) . 'MdoModel', File::read(__DIR__ . DS . 'dbModel.tpl')));
            }

            $class = '\\Thin\\' . ucfirst(Inflector::lower($db)) . ucfirst(Inflector::lower($table)) . 'MdoModel';

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

        public function __destruct()
        {
            $this->reset();
        }

        public function findOne($query, $object = false)
        {
            return $this->where($query)->get()->first($object);
        }

        public function find($id, $object = true)
        {
            if (!is_numeric($id)) {
                return null;
            }

            $id     = (int) $id;
            $query  = "SELECT * FROM $this->table WHERE id = $id";
            $result = mysqli_query($this->store->db, $query);

            if (is_bool($result)) {
                return null;
            }

            $obj    = null;

            while($obj = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                break;
            }

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
            return self::instance($this->db, $table)->find((int) $id, false);
        }

        public function save(array $data)
        {
            $id = isAke($data, 'id', false);

            return !$id ? $this->add($data) : $this->edit($id, $data);
        }

        private function add(array $data, $checkTuple = true)
        {
            if ($checkTuple) {
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
            }

            if (!isset($data['created_at'])) {
                $data['created_at'] = (int) time();
            }

            if (!isset($data['updated_at'])) {
                $data['updated_at'] = (int) time();
            }

            $data = $this->analyze($data);

            $query = "INSERT INTO $this->table SET ";

            foreach ($data as $k => $v) {
                $query .= "$k = '" . mysqli_escape_string($this->store->db, $v) . "', ";
            }

            $query = substr($query, 0, -2);

            $result = mysqli_query($this->store->db, $query);

            if (is_bool($result)) {
                throw new Exception($this->store->db->error);
            }

            $id = mysqli_insert_id($this->store->db);

            $data['id'] = $id;

            if ($checkTuple) {
                $this->addTuple($id, $keyTuple);
            }

            $this->setAge();

            return $this->model($data);
        }

        public function insert(array $data)
        {
            return $this->add($data, false);
        }

        private function edit($id, array $data, $checkTuple = true)
        {
            if ($checkTuple) {
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
            }

            $data['updated_at'] = (int) time();

            $data = $this->analyze($data);

            $query = "UPDATE $this->table SET ";

            foreach ($data as $k => $v) {
                $query .= "$k = '" . mysqli_escape_string($this->store->db, $v) . "', ";
            }

            $query = substr($query, 0, -2) . " WHERE id = $id";

            $result = mysqli_query($this->store->db, $query);

            if (is_bool($result)) {
                throw new Exception($this->store->db->error);
            }

            if ($checkTuple) {
                $this->delTuple($id);
                $this->addTuple($id, $keyTuple);
            }

            $this->setAge();

            return $this->find($id);
        }

        public function delete($id)
        {
            $query = "SELECT * FROM $this->table WHERE id = $id";
            $result = mysqli_query($this->store->db, $query);

            if (is_bool($result)) {
                throw new Exception($this->store->db->error);
            }

            while($data = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                break;
            }

            if ($data) {
                $this->delTuple($id);
                $data = $data;
                $q = "DELETE FROM $this->table WHERE id = $id";

                $result = mysqli_query($this->store->db, $q);

                if (is_bool($result)) {
                    throw new Exception($this->store->db->error);
                }

                $this->setAge();

                return true;
            }

            return false;
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

        public function cursor($closure = null, $object = false)
        {
            return $this->get($closure, $object);
        }

        public function models($closure = null, $object = true)
        {
            return $this->get($closure, $object);
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

        public function refresh()
        {
            return $this->setAge();
        }

        public function flush()
        {
            $query  = "DELETE FROM $this->table";
            $result = mysqli_query($this->store->db, $query);

            return $this->setAge();
        }

        public function drop()
        {
            $query = "DROP $this->table";
            $result = mysqli_query($this->store->db, $query);

            if (is_bool($result)) {
                return false;
            }

            return true;
        }

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

                    return $this->where([$object, '=', current($args)])->get()->last($obj);
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

                $first = $this->get()->first(true);

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

            return $this;
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
            $total      = $this->get()->count();
            $last       = ceil($total / $byPage);
            $paginator  = lib('paginator', [[], $page, $total, $byPage, $last, $var]);

            $start  = ($byPage * $page) - ($byPage - 1);
            $end    = $byPage * $page;

            $end    = $end > $total ? $total : $end;

            $data       = $this->limit($byPage, $offset)->get();
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

            $rows = $this->multiQuery($where)->get();

            $ids = [];

            foreach ($rows as $row) {
                $ids[] = $row['id'];
            }

            $sub = $db1->where([$fk, 'IN', implode(',', $ids)])->get();

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

            return self::instance($database, $t2)->where([$fk2, 'IN', implode(',', $ids)])->get();
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

        public function lt($field, $value)
        {
            return $this->where([$field, '<', $value]);
        }

        public function gt($field, $value)
        {
            return $this->where([$field, '>', $value]);
        }

        public function lte($field, $value)
        {
            return $this->where([$field, '<=', $value]);
        }

        public function gte($field, $value)
        {
            return $this->where([$field, '>=', $value]);
        }

        public function before($date, $exact = true)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $exact ? $this->lt('created_at', $date) : $this->lte('created_at', $date);
        }

        public function after($date, $exact = true)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $exact ? $this->gt('created_at', $date) : $this->gte('created_at', $date);
        }

        public function when($field, $op, $date)
        {
            if (!is_int($date)) {
                $date = (int) $date->timestamp;
            }

            return $this->where([$field, $op, $date]);
        }

        public function deleted()
        {
            return $this->when('deleted_at', '<=', lib('time')->now());
        }

        public function closure(callable $closure)
        {
            $rows = new Rows($this);

            $cursor = $this->get();

            foreach ($cursor as $row) {
                $check = call_user_func_array($closure, [$this, $row]);

                if ($check) {
                    $rows->add($row);
                }
            }

            return $rows;
        }

        public function contains($field, $pattern)
        {
            return $this->where([$field, 'LIKE', "%$pattern%"]);
        }

        public function notContains($field, $pattern)
        {
            return $this->where([$field, 'NOT LIKE', "%$pattern%"]);
        }

        public function view($name)
        {
            return (new View($this, $name))->get();
        }

        public function makeView($name)
        {
            return (new View($this, $name))->make();
        }

        public function firstByAttributes($attributes, $object = false)
        {
            foreach ($attributes as $k => $v) {
                $this->where([$k, '=', $v]);
            }

            return $this->first($object);
        }

        public function min($field)
        {
            if (empty($this->wheres)) {
                $query = "SELECT MIN($field) as min FROM $this->table";
                $result = mysqli_query($this->store->db, $query);

                while($data = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                    return $data['min'];
                }

                return 0;
            }

            $row = $this->order($field)->get()->first();

            if ($row) {
                return isAke($row, $field);
            }

            return 0;
        }

        public function max($field)
        {
            if (empty($this->wheres)) {
                $query = "SELECT MAX($field) as max FROM $this->table";
                $result = mysqli_query($this->store->db, $query);

                while($data = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                    return $data['max'];
                }

                return 0;
            }

            $row = $this->order($field, 'DESC')->get()->first();

            if ($row) {
                return isAke($row, $field);
            }

            return 0;
        }

        public function sum($field)
        {
            if (empty($this->wheres)) {
                $query = "SELECT SUM($field) as sum FROM $this->table";
                $result = mysqli_query($this->store->db, $query);

                while($data = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                    return $data['sum'];
                }

                return 0;
            }

            $rows = $this->select($field)->get();

            $sum = 0;

            foreach ($rows as $row) {
                $sum += isAke($row, $field, 0);
            }

            return $sum;
        }

        public function avg($field)
        {
            if (empty($this->wheres)) {
                $query = "SELECT AVG($field) as avg FROM $this->table";
                $result = mysqli_query($this->store->db, $query);

                while($data = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                    return $data['avg'];
                }

                return 0;
            }

            $rows = $this->select($field)->get();

            $sum = 0;

            foreach ($rows as $row) {
                $sum += isAke($row, $field, 0);
            }

            return floatval($sum / $rows->count());
        }

        public function random($nb = 1)
        {
            return $this->rand($nb);
        }

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

        public function newQuery()
        {
            return $this->reset();
        }

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

        public function custom(callable $closure, $object = false)
        {
            return new Cursor($this, $closure, $object);
        }

        public function groupBy($field)
        {
            $this->groupBy = !fnmatch('*.*', $field) ? $this->table . '.' . $field : $field;

            return $this;
        }

        public function get($closure = null, $object = false)
        {
            $query = "SELECT * FROM ##table##";

            $foreigns = [];

            if (!empty($this->wheres)) {
                $first = true;

                foreach ($this->wheres as $wh) {
                    list($condition, $op) = $wh;
                    list($f, $o, $v) = $condition;

                    if (fnmatch('*.*', $f)) {
                        list($fkt, $fkf) = explode('.', $f, 2);

                        if (!in_array($fkt, $foreigns)) {
                            $foreigns[] = $fkt;
                        }
                    } else {
                        $f = $this->table . '.' . $f;
                    }

                    if (!$first) {
                        $query .= " $op $f $o '" . mysqli_escape_string($this->store->db, $v) . "'";
                    } else {
                        $query .= " WHERE $f $o '" . mysqli_escape_string($this->store->db, $v) . "'";
                        $first = false;
                    }
                }
            }

            if (!empty($this->groupBy)) {
                $query .= " GROUP BY $this->groupBy ";
            }

            if (!empty($this->orders)) {
                $first = true;

                foreach ($this->orders as $f => $direction) {
                    if ($first) {
                        $query .= " ORDER BY $f $direction";
                        $first = false;
                    } else {
                        $query = ", $f $direction";
                    }
                }
            }

            if (!empty($this->selects)) {
                $fields = implode(',', $this->selects);

                if (!empty($foreigns)) {
                    $fields = [];

                    foreach ($this->selects as $select) {
                        if (!fnmatch('*.*', $select)) {
                            $fields[] = $this->table . '.' . $select;
                        } else {
                            $fields[] = $select;
                        }
                    }

                    $fields = implode(',', $fields);
                }

                $query = str_replace('SELECT * ', "SELECT $fields ", $query);
            } else {
                if (!empty($foreigns)) {
                    $query = str_replace('SELECT * ', "SELECT " . $this->table . ".* ", $query);
                }
            }

            if (!empty($foreigns)) {
                $join = '';

                foreach ($foreigns as $fkt) {
                    $join .= " LEFT JOIN $fkt ON " . $this->table . "." . $fkt . "_id = " . $fkt . ".id";
                }

                $table = $this->table . $join;
            } else {
                $table = $this->table;
            }

            if (isset($this->limit)) {
                $offset = isset($this->offset) ? $this->offset : 0;
                $query .= " LIMIT $offset,$this->limit";
            }

            $query = str_replace('##table##', $table, $query);

            $this->query = $query;

            return new Cursor($this, $closure, $object);
        }

        public function raw($query, $closure = null, $object = false)
        {
            $query = !fnmatch('*FROM ' . $this->table . '*', $query)
            ? 'SELECT * FROM ' . $this->table . ' ' . $query
            : $query;

            $this->query = $query;

            return new Cursor($this, $closure, $object);
        }

        public function all($closure = null, $object = false)
        {
            $this->query = "SELECT * FROM $this->table";

            return new Cursor($this, $closure, $object);
        }

        public static function schema($table, $db = null)
        {
            $db = is_null($db) ? SITE_NAME : $db;

            return new Schema($db, $table);
        }

        public static function migrate()
        {
            $dir = Conf::get('dir.mdo.migrations', APPLICATION_PATH . DS . 'migrations' . DS . 'Mdo');

            if (is_dir($dir)) {
                $files = glob($dir . DS . '*.php');

                foreach ($files as $file) {
                    $cb = include($file);
                    call_user_func_array($cb, []);
                }
            }
        }

        public static function seeds()
        {
            $dir = Conf::get('dir.mdo.seeds', APPLICATION_PATH . DS . 'seeds' . DS . 'Mdo');

            if (is_dir($dir)) {
                $files = glob($dir . DS . '*.php');

                foreach ($files as $file) {
                    $cb = include($file);
                    call_user_func_array($cb, []);
                }
            }
        }

        public function index($drop = false, $id = null)
        {
            $model = $this->model();

            $methods = get_class_methods($instance);

            if (in_array('indices', $model)) {
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

        public function reindex($id = null)
        {
            return $this->index(true, $id);
        }

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

        public function mutable(array $config)
        {
            return new Cursor($this, function ($model) use ($config) {
                foreach ($config as $fn => $cb) {
                    if (is_callable($cb)) {
                        $model->$fn = $cb;
                    }
                }

                return $model;
            }, true);
        }
    }
