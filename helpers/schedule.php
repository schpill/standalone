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

    use ReflectionClass;

    class ScheduleLib
    {
        public function run()
        {
            if (fnmatch('*cli*', php_sapi_name())) {
                $dir = Config::get('dir.schedules', APPLICATION_PATH . DS . 'schedules');

                if (is_dir($dir)) {
                    Timer::start();
                    Cli::show("Start of execution", 'COMMENT');
                    $files = glob($dir . DS . '*.php');

                    foreach ($files as $file) {
                        require_once $file;

                        $object     = str_replace('.php', '', Arrays::last(explode(DS, $file)));
                        $class      = 'Thin\\' . ucfirst(Inflector::camelize($object . '_schedule'));
                        $instance   = lib('app')->make($class);

                        $methods    = get_class_methods($instance);

                        Cli::show("Start schedule '$object'", 'COMMENT');

                        foreach ($methods as $method) {
                            $when = $this->getWhen($instance, $method);

                            $isDue = $this->isDue($object, $method, $when);

                            if (true === $isDue) {
                                Cli::show("Execution of $object->$method", 'INFO');
                                $instance->$method();
                            } else {
                                Cli::show("No need to execute $object->$method", 'QUESTION');
                            }
                        }
                    }

                    Cli::show("Time of execution [" . Timer::get() . " s.]", 'SUCCESS');
                    Cli::show("end of execution", 'COMMENT');
                }
            }
        }

        private function isDue($class, $method, $when)
        {
            $next = Crontab::factory($when)->getNextRunDate()->getTimestamp();

            $row = Raw::ScheduleTask()
            ->where(['class', '=', $class])
            ->where(['method', '=', $method])
            ->first(true);

            if (!$row) {
                $row = Raw::ScheduleTask()
                ->create([
                    'class'     => $class,
                    'method'    => $method,
                    'next'      => (int) $next
                ])->save();
            } else {
                if ($row->next <= time()) {
                    $row->setNext($next)->save();

                    return true;
                }
            }

            return false;
        }

        public function getWhen($instance, $method)
        {
            $ref = new ReflectionClass($instance);

            $refMethod = $ref->getMethod($method);

            $comment = $refMethod->getDocComment();

            $when = Utils::cut('@when=', "\n", $comment);

            return $when ?: '* * * * *';
        }

        public function background()
        {
            $file = realpath(APPLICATION_PATH . DS . '..' . DS . 'public' . DS . 'scripts' . DS . 'schedule.php');

            if (File::exists($file)) {
                $cmd = 'php ' . $file;
                lib('utils')->backgroundTask($cmd);
            }
        }
    }
