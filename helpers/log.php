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

    class LogLib
    {
        private $file;

        public function __construct($name = null)
        {
            $name = is_null($name) ? date('d_m_Y') : $name;
            $this->file = Config::get('dir.module.logs', LOGS_PATH) . DS . $name . '.log';
        }

        public function write($str, $type = null)
        {
            $type = is_null($type) ? 'INFO' : $type;
            $type = Inflector::upper($type);

            $data = date('Y-m-d H:i:s') . ":$type:$str";

            File::append($this->file, $data . "\n");
        }

        public function __call($f, $a)
        {
            return $this->write(current($a), $f);
        }
    }
