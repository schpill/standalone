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

    class MultidbCore
    {
        private $credentials = [], $db, $table, $query = [];

        public function connect($host, $user, $password)
        {
            $this->credentials = [
                'host'      => $host,
                'user'      => $user,
                'password'  => $password
            ];

            return $this;
        }

        public function db($db)
        {
            $this->db = $db;

            return $this;
        }

        public function table($table)
        {
            $this->table = $table;

            return $this;
        }

        public function from($table)
        {
            $this->table = $table;

            return $this;
        }

        public function __call($m, $a)
        {
            if (!isset($this->query[$m])) {
                $this->query[$m] = [];
            }

            $this->query[$m][] = $a;

            return $this;
        }
    }
