<?php
    /**
     * Thin is a swift Framework for PHP 5.4+
     *
     * @package    Thin
     * @version    1.0
     * @author     Gerald Plusquellec
     * @license    BSD License
     * @copyright  1996 - 2016 Gerald Plusquellec
     * @link       http://github.com/schpill/thin
     */

    namespace Thin;

    class LogicLib
    {
        private $instance = null, $steps = [], $logics = [], $successes = [], $errors = [];

        public function __construct($instance = null)
        {
            $this->instance = is_null($instance) ? appli() : $instance;
        }

        public function step($k, $action, $success = null, $error = null, $args = [])
        {
            $this->steps[$k] = [
                'success'   => $success,
                'error'     => $error,
                'args'      => $args,
                'action'    => $action
            ];

            $this->logics[] = $k;

            return $this;
        }

        public function run()
        {
            foreach ($this->logics as $k) {
                $step = isAke($this->steps, $k, null);

                if ($step) {
                    $action = isAke($step, 'action', null);

                    if ($action) {
                        $a = array_merge(
                            isAke(
                                $step,
                                'action',
                                []
                            ),
                            [$this->instance]
                        );

                        $check = call_user_func_array(
                            $action,
                            $a
                        );

                        if ($check) {
                            $this->successes[]  = $k;
                            $success            = isAke($step, 'success', null);

                            if ($success) {
                                call_user_func_array(
                                    $success,
                                    [$this->instance]
                                );
                            }
                        } else {
                            $this->errors[] = $k;
                            $error          = isAke($step, 'error', null);

                            if ($error) {
                                call_user_func_array(
                                    $error,
                                    [$this->instance]
                                );
                            }
                        }
                    } else {
                        $this->errors[] = $k;
                        $error          = isAke($step, 'error', null);

                        if ($error) {
                            call_user_func_array(
                                $error,
                                [$this->instance]
                            );
                        }
                    }
                }
            }

            return [
                'successes' => $this->successes,
                'errors'    => $this->errors
            ];
        }

        public function __call($m, $a)
        {
            $action = array_shift($a);

            if (!empty($a)) {
                $success = array_shift($a);
            } else {
                $success = null;
            }

            if (!empty($a)) {
                $error = array_shift($a);
            } else {
                $error = null;
            }

            return $this->step(
                Inflector::uncamelize($m),
                $action,
                $success,
                $error,
                $a
            );
        }
    }
