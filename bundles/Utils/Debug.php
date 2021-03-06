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

    namespace Utils;

    class Debug
    {
        public static $argLength = 16;

        protected static $_filePath;

        /**
         * Retrieve real root path with last directory separator
         *
         * @return string
         */
        public static function getRootPath()
        {
            return defined('APPLICATION_PATH') ? APPLICATION_PATH : dirname(dirname(__FILE__));
        }

        /**
         * Prints or return a backtrace
         *
         * @param bool $return      return or print
         * @param bool $html        output in HTML format
         * @param bool $withArgs    add short argumets of methods
         * @return string|bool
         */
        public static function backtrace($return = false, $html = true, $withArgs = true)
        {
            $trace  = debug_backtrace();

            return self::trace($trace, $return, $html, $withArgs);
        }

        /**
         * Prints or return a trace
         *
         * @param array $trace      trace array
         * @param bool $return      return or print
         * @param bool $html        output in HTML format
         * @param bool $withArgs    add short argumets of methods
         * @return string|bool
         */
        public static function trace(array $trace, $return = false, $html = true, $withArgs = true)
        {
            $out = '';

            if ($html) {
                $out .= '<pre>';
            }

            foreach ($trace as $i => $data) {
                if ($i == 0) {
                    continue;
                }

                // prepare method argments
                $args = [];

                if (isset($data['args']) && $withArgs) {
                    foreach ($data['args'] as $arg) {
                        $args[] = self::_formatCalledArgument($arg);
                    }
                }

                if (isset($data['class']) && isset($data['function'])) {
                    if (isset($data['object']) && get_class($data['object']) != $data['class']) {
                        $className = get_class($data['object']) . '[' . $data['class'] . ']';
                    } else {
                        $className = $data['class'];
                    }

                    if (isset($data['object'])) {
                        $className .= sprintf(
                            '#%s#',
                            spl_object_hash($data['object'])
                        );
                    }

                    $methodName = sprintf('%s%s%s(%s)',
                        $className,
                        isset($data['type']) ? $data['type'] : '->',
                        $data['function'],
                        join(', ', $args)
                    );
                } else if (isset($data['function'])) {
                    $methodName = sprintf(
                        '%s(%s)',
                        $data['function'],
                        join(', ', $args)
                    );
                }

                if (isset($data['file'])) {
                    $pos = strpos($data['file'], self::getRootPath());
                    if ($pos !== false) {
                        $data['file'] = substr(
                            $data['file'],
                            strlen(self::getRootPath()) + 1
                        );
                    }
                    $fileName = sprintf(
                        '%s:%d',
                        $data['file'],
                        $data['line']
                    );
                } else {
                    $fileName = false;
                }

                if ($fileName) {
                    $out .= sprintf(
                        '#%d %s called at [%s]',
                        $i,
                        $methodName,
                        $fileName
                    );
                } else {
                    $out .= sprintf(
                        '#%d %s',
                        $i,
                        $methodName
                    );
                }

                $out .= "\n";
            }

            if ($html) {
                $out .= '</pre>';
            }

            if ($return) {
                return $out;
            } else {
                echo $out;

                return true;
            }
        }

        /**
         * Format argument in called method
         *
         * @param mixed $arg
         */
        protected static function _formatCalledArgument($arg)
        {
            $out = '';

            if (is_object($arg)) {
                $out .= sprintf(
                    "&%s#%s#",
                    get_class($arg),
                    spl_object_hash($arg)
                );

            } else if (is_resource($arg)) {
                $out .= '#[' . get_resource_type($arg) . ']';
            } else if (is_array($arg)) {
                $isAssociative = false;
                $args = [];

                foreach ($arg as $k => $v) {
                    if (!is_numeric($k)) {
                        $isAssociative = true;
                    }

                    $args[$k] = self::_formatCalledArgument($v);
                }

                if ($isAssociative) {
                    $arr = [];

                    foreach ($args as $k => $v) {
                        $arr[] = self::_formatCalledArgument($k) . ' => ' . $v;
                    }

                    $out .= 'array(' . join(', ', $arr) . ')';
                } else {
                    $out .= 'array(' . join(', ', $args) . ')';
                }
            } else if (is_null($arg)) {
                $out .= 'NULL';
            } else if (is_numeric($arg) || is_float($arg)) {
                $out .= $arg;
            } else if (is_string($arg)) {
                if (strlen($arg) > self::$argLength) {
                    $arg = substr($arg, 0, self::$argLength) . "...";
                }

                $arg = strtr(
                    $arg,
                    array(
                        "\t" => '\t',
                        "\r" => '\r',
                        "\n" => '\n',
                        "'" => '\\\''
                    )
                );

                $out .= "'" . $arg . "'";
            } else if (is_bool($arg)) {
                $out .= $arg === true ? 'true' : 'false';
            }

            return $out;
        }
    }
