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
