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

    class ControllerCore
    {
        public function boot()
        {
            $this->request  = core('request');
            $this->response = core('response');
            forever();

            core('registry')->set('app.controller', $this);

            $this->csrf = Utils::token();

            session('front')->setCsrf($this->csrf);
        }

        public function getCsrf()
        {
            return $this->csrf;
        }

        public function unboot()
        {

        }

        public function app($make = null, $parameters = [])
        {
            if (is_null($make)) {
                return core('container')->getInstance();
            }

            return core('container')->getInstance()->make($make, $parameters);
        }

        public function cache($ns = 'core')
        {
            return core('cache', [$ns]);
        }

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

        public function user($k, $d = null)
        {
            $session = session('front');
            $val = $session->getUser();

            return isAke($val, $k, $d);
        }

        public function session($k, $d = null)
        {
            $session = session('front');

            if (is_null($d)) {
                $getter = getter($k);

                $val = $session->$getter();

                return $val ? $val : $d;
            } else {
                $session->erase($k);
                $setter = setter($k);
                $session->$setter($d);
            }
        }

        public function redirectToUrl($url)
        {
            header("HTTP/1.1 301 Moved Permanently");
            header('Location: ' . $url);

            exit;
        }

        public function redirectToUri($uri)
        {
            header("HTTP/1.1 301 Moved Permanently");
            header('Location: ' . URSLITE . $uri);

            exit;
        }
    }
