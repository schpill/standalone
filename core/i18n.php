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

    use Locale;
    use Collator;

    class I18nCore
    {
        private $locale, $db, $mustTranslate = false;

        public function __construct()
        {
            $this->locale   = session('web')->getLanguage();
            $this->db       = System::Language();

            $defaultLng     = Config::get(
                'application.language',
                DEFAULT_LANGUAGE
            );

            $this->mustTranslate = $defaultLng != $this->locale;
        }

        public function i18n($default, $key = null, $args = [])
        {
            $key = is_null($key) ? sha1($default . serialize($args)) : $key;

            $locale = session('web')->getLanguage();

            if (!$locale) {
                $locale = lng();
                session('web')->setLanguage($locale);
            }

            return $this->get($key, $default, $args);
        }

        public function get($key, $default, $args = [])
        {
            $translation = $default;

            $locale = session('web')->getLanguage();

            if (!$locale) {
                $locale = lng();
                session('web')->setLanguage($locale);
            }

            if (fnmatch('*_*', $locale)) {
                list($locale, $d) = explode('_', $locale, 2);
            }

            $locale = strtolower($locale);

            if (false !== $this->mustTranslate) {
                $row = $this->db
                ->where(['key', '=', $key])
                ->first();

                if ($row) {
                    if (!isset($row[$locale])) {
                        $translate  = $this->db->find((int) $row['id']);
                        $setter     = setter($locale);
                        $translate->$setter('')->save();
                    }

                    $translation = isAke($row, $locale, $default);

                    if (!strlen($translation)) {
                        $translation = $default;
                    }
                } else {
                    $default    = preg_replace('~[\r\n]+~', '', $default);
                    $translate  = $this->db->firstOrCreate(['key' => $key]);
                    $setter     = setter(DEFAULT_LANGUAGE);
                    $translate->$setter($default);
                    $setter     = setter($locale);
                    $translate->$setter('')->save();
                }
            }

            if (!empty($args)) {
                foreach ($args as $k => $v) {
                    $translation = str_replace('%' . $k . '%', $v, $translation);
                }
            }

            return $translation;
        }

        public function exists($key)
        {
            if (false === $this->mustTranslate) {
                return true;
            }

            $row = $this->db
            ->where(['key', '=', $key])
            ->first(true);

            return !empty($row);
        }

        public function locale($context = 'web')
        {
            $language       = session($context)->getLanguage();
            $isCli          = false;
            $fromBrowser    = isAke($_SERVER, 'HTTP_ACCEPT_LANGUAGE', false);

            if (false === $fromBrowser) {
                $isCli = true;
            }

            if ($isCli) {
                return defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'en';
            }

            $var = defined('LANGUAGE_VAR') ? LANGUAGE_VAR : 'lng';

            if (is_null($language)) {
                $language = isAke(
                    $_REQUEST,
                    $var,
                    Locale::acceptFromHttp($_SERVER["HTTP_ACCEPT_LANGUAGE"])
                );

                session($context)->setLanguage($language);
            }

            if (fnmatch('*_*', $language)) {
                list($language, $d) = explode('_', $language, 2);
                session($context)->setLanguage($language);
            }

            return $language;
        }

        public function set($lng, $context = 'web')
        {
            session($context)->setLanguage($lng);
        }

        public function parse($html)
        {
            require_once __DIR__ . DS . 'dom.php';

            $str = str_get_html($html);

            $segLangs = $str->find('lang');

            foreach ($segLangs as $segLang) {
                $default    = $segLang->innertext;
                $key        = $segLang->id;
                $args       = $segLang->args;

                if (!empty($args)) {
                    $argsTo = eval('return ' . $args . ';');

                    if (!empty($key)) {
                        $replace = "<lang id=\"$key\" args=\"$args\">$default</lang>";
                    } else {
                        $replace = "<lang args=\"$args\">$default</lang>";
                    }
                } else {
                    $args = '[]';

                    if (!empty($key)) {
                        $replace = "<lang id=\"$key\">$default</lang>";
                    } else {
                        $replace = "<lang>$default</lang>";
                    }
                }

                if (!empty($key)) {
                    $by = '<?php __(\'' . $default . '\', \'' . $key . '\', ' . $args . '); ?>';
                } else {
                    $by = '<?php __(\'' . $default . '\', null, ' . $args . '); ?>';
                }

                $html = str_replace($replace, $by, $html);
            }

            return $html;
        }

        public function check($id, $html)
        {
            require_once __DIR__ . DS . 'dom.php';

            $str = str_get_html($html);

            $segLangs = $str->find('lang');

            foreach ($segLangs as $segLang) {
                $default    = $segLang->innertext;
                $args       = $segLang->args;

                if (!empty($args)) {
                    $controller = Now::get('instance.controller');

                    $replace = "<lang args=\"$args\">$default</lang>";

                    $file = path('cache') . DS . sha1(serialize($args)) . '.display';

                    File::put($file, '<?php namespace Thin; ?>' . $args);

                    ob_start();

                    include $file;

                    $args = ob_get_contents();

                    ob_end_clean();

                    File::delete($file);
                } else {
                    $args = '[]';
                    $replace = "<lang>$default</lang>";
                }

                $by = '<?php __(\'' . $default . '\', \'' . $id . '.' . Inflector::urlize($default, '-') . '\', ' . $args . '); ?>';

                $html = str_replace($replace, $by, $html);
            }

            return $html;
        }
    }
