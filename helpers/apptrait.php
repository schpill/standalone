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

    trait Apptrait
    {
        /**
         * IoC container instance.
         *
         */
        protected $container;

        /**
         * Array of resolved objects and/or references to resolved objects.
         *
         * @var array
         */
        protected $resolved = [];

        /**
         * Sets the container instance.
         *
         * @access  public
         */
        public function setContainer(AppLib $container)
        {
            $this->container = $container;
        }

        /**
         * Gets the container instance.
         *
         * @access  public
         */
        public function getContainer($container)
        {
            return $this->container;
        }

        /**
         * Resolves item from the container using overloading.
         *
         * @access  public
         * @param   string  $key  Key
         * @return  mixed
         */
        public function __get($key)
        {
            if (!isset($this->resolved[$key])) {
                if (!$this->container->has($key)) {
                    throw new \RuntimeException(
                        vsprintf(
                            "%s::%s(): Unable to resolve [ %s ].",
                            [
                                __TRAIT__,
                                __FUNCTION__,
                                $key
                            ]
                        )
                    );
                }

                $this->resolved[$key] = $this->container->get($key);
            }

            return $this->resolved[$key];
        }
    }
