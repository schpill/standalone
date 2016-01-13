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

    class ContratLib
    {
        public function store($univers, $platform, $affiliation, $zechallenge_id)
        {
            return Model::FacturationContrat()->refresh()->reset()->create([
                'start'             => time(),
                'end'               => strtotime('+1 year -1 day'),
                'univers'           => $univers,
                'platform'          => $platform,
                'affiliation'       => $affiliation,
                'zechallenge_id'    => (int) $zechallenge_id,
                'contract_date'     => time()
            ])->save();
        }
    }
