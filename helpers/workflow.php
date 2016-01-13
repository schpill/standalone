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

    class WorkflowLib
    {
        private $steps = [], $validated = [], $step, $finished = false;

        public function addStep(callable $action, callable $check, $key = null)
        {
            $key = is_null($key) ? 'step_' . count($this->steps) + 1 : $key;

            $this->steps[$key] = [
                'action'    => $action,
                'check'     => $check
            ];

            return $this;
        }

        public function run($key, $args = [])
        {
            $row    = isAke($this->steps, $key, []);
            $action = isAke($row, 'action', false);

            if ($action) {
                if (is_callable($action)) {
                    $args[] = $this;

                    return call_user_func_array($action, $args);
                }
            }

            throw new Exception('Step ' . $key . ' does not exist in this workflow.');
        }

        public function check($key, $args = [])
        {
            $row    = isAke($this->steps, $key, []);
            $check  = isAke($row, 'check', false);

            if ($check) {
                if (is_callable($check)) {
                    $args[] = $this;

                    return call_user_func_array($check, $args);
                }
            }

            throw new Exception('Step ' . $key . ' does not exist in this workflow.');
        }

        public function next($args_action = [], $args_check = [], $step = null)
        {
            if ($this->finished) {
                return 'This workflow is terminated.';
            }

            if (!isset($this->step)) {
                $this->step = 1;
            }

            $step   = is_null($step) ? $this->step : $step;

            $key    = 'step_' . $step;

            $this->run($key, $args_action);

            $check = $this->check($key, $args_check);

            if ($check) {
                $this->step++;

                if (count($this->steps) < $this->step) {
                    $this->step--;
                    $this->finished = true;
                }

                if (!in_array($key, $this->validated)) {
                    $this->validated[] = $key;
                }

                return true;
            }

            return false;
        }

        public function start($args_action = null, $args_check = [])
        {
            $this->finished     = false;
            $this->validated    = [];
            $this->step         = 1;

            return is_null($args_action) ? $this : $this->next($args_action, $args_check);
        }

        public function getSteps()
        {
            return array_keys($this->steps);
        }

        public function __call($m, $a)
        {
            if (fnmatch('step_*', $m)) {
                $args   = $a;
                $args[] = (int) str_replace('step_', '', $m);

                return call_user_func_array([$this, 'next'], $args);
            }
        }
    }
