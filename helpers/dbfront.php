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

    use Dbredis\Caching as C;

    class DbfrontLib
    {
        private $db;

        public function __construct()
        {
            $this->db = C::instance('front.db');
        }

        public function set($key, $value, $ttl = 0)
        {
            $this->db->set($key, $value, $ttl);

            return true;
        }

        public function del($key)
        {
            $this->db->del($key);

            return true;
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->db, $m], $a);
        }
    }
