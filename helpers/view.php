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

    class viewLib
    {
        private $tpl, $datas = [];

        public function __construct($tpl)
        {
            $this->tpl = $tpl;
        }

        public function __set($key, $value)
        {
            $this->datas[$key] = $value;

            return $this;
        }

        public function __get($key)
        {
            return isAke($this->datas, $key, null);
        }

        public function __isset($key)
        {
            return array_key_exists($key, $this->datas);
        }

        public function __unset($key)
        {
            unset($this->datas[$key]);
        }

        public function render($echo = false)
        {
            ob_start();

            require($this->tpl);

            $content = ob_get_clean();

            $content = str_replace('$this', 'lib("view", "' . $this->tpl . '")', $content);

            if ($echo) {
                echo $content;
            }

            return $content;
        }

        public static function instance($tpl)
        {
            $key    = sha1($tpl);
            $has    = Instance::has('libView', $key);

            if (true === $has) {
                return Instance::get('libView', $key);
            } else {
                return Instance::make('libView', $key, new self($tpl));
            }
        }
    }
