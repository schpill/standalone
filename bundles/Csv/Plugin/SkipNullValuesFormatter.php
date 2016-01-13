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

    namespace Csv\Plugin;

    /**
     *  A class to remove null value from data before insertion into a CSV
     *
     * @package csv
     * @since  7.0.0
     *
     */
    class SkipNullValuesFormatter
    {
        /**
         * remove null value form the submitted array
         *
         * @param array $row
         *
         * @return array
         */
        public function __invoke(array $row)
        {
            return array_filter($row, function ($value) {
                return !is_null($value);
            });
        }
    }
