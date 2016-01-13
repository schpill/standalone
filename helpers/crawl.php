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
    use Dbredis\Caching;

    class CrawlLib
    {
        public function sos()
        {
            ini_set('memory_limit', '1024M');

            $cache = Caching::instance('crawl.sos');

            require_once APPLICATION_PATH . DS . '..' . '/public/vendeur/lib/simple_html_dom.php';

            set_time_limit(0);

            $regions = $cache->get('regions', []);

            if (empty($regions)) {
                $html = $cache->get('html.regions');

                if (empty($html)) {
                    $html = dwn('http://www.starofservice.com/annuaire');
                    $cache->set('html.regions', $html);
                }

                $html = str_get_html($html);

                $div    = $html->find('.region', 0);
                $div    = $div->find('.standard-container', 0);
                $ul     = $div->find('ul', 0);
                $lis    = $ul->find('li');

                foreach ($lis as $li) {
                    $a      = $li->find('a', 0);
                    $href   = $a->attr['href'];

                    $regions[] = Arrays::last(explode('/', $href));
                }

                $cache->set('regions', $regions);
            }

            $departments = $cache->get('departments', []);

            if (empty($departments)) {
                foreach ($regions as $region) {
                    $html = $cache->get('html.region.' . $region);

                    if (empty($html)) {
                        $html = dwn("http://www.starofservice.com/annuaire/region/$region");
                        $cache->set('html.region.' . $region, $html);
                    }

                    $html = str_get_html($html);

                    $div    = $html->find('.region', 0);
                    $div    = $div->find('.standard-container', 0);
                    $ul     = $div->find('ul', 0);
                    $lis    = $ul->find('li');

                    foreach ($lis as $li) {
                        $a      = $li->find('a', 0);
                        $href   = $a->attr['href'];

                        $tab = explode('/' . $region . '/', $href);

                        $department = $region . '/' . $tab[1];

                        $departments[] = $department;
                    }
                }

                $cache->set('departments', $departments);
            }

            $count = 0;

            // foreach ($departments as $department) {
            //     $cache  = redis();
            //     $html   = $cache->get('html.department.' . str_replace('/', '.', $department));

            //     list($r, $d) = explode('/', $department, 2);

            //     if (empty($html)) {
            //         $html = dwn("http://www.starofservice.com/annuaire/departement/$department");
            //         $cache->set('html.department.' . str_replace('/', '.', $department), $html);
            //     }
            // }

            // dd('termine');

            foreach ($departments as $department) {
                $cache  = redis();
                $html   = $cache->get('html.department.' . str_replace('/', '.', $department));

                list($r, $d) = explode('/', $department, 2);

                if (empty($html)) {
                    $html = dwn("http://www.starofservice.com/annuaire/departement/$department");
                    $cache->set('html.department.' . str_replace('/', '.', $department), $html);
                }

                $html   = Utils::cut('<div class="region">', '</div>', $html);
                $ul     = Utils::cut('<ul>', '</ul>', $html);
                $lis    = explode('<li>', $ul);

                array_shift($lis);

                foreach ($lis as $li) {
                    $li     = trim(str_replace(["\t", "\n", "\r"], "", $li));
                    $a      = Utils::cut('<a ', '/a>', $li);
                    $href   = Utils::cut('href="', '"', $a);
                    $tab = explode('/', $href);

                    array_shift($tab);
                    array_shift($tab);

                    $row = [];

                    $row['name']        = end($tab);
                    $row['href']        = $href;
                    $row['region']      = $r;
                    $row['department']  = $d;
                    $row['category']    = $tab[0];
                    $sos_id             = $row['sosid'] = (int) $tab[2];
                    $where              = $tab[1];

                    $row['zip']        = null;

                    if (fnmatch('*-*', $where)) {
                        $tabWhere       = explode('-', $where);

                        $row['zip']     = (int) array_shift($tabWhere);
                        $row['city']    = implode(" ", $tabWhere);

                        if (is_numeric($row['city'])) {
                            $row['zip']     = (int) $row['city'];
                            $row['city']    = null;
                        }
                    } else {
                        $row['city'] = $where;
                    }

                    $row['rs'] = str_replace('-', ' ', end($tab));

                    // $exists = $cache->get('company.' . $sos_id);

                    // if (!$exists) {
                        rdb('data', 'sos')->create($row)->insert();
                        $count++;

                        echo "$count => $r, $d [" . current($tab) . "] $sos_id " . end($tab) . "\n";

                        // $cache->set('company.' . $sos_id, $service->id);
                    // }
                }
            }
        }

        public function sosFind($sosId)
        {
            $cache = redis();
            require_once APPLICATION_PATH . DS . '..' . '/public/vendeur/lib/simple_html_dom.php';

            $row = rdb('data', 'sos')->where(['sosid', '=', $sosId])->first(true);

            if ($row) {
                $html = $cache->get('sos.html.' . $sosId);

                if (empty($html)) {
                    $html = dwn('http://www.starofservice.com' . $row->href);

                    if (fnmatch('*http-equiv="refresh"*', $html)) {
                        $url = Utils::cut('content="0;url=', '"/>', $html);
                        $html = dwn($url);
                    }

                    $cache->set('sos.html.' . $sosId, $html);
                }

                $zip        = (int) Utils::cut('<span itemprop="postalCode">', '<', $html);
                $city       = Utils::cut('<span itemprop="addressLocality">', '<', $html);
                $tel        = Utils::cut('<div class="offre-meta opt">', '<', $html);
                $name       = html_entity_decode(Utils::cut('<span itemprop="name">', '<', $html));
                $latLong    = str_replace(' ', '', Utils::cut('LatLng: {', '}', $html));

                $description = str_replace('&#039;', "'", Utils::cut('<meta name="description" content="', '"', $html));

                list($lat, $lng) = explode(',lng:', $latLong, 2);

                $lat = (float) str_replace('lat:', '', $lat);
                $lng = (float) $lng;

                $hours = Utils::cut('<div id="offre-hours">', '</div>', $html);
                $dhours = Utils::cut('<tbody>', '</tbody>', $hours);

                $name = str_replace('&#039;', "'", $name);
                $city = str_replace('&#039;', "'", $city);

                $hours = [];

                if (fnmatch('*<tr>*', $dhours)) {
                    $tab = explode('<tr>', $dhours);
                    array_shift($tab);

                    foreach ($tab as $day) {
                        $tabDay = explode('<td', $day);
                        array_shift($tabDay);

                        list($dummy, $d) = explode('>', $tabDay[0]);
                        list($dummy, $h) = explode('>', $tabDay[1]);

                        $d = str_replace('</td', '', $d);
                        $h = str_replace('</td', '', $h);

                        $hours[$d] = $h;
                    }
                }

                if (strstr($html, 'Numéro de SIRET')) {
                    $seg = Utils::cut('<a rel="nofollow" target="_blank" href="http://www.infogreffe.fr/"', 'p>', $html);
                    $siret = Utils::cut('<br />', '<', $seg);

                    if ($siret) {
                        $siret = str_replace(' ', '', $siret);
                        dd($siret);
                    }
                }

                $row->name          = $name;
                $row->hours         = $hours;
                $row->zip           = (int) $zip;
                $row->city          = $city;
                $row->tel           = $tel;
                $row->lat           = $lat;
                $row->lng           = $lng;
                $row->description   = $description;

                error_log($row->name . ' ' . $row->zip . ' ' . $row->city . ' ' . $tel . ' ' . $lat . ' ' . $description);

                return $row->save();
            }

            return false;
        }

        public function fetchDepartment($dpt)
        {
            set_time_limit(0);

            $rows = rdb('data', 'sos')->where(['department', '=', $dpt])->exec();

            $count = 0;

            foreach ($rows as $row) {
                $this->sosFind($row['sosid']);
                $count++;

                error_log($count);
            }

            die('termine');
        }

        public function getSiretByApeAndCityAndDpt($ape, $city, $dpt)
        {
            ini_set('memory_limit', '1024M');
            $cityM = Model::City()->where(['name', '=', (string) strtolower($city)])->first(true);

            if (!$cityM) {
                return false;
            }

            $zip = $cityM->zip;
            $d = substr($zip, 0, 2);

            if ($dpt != $d) {
                return false;
            }

            require_once APPLICATION_PATH . DS . '..' . '/public/vendeur/lib/simple_html_dom.php';

            set_time_limit(0);

            $cache  = redis();
            $url    = 'http://www.societe.com/cgi-bin/liste?ens=on&nom=&ape=' . $ape .'&adr=&num=&ville=' . $city . '&dep=' . $d;
            $key    = 'siret.ape.city.dep.' . $ape . '.' . $city . '.' . $dpt;

            $cached = $cache->get($key);

            if (empty($cached)) {
                $html = dwn($url);

                $cache->set($key, $html);
                $cache->expire($key, strtotime('+1 month') - time());
            } else {
                $html = $cached;
            }

            $html = str_get_html($html);

            $links = $html->find('.linkresult');

            $sirens = [];

            foreach ($links as $link) {
                $href = $link->attr['href'];
                $siren = (int) str_replace('.html', '', Arrays::last(explode('-', $href)));

                $sirens[] = $siren;
            }

            $data = [];

            foreach ($sirens as $siren) {
                $infos = $this->siren($siren);

                if (isset($infos['code_postal'])) {
                    $cp = $infos['code_postal'];

                    $d = (int) substr($cp, 0, 2);

                    if (!is_null($infos['siret']) && $dpt == $d) {
                        $data[] = $infos;
                    }
                }
            }

            dd($data);
        }

        public function getSiretByApeAndDepartment($ape, $dpt = 21)
        {
            ini_set('memory_limit', '1024M');

            require_once APPLICATION_PATH . DS . '..' . '/public/vendeur/lib/simple_html_dom.php';

            set_time_limit(0);

            $cache  = redis();
            $url    = 'http://www.societe.com/cgi-bin/liste?nom=&dirig=&pre=&dep=' . $dpt . '&ape=' . $ape . '&rec=&exa';
            $key    = 'siret.ape.dpt.' . $ape . '.' . $dpt;

            $cached = $cache->get($key);

            if (empty($cached)) {
                $html = dwn($url);

                $cache->set($key, $html);
                $cache->expire($key, strtotime('+1 month') - time());
            } else {
                $html = $cached;
            }

            $html = str_get_html($html);

            $links = $html->find('.linkresult');

            $sirens = [];

            foreach ($links as $link) {
                $href = $link->attr['href'];
                $siren = (int) str_replace('.html', '', Arrays::last(explode('-', $href)));

                $sirens[] = $siren;
            }

            $data = [];

            foreach ($sirens as $siren) {
                $infos = $this->siren($siren);

                if (isset($infos['code_postal'])) {
                    $cp = $infos['code_postal'];

                    $d = (int) substr($cp, 0, 2);

                    if (!is_null($infos['siret']) && $dpt == $d) {
                        $data[] = $infos;
                    }
                }
            }

            dd($data);
        }

        public function siren($siren)
        {
            require_once APPLICATION_PATH . DS . '..' . '/public/vendeur/lib/simple_html_dom.php';

            set_time_limit(0);

            $key = 'siren.' . $siren;
            $cached = redis()->get($key);

            if (empty($cached)) {
                $html = dwn("http://www.societe.com/cgi-bin/fiche?rncs=$siren&mode=prt");
                redis()->set($key, $html);
            } else {
                $html = $cached;
            }

            $html = utf8_encode($html);

            $infos = [];

            $html = str_get_html($html);
            $table = $html->find('table[id=rensjur]', 0);

            if (is_object($table)) {
                $trs = $table->find('tr');

                foreach ($trs as $tr) {
                    $tds = $tr->find('td');

                    $first = true;

                    foreach ($tds as $td) {
                        if ($first) {
                            $attribute = Inflector::urlize($td->innertext, '_');
                        } else {
                            $value = $td->innertext;
                        }

                        $first = false;
                    }

                    $attribute = str_replace(
                        ['denomination', 'description_de_l_activite_de_l_entreprise', 'siret_siege', 'tranche_d_effectif'],
                        ['raison_sociale', 'activite', 'siret', 'effectif'],
                        $attribute
                    );

                    if ($attribute == 'activite') {
                        continue;
                    }

                    $infos[$attribute] = $value;
                }
            }

            $table = $html->find('table[id=rensjurcomplete]', 0);

            if (is_object($table)) {
                $trs = $table->find('tr');

                foreach ($trs as $tr) {
                    $tds = $tr->find('td');

                    $first = true;

                    foreach ($tds as $td) {
                        if ($first) {
                            $attribute = Inflector::urlize($td->innertext, '_');
                        } else {
                            $value = $td->innertext;
                        }

                        $first = false;
                    }

                    $attribute = str_replace(
                        ['denomination', 'description_de_l_activite_du_siege', 'description_de_l_activite_de_l_entreprise', 'siret_siege', 'tranche_d_effectif', 'code_ape_naf_de_l_entreprise', 'code_ape_naf_du_siege', 'n_dossier'],
                        ['raison_sociale', 'activite_siege', 'activite_entreprise', 'siret', 'effectif', 'naf_entrprise', 'naf_siege', 'numero_dossier'],
                        $attribute
                    );

                    $infos[$attribute] = $value;
                }

                if (isset($infos['adresse_rcs'])) {
                    $infos['adresse'] = $infos['adresse_rcs'];
                }

                if (isset($infos['adresse_insee'])) {
                    $infos['adresse'] = $infos['adresse_insee'];
                }

                if (isset($infos['complement_nom_adressage'])) {
                    $infos['nom_commercial'] = $infos['complement_nom_adressage'];
                    unset($infos['complement_nom_adressage']);
                }

                $infos['code_postal']       = $infos['code_postal'];
                $infos['department_code']   = substr($infos['code_postal'], 0, 2);

                $department = Model::Department()->where(['code', '=', (string) $infos['department_code']])->first(true);

                if($department) {
                    $infos['department_id'] = $department->id;
                    $infos['department'] = $department->name;
                    $region = $department->region(true);

                    if ($region) {
                        $infos['region_id'] = $region->id;
                        $infos['region'] = $region->name;
                    }
                }

                if (isset($infos['adresse'])) {
                    $coords = getCoords($infos['adresse'] . ' ' . $infos['code_postal'] . ' ' . $infos['ville']);

                    $lat = isAke($coords, 'lat', false);
                    $lng = isAke($coords, 'lng', false);

                    if (false !== $lat) {
                        $infos['lat'] = $lat;
                    }

                    if (false !== $lng) {
                        $infos['lng'] = $lng;
                    }

                    $infos['coords'] = $coords;
                }
            }

            unset($infos['adresse_rcs'], $infos['nom_adressage'], $infos['adresse_insee']);

            return $infos;
        }

        private function siren2($siren)
        {
            $infos = [];

            if (is_numeric($siren) && strlen($siren)) {
                $cache = redis()->get('siren::' . $siren);

                if (!strlen($cache)) {
                    $data = dwn("http://www.verif.com/imprimer/$siren/1/1/");
                    redis()->set('siren::' . $siren, $data);
                } else {
                    $data = $cache;
                }

                $formeJuridique = $registre = $capital = $dirigeant = $immatriculation = $departement = $codePostal = $ville = $adresse = $ape = $creation =  $activite = $tel = $fax = $effectif = $siret = null;

                $cmdTel = "curl 'http://www.pagespro.com/recherche.php' -H 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' -H 'Accept-Encoding: gzip, deflate' -H 'Accept-Language: fr,fr-fr;q=0.8,en-us;q=0.5,en;q=0.3' -H 'Connection: keep-alive' -H 'Cookie: EIRAM=1; xtvrn=$486926$; xtan=-; xtant=1' -H 'Host: www.pagespro.com' -H 'Referer: http://www.pagespro.com/recherche.php' -H 'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:29.0) Gecko/20100101 Firefox/29.0' -H 'Content-Type: application/x-www-form-urlencoded' --data 'p_ACTION=&p_ORDRE=AfficheRes&a_PAGE=1&a_TAG=&a_OccRecherche=&typeRecherche=express&satellite=siret&a_activ=&a_rai_soc=&a_naf=&a_siren=$siren&a_tel=&a_geo=&typeTri=&ordreTri=&a_session='";

                $cacheTel = redis()->get('siren::tel::' . $siren);

                if (!strlen($cacheTel)) {
                    exec($cmdTel, $dataTel);
                    $dataTel = implode("", $dataTel);

                    redis()->set('siren::tel::' . $siren, $dataTel);
                } else {
                    $dataTel = $cacheTel;
                }

                if (strstr($dataTel, '<span itemprop="tel">')) {
                    $tel = strReplaceFirst(
                        '0',
                        '+33',
                        str_replace(
                            ["\t", '&nbsp;', ' '],
                            '',
                            Utils::cut(
                                '<span itemprop="tel">',
                                '</span>',
                                $dataTel
                            )
                        )
                    );
                }

                $dataFax = str_replace(["\t"], '', $dataTel);

                if (strstr($dataFax, "<span>fax")) {
                    $segFax = Utils::cut('<span>fax', '</d', $dataFax);

                    $fax = strReplaceFirst(
                        '0',
                        '+33',
                        str_replace(
                            ["\t", '&nbsp;', ' '],
                            '',
                            Utils::cut(
                                '<span itemprop="tel">',
                                '</span>',
                                $segFax
                            )
                        )
                    );
                }

                if (strstr($dataTel, '<b>Effectif')) {
                    $segEffectif = Utils::cut('<b>Effectif', '/div>', $dataTel);

                    $effectif = str_replace(
                        ["\t", '&nbsp;'],
                        '',
                        Utils::cut(
                            '</b>',
                            '<',
                            $segEffectif
                        )
                    );
                }

                if (strstr($dataTel, '<b>Siret')) {
                    $segSiret = Utils::cut('<b>Siret', '/div>', $dataTel);

                    $siret = str_replace(
                        ["\t", '&nbsp;'],
                        '',
                        Utils::cut(
                            '</b>',
                            '<',
                            $segSiret
                        )
                    );
                }

                $seg = Utils::cut('<h4>Informations g&eacute;n&eacute;rales</h4>', '</table>', $data);

                $sousSeg = Utils::cut(
                    '<td class="fiche_tdhead">Raison sociale</td>',
                    '</tr>',
                    $seg
                );

                $raisonSociale = Utils::cut('<td>', '</td>', $sousSeg);

                $sousSeg    = Utils::cut('<td class="fiche_tdhead">APE</td>', '</tr>', $seg);
                $ape        = Utils::cut('<td>', '</td>', $sousSeg);
                list($ape, $activite) = explode(' / ', Utils::cut('>', '</', $ape), 2);

                $sousSeg    = Utils::cut('<td class="fiche_tdhead">Forme juridique', '</tr>', $seg);
                list($formeJuridique, $creation) = explode(', cr&eacute;&eacute;e le ', Utils::cut('<td>', '</td>', $sousSeg), 2);

                $sousSeg    = Utils::cut('<td class="fiche_tdhead">Adresse', '</tr>', $seg);
                $adr        = Utils::cut('<td>', '</td>', $sousSeg);

                $adr = explode("\n", $adr);
                array_pop($adr);

                $adresse = Arrays::first($adr);
                $cpville = Arrays::last($adr);

                $adresse = $this->clean(str_replace(["\t", "<br />"], '', $adresse));

                list($codePostal, $ville) = explode('&nbsp;', $cpville, 2);
                $codePostal     = $this->clean(str_replace(["\t", " "], '', $codePostal));
                $departement    = substr($codePostal, 0, 2);

                $sousSeg = Utils::cut('<td class="fiche_tdhead">Capital Social', '</tr>', $seg);
                $capital = Utils::cut('<td>', '&', $sousSeg);

                $sousSeg = Utils::cut(
                    '<td class="fiche_tdhead">Registre du commerce',
                    '</tr>',
                    $seg
                );

                $registre = Utils::cut('<td>', '<', $sousSeg);
                $registre = str_replace(' ', '', $registre);

                if (null === $siret && strstr($seg, 'tdhead">SIRET</td>')) {
                    $sousSeg = Utils::cut('<td class="fiche_tdhead">SIRET</td>', '</tr>', $seg);
                    $siret = str_replace(
                        ' ',
                        '',
                        Utils::cut(
                            '<td>',
                            '</td>',
                            $sousSeg
                        )
                    );
                }

                if (strstr($data, '<H4>Dirigeants</H4>')) {
                    $segDirigeant = Utils::cut('<H4>Dirigeants</H4>', '</div>', $data);

                    $d = Utils::cut('<table', '</table>', $segDirigeant);
                    $d = Utils::cut('<tr', '</tr>', $d);

                    $rows = explode("\n", $d);

                    array_shift($rows);
                    array_pop($rows);

                    $fonction   = Utils::cut('>', '<', Arrays::first($rows));

                    $segPersonne = Arrays::last($rows);

                    if (fnmatch('*(*', $segPersonne)) {
                        $personne   = Utils::cut('>', '(', $segPersonne);
                        $personne   = str_replace(['&nbsp;'], '', $personne);
                    } else {
                        $personne   = Utils::cut('>', '</', $segPersonne);
                    }

                    $dirigeant  = "$personne - $fonction";
                }

                $infos = [
                    'siren'                 => $this->clean($siren),
                    'siret'                 => $this->clean($siret),
                    'raison_sociale'        => $this->clean($raisonSociale),
                    'ape'                   => $this->clean($ape),
                    'activite'              => $this->clean($activite),
                    'forme_juridique'       => $this->clean($formeJuridique),
                    'telephone'             => $this->clean($tel),
                    'fax'                   => $this->clean($fax),
                    'adresse'               => $this->clean($adresse),
                    'code_postal'           => $this->clean($codePostal),
                    'ville'                 => $this->clean($ville),
                    'departement'           => $this->clean($departement),
                    'effectif'              => $this->clean($effectif),
                    'date_immatriculation'  => $this->clean($creation),
                    'registre'              => $this->clean($registre),
                    'capital'               => $this->clean($capital),
                    'dirigeant'             => $this->clean($dirigeant)
                ];
            }

            return $infos;
        }

        private function clean($str)
        {
            return str_replace(
                '  ',
                ' ',
                utf8_encode(
                    str_replace(
                        [';', ',', "\t"],
                        ' ',
                        html_entity_decode(
                            str_replace(
                                ["\n", "\r", "\t"],
                                '',
                                $str
                            )
                        )
                    )
                )
            );
        }

        function getServicesAroundAddress($address, $id = 1 /* Hôtels */)
        {
            return $this->addServicesAroundAddress($address, $id, false);
        }

        function addServicesAroundAddress($address, $id = 1 /* Hôtels */, $add = true)
        {
            ini_set('memory_limit', '1024M');
            /*
                1 => hotel
                2 => chambre-d-hotes
                3 => location-gite
                4 => camping
                5 => auberge-de-jeunesse
                6 => restaurant
                7 => pizzeria
                8 => restauration-rapide
                9 => bar-club
                10 => cafe
                11 => magasin-jouet
                12 => cinema
                13 => musee
                14 => theatre-spectacle
                15 => librairies-papeteries
                16 => sports
                17 => magasin-de-sport
                18 => loisir
                19 => supermarches-hypermarches
                20 => meubles
                21 => hifi-electromenager
                22 => telephonie-internet
                23 => bricolage
                24 => epicerie
                25 => boulangerie-patisserie
                26 => caviste
                27 => tabac
                28 => fleuriste
                29 => coiffeur
                30 => institut-de-beaute
                31 => parfumeries
                32 => pharmacie-parapharmacie
                33 => opticien
                34 => vetements-femme
                35 => vetements-homme
                36 => vetements-enfant
                37 => accessoires-de-mode
                38 => chaussures
                39 => bijouterie
                40 => puericulture
                41 => station-service
                42 => parking
                43 => garage
                44 => velos-en-libre-service
                45 => location-automobile
                46 => automobiles-agents-concessionnaires-distributeurs
                47 => agence-immobiliere
                48 => banque
                49 => poste
                50 => mairie
                51 => ecole
                52 => hopital
                53 => police
                54 => office-de-tourisme
            */

            $collection = [];

            $service = rdb('geo', 'service')->find($id);

            if ($service) {
                $urlLocalisation = "http://search.mappy.net/search/1.0/find?q=" . urlencode($address) . "&favorite_country=250&language=FRE&loc_format=geojson";

                $keyJsonLocal       = 'pois.' . sha1($address);
                $keyJsonServices    = 'pois.' . sha1($address) . '.' . $id . date('Y') . date('W');

                $json = redis()->get($keyJsonLocal);

                if (!$json) {
                    $json = dwn($urlLocalisation);
                    redis()->set($keyJsonLocal, $json);
                }

                $tab = json_decode($json, true);

                if (isset($tab['addresses'])) {
                    if (isset($tab['addresses']['features'])) {
                        if (count($tab['addresses']['features'])) {
                            $coords = current($tab['addresses']['features']);
                            $bbox = isAke($coords, 'bbox', false);

                            if (false !== $bbox && count($bbox) == 4) {
                                $lng1 = str_replace(',', '.', $bbox[0]);
                                $lat1 = str_replace(',', '.', $bbox[1]);
                                $lng2 = str_replace(',', '.', $bbox[2]);
                                $lat2 = str_replace(',', '.', $bbox[3]);

                                $zone = rdb('geo', 'zone')->firstOrCreate([
                                    'address' => $address,
                                    'lat1' => $lat1,
                                    'lng1' => $lng1,
                                    'lat2' => $lat2,
                                    'lng2' => $lng2
                                ]);

                                $code = $service->code;

                                $urlServices = "http://uws2.mappy.net/data/poi/5.3/geoentities/$lat1,$lng1,$lat2,$lng2/rubric.json?ids=$code&max=299&cache=true";

                                $json = redis()->get($keyJsonServices);

                                if (!$json) {
                                    $json = dwn($urlServices);
                                    redis()->set($keyJsonServices, $json);
                                } else {
                                    if (true === $add) {
                                        $now = time();
                                        $age = dbCache('geo')->get($keyJsonServices, $now);

                                        if ($age == $now) {
                                            dbCache('geo')->set($keyJsonServices, $now);

                                            return true;
                                        } else {
                                            $toOld = (($now - $age) > (720 * 3600)) ? true : false;

                                            if (!$toOld) {
                                                return true;
                                            } else {
                                                $jsonNew = dwn($urlServices);

                                                $same = sha1($jsonNew) == sha1($json);

                                                dbCache('geo')->set($keyJsonServices, $now);

                                                if (true === $same) {
                                                    return true;
                                                }

                                                $json = $jsonNew;

                                                redis()->set($keyJsonServices, $json);
                                            }
                                        }
                                    }
                                }

                                $tab = json_decode($json, true);

                                foreach ($tab as $service) {
                                    $serviceproxIds     = $supplements = [];
                                    $serviceproxIds[]   = $id;
                                    $poi                = $service['id'];

                                    unset($service['id']);
                                    unset($service['offer']);
                                    unset($service['prov']);
                                    unset($service['pjBlocId']);
                                    unset($service['thematicId']);

                                    if (isset($service['tabs'])) {
                                        if (count($service['tabs'])) {
                                            foreach ($service['tabs'] as $tmp) {
                                                $tmpUrl = isAke($tmp, 'url', false);
                                                $tmpId  = isAke($tmp, 'appId', false);

                                                if (false !== $tmpUrl && false !== $tmpId) {
                                                    $key = 'inf.' . $poi . '.' . $tmpId;

                                                    if ($tmpId == 'pj') {
                                                        $inf = redis()->get($key);

                                                        if (empty($inf)) {
                                                            $infHtml    = dwn($tmpUrl);
                                                            $inf        = substr($infHtml, strlen('callback('), -1);
                                                            redis()->set($key, $inf);
                                                        }

                                                        $inf = json_decode($inf, true);

                                                        $t = isAke($inf, 'tabs', []);

                                                        if (!empty($t)) {
                                                            foreach ($t as $tmpT) {
                                                                $b = isAke($tmpT, 'blocks', []);
                                                                $t = isAke($tmpT, 'tags', []);

                                                                if (!empty($b)) {
                                                                    for ($ti = 0; $ti < count($b); $ti +=2) {
                                                                        $seg    = $b[$ti];
                                                                        $seg2   = $b[$ti + 1];

                                                                        if (is_array($seg) && is_array($seg2)) {
                                                                            $title  = isAke($seg, 'title', false);
                                                                            $kv     = isAke($seg2, 'keyValue', false);

                                                                            if (false !== $title && false !== $kv) {
                                                                                $title = Inflector::urlize($title);

                                                                                foreach ($kv as $tmpRow) {
                                                                                    $supplements[$title][] = [$tmpRow['key'] => $tmpRow['value']];
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }

                                                                if (!empty($t)) {
                                                                    dd('tags', $t);
                                                                }
                                                            }
                                                        }
                                                    } elseif ($tmpId == 'indoor') {

                                                    } elseif ($tmpId == 'localbusinesspremium') {

                                                    } elseif ($tmpId == 'total') {

                                                    } elseif ($tmpId == 'totalaccess') {

                                                    } elseif ($tmpId == 'darty') {

                                                    } elseif ($tmpId == 'eleclerc') {

                                                    } elseif ($tmpId == 'moteurproduitpromo') {

                                                    } elseif ($tmpId == 'mappyshopping') {

                                                    } elseif ($tmpId == 'booking') {
                                                        $inf = redis()->get($key);

                                                        if (empty($inf)) {
                                                            $infHtml    = dwn($tmpUrl);
                                                            $inf        = substr($infHtml, strlen('callback('), -1);
                                                            redis()->set($key, $inf);
                                                        }

                                                        $inf = json_decode($inf, true);

                                                        /* TODO */
                                                    } elseif ($tmpId == 'localbusinessdiscovery') {
                                                        $inf = redis()->get($key);

                                                        if (empty($inf)) {
                                                            $infHtml    = dwn($tmpUrl);
                                                            $inf        = substr($infHtml, strlen('callback('), -1);
                                                            redis()->set($key, $inf);
                                                        }

                                                        $inf = json_decode($inf, true);

                                                        $t = isAke($inf, 'tabs', []);

                                                        if (!empty($t)) {
                                                            foreach ($t as $tmpT) {
                                                                $b = isAke($tmpT, 'blocks', []);

                                                                if (!empty($b)) {
                                                                    foreach ($b as $tmpB) {
                                                                        $kv = isAke($tmpB, 'keyValue', false);

                                                                        if (false !== $kv) {
                                                                            foreach ($kv as $tmpRow) {
                                                                                $tmpK = Inflector::urlize($tmpRow['key']);
                                                                                $supplements['infos'][$tmpK] = $tmpRow['value'];
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    } else {
                                                        $inf = redis()->get($key);

                                                        if (empty($inf)) {
                                                            $infHtml = dwn($tmpUrl);
                                                            $inf = substr($infHtml, strlen('callback('), -1);
                                                            redis()->set($key, $inf);
                                                        }

                                                        $inf = json_decode($inf, true);
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    unset($service['tabs']);
                                    unset($service['contextualPoiUrl']);
                                    unset($service['providerIds']);
                                    unset($service['pjRatingId']);
                                    unset($service['hasUnclaimableProvider']);
                                    unset($service['additionalInfos']);
                                    unset($service['additionalInfo']);
                                    unset($service['coordinateProvider']);
                                    unset($service['coordinateProvider3D']);
                                    unset($service['offerType']);
                                    unset($service['rubricId']);
                                    unset($service['closestPanoramicId']);

                                    if (isset($service['allRubrics'])) {
                                        if (count($service['allRubrics'])) {
                                            foreach ($service['allRubrics'] as $rubrikA) {
                                                $idr    = isAke($rubrikA, 'id');
                                                $lr     = isAke($rubrikA, 'label');
                                                $pr     = isAke($rubrikA, 'rubricParentId');
                                                $spi    = rdb('geo', 'service')
                                                ->firstOrCreate(['code' => $idr])
                                                ->setLabel($lr)
                                                ->setFamily($pr)
                                                ->save();
                                            }
                                        }

                                        unset($service['allRubrics']);
                                    }

                                    if (isset($service['additionalRubricIds'])) {
                                        if (count($service['additionalRubricIds'])) {
                                            foreach ($service['additionalRubricIds'] as $newRubrique) {
                                                $spi = rdb('geo', 'service')->firstOrCreate(['code' => $newRubrique]);
                                                $serviceproxIds[] = $spi->id;
                                            }
                                        }
                                    }

                                    unset($service['additionalRubricIds']);
                                    unset($service['brandIconUrl']);
                                    unset($service['slat']);
                                    unset($service['slng']);
                                    unset($service['salt']);
                                    unset($service['brand']);
                                    unset($service['indoors']);
                                    unset($service['visibleIn3D']);
                                    unset($service['townCode']);

                                    if (isset($service['town'])) {
                                        $service['city'] = $service['town'];
                                        unset($service['town']);
                                    }

                                    if (isset($service['positions3D'])) {
                                        if (isset($service['positions3D']['origin'])) {
                                            if (isset($service['positions3D']['origin']['alt'])) {
                                                $service['altitude'] = $service['positions3D']['origin']['alt'];

                                                unset($service['positions3D']);
                                            }
                                        }
                                    }

                                    if (isset($service['way'])) {
                                        $service['address'] = $service['way'];
                                        unset($service['way']);
                                    }

                                    if (isset($service['pCode'])) {
                                        $service['zip'] = $service['pCode'];
                                        unset($service['pCode']);
                                        unset($service['positions3D']);
                                    }

                                    if (isset($service['lat']) && isset($service['lng'])) {
                                        $distances = distanceKmMiles(
                                            $lng1,
                                            $lat1,
                                            $service['lng'],
                                            $service['lat']
                                        );

                                        $service['distance'] = (float) $distances['km'];
                                    }

                                    ksort($service);

                                    if (false === $add) {
                                        $collection[] = $service;
                                    }

                                    if (true === $add) {
                                        unset($service['distance']);
                                        $etab = rdb('geo', 'etablissement')
                                        ->firstOrCreate($service)
                                        ->setPoi($poi)
                                        ->save();

                                        setLocation($etab, $etab->lng, $etab->lat);

                                        if (!empty($supplements)) {
                                            foreach ($supplements as $supK => $supV) {
                                                $setter = setter($supK);

                                                $etab->$setter($supV);
                                            }

                                            $etab->save();
                                        }

                                        $fields = $etab->_keys();

                                        $sFields = array_merge(
                                            array_keys($service),
                                            array_keys($supplements)
                                        );

                                        $except = ['id', 'poi', 'created_at', 'updated_at'];

                                        $resave = false;

                                        $expurge = [];

                                        foreach ($fields as $field) {
                                            if (!in_array($field, $except)) {
                                                if (!in_array($field, $sFields)) {
                                                    $expurge[] = $field;
                                                    $etab = $etab->expurge($field);
                                                    $resave = true;
                                                }
                                            }
                                        }

                                        if (true === $resave) {
                                            $etab = $etab->save();
                                        }

                                        foreach ($serviceproxIds as $serviceproxId) {
                                            $spTmp = rdb('geo', 'service')->find((int) $serviceproxId);
                                            $spTmp->attach($etab);
                                        }

                                        $zone->attach($etab);

                                        if (isset($service['phone'])) lib('log')->write($service['phone'] . ' '. $service['zip']);
                                    } else {
                                        $service['poi'] = $poi;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return $collection;
        }

        public function populateEtabsByAddress($address)
        {
            set_time_limit(0);

            // $all = rdb('geo', 'service')->order('created_at', 'DESC')->exec();
            $all = rdb('geo', 'service')->order('id')->cursor();

            while ($row = $all->fetch()) {
                $this->addServicesAroundAddress($address, $row['id']);
            }

            $zone = rdb('geo', 'zone')
            ->where(['address', '=i', $address])
            ->first(true);

            if ($zone) {
                return $zone->pivots(rdb('geo', 'etablissement')->model())->count();
            }

            return rdb('geo', 'etablissement')->count();
        }

        public function populateEtabsByAddressById($address, $id = 'restaurant')
        {
            set_time_limit(0);

            $service = rdb('geo', 'service')->where(['code', '=', $id])->first(true);

            if ($service) {
                $this->addServicesAroundAddress($address, $service->id);
            }

            $zone = rdb('geo', 'zone')
            ->where(['address', '=i', $address])
            ->first(true);

            if ($zone) {
                return $zone->pivots(rdb('geo', 'etablissement')->model())->count();
            }

            return rdb('geo', 'etablissement')->count();
        }

        public function populateEtabsByAddressByLike($address, $like = 'restaurant')
        {
            set_time_limit(0);

            $services = rdb('geo', 'service')->where(['code', 'LIKE', "%$like%"])->exec(true);

            foreach ($services as $service) {
                $this->addServicesAroundAddress($address, $service->id);
            }

            $zone = rdb('geo', 'zone')
            ->where(['address', '=i', $address])
            ->first(true);

            if ($zone) {
                return $zone->pivots(rdb('geo', 'etablissement')->model())->count();
            }

            return rdb('geo', 'etablissement')->count();
        }

        public function getPoInfos($poi)
        {
            $key = 'infos.poi.' . $poi;

            $json = redis()->get($key);

            if (empty($json)) {
                $url = "http://uws2.mappy.net/data/poi/5.3/geoentity/$poi.json";
                $json = dwn($url);

                redis()->set($key, $json);
            }

            return json_decode($json, true);
        }

        public function csvByZone($address)
        {
            ini_set('memory_limit', '1024M');
            set_time_limit(0);

            $file = '/home/gerald/Bureau/' . Inflector::urlize($address) . '_' . date('dmYHis') . '.csv';

            touch($file);

            $zone = rdb('geo', 'zone')
            ->where(['address', '=i', $address])
            ->first(true);

            if ($zone) {
                File::append(
                    $file,
                    implode(
                        ';',
                        [
                            'category',
                            'name',
                            'address',
                            'lat',
                            'lng',
                            'altitude',
                            'zip',
                            'city',
                            'phone',
                            'mail',
                            'url'
                        ]
                    ) . "\n"
                );
                $pivots = $zone->pivots(rdb('geo', 'etablissement')->model())->exec(true);

                foreach ($pivots as $pivot) {
                    $etab = $pivot->etablissement(true);
                    if ($zone) {
                        $relations  = $etab->pivots(rdb('geo', 'service'))->exec(true);

                        foreach ($relations as $relation) {
                            $sp = $relation->service(true);

                            if ($sp) {
                                $item = [];

                                $item['category']   = str_replace([', ', ','], '-', $sp->label);
                                $item['name']       = str_replace([', ', ','], '-', $etab->name);
                                $item['address']    = str_replace([', ', ','], '-', $etab->address);
                                $item['lat']        = str_replace([', ', ','], '.', $etab->lat);
                                $item['lng']        = str_replace([', ', ','], '.', $etab->lng);
                                $item['altitude']   = str_replace([', ', ','], '.', $etab->altitude);
                                $item['zip']        = str_replace([', ', ','], '-', $etab->zip);
                                $item['city']       = str_replace([', ', ','], '-', $etab->city);
                                $item['phone']      = str_replace([', ', ','], '-', $etab->phone);
                                $item['mail']       = str_replace([', ', ','], '-', $etab->mail);
                                $item['url']        = str_replace([', ', ','], '-', $etab->url);

                                File::append($file, implode(';', $item) . "\n");
                            }
                        }
                    }
                }
            }
        }

        public function companiesCreated($region = null, $month = null, $year = null, $limit = 0, $offset = 0)
        {
            /*
                Array (
                    [1] => Alsace
                    [2] => Aquitaine
                    [3] => Auvergne
                    [4] => Basse-Normandie
                    [5] => Bourgogne
                    [6] => Bretagne
                    [7] => Centre
                    [8] => Champagne-Ardennes
                    [9] => Corse
                    [10] => Franche-Comté
                    [11] => Haute-Normandie
                    [12] => Ile-de-France
                    [13] => Languedoc-Roussillon
                    [14] => Limousin
                    [15] => Lorraine
                    [16] => Midi-Pyrénées
                    [17] => Nord-Pas-de-Calais
                    [18] => Pays de la Loire
                    [19] => Picardie
                    [20] => Poitou-Charentes
                    [21] => Provence-Alpes-Côte d'Azur
                    [22] => Rhône-Alpes
                )
            */
            require_once APPLICATION_PATH . DS . '..' . '/public/vendeur/lib/simple_html_dom.php';
            ini_set('memory_limit', '1024M');
            set_time_limit(0);

            $collection = [];

            $nb = 0;

            $toCache = is_null($month) && is_null($year) ? false : true;

            $region = is_null($region)  ? 5         : $region;
            $month  = is_null($month)   ? date('m') : $month;
            $year   = is_null($year)    ? date('Y') : $year;

            $continue = mktime(0, 0, 0, $month, 1, $year) < time() ? true : false;

            if (!$continue) {
                throw new Exception("This method requires a past date to process.");
            }

            if (strlen($month) == 1) {
                $month = "0$month";
            }

            $date = $year . $month;

            $url = "http://www.score3.fr/liste-creations-entreprises.shtml?region=$region&date=$date&activite=0&tri=cp&sens=ascending&type=text";

            if ($toCache) {
                $keyCached = "companies.just.created.$region.$month.$year.1";
                $html = redis()->get($keyCached);

                if (!$html) {
                    $html = dwn($url);
                    redis()->set($keyCached, $html);
                }
            } else {
                $html = dwn($url);
            }

            $max = (int) Utils::cut('<td width="200" align="center" class="infos">page 1 / ', '</td>', $html);

            $html   = str_get_html($html);

            $table  = $html->find('table', 0);
            $trs    = $table->find('tr');

            array_shift($trs);

            foreach ($trs as $tr) {
                $item   = [];
                $td     = $tr->find('td', 1);
                $a      = $td->find('a', 0);
                $siren  = (int) $a->innertext;
                $infos  = $this->siren($siren);

                $cp = isAke($infos, 'code_postal', null);

                if (!empty($cp)) {
                    if ($limit > 0) {
                        if ($nb >= $offset) {
                            $collection[] = $infos;
                        }

                        $nb++;
                    } else {
                        $collection[] = $infos;
                        $nb++;
                    }

                    if ($limit > 0 && $nb >= $limit) {
                        return $collection;
                    }
                }
            }

            if ($max > 1) {
                for ($i = 2; $i <= $max; $i++) {
                    $url = "http://www.score3.fr/liste-creations-entreprises.shtml?region=$region&date=$date&activite=0&tri=cp&sens=ascending&type=text&page=" . $i;

                    if ($toCache) {
                        $keyCached = "companies.just.created.$region.$month.$year.$i";
                        $html = redis()->get($keyCached);

                        if (!$html) {
                            $html = dwn($url);
                            redis()->set($keyCached, $html);
                        }
                    } else {
                        $html = dwn($url);
                    }

                    $html   = str_get_html($html);
                    $table  = $html->find('table', 0);

                    if ($table) {
                        $trs = $table->find('tr');

                        array_shift($trs);

                        foreach ($trs as $tr) {
                            $item   = [];
                            $td     = $tr->find('td', 1);
                            $a      = $td->find('a', 0);
                            $siren  = (int) $a->innertext;

                            $infos = $this->siren($siren);

                            $cp = isAke($infos, 'code_postal', null);

                            if (!empty($cp)) {
                                if ($limit > 0) {
                                    if ($nb >= $offset) {
                                        $collection[] = $infos;
                                    }

                                    $nb++;
                                } else {
                                    $collection[] = $infos;
                                    $nb++;
                                }

                                if ($limit > 0 && $nb >= $limit) {
                                    return $collection;
                                }
                            }
                        }
                    }
                }
            }

            return $collection;
        }

        public function searchNearByAddress($address, $query)
        {
            $collection = lib('collection');

            $coords = lib('geo')->getCoords($address);

            $url = 'http://search.mappy.net/search/1.0/find?extend_bbox=1&bbox=' . $coords['lat1'] . ',' . $coords['lng1'] . ',' . $coords['lat2'] . ',' . $coords['lng2'] . '&q=' . urlencode($query) . '&favorite_country=' . $coords['country_id'] . '&language=FRE&&max_results=199';dd($url);

            $key = 'serach.near.' . sha1($url);

            $json = redis()->get($key);

            if (!$json) {
                $json = dwn($url);
                redis()->set($key, $json);
            }

            $data = json_decode($json, true);

            $pois = isAke($data, 'pois', []);

            foreach ($pois as $service) {
                $dbService = rdb('geo', 'service')->where(['code', '=', $service['rubricId']])->first(true);

                if ($dbService) {
                    $id = $dbService->id;
                } else {
                    if (isset($service['allRubrics'])) {
                        if (count($service['allRubrics'])) {
                            foreach ($service['allRubrics'] as $rubrikA) {
                                $idr    = isAke($rubrikA, 'id');
                                $lr     = isAke($rubrikA, 'label');
                                $pr     = isAke($rubrikA, 'rubricParentId');
                                if ($idr == $service['rubricId']) {
                                    $spi    = rdb('geo', 'service')
                                    ->firstOrCreate(['code' => $idr])
                                    ->setLabel($lr)
                                    ->setFamily($pr)
                                    ->save();

                                    $id = $spi->id;
                                }
                            }
                        }
                    }
                }

                $serviceproxIds     = $supplements = [];
                $serviceproxIds[]   = $id;
                $poi                = $service['id'];

                $checkEtab = rdb('geo', 'etablissement')->where(['poi', '=', (string) $poi])->first(true);

                $add = $checkEtab ? false : true;

                unset($service['id']);

                if (false === $add) {
                    $service['id'] = $checkEtab->id;
                }

                unset($service['offer']);
                unset($service['prov']);
                unset($service['pjBlocId']);
                unset($service['thematicId']);

                if (isset($service['tabs'])) {
                    if (count($service['tabs'])) {
                        foreach ($service['tabs'] as $tmp) {
                            $tmpUrl = isAke($tmp, 'url', false);
                            $tmpId  = isAke($tmp, 'appId', false);

                            if (false !== $tmpUrl && false !== $tmpId) {
                                $key = 'inf.' . $poi . '.' . $tmpId;

                                if ($tmpId == 'pj') {
                                    $inf = redis()->get($key);

                                    if (empty($inf)) {
                                        $infHtml    = dwn($tmpUrl);
                                        $inf        = substr($infHtml, strlen('callback('), -1);
                                        redis()->set($key, $inf);
                                    }

                                    $inf = json_decode($inf, true);

                                    $t = isAke($inf, 'tabs', []);

                                    if (!empty($t)) {
                                        foreach ($t as $tmpT) {
                                            $b = isAke($tmpT, 'blocks', []);
                                            $t = isAke($tmpT, 'tags', []);

                                            if (!empty($b)) {
                                                for ($ti = 0; $ti < count($b); $ti +=2) {
                                                    $seg    = $b[$ti];
                                                    $seg2   = $b[$ti + 1];

                                                    if (is_array($seg) && is_array($seg2)) {
                                                        $title  = isAke($seg, 'title', false);
                                                        $kv     = isAke($seg2, 'keyValue', false);

                                                        if (false !== $title && false !== $kv) {
                                                            $title = Inflector::urlize($title);

                                                            foreach ($kv as $tmpRow) {
                                                                $supplements[$title][] = [$tmpRow['key'] => $tmpRow['value']];
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                } elseif ($tmpId == 'indoor') {

                                } elseif ($tmpId == 'localbusinesspremium') {

                                } elseif ($tmpId == 'total') {

                                } elseif ($tmpId == 'totalaccess') {

                                } elseif ($tmpId == 'darty') {

                                } elseif ($tmpId == 'eleclerc') {

                                } elseif ($tmpId == 'moteurproduitpromo') {

                                } elseif ($tmpId == 'mappyshopping') {

                                } elseif ($tmpId == 'booking') {
                                    $inf = redis()->get($key);

                                    if (empty($inf)) {
                                        $infHtml    = dwn($tmpUrl);
                                        $inf        = substr($infHtml, strlen('callback('), -1);
                                        redis()->set($key, $inf);
                                    }

                                    $inf = json_decode($inf, true);

                                    /* TODO */
                                } elseif ($tmpId == 'localbusinessdiscovery') {
                                    $inf = redis()->get($key);

                                    if (empty($inf)) {
                                        $infHtml    = dwn($tmpUrl);
                                        $inf        = substr($infHtml, strlen('callback('), -1);
                                        redis()->set($key, $inf);
                                    }

                                    $inf = json_decode($inf, true);

                                    $t = isAke($inf, 'tabs', []);

                                    if (!empty($t)) {
                                        foreach ($t as $tmpT) {
                                            $b = isAke($tmpT, 'blocks', []);

                                            if (!empty($b)) {
                                                foreach ($b as $tmpB) {
                                                    $kv = isAke($tmpB, 'keyValue', false);

                                                    if (false !== $kv) {
                                                        foreach ($kv as $tmpRow) {
                                                            $tmpK = Inflector::urlize($tmpRow['key']);
                                                            $supplements['infos'][$tmpK] = $tmpRow['value'];
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $inf = redis()->get($key);

                                    if (empty($inf)) {
                                        $infHtml = dwn($tmpUrl);
                                        $inf = substr($infHtml, strlen('callback('), -1);
                                        redis()->set($key, $inf);
                                    }

                                    $inf = json_decode($inf, true);
                                }
                            }
                        }
                    }
                }

                unset($service['tabs']);
                unset($service['contextualPoiUrl']);
                unset($service['providerIds']);
                unset($service['pjRatingId']);
                unset($service['hasUnclaimableProvider']);
                unset($service['additionalInfos']);
                unset($service['additionalInfo']);
                unset($service['coordinateProvider']);
                unset($service['coordinateProvider3D']);
                unset($service['offerType']);
                unset($service['rubricId']);
                unset($service['closestPanoramicId']);

                if (isset($service['allRubrics'])) {
                    if (count($service['allRubrics'])) {
                        foreach ($service['allRubrics'] as $rubrikA) {
                            $idr    = isAke($rubrikA, 'id');
                            $lr     = isAke($rubrikA, 'label');
                            $pr     = isAke($rubrikA, 'rubricParentId');
                            $spi    = rdb('geo', 'service')
                            ->firstOrCreate(['code' => $idr])
                            ->setLabel($lr)
                            ->setFamily($pr)
                            ->save();
                        }
                    }

                    unset($service['allRubrics']);
                }

                if (isset($service['additionalRubricIds'])) {
                    if (count($service['additionalRubricIds'])) {
                        foreach ($service['additionalRubricIds'] as $newRubrique) {
                            $spi = rdb('geo', 'service')->firstOrCreate(['code' => $newRubrique]);
                            $serviceproxIds[] = $spi->id;
                        }
                    }
                }

                unset($service['additionalRubricIds']);
                unset($service['brandIconUrl']);
                unset($service['slat']);
                unset($service['slng']);
                unset($service['salt']);
                unset($service['brand']);
                unset($service['indoors']);
                unset($service['visibleIn3D']);
                unset($service['townCode']);

                if (isset($service['town'])) {
                    $service['city'] = $service['town'];
                    unset($service['town']);
                }

                if (isset($service['positions3D'])) {
                    if (isset($service['positions3D']['origin'])) {
                        if (isset($service['positions3D']['origin']['alt'])) {
                            $service['altitude'] = $service['positions3D']['origin']['alt'];

                            unset($service['positions3D']);
                        }
                    }
                }

                if (isset($service['way'])) {
                    $service['address'] = $service['way'];
                    unset($service['way']);
                }

                if (isset($service['pCode'])) {
                    $service['zip'] = $service['pCode'];
                    unset($service['pCode']);
                    unset($service['positions3D']);
                }

                if (isset($service['lat']) && isset($service['lng'])) {
                    $distances = distanceKmMiles(
                        $coords['lng1'],
                        $coords['lat1'],
                        $service['lng'],
                        $service['lat']
                    );

                    $service['distance'] = (float) $distances['km'];
                }

                ksort($service);

                $service['poi'] = $poi;

                if (false === $add) {
                    $collection[] = $service;
                }

                if (true === $add) {
                    $distance = $service['distance'];
                    unset($service['distance']);
                    $etab = rdb('geo', 'etablissement')
                    ->firstOrCreate($service)
                    ->setPoi($poi)
                    ->save();

                    setLocation($etab, $etab->lng, $etab->lat);

                    if (!empty($supplements)) {
                        foreach ($supplements as $supK => $supV) {
                            $setter = setter($supK);

                            $etab->$setter($supV);
                        }

                        $etab->save();
                    }

                    $fields = $etab->_keys();

                    $sFields = array_merge(
                        array_keys($service),
                        array_keys($supplements)
                    );

                    $except = ['id', 'poi', 'created_at', 'updated_at'];

                    $resave = false;

                    $expurge = [];

                    foreach ($fields as $field) {
                        if (!in_array($field, $except)) {
                            if (!in_array($field, $sFields)) {
                                $expurge[] = $field;
                                $etab = $etab->expurge($field);
                                $resave = true;
                            }
                        }
                    }

                    if (true === $resave) {
                        $etab = $etab->save();
                    }

                    foreach ($serviceproxIds as $serviceproxId) {
                        $spTmp = rdb('geo', 'service')->find((int) $serviceproxId);
                        $spTmp->attach($etab);
                    }

                    $zone = rdb('geo', 'zone')->find(1);

                    $zone->attach($etab);
                    $service['new'] = true;
                    $service['id'] = $etab->id;
                    $service['distance'] = $distance;
                }

                $collection[] = $service;
            }

            if (!empty($collection)) {
                $tuples = $new = [];
                $collection->sortBy('distance');

                foreach ($collection as $row) {
                    $hasPoi = isAke($row, 'poi', false);

                    if (false === $hasPoi) {
                        $new[] = $row;
                    } else {
                        $poi = sha1($hasPoi);

                        if (!in_array($poi, $tuples)) {
                            $tuples[] = $poi;
                            $new[] = $row;
                        }
                    }
                }

                $collection = lib('collection', [$new]);
            }

            return $collection->toArray();
        }

        public function ponterestSearch($rec)
        {
            $cached = redis()->get('pins.s.' . sha1($rec));

            if (!$cached) {
                $cached = file_get_contents("https://fr.pinterest.com/search/pins/?q=" . urlencode($rec));
                // $cached = dwn("https://fr.pinterest.com/search/pins/?q=" . urlencode($rec) . "&term_meta[]=" . urlencode($rec) . "|typed");
                redis()->set('pins.s.' . sha1($rec), $cached);
            }

            $data = Utils::cut('P.main.start(', '});', $cached) . '}';

            $data = json_decode($data, true);

            $resourceDataCache = isAke($data, 'resourceDataCache', []);
            $data = isAke(current($resourceDataCache), 'data', []);
            $results = isAke($data, 'results', []);

            dd($results);
        }

        public function img($q)
        {
            $data   = lib('geo')->dwnCache("https://www.pinterest.com/search/pins/?q=" . urlencode($q));
            $tab    = explode('}, "orig": {"url": ', $data);
            array_shift($tab);

            $collection = [];

            foreach ($tab as $row) {
                $src    = str_replace(["\\"], [""], Utils::cut('"', '"', $row));
                $width  = Utils::cut('"width": ', ',', $row);
                $height = Utils::cut('"height": ', '}', $row);

                $collection[] = [
                    'src'       => $src,
                    'width'     => $width,
                    'height'    => $height
                ];
            }

            return $collection;
        }
    }
