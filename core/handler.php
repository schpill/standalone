<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2016 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */

    namespace Thin;

    class HandlerCore implements \SessionHandlerInterface
    {
        private $db;

        public function __construct()
        {
            session_set_save_handler($this, true);
            session_start();
        }

        public function open($savePath, $sessionName)
        {
            $this->db = core('fast')->instanciate('session', Inflector::urlize($sessionName, ''));
        }

        public function close()
        {
            return true;
        }

        public function read($id)
        {
            $row = $this->db->where(['id_session', '=', $id])->first();

            if ($row) {
                return $row['data'];
            } else {
                return "";
            }
        }

        public function write($id, $data)
        {
            $NewDateTime = strtotime(
                Config::get(
                    'session.flat.time',
                    '+1 hour'
                )
            );

            $row = $this->db->firstOrCreate(['id_session', '=', $id])->setDate($data)->setExp($NewDateTime);

            return true;
        }

        public function destroy($id)
        {
            $this->db->firstOrCreate(['id_session', '=', $id])->delete();

            return true;
        }

        public function gc($maxlifetime)
        {
            $rows = $this->db->where(['exp', '<', time()])->models();

            foreach ($rows as $row) {
                $row->delete();
            }
        }
    }
