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
        private $wheres = [], $sortBy = ['created_at', 'ASC'], $selects = ['id'];

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

            return $response['message'];
        }

        public function select($what)
        {
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
                'wheres'    => $this->wheres,
                'selects'   => $this->selects,
                'db'        => $this->db,
                'table'     => $this->table
            ]);

            if (isset($response['token'])) {
                $this->token = $response['token'];
            }
            wdd($response);

            return $response['data'];
        }

        public function count()
        {
            $this->check();

            $response = $this->sender()->post($this->host, [
                'action'    => 'count',
                'wheres'    => $this->wheres,
                'db'        => $this->db,
                'table'     => $this->table
            ]);

            if (isset($response['token'])) {
                $this->token = $response['token'];
            }

            return $response['data'];
        }

        public function min()
        {
            $this->check();

            $response = $this->sender()->post($this->host, [
                'action'    => 'min',
                'wheres'    => $this->wheres,
                'db'        => $this->db,
                'table'     => $this->table
            ]);

            if (isset($response['token'])) {
                $this->token = $response['token'];
            }

            return $response['data'];
        }

        public function max()
        {
            $this->check();

            $response = $this->sender()->post($this->host, [
                'action'    => 'max',
                'wheres'    => $this->wheres,
                'db'        => $this->db,
                'table'     => $this->table
            ]);

            if (isset($response['token'])) {
                $this->token = $response['token'];
            }

            return $response['data'];
        }

        public function avg()
        {
            $this->check();

            $response = $this->sender()->post($this->host, [
                'action'    => 'avg',
                'wheres'    => $this->wheres,
                'db'        => $this->db,
                'table'     => $this->table
            ]);

            if (isset($response['token'])) {
                $this->token = $response['token'];
            }

            return $response['data'];
        }

        public function sum()
        {
            $this->check();

            $response = $this->sender()->post($this->host, [
                'action'    => 'sum',
                'wheres'    => $this->wheres,
                'db'        => $this->db,
                'table'     => $this->table
            ]);

            if (isset($response['token'])) {
                $this->token = $response['token'];
            }

            return $response['data'];
        }

        public function groupBy()
        {
            $this->check();

            $response = $this->sender()->post($this->host, [
                'action'    => 'groupBy',
                'wheres'    => $this->wheres,
                'db'        => $this->db,
                'table'     => $this->table
            ]);

            if (isset($response['token'])) {
                $this->token = $response['token'];
            }

            return $response['data'];
        }

        public function __call($m, $a)
        {
            if ('or' == $m) {
                $this->check();
                $a[] = 'or';
                $this->wheres[] = $a;

                return $this;
            }
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
