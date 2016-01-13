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

    class InflectorCore
    {
        /**
         * Contains a default map of accented and special characters to ASCII characters.  Can be
         * extended or added to using `Inflector::rules()`.
         *
         * @see Inflector::slug()
         * @see Inflector::rules()
         * @var array
         */
        protected static $_transliteration = array(
            '/à|á|å|â/' => 'a',
            '/è|é|ê|ẽ|ë/' => 'e',
            '/ì|í|î/' => 'i',
            '/ò|ó|ô|ø/' => 'o',
            '/ù|ú|ů|û/' => 'u',
            '/ç/' => 'c', '/ñ/' => 'n',
            '/ä|æ/' => 'ae', '/ö/' => 'oe',
            '/ü/' => 'ue', '/Ä/' => 'Ae',
            '/Ü/' => 'Ue', '/Ö/' => 'Oe',
            '/ß/' => 'ss'
       );

        /**
         * Indexed array of words which are the same in both singular and plural form.  You can add
         * rules to this list using `Inflector::rules()`.
         *
         * @see Inflector::rules()
         * @var array
         */
        protected static $_uninflected = array(
            'Amoyese', 'bison', 'Borghese', 'bream', 'breeches', 'britches', 'buffalo', 'cantus',
            'carp', 'chassis', 'clippers', 'cod', 'coitus', 'Congoese', 'contretemps', 'corps',
            'debris', 'diabetes', 'djinn', 'eland', 'elk', 'equipment', 'Faroese', 'flounder',
            'Foochowese', 'gallows', 'Genevese', 'Genoese', 'Gilbertese', 'graffiti',
            'headquarters', 'herpes', 'hijinks', 'Hottentotese', 'information', 'innings',
            'jackanapes', 'Kiplingese', 'Kongoese', 'Lucchese', 'mackerel', 'Maltese', 'media',
            'mews', 'moose', 'mumps', 'Nankingese', 'news', 'nexus', 'Niasese', 'People',
            'Pekingese', 'Piedmontese', 'pincers', 'Pistoiese', 'pliers', 'Portuguese',
            'proceedings', 'rabies', 'rice', 'rhinoceros', 'salmon', 'Sarawakese', 'scissors',
            'sea[- ]bass', 'series', 'Shavese', 'shears', 'siemens', 'species', 'swine', 'testes',
            'trousers', 'trout','tuna', 'Vermontese', 'Wenchowese', 'whiting', 'wildebeest',
            'Yengeese'
       );

        /**
         * Contains the list of pluralization rules.
         *
         * @see Inflector::rules()
         * @var array Contains the following keys:
         *   - `'rules'`: An array of regular expression rules in the form of `'match' => 'replace'`,
         *     which specify the matching and replacing rules for the pluralization of words.
         *   - `'uninflected'`: A indexed array containing regex word patterns which do not get
         *     inflected (i.e. singular and plural are the same).
         *   - `'irregular'`: Contains key-value pairs of specific words which are not inflected
         *     according to the rules. This is populated from `Inflector::$_plural` when the class
         *     is loaded.
         */
        protected static $_singular = array(
            'rules' => array(
                '/(s)tatuses$/i' => '\1\2tatus',
                '/^(.*)(menu)s$/i' => '\1\2',
                '/(quiz)zes$/i' => '\\1',
                '/(matr)ices$/i' => '\1ix',
                '/(vert|ind)ices$/i' => '\1ex',
                '/^(ox)en/i' => '\1',
                '/(alias)(es)*$/i' => '\1',
                '/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|viri?)i$/i' => '\1us',
                '/(cris|ax|test)es$/i' => '\1is',
                '/(shoe)s$/i' => '\1',
                '/(o)es$/i' => '\1',
                '/ouses$/' => 'ouse',
                '/uses$/' => 'us',
                '/([m|l])ice$/i' => '\1ouse',
                '/(x|ch|ss|sh)es$/i' => '\1',
                '/(m)ovies$/i' => '\1\2ovie',
                '/(s)eries$/i' => '\1\2eries',
                '/([^aeiouy]|qu)ies$/i' => '\1y',
                '/([lr])ves$/i' => '\1f',
                '/(tive)s$/i' => '\1',
                '/(hive)s$/i' => '\1',
                '/(drive)s$/i' => '\1',
                '/([^fo])ves$/i' => '\1fe',
                '/(^analy)ses$/i' => '\1sis',
                '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\1\2sis',
                '/([ti])a$/i' => '\1um',
                '/(p)eople$/i' => '\1\2erson',
                '/(m)en$/i' => '\1an',
                '/(c)hildren$/i' => '\1\2hild',
                '/(n)ews$/i' => '\1\2ews',
                '/^(.*us)$/' => '\\1',
                '/s$/i' => ''
           ),
            'irregular' => array(),
            'uninflected' => array(
                '.*[nrlm]ese', '.*deer', '.*fish', '.*measles', '.*ois', '.*pox', '.*sheep', '.*ss'
           )
       );

        /**
         * Contains a cache map of previously singularized words.
         *
         * @var array
         */
        protected static $_singularized = [];

        /**
         * Contains the list of pluralization rules.
         *
         * @see Inflector::rules()
         * @var array Contains the following keys:
         *   - `'rules'`: An array of regular expression rules in the form of `'match' => 'replace'`,
         *     which specify the matching and replacing rules for the pluralization of words.
         *   - `'uninflected'`: A indexed array containing regex word patterns which do not get
         *     inflected (i.e. singular and plural are the same).
         *   - `'irregular'`: Contains key-value pairs of specific words which are not inflected
         *     according to the rules.
         */
        protected static $_plural = array(
            'rules' => array(
                '/(s)tatus$/i' => '\1\2tatuses',
                '/(quiz)$/i' => '\1zes',
                '/^(ox)$/i' => '\1\2en',
                '/([m|l])ouse$/i' => '\1ice',
                '/(matr|vert|ind)(ix|ex)$/i'  => '\1ices',
                '/(x|ch|ss|sh)$/i' => '\1es',
                '/([^aeiouy]|qu)y$/i' => '\1ies',
                '/(hive)$/i' => '\1s',
                '/(?:([^f])fe|([lr])f)$/i' => '\1\2ves',
                '/sis$/i' => 'ses',
                '/([ti])um$/i' => '\1a',
                '/(p)erson$/i' => '\1eople',
                '/(m)an$/i' => '\1en',
                '/(c)hild$/i' => '\1hildren',
                '/(buffal|tomat)o$/i' => '\1\2oes',
                '/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|vir)us$/i' => '\1i',
                '/us$/' => 'uses',
                '/(alias)$/i' => '\1es',
                '/(ax|cri|test)is$/i' => '\1es',
                '/s$/' => 's',
                '/^$/' => '',
                '/$/' => 's'
           ),
            'irregular' => array(
                'atlas' => 'atlases', 'beef' => 'beefs', 'brother' => 'brothers',
                'child' => 'children', 'corpus' => 'corpuses', 'cow' => 'cows',
                'ganglion' => 'ganglions', 'genie' => 'genies', 'genus' => 'genera',
                'graffito' => 'graffiti', 'hoof' => 'hoofs', 'loaf' => 'loaves', 'man' => 'men',
                'leaf' => 'leaves', 'money' => 'monies', 'mongoose' => 'mongooses', 'move' => 'moves',
                'mythos' => 'mythoi', 'numen' => 'numina', 'occiput' => 'occiputs',
                'octopus' => 'octopuses', 'opus' => 'opuses', 'ox' => 'oxen', 'penis' => 'penises',
                'person' => 'people', 'sex' => 'sexes', 'soliloquy' => 'soliloquies',
                'testis' => 'testes', 'trilby' => 'trilbys', 'turf' => 'turfs'
           ),
            'uninflected' => array(
                '.*[nrlm]ese', '.*deer', '.*fish', '.*measles', '.*ois', '.*pox', '.*sheep'
           )
       );

        /**
         * Contains a cache map of previously pluralized words.
         *
         * @var array
         */
        protected static $_pluralized = [];

        /**
         * Contains a cache map of previously camelized words.
         *
         * @var array
         */
        protected static $_camelized = [];

        /**
         * Contains a cache map of previously underscored words.
         *
         * @var array
         */
        protected static $_underscored = [];

        /**
         * Contains a cache map of previously humanized words.
         *
         * @var array
         */
        protected static $_humanized = [];

        public function utf8($str)
        {
            if (false === Utils::isUtf8($str)) {
                $str = utf8_encode($str);
            }

            return $str;
        }

        /**
         * Gets or adds inflection and transliteration rules.
         *
         * @param string $type Either `'transliteration'`, `'uninflected'`, `'singular'` or `'plural'`.
         * @param array $config
         * @return mixed If `$config` is empty, returns the rules list specified
         *         by `$type`, otherwise returns `null`.
         */
        public function rules($type, $config = array())
        {
            $var = '_' . $type;

            if (!isset(self::${$var})) {
                return null;
            }

            if (empty($config)) {
                return self::${$var};
            }

            switch ($type) {
                case 'transliteration':
                    $_config = [];

                    foreach ($config as $key => $val) {
                        if ($key[0] != '/') {
                            $key = '/' . join('|', array_filter(preg_split('//u', $key))) . '/';
                        }

                        $_config[$key] = $val;
                    }
                    self::$_transliteration = array_merge(
                        $_config, self::$_transliteration, $_config
                   );
                break;
                case 'uninflected':
                    self::$_uninflected = array_merge(self::$_uninflected, (array) $config);
                    self::$_plural['regexUninflected'] = null;
                    self::$_singular['regexUninflected'] = null;

                    foreach ((array) $config as $word) {
                        unset(self::$_singularized[$word], self::$_pluralized[$word]);
                    }
                break;
                case 'singular':
                case 'plural':
                    if (isset(self::${$var}[key($config)])) {
                        foreach ($config as $rType => $set) {
                            self::${$var}[$rType] = array_merge($set, self::${$var}[$rType], $set);

                            if ($rType == 'irregular') {
                                $swap = ($type == 'singular' ? '_plural' : '_singular');
                                self::${$swap}[$rType] = array_flip(self::${$var}[$rType]);
                            }
                        }
                    } else {
                        self::${$var}['rules'] = array_merge(
                            $config, self::${$var}['rules'], $config
                       );
                    }
                break;
            }
        }

        /**
         * Changes the form of a word from singular to plural.
         *
         * @param string $word Word in singular form.
         * @return string Word in plural form.
         */
        public function pluralize($word)
        {
            if (isset(self::$_pluralized[$word])) {
                return self::$_pluralized[$word];
            }

            extract(self::$_plural);

            if (!isset($regexUninflected) || !isset($regexIrregular)) {
                $regexUninflected = $this->_enclose(join('|', $uninflected + self::$_uninflected));
                $regexIrregular = $this->_enclose(join('|', array_keys($irregular)));

                self::$_plural += compact('regexUninflected', 'regexIrregular');
            }

            if (preg_match('/(' . $regexUninflected . ')$/i', $word, $regs)) {
                return self::$_pluralized[$word] = $word;
            }

            if (preg_match('/(.*)\\b(' . $regexIrregular . ')$/i', $word, $regs)) {
                $plural = substr($word, 0, 1) . substr($irregular[$this->lower($regs[2])], 1);

                return self::$_pluralized[$word] = $regs[1] . $plural;
            }

            foreach ($rules as $rule => $replacement) {
                if (preg_match($rule, $word)) {
                    return self::$_pluralized[$word] = preg_replace($rule, $replacement, $word);
                }
            }

            return self::$_pluralized[$word] = $word;
        }

        /**
         * Changes the form of a word from plural to singular.
         *
         * @param string $word Word in plural form.
         * @return string Word in singular form.
         */
        public function singularize($word)
        {
            if (isset(self::$_singularized[$word])) {
                return self::$_singularized[$word];
            }

            if (empty(self::$_singular['irregular'])) {
                self::$_singular['irregular'] = array_flip(self::$_plural['irregular']);
            }

            extract(self::$_singular);

            if (!isset($regexUninflected) || !isset($regexIrregular)) {
                $regexUninflected = $this->_enclose(join('|', $uninflected + self::$_uninflected));
                $regexIrregular = $this->_enclose(join('|', array_keys($irregular)));
                self::$_singular += compact('regexUninflected', 'regexIrregular');
            }

            if (preg_match("/(.*)\\b({$regexIrregular})\$/i", $word, $regs)) {
                $singular = substr($word, 0, 1) . substr($irregular[strtolower($regs[2])], 1);
                return self::$_singularized[$word] = $regs[1] . $singular;
            }

            if (preg_match('/^(' . $regexUninflected . ')$/i', $word, $regs)) {
                return self::$_singularized[$word] = $word;
            }

            foreach ($rules as $rule => $replacement) {
                if (preg_match($rule, $word)) {
                    return self::$_singularized[$word] = preg_replace($rule, $replacement, $word);
                }
            }

            return self::$_singularized[$word] = $word;
        }

        /**
         * Clears local in-memory caches.  Can be used to force a full-cache clear when updating
         * inflection rules mid-way through request execution.
         *
         * @return void
         */
        public function reset()
        {
            self::$_singularized    = self::$_pluralized = [];
            self::$_camelized       = self::$_underscored = [];
            self::$_humanized       = [];

            self::$_plural['regexUninflected']  = self::$_singular['regexUninflected'] = null;
            self::$_plural['regexIrregular']    = self::$_singular['regexIrregular'] = null;

            self::$_transliteration = array(
                '/à|á|å|â/' => 'a', '/è|é|ê|ẽ|ë/' => 'e',
                '/ì|í|î/' => 'i', '/ò|ó|ô|ø/' => 'o',
                '/ù|ú|ů|û/' => 'u', '/ç/' => 'c',
                '/ñ/' => 'n', '/ä|æ/' => 'ae', '/ö/' => 'oe',
                '/ü/' => 'ue', '/Ä/' => 'Ae',
                '/Ü/' => 'Ue', '/Ö/' => 'Oe',
                '/ß/' => 'ss'
           );
        }

        /**
         * Takes a under_scored word and turns it into a CamelCased or camelBack word
         *
         * @param string $word An under_scored or slugged word (i.e. `'red_bike'` or `'red-bike'`).
         * @param boolean $cased If false, first character is not upper cased
         * @return string CamelCased version of the word (i.e. `'RedBike'`).
         */
        public function camelize($string, $spacify = true, $lazy = false)
        {
            return implode('', explode(' ', ucwords(implode(' ', explode('_', $string)))));
        }

        public function uncamelize($string, $splitter = "_")
        {
            $string = preg_replace('/(?!^)[[:upper:]][[:lower:]]/', '$0', preg_replace('/(?!^)[[:upper:]]+/', $splitter . '$0', $string));

            return $this->lower($string);
        }

        /**
         * Takes a CamelCased version of a word and turns it into an under_scored one.
         *
         * @param string $word CamelCased version of a word (i.e. `'RedBike'`).
         * @return string Under_scored version of the workd (i.e. `'red_bike'`).
         */
        public function underscore($word)
        {
            if (isset(self::$_underscored[$word])) {
                return self::$_underscored[$word];
            }

            return self::$_underscored[$word] = $this->lower($this->slug($word, '_'));
        }

        /**
         * Returns a string with all spaces converted to given replacement and
         * non word characters removed.  Maps special characters to ASCII using
         * `Inflector::$_transliteration`, which can be updated using `Inflector::rules()`.
         *
         * @see Inflector::rules()
         * @param string $string An arbitrary string to convert.
         * @param string $replacement The replacement to use for spaces.
         * @return string The converted string.
         */
        public function slug($string, $replacement = '-')
        {
            $map = self::$_transliteration + array(
                '/[^\w\s]/' => ' ', '/\\s+/' => $replacement,
                '/(?<=[a-z])([A-Z])/' => $replacement . '\\1',
                str_replace(':rep', preg_quote($replacement, '/'), '/^[:rep]+|[:rep]+$/') => ''
            );

            return preg_replace(array_keys($map), array_values($map), $string);
        }

        /**
         * Takes an under_scored version of a word and turns it into an human- readable form
         * by replacing underscores with a space, and by upper casing the initial character.
         *
         * @param string $word Under_scored version of a word (i.e. `'red_bike'`).
         * @param string $separator The separator character used in the initial string.
         * @return string Human readable version of the word (i.e. `'Red Bike'`).
         */
        public function humanize($word, $separator = '_')
        {
            if (isset(self::$_humanized[$key = $word . ':' . $separator])) {
                return self::$_humanized[$key];
            }

            return self::$_humanized[$key] = ucwords(str_replace($separator, " ", $word));
        }

        /**
         * Takes a CamelCased class name and returns corresponding under_scored table name.
         *
         * @param string $className CamelCased class name (i.e. `'Post'`).
         * @return string Under_scored and plural table name (i.e. `'posts'`).
         */
        public function tableize($className)
        {
            return $this->pluralize($this->underscore($className));
        }

        /**
         * Takes a under_scored table name and returns corresponding class name.
         *
         * @param string $tableName Under_scored and plural table name (i.e. `'posts'`).
         * @return string CamelCased class name (i.e. `'Post'`).
         */
        public function reclassify($tableName)
        {
            return $this->camelize($this->singularize($tableName));
        }

        /**
         * Enclose a string for preg matching.
         *
         * @param string $string String to enclose
         * @return string Enclosed string
         */
        protected function _enclose($string)
        {
            return '(?:' . $string . ')';
        }

        public function length($value)
        {
            return (MB_STRING) ? mb_strlen($value, $this->encoding()) : strlen($value);
        }

        public function lower($value)
        {
            return (MB_STRING) ? mb_strtolower($value, $this->encoding()) : strtolower($value);
        }

        public function upper($value)
        {
            return (MB_STRING) ? mb_strtoupper($value, $this->encoding()) : strtoupper($value);
        }

        public function substr($str, $start, $length = false, $encoding = 'utf-8')
        {
            if (Arrays::is($str)) {
                return false;
            }

            if (function_exists('mb_substr')) {
                return mb_substr($str, (int)$start, ($length === false ? $this->length($str) : (int)$length), $encoding);
            }

            return substr($str, $start, ($length === false ? $this->length($str) : (int)$length));
        }

        public function ucfirst($str)
        {
            return $this->upper($this->substr($str, 0, 1)) . $this->substr($str, 1);
        }

        public function isEmpty($value)
        {
            return ($value === '' || $value === null);
        }

        public function encoding()
        {
            return 'utf-8';
        }

        public function urlize($text, $separator = '-')
        {
            $text = str_replace(
                array(
                    "\n",
                    "\r",
                    "\t",
                ),
                '',
                $text
            );

            // Remove all non url friendly characters with the unaccent function
            $text = $this->lower($this->unaccent($text));

            // Remove all none word characters
            $text = preg_replace('/\W/', ' ', $text);

            // More stripping. Replace spaces with dashes
            $text = $this->lower(
                preg_replace(
                    '/[^A-Z^a-z^0-9^\/]+/',
                    $separator,
                    preg_replace(
                        '/([a-z\d])([A-Z])/',
                        '\1_\2',
                        preg_replace(
                            '/([A-Z]+)([A-Z][a-z])/',
                            '\1_\2',
                            preg_replace(
                                '/::/',
                                '/',
                                $text
                            )
                        )
                    )
                )
            );

            return trim($text, $separator);
        }

        public function makeIndexes($str)
        {
            $words = $this->urlize($str, ' ');

            $words = explode(' ', $words);

            $collection = [];

            if (count($words)) {
                foreach ($words as $word) {
                    if (strlen($word) > 1 || is_numeric($word)) {
                        array_push($collection, $word);
                    }
                }
            }

            return $collection;
        }

        public function unaccent($string)
        {
            if (!preg_match('/[\x80-\xff]/', $string)) {
                return $string;
            }

            if (Utils::isUtf8($string)) {
                $chars = array(
                    // Decompositions for Latin-1 Supplement
                    chr(195).chr(128) => 'A',
                    chr(195).chr(129) => 'A',
                    chr(195).chr(130) => 'A',
                    chr(195).chr(131) => 'A',
                    chr(195).chr(132) => 'A',
                    chr(195).chr(133) => 'A',
                    chr(195).chr(135) => 'C',
                    chr(195).chr(136) => 'E',
                    chr(195).chr(137) => 'E',
                    chr(195).chr(138) => 'E',
                    chr(195).chr(139) => 'E',
                    chr(195).chr(140) => 'I',
                    chr(195).chr(141) => 'I',
                    chr(195).chr(142) => 'I',
                    chr(195).chr(143) => 'I',
                    chr(195).chr(145) => 'N',
                    chr(195).chr(146) => 'O',
                    chr(195).chr(147) => 'O',
                    chr(195).chr(148) => 'O',
                    chr(195).chr(149) => 'O',
                    chr(195).chr(150) => 'O',
                    chr(195).chr(153) => 'U',
                    chr(195).chr(154) => 'U',
                    chr(195).chr(155) => 'U',
                    chr(195).chr(156) => 'U',
                    chr(195).chr(157) => 'Y',
                    chr(195).chr(159) => 's',
                    chr(195).chr(160) => 'a',
                    chr(195).chr(161) => 'a',
                    chr(195).chr(162) => 'a',
                    chr(195).chr(163) => 'a',
                    chr(195).chr(164) => 'a',
                    chr(195).chr(165) => 'a',
                    chr(195).chr(167) => 'c',
                    chr(195).chr(168) => 'e',
                    chr(195).chr(169) => 'e',
                    chr(195).chr(170) => 'e',
                    chr(195).chr(171) => 'e',
                    chr(195).chr(172) => 'i',
                    chr(195).chr(173) => 'i',
                    chr(195).chr(174) => 'i',
                    chr(195).chr(175) => 'i',
                    chr(195).chr(177) => 'n',
                    chr(195).chr(178) => 'o',
                    chr(195).chr(179) => 'o',
                    chr(195).chr(180) => 'o',
                    chr(195).chr(181) => 'o',
                    chr(195).chr(182) => 'o',
                    chr(195).chr(182) => 'o',
                    chr(195).chr(185) => 'u',
                    chr(195).chr(186) => 'u',
                    chr(195).chr(187) => 'u',
                    chr(195).chr(188) => 'u',
                    chr(195).chr(189) => 'y',
                    chr(195).chr(191) => 'y',
                    // Decompositions for Latin Extended-A
                    chr(196).chr(128) => 'A',
                    chr(196).chr(129) => 'a',
                    chr(196).chr(130) => 'A',
                    chr(196).chr(131) => 'a',
                    chr(196).chr(132) => 'A',
                    chr(196).chr(133) => 'a',
                    chr(196).chr(134) => 'C',
                    chr(196).chr(135) => 'c',
                    chr(196).chr(136) => 'C',
                    chr(196).chr(137) => 'c',
                    chr(196).chr(138) => 'C',
                    chr(196).chr(139) => 'c',
                    chr(196).chr(140) => 'C',
                    chr(196).chr(141) => 'c',
                    chr(196).chr(142) => 'D',
                    chr(196).chr(143) => 'd',
                    chr(196).chr(144) => 'D',
                    chr(196).chr(145) => 'd',
                    chr(196).chr(146) => 'E',
                    chr(196).chr(147) => 'e',
                    chr(196).chr(148) => 'E',
                    chr(196).chr(149) => 'e',
                    chr(196).chr(150) => 'E',
                    chr(196).chr(151) => 'e',
                    chr(196).chr(152) => 'E',
                    chr(196).chr(153) => 'e',
                    chr(196).chr(154) => 'E',
                    chr(196).chr(155) => 'e',
                    chr(196).chr(156) => 'G',
                    chr(196).chr(157) => 'g',
                    chr(196).chr(158) => 'G',
                    chr(196).chr(159) => 'g',
                    chr(196).chr(160) => 'G',
                    chr(196).chr(161) => 'g',
                    chr(196).chr(162) => 'G',
                    chr(196).chr(163) => 'g',
                    chr(196).chr(164) => 'H',
                    chr(196).chr(165) => 'h',
                    chr(196).chr(166) => 'H',
                    chr(196).chr(167) => 'h',
                    chr(196).chr(168) => 'I',
                    chr(196).chr(169) => 'i',
                    chr(196).chr(170) => 'I',
                    chr(196).chr(171) => 'i',
                    chr(196).chr(172) => 'I',
                    chr(196).chr(173) => 'i',
                    chr(196).chr(174) => 'I',
                    chr(196).chr(175) => 'i',
                    chr(196).chr(176) => 'I',
                    chr(196).chr(177) => 'i',
                    chr(196).chr(178) => 'IJ',
                    chr(196).chr(179) => 'ij',
                    chr(196).chr(180) => 'J',
                    chr(196).chr(181) => 'j',
                    chr(196).chr(182) => 'K',
                    chr(196).chr(183) => 'k',
                    chr(196).chr(184) => 'k',
                    chr(196).chr(185) => 'L',
                    chr(196).chr(186) => 'l',
                    chr(196).chr(187) => 'L',
                    chr(196).chr(188) => 'l',
                    chr(196).chr(189) => 'L',
                    chr(196).chr(190) => 'l',
                    chr(196).chr(191) => 'L',
                    chr(197).chr(128) => 'l',
                    chr(197).chr(129) => 'L',
                    chr(197).chr(130) => 'l',
                    chr(197).chr(131) => 'N',
                    chr(197).chr(132) => 'n',
                    chr(197).chr(133) => 'N',
                    chr(197).chr(134) => 'n',
                    chr(197).chr(135) => 'N',
                    chr(197).chr(136) => 'n',
                    chr(197).chr(137) => 'N',
                    chr(197).chr(138) => 'n',
                    chr(197).chr(139) => 'N',
                    chr(197).chr(140) => 'O',
                    chr(197).chr(141) => 'o',
                    chr(197).chr(142) => 'O',
                    chr(197).chr(143) => 'o',
                    chr(197).chr(144) => 'O',
                    chr(197).chr(145) => 'o',
                    chr(197).chr(146) => 'OE',
                    chr(197).chr(147) => 'oe',
                    chr(197).chr(148) => 'R',
                    chr(197).chr(149) => 'r',
                    chr(197).chr(150) => 'R',
                    chr(197).chr(151) => 'r',
                    chr(197).chr(152) => 'R',
                    chr(197).chr(153) => 'r',
                    chr(197).chr(154) => 'S',
                    chr(197).chr(155) => 's',
                    chr(197).chr(156) => 'S',
                    chr(197).chr(157) => 's',
                    chr(197).chr(158) => 'S',
                    chr(197).chr(159) => 's',
                    chr(197).chr(160) => 'S',
                    chr(197).chr(161) => 's',
                    chr(197).chr(162) => 'T',
                    chr(197).chr(163) => 't',
                    chr(197).chr(164) => 'T',
                    chr(197).chr(165) => 't',
                    chr(197).chr(166) => 'T',
                    chr(197).chr(167) => 't',
                    chr(197).chr(168) => 'U',
                    chr(197).chr(169) => 'u',
                    chr(197).chr(170) => 'U',
                    chr(197).chr(171) => 'u',
                    chr(197).chr(172) => 'U',
                    chr(197).chr(173) => 'u',
                    chr(197).chr(174) => 'U',
                    chr(197).chr(175) => 'u',
                    chr(197).chr(176) => 'U',
                    chr(197).chr(177) => 'u',
                    chr(197).chr(178) => 'U',
                    chr(197).chr(179) => 'u',
                    chr(197).chr(180) => 'W',
                    chr(197).chr(181) => 'w',
                    chr(197).chr(182) => 'Y',
                    chr(197).chr(183) => 'y',
                    chr(197).chr(184) => 'Y',
                    chr(197).chr(185) => 'Z',
                    chr(197).chr(186) => 'z',
                    chr(197).chr(187) => 'Z',
                    chr(197).chr(188) => 'z',
                    chr(197).chr(189) => 'Z',
                    chr(197).chr(190) => 'z',
                    chr(197).chr(191) => 's',
                    // Euro Sign
                    chr(226).chr(130).chr(172) => 'E',
                    // GBP (Pound) Sign
                    chr(194).chr(163) => '',
                    'Ã„' => 'Ae', 'Ã¤' => 'ae', 'Ãœ' => 'Ue', 'Ã¼' => 'ue',
                    'Ã–' => 'Oe', 'Ã¶' => 'oe', 'ÃŸ' => 'ss'
                );

                $string = strtr($string, $chars);
            } else {
                // Assume ISO-8859-1 if not UTF-8
                $chars['in'] = chr(128) . chr(131) . chr(138) . chr(142) . chr(154) . chr(158)
                    . chr(159) . chr(162) . chr(165) . chr(181) . chr(192) . chr(193) . chr(194)
                    . chr(195) . chr(196) . chr(197) . chr(199) . chr(200) . chr(201) . chr(202)
                    . chr(203) . chr(204) . chr(205) . chr(206) . chr(207) . chr(209) . chr(210)
                    . chr(211) . chr(212) . chr(213) . chr(214) . chr(216) . chr(217) . chr(218)
                    . chr(219) . chr(220) . chr(221) . chr(224) . chr(225) . chr(226) . chr(227)
                    . chr(228) . chr(229) . chr(231) . chr(232) . chr(233) . chr(234) . chr(235)
                    . chr(236) . chr(237) . chr(238) . chr(239) . chr(241) . chr(242) . chr(243)
                    . chr(244) . chr(245) . chr(246) . chr(248) . chr(249) . chr(250) . chr(251)
                    . chr(252) . chr(253) . chr(255);

                $chars['out']       = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";
                $string             = strtr($string, $chars['in'], $chars['out']);

                $doubleChars['in']  = array(
                    chr(140),
                    chr(156),
                    chr(198),
                    chr(208),
                    chr(222),
                    chr(223),
                    chr(230),
                    chr(240),
                    chr(254)
                );

                $doubleChars['out'] = array(
                    'OE',
                    'oe',
                    'AE',
                    'DH',
                    'TH',
                    'ss',
                    'ae',
                    'dh',
                    'th'
                );

                $string = str_replace(
                    $doubleChars['in'],
                    $doubleChars['out'],
                    $string
                );
            }

            return $string;
        }

        public function stripAccents($str)
        {
            return strtr(
                utf8_decode($str),
                utf8_decode(
                    '’àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'
                ),
                '\'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'
           );
        }

        public function urlSafeB64Encode($data)
        {
            $b64 = base64_encode($data);

            $b64 = str_replace(
                array(
                    '+',
                    '/',
                    '\r',
                    '\n',
                    '='
                ), array(
                    '-',
                    '_'
                ),
                $b64
            );

            return $b64;
        }

        public function urlSafeB64Decode($b64)
        {
            $b64 = str_replace(
                array(
                    '-',
                    '_'
                ),
                array(
                    '+',
                    '/'
                ),
                $b64
            );

            return base64_decode($b64);
        }

        /**
        * Takes the namespace off the given class name.
        *
        * @param string the class name
        * @return string the string without the namespace
        */
        public function deNamespace($className)
        {
            $className = trim($className, '\\');

            if ($lastSeparator = strrpos($className, '\\')) {
                $className = substr($className, $lastSeparator + 1);
            }

            return $className;
        }

        /**
        * Returns the namespace of the given class name.
        *
        * @param string $class_name the class name
        * @return string the string without the namespace
        */
        public function getNamespace($className)
        {
            $className = trim($className, '\\');

            if ($lastSeparator = strrpos($className, '\\')) {
                return substr($className, 0, $lastSeparator + 1);
            }

            return '';
        }

        public function getFileExtension($filename, $lower = true)
        {
            if (true === $lower) {
                $filename = $this->lower($filename);
            }

            $tab = explode('.', $filename);

            return end($tab);
        }

        public function strReplaceFirst($search, $replace, $subject)
        {
            return implode($replace, explode($search, $subject, 2));
        }

        /**
         * Limit the number of characters in a string.
         *
         * <code>
         *      // Returns "hel..."
         *      echo Inflector::limit('hello word', 3);
         *
         *      // Limit the number of characters and append a custom ending
         *      echo Inflector::limit('hello word', 3, '---');
         * </code>
         *
         * @param  string  $value
         * @param  int     $limit
         * @param  string  $end
         * @return string
         */
        public function limit($value, $limit = 100, $end = '...')
        {
            if ($this->length($value) <= $limit) {
                return $value;
            }

            return mb_substr($value, 0, $limit, 'utf8') . $end;
        }

        /**
         * Limit the number of chracters in a string including custom ending
         *
         * <code>
         *      // Returns "hello..."
         *      echo Inflector::limitExact('hello word', 9);
         *
         *      // Limit the number of characters and append a custom ending
         *      echo Inflector::limitExact('hello word', 9, '---');
         * </code>
         *
         * @param  string  $value
         * @param  int     $limit
         * @param  string  $end
         * @return string
         */
        public function limitExact($value, $limit = 100, $end = '...')
        {
            if ($this->length($value) <= $limit) {
                return $value;
            }

            $limit -= $this->length($end);

            return $this->limit($value, $limit, $end);
        }

        /**
         * Convert a string to title case (ucwords equivalent).
         *
         * <code>
         *      // Convert a string to title case
         *      $title = Inflector::title('hello word');
         *
         *      // Convert a multi-byte string to title case
         *      $title = Inflector::title('hélène de Troie');
         * </code>
         *
         * @param  string  $value
         * @return string
         */
        public function title($value)
        {
            return mb_convert_case($value, MB_CASE_TITLE, 'utf8');
        }

        /**
         * Limit the number of words in a string.
         *
         * <code>
         *      // Returns "This is a..."
         *      echo Inflector::words('This is a sentence.', 3);
         *
         *      // Limit the number of words and append a custom ending
         *      echo Inflector::words('This is a sentence.', 3, '---');
         * </code>
         *
         * @param  string  $value
         * @param  int     $words
         * @param  string  $end
         * @return string
         */
        public function words($value, $words = 100, $end = '...')
        {
            if (trim($value) == '') {
                return '';
            }

            preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

            if ($this->length($value) == $this->length($matches[0])) {
                $end = '';
            }

            return rtrim($matches[0]) . $end;
        }

        /**
         * Generate a URL friendly "slug" from a given string.
         *
         * <code>
         *      // Returns "this-is-my-blog-post"
         *      $slug = Inflector::slugify('This is my blog post!');
         *
         *      // Returns "this_is_my_blog_post"
         *      $slug = Inflector::slugify('This is my blog post!', '_');
         * </code>
         *
         * @param  string  $title
         * @param  string  $separator
         * @return string
         */
        public function slugify($title, $separator = '-')
        {
            $title = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $title);

            // Remove all characters that are not the separator, letters, numbers, or whitespace.
            $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', $this->lower($title));

            // Replace all separator characters and whitespace by a single separator
            $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);

            return trim($title, $separator);
        }

        /**
         * Convert a string to an underscored, camel-cased class name.
         *
         * This method is primarily used to format task and controller names.
         *
         * <code>
         *      // Returns "Task_Name"
         *      $class = Inflector::classify('task_name');
         *
         *      // Returns "Hello_Word"
         *      $class = Inflector::classify('taylor otwell')
         * </code>
         *
         * @param  string  $value
         * @return string
         */
        public function classify($value)
        {
            $search = array('_', '-', '.');

            return str_replace(' ', '_', $this->title(str_replace($search, ' ', $value)));
        }

        /**
         * Return the "URI" style segments in a given string.
         *
         * @param  string  $value
         * @return array
         */
        public function segments($value)
        {
            return array_diff(explode('/', trim($value, '/')), array(''));
        }

        /**
         * Generate a random alpha or alpha-numeric string.
         *
         * <code>
         *      // Generate a 40 character random alpha-numeric string
         *      echo Inflector::random(40);
         *
         *      // Generate a 16 character random alphabetic string
         *      echo Inflector::random(16, 'alpha');
         * <code>
         *
         * @param  int     $length
         * @param  string  $type
         * @return string
         */
        public function random($length, $type = 'alnum')
        {
            return substr(str_shuffle(str_repeat($this->pool($type), 5)), 0, $length);
        }

        /**
         * Determine if a given string matches a given pattern.
         *
         * @param  string  $pattern
         * @param  string  $value
         * @return bool
         */
        public function is($pattern, $value)
        {
            if (sha1($pattern) == sha1($value)) {
                return true;
            }

            $pattern = preg_quote($pattern, '#');

            // Asterisks are translated into zero-or-more regular expression wildcards
            // to make it convenient to check if the strings starts with the given
            // pattern such as "library/*", making any string check convenient.

            $pattern = str_replace('\*', '.*', $pattern) . '\z';

            return (bool) preg_match('#^' . $pattern . '#', $value);
        }


        /**
         * Get the character pool for a given type of random string.
         *
         * @param  string  $type
         * @return string
         */
        protected function pool($type)
        {
            switch ($type) {
                case 'alpha':
                    return 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                case 'alnum':
                    return '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                default:
                    throw new Exception("Invalid random string type [$type].");
            }
        }

        /**
         * Generate a more truly "random" alpha-numeric string.
         *
         * @param  int     $length
         * @return string
         */
        public function random2($length = 16)
        {
            if (function_exists('openssl_random_pseudo_bytes')) {
                $bytes = openssl_random_pseudo_bytes($length * 2);

                if ($bytes === false) {
                    throw new Exception('Unable to generate random string.');
                }

                return substr(str_replace(array('/', '+', '='), '', base64_encode($bytes)), 0, $length);
            }

            return $this->quickRandom($length);
        }

        /**
         * Generate a "random" alpha-numeric string.
         *
         * Should not be considered sufficient for cryptography, etc.
         *
         * @param  int     $length
         * @return string
         */
        public function quickRandom($length = 16)
        {
            $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

            return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
        }

        /**
         * Convert a string to snake case.
         *
         * @param  string  $value
         * @param  string  $delimiter
         * @return string
         */
        public function snake($value, $delimiter = '_')
        {
            $replace = '$1' . $delimiter . '$2';

            return ctype_lower($value) ? $value : $this->lower(preg_replace('/(.)([A-Z])/', $replace, $value));
        }

        /**
         * Convert a value to studly caps case.
         *
         * @param  string  $value
         * @return string
         */
        public function studly($value)
        {
            $value = ucwords(str_replace(array('-', '_'), ' ', $value));

            return str_replace(' ', '', $value);
        }

        public function removeXss($str)
        {
            $attr = array('style','on[a-z]+');
            $elem = array('script','iframe','embed','object');
            $str = preg_replace('#<!--.*?-->?#', '', $str);
            $str = preg_replace('#<!--#', '', $str);
            $str = preg_replace('#(<[a-z]+(\s+[a-z][a-z\-]+\s*=\s*(\'[^\']*\'|"[^"]*"|[^\'">][^\s>]*))*)\s+href\s*=\s*(\'javascript:[^\']*\'|"javascript:[^"]*"|javascript:[^\s>]*)((\s+[a-z][a-z\-]*\s*=\s*(\'[^\']*\'|"[^"]*"|[^\'">][^\s>]*))*\s*>)#is', '$1$5', $str);

            foreach($attr as $a) {
                $regex = '(<[a-z]+(\s+[a-z][a-z\-]+\s*=\s*(\'[^\']*\'|"[^"]*"|[^\'">][^\s>]*))*)\s+' . $a . '\s*=\s*(\'[^\']*\'|"[^"]*"|[^\'">][^\s>]*)((\s+[a-z][a-z\-]*\s*=\s*(\'[^\']*\'|"[^"]*"|[^\'">][^\s>]*))*\s*>)';
                $str = preg_replace('#' . $regex . '#is', '$1$5', $str);
            }

            foreach($elem as $e) {
                $regex = '<' . $e . '(\s+[a-z][a-z\-]*\s*=\s*(\'[^\']*\'|"[^"]*"|[^\'">][^\s>]*))*\s*>.*?<\/' . $e . '\s*>';
                $str = preg_replace('#' . $regex . '#is', '', $str);
            }

            return $str;
        }

        public function html($string, $keepHtml = true)
        {
            if(true === $keepHtml) {
                return stripslashes(
                    implode(
                        '',
                        preg_replace(
                            '/^([^<].+[^>])$/e',
                            "htmlentities('\\1', ENT_COMPAT, 'utf-8')",
                            preg_split(
                                '/(<.+?>)/',
                                $string,
                                -1,
                                PREG_SPLIT_DELIM_CAPTURE
                           )
                       )
                   )
               );
            } else {
                return htmlentities($string, ENT_COMPAT, 'utf-8');
            }
        }

        public function isSerialized($data, $strict = true)
        {
            if (!is_string($data)) return false;

            $data = trim($data);

            if ('N;' == $data) return true;

            $length = strlen($data);

            if ($length < 4) return false;

            if (':' !== $data[1]) return false;

            if ($strict) {
                $lastc = $data[ $length - 1 ];
                if (';' !== $lastc && '}' !== $lastc) return false;
            } else {
                $semicolon = strpos($data, ';');
                $brace     = strpos($data, '}');
                // Either ; or } must exist.
                if (false === $semicolon && false === $brace) return false;
                // But neither must be in the first X characters.
                if (false !== $semicolon && $semicolon < 3) return false;

                if (false !== $brace && $brace < 4) return false;
            }

            $token = $data[0];

            switch ($token) {
                case 's' :
                    if ($strict) {
                        if ('"' !== $data[$length - 2]) return false;
                    } elseif (false === strpos($data, '"')) {
                        return false;
                    }
                    // or else fall through
                case 'a' :
                case 'O' :
                    return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
                case 'b' :
                case 'i' :
                case 'd' :
                    $end = $strict ? '$' : '';

                    return (bool) preg_match("/^{$token}:[0-9.E-]+;$end/", $data);
            }
            return false;
        }

        public function extractUrls($content)
        {
            preg_match_all(
                "#((?:[\w-]+://?|[\w\d]+[.])[^\s()<>]+[.](?:\([\w\d]+\)|(?:[^`!()\[\]{};:'\".,<>?«»“”‘’\s]|(?:[:]\d+)?/?)+))#",
                $content,
                $links
            );

            $links = array_unique(array_map('html_entity_decode', current($links)));

            return array_values($links);
        }

        public function isMd5($str)
        {
            return (bool) preg_match('/^[0-9a-f]{32}$/i', $str);
        }

        public function isSha1($str)
        {
            return (bool) preg_match('/^[0-9a-f]{40}$/i', $str);
        }

        /**
         * Determine if a given string contains a given substring.
         *
         * @param  string  $haystack
         * @param  string|array  $needles
         * @return bool
         */
        public function contains($haystack, $needles)
        {
            foreach ((array) $needles as $needle)  {
                if ($needle != '' && strpos($haystack, $needle) !== false) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Determine if a given string ends with a given substring.
         *
         * @param  string  $haystack
         * @param  string|array  $needles
         * @return bool
         */
        public function endsWith($haystack, $needles)
        {
            foreach ((array) $needles as $needle) {
                if ((string) $needle === substr($haystack, -strlen($needle))) return true;
            }

            return false;
        }

        /**
         * Determine if a given string starts with a given substring.
         *
         * @param  string  $haystack
         * @param  string|array  $needles
         * @return bool
         */
        public function startsWith($haystack, $needles)
        {
            foreach ((array) $needles as $needle) {
                if ($needle != '' && strpos($haystack, $needle) === 0) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Cap a string with a single instance of a given value.
         *
         * @param  string  $value
         * @param  string  $cap
         * @return string
         */
        public function finish($value, $cap)
        {
            $quoted = preg_quote($cap, '/');

            return preg_replace('/(?:' . $quoted . ')+$/', '', $value) . $cap;
        }

        public function parseCallback($callback, $default, $separator = '@')
        {
            return $this->contains($callback, $separator) ? explode($separator, $callback, 2) : [$callback, $default];
        }
    }
