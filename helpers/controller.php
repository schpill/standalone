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

    class ControllerLib
    {
        public $view;
        private $app;

        public function __construct()
        {
            /* DI */
            $this->app = lib('app')->getInstance();
        }
    }
