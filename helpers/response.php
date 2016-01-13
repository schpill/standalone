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

    use ArrayObject;
    use Symfony\Component\HttpFoundation\Response as BaseResponse;
    use Symfony\Component\HttpFoundation\Cookie;

    class ResponseLib extends BaseResponse
    {
        /**
         * The original content of the response.
         *
         * @var mixed
         */
        public $original;

        /**
         * Set a header on the Response.
         *
         * @param  string  $key
         * @param  string  $value
         * @param  bool    $replace
         * @return $this
         */
        public function header($key, $value, $replace = true)
        {
            $this->headers->set($key, $value, $replace);

            return $this;
        }

        /**
         * Add a cookie to the response.
         *
         * @param  \Symfony\Component\HttpFoundation\Cookie  $cookie
         * @return $this
         */
        public function withCookie(Cookie $cookie)
        {
            $this->headers->setCookie($cookie);

            return $this;
        }

        /**
         * Get the original response content.
         *
         * @return mixed
         */
        public function getOriginalContent()
        {
            return $this->original;
        }

        /**
         * Determine if the given content should be turned into JSON.
         *
         * @param  mixed  $content
         * @return bool
         */
        protected function shouldBeJson($content)
        {
            return $content instanceof ArrayObject || is_array($content);
        }

        /**
         * Set the content on the response.
         *
         * @param  mixed  $content
         * @return $this
         */
        public function setContent($content)
        {
            $this->original = $content;

            if ($this->shouldBeJson($content)) {
                $this->header('Content-Type', 'application/json');

                $content = $this->morphToJson($content);
            }

            return parent::setContent($content);
        }

        /**
         * Morph the given content into JSON.
         *
         * @param  mixed   $content
         * @return string
         */
        protected function morphToJson($content)
        {
            return json_encode($content);
        }

        public function json($content)
        {
            $this->original = $content;

            if ($this->shouldBeJson($content)) {
                $content = $this->morphToJson($content);

                header('content-type: application/json; charset=utf-8');

                $response = thin('response');

                $response->setStatusCode(200, 'OK')
                ->sendHeaders()
                ->setContent($content)->send();

                exit;
            }
        }

        public function render($content)
        {
            $this->original = $content;

            header('content-type: text/html; charset=utf-8');

            $response = thin('response');

            $response->setStatusCode(200, 'OK')
            ->sendHeaders()
            ->setContent($content)->send();

            exit;
        }

        public function isForbidden()
        {
            header('content-type: text/html; charset=utf-8');

            $response = thin('response');

            $response->setStatusCode(403, 'Forbidden')
            ->sendHeaders()
            ->setContent('<h1>Forbidden</h1>')
            ->send();

            exit;
        }

        public function isError()
        {
            header('content-type: text/html; charset=utf-8');$response = thin('response');

            $response->setStatusCode(500, 'Internal Server Error')
            ->sendHeaders()
            ->setContent('<h1>Internal Server Error</h1>')
            ->send();

            exit;
        }

        public function is404()
        {
            header('content-type: text/html; charset=utf-8');

            $response = thin('response');

            $response->setStatusCode(404, 'Not Found')
            ->sendHeaders()
            ->setContent('<h1>Page not found</h1>')
            ->send();

            exit;
        }
    }
