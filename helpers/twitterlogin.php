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

    require_once(APPLICATION_PATH . '/lib/twitteroauth/twitteroauth.php');

    use TwitterOAuth;

    class TwitterloginLib
    {
        public function getUrl()
        {
            $session = session('social_twitter');

            $connection     = new TwitterOAuth(Config::get('twitter.key'), Config::get('twitter.secret'));
            $request_token  = $connection->getRequestToken(URLSITE . 'twitter/auth');

            $token          = $request_token['oauth_token'];
            $secret         = $request_token['oauth_token_secret'];

            $session->setToken($token);
            $session->setSecret($secret);

            return $connection->getAuthorizeURL($token);
        }

        public function getContent()
        {
            $session        = session('social_twitter');
            $token          = $session->getToken();
            $secret         = $session->getSecret();

            $connection     = new TwitterOAuth(Config::get('twitter.key'), Config::get('twitter.secret'), $token, $secret);
            $access_token   = $connection->getAccessToken($_REQUEST['oauth_verifier']);

            $session->setAccess($access_token);

            return $connection->get('account/verify_credentials');
        }
    }
