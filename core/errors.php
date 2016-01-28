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

    use Whoops;

    class ErrorsCore
    {
        public function init()
        {
            $whoops = new \Whoops\Run;

            $errorPage = new Whoops\Handler\PrettyPageHandler;
            $errorPage->setPageTitle('Oops! There was an error...');
            $whoops->pushHandler($errorPage);


            if (function_exists('Whoops\isAjaxRequest')) {
                if (Whoops\isAjaxRequest()) {
                    $whoops->pushHandler(new Whoops\Handler\JsonResponseHandler);
                }
            } else {
                $jsonPage = new Whoops\Handler\JsonResponseHandler;
                $jsonPage->onlyForAjaxRequests(true);
            }

            $whoops->pushHandler(function($exception, $inspector, $run) {
                try {
                    logg($exception->getMessage() . ' - Trace: ' . $exception->getTraceAsString(), 'critical');
                } catch (\Exception $e) {
                    echo $e;
                }
            }, 'log');

            $whoops->register();
        }
    }
