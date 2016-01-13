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

    namespace Raw;

    use Thin\Arrays;

    class Entityalias
    {
        private $db;

        public function __construct()
        {
            $class = get_called_class();
            $newClass = strtolower(str_replace(['Thin\\', '_Db'], '', $class));

            list($db, $table) = explode('_', $newClass, 2);

            if (empty($table)) {
                $table  = $db;
                $db     = SITE_NAME;
            }

            $this->db = Db::instance($db, $table);
        }

        public function __call($m, $a)
        {
            return call_user_func_array([$this->db, $m], $a);
        }
    }
