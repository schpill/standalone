<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2013 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */
    namespace Thin;

    loader('loop');

    class PromiseLib
    {
        /**
         * The asynchronous operation is pending.
         */
        const PENDING = 0;

        /**
         * The asynchronous operation has completed, and has a result.
         */
        const FULFILLED = 1;

        /**
         * The asynchronous operation has completed with an error.
         */
        const REJECTED = 2;

        /**
         * The current state of this promise.
         *
         * @var int
         */
        public $state = self::PENDING;


        /**
         * A list of subscribers. Subscribers are the callbacks that want us to let
         * them know if the callback was fulfilled or rejected.
         *
         * @var array
         */
        protected $subscribers = [];

        /**
         * The result of the promise.
         *
         * If the promise was fulfilled, this will be the result value. If the
         * promise was rejected, this property hold the rejection reason.
         *
         * @var mixed
         */
        protected $value = null;

        /**
         * @param callable $executor
         */
        public function __construct(callable $executor = null)
        {
            if ($executor) {
                $executor(
                    [$this, 'fulfill'],
                    [$this, 'reject']
                );
            }
        }

        /**
         * @param callable $onFulfilled
         * @param callable $onRejected
         * @return Promise
         */
        public function then(callable $onFulfilled = null, callable $onRejected = null)
        {
            $subPromise = new self();

            switch ($this->state) {
                case self::PENDING :
                    $this->subscribers[] = [$subPromise, $onFulfilled, $onRejected];
                    break;
                case self::FULFILLED :
                    $this->invokeCallback($subPromise, $onFulfilled);
                    break;
                case self::REJECTED :
                    $this->invokeCallback($subPromise, $onRejected);
                    break;
            }

            return $subPromise;
        }

        /**
         * Add a callback for when this promise is rejected.
         *
         * Its usage is identical to then(). However, the otherwise() function is
         * preferred.
         *
         * @param callable $onRejected
         * @return Promise
         */
        public function otherwise(callable $onRejected)
        {
            return $this->then(null, $onRejected);
        }

        /**
         * Marks this promise as fulfilled and sets its return value.
         *
         * @param mixed $value
         * @return void
         */
        public function fulfill($value = null)
        {
            if ($this->state !== self::PENDING) {
                throw new Exception('This promise is already resolved, and you\'re not allowed to resolve a promise more than once');
            }

            $this->state = self::FULFILLED;
            $this->value = $value;

            foreach ($this->subscribers as $subscriber) {
                $this->invokeCallback($subscriber[0], $subscriber[1]);
            }
        }

        /**
         * Marks this promise as rejected, and set it's rejection reason.
         *
         * While it's possible to use any PHP value as the reason, it's highly
         * recommended to use an Exception for this.
         *
         * @param mixed $reason
         * @return void
         */
        public function reject($reason = null)
        {
            if ($this->state !== self::PENDING) {
                throw new Exception('This promise is already resolved, and you\'re not allowed to resolve a promise more than once');
            }

            $this->state = self::REJECTED;
            $this->value = $reason;

            foreach ($this->subscribers as $subscriber) {
                $this->invokeCallback($subscriber[0], $subscriber[2]);
            }
        }

        /**
         *
         * @throws Exception
         * @return mixed
         */
        public function wait()
        {
            $hasEvents = true;

            while ($this->state === self::PENDING) {
                if (!$hasEvents) {
                    throw new Exception('There were no more events in the loop. This promise will never be fulfilled.');
                }

                $hasEvents = tick(true);
            }

            if ($this->state === self::FULFILLED) {
                return $this->value;
            } else {
                $reason = $this->value;

                if ($reason instanceof Exception || $reason instanceof \Exception) {
                    throw $reason;
                } elseif (is_scalar($reason)) {
                    throw new Exception($reason);
                } else {
                    $type = is_object($reason) ? get_class($reason) : gettype($reason);

                    throw new Exception('Promise was rejected with reason of type: ' . $type);
                }
            }
        }

        /**
         * @param Promise $subPromise
         * @param callable $callBack
         * @return void
         */
        private function invokeCallback(Promise $subPromise, callable $callBack = null)
        {
            nextTick(function() use ($callBack, $subPromise) {
                if (is_callable($callBack)) {
                    try {
                        $result = $callBack($this->value);

                        if ($result instanceof self) {
                            $result->then([$subPromise, 'fulfill'], [$subPromise, 'reject']);
                        } else {
                            $subPromise->fulfill($result);
                        }
                    } catch (\Exception $e) {
                        // If the event handler threw an exception, we need to make sure that
                        // the chained promise is rejected as well.
                        $subPromise->reject($e);
                    }
                } else {
                    if ($this->state === self::FULFILLED) {
                        $subPromise->fulfill($this->value);
                    } else {
                        $subPromise->reject($this->value);
                    }
                }
            });
        }

        /**
         * Alias for 'otherwise'.
         *
         * This function is now deprecated and will be removed in a future version.
         *
         * @param callable $onRejected
         * @deprecated
         * @return PromiseLib
         */
        public function error(callable $onRejected)
        {
            return $this->otherwise($onRejected);
        }

        /**
         * Deprecated.
         *
         * @param Promise[] $promises
         * @deprecated
         * @return PromiseLib
         */
        public function all(array $promises)
        {
            return allPromises($promises);
        }
    }

    /**
     * This file contains a set of functions that are useful for dealing with the
     * Promise object.
     *
     * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
     * @author Evert Pot (http://evertpot.com/)
     * @license http://sabre.io/license/ Modified BSD License
     */


    /**
     * This function takes an array of Promises, and returns a Promise that
     * resolves when all of the given arguments have resolved.
     *
     * The returned Promise will resolve with a value that's an array of all the
     * values the given promises have been resolved with.
     *
     * This array will be in the exact same order as the array of input promises.
     *
     * If any of the given Promises fails, the returned promise will immidiately
     * fail with the first Promise that fails, and its reason.
     *
     * @param Promise[] $promises
     * @return PromiseLib
     */
    function allPromises(array $promises)
    {
        return new PromiseLib(function($success, $fail) use ($promises) {
            $successCount = 0;
            $completeResult = [];

            foreach ($promises as $promiseIndex => $subPromise) {
                $subPromise->then(
                    function($result) use ($promiseIndex, &$completeResult, &$successCount, $success, $promises) {
                        $completeResult[$promiseIndex] = $result;
                        $successCount++;
                        if ($successCount === count($promises)) {
                            $success($completeResult);
                        }
                        return $result;
                    }
                )->otherwise(
                    function($reason) use ($fail) {
                        $fail($reason);
                    }
                );
            }
        });
    }

    /**
     * The race function returns a promise that resolves or rejects as soon as
     * one of the promises in the argument resolves or rejects.
     *
     * The returned promise will resolve or reject with the value or reason of
     * that first promise.
     *
     * @param Promise[] $promises
     * @return Promise
     */
    function race(array $promises)
    {
        return new PromiseLib(function($success, $fail) use ($promises) {
            $alreadyDone = false;

            foreach ($promises as $promise) {
                $promise->then(
                    function($result) use ($success, &$alreadyDone) {
                        if ($alreadyDone) {
                            return;
                        }

                        $alreadyDone = true;
                        $success($result);
                    },
                    function($reason) use ($fail, &$alreadyDone) {
                        if ($alreadyDone) {
                            return;
                        }

                        $alreadyDone = true;
                        $fail($reason);
                    }
                );
            }
        });
    }

    /**
     * Returns a Promise that resolves with the given value.
     *
     * If the value is a promise, the returned promise will attach itself to that
     * promise and eventually get the same state as the followed promise.
     *
     * @param mixed $value
     * @return Promise
     */
    function resolvePromise($value)
    {
        if ($value instanceof PromiseLib) {
            return $value->then();
        } else {
            $promise = new PromiseLib();
            $promise->fulfill($value);

            return $promise;
        }

    }

    /**
     * Returns a Promise that will reject with the given reason.
     *
     * @param mixed $reason
     * @return Promise
     */
    function rejectPromise($reason)
    {
        $promise = new PromiseLib();
        $promise->reject($reason);

        return $promise;
    }

    use Generator;

    /**
     *
     * Example with rules:
     *
     * rules(function() {
     *
     *   try {
     *     yield $httpClient->request('GET', '/foo');
     *     yield $httpClient->request('DELETE', /foo');
     *     yield $httpClient->request('PUT', '/foo');
     *   } catch(\Exception $reason) {
     *     echo "Failed because: $reason\n";
     *   }
     *
     * });
     */
    function rules(callable $gen)
    {
        $generator = $gen();

        if (!$generator instanceof Generator) {
            throw new Exception('You must pass a generator function');
        }

        // This is the value we're returning.
        $promise = lib('promise');

        $lastYieldResult = null;

        /**
         * So tempted to use the mythical y-combinator here, but it's not needed in
         * PHP.
         */
        $advanceGenerator = function() use (&$advanceGenerator, $generator, $promise, &$lastYieldResult) {
            while ($generator->valid()) {
                $yieldedValue = $generator->current();

                if ($yieldedValue instanceof PromiseLib) {
                    $yieldedValue->then(
                        function($value) use ($generator, &$advanceGenerator, &$lastYieldResult) {
                            $lastYieldResult = $value;
                            $generator->send($value);
                            $advanceGenerator();
                        },
                        function($reason) use ($generator, $advanceGenerator) {
                            if ($reason instanceof Exception || $reason instanceof \Exception) {
                                $generator->throw($reason);
                            } elseif (is_scalar($reason)) {
                                $generator->throw(new Exception($reason));
                            } else {
                                $type = is_object($reason) ? get_class($reason) : gettype($reason);
                                $generator->throw(new Exception('Promise was rejected with reason of type: ' . $type));
                            }
                            $advanceGenerator();
                        }
                    )->otherwise(function($reason) use ($promise) {
                        // This error handler would be called, if something in the
                        // generator throws an exception, and it's not caught
                        // locally.
                        $promise->reject($reason);
                    });
                    // We need to break out of the loop, because $advanceGenerator
                    // will be called asynchronously when the promise has a result.
                    break;
                } else {
                    // If the value was not a promise, we'll just let it pass through.
                    $lastYieldResult = $yieldedValue;
                    $generator->send($yieldedValue);
                }

            }

            // If the generator is at the end, and we didn't run into an exception,
            // we can fullfill the promise with the last thing that was yielded to
            // us.
            if (!$generator->valid() && $promise->state === PromiseLib::PENDING) {
                $promise->fulfill($lastYieldResult);
            }
        };

        try {
            $advanceGenerator();
        } catch (\Exception $e) {
            $promise->reject($e);
        }

        return $promise;
    }
