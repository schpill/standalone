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

    class TemplateLib
    {
        public function compile($content, $context = null)
        {
            $html = str_replace(
                [
                    '$this'
                ],
                [
                    '$context'
                ],
                $content
            );

            ob_start();

            try {
                eval('?>' . $html);
            } catch (\Exception $e) {
                ob_end_clean();
                throw $e;
            }

            $str = ob_get_contents();

            ob_end_clean();

            return $str;
        }
    }
