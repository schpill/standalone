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

    use Cron\CronExpression;

    class CronLib
    {
        public function run($name, $when, $event, $args = [])
        {
            Timer::start();
            Cli::show("Start of execution", 'SUCCESS');

            $db     = rdb('cron', 'task');
            $dbCron = $db->firstOrCreate(['name' => $name]);
            $nextDb = $dbCron->next;

            $cron   = CronExpression::factory($when);
            $next   = $cron->getNextRunDate()->format('Y-m-d-H-i-s');

            list($y, $m, $d, $h, $i, $s) = explode('-', $next, 6);

            $timestamp = mktime($h, $i, $s, $m, $d, $y);

            if ($nextDb) {
                if ($nextDb < $timestamp) {
                    Cli::show("Execution $name", 'COMMENT');

                    call_user_func_array($event, $args);

                    $dbCron->setNext($timestamp)->save();
                }
            } else {
                $dbCron->setNext($timestamp)->save();
            }

            Cli::show('Elapsed time: ' . Timer::get() . ' s.', 'INFO');
            Cli::show("End of execution", 'SUCCESS');
        }

        public function refactorNodes()
        {
            /* each 2 minutes */
            if (date('i') % 2 == 0) {
                Model::Core()->refactor('nodes');
            }
        }
    }
