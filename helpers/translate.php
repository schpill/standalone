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

    class TranslateLib
    {
        private $data = [];

        public function __construct($context = 'core')
        {
            $file = APPLICATION_PATH . DS . 'store' . DS . 'translations' . DS . SITE_NAME . DS . $context . '.php';

            if (File::exists($file)) {
                $this->data = include($file);
            }
        }

        public function get($key, $lng = null, $default = null, $args = [])
        {
            $lng = is_null($lng) ? lng() : $lng;

            $keyT = $key . '.' . $lng;

            $val = array_get($this->data, $keyT, $default);

            if (!empty($args) && fnmatch('##*##', $val)) {
                foreach ($args as $k => $v) {
                    $val = str_replace("##$k##", $v, $val);
                }
            }

            return $val;
        }
    }
