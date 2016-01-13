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

    use ReflectionClass;
    use Serializable;

    class Reflection implements Serializable
    {
        /**
         *
         * The class on which we are reflecting.
         *
         * @var string
         *
         */
        protected $class;

        /**
         *
         * The decorated ReflectionClass instance.
         *
         * @var ReflectionClass
         *
         */
        protected $reflection;

        /**
         *
         * Constructor.
         *
         * @param string $class The class on which we are reflecting.
         *
         */
        public function __construct($class)
        {
            $this->class = $class;
            $this->setReflection();
        }

        /**
         *
         * Pass-through to decorated ReflectionClass methods.
         *
         * @param string $name The method name.
         *
         * @param array $args The method arguments.
         *
         * @return mixed
         *
         */
        public function __call($name, $args)
        {
            $this->setReflection();
            return call_user_func_array(array($this->reflection, $name), $args);
        }

        /**
         *
         * Sets the decorated ReflectionClass instance.
         *
         * @return null
         *
         */
        protected function setReflection()
        {
            if (! $this->reflection) {
                $this->reflection = new ReflectionClass($this->class);
            }
        }

        /**
         *
         * Implements Serializer::serialize().
         *
         * @return string The serialized string.
         *
         */
        public function serialize()
        {
            $this->reflection = null;
            return serialize($this->class);
        }

        /**
         *
         * Implements Serializer::unserialize().
         *
         * @param string $serialized The serialized string.
         *
         * @return null
         *
         */
        public function unserialize($serialized)
        {
            $class = unserialize($serialized);
            $this->class = $class;
        }
    }
