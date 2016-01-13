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

    use Closure;
    use ArrayObject;
    use ArrayAccess;
    use Countable;
    use Iterator;
    use IteratorAggregate;

    class FlightLib
    {
        public function __construct($db = null, $table = null)
        {
            $this->db       = is_null($db) ? SITE_NAME : $db;
            $this->table    = is_null($table) ? 'core' : $table;
            $this->store    = Config::get('dir.flight.store');

            $this->db       = Inflector::urlize($this->db, '');
            $this->table    = Inflector::urlize($this->table, '');

            if (!$this->store) {
                throw new Exception("You must defined in config a dir store for flight DB.");
            }

            if (!is_dir($this->store)) {
                File::mkdir($this->store);
            }

            $this->dir = $this->store . DS . $this->db;

            if (!is_dir($this->dir)) {
                File::mkdir($this->dir);
            }

            $this->ids = $this->dir . DS . 'ids.' . $this->table;

            if (!file_exists($this->ids)) {
                File::put($this->ids, 1);
            }

            $this->dir .= DS . $this->table;

            if (!is_dir($this->dir)) {
                File::mkdir($this->dir);
            }

            $this->age = filemtime($this->dir);

            $this->tuplesDir = $this->dir . DS . 'tuples';

            if (!is_dir($this->tuplesDir)) {
                File::mkdir($this->tuplesDir);
            }

            $this->cacheDir = $this->dir . DS . 'cache';

            if (!is_dir($this->cacheDir)) {
                File::mkdir($this->cacheDir);
            }

            $this->indicesDir = $this->dir . DS . 'indices';

            if (!is_dir($this->indicesDir)) {
                File::mkdir($this->indicesDir);
            }

            $this->relationsDir = $this->dir . DS . 'relations';

            if (!is_dir($this->relationsDir)) {
                File::mkdir($this->relationsDir);
            }
        }

        public function getAge()
        {
            return filemtime($this->dir);
        }

        public function save($data)
        {
            $id = isAke($data, 'id', null);

            unset($data['id']);
            unset($data['_db']);

            touch($this->dir, time());

            if (!strlen($id) || !is_numeric($id)) {
                return $this->insert($data);
            } else {
                if (file_exists($this->dir . DS . $id)) {
                    return $this->update($id, $data);
                } else {
                    return $this->insert($data);
                }
            }
        }

        public function insert($data)
        {
            unset($data['updated_at']);
            unset($data['created_at']);

            $key = sha1(serialize($data));

            $tuple = $this->tuplesDir . DS . $key;

            if (!file_exists($tuple)) {
                $id = $this->nextId();
                $data['id']         = $id;
                $data['updated_at'] = time();
                $data['created_at'] = time();

                $content = serialize($data);

                $file = $this->dir . DS . $id;

                File::put($file, $content);
                File::put($tuple, $id);
            }
        }

        public function update($id, $data)
        {
            $ca = isAke($data, 'created_at', filemtime($this->dir . DS . $id));
            $ua = isAke($data, 'updated_at', filemtime($this->dir . DS . $id));
            unset($data['updated_at']);
            unset($data['created_at']);

            $key = sha1(serialize($data));

            $tuple = $this->tuplesDir . DS . $key;

            $old = unserialize(File::read($this->dir . DS . $id));

            unset($old['updated_at']);
            unset($old['created_at']);
            unset($old['id']);

            $keyOld     = sha1(serialize($old));
            $tupleOld   = $this->tuplesDir . DS . $keyOld;

            File::delete($tupleOld);
            File::delete($this->dir . DS . $id);

            if (!file_exists($tuple)) {
                $data['id'] = $id;
                $data['created_at'] = $ca;
                $data['updated_at'] = time();

                $content = serialize($data);

                $file = $this->dir . DS . $id;

                File::put($file, $content);
                File::put($tuple, $id);
            }
        }

        public function delete($id)
        {
            if (is_integer($id)) {
                touch($this->dir, time());

                $file = $this->dir . DS . $id;

                if (file_exists($file)) {
                    $old = unserialize(File::read($this->dir . DS . $id));

                    unset($old['updated_at']);
                    unset($old['created_at']);
                    unset($old['id']);

                    $keyOld     = sha1(serialize($old));
                    $tupleOld   = $this->tuplesDir . DS . $keyOld;

                    File::delete($tupleOld);
                    File::delete($this->dir . DS . $id);

                    return true;
                }
            }

            return false;
        }

        public function find($id, $model = false)
        {
            if (is_integer($id)) {
                $file = $this->dir . DS . $id;

                if (file_exists($file)) {
                    return $model ? $this->model(unserialize(File::read($this->dir . DS . $id))) : unserialize(File::read($this->dir . DS . $id));
                }
            }

            return null;
        }

        public function findOrFail($id, $model = false)
        {
            $row = $this->find($id, $model);

            if (!$row) {
                throw new Exception("The row $id does not exist.");
            }

            return $row;
        }

        public function all()
        {
            $k = sha1($this->dir . '.all');

            return ageCache($k, function () {
                $files = glob($this->dir . DS . '*');
                $collection = [];

                foreach ($files as $file) {
                    if (is_file($file)) {
                        $collection[] = unserialize(File::read($file));
                    }
                }

                return $collection;
            }, filemtime($this->dir));
        }

        public function __call($m, $a)
        {
            $data = isset($this->res) && is_object($this->res) ? array_values($this->res->toArray()) : $this->all();

            $i = coll($data);

            $this->res = call_user_func_array([$i, $m], $a);

            return is_object($this->res) ? $this : $this->res;
        }

        public function get($model = false)
        {
            if (!isset($this->res)) {
                $this->res = coll($this->all());
            }

            if (is_object($this->res)) {
                $res = lib('array')->fixed(array_values($this->res->fetch('id')->toArray()));
                unset($this->res);
                unset($this->db);
                unset($this->store);
                unset($this->ids);
                unset($this->tuplesDir);
                unset($this->cacheDir);
                unset($this->indicesDir);
                unset($this->relationsDir);

                return new FlightCursor($this, $res, $model);
            }

            return $this->res;
        }

        private function nextId()
        {
            $id = File::read($this->ids);
            File::delete($this->ids);
            File::put($this->ids, $id + 1);

            return (int) $id;
        }

        public function model(array $data)
        {
            return new FlightModel($this, $data);
        }
    }

    class FlightCursor implements Countable, Iterator
    {
        private $resource,
        $model,
        $closure,
        $age,
        $count,
        $db,
        $cursor,
        $selects,
        $position = 0;

        public function __construct($db, $data, $model = false)
        {
            $this->makeResource($data);
            $this->model    = $model;
            $this->db       = $db;
            $this->age      = $db->age;
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
            return unserialize(File::read($this->db->dir . DS . $id));
        }

        public function getFieldValueById($field, $id, $d = null)
        {
            return isAke(unserialize(File::read($this->db->dir . DS . $id)), $field, $d);
        }

        public function getNext()
        {
            if (isset($this->cursor[$this->position])) {
                $row = $this->getRow($this->cursor[$this->position]);

                $this->position++;

                if (isset($this->closure)) {
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

                if (isset($this->closure)) {
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

        public function one()
        {
            return $this->seek()->current();
        }

        public function current()
        {
            $cursor = $this->getIterator();

            if (isset($cursor[$this->position])) {
                if (isset($this->selects)) {
                    $row = [];
                    $row['id'] = $cursor[$this->position];

                    foreach ($this->selects as $field) {
                        $row[$field] = $this->getFieldValueById($field, $cursor[$this->position]);
                    }

                    if (isset($this->closure)) {
                        if (is_callable($this->closure)) {
                            $callable = $this->closure;
                            $row = $callable($row);
                        }
                    }

                    return $this->model ? $this->db->model($row) : $row;
                } else {
                    $row = $this->getRow($cursor[$this->position]);

                    if (isset($this->closure)) {
                        if (is_callable($this->closure)) {
                            $callable = $this->closure;
                            $row = $callable($row);
                        }
                    }

                    return $this->model ? $this->db->model($row) : $row;
                }
            }

            return false;
        }

        public function toArray()
        {
            $cursor = $this->getIterator();
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            if (!isset($this->selects)) {
                return \SplFixedArray::fromArray(array_map(function ($row) {
                    $row = $this->getRow($row);

                    if (isset($this->closure)) {
                        if (is_callable($this->closure)) {
                            $callable = $this->closure;
                            $row = $callable($row);
                        }
                    }

                    return $row;
                }, $cursor));
            } else {
                $fields = $this->selects;

                return \SplFixedArray::fromArray(array_map(function ($id) use ($fields) {
                    $row = [];
                    $row['id'] = $id;

                    foreach ($fields as $field) {
                        $row[$field] = $this->getFieldValueById($field, $id);
                    }

                    if (isset($this->closure)) {
                        if (is_callable($this->closure)) {
                            $callable = $this->closure;
                            $row = $callable($row);
                        }
                    }

                    return $row;
                }, $cursor));
            }
        }

        public function toJson()
        {
            return json_encode($this->toArray());
        }

        public function fetch()
        {
            $row = $this->getNext();

            if ($row) {
                return $this->model ? $this->db->model($row) : $row;
            }

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

                $id = isAke($row, 'id', false);

                if (!$id) {
                    return null;
                }

                if (isset($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $this->model ? $this->db->model($row) : $row;
            }

            return null;
        }

        public function last()
        {
            $this->position = $this->count() - 1;
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

                if (isset($this->closure)) {
                    if (is_callable($this->closure)) {
                        $callable = $this->closure;
                        $row = $callable($row);
                    }
                }

                return $this->model ? $this->db->model($row) : $row;
            }

            return null;
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

        private function makeResource($cursor)
        {
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            $this->resource = lib('array')->makeResource($cursor);
        }

        public function getIterator()
        {
            $cursor = lib('array')->makeFromResource($this->resource);
            $cursor = is_array($cursor) ? $cursor : iterator_to_array($cursor);

            return \SplFixedArray::fromArray($cursor);
        }

        public function each(callable $closure)
        {
            $row = $this->getNext();

            if ($row) {
                return $closure($row);
            }

            return false;
        }
    }

    class FlightQuery
    {

    }

    class FlightModel
    {
        public function __construct($db, array $data = [])
        {
            $this->_db = $db;

            foreach ($data as $k => $v) {
                $this->$k = $v;
            }
        }

        public function __set($k, $v)
        {
            $this->$k = $v;
        }

        public function __get($k)
        {
            return isset($this->$k) ? $this->$k : null;
        }

        public function __isset($k)
        {
            return isset($this->$k);
        }

        public function __unset($k)
        {
            unset($this->$k);
        }

        public function fn($m, callable $c)
        {
            Now::set(str_replace(DS, '.', $this->_db->dir) . '.' . $m, $c);

            return $this;
        }

        public function __call($m, $a)
        {
            $c = Now::get(str_replace(DS, '.', $this->_db->dir) . '.' . $m);

            if ($c && is_callable($c)) {
                $a[] = $this;

                return call_user_func_array($c, $a);
            } else {
                if (fnmatch('set*', $m)) {
                    $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, strlen('set'))));
                    $field              = Inflector::lower($uncamelizeMethod);

                    if (!empty($a)) {
                        $val = current($a);
                    } else {
                        $val = null;
                    }

                    $this->$field = $val;

                    return $this;
                } elseif (fnmatch('get*', $m)) {
                    $uncamelizeMethod   = Inflector::uncamelize(lcfirst(substr($func, strlen('set'))));
                    $field              = Inflector::lower($uncamelizeMethod);

                    $default = count($a) == 1 ? current($a) : null;

                    return isset($this->$field) ? $this->$field : $default;
                } else {
                    return call_user_func_array([$this->_db], $a);
                }
            }
        }

        public function save()
        {
            $tab = (array) $this;
            unset($tab['_db']);

            return $this->_db->save($tab);
        }

        public function delete()
        {
            return $this->_db->delete($this->id);
        }
    }

    class FlightSchema
    {
        /**
         * @var array
         */
        protected $items = [];

        /**
         * @var array
         */
        protected $rules = [];

        /**
         * @var array
         */
        protected $nested = [];

        /**
         * @var array
         */
        protected $dynamic = [];

        /**
         * @var array
         */
        protected $filter = ['validation' => true];

        /**
         * Constructor.
         *
         * @param array $serialized  Serialized content if available.
         */
        public function __construct(array $serialized = null)
        {
            if ($serialized) {
                $this->items    = (array) $serialized['items'];
                $this->rules    = (array) $serialized['rules'];
                $this->nested   = (array) $serialized['nested'];
                $this->dynamic  = (array) $serialized['dynamic'];
                $this->filter   = (array) $serialized['filter'];
            }
        }

        /**
         * Initialize FlightSchema with its dynamic fields.
         *
         * @return $this
         */
        public function init()
        {
            foreach ($this->dynamic as $key => $data) {
                $field = &$this->items[$key];

                foreach ($data as $property => $call) {
                    $func   = $call['function'];
                    $value  = $call['params'];

                    list($o, $f) = preg_split('/::/', $func);

                    if (!$f && function_exists($o)) {
                        $data = call_user_func_array(
                            $o,
                            $value
                        );
                    } elseif ($f && method_exists($o, $f)) {
                        $data = call_user_func_array(
                            [$o, $f],
                            $value
                        );
                    }

                    // If function returns a value,
                    if (isset($data)) {
                        if (isset($field[$property]) && is_array($field[$property]) && is_array($data)) {
                            // Combine field and @data-field together.
                            $field[$property] += $data;
                        } else {
                            // Or create/replace field with @data-field.
                            $field[$property] = $data;
                        }
                    }
                }
            }

            return $this;
        }

        /**
         * Set filter for inherited properties.
         *
         * @param array $filter     List of field names to be inherited.
         */
        public function setFilter(array $filter)
        {
            $this->filter = array_flip($filter);
        }

        /**
         * Get value by using dot notation for nested arrays/objects.
         *
         * @example $value = $data->get('this.is.my.nested.variable');
         *
         * @param string  $name       Dot separated path to the requested value.
         * @param mixed   $default    Default value (or null).
         * @param string  $separator  Separator, defaults to '.'
         *
         * @return mixed  Value.
         */
        public function get($name, $default = null, $separator = '.')
        {
            $name = $separator != '.' ? strtr($name, $separator, '.') : $name;

            return isset($this->items[$name]) ? $this->items[$name] : $default;
        }

        /**
         * Set value by using dot notation for nested arrays/objects.
         *
         * @example $value = $data->set('this.is.my.nested.variable', $newField);
         *
         * @param string  $name       Dot separated path to the requested value.
         * @param mixed   $value      New value.
         * @param string  $separator  Separator, defaults to '.'
         */
        public function set($name, $value, $separator = '.')
        {
            $name = $separator != '.' ? strtr($name, $separator, '.') : $name;

            $this->items[$name] = $value;
            $this->addProperty($name);
        }

        /**
         * Define value by using dot notation for nested arrays/objects.
         *
         * @example $value = $data->set('this.is.my.nested.variable', true);
         *
         * @param string  $name       Dot separated path to the requested value.
         * @param mixed   $value      New value.
         * @param string  $separator  Separator, defaults to '.'
         */
        public function def($name, $value, $separator = '.')
        {
            $this->set($name, $this->get($name, $value, $separator), $separator);
        }

        /**
         * Convert object into an array.
         *
         * @return array
         */
        public function toArray()
        {
            return ['items' => $this->items, 'rules' => $this->rules, 'nested' => $this->nested, 'dynamic' => $this->dynamic, 'filter' => $this->filter];
        }

        /**
         * Get nested structure containing default values defined in the FlightSchema.
         *
         * Fields without default value are ignored in the list.
         *
         * @return array
         */
        public function getDefaults()
        {
            return $this->buildDefaults($this->nested);
        }

        /**
         * Embed an array to the blueprint.
         *
         * @param $name
         * @param array $value
         * @param string $separator
         * @return $this
         */
        public function embed($name, array $value, $separator = '.')
        {
            if (isset($value['rules'])) {
                $this->rules = array_merge($this->rules, $value['rules']);
            }

            if (!isset($value['form']['fields']) || !is_array($value['form']['fields'])) {
                return $this;
            }

            $prefix = $name ? ($separator != '.' ? strtr($name, $separator, '.') : $name) . '.' : '';
            $params = array_intersect_key($this->filter, $value);

            $this->parseFormFields($value['form']['fields'], $params, $prefix);

            return $this;
        }

        /**
         * Merge two arrays by using FlightSchema.
         *
         * @param  array $data1
         * @param  array $data2
         * @param  string $name         Optional
         * @param  string $separator    Optional
         * @return array
         */
        public function mergeData(array $data1, array $data2, $name = null, $separator = '.')
        {
            $nested = $this->getProperty($name, $separator);

            return $this->mergeArrays($data1, $data2, $nested);
        }

        /**
         * @param array $nested
         * @return array
         */
        protected function buildDefaults(array &$nested)
        {
            $defaults = [];

            foreach ($nested as $key => $value) {
                if ($key === '*') {
                    // TODO: Add support for adding defaults to collections.
                    continue;
                }

                if (is_array($value)) {
                    // Recursively fetch the items.
                    $list = $this->buildDefaults($value);

                    // Only return defaults if there are any.
                    if (!empty($list)) {
                        $defaults[$key] = $list;
                    }
                } else {
                    // We hit a field; get default from it if it exists.
                    $item = $this->get($value);

                    // Only return default value if it exists.
                    if (isset($item['default'])) {
                        $defaults[$key] = $item['default'];
                    }
                }
            }

            return $defaults;
        }

        /**
         * @param array $data1
         * @param array $data2
         * @param array $rules
         * @return array
         * @internal
         */
        protected function mergeArrays(array $data1, array $data2, array $rules)
        {
            foreach ($data2 as $key => $field) {
                $val    = isset($rules[$key]) ? $rules[$key]        : null;
                $rule   = is_string($val)     ? $this->items[$val]  : null;

                if ($rule && $rule['type'] === '_parent' || (array_key_exists($key, $data1) && is_array($data1[$key]) && is_array($field) && is_array($val) && !isset($val['*']))) {
                    // Array has been defined in FlightSchema and is not a collection of items.
                    $data1[$key] = $this->mergeArrays($data1[$key], $field, $val);
                } else {
                    // Otherwise just take value from the data2.
                    $data1[$key] = $field;
                }
            }

            return $data1;
        }

        /**
         * Gets all field definitions from the FlightSchema.
         *
         * @param array $fields
         * @param array $params
         * @param string $prefix
         * @param string $parent
         * @internal
         */
        protected function parseFormFields(array &$fields, array $params, $prefix = '', $parent = '')
        {
            // Go though all the fields in current level.
            foreach ($fields as $key => &$field) {
                // Set name from the array key.
                if ($key && $key[0] == '.') {
                    $key = ($parent ?: rtrim($prefix, '.')) . $key;
                } else {
                    $key = $prefix . $key;
                }
                $field['name'] = $key;
                $field += $params;

                if (isset($field['fields'])) {
                    $isArray = !empty($field['array']);

                    // Recursively get all the nested fields.
                    $newParams = array_intersect_key($this->filter, $field);
                    $this->parseFormFields($field['fields'], $newParams, $prefix, $key . ($isArray ? '.*': ''));
                } else {
                    // Add rule.
                    $path = explode('.', $key);
                    array_pop($path);
                    $parent = '';

                    foreach ($path as $part) {
                        $parent .= ($parent ? '.' : '') . $part;
                        if (!isset($this->items[$parent])) {
                            $this->items[$parent] = ['type' => '_parent', 'name' => $parent];
                        }
                    }

                    $this->items[$key] = &$field;
                    $this->addProperty($key);

                    if (!empty($field['data'])) {
                        $this->dynamic[$key] = $field['data'];
                    }

                    foreach ($field as $name => $value) {
                        if (substr($name, 0, 6) == '@data-') {
                            $property = substr($name, 6);

                            if (is_array($value)) {
                                $func = array_shift($value);
                            } else {
                                $func = $value;
                                $value = [];
                            }

                            $this->dynamic[$key][$property] = ['function' => $func, 'params' => $value];
                        }
                    }

                    // Initialize predefined validation rule.
                    if (isset($field['validate']['rule'])) {
                        $field['validate'] += $this->getRule($field['validate']['rule']);
                    }
                }
            }
        }

        /**
         * Get property from the definition.
         *
         * @param  string  $path  Comma separated path to the property.
         * @param  string  $separator
         * @return array
         * @internal
         */
        public function getProperty($path = null, $separator = '.')
        {
            if (!$path) {
                return $this->nested;
            }

            $parts  = explode($separator, $path);
            $item   = array_pop($parts);

            $nested = $this->nested;

            foreach ($parts as $part) {
                if (!isset($nested[$part])) {
                    return [];
                }

                $nested = $nested[$part];
            }

            return isset($nested[$item]) ? $nested[$item] : [];
        }

        /**
         * Add property to the definition.
         *
         * @param  string  $path  Comma separated path to the property.
         * @internal
         */
        protected function addProperty($path)
        {
            $parts = explode('.', $path);
            $item = array_pop($parts);

            $nested = &$this->nested;

            foreach ($parts as $part) {
                if (!isset($nested[$part])) {
                    $nested[$part] = [];
                }

                $nested = &$nested[$part];
            }

            if (!isset($nested[$item])) {
                $nested[$item] = $path;
            }
        }

        /**
         * @param $rule
         * @return array
         * @internal
         */
        protected function getRule($rule)
        {
            if (isset($this->rules[$rule]) && is_array($this->rules[$rule])) {
                return $this->rules[$rule];
            }

            return [];
        }
    }
