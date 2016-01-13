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

    class GuardLib
    {
          use Macroaable;

          private $user, $abilities = [], $policies = [];

          public function __construct($user, array $abilities = [], array $policies = [])
          {
               $this->user         = is_callable($user) ? $user() : $user;
               $this->abilities    = $abilities;
               $this->policies     = $policies;
          }

          public function allows($ability)
          {
               $closure = isAke($this->abilities, $ability, null);

               if ($closure) {
                    if (is_callable($closure)) {
                         return (bool) call_user_func_array($closure, [$this->user]);
                    }
               }

               return false;
          }

          public function denies($ability)
          {
               return !$this->allows($ability);
          }

          public function can($policy)
          {
               $closure = isAke($this->policies, $policy, null);

               if ($closure) {
                    if (is_callable($closure)) {
                         return (bool) call_user_func_array($closure, [$this->user]);
                    }
               }

               return false;
          }

          public function cannot($policy)
          {
               return !$this->can($policy);
          }

          public function setAbility($ability, callable $c)
          {
               $this->abilities[$ability] = $c;

               return $this;
          }

          public function setPolicy($policy, callable $c)
          {
               $this->policies[$policy] = $c;

               return $this;
          }
    }
