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

    namespace Dbredis;

    use Thin\Exception;

    class Query
    {
        private $db;

        public function from($from)
        {
            if (fnmatch('*::*', $from)) {
                list($database, $table) = explode('::', $from, 2);
                $this->db = Db::instance($database, $table);
            } else {
                $this->db = Db::instance(SITE_NAME, $from);
            }

            return $this;
        }

        public static function instance($from)
        {
            if (fnmatch('*::*', $from)) {
                list($database, $table) = explode('::', $from, 2);
                $instance = Db::instance($database, $table);
            } else {
                $instance = Db::instance(SITE_NAME, $from);
            }

            return $instance;
        }

        public static function __callStatic($method, $args)
        {
            if (!isset(self::$staticDb)) {
                throw new Exception('You must define an instance database.');
            }

            return call_user_func_array([self::$staticDb, $method], $args);
        }

        public function __call($method, $args)
        {
            if (!isset($this->db)) {
                throw new Exception('You must define a from database.');
            }

            return call_user_func_array([$this->db, $method], $args);
        }
    }
