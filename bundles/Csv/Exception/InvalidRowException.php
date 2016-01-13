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

    namespace Csv\Exception;

    use InvalidArgumentException;

    /**
     *  Thrown when a data is not validated prior to insertion
     *
     * @package csv
     * @since  7.0.0
     *
     */
    class InvalidRowException extends \InvalidArgumentException
    {
        /**
         * Validator which did not validated the data
         * @var string
         */
        private $name;

        /**
         * Validator Data which caused the error
         * @var array
         */
        private $data;

        /**
         * New Instance
         *
         * @param string $name    validator name
         * @param array  $data    invalid  data
         * @param string $message exception message
         */
        public function __construct($name, array $data = [], $message = "")
        {
            parent::__construct($message);
            $this->name = $name;
            $this->data = $data;
        }

        /**
         * return the validator name
         *
         * @return string
         */
        public function getName()
        {
            return $this->name;
        }

        /**
         * return the invalid data submitted
         *
         * @return array
         */
        public function getData()
        {
            return $this->data;
        }
    }
