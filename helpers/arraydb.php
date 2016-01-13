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

    class ArraydbLib
    {
        private $db;

        public function __construct(array $collection)
        {
            $table = 'array' . uniqid();

            $db = Inflector::camelize('temporary_' . $table);

            $this->db = Blazz::$db();

            foreach ($collection as $row) {
                $this->db->insertWithId($row);
            }
        }

        public function __destruct()
        {
            $this->db->drop();
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->db, $m], $a);
        }
    }
