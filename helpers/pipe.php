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

    class PipeLib
    {
        private $steps = [];

        public function __construct(array $steps = [])
        {
            $this->steps = $steps;
        }

        public function add($step)
        {
            $steps = $this->steps;
            $steps[] = $step;

            return new self($steps);
        }

        public function process($payload)
        {
            $reducer = function ($payload, $step) {
                if (is_callable($step)) {
                    return call_user_func($step, $payload);
                } else {
                    return $step->process($payload);
                }
            };

            return array_reduce($this->steps, $reducer, $payload);
        }
    }
