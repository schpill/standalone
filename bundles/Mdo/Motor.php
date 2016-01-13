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

    namespace Mdo;

    use Thin\Config;
    use Thin\Exception;
    use Thin\Now;

    class Motor
    {
        public $table, $database;

        public function __construct($db, $table)
        {
            $config = Config::get('mdo.config.' . $db, []);

            if (empty($config)) {
                $config = [
                    'driver'    => Config::get('database.adapter', 'mysql'),
                    'host'      => Config::get('database.host', '127.0.0.1'),
                    'database'  => Config::get('database.dbname', SITE_NAME),
                    'username'  => Config::get('database.username', 'root'),
                    'password'  => Config::get('database.password', 'root'),
                    'charset'   => Config::get('database.charset', 'utf8'),
                    'collation' => Config::get('database.collation', 'utf8_unicode_ci'),
                    'prefix'    => Config::get('database.prefix', '')
                ];
            }

            if (empty($config)) {
                throw new Exception('No config provided to launch motor.');
            }

            $link = mysqli_connect(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['database']
            );

            Now::set("link.$db.$table", $link);

            $this->table    = $table;
            $this->database = $db;
        }

        public function __get($key)
        {
            if ($key == 'db') {
                $nowKey = 'link.' . $this->database . '.' . $this->table;

                return Now::get($nowKey);
            }
        }

        public function __destruct()
        {

        }

        public function set($k, $v)
        {
            $k = $this->table . '.' . $k;
            $this->del($k);

            $query = "REPLACE INTO infosdb SET data_key = '$k', data_value = '" . mysqli_escape_string($this->db, serialize($v)) . "'";

            $result = mysqli_query($this->db, $query);

            return $this;
        }

        public function get($k, $default = null)
        {
            $k = $this->table . '.' . $k;
            $query = "SELECT data_value FROM infosdb WHERE data_key = '$k'";
            $result = mysqli_query($this->db, $query);

            if (is_bool($result)) {
                return $default;
            }

            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                return unserialize($row['data_value']);
            }

            return $default;
        }

        public function delete($k)
        {
            return $this->del($k);
        }

        public function del($k)
        {
            $k = $this->table . '.' . $k;
            $query = "DELETE FROM infosdb WHERE data_key = '$k'";
            $result = mysqli_query($this->db, $query);

            if (is_bool($result)) {
                return false;
            }

            return $this;
        }

        public function has($k)
        {
            $k = $this->table . '.' . $k;
            $query = "SELECT COUNT(data_key) AS nb FROM infosdb WHERE data_key = '$k'";
            $result = mysqli_query($this->db, $query);

            if (is_bool($result)) {
                return false;
            }

            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                return $row['nb'] > 0;
            }

            return false;
        }
    }
