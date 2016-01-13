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

    class MemorycacheLib
    {
        private static $datas = [];
        private $collection;

        public function __construct($collection = 'cache')
        {
            if (!isset(self::$datas[$collection])) {
                self::$datas[$collection] = [];
            }

            $this->collection = $collection;
        }

        /**
         * {@inheritdoc}
         */
        public function get($id, $d = null)
        {
            $data = self::$datas[$this->collection];

            return isAke($data, $id, $d);
        }

        /**
         * {@inheritdoc}
         */
        public function has($id)
        {
            $data = self::$datas[$this->collection];

            $val = isAke($data, $id, null);

            return $val ? true : false;
        }

        /**
         * {@inheritdoc}
         */
        public function set($id, $data)
        {
            self::$datas[$this->collection][$id] = $data;

            return $this;
        }

        public function hset($hash, $id, $data)
        {
            $id = "$hash.$id";

            return $this->set($id, $data);
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

        public function hincr($hash, $id, $by = 1)
        {
            $id = "$hash.$id";

            return $this->incr($id, $by);
        }

        public function hdecr($hash, $id, $by = 1)
        {
            $id = "$hash.$id";

            return $this->decr($id, $by);
        }

        public function incr($id, $by = 1)
        {
            $old = $this->get($id, 0);
            $new = $old + $by;

            $this->set($id, $new);

            return $new;
        }

        public function decr($id, $by = 1)
        {
            $old = $this->get($id, 0);
            $new = $old - $by;

            $this->set($id, $new);

            return $new;
        }

        /**
         * {@inheritdoc}
         */
        public function delete($id)
        {
            unset(self::$datas[$this->collection][$id]);

            return $this;
        }

        /**
         * {@inheritdoc}
         */
        public function flush()
        {
            self::$datas[$this->collection] = [];

            return $this;
        }
    }
