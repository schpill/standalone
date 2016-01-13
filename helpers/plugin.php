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

    use Phalcon\Mvc\User\Plugin as UP;

    class PluginLib extends UP
    {
        public function annotations($class, $method = null)
        {
            return is_null($method) ?
            $this->annotations->get($class)->getPropertiesAnnotations() :
            $this->annotations->getMethod($class, $method);
        }

        public function di($service = null)
        {
            return ph($service);
        }
    }
