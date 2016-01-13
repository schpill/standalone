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

    class FacebookLib
    {
        private $appId;
        private $appSecret;

        /**
         * @param $appId Facebook Application ID
         * @param $appSecret Facebook Application secret
         */
        public function __construct($appId, $appSecret)
        {
            $this->appId        = $appId;
            $this->appSecret    = $appSecret;
            $this->session    	= session('fb');
        }

        /**
         * @param $redirectUrl
         * @return string|Facebook\GraphUser Login URL or GraphUser
         */
        public function connect($redirectUrl)
        {
            $fb = new \Facebook\Facebook([
              'app_id' => $this->appId,
              'app_secret' => $this->appSecret,
              'default_graph_version' => 'v2.5',
            ]);

            $helper = $fb->getRedirectLoginHelper();
            $permissions = ['email', 'user_likes', 'user_friends'];
            $loginUrl = $helper->getLoginUrl($redirectUrl, $permissions);

            return $loginUrl;
        }

        public function getSession()
        {
            $fb = new \Facebook\Facebook([
              'app_id' => $this->appId,
              'app_secret' => $this->appSecret,
              'default_graph_version' => 'v2.5',
            ]);

            $helper = $fb->getRedirectLoginHelper();

            try {
                $accessToken = $helper->getAccessToken();
            } catch(\Facebook\Exceptions\FacebookResponseException $e) {
                echo 'Graph returned an error: ' . $e->getMessage();
                exit;
            } catch(\Facebook\Exceptions\FacebookSDKException $e) {
                echo 'Facebook SDK returned an error: ' . $e->getMessage();
                exit;
            }

            if (isset($accessToken)) {
                $this->session->setToken((string) $accessToken);

                return (string) $accessToken;
            } elseif ($helper->getError()) {
                return false;
            }
        }

        public function me()
        {
            $fb = new \Facebook\Facebook([
              'app_id' => $this->appId,
              'app_secret' => $this->appSecret,
              'default_graph_version' => 'v2.5',
            ]);

            $token = $this->session->getToken();

            $response = $fb->get('/me?locale=en_US&fields=gender,last_name,email,first_name,verified', $token);
            $userNode = $response->getGraphNode();

            $email      = $userNode->getField('email');
            $name       = $userNode->getField('last_name');
            $firstname  = $userNode->getField('first_name');
            $gender     = $userNode->getField('gender');
            $verified   = $userNode->getField('verified');
            $id         = $userNode->getField('id');

            $this->session->setGender($gender)->setVerified($verified)->setName($name)->setFirstname($firstname)->setEmail($email)->setId($id);
        }
    }
