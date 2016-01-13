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

    use ArrayAccess as AA;
    use MongoClient as MGC;

    class KeepLib implements AA
    {
        private static $instances = [];
        private $ns, $cnx, $db;

        public function __construct($ns = null)
        {
            $ns = is_null($ns) ? forever() : $ns;

            $this->ns = $ns;

            $host       = Config::get('mongo.host', '127.0.0.1');
            $port       = Config::get('mongo.port', 27017);
            $protocol   = Config::get('mongo.protocol', 'mongodb');
            $auth       = Config::get('mongo.auth', true);
            $user       = Config::get('mongo.username', SITE_NAME . '_master');
            $password   = Config::get('mongo.password');

            $this->connect($protocol, $user, $password, $host, $port);

            if (!isset(self::$instances[$ns])) {
                self::$instances[$ns] = $this;
            }

            $odm = $this->cnx->selectDB('keep');

            $this->db = $odm->selectCollection($ns);
        }

        public function instance($ns = null)
        {
            $ns = is_null($ns) ? forever() : $ns;

            if (!isset(self::$instances[$ns])) {
                self::$instances[$ns] = new self($ns);
            }

            return self::$instances[$ns];
        }

        private function connect($protocol, $user, $password, $host, $port, $incr = 0)
        {
            try {
                $this->cnx = new MGC($protocol . '://' . $user . ':' . $password . '@' . $host . ':' . $port, ['connect' => true]);
            } catch (\MongoConnectionException $e) {
                if (APPLICATION_ENV == 'production') {
                    $incr++;

                    if (20 < $incr) {
                        $this->connect($protocol, $user, $password, $host, $port, $incr);
                    } else {
                        dd($e->getMessage());
                    }
                } else {
                    $this->connect($protocol, $user, $password, $host, $port, $incr);
                }
            }
        }

        public function __set($k, $v)
        {
            return $this->set($k, $v);
        }

        public function __get($k)
        {
            return $this->get($k);
        }

        public function __isset($k)
        {
            return $this->has($k);
        }

        public function __unset($k)
        {
            return $this->delete($k);
        }

        public function set($k, $v)
        {
            $exists = $this->db->findOne(['id' => $k]);

            if ($exists) {
                $update = $this->db->update(['id' => $k], ['$set' => ['value' => $v]]);
            } else {
                $new    = $this->db->insert(['id' => $k, 'value' => $v]);
            }

            return $this;
        }

        public function get($k, $default = null)
        {
            $exists = $this->db->findOne(['id' => $k]);

            if ($exists) {
                return $exists['value'];
            }

            return $default;
        }

        public function has($k)
        {
            $exists = $this->db->findOne(['id' => $k]);

            return $exists ? true : false;
        }

        public function offsetSet($k, $v)
        {
            return $this->set($k, $v);
        }

        public function offsetGet($k)
        {
            return $this->get($k);
        }

        public function offsetExists($k)
        {
            return $this->has($k);
        }

        public function offsetUnset($k)
        {
            return $this->delete($k);
        }

        public function delete($k)
        {
            $this->db->remove(
                ['id' => $k],
                ["justOne" => true]
            );

            return $this;
        }

        public function forget($k)
        {
            return $this->delete($k);
        }

        public function remove($k)
        {
            return $this->delete($k);
        }

        public function del($k)
        {
            return $this->delete($k);
        }
    }
