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

    class IcalLib
    {
        private $name;
        private $timezoneICal = 'Europe/Paris';
        private $dateStart;
        private $summary;
        private $dateEnd;
        private $filename;
        private $address;
        private $description;
        private $alarm        = false;
        private $repeat       = false;

        public function getName()
        {
            return $this->name;
        }

        public function getTimezoneICal()
        {
            return $this->timezoneICal;
        }

        public function getDateStart()
        {
            return $this->dateStart;
        }

        public function getSummary()
        {
            return $this->summary;
        }

        public function getDateEnd()
        {
            return $this->dateEnd;
        }

        public function getFilename()
        {
            return $this->filename;
        }

        public function getAddress()
        {
            return $this->address;
        }

        public function getDescription()
        {
            return $this->description;
        }

        public function getAlarm()
        {
            return $this->alarm;
        }

        public function getRepeat()
        {
            return $this->repeat;
        }

        public function setName($name)
        {
            $this->name = $name;

            return $this;
        }

        public function setTimezoneICal($timezoneICal)
        {
            $this->timezoneICal = $timezoneICal;

            return $this;
        }

        public function setDateStart($dateStart)
        {
            $this->dateStart = $this->_dateToCal($this->_human_to_unix($dateStart));

            return $this;
        }

        public function setSummary($summary)
        {
            $this->summary = $summary;

            return $this;
        }

        public function setDateEnd($dateEnd)
        {
            $this->dateEnd = $this->_dateToCal($this->_human_to_unix($dateEnd));

            return $this;
        }

        public function setFilename($filename)
        {
            $this->filename = $filename;

            return $this;
        }

        public function setAddress($address)
        {
            $this->address = $address;

            return $this;
        }

        public function setDescription($description)
        {
            $this->description = $description;

            return $this;
        }

        public function setAlarm($alarm)
        {
            if (is_int($alarm)) {
                $this->alarm = $alarm;

                return $this;
            } else {
                throw new Exception(__CLASS__ . " : It's not an integer");
            }
        }

        public function setRepeat($repeat)
        {
            $this->repeat = $repeat;

            return $this;
        }

        /**
         * @name getIcal()
         * @access public
         * @return string $iCal
         */
        public function getIcal()
        {
            $iCal = "BEGIN:VCALENDAR" . "\r\n";
            $iCal .= 'VERSION:2.0' . "\r\n";
            $iCal .= "PRODID:" . $this->getName() . "\r\n";
            $iCal .= "CALSCALE:GREGORIAN " . "\r\n";
            $iCal .= "BEGIN:VEVENT" . "\r\n";
            $iCal .= "DTSTART:" . $this->getDateStart() . "\r\n";
            $iCal .= "DTEND:" . $this->getDateEnd() . "\r\n";
            $iCal .= "SUMMARY:" . $this->_escapeString($this->getSummary()) . "\r\n";
            $iCal .= 'UID:' . uniqid() . "\r\n";
            $iCal .= 'LOCATION: ' . $this->_escapeString($this->getAddress()) . "\r\n";
            $iCal .= 'DESCRIPTION:' . $this->_escapeString($this->getDescription()) . "\r\n";

            if ($this->getAlarm()) {
                $iCal .= 'BEGIN:VALARM' . "\r\n";
                $iCal .= 'ACTION:DISPLAY' . "\r\n";
                $iCal .= 'DESCRIPTION:Reminder' . "\r\n";
                $iCal .= 'TRIGGER:-PT' . $this->getAlarm() . 'M' . "\r\n";

                if ($this->getRepeat()) {
                    $iCal .= 'REPEAT:' . $this->getRepeat() . "\r\n";
                }

                $iCal .= "END:VALARM" . "\r\n";
            }

            $iCal .= 'END:VEVENT' . "\r\n";
            $iCal .= 'END:VCALENDAR' . "\r\n";

            return $iCal;
        }

        /**
         * @name _dateToCal()
         * @access private
         * @param string $timestamp
         * @return string
         */
        private function _dateToCal($timestamp)
        {
            return date('Ymd\THis\Z', $timestamp);
        }

        /**
         * @name _escapeString()
         * @abstract Escapes a string of characters
         * @param string $string
         * @return string
         */
        private function _escapeString($string)
        {
            return preg_replace('/([\,;])/', '\\\$1', $string);
        }

        /**
         * @name $setHeader();
         */
        public function setHeader()
        {
            header('Content-type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $this->getFilename() . '.ics');
        }

        /**
         * @name _human_to_unix()
         * @desciption this function is from codeigniter frameWork
         * @param string $datestr
         * @return boolean
         */
        private function _human_to_unix($datestr)
        {
            $datestr = preg_replace("/\040+/", ' ', trim($datestr));

            if (!preg_match('/^[0-9]{2,4}\-[0-9]{1,2}\-[0-9]{1,2}\s[0-9]{1,2}:[0-9]{1,2}(?::[0-9]{1,2})?(?:\s[AP]M)?$/i', $datestr)) {
                throw new \Exception(__CLASS__ . " : bad" . $datestr);
            }

            $split = explode(' ', $datestr);

            $ex = explode("-", $split['0']);

            $year  = (strlen($ex['0']) == 2) ? '20' . $ex['0'] : $ex['0'];
            $month = (strlen($ex['1']) == 1) ? '0' . $ex['1'] : $ex['1'];
            $day   = (strlen($ex['2']) == 1) ? '0' . $ex['2'] : $ex['2'];

            $ex = explode(":", $split['1']);

            $hour = (strlen($ex['0']) == 1) ? '0' . $ex['0'] : $ex['0'];
            $min  = (strlen($ex['1']) == 1) ? '0' . $ex['1'] : $ex['1'];

            if (isset($ex['2']) && preg_match('/[0-9]{1,2}/', $ex['2'])) {
                $sec = (strlen($ex['2']) == 1) ? '0' . $ex['2'] : $ex['2'];
            } else {
                // Unless specified, seconds get set to zero.
                $sec = '00';
            }

            if (isset($split['2'])) {
                $ampm = strtolower($split['2']);

                if (substr($ampm, 0, 1) == 'p' AND $hour < 12) $hour = $hour + 12;

                if (substr($ampm, 0, 1) == 'a' AND $hour == 12) $hour = '00';

                if (strlen($hour) == 1) $hour = '0' . $hour;
            }

            return mktime($hour, $min, $sec, $month, $day, $year);
        }
    }
