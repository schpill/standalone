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

    use SplFileObject;
    use ReflectionClass;

    class AnnotationsLib
    {
        private $_class;

        /**
         * The token list.
         *
         * @var array
         */
        private $tokens;

        /**
         * The number of tokens.
         *
         * @var int
         */
        private $numTokens;

        /**
         * The current array pointer.
         *
         * @var int
         */
        private $pointer = 0;

        public function __construct($className)
        {
            $this->_class = new ReflectionClass($className);
        }

        public function parse()
        {
            $class = $this->_class;

            if (false === $filename = $class->getFilename()) {
                return [];
            }

            $content = $this->getFileContent($filename);

            $annotations = [];

            foreach ($content as $row) {
                $row = trim($row);

                if (fnmatch('*@@*', $row) && (strstr($row, '*') || strstr($row, '//'))) {
                    $annotation = str_replace(['* @@', ' @@', '//', '/*', ' * ', '* '], '', $row);

                    $annotations[] = $annotation;
                }
            }

            return $annotations;
        }

        private function getFileContent($filename)
        {
            if (!is_file($filename)) {
                return null;
            }

            return file($filename);
        }
    }
