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

    class MysessionLib
    {
        private $db, $ns, $name, $maxLifeTime;

        public function start($ns = 'core')
        {
            $this->db = \Raw\Db::instance('session', $ns . '.' . forever());
            $this->ns = $ns;

            register_shutdown_function('session_write_close');

            $this->maxLifeTime = ini_get('session.gc_maxlifetime');

            session_set_save_handler(
                array($this, 'open'),
                array($this, 'close'),
                array($this, 'read'),
                array($this, 'write'),
                array($this, 'destroy'),
                array($this, 'gc')
            );
        }

        public function open($savePath, $sessionName)
        {
            $this->name = $sessionName;

            return true;
        }

        public function close()
        {
            $rows = $this->db->get();

            foreach ($rows as $row) {
                if ((int) $row['updated_at'] + $this->maxLifeTime < time()) {
                    $object = $this->db->find((int) $row['id'])->delete();
                }
            }

            return true;
        }

        public function read($id)
        {
            $key = $this->normalize($id);

            $row = $this->db->where(['key', '=', $key])->first(true);

            if ($row) {
                return $row->value;
            }

            return null;
        }

        public function write($id, $data)
        {
            $key = $this->normalize($id);vd($key);

            $row = $this->db->firstOrCreate(['key' => $key])->setValue($data)->save();

            return true;
        }

        public function destroy($id)
        {
            $key = $this->normalize($id);

            $row = $this->db->where(['key', '=', $key])->first(true);

            if ($row) {
                $row->delete();
            }

            return true;
        }

        public function gc($maxlifetime)
        {
            $rows = $this->db->get();

            foreach ($rows as $row) {
                if ((int) $row['updated_at'] + $maxlifetime < time()) {
                    $object = $this->db->find((int) $row['id'])->delete();
                }
            }

            return true;
        }

        private function normalize($str)
        {
            return Inflector::urlize($str, '');
        }
    }
