<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2013 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */
    namespace Thin;

    class FlatLib
    {
        private $file, $db, $table;

        public function __construct($db = null, $table = null)
        {
            $this->db       = is_null($db) ? SITE_NAME : $db;
            $this->table    = is_null($table) ? 'core' : $table;

            $dir = Config::get('dir.flat.store', '/tmp');

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $dir .= DS . Inflector::urlize(Inflector::uncamelize($this->db));

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $this->file = $dir . DS . Inflector::urlize(Inflector::uncamelize($this->table)) . '.flat';

            if (!file_exists($this->file)) {
                File::put($this->file, serialize([]));
            }

            Now::set('flat.collection.' . $this->db . '.' . $this->table, lib('sessy', [$this->db, $this->table, unserialize(File::read($this->file))]));
        }

        public function collection()
        {
            return Now::get('flat.collection.' . $this->db . '.' . $this->table);
        }

        public function __destruct()
        {
            if (true === $this->collection()->write) {
                File::delete($this->file);

                File::put($this->file, serialize($this->collection()->collection()));
            }
        }

        public function age()
        {
            return filemtime($this->file);
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->collection(), $m], $a);
        }
    }
