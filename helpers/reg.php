<?php

    namespace Thin;

    use InvalidArgumentException;


    class RegLib
    {
        /**
         * List of all loggers in the registry (ba named indexes)
         *
         * @var Logger[]
         */
        private static $loggers = array();

        /**
         * Adds new logging channel to the registry
         *
         * @param  Logger                    $app    Instance of the app channel
         * @param  string|null               $name      Name of the app channel ($app->getName() by default)
         * @param  boolean                   $overwrite Overwrite instance in the registry if the given name already exists?
         * @throws \InvalidArgumentException If $overwrite set to false and named Logger instance already exists
         */
        public static function addLogger(AppLib $app, $name = null, $overwrite = false)
        {
            $name = $name ?: $logger->getName();

            if (isset(self::$loggers[$name]) && !$overwrite) {
                throw new InvalidArgumentException('Logger with the given name already exists');
            }

            self::$loggers[$name] = $logger;
        }

        /**
         * Checks if such logging channel exists by name or instance
         *
         * @param string|Logger $logger Name or logger instance
         */
        public static function hasLogger($logger)
        {
            if ($logger instanceof Logger) {
                $index = array_search($logger, self::$loggers, true);

                return false !== $index;
            } else {
                return isset(self::$loggers[$logger]);
            }
        }

        /**
         * Removes instance from registry by name or instance
         *
         * @param string|Logger $logger Name or logger instance
         */
        public static function removeLogger($logger)
        {
            if ($logger instanceof Logger) {
                if (false !== ($idx = array_search($logger, self::$loggers, true))) {
                    unset(self::$loggers[$idx]);
                }
            } else {
                unset(self::$loggers[$logger]);
            }
        }

        /**
         * Clears the registry
         */
        public static function clear()
        {
            self::$loggers = array();
        }

        /**
         * Gets Logger instance from the registry
         *
         * @param  string                    $name Name of the requested Logger instance
         * @return Logger                    Requested instance of Logger
         * @throws \InvalidArgumentException If named Logger instance is not in the registry
         */
        public static function getInstance($name)
        {
            if (!isset(self::$loggers[$name])) {
                throw new InvalidArgumentException(sprintf('Requested "%s" logger instance is not in the registry', $name));
            }

            return self::$loggers[$name];
        }

        /**
         * Gets Logger instance from the registry via static method call
         *
         * @param  string                    $name      Name of the requested Logger instance
         * @param  array                     $arguments Arguments passed to static method call
         * @return Logger                    Requested instance of Logger
         * @throws \InvalidArgumentException If named Logger instance is not in the registry
         */
        public static function __callStatic($name, $arguments)
        {
            return self::getInstance($name);
        }
    }
