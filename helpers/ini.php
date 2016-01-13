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

    class IniLib
    {
        private $ns, $dir;

        public function __construct($ns = 'core')
        {
            $dir = STORAGE_PATH . DS . 'ini';

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $dir .= DS . $ns;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }

            $this->dir = $dir;
            $this->ns = $ns;

        }

        public function read($id)
        {
            $file = $this->dir . DS . $id . '.ini';

            if (is_file($file)) {
                return parse_ini_file($file, true);
            }

            return null;
        }

        function write($array)
        {
            $files  = glob($this->dir . DS . '*.ini');
            $id     = count($files) + 1;
            $file   = $this->dir . DS . $id . '.ini';

            $res = [];

            foreach ($array as $key => $val) {
                if (is_array($val)) {
                    $res[] = "[$key]";

                    foreach ($val as $skey => $sval) {
                        $res[] = "$skey = " . (is_numeric($sval) ? $sval : '"' . $sval . '"');
                    }
                } else {
                    $res[] = "$key = " . (is_numeric($val) ? $val : '"' . $val . '"');
                }
            }

            $this->safeWrite($file, implode("\r\n", $res));
        }

        function safeWrite($fileName, $dataToSave)
        {
            if ($fp = fopen($fileName, 'w')) {
                $startTime = microtime(true);

                do {
                    $canWrite = flock($fp, LOCK_EX);

                    if(!$canWrite) {
                        usleep(round(rand(0, 100) * 1000));
                    }
                } while ((!$canWrite) && ((microtime(true) - $startTime) < 5));

                if ($canWrite) {
                    fwrite($fp, $dataToSave);
                    flock($fp, LOCK_UN);
                }

                fclose($fp);
            }
        }
    }
