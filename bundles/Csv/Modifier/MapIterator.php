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

    namespace Csv\Modifier;

    use Iterator;
    use IteratorIterator;

    /**
     *  A simple MapIterator
     *
     * @package Csv
     * @since  3.3.0
     * @internal used internally to modify CSV content
     *
     */
    class MapIterator extends IteratorIterator
    {
        /**
         * The function to be apply on all InnerIterator element
         *
         * @var callable
         */
        private $callable;

        /**
         * The Constructor
         *
         * @param Iterator $iterator
         * @param callable    $callable
         */
        public function __construct(Iterator $iterator, callable $callable)
        {
            parent::__construct($iterator);
            $this->callable = $callable;
        }

        /**
         * Get the value of the current element
         */
        public function current()
        {
            $iterator = $this->getInnerIterator();
            $callable = $this->callable;

            return $callable($iterator->current(), $iterator->key(), $iterator);
        }
    }
