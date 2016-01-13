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

    use Thin\Utils;
    use Thin\Arrays;
    use Thin\Inflector;
    use Closure;
    use ArrayObject;
    use ArrayAccess;
    use Countable;
    use IteratorAggregate;

    class Model extends ArrayObject implements ArrayAccess, Countable, IteratorAggregate
    {
        public $_db, $_initial;
        public $_data       = [];
        public $_events     = [];
        public $_hooks      = [
            'beforeCreate'  => null,
            'beforeRead'    => null,
            'beforeUpdate'  => null,
            'beforeDelete'  => null,
            'afterCreate'   => null,
            'afterRead'     => null,
            'afterUpdate'   => null,
            'afterDelete'   => null,
            'validate'      => null
        ];

        public function __construct(Db $db, $data = [])
        {
            $this->_db  = $db;

            $hasId = false;

            if (!empty($data)) {
                $data = $this->treatCast($data);

                $id = isAke($data, 'id', false);

                if (false !== $id) {
                    $hasId = true;

                    $this->_data['id'] = (int) $id;

                    unset($data['id']);
                }
            } else {
                $fields = $db->store->get('schema', []);

                if (empty($fields)) {
                    $first = $db->first();

                    if ($first) {
                        unset($first['id'], $first['updated_at'], $first['created_at']);
                        $fields = array_keys($first);

                        foreach ($fields as $f) {
                            $data[$f] = null;
                        }
                    }
                } else {
                    $datas = $fields;

                    $fields = array_keys($fields);

                    foreach ($fields as $f) {
                        $i          = isAke($datas, $f, []);
                        $default    = isAke($i, 'default', null);
                        $data[$f]   = $default;
                    }
                }
            }

            $this->_data = array_merge(
                $this->_data,
                $data
            );

            $this->boot();

            if (false !== $hasId) {
                $this->_related();
            }

            $this->_hooks();

            $this->_initial = $this->assoc();
        }

        private function treatCast($tab)
        {
            if (!empty($tab) && Arrays::isAssoc($tab)) {
                foreach ($tab as $k => $v) {
                    if (fnmatch('*_id', $k) && !empty($v)) {
                        if (is_numeric($v)) {
                            $tab[$k] = (int) $v;
                        }
                    }
                }
            }

            return $tab;
        }

        public function _keys()
        {
            return array_keys($this->_data);
        }

        public function expurge($field)
        {
            unset($this->_data[$field]);

            return $this;
        }

        public function _related()
        {
            $fields = array_keys($this->_data);
            $obj = $this;

            foreach ($fields as $field) {
                if (fnmatch('*_id', $field)) {
                    if (is_string($field)) {
                        $value = $this->$field;

                        if (!is_callable($value)) {
                            $fk = str_replace('_id', '', $field);
                            $ns = $this->_db->db;

                            $cb = function($object = false) use ($value, $fk, $ns, $field, $obj) {
                                $db = Db::instance($ns, $fk);

                                if (is_bool($object)) {
                                    return $db->find($value, $object);
                                } elseif (is_object($object)) {
                                    $obj->$field = (int) $object->id;

                                    return $obj;
                                }
                            };

                            $this->_event($fk, $cb);
                        }
                    }
                }
            }

            return $this;
        }

        public function _event($name, Closure $cb)
        {
            $this->_events[$name] = $cb;

            return $this;
        }

        public function offsetSet($key, $value)
        {
            if (fnmatch('*_id', $key)) {
                if (is_numeric($value)) {
                    $value = (int) $value;
                } elseif (is_object($value)) {
                    $value = (int) $value->id;
                    $this->_data[str_replace('_id', '', $key)] = $value->toArray();
                }
            } else {
                if (is_object($value)) {
                    if ($value instanceof \Thin\TimeLib) {
                        $value = $value->timestamp;
                    } else {
                        $this->_data[$key . '_id'] = $value->id;
                        $value = $value->toArray();
                    }
                }
            }

            $method = lcfirst(Inflector::camelize('set_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                $value = $this->$method($value);
            }

            $this->_data[$key] = $value;

            $autosave = isAke($this->_data, 'autosave', false);

            return !$autosave ? $this : $this->save();
        }

        public function offsetExists($key)
        {
            $method = lcfirst(Inflector::camelize('isset_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            $check = Utils::UUID();

            return $check != isAke($this->_data, $key, $check);
        }

        public function offsetUnset($key)
        {
            $method = lcfirst(Inflector::camelize('unset_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            unset($this->_data[$key]);

            return $this;
        }

        public function offsetGet($key)
        {
            $method = lcfirst(Inflector::camelize('get_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            $value = isAke($this->_data, $key, false);

            if (false === $value) {
                if ($key[strlen($key) - 1] == 's' && isset($this->_data['id']) && $key[0] != '_') {
                    $db = Db::instance($this->_db->db, substr($key, 0, -1));

                    $idField = $this->_db->table . '_id';

                    return $db->where([$idField, '=', $this->_data['id']])->get(null, true);
                } elseif (isset($this->_data[$key . '_id'])) {
                    $db = Db::instance($this->_db->db, $key);

                    return $db->find($this->_data[$key . '_id']);
                } else {
                    $value = null;
                }
            }

            return $value;
        }

        public function __set($key, $value)
        {
            if (fnmatch('*_id', $key)) {
                if (is_numeric($value)) {
                    $value = (int) $value;
                } elseif (is_object($value)) {
                    $value = (int) $value->id;
                    $this->_data[str_replace('_id', '', $key)] = $value->toArray();
                }
            } else {
                if (is_object($value) && !is_callable($value)) {
                    if ($value instanceof \Thin\TimeLib) {
                        $value = $value->timestamp;
                    } else {
                        $this->_data[$key . '_id'] = $value->id;
                        $value = $value->toArray();
                    }
                }
            }

            $method = lcfirst(Inflector::camelize('set_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                $value = $this->$method($value);
            }

            $this->_data[$key] = $value;

            $autosave = isAke($this->_data, 'autosave', false);

            return !$autosave ? $this : $this->save();
        }

        public function __get($key)
        {
            $method = lcfirst(Inflector::camelize('get_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            $value = isAke($this->_data, $key, false);

            if (false === $value) {
                if ($key[strlen($key) - 1] == 's' && isset($this->_data['id']) && $key[0] != '_') {
                    $db         = Db::instance($this->_db->db, substr($key, 0, -1));
                    $hasPivot   = $this->hasPivot($db);

                    if (true === $hasPivot) {
                        $model  = $db->model();
                        $pivots = $this->pivots($model)->get();

                        $ids = [];

                        if ($pivots->count() > 0) {
                            foreach ($pivots as $pivot) {
                                $id = isAke($pivot, substr($key, 0, -1) . '_id', false);

                                if (false !== $id) {
                                    array_push($ids, $id);
                                }
                            }

                            if (!empty($ids)) {
                                return $db->where(['id', 'IN', implode(',', $ids)])->get(null, true);
                            } else {
                                return [];
                            }
                        }
                    } else {
                        $idField = $this->_db->table . '_id';

                        return $db->where([$idField, '=', $this->_data['id']])->get(null, true);
                    }
                } elseif (isset($this->_data[$key . '_id'])) {
                    $db = Db::instance($this->_db->db, $key);

                    return $db->find($this->_data[$key . '_id']);
                } else {
                    $value = null;
                }
            }

            return $value;
        }

        public function __isset($key)
        {
            $method = lcfirst(Inflector::camelize('isset_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            $check = sha1(__file__);

            return $check != isAke($this->_data, $key, $check);
        }

        public function __unset($key)
        {
            $method = lcfirst(Inflector::camelize('unset_' . $key . '_attribute'));

            $methods = get_class_methods($this);

            if (in_array($method, $methods)) {
                return $this->$method();
            }

            unset($this->_data[$key]);
        }

        public function __call($func, $args)
        {
            if (substr($func, 0, strlen('get')) == 'get') {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, strlen('get'))));
                $field              = Inflector::lower($uncamelizeMethod);

                $method = lcfirst(Inflector::camelize('get_' . $field . '_attribute'));

                $methods = get_class_methods($this);

                if (in_array($method, $methods)) {
                    return $this->$method();
                }

                $default = count($args) == 1 ? current($args) : null;

                $res =  isAke($this->_data, $field, false);

                if (false !== $res) {
                    return $res;
                } else {
                    $resFk = isAke($this->_data, $field . '_id', false);

                    if (false !== $resFk) {
                        $db = Db::instance($this->_db->db, $field);
                        $object = count($args) == 1 ? $args[0] : false;

                        if (!is_bool($object)) {
                            $object = false;
                        }

                        return $db->find($resFk, $object);
                    } else {
                        if ($field[strlen($field) - 1] == 's' && isset($this->_data['id']) && $field[0] != '_') {
                            $db = Db::instance($this->_db->db, substr($field, 0, -1));
                            $object = count($args) == 1 ? $args[0] : false;

                            if (!is_bool($object)) {
                                $object = false;
                            }

                            $hasPivot   = $this->hasPivot($db);

                            if (true === $hasPivot) {
                                $model  = $db->model();
                                $pivots = $this->pivots($model)->get();

                                $ids = [];

                                if ($pivots->count() > 0) {
                                    foreach ($pivots as $pivot) {
                                        $id = isAke($pivot, substr($field, 0, -1) . '_id', false);

                                        if (false !== $id) {
                                            array_push($ids, $id);
                                        }
                                    }

                                    if (!empty($ids)) {
                                        return $db->where(['id', 'IN', implode(',', $ids)])->get(null, $object);
                                    } else {
                                        return [];
                                    }
                                }
                            } else {
                                $idField = $this->_db->table . '_id';

                                return $db->where([$idField, '=', $this->_data['id']])->get(null, $object);
                            }
                        } else {
                            return $default;
                        }
                    }
                }
            } elseif (substr($func, 0, strlen('has')) == 'has' && strlen($func) > strlen('has')) {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, strlen('has'))));
                $field              = Inflector::lower($uncamelizeMethod);

                $res =  isAke($this->_data, $field, false);

                if (false !== $res) {
                    return true;
                } else {
                    $resFk = isAke($this->_data, $field . '_id', false);

                    if (false !== $resFk) {
                        return true;
                    } else {
                        if ($field[strlen($field) - 1] == 's' && isset($this->_data['id']) && $field[0] != '_') {
                            $db = Db::instance($this->_db->db, substr($field, 0, -1));

                            $hasPivot = $this->hasPivot($db);

                            if (true === $hasPivot) {
                                $model  = $db->model();
                                $pivots = $this->pivots($model)->get();

                                $ids = [];

                                if ($pivots->count() > 0) {
                                    foreach ($pivots as $pivot) {
                                        $id = isAke($pivot, substr($field, 0, -1) . '_id', false);

                                        if (false !== $id) {
                                            return true;
                                        }
                                    }

                                    return false;
                                }
                            } else {
                                $idField = $this->_db->table . '_id';

                                $count = $db->where([$idField, '=', $this->_data['id']])->count();

                                return $count > 0 ? true : false;
                            }
                        }
                    }
                }

                return false;
            } elseif (substr($func, 0, strlen('belongsTo')) == 'belongsTo' && strlen($func) > strlen('belongsTo')) {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, strlen('set'))));
                $field              = Inflector::lower($uncamelizeMethod);

                $fk = current($args);

                if (is_object($fk)) {
                    $val = isAke($this->_data, $field . '_id', false);
                    $fkId = isset($fk->id) ? $fk->id : false;

                    if ($val && $fkId) {
                        return intval($val) == intval($fkId);
                    }
                }

                return false;
            } elseif (substr($func, 0, strlen('belongsToMany')) == 'belongsToMany' && strlen($func) > strlen('belongsToMany')) {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, strlen('set'))));
                $field              = Inflector::lower($uncamelizeMethod);

                if (is_object($fk)) {
                    return $this->belongsToMany($field);
                }

                return false;
            } elseif (substr($func, 0, strlen('set')) == 'set') {
                $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, strlen('set'))));
                $field              = Inflector::lower($uncamelizeMethod);

                if (!empty($args)) {
                    $val = current($args);
                } else {
                    $val = null;
                }

                if (fnmatch('*_id', $field)) {
                    if (is_numeric($val)) {
                        $val = (int) $val;
                    } elseif (is_object($val)) {
                        $val = (int) $val->id;
                        $this->_data[str_replace('_id', '', $field)] = $val->toArray();
                    }
                } else {
                    if (is_object($val)) {
                        if ($val instanceof \Thin\TimeLib) {
                            $val = $val->timestamp;
                        } else {
                            $this->_data[$field . '_id'] = $val->id;
                            $val = $val->toArray();
                        }
                    }
                }

                $method = lcfirst(Inflector::camelize('set_' . $field . '_attribute'));

                $methods = get_class_methods($this);

                if (in_array($method, $methods)) {
                    $val = $this->$method($val);
                }

                $this->_data[$field] = $val;

                $autosave = isAke($this->_data, 'autosave', false);

                return !$autosave ? $this : $this->save();
            } else {
                $cb = isAke($this->_events, $func, false);

                if (false !== $cb) {
                    if ($cb instanceof Closure) {
                        return call_user_func_array($cb, $args);
                    }
                } else {
                    if ($func[strlen($func) - 1] == 's' && isset($this->_data['id']) && $func[0] != '_') {
                        $db     = Db::instance($this->_db->db, substr($func, 0, -1));
                        $object = count($args) == 1 ? $args[0] : false;

                        if (!is_bool($object)) {
                            $object = false;
                        }

                        $hasPivot   = $this->hasPivot($db);

                        if (true === $hasPivot) {
                            $model  = $db->model();
                            $pivots = $this->pivots($model)->get();

                            $ids = [];

                            if ($pivots->count() > 0) {
                                foreach ($pivots as $pivot) {
                                    $id = isAke($pivot, substr($func, 0, -1) . '_id', false);

                                    if (false !== $id) {
                                        array_push($ids, $id);
                                    }
                                }

                                if (!empty($ids)) {
                                    return $db->where(['id', 'IN', implode(',', $ids)])->get(null, $object);
                                } else {
                                    return [];
                                }
                            }
                        } else {
                            $idField = $this->_db->table . '_id';

                            return $object
                            ? $db->where([$idField, '=', $this->_data['id']])->models()->toArray()
                            : $db->where([$idField, '=', $this->_data['id']])->cursor()->toArray();
                        }
                    } else {
                        if (!empty($args)) {
                            $object = count($args) == 1 ? $args[0] : false;
                            $db = Db::instance($this->_db->db, $func);

                            $field = $func . '_id';

                            if (is_bool($object) && isset($this->_data[$field])) {
                                return $db->find($value, $object);
                            } elseif (is_object($object)) {
                                $this->$field = (int) $object->id;

                                return $this;
                            }
                        }

                        $auth = ['checkIndices', '_hooks', 'rel', 'boot'];

                        if (in_array($func, $auth)) {
                            return true;
                        } else {
                            $scopes = $this->_db->store->get('scopes', []);
                            $scope = Inflector::uncamelize($func);

                            $closure = isAke($scopes, $scope, false);

                            if ($closure) {
                                if (is_callable($closure)) {
                                    $model = $this;

                                    return call_user_func_array($closure, [$model]);
                                }
                            }
                        }

                        if (is_callable($this->$func)) {
                            $args = array_merge([$this], $args);

                            return call_user_func_array($this->$func, $args);
                        }

                        throw new Exception("$func is not a model function of $this->_db.");
                    }
                }
            }
        }

        public function save()
        {
            $valid  = true;
            $create = false;
            $id     = isAke($this->_data, 'id', false);

            if (false !== $id) {
                $continue = sha1(serialize($this->_data)) != sha1(serialize($this->initial()));

                if (false === $continue) {
                    return $this;
                }
            }

            $hook = isAke($this->_hooks, 'validate', false);

            if ($hook) {
                $valid = call_user_func_array($hook, [$this->_data]);
            }

            if (true !== $valid) {
                throw new Exception("This model must be valid to be saved.");
            }

            if ($id) {
                $hook   = isAke($this->_hooks, 'beforeUpdate', false);
            } else {
                $create = true;
                $hook   = isAke($this->_hooks, 'beforeCreate', false);
            }

            if ($hook) {
                call_user_func_array($hook, [$this]);
            }

            $this->schema();

            $commands = $this->_db->store->get('commands', []);

            $onSave = $id ? isAke($commands, 'on_edit', false) : isAke($commands, 'on_create', false);

            if ($onSave) {
                if (is_callable($onSave)) {
                    $res = $onSave($this->_data);

                    if (is_array($res)) {
                        $this->_data = $res;
                    }
                }
            }

            $row = $this->_db->save($this->_data);

            if ($create) {
                $hook = isAke($this->_hooks, 'afterCreate', false);
            } else {
                $hook = isAke($this->_hooks, 'afterUpdate', false);
            }

            if ($hook) {
                call_user_func_array($hook, [$row]);
            }

            return $row;
        }

        private function schema()
        {
            $schema = $this->_db->store->get('schema', []);

            if (!empty($schema)) {
                foreach ($schema as $f => $i) {
                    $val        = isAke($this->_data, $f, null);
                    $required   = isAke($i, 'required', false);
                    $default    = isAke($i, 'default', null);
                    $maxLength  = isAke($i, 'maxLength', false);
                    $minLength  = isAke($i, 'minLength', false);
                    $unique     = isAke($i, 'unique', false);
                    $validate   = isAke($i, 'validate', false);
                    $type       = isAke($i, 'type', 'string');

                    if (!empty($val) && $type != 'array' && $type != 'object' && $type != 'bool' && $maxLength) {
                        $check = strlen($val) <= $maxLength;

                        if (!$check) {
                            throw new Exception("Field $f must have max $maxLength length.");
                        }
                    }

                    if (empty($val)  && $type != 'array' && $type != 'object' && $type != 'bool' && $minLength) {
                        throw new Exception("Field $f must have max $minLength length.");
                    }

                    if (!empty($val)  && $type != 'array' && $type != 'object' && $type != 'bool' && $minLength) {
                        $check = strlen($val) >= $maxLength;

                        if (!$check) {
                            throw new Exception("Field $f must have max $maxLength length.");
                        }
                    }

                    $this->_data[$f] = empty($val) ? $default : $val;

                    $val = empty($val) ? $default : $val;

                    if ($required && empty($val)) {
                        throw new Exception("Field $f is required to store this row.");
                    }

                    $funcCheck = 'is_' . $type;

                    $funcCheck = 'is_double' == $type ? 'is_float' : $funcCheck;

                    if (function_exists($funcCheck)) {
                        if ('is_float' == $funcCheck) {
                            if (is_int($val)) {
                                $val = floatval($val);
                            }
                        }

                        $check = $funcCheck($val);

                        if (!$check) {
                            throw new Exception("Field $f has wrong type. It must be '$type' and it is '" . gettype($val) . "'");
                        }
                    }

                    if ($validate) {
                        if (is_callable($validate)) {
                            $check = $validate($val);

                            if (!$check) {
                                throw new Exception("Field $f has wrong value [$val] to be stored.");
                            }
                        }
                    }

                    if ($unique) {
                        $check = $this->_db->where([$f, '=', $val])->cursor()->first(true);

                        if ($check) {
                            $actual = $this->toArray();
                            $exist = $check->toArray();

                            unset($actual['created_at']);
                            unset($actual['updated_at']);
                            unset($actual['id']);

                            unset($exist['created_at']);
                            unset($exist['updated_at']);
                            unset($exist['id']);

                            $same = sha1(serialize($actual)) == sha1(serialize($exist));

                            if (!$same) {
                                throw new Exception("Field $f must be unique.");
                            }
                        }
                    }
                }
            }
        }

        public function saveAndAttach($model, $atts = [])
        {
            $this->save();

            return $this->attach($model, $atts);
        }

        public function restore()
        {
            $id = isAke($this->_data, 'id', false);

            if (false !== $id) {
                $hook = isAke($this->_hooks, 'beforeRestore', false);

                if ($hook) {
                    call_user_func_array($hook, [$this]);
                }

                $commands = $this->_db->store->get('commands', []);

                $closure = isAke($commands, 'on_restore', false);

                if ($closure) {
                    if (is_callable($closure)) {
                        $res = $closure($this->_data);

                        if (is_array($res)) {
                            $this->_data = $res;
                        }
                    }
                }

                $this->schema();

                $row = $this->_db->save($this->_data);

                $hook = isAke($this->_hooks, 'afterRestore', false);

                if ($hook) {
                    call_user_func_array($hook, [$row]);
                }

                return $row;
            }

            return false;
        }

        public function insert()
        {
            $valid = true;

            $hook = isAke($this->_hooks, 'validate', false);

            if ($hook) {
                $valid = call_user_func_array($hook, [$this]);
            }

            if (true !== $valid) {
                throw new Exception("Thos model must be valid to be saved.");
            }

            $hook = isAke($this->_hooks, 'beforeCreate', false);

            if ($hook) {
                call_user_func_array($hook, [$this]);
            }

            $this->schema();

            $commands = $this->_db->store->get('commands', []);

            $closure = isAke($commands, 'on_create', false);

            if ($closure) {
                if (is_callable($closure)) {
                    $res = $closure($this->_data);

                    if (is_array($res)) {
                        $this->_data = $res;
                    }
                }
            }

            $row = $this->_db->insert($this->_data);

            $hook = isAke($this->_hooks, 'afterCreate', false);

            if ($hook) {
                call_user_func_array($hook, [$row]);
            }

            return $row;
        }

        public function insertGetId()
        {
            return $this->insert()->id;
        }

        public function saveGetId()
        {
            return $this->save()->id;
        }

        public function delete()
        {
            $id = isAke($this->_data, 'id', false);

            if (false !== $id) {
                $hook = isAke($this->_hooks, 'beforeDelete', false);

                if ($hook) {
                    call_user_func_array($hook, [$this]);
                }

                $commands = $this->_db->store->get('commands', []);

                $closure = isAke($commands, 'on_delete', false);

                if ($closure) {
                    if (is_callable($closure)) {
                        $res = $closure($this->_data);

                        if (is_array($res)) {
                            $this->_data = $res;
                        }
                    }
                }

                $res = $this->_db->delete((int) $id);

                $hook = isAke($this->_hooks, 'afterDelete', false);

                if ($hook) {
                    call_user_func_array($hook, [$this]);
                }

                return $res;
            }

            return false;
        }

        function deleteCascade(array $fields)
        {
            foreach ($fields as $field) {
                $val = isake($this->_data, $field, false);

                if (fnmatch('*_id', $field) && false !== $val) {
                    $row = bigDb(str_replace('_id', '', $field))->find($val);

                    if ($row) {
                        $row->delete();
                    }
                }
            }

            return $this->delete();
        }

        public function hydrate(array $data = [])
        {
            $data = empty($data) ? $_POST : $data;

            if (Arrays::isAssoc($data)) {
                foreach ($data as $k => $v) {
                    if ($k != 'id') {
                        if ('true' == $v) {
                            $v = true;
                        } elseif ('false' == $v) {
                            $v = false;
                        } elseif ('null' == $v) {
                            $v = null;
                        }

                        if (fnmatch('*_id', $k)) {
                            if (is_numeric($v)) {
                                $v = (int) $v;
                            } elseif (is_object($value)) {
                                $v = (int) $v->id;
                                $this->_data[str_replace('_id', '', $k)] = $v->toArray();
                            }
                        } else {
                            if (is_object($value)) {
                                $this->_data[$k . '_id'] = $v->id;
                                $value = $v->toArray();
                            }
                        }

                        $this->_data[$k] = $v;
                    }
                }
            }

            return $this;
        }

        public function id()
        {
            return isAke($this->_data, 'id', null);
        }

        public function exists()
        {
            return null !== isAke($this->_data, 'id', null);
        }

        public function duplicate()
        {
            $this->_data['copyrow'] = sha1(__file__ . time());

            unset($this->_data['id']);
            unset($this->_data['created_at']);
            unset($this->_data['updated_at']);
            unset($this->_data['deleted_at']);

            $commands = $this->_db->store->get('commands', []);

            $closure = isAke($commands, 'on_duplicate', false);

            if ($closure) {
                if (is_callable($closure)) {
                    $res = $closure($this->_data);

                    if (is_array($res)) {
                        $this->_data = $res;
                    }
                }
            }

            return $this->save();
        }

        public function assoc()
        {
            return $this->_data;
        }

        public function toArray()
        {
            return $this->_data;
        }

        public function toJson()
        {
            return json_encode($this->_data);
        }

        public function __tostring()
        {
            return json_encode($this->_data);
        }

        public function __invoke($json = false)
        {
            return $json ? $this->save()->toJson() : $this->save();
        }

        public function deleteSoft()
        {
            $this->_data['deleted_at'] = time();

            return $this->save();
        }

        public function db()
        {
            return $this->_db;
        }

        public function with($model)
        {
            $db = $model->_db->db;
            $table = $model->_db->table;

            if ($db == $this->_db->db) {
                $this->_data[$table . '_id'] = $model->id;
                $this->_data[$table] = $model->toArray();
            } else {
                $this->_data[$db . '_' . $table . '_id'] = $model->id;
                $this->_data[$db . '_' . $table] = $model->toArray();
            }

            return $this;
        }

        public function attach($model, $attributes = [])
        {
            $m = !is_array($model) ? $model : current($model);

            if (!isset($this->_data['id']) || empty($m->id)) {
                throw new Exception("Attach method requires a valid model.");
            }

            $mTable = $m->db()->table;

            $names = [$this->_db->table, $mTable];
            asort($names);
            $pivot = Inflector::lower('pivot' . implode('', $names));

            $db = Db::instance($this->_db->db, $pivot);

            if (is_array($model)) {
                foreach ($model as $mod) {
                    $id = (int) $mod->id;

                    $row = $mod->db()->find($id);

                    if ($row) {
                        $fieldAttach    = $mTable . '_id';
                        $fieldModel     = $this->_db->table . '_id';

                        $attach = $db->firstOrCreate([
                            $fieldAttach    => $id,
                            $fieldModel     => $this->_data['id']
                        ]);

                        if (!empty($attributes)) {
                            foreach ($attributes as $k => $v) {
                                $setter = setter($k);
                                $attach->$setter($v);
                            }

                            $attach->save();
                        }
                    }
                }
            } else {
                $id = (int) $model->id;
                $row = $model->db()->find($id);

                if ($row) {
                    $fieldAttach    = $mTable . '_id';
                    $fieldModel     = $this->_db->table . '_id';

                    $attach = $db->firstOrCreate([
                        $fieldAttach    => $id,
                        $fieldModel     => $this->_data['id']
                    ]);

                    if (!empty($attributes)) {
                        foreach ($attributes as $k => $v) {
                            $setter = setter($k);
                            $attach->$setter($v);
                        }

                        $attach->save();
                    }
                }
            }

            return $this;
        }

        public function detach($model)
        {
            if (!isset($this->_data['id'])) {
                throw new Exception("detach method requires a valid model.");
            }

            $m = !is_array($model) ? $model : current($model);

            if ($m instanceof Db) {
                $m = $m->model();
            }

            $all = false;

            if (empty($m->id)) {
                $all = true;
            }

            $mTable = $m->db()->table;

            $names = [$this->_db->table, $mTable];
            asort($names);
            $pivot = Inflector::lower('pivot' . implode('', $names));

            $db = Db::instance($this->_db->db, $pivot);

            if (is_array($model)) {
                foreach ($model as $mod) {
                    $id = (int) $mod->id;

                    $row = $mod->db()->find($id);

                    if ($row) {
                        $fieldAttach    = $mTable . '_id';
                        $fieldModel     = $this->_db->table . '_id';

                        $attach = $db->where([$fieldAttach , '=', (int) $id])
                        ->where([$fieldModel, '=', (int) $this->_data['id']])
                        ->first(true);

                        if ($attach) {
                            $attach->delete();
                        }
                    }
                }
            } else {
                if (false === $all) {
                    $id = (int) $model->id;
                    $row = $model->db()->find($id);

                    if ($row) {
                        $fieldAttach    = $mTable . '_id';
                        $fieldModel     = $this->_db->table . '_id';

                        $attach = $db->where([$fieldAttach , '=', (int) $id])
                        ->where([$fieldModel, '=', (int) $this->_data['id']])
                        ->first(true);

                        if ($attach) {
                            $attach->delete();
                        }
                    }
                } else {
                    $fieldModel = $this->_db->table . '_id';

                    $attachs = $db->where([$fieldModel, '=', (int) $this->_data['id']])
                    ->get(null, true);

                    if (!empty($attachs)) {
                        foreach ($attachs as $attach) {
                            $attach->delete();
                        }
                    }
                }
            }

            return $this;
        }

        public function log()
        {
            $ns = isset($this->_data['id']) ? 'row_' . $this->_data['id'] : null;

            return $this->_db->log($ns);
        }

        public function actual()
        {
            return $this;
        }

        public function initial($model = false)
        {
            return $model ? new self($this->_initial) : $this->_initial;
        }

        public function cancel()
        {
            $this->_data = $this->_initial;

            return $this;
        }

        public function isDirty()
        {
            return sha1(serialize($this->_data)) != sha1(serialize($this->_initial));
        }

        public function observer()
        {
            return new Observer($this);
        }

        public function pivot($model)
        {
            if ($model instanceof Db) {
                $model = $model->model();
            }

            $mTable = $model->db()->table;

            $names = [$this->_db->table, $mTable];

            asort($names);

            $pivot = Inflector::lower('pivot' . implode('', $names));

            return Db::instance($this->_db->db, $pivot);
        }

        public function pivots($model)
        {
            if (!isset($this->_data['id'])) {
                throw new Exception("pivots method requires a valid model.");
            }

            $fieldModel = $this->_db->table . '_id';

            return $this->pivot($model)->where([$fieldModel, '=', (int) $this->_data['id']]);
        }

        public function hasPivot($model)
        {
            if (!isset($this->_data['id'])) {
                throw new Exception("hasPivot method requires a valid model.");
            }

            if ($model instanceof Db) {
                $model = $model->model();
            }

            $fieldModel = $this->_db->table . '_id';

            $count = $this->pivot($model)->where([$fieldModel, '=', (int) $this->_data['id']])->count();

            return $count > 0 ? true : false;
        }

        public function getPivots($pivot)
        {
            return $this->getPivot($pivot, false);
        }

        public function getPivot($pivot, $first = true)
        {
            $res = $this->pivots($pivot);

            return $first ? $res->get()->first() : $res->get();
        }

        public function oneToOne($table)
        {
            if (!isset($this->_data['id'])) {
                throw new Exception("oneToOne method requires a valid model.");
            }

            $idFk = $table . '_id';

            if (!isset($this->_data[$idFk])) {
                throw new Exception("oneToOne method requires a valid model.");
            }

            $rowFk = Db::instance($this->_db->db, $table)->find($this->_data[$idFk]);

            return $rowFk ? true : false;
        }

        public function belongsToOne($table)
        {
            if (!isset($this->_data['id'])) {
                throw new Exception("belongsToOne method requires a valid model.");
            }

            $idFk = $table . '_id';

            if (!isset($this->_data[$idFk])) {
                throw new Exception("belongsToOne method requires a valid model.");
            }

            $rowFk = Db::instance($this->_db->db, $table)->find($this->_data[$idFk]);

            return $rowFk ? true : false;
        }

        public function belongsToMany($table)
        {
            $model = Db::instance($this->_db->db, $table)->model();

            $pivot = $this->pivot($model);

            return $this->getPivot($pivot, false)->count() > 0;
        }

        public function manyToMany($table)
        {
            $model = Db::instance($this->_db->db, $table)->model();

            $pivot = $this->pivot($model);

            return $this->getPivot($pivot, false);
        }

        public function oneToMany($table)
        {
            if (!isset($this->_data['id'])) {
                throw new Exception("belongsToOne method requires a valid model.");
            }

            $idFk = $this->_db->table . '_id';

            $dbFk = Db::instance($this->_db->db, $table);

            return $dbFk->where([$idFk, '=', $this->_data['id']])->get();
        }

        public function take($fk)
        {
            if (!isset($this->_data['id'])) {
                throw new Exception('id must be defined to use take.');
            }

            $db = fnmatch('*s', $fk) ? Db::instance($this->_db->db, substr($fk, 0, -1)) : Db::instance($this->_db->db, $fk);

            return $db->where([$this->_db->table . '_id', '=', (int) $this->_data['id']]);
        }

        public function incr($key, $by = 1)
        {
            $oldVal = isset($this->_data[$key]) ? $this->_data[$key] : 0;
            $newVal = $oldVal + $by;

            $this->_data[$key] = $newVal;

            return $this;
        }

        public function decr($key, $by = 1)
        {
            $oldVal = isset($this->_data[$key]) ? $this->_data[$key] : 1;
            $newVal = $oldVal - $by;

            $this->_data[$key] = $newVal;

            return $this;
        }

        public function through($t1, $t2)
        {
            $database = $this->_db->db;

            $db1 = Db::instance($database, $t1);

            $fk = $this->_db->table . '_id';

            $sub = $db1->where([$fk, '=', (int) $this->_data['id']])->cursor();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $t1 . '_id';

            return Db::instance($database, $t2)->where([$fk2, 'IN', implode(',', $ids)])->cursor()->toArray();
        }

        public function hasThrough($t1, $t2)
        {
            $database = $this->_db->db;

            $db1 = Db::instance($database, $t1);

            $fk = $this->_db->table . '_id';

            $sub = $db1->where([$fk, '=', (int) $this->_data['id']])->cursor();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $t1 . '_id';

            return Db::instance($database, $t2)->where([$fk2, 'IN', implode(',', $ids)])->cursor()->count() > 0 ? true : false;
        }

        public function countThrough($t1, $t2)
        {
            $database = $this->_db->db;

            $db1 = Db::instance($database, $t1);

            $fk = $this->_db->table . '_id';

            $sub = $db1->where([$fk, '=', (int) $this->_data['id']])->cursor();

            $ids = [];

            foreach ($sub as $row) {
                $ids[] = $row['id'];
            }

            $fk2 = $t1 . '_id';

            return Db::instance($database, $t2)->where([$fk2, 'IN', implode(',', $ids)])->cursor()->count();
        }

        public function create(array $data)
        {
            return new self($this->_db, $data);
        }

        public function createFromId($id)
        {
            $row = $this->_db->find($id);

            if ($row) {
                $row = $row->toArray();
                unset($row['id']);
                unset($row['created_at']);
                unset($row['updated_at']);

                return (new self($this->_db, $row))->save();
            }

            return $this;
        }

        public function createFromQuery($query)
        {
            $rows = null;

            if (!empty($query)) {
                $first = current($query);

                if (is_string($first)) {
                    $rows = $this->_db->where($query)->cursor();
                } elseif (is_array($first)) {
                    $rows = $this->_db->multiQuery($query)->cursor();
                }

                if ($rows) {
                    foreach ($rows as $row) {
                        unset($row['id']);
                        unset($row['created_at']);
                        unset($row['updated_at']);

                        $new = (new self($this->_db, $row))->save();
                    }
                }
            }

            return $this;
        }

        public function set(array $data)
        {
            foreach ($data as $key => $value) {
                if (fnmatch('*_id', $key)) {
                    if (is_numeric($value)) {
                        $value = (int) $value;
                    } elseif (is_object($value)) {
                        $value = (int) $value->id;
                        $this->_data[str_replace('_id', '', $key)] = $value->toArray();
                    }
                } else {
                    if (is_object($value)) {
                        $this->_data[$key . '_id'] = $value->id;
                        $value = $value->toArray();
                    }
                }

                $this->_data[$key] = $value;
            }

            $autosave = isAke($this->_data, 'autosave', false);

            return !$autosave ? $this : $this->save();
        }

        public function related()
        {
            $fields = func_get_args();

            foreach ($fields as $field) {
                if (fnmatch('*_*', $field)) {
                    list($db, $table) = explode('_', $field, 2);
                } else {
                    $table = $field;
                    $db = SITE_NAME;
                }

                $fid = isAke($this->_data, $field . '_id', false);

                if ($fid) {
                    $row = Db::instance($db, $table)->find((int) $fid, false);

                    $this->_data[$key] = $row;
                }
            }

            return $this;
        }

        public function custom(callable $closure)
        {
            return call_user_func_array($closure, [$this]);
        }

        public function fill(array $data)
        {
            return $this->hydrate($data);
        }

        public function fillAndSave(array $data)
        {
            return $this->hydrate($data)->save();
        }

        public function timestamps()
        {
            return [
                'created_at' => lib('time')->createFromTimestamp(isAke($this->_data, 'created_at', time())),
                'updated_at' => lib('time')->createFromTimestamp(isAke($this->_data, 'updated_at', time()))
            ];
        }

        public function updated()
        {
            return lib('time')->createFromTimestamp(isAke($this->_data, 'updated_at', time()));
        }

        public function created()
        {
            return lib('time')->createFromTimestamp(isAke($this->_data, 'updated_at', time()));
        }

        public function __destruct()
        {
            $methods = get_class_methods($this);

            if (in_array('autosave', $methods)) {
                $autosave = $this->autosave();

                if ($autosave) {
                    $this->save();
                }
            }
        }

        public function touch()
        {
            $this->_data['updated_at'] = time();

            return $this->save();
        }

        public function associate($model)
        {
            $db = $model->db();
            $field = $db->table . '_id';

            $this->_data[$field] = $model->id;
            $this->_data[$db->table] = $model->toArray();

            return $this;
        }

        public function dissociate($model)
        {
            $db = $model->db();
            $field = $db->table . '_id';

            unset($this->_data[$field]);
            unset($this->_data[$db->table]);

            return $this;
        }
    }
