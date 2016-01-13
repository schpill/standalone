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

    class CompanyLib
    {
        public function create($args)
        {
            return Model::Company()->create($args)->save();
        }

        public function attach($company, $model)
        {
            if (!is_object($company)) {
                throw new Exception('Company must be a model.');
            }

            if (!is_object($model)) {
                throw new Exception('the second argument must be a model.');
            }

            $company->attach($model);

            return true;
        }

        public function detach($company, $model)
        {
            if (!is_object($company)) {
                throw new Exception('Company must be a model.');
            }

            if (!is_object($model)) {
                throw new Exception('the second argument must be a model.');
            }

            $company->detach($model);

            return true;
        }
    }
