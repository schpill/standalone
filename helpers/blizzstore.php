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
    use SQLite3;

    class BlizzstoreLib
    {
        public function __construct($db)
        {
            $this->orm      = $db;
            $this->db       = $db->db();
            $this->table    = $db->table();

            $dir = Config::get('dir.blizz.store', session_save_path());

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $dir .= DS . Inflector::urlize(Inflector::uncamelize($this->db));

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $this->dir = $dir . DS . Inflector::urlize(Inflector::uncamelize($this->table));

            if (!is_dir($this->dir)) {
                File::mkdir($this->dir);
            }

            $file = $this->dir . DS . 'data.db';
            $new = false;

            if (!is_file($file)) {
                File::create($file);
                $new = true;

                File::put($this->dir . DS . 'age.blizz', '');
            }

            $link = new SQLite3($file);

            Now::set("blizz.link.$this->db.$this->table", $link);

            if ($new) {
                $this->init();
            }
        }

        public function __get($k)
        {
            if ($k == 'link') {
                return Now::get("blizz.link.$this->db.$this->table");
            }

            return isset($this->$k) ? $this->$k : null;
        }

        private function init()
        {
            $q = "CREATE TABLE IF NOT EXISTS data (data_key VARCHAR PRIMARY KEY, data_value);";

            $res = $this->link->exec($q);
        }

        public function set($k, $v)
        {
            $query = "DELETE FROM data WHERE data_key = '$k'";
            $this->link->exec($query);

            $query = "INSERT INTO data (data_key, data_value) VALUES('$k', '" . SQLite3::escapeString(serialize($v)) . "');";

            $this->link->exec($query);

            return $this;
        }

        public function get($k, $default = null)
        {
            $query = "SELECT data_value FROM data WHERE data_key = '$k'";
            $result = $this->link->query($query);

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                return unserialize($row['data_value']);
            }

            return $default;
        }

        public function getOr($k, callable $c, $e = null)
        {
            if ($this->has($k)) {
                return $this->get($k);
            }

            $res = $c();

            $this->set($k, $res, $e);

            return $res;
        }

        public function keys($pattern = '*')
        {
            $collection = [];

            $pattern = str_replace('*', '%', $pattern);

            $query = "SELECT data_key FROM data WHERE data_key LIKE '" . SQLite3::escapeString($pattern) . "'";

            $result = $this->link->query($query);

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $collection[] = $row['data_key'];
            }

            return $collection;
        }

        public function delete($k)
        {
            return $this->del($k);
        }

        public function del($k)
        {
            $query = "DELETE FROM data WHERE data_key = '$k'";
            $this->link->exec($query);

            return $this;
        }

        public function remove($k)
        {
            return $this->delete($k);
        }

        public function forget($k)
        {
            return $this->delete($k);
        }

        public function has($k)
        {
            $query = "SELECT COUNT(data_key) AS nb FROM data WHERE data_key = '$k'";
            $result = $this->link->query($query);

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                return $row['nb'] > 0;
            }

            return false;
        }

        public function setnx($key, $value)
        {
            if (!$this->has($key)) {
                $this->set($key, $value);

                return true;
            }

            return false;
        }

        public function incr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old + $by;

            $this->set($k, $new);

            return $new;
        }

        public function decr($k, $by = 1)
        {
            $old = $this->get($k, 0);
            $new = $old - $by;

            $this->set($k, $new);

            return $new;
        }


        public function increment($k, $by = 1)
        {
            return $this->incr($k, $by);
        }

        public function decrement($k, $by = 1)
        {
            return $this->decr($k, $by);
        }

        public function orm()
        {
            return $this->orm;
        }

        public function db()
        {
            return $this->db;
        }

        public function table()
        {
            return $this->table;
        }
    }
