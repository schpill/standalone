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

    class DueLib
    {
        private $action, $task, $age, $closure = false;

        public function schedule($task, $action = null)
        {
            $this->task     = $task;
            $this->action   = $action;

            if (is_null($action)) {
                $this->action   = sha1($this->task);
                $this->closure  = true;
            }

            return $this;
        }

        public function minutely()
        {
            $this->age = strtotime('+1 minute');

            return $this->exec('minutely');
        }

        public function hourly()
        {
            $this->age = strtotime('+1 hour');

            return $this->exec('hourly');
        }

        public function daily()
        {
            $this->age = strtotime('+1 day');

            return $this->exec('daily');
        }

        public function weekly()
        {
            $this->age = strtotime('+1 week');

            return $this->exec('weekly');
        }

        public function monthly()
        {
            $this->age = strtotime('+1 month');

            return $this->exec('monthly');
        }

        public function annually()
        {
            $this->age = strtotime('+1 year');

            return $this->exec('annualy');
        }

        public function yearly()
        {
            $this->age = strtotime('+1 year');

            return $this->exec('annualy');
        }

        public function weeklyAt($h)
        {
            return $this->at($h, '+1 week');
        }

        public function monthlyAt($h)
        {
            return $this->at($h, '+1 week');
        }

        public function yearlyAt($h)
        {
            return $this->at($h, '+1 year');
        }

        public function annuallyAt($h)
        {
            return $this->at($h, '+1 year');
        }

        public function cron($expression = '* * * * *')
        {
            $next = Crontab::factory($expression)->getNextRunDate()->getTimestamp();

            $key    = sha1($this->task . $this->action . $expression);
            $file   = '/home/php/storage/schedule/' . $key;

            if (file_exists($file)) {
                $aged = filemtime($file);

                if ($aged <= time()) {
                    touch($file, $next);

                    if (!$this->closure) {
                        lib('utils')->backgroundTask('thin --task=' . $this->task . ' --action=' . $this->action);
                    } else {
                        if (is_callable($this->task)) {
                            async($this->task);
                        } else {
                            lib('utils')->backgroundTask($this->task);
                        }
                    }
                }
            } else {
                $prev = Crontab::factory($expression)->getPreviousRunDate()->getTimestamp();

                if ($prev <= time()) {
                    touch($file, $next);

                    if (!$this->closure) {
                        lib('utils')->backgroundTask('thin --task=' . $this->task . ' --action=' . $this->action);
                    } else {
                        if (is_callable($this->task)) {
                            async($this->task);
                        } else {
                            lib('utils')->backgroundTask($this->task);
                        }
                    }
                }
            }
        }

        public function at($h, $w = 'tomorrow')
        {
            $key    = sha1($this->task . $this->action . $h . $w);
            $file   = '/home/php/storage/schedule/' . $key;

            if (file_exists($file)) {
                $aged = filemtime($file);

                if ($aged <= time()) {
                    touch($file, strtotime($w . ' ' . $h));

                    if (!$this->closure) {
                        lib('utils')->backgroundTask('thin --task=' . $this->task . ' --action=' . $this->action);
                    } else {
                        if (is_callable($this->task)) {
                            async($this->task);
                        } else {
                            lib('utils')->backgroundTask($this->task);
                        }
                    }
                }
            } else {
                $when = strtotime($h);

                if ($when <= time()) {
                    touch($file, strtotime($w . ' ' . $h));

                    if (!$this->closure) {
                        lib('utils')->backgroundTask('thin --task=' . $this->task . ' --action=' . $this->action);
                    } else {
                        if (is_callable($this->task)) {
                            async($this->task);
                        } else {
                            lib('utils')->backgroundTask($this->task);
                        }
                    }
                }
            }
        }

        public function exec($h)
        {
            $key = sha1($this->task . $this->action . $h);
            $file = '/home/php/storage/schedule/' . $key;

            if (file_exists($file)) {
                $aged = filemtime($file);

                if ($aged <= time()) {
                    touch($file, $this->age);

                    if (!$this->closure) {
                        lib('utils')->backgroundTask('thin --task=' . $this->task . ' --action=' . $this->action);
                    } else {
                        if (is_callable($this->task)) {
                            async($this->task);
                        } else {
                            lib('utils')->backgroundTask($this->task);
                        }
                    }
                }
            } else {
                touch($file, $this->age);

                if (!$this->closure) {
                    lib('utils')->backgroundTask('thin --task=' . $this->task . ' --action=' . $this->action);
                } else {
                    if (is_callable($this->task)) {
                        async($this->task);
                    } else {
                        lib('utils')->backgroundTask($this->task);
                    }
                }
            }
        }
    }
