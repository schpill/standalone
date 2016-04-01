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

	Class SyncLib
    {
		protected $coroutines = [];

		public function __construct($params)
        {
			$this->register($params);
		}

		public function register($params)
        {
			foreach ($params as $obj) {
				$this->coroutines[] = $obj;
			}

			return $this;
		}

		protected function tick()
        {
			foreach ($this->coroutines as $i => $co) {
				if (is_callable($co)) {
					$co = $this->coroutines[$i] = $co();

                    if (!$co instanceof \Generator) {
						throw new \InvalidArgumentException(sprintf("The axync worker (%s) is not valid", $i));
					}

					$co->rewind();
				} else if (!$co->valid()) {
					unset($this->coroutines[$i]);
				} else {
					$co->next();
				}
			}
		}

		protected function tickYield()
        {
			foreach ($this->coroutines as $i => $co) {
				if (is_callable($co)) {
					$co = $this->coroutines[$i] = $co();

                    if (!$co instanceof \Generator ) {
						throw new \InvalidArgumentException(sprintf("The axync worker (%s) is not valid", $i));
					}

					$co->rewind();
				} else if ( ! $co->valid() ) {
					unset($this->coroutines[$i]);
				} else {
					$co->next();
				}

				yield;
			}
		}

		public function exec()
        {
			while (!empty($this->coroutines)) {
				$this->tick();
			}

			return $this;
		}

		public function toGenerator()
        {
			return function() {
				while (!empty($this->coroutines)) {
					yield from $this->tickYield();
				}
			};
		}
	}
