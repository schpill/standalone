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

    class FacturationLib
    {
        public function buy($zechallenge_id, $product_id, $quantity = 1)
        {
            return Model::FacturationPurchase()->create([
                'status'            => 'UNBILLED',
                'purchase_date'     => time(),
                'zechallenge_id'    => (int) $zechallenge_id,
                'product_id'        => (int) $product_id,
                'quantity'          => (int) $quantity
            ])->save()->id;
        }

        public function avoir($zechallenge_id, $product_id, $quantity = 1)
        {
            return Model::FacturationAvoir()->create([
                'status'            => 'UNBILLED',
                'purchase_date'     => time(),
                'zechallenge_id'    => (int) $zechallenge_id,
                'product_id'        => (int) $product_id,
                'quantity'          => (int) $quantity
            ])->save()->id;
        }

        public function acompte($zechallenge_id, $product_id, $amount, $quantity = 1)
        {
            return Model::FacturationAcompte()->create([
                'status'            => 'UNBILLED',
                'purchase_date'     => time(),
                'zechallenge_id'    => (int) $zechallenge_id,
                'product_id'        => (int) $product_id,
                'amount'            => (float) $amount,
                'quantity'          => (int) $quantity
            ])->save()->id;
        }

        public function getProductsByUniverseAndByPlatform($universe = 'resto', $platform = 'zeChallenge')
        {
            return Model::FacturationProduct()
            ->where(['univers', '=', $universe])
            ->where(['platform', '=', $platform])
            ->order('category_id')
            ->cursor();
        }

        public function abonnementChallenge($universe, $zechallenge_id = null)
        {
            $collection = [];
            $products = $this->getProductsByUniverseAndByPlatform($universe, 'zeChallenge');

            foreach ($products as $product) {
                if ($product['name'] == 'Inscription lancement' || $product['name'] == 'Inscription' || $product['name'] == 'Abonnement annuel' || $product['name'] == 'Article') {
                    if (!is_null($zechallenge_id)) {
                        $count = Model::FacturationPurchase()
                        ->where(['status', '=', 'UNBILLED'])
                        ->where(['product_id', '=', (int) $product['id']])
                        ->where(['zechallenge_id', '=', (int) $zechallenge_id])
                        ->count();

                        if ($count > 0) {
                            $product['factu'] = 1;
                        } else {
                            $product['factu'] = 0;
                        }
                    }

                    $collection[] = $product;
                }
            }

            return $collection;
        }

        public function abonnementMyzelift($universe, $zechallenge_id = null)
        {
            $collection = [];
            $products = $this->getProductsByUniverseAndByPlatform($universe, 'myZelift');

            foreach ($products as $product) {
                if (!is_null($zechallenge_id)) {
                    $count = Model::FacturationPurchase()
                    ->where(['status', '=', 'UNBILLED'])
                    ->where(['product_id', '=', (int) $product['id']])
                    ->where(['zechallenge_id', '=', (int) $zechallenge_id])
                    ->count();

                    if ($count > 0) {
                        $product['factu'] = 1;
                    } else {
                        $product['factu'] = 0;
                    }
                }

                $collection[] = $product;
            }

            return $collection;
        }

        public function makeSepa($reseller_id, $redo = false, $disposition = null)
        {
            $disposition = is_null($disposition) ? 'attachment' : $disposition;

            $reseller = Model::Reseller()->find((int) $reseller_id);

            if ($reseller) {
                $bi = Model::Inovibackend()->where(['reseller_id', '=', (int) $reseller_id])->first(true);

                if ($bi) {
                    $infos = $bi->toArray();

                    $url = isAke($infos, 'url_sepa', false);

                    if (!$url || $redo) {
                        $tpl = File::read(APPLICATION_PATH . DS . 'templates/sepa.html');

                        $tpl = str_replace(
                            [
                                '##ics##',
                                '##rum##',
                                '##inovi_id##',
                                '##iban##',
                                '##bic##',
                                '##adresse##',
                                '##code_postal##',
                                '##ville##',
                                '##banque##',
                                '##ville_banque##',
                                '##nom##',
                                '##corporate##',
                                '##pays##',
                                '##ville_signature##',
                                '##date_signature##',
                            ],
                            [
                                'zelift123456789',
                                $infos['id'] . date('dmY'),
                                $infos['id'],
                                $infos['iban'],
                                $infos['bic'],
                                $infos['address'],
                                $infos['zip'],
                                $infos['city'],
                                $infos['banque_nom'],
                                $infos['banque_ville'],
                                $infos['name'],
                                $infos['corporate_name'],
                                isAke($infos, 'country', 'France'),
                                isAke($infos, 'ville_signature', isAke($infos, 'city')),
                                date('d/m/Y', isAke($infos, 'start_adhesion', time())),
                            ],
                            $tpl
                        );

                        $bucket = new Bucket(SITE_NAME, 'http://zelift.com/bucket');

                        $pdf = pdfFile($tpl, 'Mandat-SEPA', 'portrait');

                        $url = $bucket->data($pdf, 'pdf');

                        $bi->url_sepa = $url;

                        if (!fnmatch('http://*', $url)) {
                            return ['url' => false, 'error' => $bi->url_sepa];
                        }

                        $bi->save();
                    } else {
                        $pdf = file_get_contents($url);
                    }

                    if ($disposition == 'attachment') {
                        return ['url' => $url, 'error' => false];
                    }

                    header('Content-Type: application/pdf');
                    header('Content-Length: ' . strlen($pdf));
                    header('Content-Disposition: ' . $disposition . '; filename="Mandat-SEPA.pdf"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');

                    ini_set('zlib.output_compression', '0');

                    die($pdf);
                }
            }

            return ['url' => false, 'error' => 'Revendeur inconnu'];
        }

        public function contrat(array $data, $disposition = null, $test = false)
        {
            $disposition = is_null($disposition) ? 'attachment' : $disposition;

            $tpl = File::read(APPLICATION_PATH . DS . 'templates/contrat_zechallenge.html');

            $bucket = new Bucket(SITE_NAME, 'http://zelift.com/bucket');

            $affiliation    = isAke($data, 'affiliation', 'resto');
            $univers        = isAke($data, 'univers', 'resto');
            $zechallenge_id = isAke($data, 'compte_zechallenge', 1);

            $contrat = Model::FacturationContrat()
            ->reset()
            ->where(['platform', '=', 'ZeChallenge'])
            ->where(['affiliation', '=', $affiliation])
            ->where(['univers', '=', $univers])
            ->where(['zechallenge_id', '=', (int) $zechallenge_id])
            ->first(true);

            if ($contrat && !$test) {
                $return = true;

                if ($disposition == 'attachment') {
                    if (!fnmatch('http://*', $contrat->url)) {
                        $contrat->delete();

                        $return = false;
                    }

                    if ($return) {
                        return ['url' => $contrat->url, 'error' => false];
                    }
                }

                if ($return) {
                    $pdf = dwn($contrat->url);

                    header('Content-Type: application/pdf');
                    header('Content-Length: ' . strlen($pdf));
                    header('Content-Disposition: ' . $disposition . '; filename="Contrat-ZeChallenge.pdf"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');

                    ini_set('zlib.output_compression', '0');

                    die($pdf);
                }
            }

            $contract = lib('contrat')->store($univers, 'ZeChallenge', $affiliation, $zechallenge_id);

            if ($univers == 'resto') {
                $code_contrat = 'RES';
            } elseif ($univers == 'services') {
                $code_contrat = 'SER';
            }

            $code_contrat .= '-C-' . $contract->id;

            $data['code_contrat'] = $code_contrat;

            foreach ($data as $k => $v) {
                if ($k == 'univers') {
                    $tpl = str_replace("##Univers##", ucfirst($v), $tpl);
                    $tpl = str_replace("##univers##", $v, $tpl);
                } else {
                    $tpl = str_replace("##$k##", $v, $tpl);
                }
            }

            $disposition = is_null($disposition) ? 'attachment' : $disposition;

            // $keep = lib('keep')->instance();

            // $keep['url'] = 'http://zelift.com/';

            $pdf = pdfFile($tpl, 'Contrat-ZeChallenge', 'portrait');

            $url = $bucket->data($pdf, 'pdf');

            $contract->url = $url;

            if (!fnmatch('http://*', $url)) {
                return ['url' => false, 'error' => $contract->url];
            }

            if (!$test) {
                $contract->save();
            }

            if ($disposition == 'attachment') {
                return ['url' => $url, 'error' => false];
            }

            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($pdf));
            header('Content-Disposition: ' . $disposition . '; filename="Contrat-ZeChallenge.pdf"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            ini_set('zlib.output_compression', '0');

            die($pdf);
        }

        // public function getUrlPdf($zechallenge_id)
    }
