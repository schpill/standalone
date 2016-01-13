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

    class WikipediaLib
    {

        public function search($q)
        {
            $json = file_get_contents('https://fr.wikipedia.org/w/api.php?action=opensearch&format=json&search=' . urlencode(urldecode($q)) . '&namespace=0&limit=20&suggest=');

            return json_decode($json, true);
        }

        public function getApp($title)
        {
            return isCached('getApp.wp.' . $title, function () use ($title) {
                return json_decode(file_get_contents("http://appservice.wmflabs.org/fr.m.wikipedia.org/v1/page/mobile-html-sections/" . $title), true);
            });
        }

        public function getInfosObject($url)
        {
            $last = Arrays::last(explode('/', $url));
            $urlDb = 'http://fr.dbpedia.org/page/' . $last;
            // $url2 = 'https://fr.wikipedia.org/w/api.php?action=query&titles=' . urlencode($last) . '&prop=revisions&rvprop=content&format=json';
            $infos = [];

            $wp = redis()->get('wp.wp.info.' . $last);
            $html = redis()->get('wp.html.info.' . $last);

            if (!$wp) {
                $wp = file_get_contents($url);
                redis()->set('wp.wp.info.' . $last, $wp);
            }

            if (!$html) {
                $html = file_get_contents($urlDb);
                redis()->set('wp.html.info.' . $last, $html);
            }

            $infos['name']  = Utils::cut('"wgTitle":"', '"', $wp);

            if (strstr($wp, 'class="infobox')) {
                $infos['image'] = Utils::cut('src="//upload.wikimedia.org/', '"', Utils::cut('class="infobox', '</table>', $wp));

                if (strlen($infos['image'])) {
                    $infos['image'] = 'http://upload.wikimedia.org/' . $infos['image'];
                }

                $coords = Utils::cut('<span class="geo-dec">', '</span>', $wp);

                if (fnmatch('*,*', $coords)) {
                    list($infos['latitude'], $infos['longitude']) = explode(',', $coords, 2);
                }

                $segAddress = Utils::cut('>Adresse</th>', '</tr>', $wp);

                if (strlen($segAddress)) {
                    $infos['address'] = str_replace(["\n", "\r", "\t"], '', strip_tags($segAddress));
                } else {
                    if (isset($infos['latitude']) && isset($infos['longitude'])) {
                        $address = lib('geo')->getAddressByCoords((float) $infos['latitude'], (float) $infos['longitude'], true);
                        $infos['address'] = $address['address'];
                        $infos['city'] = $address['city'];
                        $infos['country'] = $address['country'];
                    }
                }

                $segCity = Utils::cut('>Ville</th>', '</tr>', $wp);

                if (strlen($segCity)) {
                    $infos['city'] = str_replace(["\n", "\r", "\t"], '', strip_tags($segCity));
                }

                $segCountry = Utils::cut('>Pays</th>', '</tr>', $wp);

                if (strlen($segCountry)) {
                    $infos['country'] = str_replace(["\n", "\r", "\t"], '', strip_tags($segCountry));
                }
            }

            $infos['name']  = Utils::cut('"wgTitle":"', '"', $wp);

            if (fnmatch('http*', $infos['name'])) {
                $infos['name'] = ucwords(str_replace('_', ' ', Arrays::end(explode('/', $infos['name']))));
            }

            $wp = str_replace('<p><small>', '<small>', $wp);
            $wp = str_replace('<p><span', '<span>', $wp);

            $p = Utils::cut('<p>', '</p>', $wp);

            $infos['abstract'] = str_replace(["\n", "\r", "\t"], '', strip_tags($p));

            $app = $this->getApp($last);

            $media = array_get($app, 'lead.media');

            if (is_array($media)) {
                $items = isAke($media, 'items', []);

                if (!empty($items)) {
                    $item = current($items);
                    $infos['image'] = isAke($item, 'url', false);
                }
            }

            if (!isset($infos['image'])) {
                $infos['image'] = $this->getImage((int) $wpid);
            }

            if (!$infos['image']) {
                $infos['image'] = $this->getImage((int) $wpid);
            }

            $wpid               = Utils::cut('"wgArticleId":', ',', $wp);
            $infos['id_wp']     = (int) $wpid;
            $infos['abstract']  = $this->getExtract((int) $wpid);

            return $infos;
        }

        public function getInfosLieu($url)
        {
            $last = Arrays::last(explode('/', $url));
            $urlDb = 'http://fr.dbpedia.org/page/' . $last;
            // $url2 = 'https://fr.wikipedia.org/w/api.php?action=query&titles=' . urlencode($last) . '&prop=revisions&rvprop=content&format=json';
            $infos = [];

            $wp = redis()->get('wp.wp.info.' . $last);
            $html = redis()->get('wp.html.info.' . $last);

            if (!$wp) {
                $wp = file_get_contents($url);
                redis()->set('wp.wp.info.' . $last, $wp);
            }

            if (strstr($wp, 'class="infobox')) {
                $infos['name']  = Utils::cut('"wgTitle":"', '"', $wp);
                $infos['image'] = Utils::cut('src="//upload.wikimedia.org/', '"', Utils::cut('class="infobox', '</table>', $wp));

                if (strlen($infos['image'])) {
                    $infos['image'] = 'http://upload.wikimedia.org/' . $infos['image'];
                }

                if (strstr($wp, 'Site web</a></th>')) {
                    $segTmp = Utils::cut('Site web</a></th>', '</tr>', $wp);
                    $infos['website'] = strip_tags(Utils::cut('<td>', '</td>', $wp));
                }

                if (strstr($wp, 'Canton</a></th>')) {
                    $segTmp = Utils::cut('Canton</a></th>', '</tr>', $wp);
                    $infos['canton'] = strip_tags(Utils::cut('<td>', '</td>', $wp));
                }

                if (strstr($wp, 'Arrondissement</a></th>')) {
                    $segTmp = Utils::cut('Arrondissement</a></th>', '</tr>', $wp);
                    $infos['arrondissement'] = strip_tags(Utils::cut('<td>', '</td>', $wp));
                }

                if (strstr($wp, 'Région</a></th>')) {
                    $segTmp = Utils::cut('Région</a></th>', '</tr>', $wp);
                    $infos['region'] = strip_tags(Utils::cut('<td>', '</td>', $wp));
                }

                if (strstr($wp, 'Gentilé</a></th>')) {
                    $segTmp = Utils::cut('Gentilé</a></th>', '</tr>', $wp);
                    $infos['gentile'] = strip_tags(Utils::cut('<td>', '</td>', $wp));
                }

                if (strstr($wp, 'Code commune</a></th>')) {
                    $segTmp = Utils::cut('Code commune</a></th>', '</tr>', $wp);
                    $infos['city_code'] = strip_tags(Utils::cut('<td>', '</td>', $wp));
                }

                $coords = Utils::cut('<span class="geo-dec">', '</span>', $wp);

                if (fnmatch('*,*', $coords)) {
                    list($infos['latitude'], $infos['longitude']) = explode(',', $coords, 2);
                }

                $segAddress = Utils::cut('>Adresse</th>', '</tr>', $wp);

                if (strlen($segAddress)) {
                    $infos['address'] = str_replace(["\n", "\r", "\t"], '', strip_tags($segAddress));
                } else {
                    if (isset($infos['latitude']) && isset($infos['longitude'])) {
                        $address = lib('geo')->getAddressByCoords((float) $infos['latitude'], (float) $infos['longitude'], true);
                        $infos['address'] = $address['address'];
                        $infos['city'] = $address['city'];
                        $infos['country'] = $address['country'];
                    }
                }

                $segCity = Utils::cut('>Ville</th>', '</tr>', $wp);

                if (strlen($segCity)) {
                    $infos['city'] = str_replace(["\n", "\r", "\t"], '', strip_tags($segCity));
                }

                $segCountry = Utils::cut('>Pays</th>', '</tr>', $wp);

                if (strlen($segCountry)) {
                    $infos['country'] = str_replace(["\n", "\r", "\t"], '', strip_tags($segCountry));
                }

                // list($dummy, $seg) = explode(Utils::cut('<table class="infobox', '</table>', $wp), $wp, 2);
                $wp = str_replace('<p><small>', '<small>', $wp);
                $wp = str_replace('<p><span', '<span>', $wp);

                $p = Utils::cut('<p>', '</p>', $wp);

                $infos['abstract'] = str_replace(["\n", "\r", "\t"], '', strip_tags($p));
            } else {
                if (!$html) {
                    $html = file_get_contents($urlDb);
                    redis()->set('wp.html.info.' . $last, $html);
                }

                $infos['name'] = Utils::cut('<title ng:bind-template="{{about.title}} | DBpedia">', '</title>', $html);
                $infos['abstract'] = Utils::cut('<li><span class="literal"><span property="dbpedia-owl:abstract" xmlns:dbpedia-owl="http://dbpedia.org/ontology/">', '</span>', $html);
                $infos['latitude'] = Utils::cut('<li><span class="literal"><span property="prop-fr:latitude" xmlns:prop-fr="http://fr.dbpedia.org/property/">', '</span>', $html);
                $infos['longitude'] = Utils::cut('<li><span class="literal"><span property="prop-fr:longitude" xmlns:prop-fr="http://fr.dbpedia.org/property/">', '</span>', $html);
                $infos['image'] = Utils::cut('<li><span class="literal"><a class="uri" rel="dbpedia-owl:thumbnail nofollow" xmlns:dbpedia-owl="http://dbpedia.org/ontology/" href="', '"', $html);

                $address = lib('geo')->getAddressByCoords((float) $infos['latitude'], (float) $infos['longitude'], true);
                $infos['address'] = $address['address'];
                $infos['city'] = $address['city'];
                $infos['country'] = $address['country'];

                $wp = str_replace('<p><small>', '<small>', $wp);
                $wp = str_replace('<p><span', '<span>', $wp);

                $p = Utils::cut('<p>', '</p>', $wp);

                $infos['abstract'] = str_replace(["\n", "\r", "\t"], '', strip_tags($p));

                if (fnmatch('http*', $infos['name'])) {
                    $infos['name'] = ucwords(str_replace('_', ' ', Arrays::end(explode('/', $infos['name']))));
                }

                $tagsHtml = explode('ontology/" href="http://fr.dbpedia.org/resource/', $html);
                array_shift($tagsHtml);

                $tags = [];

                foreach ($tagsHtml as $tagHtml) {
                    list($tag, $dummy) = explode('">', $tagHtml, 2);

                    if (!fnmatch('*:*', $tag) && !is_numeric($tag)) {
                        $tags[] = $tag;
                    }
                }
            }

            $wpid               = Utils::cut('"wgArticleId":', ',', $wp);
            $infos['id_wp']     = (int) $wpid;

            $app = $this->getApp($last);

            $media = array_get($app, 'lead.media');

            if (is_array($media)) {
                $items = isAke($media, 'items', []);

                if (!empty($items) && is_array($items)) {
                    $item = current($items);
                    $infos['image'] = isAke($item, 'url', false);
                }
            }

            if (!isset($infos['image'])) {
                $infos['image'] = $this->getImage((int) $wpid);
            }

            if (!$infos['image']) {
                $infos['image'] = $this->getImage((int) $wpid);
            }

            $infos['abstract']  = str_replace(['[1]', '[2]', '[3]', '[4]', '[5]'], '', $infos['abstract']);
            $infos['abstract']  = $this->getExtract((int) $wpid);

            // $tags = array_unique($tags);
            // asort($tags);

            // $infos['tags'] = array_values($tags);

            return $infos;

        }

        public function getInfosPersonne($url)
        {
            $last = Arrays::last(explode('/', $url));

            $wp = redis()->get('wp.wp.info.' . $last);
            $html = redis()->get('wp.html.info.' . $last);

            if (!$wp) {
                $wp = file_get_contents($url);
                redis()->set('wp.wp.info.' . $last, $wp);
            }

            $url = 'http://fr.dbpedia.org/page/' . $last;
            $infos = [];
            $html = redis()->get('wp.info.' . $last);

            if (!$html) {
                $html = file_get_contents($url);
                redis()->set('wp.info.' . $last, $html);
            }

            $infos['completename'] = Utils::cut('<title ng:bind-template="{{about.title}} | DBpedia">', '</title>', $html);
            $infos['abstract'] = Utils::cut('<li><span class="literal"><span property="dbpedia-owl:abstract" xmlns:dbpedia-owl="http://dbpedia.org/ontology/">', '</span>', $html);
            $infos['image'] = Utils::cut('<li><span class="literal"><a class="uri" rel="dbpedia-owl:thumbnail nofollow" xmlns:dbpedia-owl="http://dbpedia.org/ontology/" href="', '"', $html);
            $infos['profession'] = Utils::cut('<li><span class="literal"><a class="uri" rel="dbpedia-owl:profession" xmlns:dbpedia-owl="http://dbpedia.org/ontology/" href="http://fr.dbpedia.org/resource/', '"', $html);
            $infos['birthname'] = Utils::cut('<li><span class="literal"><span property="dbpedia-owl:birthName" xmlns:dbpedia-owl="http://dbpedia.org/ontology/">', '</span>', $html);

            $birthdate = Utils::cut('<li><span class="literal"><span property="dbpedia-owl:birthDate" xmlns:dbpedia-owl="http://dbpedia.org/ontology/">', '</span>', $html);

            list($y, $m, $d) = explode('-', $birthdate, 3);
            $birthdate = "$d/$m/$y";

            $infos['birthdate'] = $birthdate;

            $deathdate = Utils::cut('<li><span class="literal"><span property="dbpedia-owl:deathDate" xmlns:dbpedia-owl="http://dbpedia.org/ontology/">', '</span>', $html);

            list($y, $m, $d) = explode('-', $deathdate, 3);
            $deathdate = "$d/$m/$y";

            $infos['deathdate'] = $deathdate != '//' ? $deathdate : null;

            // $tagsHtml = explode('ontology/" href="http://fr.dbpedia.org/resource/', $html);
            // array_shift($tagsHtml);

            // $tags = [];

            $wp = str_replace('<p><small>', '<small>', $wp);
            $wp = str_replace('<p><span', '<span>', $wp);

            $wpid   = Utils::cut('"wgArticleId":', ',', $wp);
            $p      = Utils::cut('<p>', '</p>', $wp);

            $infos['abstract']  = str_replace(["\n", "\r", "\t"], '', strip_tags($p));
            $infos['id_wp']     = (int) $wpid;

            if (strstr($wp, 'Profession</th')) {
                $tab = explode('Profession</th>', $wp);

                if (count($tab) > 1) {
                    $seg = Utils::cut('<td>', '</td>', $tab[1]);

                    if (strlen($seg)) {
                        $seg = str_replace(['<br />', '<br/>', '<br>'], ', ', $seg);
                        $infos['profession'] = strip_tags($seg);
                    }
                }
            } else {
                if (strstr($wp, 'Activité principale</th')) {
                    $tab = explode('Activité principale</th', $wp);

                    if (count($tab) > 1) {
                        $seg = Utils::cut('<td>', '</td>', $tab[1]);

                        if (strlen($seg)) {
                            $seg = str_replace(['<br />', '<br/>', '<br>'], ', ', $seg);
                            $infos['profession'] = strip_tags($seg);
                        }
                    }
                }
            }

            // foreach ($tagsHtml as $tagHtml) {
            //     list($tag, $dummy) = explode('">', $tagHtml, 2);

            //     if (!fnmatch('*:*', $tag) && !is_numeric($tag)) {
            //         $tags[] = $tag;
            //     }
            // }

            if (fnmatch('http*', $infos['completename'])) {
                $infos['completename'] = ucwords(str_replace('_', ' ', Arrays::end(explode('/', $infos['completename']))));
            }

            $app = $this->getApp($last);

            $media = array_get($app, 'lead.media');

            if (is_array($media)) {
                $items = isAke($media, 'items', []);

                if (!empty($items)) {
                    $item = current($items);
                    $infos['image'] = isAke($item, 'url', false);
                }
            }

            if (!isset($infos['image'])) {
                $infos['image'] = $this->getImage((int) $wpid);
            }

            if (!$infos['image']) {
                $infos['image'] = $this->getImage((int) $wpid);
            }

            $infos['abstract']  = str_replace(['[1]', '[2]', '[3]', '[4]', '[5]'], '', $infos['abstract']);
            $infos['abstract']  = $this->getExtract((int) $wpid);

            // $tags = array_unique($tags);
            // asort($tags);

            // $infos['tags'] = array_values($tags);

            return $infos;
        }

        private function clean($str)
        {
            if (strlen($str)) {
                if (':' == $str[0]) {
                    $str = substr($str, 1, strlen($stre));
                }
            }

            return $str;
        }

        public function searchWd($q)
        {
            /*https://www.wikidata.org/w/api.php?action=wbgetentities&ids=Q1631&format=json*/
            $keyCache = 'wm.search.' . sha1($q);
            $json = redis()->get($keyCache);

            if (!$json) {
                $json = file_get_contents('https://www.wikidata.org/w/api.php?action=wbsearchentities&search='.urlencode($q).'&language=fr&limit=20&format=json');
                redis()->set($keyCache, $json);
                redis()->expire($keyCache, strtotime('+6 month') - time());
            }

            $tab = json_decode($json, true);

            dd($tab);
        }

        public function freebase($rec)
        {
            $url = 'https://www.googleapis.com/freebase/v1/search?query=' . urlencode(urldecode($rec)) . '&key=AIzaSyCQVC9yA72POMg2VjiQhSJQQP1nf3ToZTs&lang=s%2Ffr%2Cd%2Ffr%2Cs%2Fall%2Cd%2Fall';
        }

        public function getExtract($id)
        {
            $json = getCached('getExtract.wp.' . $id, function () use ($id) {
                return file_get_contents("https://fr.wikipedia.org/w/api.php?action=query&pageids=$id&prop=extracts|pageimages|pageterms&format=json&redirects=true&exchars=1024&explaintext=true&piprop=thumbnail|name");
            });

            $tab = json_decode($json, true);

            $extract = array_get($tab, 'query.pages.' . $id . '.extract');

            return Arrays::first(explode("\n", $extract));
        }

        public function getImage($id)
        {
            $json = getCached('getImage.wp.' . $id, function () use ($id) {
                return file_get_contents("https://fr.wikipedia.org/w/api.php?action=query&pageids=$id&prop=pageimages&format=json");
            });

            $tab = json_decode($json, true);
            $seg = array_get($tab, 'query.pages.' . $id . '.thumbnail.source');

            return str_replace('50px-', '360px-', $seg);
        }

        public function tags($id)
        {
            $collection = [];

            $json = isCached('tags.wp.' . $id, function () use ($id) {
                return file_get_contents("https://fr.wikipedia.org/w/api.php?action=query&pageids=$id&prop=revisions|pageimages|pageterms&rvprop=content&format=json");
            });

            $tab = json_decode($json, true);
            $tab = array_get($tab, 'query.pages.' . $id . '.revisions');

            if (!is_array($tab)) {
                return [];
            }

            $seg = current($tab);
            $seg = $seg['*'];

            $tags = explode('[[', $seg);
            array_shift($tags);

            foreach ($tags as $tag) {
                if (strlen($tag) > 4095) continue;

                if (fnmatch('*|*]]*', $tag)) {
                    list($tag, $dummy) = explode('|', $tag, 2);
                }

                list($tag, $dummy) = explode(']]', $tag, 2);

                if (!in_array($tag, $collection) && !fnmatch('*{*', $tag) && !fnmatch('*|*', $tag) && strlen($tag) > 2) {
                    $collection[] = $tag;
                }
            }

            return $collection;
        }

        public function getPois($lat, $lng)
        {
            $ll = lib('geo')->getBoundingBox($lat, $lng);
            $url = "https://tools.wmflabs.org/wp-world/marks.php?LANG=fr&coats=0&thumbs=0&bbox={$ll[1]},{$ll[0]},{$ll[3]},{$ll[2]}";
            $kml = lib('geo')->dwnCache($url);
        }
    }
