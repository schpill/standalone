<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2016 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */

    namespace Thin;

    class IteratorCore implements \IteratorAggregate, \Countable
    {
        /**
         * storage.
         *
         * @var array
         */
        protected $datas;

        /**
         * Constructor.
         *
         * @param array $datas An array of datas
         *
         * @api
         */
        public function __construct(array $datas = array())
        {
            $this->datas = $datas;
        }

        /**
         * Returns the datas.
         *
         * @return array An array of datas
         *
         * @api
         */
        public function all()
        {
            return $this->datas;
        }

        /**
         * Returns the parameter keys.
         *
         * @return array An array of parameter keys
         *
         * @api
         */
        public function keys()
        {
            return array_keys($this->datas);
        }

        /**
         * Replaces the current datas by a new set.
         *
         * @param array $datas An array of datas
         *
         * @api
         */
        public function replace(array $datas = array())
        {
            $this->datas = $datas;
        }

        /**
         * Adds datas.
         *
         * @param array $datas An array of datas
         *
         * @api
         */
        public function add(array $datas = array())
        {
            $this->datas = array_replace($this->datas, $datas);
        }

        /**
         * Returns a parameter by name.
         *
         * @param string $path    The key
         * @param mixed  $default The default value if the parameter key does not exist
         * @param bool   $deep    If true, a path like foo[bar] will find deeper items
         *
         * @return mixed
         *
         * @throws \InvalidArgumentException
         *
         * @api
         */
        public function get($path, $default = null, $deep = false)
        {
            if (!$deep || false === $pos = strpos($path, '[')) {
                return array_key_exists($path, $this->datas) ? $this->datas[$path] : $default;
            }

            $root = substr($path, 0, $pos);
            if (!array_key_exists($root, $this->datas)) {
                return $default;
            }

            $value = $this->datas[$root];
            $currentKey = null;
            for ($i = $pos, $c = strlen($path); $i < $c; ++$i) {
                $char = $path[$i];

                if ('[' === $char) {
                    if (null !== $currentKey) {
                        throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "[" at position %d.', $i));
                    }

                    $currentKey = '';
                } elseif (']' === $char) {
                    if (null === $currentKey) {
                        throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "]" at position %d.', $i));
                    }

                    if (!is_array($value) || !array_key_exists($currentKey, $value)) {
                        return $default;
                    }

                    $value = $value[$currentKey];
                    $currentKey = null;
                } else {
                    if (null === $currentKey) {
                        throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "%s" at position %d.', $char, $i));
                    }

                    $currentKey .= $char;
                }
            }

            if (null !== $currentKey) {
                throw new \InvalidArgumentException(sprintf('Malformed path. Path must end with "]".'));
            }

            return $value;
        }

        /**
         * Sets a parameter by name.
         *
         * @param string $key   The key
         * @param mixed  $value The value
         *
         * @api
         */
        public function set($key, $value)
        {
            $this->datas[$key] = $value;
        }

        /**
         * Returns true if the parameter is defined.
         *
         * @param string $key The key
         *
         * @return bool true if the parameter exists, false otherwise
         *
         * @api
         */
        public function has($key)
        {
            return array_key_exists($key, $this->datas);
        }

        /**
         * Removes a parameter.
         *
         * @param string $key The key
         *
         * @api
         */
        public function remove($key)
        {
            unset($this->datas[$key]);
        }

        /**
         * Returns the alphabetic characters of the parameter value.
         *
         * @param string $key     The parameter key
         * @param mixed  $default The default value if the parameter key does not exist
         * @param bool   $deep    If true, a path like foo[bar] will find deeper items
         *
         * @return string The filtered value
         *
         * @api
         */
        public function getAlpha($key, $default = '', $deep = false)
        {
            return preg_replace('/[^[:alpha:]]/', '', $this->get($key, $default, $deep));
        }

        /**
         * Returns the alphabetic characters and digits of the parameter value.
         *
         * @param string $key     The parameter key
         * @param mixed  $default The default value if the parameter key does not exist
         * @param bool   $deep    If true, a path like foo[bar] will find deeper items
         *
         * @return string The filtered value
         *
         * @api
         */
        public function getAlnum($key, $default = '', $deep = false)
        {
            return preg_replace('/[^[:alnum:]]/', '', $this->get($key, $default, $deep));
        }

        /**
         * Returns the digits of the parameter value.
         *
         * @param string $key     The parameter key
         * @param mixed  $default The default value if the parameter key does not exist
         * @param bool   $deep    If true, a path like foo[bar] will find deeper items
         *
         * @return string The filtered value
         *
         * @api
         */
        public function getDigits($key, $default = '', $deep = false)
        {
            // we need to remove - and + because they're allowed in the filter
            return str_replace(array('-', '+'), '', $this->filter($key, $default, $deep, FILTER_SANITIZE_NUMBER_INT));
        }

        /**
         * Returns the parameter value converted to integer.
         *
         * @param string $key     The parameter key
         * @param mixed  $default The default value if the parameter key does not exist
         * @param bool   $deep    If true, a path like foo[bar] will find deeper items
         *
         * @return int The filtered value
         *
         * @api
         */
        public function getInt($key, $default = 0, $deep = false)
        {
            return (int) $this->get($key, $default, $deep);
        }

        /**
         * Returns the parameter value converted to boolean.
         *
         * @param string $key     The parameter key
         * @param mixed  $default The default value if the parameter key does not exist
         * @param bool   $deep    If true, a path like foo[bar] will find deeper items
         *
         * @return bool The filtered value
         */
        public function getBoolean($key, $default = false, $deep = false)
        {
            return $this->filter($key, $default, $deep, FILTER_VALIDATE_BOOLEAN);
        }

        /**
         * Filter key.
         *
         * @param string $key     Key.
         * @param mixed  $default Default = null.
         * @param bool   $deep    Default = false.
         * @param int    $filter  FILTER_* constant.
         * @param mixed  $options Filter options.
         *
         * @see http://php.net/manual/en/function.filter-var.php
         *
         * @return mixed
         */
        public function filter($key, $default = null, $deep = false, $filter = FILTER_DEFAULT, $options = array())
        {
            $value = $this->get($key, $default, $deep);

            // Always turn $options into an array - this allows filter_var option shortcuts.
            if (!is_array($options) && $options) {
                $options = array('flags' => $options);
            }

            // Add a convenience check for arrays.
            if (is_array($value) && !isset($options['flags'])) {
                $options['flags'] = FILTER_REQUIRE_ARRAY;
            }

            return filter_var($value, $filter, $options);
        }

        /**
         * Returns an iterator for datas.
         *
         * @return \ArrayIterator An \ArrayIterator instance
         */
        public function getIterator()
        {
            return new \ArrayIterator($this->datas);
        }

        public function iterator()
        {
            foreach ($this->datas as $row) {
                yield $row;
            }
        }

        /**
         * Returns the number of datas.
         *
         * @return int The number of datas
         */
        public function count()
        {
            return count($this->datas);
        }
    }
