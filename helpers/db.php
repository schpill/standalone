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

    class DbLib
    {
        public function start()
        {
            $host       = Config::get('mongo.host', '127.0.0.1');
            $port       = Config::get('mongo.port', 27017);
            $protocol   = Config::get('mongo.protocol', 'mongodb');
            $auth       = Config::get('mongo.auth', true);

            if ($auth) {
                $user       = Config::get('mongo.username', SITE_NAME . '_master');
                $password   = Config::get('mongo.password');
                $dsn = $protocol . '://' . $user . ':' . $password . '@' . $host . ':' . $port;
            } else {
                $dsn = $protocol . '://' . $host . ':' . $port;
            }

            return Mdb::connection($dsn, ['connect' => true]);
        }

        public function __call($method, $args)
        {
            $db = Inflector::uncamelize($method);

            if (fnmatch('*_*', $db)) {
                list($database, $table) = explode('_', $db, 2);
            } else {
                $database   = SITE_NAME;
                $table      = $db;
            }

            $connection = $this->start();
            $odm = $connection->database(SITE_NAME);

            return $odm->collection("$database.$table");
        }
    }
