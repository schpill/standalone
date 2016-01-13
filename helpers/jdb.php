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
    use IteratorAggregate;

    class JdbLib
    {
        public function __construct($db = null, $table = null)
        {
            $this->db       = is_null($db) ? SITE_NAME : $db;
            $this->table    = is_null($table) ? 'core' : $table;
            $this->store    = Config::get('dir.flight.store');

            $this->db       = Inflector::urlize($this->db, '');
            $this->table    = Inflector::urlize($this->table, '');

            if (!$this->store) {
                throw new Exception("You must defined in config a dir store for JDB.");
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

            if (!strlen($id)) {
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
            $key = sha1(serialize($data));

            $tuple = $this->tuplesDir . DS . $key;

            if (!file_exists($tuple)) {
                $id = $this->nextId();
                $data['id'] = $id;

                $content = serialize($data);

                $file = $this->dir . DS . $id;

                File::put($file, $content);
                File::put($tuple, $id);
            }
        }

        public function update($id, $data)
        {
            $key = sha1(serialize($data));

            $tuple = $this->tuplesDir . DS . $key;

            $old = unserialize(File::read($this->dir . DS . $id));

            unset($old['id']);

            $keyOld     = sha1(serialize($old));
            $tupleOld   = $this->tuplesDir . DS . $keyOld;

            File::delete($tupleOld);
            File::delete($this->dir . DS . $id);

            if (!file_exists($tuple)) {
                $data['id'] = $id;

                $content = serialize($data);

                $file = $this->dir . DS . $id;

                File::put($file, $content);
                File::put($tuple, $id);
            }
        }

        public function delete($id)
        {
            if (is_integer($id)) {
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

        public function find($id)
        {
            if (is_integer($id)) {
                $file = $this->dir . DS . $id;

                if (file_exists($file)) {
                    return unserialize(File::read($this->dir . DS . $id));
                }
            }

            return null;
        }

        public function findOrFail($id)
        {
            $row = $this->find($id);

            if (!$row) {
                throw new Exception("The row $id dpes not exist.");
            }

            return $row;
        }

        public function all()
        {
            $collection = $this->readCache('all');

            if (!$collection) {
                $files = glob($this->dir . DS . '*');
                $collection = [];

                foreach ($files as $file) {
                    if (is_file($file)) {
                        $collection[] = unserialize(File::read($file));
                    }
                }

                $this->putInCache('all', $collection);
            }

            return $collection;
        }

        public function __call($m, $a)
        {
            $data = isset($this->res) && is_object($this->res) ? array_values($this->res->toArray()) : $this->all();

            $i = coll($data);

            $this->res = call_user_func_array([$i, $m], $a);

            return is_object($this->res) ? $this : $this->res;
        }

        public function get()
        {
            return is_object($this->res) ? array_values($this->res->toArray()) : $this->res;
        }

        private function nextId()
        {
            return sha1(Utils::UUID() . Utils::token() . time());
        }

        public function readCache($key)
        {
            $age = filemtime($this->dir);
            $file = $this->cacheDir . DS . $key;

            if (file_exists($file) && filemtime($file) >= $age) {
                return unserialize(File::read($file));
            }

            File::delete($file);

            return null;
        }

        public function putInCache($key, $data)
        {
            $file = $this->cacheDir . DS . $key;

            File::delete($file);

            File::put($file, serialize($data));
        }

        public function model(array $data)
        {
            return new JdbModel($this, $data);
        }
    }

    class JdbModel
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

        public function __call($m, $a)
        {
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

