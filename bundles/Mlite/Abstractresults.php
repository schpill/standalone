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

    abstract class Abstractresults implements \Iterator
    {
        protected $results;

        function __construct($results)
        {
            $this->results = $results;
        }

        function __destruct()
        {
            $this->close();
        }

        abstract function close();
    }
