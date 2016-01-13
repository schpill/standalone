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

    use Closure;
    use ReflectionClass;

    class InstantiatorLib
    {
        const SERIALIZATION_FORMAT_USE_UNSERIALIZER   = 'C';
        const SERIALIZATION_FORMAT_AVOID_UNSERIALIZER = 'O';

        /**
         * @var \Closure[] of {@see \Closure} instances used to instantiate specific classes
         */
        private static $cachedInstantiators = array();

        /**
         * @var object[] of objects that can directly be cloned
         */
        private static $cachedCloneables = array();

        /**
         * {@inheritDoc}
         */
        public function instantiate($className)
        {
            if (isset(self::$cachedCloneables[$className])) {
                return clone self::$cachedCloneables[$className];
            }

            if (isset(self::$cachedInstantiators[$className])) {
                $factory = self::$cachedInstantiators[$className];

                return $factory();
            }

            return $this->buildAndCacheFromFactory($className);
        }

        /**
         * Builds the requested object and caches it in static properties for performance
         *
         * @param string $className
         *
         * @return object
         */
        private function buildAndCacheFromFactory($className)
        {
            $factory  = self::$cachedInstantiators[$className] = $this->buildFactory($className);
            $instance = $factory();

            if ($this->isSafeToClone(new ReflectionClass($instance))) {
                self::$cachedCloneables[$className] = clone $instance;
            }

            return $instance;
        }

        /**
         * Builds a {@see \Closure} capable of instantiating the given $className without
         * invoking its constructor.
         *
         * @param string $className
         *
         * @return Closure
         */
        private function buildFactory($className)
        {
            $reflectionClass = $this->getReflectionClass($className);

            if ($this->isInstantiableViaReflection($reflectionClass)) {
                return function () use ($reflectionClass) {
                    return $reflectionClass->newInstanceWithoutConstructor();
                };
            }

            $serializedString = sprintf(
                '%s:%d:"%s":0:{}',
                $this->getSerializationFormat($reflectionClass),
                strlen($className),
                $className
            );

            $this->checkIfUnSerializationIsSupported($reflectionClass, $serializedString);

            return function () use ($serializedString) {
                return unserialize($serializedString);
            };
        }

        /**
         * @param string $className
         *
         * @return ReflectionClass
         *
         * @throws InvalidArgumentException
         */
        private function getReflectionClass($className)
        {
            if (! class_exists($className)) {
                throw new Exception("This class [$className] does not exist.");
            }

            $reflection = new ReflectionClass($className);

            if ($reflection->isAbstract()) {
                throw new Exception("This class [$className] is abstract and cannot be instancied.");
            }

            return $reflection;
        }

        /**
         * @param ReflectionClass $reflectionClass
         * @param string          $serializedString
         *
         * @throws UnexpectedValueException
         *
         * @return void
         */
        private function checkIfUnSerializationIsSupported(ReflectionClass $reflectionClass, $serializedString)
        {
            $this->attemptInstantiationViaUnSerialization($reflectionClass, $serializedString);
        }

        /**
         * @param ReflectionClass $reflectionClass
         * @param string          $serializedString
         *
         * @throws UnexpectedValueException
         *
         * @return void
         */
        private function attemptInstantiationViaUnSerialization(ReflectionClass $reflectionClass, $serializedString)
        {
            try {
                unserialize($serializedString);
            } catch (\Exception $exception) {
                throw new Exception("UnSerialization Is not Supported");
            }
        }

        /**
         * @param ReflectionClass $reflectionClass
         *
         * @return bool
         */
        private function isInstantiableViaReflection(ReflectionClass $reflectionClass)
        {
            if (\PHP_VERSION_ID >= 50600) {
                return !($this->hasInternalAncestors($reflectionClass) && $reflectionClass->isFinal());
            }

            return \PHP_VERSION_ID >= 50400 && !$this->hasInternalAncestors($reflectionClass);
        }

        /**
         * Verifies whether the given class is to be considered internal
         *
         * @param ReflectionClass $reflectionClass
         *
         * @return bool
         */
        private function hasInternalAncestors(ReflectionClass $reflectionClass)
        {
            do {
                if ($reflectionClass->isInternal()) {
                    return true;
                }
            } while ($reflectionClass = $reflectionClass->getParentClass());

            return false;
        }

        /**
         * Verifies if the given PHP version implements the `Serializable` interface serialization
         * with an incompatible serialization format. If that's the case, use serialization marker
         * "C" instead of "O".
         *
         * @link http://news.php.net/php.internals/74654
         *
         * @param ReflectionClass $reflectionClass
         *
         * @return string the serialization format marker, either self::SERIALIZATION_FORMAT_USE_UNSERIALIZER
         *                or self::SERIALIZATION_FORMAT_AVOID_UNSERIALIZER
         */
        private function getSerializationFormat(ReflectionClass $reflectionClass)
        {
            if ($this->isPhpVersionWithBrokenSerializationFormat() && $reflectionClass->implementsInterface('Serializable')) {
                return self::SERIALIZATION_FORMAT_USE_UNSERIALIZER;
            }

            return self::SERIALIZATION_FORMAT_AVOID_UNSERIALIZER;
        }

        /**
         * Checks whether the current PHP runtime uses an incompatible serialization format
         *
         * @return bool
         */
        private function isPhpVersionWithBrokenSerializationFormat()
        {
            return PHP_VERSION_ID === 50429 || PHP_VERSION_ID === 50513;
        }

        /**
         * Checks if a class is cloneable
         *
         * @param ReflectionClass $reflection
         *
         * @return bool
         */
        private function isSafeToClone(ReflectionClass $reflection)
        {
            if (method_exists($reflection, 'isCloneable') && ! $reflection->isCloneable()) {
                return false;
            }

            // not cloneable if it implements `__clone`, as we want to avoid calling it
            return ! $reflection->hasMethod('__clone');
        }
    }
