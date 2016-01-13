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

    class FactureLib
    {
        public function firstZechallenge($reseller_id, $disposition = null)
        {
            $bucket      = new Bucket(SITE_NAME, 'http://zelift.com/bucket');
            $disposition = is_null($disposition) ? 'attachment' : $disposition;

            $reseller = Model::Reseller()->find((int) $reseller_id);

            if ($reseller) {
                $zid = $reseller->zechallenge_id;
                $zechallenge = Model::Zechallenge()->find((int) $zid);

                if ($zechallenge) {
                    $contract = Model::FacturationContrat()
                    ->where(['zechallenge_id', '=', (int) $zid])
                    ->where(['platform', '=', 'ZeChallenge'])->first(true);

                    if (!$contract) {
                        exception("facture", 'Aucun contrat zechallenge trouvé.');
                    }

                    $products = lib('facturation')->abonnementChallenge(
                        lib('zechallenge')->getContext($reseller->id)
                    );

                    $toBilled = $acomptes = $hasAcomptes = [];

                    foreach ($products as $product) {
                        $haveTobeBilled = Model::FacturationAcompte()
                        ->where(['status', '=', 'UNBILLED'])
                        ->where(['product_id', '=', (int) $product['id']])
                        ->where(['zechallenge_id', '=', (int) $zid])
                        ->with('product');

                        foreach ($haveTobeBilled as $hasToBilled) {
                            $acomptes[] = $hasToBilled;
                            $hasAcomptes[$hasToBilled['product_id']] = true;
                        }

                        $haveTobeBilled = Model::FacturationPurchase()
                        ->where(['status', '=', 'UNBILLED'])
                        ->where(['product_id', '=', (int) $product['id']])
                        ->where(['zechallenge_id', '=', (int) $zid])
                        ->with('product');

                        foreach ($haveTobeBilled as $hasToBilled) {
                            if (!isset($hasAcomptes[$hasToBilled['product_id']])) {
                                $toBilled[] = $hasToBilled;
                            }
                        }
                    }

                    $total = 0;

                    $details = [];

                    foreach ($toBilled as $hastoBilled) {
                        $sum = $hastoBilled['quantity'] * $hastoBilled['product']['amount'];
                        $total += $sum;

                        $details[] = '<tr style="border:1px solid #a4a4a4;border-top:none;">
                    <td style="width:500px;">
                        <div style="width:500px; text-indent:30px; height:25px; line-height:25px;">
                            ' . $hastoBilled['product']['name'] . '
                        </div>
                    </td>
                    <td style="width:300px; font-size:0; padding:0;">
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;">' . number_format($hastoBilled['product']['amount'], 2) . '€</div>
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;">' . $hastoBilled['quantity'] . '</div>
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;"></div>
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;">
                            ' . number_format($sum, 2) . '€
                        </div>
                    </td>
                </tr>';
                    }

                    foreach ($acomptes as $hastoBilled) {
                        $sum = $hastoBilled['quantity'] * $hastoBilled['amount'];
                        $total += $sum;

                        $details[] = '<tr style="border:1px solid #a4a4a4;border-top:none;">
                    <td style="width:500px;">
                        <div style="width:500px; text-indent:30px; height:25px; line-height:25px;">
                            ' . $hastoBilled['product']['name'] . ' (Acompte)
                        </div>
                    </td>
                    <td style="width:300px; font-size:0; padding:0;">
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;">' . number_format($hastoBilled['amount'], 2) . '€</div>
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;">' . $hastoBilled['quantity'] . '</div>
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;"></div>
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;">
                            ' . number_format($sum, 2) . '€
                        </div>
                    </td>
                </tr>';
                    }

                    $facture = Model::FacturationFacture()->firstOrCreate([
                        'zechallenge_id' => $zid,
                        'products' => $toBilled
                    ]);

                    $tva = number_format(round($total * 0.2, 2), 2);
                    $ttc = number_format(($total + $tva), 2);

                    $details = implode("\n", $details);

                    $tpl = File::read(APPLICATION_PATH . DS . 'templates/premiere_facture_zechallenge.html');

                    $ib = Model::Inovibackend()->find((int) $reseller->inovibackend_id);

                    $tpl = str_replace([
                        '##typoColor##',
                        '##typo##',
                        '##no_facture##',
                        '##compte_inovi##',
                        '##no_contrat##',
                        '##univers##',
                        '##affil##',
                        '##nom_client##',
                        '##adresse_client##',
                        '##cp_client##',
                        '##ville_client##',
                        '##lieu_contrat##',
                        '##date_contrat##',
                        '##date_debut##',
                        '##date_fin##',
                        '##date_jour##',
                        '##total##',
                        '##total_tva##',
                        '##total_ttc##',
                        '##details##',
                    ], [
                        '#6e358b',
                        'ZeChallenge',
                        $facture->id,
                        $reseller->inovibackend_id,
                        $zid,
                        lib('zechallenge')->getMarket($reseller->id),
                        lib('segment')->getAffiliation($reseller->id, false),
                        $ib->name,
                        $ib->address,
                        $ib->zip,
                        $ib->city,
                        $ib->city,
                        date('d/m/Y', $contract->contract_date),
                        date('d/m/Y', $contract->contract_date),
                        date('d/m/Y', $contract->end),
                        date('d/m/Y'),
                        number_format($total, 2),
                        $tva,
                        $ttc,
                        $details,
                    ], $tpl);

                    $pdf = pdfFile($tpl, 'Facture-ZeChallenge', 'portrait');

                    $url = $bucket->data($pdf, 'pdf');

                    if (!fnmatch('http://*', $url)) {
                        return ['url' => false, 'error' => $url];
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
            }
        }

        public function firstMyZelift($reseller_id, $disposition = null)
        {
            $bucket      = new Bucket(SITE_NAME, 'http://zelift.com/bucket');
            $disposition = is_null($disposition) ? 'attachment' : $disposition;

            $reseller = Model::Reseller()->find((int) $reseller_id);

            if ($reseller) {
                $zid = $reseller->zechallenge_id;
                $myzelift = Model::Myzelift()->where(['zechallenge_id', '=', (int) $zid])->first(true);

                if ($myzelift) {
                    $contract = Model::FacturationContrat()
                    ->where(['zechallenge_id', '=', (int) $zid])
                    ->where(['platform', '=', 'MyZeLift'])->first(true);

                    if (!$contract) {
                        exception("facture", 'Aucun contrat zechallenge trouvé.');
                    }

                    $products = lib('facturation')->abonnementMyzelift(
                        lib('zechallenge')->getContext($reseller->id)
                    );

                    $toBilled = $acomptes = $hasAcomptes = $purchases = [];

                    foreach ($products as $product) {
                        $haveTobeBilled = Model::FacturationAcompte()
                        ->where(['status', '=', 'UNBILLED'])
                        ->where(['product_id', '=', (int) $product['id']])
                        ->where(['zechallenge_id', '=', (int) $zid])
                        ->with('product');

                        foreach ($haveTobeBilled as $hasToBilled) {
                            $purchases[] = $hasToBilled;
                            $acomptes[] = $hasToBilled;
                            $hasAcomptes[$hasToBilled['product_id']] = true;
                        }

                        $haveTobeBilled = Model::FacturationPurchase()
                        ->where(['status', '=', 'UNBILLED'])
                        ->where(['product_id', '=', (int) $product['id']])
                        ->where(['zechallenge_id', '=', (int) $zid])
                        ->with('product');

                        foreach ($haveTobeBilled as $hasToBilled) {
                            if (!isset($hasAcomptes[$hasToBilled['product_id']])) {
                                $purchases[] = $toBilled[] = $hasToBilled;
                            }
                        }
                    }

                    // dd($toBilled);

                    $total = 0;

                    $details = [];

                    foreach ($toBilled as $hastoBilled) {
                        $sum = $hastoBilled['quantity'] * $hastoBilled['product']['amount'];
                        $total += $sum;

                        $details[] = '<tr style="border:1px solid #a4a4a4;border-top:none;">
                    <td style="width:500px;">
                        <div style="width:500px; text-indent:30px; height:25px; line-height:25px;">
                            ' . $hastoBilled['product']['name'] . '
                        </div>
                    </td>
                    <td style="width:300px; font-size:0; padding:0;">
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;">' . number_format($hastoBilled['product']['amount'], 2) . '€</div>
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;">' . $hastoBilled['quantity'] . '</div>
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;"></div>
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;">
                            ' . number_format($sum, 2) . '€
                        </div>
                    </td>
                </tr>';
                    }

                    foreach ($acomptes as $hastoBilled) {
                        $sum = $hastoBilled['quantity'] * $hastoBilled['amount'];
                        $total += $sum;
                        $normalPrice = $hastoBilled['product']['amount'] * $hastoBilled['quantity'];

                        $pc = ($sum / $normalPrice) * 100;

                        $details[] = '<tr style="border:1px solid #a4a4a4;border-top:none;">
                    <td style="width:500px;">
                        <div style="width:500px; text-indent:30px; height:25px; line-height:25px;">
                            ' . $hastoBilled['product']['name'] . ' (Acompte de ' . $pc . ' %)
                        </div>
                    </td>
                    <td style="width:300px; font-size:0; padding:0;">
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;">' . number_format($hastoBilled['amount'], 2) . '€</div>
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;">' . $hastoBilled['quantity'] . '</div>
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;"></div>
                        <div style="width:75px; display:inline-block; font-size:15px; text-align:center;">
                            ' . number_format($sum, 2) . '€
                        </div>
                    </td>
                </tr>';
                    }

                    $facture = Model::FacturationFacture()->refresh()->firstOrCreate([
                        'zechallenge_id' => $zid,
                        'products' => $purchases
                    ]);

                    $tva = number_format(round($total * 0.2, 2), 2);
                    $ttc = number_format(($total + $tva), 2);

                    $details = implode("\n", $details);

                    $tpl = File::read(APPLICATION_PATH . DS . 'templates/premiere_facture_zechallenge.html');

                    $ib = Model::Inovibackend()->find((int) $reseller->inovibackend_id);

                    $tpl = str_replace([
                        '##typoColor##',
                        '##typo##',
                        '##no_facture##',
                        '##compte_inovi##',
                        '##no_contrat##',
                        '##univers##',
                        '##affil##',
                        '##nom_client##',
                        '##adresse_client##',
                        '##cp_client##',
                        '##ville_client##',
                        '##lieu_contrat##',
                        '##date_contrat##',
                        '##date_debut##',
                        '##date_fin##',
                        '##date_jour##',
                        '##total##',
                        '##total_tva##',
                        '##total_ttc##',
                        '##details##',
                    ], [
                        '#ba68c8',
                        'MyZeLift',
                        $facture->id,
                        $reseller->inovibackend_id,
                        $zid,
                        lib('zechallenge')->getMarket($reseller->id),
                        lib('segment')->getAffiliation($reseller->id, false),
                        $ib->name,
                        $ib->address,
                        $ib->zip,
                        $ib->city,
                        $ib->city,
                        date('d/m/Y', $contract->contract_date),
                        date('d/m/Y', $contract->contract_date),
                        date('d/m/Y', $contract->end),
                        date('d/m/Y'),
                        number_format($total, 2),
                        $tva,
                        $ttc,
                        $details,
                    ], $tpl);

                    $pdf = pdfFile($tpl, 'Facture-MyZeLift', 'portrait');

                    $url = $bucket->data($pdf, 'pdf');

                    if (!fnmatch('http://*', $url)) {
                        return ['url' => false, 'error' => $url];
                    }

                    if ($disposition == 'attachment') {
                        return ['url' => $url, 'error' => false];
                    }

                    header('Content-Type: application/pdf');
                    header('Content-Length: ' . strlen($pdf));
                    header('Content-Disposition: ' . $disposition . '; filename="Contrat-MyZeLift.pdf"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');

                    ini_set('zlib.output_compression', '0');

                    die($pdf);
                }
            }
        }
    }
