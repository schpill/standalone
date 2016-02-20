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

    class BlizzLib
    {
        private $write = false;

        public function __construct($db = null, $table = null)
        {
            $this->db       = is_null($db) ? SITE_NAME : $db;
            $this->table    = is_null($table) ? 'core' : $table;

            $this->store    = lib('blizzstore', [$this]);
        }

        public function instanciate($db = null, $table = null)
        {
            return new self($db, $table);
        }

        public function age()
        {
            return filemtime($this->dir . DS . 'age.blizz');
        }

        public function db()
        {
            return $this->db;
        }

        public function table()
        {
            return $this->table;
        }

        public function store()
        {
            return $this->store;
        }
    }
