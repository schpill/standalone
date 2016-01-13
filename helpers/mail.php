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

    class MailLib
    {
        public function send($to, $from, $subject, $html = null, $txt = null, $toName = null, $fromName = null, $files = [], $priority = 3)
        {
            $toName     = is_null($toName)      ? $to   : $toName;
            $fromName   = is_null($fromName)    ? $from : $fromName;

            $mail = [];

            $mail['to']         = $to;
            $mail['to_name']    = $toName;
            $mail['from']       = $from;
            $mail['from_name']  = $fromName;
            $mail['subject']    = $subject;
            $mail['priority']   = $priority;

            if (!is_null($txt)) {
                $mail['text'] = $txt;
            }

            if (!is_null($html)) {
                $mail['html'] = $html;
            }

            if (!empty($files)) {
                $mail['files'] = $files;
            }

            return zlmail($mail);
        }
    }
