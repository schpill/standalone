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

    namespace Mlite;

    use Thin\Config;
    use Thin\File;
    use Thin\Exception;
    use Thin\Now;
    use SQLite3;

    class Motor
    {
        public $table, $database;

        public function __construct($db, $table)
        {
            $file = Config::get('mlite.dir.' . $db, STORAGE_PATH . DS . $db . '.db');

            $new = !is_file($file);

            if (!is_file($file)) {
                File::create($file);
            }

            $link = new SQLite3($file);

            Now::set("lite.link.$db.$table", $link);

            if ($new) {
                $q = "CREATE TABLE IF NOT EXISTS infosdb (data_key VARCHAR PRIMARY KEY, data_value);";

                $res = $link->exec($q);
            }

            $this->table    = $table;
            $this->database = $db;
        }

        public function __get($key)
        {
            if ($key == 'db') {
                $nowKey = 'lite.link.' . $this->database . '.' . $this->table;

                return Now::get($nowKey);
            }
        }

        public function __destruct()
        {

        }

        public function set($k, $v)
        {
            $k = $this->table . '.' . $k;
            $query = "DELETE FROM infosdb WHERE data_key = '$k'";
            $this->db->exec($query);

            $query = "INSERT INTO infosdb (data_key, data_value) VALUES('$k', '" . SQLite3::escapeString(serialize($v)) . "');";

            $this->db->exec($query);

            return $this;
        }

        public function get($k, $default = null)
        {
            $k = $this->table . '.' . $k;
            $query = "SELECT data_value FROM infosdb WHERE data_key = '$k'";
            $result = $this->db->query($query);

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                return unserialize($row['data_value']);
            }

            return $default;
        }

        public function keys($pattern = '*')
        {
            $collection = [];

            $pattern = $this->table . '.' . str_replace('*', '%', $pattern);

            $query = "SELECT data_key FROM infosdb WHERE data_key LIKE '" . SQLite3::escapeString($pattern) . "'";

            $result = $this->db->query($query);

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $collection[] = str_replace($this->ns . '.', '', $row['data_key']);
            }

            return $collection;
        }

        public function delete($k)
        {
            return $this->del($k);
        }

        public function del($k)
        {
            $k = $this->table . '.' . $k;
            $query = "DELETE FROM infosdb WHERE data_key = '$k'";
            $this->db->exec($query);

            return $this;
        }

        public function has($k)
        {
            $k = $this->table . '.' . $k;
            $query = "SELECT COUNT(data_key) AS nb FROM infosdb WHERE data_key = '$k'";
            $result = $this->db->query($query);

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                return $row['nb'] > 0;
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
            $old = $this->get($k, 1);
            $new = $old - $by;

            $this->set($k, $new);

            return $new;
        }
    }
