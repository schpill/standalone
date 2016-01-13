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
    use Thin\Exception;
    use SQLite3;

    class Schema
    {
        public $db, $database, $table;

        public function __construct($db, $table)
        {
            $dir = Config::get('mlite.dir.' . $db, STORAGE_PATH . DS . $db . '.db');

            $new = !is_file($dir);

            if (!is_file($dir)) {
                File::create($dir);
            }

            $this->db = new SQLite3($dir);

            if ($new) {
                $q = "CREATE TABLE infosdb (data_key VARCHAR PRIMARY KEY, data_value);";

                $this->db->exec($q);
            }

            $this->table    = $table;
            $this->database = $db;
        }

        public function create(array $fields, $delete = true)
        {
            $sql = '';

            if ($delete) {
                $delsql = 'DROP TABLE IF EXISTS ' . $this->table;
                $result = $this->db->exec($delsql);
            }

            $sql .= 'CREATE TABLE IF NOT EXISTS ' . $this->table . ' (
              "id" integer(11) NOT NULL DEFAULT \'\',##fields##,
              "created_at" integer(11) NOT NULL DEFAULT \'\',
              "updated_at" integer(11) NOT NULL DEFAULT \'\'
            );';

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
                    $sqlField   = '"' . $name . '" text NOT NULL DEFAULT \'\'';
                } elseif ($type == 'int') {
                    $sqlField   = '"' . $name . '" integer(11) NOT NULL DEFAULT \'\'';
                } elseif ($type == 'double') {
                    $sqlField   = '"' . $name . '" numeric NOT NULL DEFAULT \'\'';
                } elseif ($type == 'float') {
                    $sqlField   = '"' . $name . '" numeric NOT NULL DEFAULT \'\'';
                } elseif ($type == 'text') {
                    $sqlField   = '"' . $name . '" text NOT NULL DEFAULT \'\'';
                } elseif ($type == 'longtext') {
                    $sqlField   = '"' . $name . '" text NOT NULL DEFAULT \'\'';
                } elseif ($type == 'date') {
                    $sqlField   = '"' . $name . '" text NOT NULL DEFAULT \'\'';
                }

                $sqlFields[] = $sqlField;
            }

            $sql = str_replace('##fields##', implode(",", $sqlFields), $sql);

            $result = $this->db->exec($sql);

            return Db::instance($this->database, $this->table);
        }
    }
