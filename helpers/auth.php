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

    class AuthLib
    {
        protected $user;
        protected $lastAttempted;
        protected $viaRemember = false;
        protected $provider;
        protected $session;
        protected $cookie;
        protected $request;
        protected $events;
        protected $loggedOut = false;
        protected $tokenRetrievalAttempted = false;

        public function __construct($provider, $session)
        {
            $this->session = session($session);
            $this->request = lib('request');
            $this->provider = $provider;
        }

        /**
         * Determine if the current user is authenticated.
         *
         * @return bool
         */
        public function check()
        {
            return !is_null($this->user());
        }

        /**
         * Determine if the current user is a guest.
         *
         * @return bool
         */
        public function guest()
        {
            return !$this->check();
        }

        /**
         * Get the currently authenticated user.
         *
         */
        public function user()
        {
            if ($this->loggedOut) {
                return;
            }

            // If we have already retrieved the user for the current request we can just
            // return it back immediately. We do not want to pull the user data every
            // request into the method because that would tremendously slow an app.
            if (!is_null($this->user)) {
                return $this->user;
            }

            $id = $this->session->get($this->getName());

            // First we will try to load the user using the identifier in the session if
            // one exists. Otherwise we will check for a "remember me" cookie in this
            // request, and if one exists, attempt to retrieve the user using that.
            $user = null;

            if (!is_null($id)) {
                $user = $this->provider->find($id);
            }

            // If the user is null, but we decrypt a "recaller" cookie we can attempt to
            // pull the user data on that cookie which serves as a remember cookie on
            // the application. Once we have a user we can return it to the caller.
            $recaller = $this->getRecaller();

            if (is_null($user) && !is_null($recaller)) {
                $user = $this->getUserByRecaller($recaller);

                if ($user) {
                    $this->updateSession($user->id);

                    $this->fireLoginEvent($user, true);
                }
            }

            return $this->user = $user;
        }

        /**
         * Get the ID for the currently authenticated user.
         *
         * @return int|null
         */
        public function id()
        {
            if ($this->loggedOut) {
                return;
            }

            $id = $this->session->get($this->getName(), $this->getRecallerId());

            if (is_null($id) && $this->user()) {
                $id = $this->user()->id;
            }

            return $id;
        }

        /**
         * Pull a user from the repository by its recaller ID.
         *
         * @param  string  $recaller
         * @return mixed
         */
        protected function getUserByRecaller($recaller)
        {
            if ($this->validRecaller($recaller) && !$this->tokenRetrievalAttempted) {
                $this->tokenRetrievalAttempted = true;

                list($id, $token) = explode('|', $recaller, 2);

                $this->viaRemember = !is_null($user = $this->provider->where(['id', '=', (int) $id])->where(['token', '=', $token])->first(true));

                return $user;
            }
        }

        /**
         * Get the decrypted recaller cookie for the request.
         *
         * @return string|null
         */
        protected function getRecaller()
        {
            return $this->request->cookies->get($this->getRecallerName());
        }

        /**
         * Get the user ID from the recaller cookie.
         *
         * @return string
         */
        protected function getRecallerId()
        {
            if ($this->validRecaller($recaller = $this->getRecaller())) {
                return head(explode('|', $recaller));
            }
        }

        /**
         * Determine if the recaller cookie is in a valid format.
         *
         * @param  string  $recaller
         * @return bool
         */
        protected function validRecaller($recaller)
        {
            if (!is_string($recaller) || !fnmatch('*|*', $recaller)) {
                return false;
            }

            $segments = explode('|', $recaller);

            return count($segments) == 2 && trim($segments[0]) !== '' && trim($segments[1]) !== '';
        }

        /**
         * Log a user into the application without sessions or cookies.
         *
         * @param  array  $credentials
         * @return bool
         */
        public function once(array $credentials = [])
        {
            if ($this->validate($credentials)) {
                $this->setUser($this->lastAttempted);

                return true;
            }

            return false;
        }

        /**
         * Validate a user's credentials.
         *
         * @param  array  $credentials
         * @return bool
         */
        public function validate(array $credentials = [])
        {
            return $this->attempt($credentials, false, false);
        }

        /**
         * Attempt to authenticate using HTTP Basic Auth.
         *
         * @param  string  $field
         */
        public function basic($field = 'email')
        {
            if ($this->check()) {
                return;
            }

            // If a username is set on the HTTP basic request, we will return out without
            // interrupting the request lifecycle. Otherwise, we'll need to generate a
            // request indicating that the given credentials were invalid for login.
            if ($this->attemptBasic($this->getRequest(), $field)) {
                return;
            }

            return $this->getBasicResponse();
        }

        /**
         * Perform a stateless HTTP Basic login attempt.
         *
         * @param  string  $field
         */
        public function onceBasic($field = 'email')
        {
            if (!$this->once($this->getBasicCredentials($this->getRequest(), $field))) {
                return $this->getBasicResponse();
            }
        }

        /**
         * Attempt to authenticate using basic authentication.
         *
         * @param  string  $field
         * @return bool
         */
        protected function attemptBasic(RequestLib $request, $field)
        {
            if (!$request->getUser()) {
                return false;
            }

            return $this->attempt($this->getBasicCredentials($request, $field));
        }

        /**
         * Get the credential array for a HTTP Basic request.
         *
         * @param  string  $field
         * @return array
         */
        protected function getBasicCredentials(RequestLib $request, $field)
        {
            return [$field => $request->getUser(), 'password' => $request->getPassword()];
        }

        /**
         * Get the response for basic authentication.
         *
         */
        protected function getBasicResponse()
        {
            $headers = ['WWW-Authenticate' => 'Basic'];

            return new \Symfony\Component\HttpFoundation\Response('Invalid credentials.', 401, $headers);
        }

        /**
         * Attempt to authenticate a user using the given credentials.
         *
         * @param  array  $credentials
         * @param  bool   $remember
         * @param  bool   $login
         * @return bool
         */
        public function attempt(array $credentials = [], $remember = false, $login = true)
        {
            $this->fireAttemptEvent($credentials, $remember, $login);

            $q = $this->provider;

            foreach ($credentials as $k => $v) {
                $q->where([$k, '=', $v]);
            }

            $this->lastAttempted = $user = $q->cursor()->first(true);

            // If an implementation of UserInterface was returned, we'll ask the provider
            // to validate the user against the given credentials, and if they are in
            // fact valid we'll log the users into the application and return true.
            if ($this->hasValidCredentials($user, $credentials)) {
                if ($login) {
                    $this->login($user, $remember);
                }

                return true;
            }

            return false;
        }

        /**
         * Determine if the user matches the credentials.
         *
         * @param  mixed  $user
         * @param  array  $credentials
         * @return bool
         */
        protected function hasValidCredentials($user, $credentials)
        {
            $q = $this->provider;

            foreach ($credentials as $k => $v) {
                $q->where([$k, '=', $v]);
            }

            return !is_null($user) && $q->cursor()->count() > 0;
        }

        /**
         * Fire the attempt event with the arguments.
         *
         * @param  array  $credentials
         * @param  bool  $remember
         * @param  bool  $login
         * @return void
         */
        protected function fireAttemptEvent(array $credentials, $remember, $login)
        {
            if ($this->events) {
                $payload = [$credentials, $remember, $login];

                $this->events->fire('auth.attempt', $payload);
            }
        }

        /**
         * Register an authentication attempt event listener.
         *
         * @param  mixed  $callback
         * @return void
         */
        public function attempting($callback)
        {
            if ($this->events) {
                $this->events->listen('auth.attempt', $callback);
            }
        }

        /**
         * Log a user into the application.
         * @param  bool  $remember
         * @return void
         */
        public function login($user, $remember = false)
        {
            $this->updateSession($user->id);

            // If the user should be permanently "remembered" by the application we will
            // queue a permanent cookie that contains the encrypted copy of the user
            // identifier. We will then decrypt this later to retrieve the users.
            if ($remember) {
                $this->createRememberTokenIfDoesntExist($user);

                $this->queueRecallerCookie($user);
            }

            // If we have an event dispatcher instance set we will fire an event so that
            // any listeners will hook into the authentication events and run actions
            // based on the login and logout events fired from the guard instances.
            $this->fireLoginEvent($user, $remember);

            $this->setUser($user);
        }

        /**
         * Fire the login event if the dispatcher is set.
         *
         * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
         * @param  bool  $remember
         * @return void
         */
        protected function fireLoginEvent($user, $remember = false)
        {
            if (isset($this->events)) {
                $this->events->fire('auth.login', [$user, $remember]);
            }
        }

        /**
         * Update the session with the given ID.
         *
         * @param  string  $id
         * @return void
         */
        protected function updateSession($id)
        {
            $this->session->set($this->getName(), $id);
        }

        /**
         * Log the given user ID into the application.
         *
         * @param  mixed  $id
         * @param  bool   $remember
         */
        public function loginUsingId($id, $remember = false)
        {
            $this->session->set($this->getName(), $id);

            $this->login($user = $this->provider->find($id), $remember);

            return $user;
        }

        /**
         * Log the given user ID into the application without sessions or cookies.
         *
         * @param  mixed  $id
         * @return bool
         */
        public function onceUsingId($id)
        {
            if (!is_null($user = $this->provider->find($id))) {
                $this->setUser($user);

                return true;
            }

            return false;
        }

        /**
         * Queue the recaller cookie into the cookie jar.
         *
         * @return void
         */
        protected function queueRecallerCookie($user)
        {
            $value = $user->id . '|' . $user->token;

            $this->getCookieJar()->queue($this->createRecaller($value));
        }

        /**
         * Create a "remember me" cookie for a given ID.
         *
         * @param  string  $value
         * @return \Symfony\Component\HttpFoundation\Cookie
         */
        protected function createRecaller($value)
        {
            return $this->getCookieJar()->forever($this->getRecallerName(), $value);
        }

        /**
         * Log the user out of the application.
         *
         * @return void
         */
        public function logout()
        {
            $user = $this->user();

            // If we have an event dispatcher instance, we can fire off the logout event
            // so any further processing can be done. This allows the developer to be
            // listening for anytime a user signs out of this application manually.
            $this->clearUserDataFromStorage();

            if (!is_null($this->user)) {
                $this->refreshRememberToken($user);
            }

            if (isset($this->events)) {
                $this->events->fire('auth.logout', [$user]);
            }

            // Once we have fired the logout event we will clear the users out of memory
            // so they are no longer available as the user is no longer considered as
            // being signed into this application and should not be available here.
            $this->user = null;

            $this->loggedOut = true;
        }

        /**
         * Remove the user data from the session and cookies.
         *
         * @return void
         */
        protected function clearUserDataFromStorage()
        {
            $this->session->remove($this->getName());

            $recaller = $this->getRecallerName();

            $this->getCookieJar()->queue($this->getCookieJar()->forget($recaller));
        }

        /**
         * Refresh the "remember me" token for the user.
         *
         * @return void
         */
        protected function refreshRememberToken($user)
        {
            $user->setToken($token = Utils::token())->save();
        }

        /**
         * Create a new "remember me" token for the user if one doesn't already exist.
         *
         * @return void
         */
        protected function createRememberTokenIfDoesntExist($user)
        {
            if (empty($user->getToken())) {
                $this->refreshRememberToken($user);
            }
        }

        /**
         * Get the cookie creator instance used by the guard.
         *
         *
         * @throws \RuntimeException
         */
        public function getCookieJar()
        {
            if (!isset($this->cookie)) {
                throw new RuntimeException('Cookie jar has not been set.');
            }

            return $this->cookie;
        }

        /**
         * Set the cookie creator instance used by the guard.
         *
         * @param  \Illuminate\Contracts\Cookie\QueueingFactory  $cookie
         * @return void
         */
        public function setCookieJar(CookieJar $cookie)
        {
            $this->cookie = $cookie;
        }

        /**
         * Get the event dispatcher instance.
         *
         * @return \Illuminate\Contracts\Events\Dispatcher
         */
        public function getDispatcher()
        {
            return $this->events;
        }

        /**
         * Set the event dispatcher instance.
         *
         * @return void
         */
        public function setDispatcher(EventLib $events)
        {
            $this->events = $events;
        }

        /**
         * Get the session store used by the guard.
         *
         */
        public function getSession()
        {
            return $this->session;
        }

        /**
         * Get the user provider used by the guard.
         *
         */
        public function getProvider()
        {
            return $this->provider;
        }

        /**
         * Set the user provider used by the guard.
         *
         * @return void
         */
        public function setProvider($provider)
        {
            $this->provider = $provider;
        }

        /**
         * Return the currently cached user.
         *
         */
        public function getUser()
        {
            return $this->user;
        }

        /**
         * Set the current user.
         * @return void
         */
        public function setUser($user)
        {
            $this->user = $user;

            $this->loggedOut = false;
        }

        /**
         * Get the current request instance.
         *
         */
        public function getRequest()
        {
            return $this->request ?: lib('request');
        }

        /**
         * Set the current request instance.
         *
         * @param  \Symfony\Component\HttpFoundation\Request  $request
         * @return $this
         */
        public function setRequest(RequestLib $request)
        {
            $this->request = $request;

            return $this;
        }

        /**
         * Get the last user we attempted to authenticate.
         *
         * @return \Illuminate\Contracts\Auth\Authenticatable
         */
        public function getLastAttempted()
        {
            return $this->lastAttempted;
        }

        /**
         * Get a unique identifier for the auth session value.
         *
         * @return string
         */
        public function getName()
        {
            return 'login_' . md5(get_class($this));
        }

        /**
         * Get the name of the cookie used to store the "recaller".
         *
         * @return string
         */
        public function getRecallerName()
        {
            return 'remember_' . md5(get_class($this));
        }

        /**
         * Determine if the user was authenticated via "remember me" cookie.
         *
         * @return bool
         */
        public function viaRemember()
        {
            return $this->viaRemember;
        }
    }
