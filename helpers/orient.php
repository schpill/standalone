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

    use OrientDB;

    class OrientLib
    {
        private $cluster, $db, $cnx;

        public function __construct($collection, $dbCluster = 'default')
        {
            $this->db   = new OrientDB('localhost', Config::get('orientdb.port', 2424));
            $this->cnx  = $this->db->connect(Config::get('orientdb.user', 'root'), Config::get('orientdb.password', 'root'));
            $clusters   = $this->db->DBOpen($collection, 'admin', Config::get('orientdb.password', 'root'));

            foreach ($clusters['clusters'] as $cluster) {
                if ($cluster->name === $dbCluster) {
                    $this->cluster = $cluster;
                }
            }
        }

        public function getDb()
        {
            return $this->db;
        }

        public function getCluster()
        {
            return $this->cluster;
        }
    }
