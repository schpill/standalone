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

    use InvalidArgumentException;

    /**
     *  A class to manage column consistency on data insertion into a CSV
     *
     * @package csv
     * @since  7.0.0
     *
     */
    class ColumnConsistencyValidator
    {
        /**
         * The number of column per row
         *
         * @var int
         */
        private $columns_count = -1;

        /**
         * should the class detect the column count based the inserted row
         *
         * @var bool
         */
        private $detect_columns_count = false;

        /**
         * Set Inserted row column count
         *
         * @param int $value
         *
         * @throws \InvalidArgumentException If $value is lesser than -1
         *
         * @return void
         */
        public function setColumnsCount($value)
        {
            if (false === filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => -1]])) {
                throw new InvalidArgumentException('the column count must an integer greater or equals to -1');
            }

            $this->detect_columns_count = false;
            $this->columns_count = $value;
        }

        /**
         * Column count getter
         *
         * @return int
         */
        public function getColumnsCount()
        {
            return $this->columns_count;
        }

        /**
         * The method will set the $columns_count property according to the next inserted row
         * and therefore will also validate the next line whatever length it has no matter
         * the current $columns_count property value.
         *
         * @return void
         */
        public function autodetectColumnsCount()
        {
            $this->detect_columns_count = true;
        }

        /**
         * Is the submitted row valid
         *
         * @param array $row
         *
         * @return bool
         */
        public function __invoke(array $row)
        {
            if ($this->detect_columns_count) {
                $this->columns_count = count($row);
                $this->detect_columns_count = false;

                return true;
            } elseif (-1 == $this->columns_count) {
                return true;
            }

            return count($row) == $this->columns_count;
        }
    }
