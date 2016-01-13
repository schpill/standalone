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

    class StrLib
    {
        use RuntimeException;
        use Stringy\StaticStringy;

        /**
         * The cache of snake-cased words.
         *
         * @var array
         */
        protected static $snakeCache = [];

        /**
         * The cache of camel-cased words.
         *
         * @var array
         */
        protected static $camelCache = [];

        /**
         * The cache of studly-cased words.
         *
         * @var array
         */
        protected static $studlyCache = [];

        /**
         * Transliterate a UTF-8 value to ASCII.
         *
         * @param  string  $value
         * @return string
         */
        public function ascii($value)
        {
            return StaticStringy::toAscii($value);
        }

        public function is($pattern, $value)
        {
            if ($pattern == $value || fnmatch("*$pattern*", $value)) {
                return true;
            }

            $pattern = preg_quote($pattern, '#');

            $pattern = str_replace('\*', '.*', $pattern) . '\z';

            return (bool) preg_match('#^' . $pattern . '#', $value);
        }

        /**
         * Convert a value to camel case.
         *
         * @param  string  $value
         * @return string
         */
        public function camel($value)
        {
            if (isset(self::$camelCache[$value])) {
                return self::$camelCache[$value];
            }

            return self::$camelCache[$value] = lcfirst($this->studly($value));
        }

        /**
         * Determine if a given string contains a given substring.
         *
         * @param  string  $haystack
         * @param  string|array  $needles
         * @return bool
         */
        public function contains($haystack, $needles)
        {
            foreach ((array) $needles as $needle) {
                if ($needle != '' && strpos($haystack, $needle) !== false) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Determine if a given string ends with a given substring.
         *
         * @param  string  $haystack
         * @param  string|array  $needles
         * @return bool
         */
        public function endsWith($haystack, $needles)
        {
            foreach ((array) $needles as $needle) {
                if ((string) $needle === substr($haystack, -strlen($needle))) return true;
            }

            return false;
        }

        /**
         * Cap a string with a single instance of a given value.
         *
         * @param  string  $value
         * @param  string  $cap
         * @return string
         */
        public function finish($value, $cap)
        {
            $quoted = preg_quote($cap, '/');

            return preg_replace('/(?:' . $quoted . ')+$/', '', $value) . $cap;
        }

        /**
         * Return the length of the given string.
         *
         * @param  string  $value
         * @return int
         */
        public function length($value)
        {
            return mb_strlen($value);
        }

        /**
         * Limit the number of characters in a string.
         *
         * @param  string  $value
         * @param  int     $limit
         * @param  string  $end
         * @return string
         */
        public function limit($value, $limit = 100, $end = '...')
        {
            if (mb_strlen($value) <= $limit) {
                return $value;
            }

            return rtrim(mb_substr($value, 0, $limit, 'UTF-8')) . $end;
        }

        /**
         * Convert the given string to lower-case.
         *
         * @param  string  $value
         * @return string
         */
        public function lower($value)
        {
            return mb_strtolower($value);
        }

        /**
         * Limit the number of words in a string.
         *
         * @param  string  $value
         * @param  int     $words
         * @param  string  $end
         * @return string
         */
        public function words($value, $words = 100, $end = '...')
        {
            preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

            if (!isset($matches[0]) || strlen($value) === strlen($matches[0])) {
                return $value;
            }

            return rtrim($matches[0]) . $end;
        }

        /**
         * Parse a Class@method style callback into class and method.
         *
         * @param  string  $callback
         * @param  string  $default
         * @return array
         */
        public function parseCallback($callback, $default)
        {
            return $this->contains($callback, '@') ? explode('@', $callback, 2) : array($callback, $default);
        }

        /**
         * Generate a more truly "random" alpha-numeric string.
         *
         * @param  int  $length
         * @return string
         *
         * @throws \RuntimeException
         */
        public function random($length = 16)
        {
            $string = '';

            while (($len = strlen($string)) < $length) {
                $size = $length - $len;
                $bytes = $this->randomBytes($size);
                $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
            }

            return $string;
        }

        /**
         * Generate a more truly "random" bytes.
         *
         * @param  int  $length
         * @return string
         *
         * @throws \RuntimeException
         */
        public function randomBytes($length = 16)
        {
            if (function_exists('random_bytes')) {
                $bytes = random_bytes($length);
            } elseif (function_exists('openssl_random_pseudo_bytes')) {
                $bytes = openssl_random_pseudo_bytes($length, $strong);
                if ($bytes === false || $strong === false)
                {
                    throw new RuntimeException('Unable to generate random string.');
                }
            } else {
                throw new RuntimeException('OpenSSL extension is required for PHP 5 users.');
            }

            return $bytes;
        }

        /**
         * Generate a "random" alpha-numeric string.
         *
         * Should not be considered sufficient for cryptography, etc.
         *
         * @param  int  $length
         * @return string
         */
        public function quickRandom($length = 16)
        {
            $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

            return substr(str_shuffle(str_repeat($pool, $length)), 0, $length);
        }

        /**
         * Convert the given string to upper-case.
         *
         * @param  string  $value
         * @return string
         */
        public function upper($value)
        {
            return mb_strtoupper($value);
        }

        /**
         * Convert the given string to title case.
         *
         * @param  string  $value
         * @return string
         */
        public function title($value)
        {
            return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
        }

        /**
         * Generate a URL friendly "slug" from a given string.
         *
         * @param  string  $title
         * @param  string  $separator
         * @return string
         */
        public function slug($title, $separator = '-')
        {
            $title = $this->ascii($title);

            // Convert all dashes/underscores into separator
            $flip = $separator == '-' ? '_' : '-';

            $title = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $title);

            // Remove all characters that are not the separator, letters, numbers, or whitespace.
            $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', mb_strtolower($title));

            // Replace all separator characters and whitespace by a single separator
            $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);

            return trim($title, $separator);
        }

        /**
         * Convert a string to snake case.
         *
         * @param  string  $value
         * @param  string  $delimiter
         * @return string
         */
        public function snake($value, $delimiter = '_')
        {
            $key = $value.$delimiter;

            if (isset(self::$snakeCache[$key])) {
                return self::$snakeCache[$key];
            }

            if (!ctype_lower($value)) {
                $value = strtolower(preg_replace('/(.)(?=[A-Z])/', '$1' . $delimiter, $value));
            }

            return self::$snakeCache[$key] = $value;
        }

        /**
         * Determine if a given string starts with a given substring.
         *
         * @param  string  $haystack
         * @param  string|array  $needles
         * @return bool
         */
        public function startsWith($haystack, $needles)
        {
            foreach ((array) $needles as $needle) {
                if ($needle != '' && strpos($haystack, $needle) === 0) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Convert a value to studly caps case.
         *
         * @param  string  $value
         * @return string
         */
        public function studly($value)
        {
            $key = $value;

            if (isset(self::$studlyCache[$key])) {
                return self::$studlyCache[$key];
            }

            $value = ucwords(
                str_replace(
                    array('-', '_'),
                    ' ',
                    $value
                )
            );

            return self::$studlyCache[$key] = str_replace(' ', '', $value);
        }
    }
