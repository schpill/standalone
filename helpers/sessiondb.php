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

    class SessiondbLib implements \ArrayAccess
    {
        private $collection, $wheres = [];

        public function __construct($db, $table)
        {
            if (!isset($_SESSION['_collections'])) {
                $_SESSION['_collections'] = [];
                $_SESSION['_tuples'] = [];
                $_SESSION['_collections']["$db.$table"] = [];
                $_SESSION['_tuples']["$db.$table"] = [];
            }

            if (!is_array($_SESSION['_collections'])) {
                $_SESSION['_collections'] = [];
            }

            if (!isset($_SESSION['_collections']["$db.$table"])) {
                $_SESSION['_collections']["$db.$table"] = [];
            }

            if (!is_array($_SESSION['_collections']["$db.$table"])) {
                $_SESSION['_collections']["$db.$table"] = [];
            }

            if (!isset($_SESSION['_tuples'])) {
                $_SESSION['_tuples'] = [];
            }

            if (!is_array($_SESSION['_tuples'])) {
                $_SESSION['_tuples'] = [];
            }

            if (!isset($_SESSION['_tuples']["$db.$table"])) {
                $_SESSION['_tuples']["$db.$table"] = [];
            }

            if (!is_array($_SESSION['_tuples']["$db.$table"])) {
                $_SESSION['_tuples']["$db.$table"] = [];
            }

            $this->collection = "$db.$table";
        }

        public function save($obj)
        {
            $obj = (array) $obj;

            $id = isAke($obj, 'id', false);

            return $id ? $this->edit($id, $obj) : $this->add($obj);
        }

        public function add($data)
        {
            $exists = $this->exists($data);

            if (!$exists) {
                $k = count($_SESSION['_collections'][$this->collection]);
                $data['id'] = $k + 1;
                $_SESSION['_collections'][$this->collection][$k] = $data;

                return $this->model($data);
            }
        }

        public function edit($id, $data)
        {
            $k = $id - 1;

            $old = $_SESSION['_collections'][$this->collection][$k];

            unset($old['id']);

            $kt = sha1(serialize($old));

            unset($_SESSION['_tuples'][$this->collection][$kt]);

            $_SESSION['_collections'][$this->collection][$k] = $data;

            $tuple = $data;

            unset($tuple['id']);

            $kt = sha1(serialize($tuple));

            $_SESSION['_tuples'][$this->collection][$kt] = $tuple;

            return $this->model($data);
        }

        public function offsetSet($k, $v)
        {
            if (empty($k)) {
                $k = count($_SESSION['_collections'][$this->collection]);
            }

            $exists = $this->exists($v);

            if (!$exists) {
                $v['id'] = $k + 1;
                $_SESSION['_collections'][$this->collection][$k] = $v;
            }
        }

        public function makeId()
        {
            $val = count($_SESSION['_collections'][$this->collection]);

            return $val + 1;
        }

        public function exists($v)
        {
            $k = sha1(serialize($v));

            if (isset($_SESSION['_tuples'][$this->collection][$k])) {
                return $_SESSION['_tuples'][$this->collection][$k];
            } else {
                $_SESSION['_tuples'][$this->collection][$k] = $v;

                return false;
            }
        }

        public function offsetGet($k)
        {
            if (isset($_SESSION['_collections'][$this->collection][$k])) {
                $row = $_SESSION['_collections'][$this->collection][$k];

                $row['id'] = $k + 1;

                return $row;
            }

            return null;
        }

        public function delete($id)
        {
            $k = $id - 1;

            if (isset($_SESSION['_collections'][$this->collection][$k])) {
                $v  = $_SESSION['_collections'][$this->collection][$k];
                $kt = sha1(serialize($v));

                unset($_SESSION['_collections'][$this->collection][$k]);
                unset($_SESSION['_tuples'][$this->collection][$kt]);

                return true;
            }

            return false;
        }

        public function offsetUnset($k)
        {
            if (isset($_SESSION['_collections'][$this->collection][$k])) {
                $v = $_SESSION['_collections'][$this->collection][$k];
                $kt = sha1(serialize($v));

                unset($_SESSION['_collections'][$this->collection][$k]);
                unset($_SESSION['_tuples'][$this->collection][$kt]);
            }
        }

        public function offsetExists($k)
        {
            return isset($_SESSION['_collections'][$this->collection][$k]);
        }

        public function get()
        {
            $cursor = lib('myiterator', [$_SESSION['_collections'][$this->collection]]);

            if (!empty($this->wheres)) {
                foreach ($this->wheres as $wh) {
                    list($c, $o) = $wh;
                    list($f, $op, $v) = $c;

                    $cursor = $cursor->where(function ($row, $db) use ($f, $op, $v) {
                        $val = isAke($row, $f, false);

                        if ($v == 'null' || is_null($v) || empty($v)) {
                            if ($operator == 'IS') {
                                $check = empty($val) || is_null($val);
                            } elseif ($op == 'IS NOT' || $op == 'ISNOT' ) {
                                $check = !empty($val) || !is_null($val);
                            } else {
                                if ($value == '0') {
                                    $check = $db->compare($val, $op, $v);
                                }
                            }
                        } else {
                            $check  = $db->compare($val, $op, $v);
                        }

                        return $check;
                    });
                }
            }

            return $cursor;
        }

        public function count()
        {
            return $this->get()->count();
        }

        public function sum($field)
        {
            return $this->get()->sum($field);
        }

        public function min($field)
        {
            return $this->get()->min($field);
        }

        public function max($field)
        {
            return $this->get()->max($field);
        }

        public function avg($field)
        {
            return $this->get()->avg($field);
        }

        public function between($field, $min, $max)
        {
            return $this->get()->between($field, $min, $max);
        }

        public function where(array $condition, $op = 'AND')
        {
            $check = !isset($this->wheres[sha1(serialize(func_get_args()))]);

            if ($check) {
                $this->wheres[sha1(serialize(func_get_args()))] = [$condition, $op];
            }

            return $this;
        }

        public function model(array $data)
        {
            $obj = new \Stdclass;

            foreach ($data as $k => $v) {
                $obj->$k = $v;
            }

            $db = $this;

            $obj->save = function () use ($obj, $db) {
                return $db->save($obj);
            };

            $obj->delete = function () use ($obj, $db) {
                return $db->delete($obj->id);
            };

            return $obj;
        }
    }
