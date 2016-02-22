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

    class OctadbLib
    {
        private $wheres = [], $sortBy = ['created_at', 'ASC'], $selects = ['id'], $age = 0, $offset = 0, $limit = 0;

        public function __construct($host, $username, $password)
        {
            $this->host     = $host;
            $this->username = $username;
            $this->password = $password;
        }

        public function db($db = null)
        {
            if (is_null($db)) {
                return $this->db;
            }

            $this->db = $db;

            return $this;
        }

        public function table($table = null)
        {
            if (is_null($table)) {
                return $this->table;
            }

            $this->table = $table;

            return $this;
        }

        public function age()
        {
            $this->check();

            $response = $this->sender()->post($this->host, [
                'action'    => 'age',
                'token'     => $this->token,
                'db'        => $this->db,
                'table'     => $this->table
            ]);

            if (isset($response['token'])) {
                $this->token = $response['token'];
            }

            return $this->age = $response['message'];
        }

        public function limit($limit)
        {
            $this->check();

            $this->limit = (int) $limit;

            return $this;
        }

        public function take($offset, $limit)
        {
            $this->check();

            $this->offset   = (int) $offset;
            $this->limit    = (int) $limit;

            return $this;
        }

        public function select($what)
        {
            $this->check();

            if (is_string($what)) {
                if (!strlen($what)) {
                    return $this;
                }

                if (fnmatch('*,*', $what)) {
                    $what = explode(',', str_replace(' ', '', $what));
                } else {
                    $what = [$what];
                }
            }

            if (!is_array($what)) {
                return $this;
            }

            if (empty($what)) {
                return $this;
            }

            $this->selects = array_merge($this->selects, $what);

            return $this;
        }

        public function where($op)
        {
            $this->check();

            $op[] = 'and';

            $this->wheres[] = $op;

            return $this;
        }

        public function sortBy($field, $direction = 'ASC')
        {
            $this->check();

            $this->sortBy = [$field, $direction];

            return $this;
        }

        public function sortByDesc($field, $direction = 'DESC')
        {
            $this->check();

            $this->sortBy = [$field, $direction];

            return $this;
        }

        private function check()
        {
            if (!isset($this->db)) {
                throw new Exception("Please provide a db.");
            }

            if (!isset($this->table)) {
                throw new Exception("Please provide a table.");
            }

            if (!isset($this->token)) {
                $this->token();
            }
        }

        public function token()
        {
            $response = $this->sender()->post($this->host, [
                'action'    => 'token',
                'username'  => $this->username,
                'password'  => $this->password
            ]);

            if (!empty($response)) {
                $this->token = isAke($response, 'token', null);
            }

            return $this;
        }

        public function run()
        {
            $this->check();

            $response = $this->sender()->post($this->host, [
                'action'    => 'query',
                'token'     => $this->token,
                'limit'     => $this->limit,
                'offset'    => $this->offset,
                'wheres'    => $this->wheres,
                'selects'   => $this->selects,
                'sortBy'    => $this->sortBy,
                'db'        => $this->db,
                'table'     => $this->table
            ]);

            if (isset($response['token'])) {
                $this->token = $response['token'];
            }

            if (isset($response['age'])) {
                $this->age = $response['age'];
            }

            return isset($response['data']) ? $response['data'] : [];
        }

        /* run's aliases */
        public function execute()
        {
            return $this->run();
        }

        public function exec()
        {
            return $this->run();
        }

        public function get()
        {
            return $this->run();
        }
        /* end run's aliases */

        public function count()
        {
            $this->check();

            return count($this->run());
        }

        public function min($field = 'id')
        {
            $this->check();

            return coll($this->select($field)->run())->min($field);
        }

        public function find($id, $model = false)
        {
            $this->check();

            $row = coll($this->where(['id', '=', (int) $id])->run())->first();

            return $model ? System::Db()->instanciate($this->db, $this->table)->model($row) : $row;
        }

        public function findOrFail($id, $model = true)
        {
            $row = $this->find($id, false);

            if (!$row) {
                throw new Exception("The row $id does not exist.");
            } else {
                return $model ? System::Db()->instanciate($this->db, $this->table)->model($row) : $row;
            }
        }

        public function firstOrCreate($conditions)
        {
            foreach ($conditions as $k => $v) {
                if ($k == 'id' || fnmatch('*_id', $k)) {
                    $v = (int) $v;
                }

                $this->where([$k, '=', $v]);

                $conditions[$k] = $v;
            }

            $data = $this->run();

            $row = lib('array')->first($data, function ($k, $row) use ($conditions) {
                foreach ($conditions as $k => $v) {
                    if (fnmatch('*_id', $k) || $k == 'id') {
                        $v = (int) $v;
                    }

                    if ($row[$k] !== $v) {
                        return false;
                    }
                }

                return true;
            }, null);

            if (!$row) {
                return $this->save($conditions);
            } else {
                return $row;
            }
        }

        public function firstOrNew($conditions)
        {
            foreach ($conditions as $k => $v) {
                if ($k == 'id' || fnmatch('*_id', $k)) {
                    $v = (int) $v;
                }

                $this->where([$k, '=', $v]);

                $conditions[$k] = $v;
            }

            $data = $this->run();

            $row = lib('array')->first($data, function ($k, $row) use ($conditions) {
                foreach ($conditions as $k => $v) {
                    if ($row[$k] != $v) {
                        return false;
                    }
                }

                return true;
            }, null);

            if (!$row) {
                return $conditions;
            } else {
                return $row;
            }
        }

        public function save($data)
        {
            $this->check();

            $response = $this->sender()->post($this->host, [
                'action'    => 'save',
                'token'     => $this->token,
                'data'      => $data,
                'db'        => $this->db,
                'table'     => $this->table
            ]);

            if (isset($response['token'])) {
                $this->token = $response['token'];
            }

            if (isset($response['age'])) {
                $this->age = $response['age'];
            }

            if (isset($response['message'])) {
                return $response['message'];
            }

            return isset($response['row']) ? $response['row'] : [];
        }

        public function delete($id)
        {
            $this->check();

            $response = $this->sender()->post($this->host, [
                'action'    => 'delete',
                'token'     => $this->token,
                'id'        => (int) $id,
                'db'        => $this->db,
                'table'     => $this->table
            ]);

            if (isset($response['token'])) {
                $this->token = $response['token'];
            }

            if (isset($response['age'])) {
                $this->age = $response['age'];
            }

            return isset($response['result']) ? $response['result'] : false;
        }

        public function first($model = false)
        {
            $row = coll($this->run())->first();

            return $model ? loadModel(System::Db()->instanciate($this->db, $this->table), $row) : $row;
        }

        public function last($model = false)
        {
            $row = coll($this->run())->last();

            return $model ? loadModel(System::Db()->instanciate($this->db, $this->table), $row) : $row;
        }

        public function max($field = 'id')
        {
            $this->check();

            return coll($this->select($field)->run())->max($field);
        }

        public function avg($field = 'id')
        {
            $this->check();

            return coll($this->select($field)->run())->avg($field);
        }

        public function sum($field = 'id')
        {
            $this->check();

            return coll($this->select($field)->run())->sum($field);
        }

        public function groupBy($field = 'id')
        {
            $this->check();

            return coll($this->run())->groupBy($field)->toArray();
        }

        public function multisort($criteria)
        {
            $this->check();

            return coll($this->run())->multisort($criteria);
        }

        public function like($field, $value)
        {
            return $this->where($field, 'like', $value);
        }

        public function notLike($field, $value)
        {
            return $this->where($field, 'not like', $value);
        }

        public function findBy($field, $value)
        {
            return $this->where($field, '=', $value);
        }

        public function firstBy($field, $value)
        {
            return $this->where($field, '=', $value)->first();
        }

        public function lastBy($field, $value)
        {
            return $this->where($field, '=', $value)->last();
        }

        public function in($field, array $values)
        {
            return $this->where($field, 'in', $values);
        }

        public function notIn($field, array $values)
        {
            return $this->where($field, 'not in', $values);
        }

        public function isBetween($field, $min, $max)
        {
            return $this->where($field, 'between', [$min, $max]);
        }

        public function isNotBetween($field, $min, $max)
        {
            return $this->where($field, 'not between', [$min, $max]);
        }

        public function isNull($field)
        {
            return $this->where($field, 'is', 'null');
        }

        public function isNotNull($field)
        {
            return $this->where($field, 'is not', 'null');
        }

        public function __call($m, $a)
        {
            if ('or' == $m) {
                if (empty($this->wheres)) {
                    return call_user_func_array([$this, 'where'], $a);
                }

                $this->check();
                $a[] = 'or';
                $this->wheres[] = $a;
            }

            if (fnmatch('findBy*', $m) && strlen($m) > 'findBy') {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('findBy', '', $m)));

                if (strlen($field) > 0 && !empty($a)) {
                    return $this->where([$field, '=', current($a)])->run();
                }
            }

            if (fnmatch('countBy*', $m) && strlen($m) > 'countBy') {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('countBy', '', $m)));

                if (strlen($field) > 0 && !empty($a)) {
                    return $this->where([$field, '=', current($a)])->count();
                }
            }

            if (fnmatch('groupBy*', $m) && strlen($m) > 'groupBy') {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('groupBy', '', $m)));

                if (strlen($field) > 0) {
                    return $this->groupBy($field);
                }
            }

            if (fnmatch('findOneBy*', $m) && strlen($m) > 'findOneBy') {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('findOneBy', '', $m)));

                if (strlen($field) > 0 && !empty($a)) {
                    $model = false;

                    if (count($a) == 2) {
                        if (true === end($a)) {
                            $model = true;
                        }
                    }

                    return $this->where([$field, '=', current($a)])->first($model);
                }
            }

            if (fnmatch('firstBy*', $m) && strlen($m) > 'firstBy') {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('firstBy', '', $m)));

                if (strlen($field) > 0 && !empty($a)) {
                    $model = false;

                    if (count($a) == 2) {
                        if (true === end($a)) {
                            $model = true;
                        }
                    }

                    return $this->where([$field, '=', current($a)])->first($model);
                }
            }

            if (fnmatch('lastBy*', $m) && strlen($m) > 'lastBy') {
                $field = Inflector::uncamelize(Inflector::lower(str_replace('lastBy', '', $m)));

                if (strlen($field) > 0 && !empty($a)) {
                    $model = false;

                    if (count($a) == 2) {
                        if (true === end($a)) {
                            $model = true;
                        }
                    }

                    return $this->where([$field, '=', current($a)])->last($model);
                }
            }

            return $this;
        }

        public function sender()
        {
            if (!isset($this->sender)) {
                $this->sender = dyn(lib('curl'))->extend('post', function ($url, $postdata, $app) {
                    $response =  $app->getNative()->sendPostData($url, $postdata);

                    if (!$response) {
                        return [];
                    } else {
                        return json_decode($response, true);
                    }
                });
            }

            return $this->sender;
        }
    }
