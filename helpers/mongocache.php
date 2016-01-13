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

    use MongoBinData;
    use MongoCollection;
    use MongoCursorException;
    use MongoDate;

    class MongocacheLib
    {
        /**
         * The data field will store the serialized PHP value.
         */
        const DATA_FIELD = 'd';

        const EXPIRATION_FIELD = 'e';

        /**
         * @var MongoCollection
         */
        private $collection;


        public function __construct($collection = 'cache')
        {
            $this->collection = lib('clipp')->em($collection);
        }

        /**
         * {@inheritdoc}
         */
        public function get($id, $d = null)
        {
            $document = $this->collection->findOne(
                array('_id' => $id),
                array(self::DATA_FIELD, self::EXPIRATION_FIELD)
            );

            if ($document === null) {
                return $d;
            }

            if ($this->isExpired($document)) {
                $this->delete($id);

                return $d;
            }

            return unserialize($document[self::DATA_FIELD]->bin);
        }

        /**
         * {@inheritdoc}
         */
        public function has($id)
        {
            $document = $this->collection->findOne(
                array('_id' => $id),
                array(self::EXPIRATION_FIELD)
            );

            if ($document === null) {
                return false;
            }

            if ($this->isExpired($document)) {
                $this->delete($id);

                return false;
            }

            return true;
        }

        /**
         * {@inheritdoc}
         */
        public function set($id, $data, $lifeTime = 0)
        {
            try {
                $result = $this->collection->update(
                    array('_id' => $id),
                    array('$set' => array(
                        self::EXPIRATION_FIELD => ($lifeTime > 0 ? new MongoDate(time() + $lifeTime) : null),
                        self::DATA_FIELD => new MongoBinData(serialize($data), MongoBinData::BYTE_ARRAY),
                    )),
                    array('upsert' => true, 'multiple' => false)
                );
            } catch (MongoCursorException $e) {
                return false;
            }

            return isset($result['ok']) ? $result['ok'] == 1 : true;
        }

        public function hset($hash, $id, $data, $lifeTime = 0)
        {
            $id = "$hash.$id";

            return $this->set($id, $data, $lifeTime);
        }

        public function hget($hash, $id, $d = null)
        {
            $id = "$hash.$id";

            return $this->get($id, $d);
        }

        public function hhas($hash, $id)
        {
            $id = "$hash.$id";

            return $this->has($id);
        }

        public function hincr($hash, $id, $by = 1, $lifeTime = 0)
        {
            $id = "$hash.$id";

            return $this->incr($id, $by, $lifeTime);
        }

        public function hdecr($hash, $id, $by = 1, $lifeTime = 0)
        {
            $id = "$hash.$id";

            return $this->decr($id, $by, $lifeTime);
        }

        public function incr($id, $by = 1, $lifeTime = 0)
        {
            $old = $this->get($id, 0);
            $new = $old + $by;

            $this->set($id, $new, $lifeTime);

            return $new;
        }

        public function decr($id, $by = 1, $lifeTime = 0)
        {
            $old = $this->get($id, 0);
            $new = $old - $by;

            $this->set($id, $new, $lifeTime);

            return $new;
        }

        /**
         * {@inheritdoc}
         */
        public function delete($id)
        {
            $result = $this->collection->remove(array('_id' => $id));

            return isset($result['ok']) ? $result['ok'] == 1 : true;
        }

        /**
         * {@inheritdoc}
         */
        public function flush()
        {
            $result = $this->collection->remove();

            return isset($result['ok']) ? $result['ok'] == 1 : true;
        }

        /**
         * {@inheritdoc}
         */
        public function stats()
        {
            $serverStatus = $this->collection->db->command(array(
                'serverStatus'  => 1,
                'locks'         => 0,
                'metrics'       => 0,
                'recordStats'   => 0,
                'repl'          => 0,
            ));

            $collStats = $this->collection->db->command(array('collStats' => 1));

            return array(
                'hits'          => null,
                'misses'        => null,
                'uptime'        => (isset($serverStatus['uptime']) ? (int) $serverStatus['uptime'] : null),
                'usage'         => (isset($collStats['size']) ? (int) $collStats['size'] : null),
                'available'     => null,
            );
        }

        /**
         * Check if the document is expired.
         *
         * @param array $document
         *
         * @return bool
         */
        private function isExpired(array $document)
        {
            return isset($document[self::EXPIRATION_FIELD]) && $document[self::EXPIRATION_FIELD] instanceof MongoDate && $document[self::EXPIRATION_FIELD]->sec < time();
        }
    }
