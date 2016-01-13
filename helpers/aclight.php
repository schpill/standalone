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

    class AclightLib
    {
        private $ns;

        public function __construct($ns = 'core')
        {
            $this->ns = $ns;
        }

        public function users(array $users)
        {
            foreach ($users as $user) {
                $this->setUser($user);
            }
        }

        public function setUser($user)
        {
            $users = Now::get('acl.users.' . $this->ns, []);

            $exists = false;

            foreach ($users as $u) {
                if ($u['username'] == $user['username']) {
                    $exists = true;

                    break;
                }
            }

            if (!$exists) {
                $users[] = $user;
            }

            Now::set('acl.users.' . $this->ns, $users);

            return $this;
        }

        public function getUser($username)
        {
            return coll(Now::get('acl.users.' . $this->ns, []))->firstBy('username', $username);
        }

        public function getValue($username, $field, $default = null)
        {
            $row = coll(Now::get('acl.users.' . $this->ns, []))->firstBy('username', $username);

            if ($row) {
                return isAke($row, $field, $default);
            }

            return $default;
        }

        public function getPassword($username)
        {
            return $this->getValue($username, 'password');
        }

        public function can($username, $resource, $action)
        {
            $role = $this->getValue($username, 'role');

            if ($role == 'admin') {
                return true;
            }

            return $this->check($role, $resource, $action);
        }

        public function cannot($username, $resource, $action)
        {
            return !$this->can($username, $resource, $action);
        }

        public function __call($m, $a)
        {
            $i = lib('acl')->getInstance($this->ns);

            return call_user_func_array([$i, $m], $a);
        }
    }
