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

    namespace Mlite;

    use Countable;
    use Iterator;
    use SplFixedArray;
    use MliteArray;
    use Thin\Inflector;
    use Thin\Exception;
    use Thin\Save;
    use Thin\Now;
    use SQLite3;
    use ArrayIterator as AI;

    class Cursor implements Countable, Iterator
    {
        private $model,
        $closure,
        $age,
        $count,
        $table,
        $database,
        $store,
        $position = 0;

        public function __construct(Db $db, $closure = null, $model = false)
        {
            $this->query    = $db->query;
            $this->closure  = $closure;
            $this->store    = $db->store;
            $this->table    = $db->table;
            $this->database = $db->db;
            $this->store    = $db->store;

            $this->age = $db->getAge();

            unset($this->count);

            $this->cursor = $this->store->db->query($this->query);

            $this->model = $model;
        }

        public function __destruct()
        {
            $this->reset();
        }

        public function count($return = true)
        {
            if (!isset($this->count) || is_null($this->count)) {
                $i = 0;

                while($row = $this->cursor->fetchArray(SQLITE3_ASSOC)) {
                    $i++;
                }

                $this->count = $i;
            }

            return $return ? $this->count : $this;
        }

        public function getFieldValueById($field, $id)
        {
            $query = "SELECT $field FROM " . $this->table . " WHERE id = '$id'";
            $result = $this->store->db->query($query);

            while($data = $result->fetchArray(SQLITE3_ASSOC)) {
                return $data[$field];
            }

            return null;
        }

        public function getNext()
        {
            $row = $this->cursor->fetchArray(SQLITE3_ASSOC);

            if (is_callable($this->closure)) {
                $callable = $this->closure;
                $row = $callable($row);
            }

            return $this->model ? Db::instance($this->database, $this->table)->model($row) : $row;
        }

        public function getPrev()
        {
            $this->position--;
            $this->cursor->reset();

            $i = 0;

            while ($row = $this->cursor->fetchArray(SQLITE3_ASSOC)) {
                if ($i == $this->position - 1) {
                    break;
                }

                $i++;
            }

            return $this->getNext();
        }

        public function seek($pos = 0)
        {
            $this->position = $pos;
            $this->cursor->reset();

            $i = 0;

            while ($row = $this->cursor->fetchArray(SQLITE3_ASSOC)) {
                if ($i == $this->position - 1) {
                    break;
                }

                $i++;
            }

            return $this;
        }

        public function one($model = false)
        {
            return $this->seek()->current($model);
        }

        public function current($model = false)
        {
            $row = $this->cursor->fetchArray(SQLITE3_ASSOC);

            if (is_callable($this->closure)) {
                $callable = $this->closure;
                $row = $callable($row);
            }

            return $this->model ? Db::instance($this->database, $this->table)->model($row) : $row;
        }

        public function getIterator()
        {
            return $this->cursor;
        }

        private function setCached($key, $value)
        {
            $key = sha1($key . $this->database . $this->table);

            $this->store->set($key . '_' . $this->age, $value);
        }

        private function cached($key)
        {
            $key = sha1($key . $this->database . $this->table);

            $cached =  $this->store->get($key . '_' . $this->age);

            if ($cached) {
                return $cached;
            }

            return null;
        }

        public function toArray($ignore = false)
        {
            $collection = [];

            while ($row = $this->getNext()) {
                if ($ignore) {
                    if ($this->model) {
                        $row = $row->toArray();
                    }
                }

                $collection[] = $row;
            }

            return $collection;
        }

        public function toJson()
        {
            return json_encode($this->toArray(true));
        }

        public function fetch()
        {
            $row = $this->getNext();

            if ($row) {
                return $this->model ? Db::instance($this->database, $this->table)->model($row) : $row;
            }

            $this->reset();

            return false;
        }

        public function model()
        {
            $row = $this->getNext();

            if ($row) {
                $id = isAke($row, 'id', false);

                return false !== $id ? Db::instance($this->database, $this->table)->model($row) : false;
            }

            $this->reset();

            return false;
        }

        public function first()
        {
            $this->position = 0;

            $this->cursor->reset();

            $row = $this->getNext();

            $id = isAke($row, 'id', false);

            if (!$id) {
                return null;
            }

            $this->reset();

            if (!is_null($this->closure)) {
                if (is_callable($this->closure)) {
                    $callable = $this->closure;
                    $row = $callable($row);
                }
            }

            return $row;
        }

        public function last()
        {
            $this->cursor->reset();

            $lastRow = null;

            while ($row = $this->cursor->fetchArray(SQLITE3_ASSOC)) {
                if ($row) {
                    $lastRow = $row;
                }
            }

            $row = $lastRow;

            $id = isAke($row, 'id', false);

            if (!$id) {
                return null;
            }

            $this->reset();

            if (!is_null($this->closure)) {
                if (is_callable($this->closure)) {
                    $callable = $this->closure;
                    $row = $callable($row);
                }
            }

            return $row;
        }

        public function __call($m, $a)
        {
            return call_user_func_array([Db::instance($this->database, $this->table), $m], $a);
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
            return $this->position < $this->count();
        }

        public function update(array $data)
        {
            while ($row = $this->getNext()) {
                $row = !$this->model ? Db::instance($this->database, $this->table)->model($row) : $row;

                foreach ($data as $k => $v) {
                    $row->$k = $v;
                }

                $row->save();
            }

            return $this;
        }

        public function delete()
        {
            $cursor = $this->getIterator();

            while ($row = $this->getNext()) {
                $row = !$this->model ? Db::instance($this->database, $this->table)->model($row) : $row;

                $row->delete();
            }

            return $this;
        }

        public function pivot($table, $id)
        {
            return Db::instance($this->database, $table)->find($id, $this->model);
        }

        public function each(callable $closure)
        {
            $row = $this->getNext();

            if ($row) {
                return $closure($row);
            }

            $this->reset();

            return false;
        }

        public function __set($k, $v)
        {
            if ($k == 'cursor') {
                $nowKey = 'cursor.' . $this->database . '.' . $this->table;
                Now::set($nowKey, $v);
            } else {
                $this->$k = $v;
            }

            return $this;
        }

        public function __get($k)
        {
            if ($k == 'cursor') {
                $nowKey = 'cursor.' . $this->database . '.' . $this->table;

                return Now::get($nowKey);
            }

            return null;
        }
    }
