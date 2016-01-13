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

    class LoopLib
    {
        /**
         * Is the main loop active
         *
         * @var bool
         */
        protected $running = false;

        /**
         * A list of timers, added by setTimeout.
         *
         * @var array
         */
        protected $timers = [];

        /**
         * A list of 'nextTick' callbacks.
         *
         * @var callable[]
         */
        protected $nextTick = [];

        /**
         * List of readable streams for stream_select, indexed by stream id.
         *
         * @var resource[]
         */
        protected $readStreams = [];

        /**
         * List of writable streams for stream_select, indexed by stream id.
         *
         * @var resource[]
         */
        protected $writeStreams = [];

        /**
         * List of read callbacks, indexed by stream id.
         *
         * @var callback[]
         */
        protected $readCallbacks = [];

        /**
         * List of write callbacks, indexed by stream id.
         *
         * @var callback[]
         */
        protected $writeCallbacks = [];
        /**
         * Executes a function after x seconds.
         *
         * @param callable $cb
         * @param float $timeout timeout in seconds
         * @return void
         */

        public function setTimeout(callable $cb, $timeout)
        {
            $triggerTime = microtime(true) + ($timeout);

            if (!$this->timers) {
                // Special case when the timers array was empty.
                $this->timers[] = [$triggerTime, $cb];

                return;
            }

            // We need to insert these values in the timers array, but the timers
            // array must be in reverse-order of trigger times.
            //
            // So here we search the array for the insertion point.
            $index = count($this->timers) - 1;

            while (true) {
                if ($triggerTime < $this->timers[$index][0]) {
                    array_splice(
                        $this->timers,
                        $index + 1,
                        0,
                        [[$triggerTime, $cb]]
                    );

                    break;
                } elseif ($index === 0) {
                    array_unshift($this->timers, [$triggerTime, $cb]);

                    break;
                }

                $index--;
            }
        }

        /**
         * Executes a function every x seconds.
         *
         * The value this function returns can be used to stop the interval with
         * clearInterval.
         *
         * @param callable $cb
         * @param float $timeout
         * @return array
         */
        public function setInterval(callable $cb, $timeout)
        {
            $keepGoing = true;
            $f = null;

            $f = function() use ($cb, &$f, $timeout, &$keepGoing) {
                if ($keepGoing) {
                    $cb();
                    $this->setTimeout($f, $timeout);
                }
            };

            $this->setTimeout($f, $timeout);

            return ['detail', &$keepGoing];
        }

        /**
         * Stops a running internval.
         *
         * @param array $intervalId
         * @return void
         */
        public function clearInterval($intervalId)
        {
            $intervalId[1] = false;
        }

        /**
         * Runs a function immediately at the next iteration of the loop.
         *
         * @param callable $cb
         * @return void
         */
        public function nextTick(callable $cb)
        {
            $this->nextTick[] = $cb;
        }


        /**
         * Adds a read stream.
         *
         * The callback will be called as soon as there is something to read from
         * the stream.
         *
         * You MUST call removeReadStream after you are done with the stream, to
         * prevent the eventloop from never stopping.
         *
         * @param resource $stream
         * @param callable $cb
         * @return void
         */
        public function addReadStream($stream, callable $cb)
        {
            $this->readStreams[(int)$stream] = $stream;
            $this->readCallbacks[(int)$stream] = $cb;
        }

        /**
         * Adds a write stream.
         *
         * The callback will be called as soon as the system reports it's ready to
         * receive writes on the stream.
         *
         * You MUST call removeWriteStream after you are done with the stream, to
         * prevent the eventloop from never stopping.
         *
         * @param resource $stream
         * @param callable $cb
         * @return void
         */
        public function addWriteStream($stream, callable $cb)
        {
            $this->writeStreams[(int)$stream] = $stream;
            $this->writeCallbacks[(int)$stream] = $cb;
        }

        /**
         * Stop watching a stream for reads.
         *
         * @param resource $stream
         * @return void
         */
        public function removeReadStream($stream)
        {
            unset(
                $this->readStreams[(int)$stream],
                $this->readCallbacks[(int)$stream]
            );
        }

        /**
         * Stop watching a stream for writes.
         *
         * @param resource $stream
         * @return void
         */
        public function removeWriteStream($stream)
        {
            unset(
                $this->writeStreams[(int)$stream],
                $this->writeCallbacks[(int)$stream]
            );
        }


        /**
         * Runs the loop.
         *
         * This function will run continiously, until there's no more events to
         * handle.
         *
         * @return void
         */
        public function run()
        {
            $this->running = true;

            do {
                $hasEvents = $this->tick(true);
            } while ($this->running && $hasEvents);

            $this->running = false;
        }

        /**
         * Executes all pending events.
         *
         * If $block is turned true, this function will block until any event is
         * triggered.
         *
         * If there are now timeouts, nextTick callbacks or events in the loop at
         * all, this function will exit immediately.
         *
         * This function will return true if there are _any_ events left in the
         * loop after the tick.
         *
         * @param bool $block
         * @return bool
         */
        public function tick($block = false)
        {
            $this->runNextTicks();
            $nextTimeout = $this->runTimers();

            // Calculating how long runStreams should at most wait.
            if (!$block) {
                // Don't wait
                $streamWait = 0;
            } elseif ($this->nextTick) {
                // There's a pending 'nextTick'. Don't wait.
                $streamWait = 0;
            } elseif (is_numeric($nextTimeout)) {
                // Wait until the next Timeout should trigger.
                $streamWait = $nextTimeout;
            } else {
                // Wait indefinitely
                $streamWait = null;
            }

            $this->runStreams($streamWait);

            return ($this->readStreams || $this->writeStreams || $this->nextTick || $this->timers);
        }

        /**
         * Stops a running eventloop
         *
         * @return void
         */
        public function stop()
        {
            $this->running = false;
        }

        /**
         * Executes all 'nextTick' callbacks.
         *
         * return void
         */
        protected function runNextTicks()
        {
            $nextTick = $this->nextTick;
            $this->nextTick = [];

            foreach ($nextTick as $cb) {
                $cb();
            }
        }

        /**
         * Runs all pending timers.
         *
         * After running the timer callbacks, this function returns the number of
         * seconds until the next timer should be executed.
         *
         * If there's no more pending timers, this function returns null.
         *
         * @return float
         */
        protected function runTimers()
        {
            $now = microtime(true);

            while (($timer = array_pop($this->timers)) && $timer[0] < $now) {
                $timer[1]();
            }

            // Add the last timer back to the array.
            if ($timer) {
                $this->timers[] = $timer;
                return $timer[0] - microtime(true);
            }
        }

        /**
         * Runs all pending stream events.
         *
         * @param float $timeout
         */
        protected function runStreams($timeout)
        {
            if ($this->readStreams || $this->writeStreams) {

                $read = $this->readStreams;
                $write = $this->writeStreams;
                $except = null;

                if (stream_select($read, $write, $except, null, $timeout)) {
                    // See PHP Bug https://bugs.php.net/bug.php?id=62452
                    // Fixed in PHP7
                    foreach ($read as $readStream) {
                        $readCb = $this->readCallbacks[(int) $readStream];
                        $readCb();
                    }
                    foreach ($write as $writeStream) {
                        $writeCb = $this->writeCallbacks[(int) $writeStream];
                        $writeCb();
                    }
                }
            } elseif ($this->running && ($this->nextTick || $this->timers)) {
                usleep($timeout !== null ? $timeout * 1000000 : 200000);
            }
        }
    }

    /**
     * Executes a function after x seconds.
     *
     * @param callable $cb
     * @param float $timeout timeout in seconds
     * @return void
     */
    function setTimeout(callable $cb, $timeout)
    {
        loop()->setTimeout($cb, $timeout);
    }

    /**
     * Executes a function every x seconds.
     *
     * The value this function returns can be used to stop the interval with
     * clearInterval.
     *
     * @param callable $cb
     * @param float $timeout
     * @return array
     */
    function setInterval(callable $cb, $timeout)
    {
        return loop()->setInterval($cb, $timeout);
    }

    /**
     * Stops a running internval.
     *
     * @param array $intervalId
     * @return void
     */
    function clearInterval($intervalId) {
        loop()->clearInterval($intervalId);
    }

    /**
     * Runs a function immediately at the next iteration of the loop.
     *
     * @param callable $cb
     * @return void
     */
    function nextTick(callable $cb)
    {
        loop()->nextTick($cb);
    }


    /**
     * Adds a read stream.
     *
     * The callback will be called as soon as there is something to read from
     * the stream.
     *
     * You MUST call removeReadStream after you are done with the stream, to
     * prevent the eventloop from never stopping.
     *
     * @param resource $stream
     * @param callable $cb
     * @return void
     */
    function addReadStream($stream, callable $cb)
    {
        loop()->addReadStream($stream, $cb);
    }

    /**
     * Adds a write stream.
     *
     * The callback will be called as soon as the system reports it's ready to
     * receive writes on the stream.
     *
     * You MUST call removeWriteStream after you are done with the stream, to
     * prevent the eventloop from never stopping.
     *
     * @param resource $stream
     * @param callable $cb
     * @return void
     */
    function addWriteStream($stream, callable $cb)
    {
        loop()->addWriteStream($stream, $cb);
    }

    /**
     * Stop watching a stream for reads.
     *
     * @param resource $stream
     * @return void
     */
    function removeReadStream($stream)
    {
        loop()->removeReadStream($stream);
    }

    /**
     * Stop watching a stream for writes.
     *
     * @param resource $stream
     * @return void
     */
    function removeWriteStream($stream)
    {
        loop()->removeWriteStream($stream);
    }


    /**
     * Runs the loop.
     *
     * This function will run continiously, until there's no more events to
     * handle.
     *
     * @return void
     */
    function runLoop()
    {
        loop()->run();
    }

    /**
     * Executes all pending events.
     *
     * If $block is turned true, this function will block until any event is
     * triggered.
     *
     * If there are now timeouts, nextTick callbacks or events in the loop at
     * all, this function will exit immediately.
     *
     * This function will return true if there are _any_ events left in the
     * loop after the tick.
     *
     * @param bool $block
     * @return bool
     */
    function tick($block = false)
    {
        return loop()->tick($block);
    }

    /**
     * Stops a running eventloop
     *
     * @return void
     */
    function stopLoop()
    {
        loop()->stop();
    }

    /**
     * Retrieves or sets the global Loop object.
     *
     * @param Loop $newLoop
     */
    function loop(Loop $newLoop = null)
    {
        static $loop;

        if ($newLoop) {
            $loop = $newLoop;
        } elseif (!$loop) {
            $loop = lib('loop');
        }

        return $loop;
    }
