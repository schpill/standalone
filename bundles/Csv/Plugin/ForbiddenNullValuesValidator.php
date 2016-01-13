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
     *  A class to validate null value handling on data insertion into a CSV
     *
     * @package csv
     * @since  7.0.0
     *
     */
    class ForbiddenNullValuesValidator
    {
        /**
         * Is the submitted row valid
         *
         * @param array $row
         *
         * @return bool
         */
        public function __invoke(array $row)
        {
            $res = array_filter($row, function ($value) {
                return is_null($value);
            });

            return !$res;
        }
    }
