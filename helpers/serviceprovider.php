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

    class ServiceproviderLib
    {
        private $ns;

        public function __construct($ns)
        {
            $this->ns = $ns;
        }

        public function register(callable $service)
        {
            $app = IApp::getInstance();

            $app->bind($this->ns, $service);

            return $this;
        }
    }
