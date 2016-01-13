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

    use SplFixedArray;

    class TabLib
    {
        private $key;

        public function __construct(array $array)
        {
            $this->key = sha1(serialize($array));

            if (Arrays::isAssoc($array)) {
                $tab = new SplFixedArray(1);
                $tab[0] = $array;
                $this->key .= 'AAA';
            } else {
                $tab = SplFixedArray::fromArray($array);
            }

            lib('resource')->set($this->key, $tab);
        }

        public function get($default = [])
        {
            $tab = lib('resource')->get($this->key, $default);

            if (!empty($tab)) {
                if (fnmatch('*AAA', $this->key)) {
                    return $tab[0];
                }
            }

            return $tab;
        }
    }
