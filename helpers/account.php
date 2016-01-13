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

    class AccountLib
    {
        private $fields = [
            'email',
            'password',
            'lastname',
            'firstname',
            'status_id',
            'genre_id',
            'avatar',
        ];

        public function create($args)
        {
            $points = isAke($args, 'points', false);

            if (false === $points) {
                $args['points'] = 0;
            }

            return Model::Account()->create($args)->save();
        }

        public function attach($account, $model)
        {
            if (!is_object($account)) {
                throw new Exception('Account must be a model.');
            }

            if (!is_object($model)) {
                throw new Exception('the second argument must be a model.');
            }

            $account->attach($model);

            return true;
        }

        public function detach($account, $model)
        {
            if (!is_object($account)) {
                throw new Exception('Account must be a model.');
            }

            if (!is_object($model)) {
                throw new Exception('the second argument must be a model.');
            }

            $account->detach($model);

            return true;
        }
    }
