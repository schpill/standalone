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

    namespace Mlite;

    class Results extends Abstractresults
    {
        protected $position = 0;
        protected $current = null;

        function __construct($results)
        {
            parent::__construct($results);
            $this->rewind();
        }

        public function rewind()
        {
            $this->results->reset();
            $this->current = $this->results->fetchArray(SQLITE3_ASSOC);
            $this->position = 0;
        }

        public function current()
        {
            return $this->current;
        }

        public function next()
        {
            $this->current = $this->results->fetchArray(SQLITE3_ASSOC);

            if (!is_null($this->current)) {
                $this->position += 1;
            }

            return $this->current;
        }

        public function key()
        {
            return $this->position;
        }

        public function valid()
        {
            return !is_null($this->current);
        }

        public function size()
        {
            return null;
        }

        function close()
        {
            $this->results->close();
        }

    }
