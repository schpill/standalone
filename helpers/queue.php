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

    use Dbredis\Db;

    class QueueLib
    {
        public function push($method, array $args = [], $when = 0, $priority = 0)
        {

            $db = Db::instance('queue', 'task');

            return $db->firstOrCreate([
                'method'    => $method,
                'args'      => $args,
                'when'      => $when,
                'priority'  => $priority
            ]);
        }

        public function pushlib($lib, $method, array $args = [], $when = 0, $priority = 0)
        {
            return $this->push('flib', [$lib, $method, $args], $when, $priority);
        }

        public function later($timestamp, $method, array $args = [], $priority = 0)
        {
            return $this->push($method, $args, $timestamp, $priority);
        }

        public function count()
        {
            return Db::instance('queue', 'task')->cursor()->count();
        }

        public function listen()
        {
            set_time_limit(false);

            $db = Db::instance('queue', 'task');

            $tasks = $db->inCache(false)
            ->where(['when', '<', time()])
            ->order('priority', 'DESC')
            ->cursor();

            if ($tasks->count() > 0) {
                foreach ($tasks as $task) {
                    $isMethod = fnmatch('*::*', $task['method']);

                    if (true === $isMethod) {
                        $this->runMethod($task);
                    } else {
                        $this->runFunction($task);
                    }
                }
            }
        }

        private function runFunction(array $task)
        {
            return $this->run($task['method'], $task);
        }

        private function runMethod(array $task)
        {
            list($_class, $method) = explode('::', $task['method'], 2);

            return $this->run([$_class, $method], $task);
        }

        private function run($what, array $task)
        {
            $dbTask      = Db::instance('queue', 'task');
            $dbInstance  = Db::instance('queue', 'instance');

            $check = $dbInstance
            ->where(['task_id', '=', $task['id']])
            ->cursor()
            ->count();

            if ($check == 0) {
                $instance = $dbInstance
                ->create(['task_id' => $task['id']])
                ->save();

                call_user_func_array($what, $task['args']);

                $dt = $dbTask->find($task['id']);

                if ($dt) {
                    $dt->delete();
                }

                $instance->delete();

                Db::instance('queue', 'history')
                ->create([
                    'task'              => $task,
                    'execution_time'    => time()
                ])->save();
            }
        }

        public function background()
        {
            $file = realpath(APPLICATION_PATH . DS . '..' . DS . 'public' . DS . 'scripts' . DS . 'queue.php');

            if (File::exists($file)) {
                $cmd = 'php ' . $file;

                lib('utils')->backgroundTask($cmd);
            }
        }
    }
