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

    namespace Api;

    use Thin\Api;

    class Page
    {
        private $token, $method;

        public function success(array $array)
        {
            $array['status'] = 200;
            Api::render($array);
        }

        public function error(array $array)
        {
            $array['status'] = 500;
            Api::render($array);
        }

        public function status(array $array, $status = 200)
        {
            $array['status'] = $status;
            Api::render($array);
        }

        public function can()
        {
            $this->method = strtolower(\Thin\Request::method());

            if (in_array($this->method, ['post', 'put', 'delete'])) {
                $_POST = json_decode(file_get_contents('php://input'), true);

                $this->token = isAke($_POST, 'token', false);

                if ($this->token) {
                    $this->checkToken();
                } else {
                    Api::forbidden();
                }
            }
        }

        public function getToken()
        {
            return $this->token;
        }

        public function getMethod()
        {
            return $this->method;
        }

        private function checkToken()
        {
            $row = Model::ApiAuth()->where(['token', '=', $this->token])->first(true);

            if ($row) {
                $row->setExpiration(time() + 3600)->save();

                return true;
            }

            Api::forbidden();
        }
    }
