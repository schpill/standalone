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

    class Schema
    {
        public $db, $database, $table;

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

            $this->db = mysqli_connect(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['database']
            );

            $this->table    = $table;
            $this->database = $db;
        }

        public function create(array $fields, $delete = true)
        {
            $sql = '';

            if ($delete) {
                $delsql = 'DROP TABLE IF EXISTS ' . $this->table;
                $result = mysqli_query($this->db, $delsql);

                if (is_bool($result) && strlen($this->db->error)) {;
                    throw new Exception($this->db->error);
                }
            }

            $sql .= 'CREATE TABLE IF NOT EXISTS ' . $this->table . ' (
              `id` int(11) NOT NULL AUTO_INCREMENT,##fields##,
              `created_at` INT(11) unsigned DEFAULT NULL,
              `updated_at` INT(11) unsigned DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;';

            $sqlFields = [];

            foreach ($fields as $name => $infos) {
                if (is_int($name)) {
                    $name = $infos;
                    $infos = [];
                }

                $type = isAke($infos, 'type', 'varchar');

                if (fnmatch('*_id', $name)) {
                    $type = 'int';
                }

                if ($type == 'varchar') {
                    $sqlField   = '`' . $name . '` varchar(255) DEFAULT NULL';
                } elseif ($type == 'int') {
                    $sqlField   = '`' . $name . '` int(11) unsigned DEFAULT \'0\'';
                } elseif ($type == 'double') {
                    $sqlField   = '`' . $name . '` double(11) unsigned DEFAULT \'0\'';
                } elseif ($type == 'float') {
                    $sqlField   = '`' . $name . '` float(11) unsigned DEFAULT \'0\'';
                } elseif ($type == 'text') {
                    $sqlField   = '`' . $name . '` text DEFAULT NULL';
                } elseif ($type == 'longtext') {
                    $sqlField   = '`' . $name . '` longtext DEFAULT NULL';
                } elseif ($type == 'date') {
                    $sqlField   = '`' . $name . '` date DEFAULT NULL';
                }

                $sqlFields[] = $sqlField;
            }

            $sql = str_replace('##fields##', implode(",", $sqlFields), $sql);

            $result = mysqli_query($this->db, $sql);

            if (is_bool($result) && strlen($this->db->error)) {
                throw new Exception($this->db->error);
            }

            return Db::instance($this->database, $this->table);
        }
    }
