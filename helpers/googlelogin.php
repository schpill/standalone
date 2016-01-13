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

    use OAuth\OAuth2\Service\Google;
    use OAuth\Common\Storage\Session as OauThSession;
    use OAuth\Common\Consumer\Credentials;
    use OAuth\ServiceFactory;

    class GoogleloginLib
    {
        public function getUrl()
        {
            $serviceFactory = new ServiceFactory();
            $storage        = new OauThSession();

            $credentials    = new Credentials(
                Config::get('google.key'),
                Config::get('google.secret'),
                URLSITE . 'google/auth'
            );

            $googleService = $serviceFactory->createService('google', $credentials, $storage, array('userinfo_email', 'userinfo_profile'));

            return (string) $googleService->getAuthorizationUri();
        }

        public function getContent()
        {
            $serviceFactory = new ServiceFactory();
            $storage        = new OauThSession();

            $credentials    = new Credentials(
                Config::get('google.key'),
                Config::get('google.secret'),
                URLSITE . 'google/auth'
            );

            $googleService = $serviceFactory->createService('google', $credentials, $storage, array('userinfo_email', 'userinfo_profile'));

            $state = isset($_GET['state']) ? $_GET['state'] : null;

            // This was a callback request from google, get the token
            $googleService->requestAccessToken($_GET['code'], $state);

            // Send a request with it
            return json_decode($googleService->request('userinfo'), true);
        }
    }
