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

    class ValidatorLib
    {
        use Macroable;

        private $rules, $messages;

        public function __construct(array $rules = [], array $messages = [])
        {
            $this->rules    = $rules;
            $this->messages = $messages;
        }

        public function validate(array $data = [])
        {
            $results = [];

            foreach ($data as $k => $v) {
                $results[$k] = [
                    'valid' => true
                ];

                $rule = isAke($this->rules, $k, null);

                if ($rule) {
                    if (is_callable($rule)) {
                        $check = $rule($v);

                        if ($check) {
                            $message = lib('array')->get($this->messages, "$k.success", "valid");
                        } else {
                            $message = lib('array')->get($this->messages, "$k.error", "error");
                        }

                        $results[$k] = [
                            'valid'     => $check,
                            'message'   => $message
                        ];
                    }
                }
            }

            return $results;
        }
    }
