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

    class LoggerLib
    {
        public function __construct()
        {
            $dir = Config::get('app.logs.dir', '/home/storage/logs');

            $dir .= DS . APPLICATION_ENV;

            if (!is_dir($dir)) {
                File::mkdir($dir);
            }
        }
    }
