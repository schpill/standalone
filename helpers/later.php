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

    class LaterLib
    {
        public function set($name, $closure, $args = [], $when = 0)
        {
            $closure_id = lib('closure')->store($name, $closure)->id;
            $db         = Model::Latertask();

            return $db->firstOrCreate([
                'closure_id'    => (int) $closure_id,
                'when'          => (int) $when,
                'args'          => (array) $args
            ]);
        }

        public function listen()
        {
            set_time_limit(false);

            $dbTask     = Model::Latertask();
            $dbInstance = Model::Laterinstance();

            $tasks      = $dbTask->where(['when', '<', time()])->cursor();

            if ($tasks->count() > 0) {
                foreach ($tasks as $task) {
                    $check = $dbInstance->where(['task_id', '=', (int) $task['id']])->cursor()->count();

                    $callback_id = isAke($task, 'callback_id', null);

                    if ($check == 0) {
                        $instance = $dbInstance->create(['task_id' => (int) $task['id'], 'start' => time()])->save();

                        $res = lib('closure')->fireStore(
                            (int) $task['closure_id'],
                            (array) $task['args']
                        );

                        if ($callback_id) {
                            $cb = $dbTask->find((int) $callback_id);
                            $t = $cb->toArray();

                            $args = array_merge([$res], $t['args']);

                            $res = lib('closure')->fireStore(
                                (int) $t['closure_id'],
                                (array) $args
                            );

                            $cb->delete();
                        }

                        $dt = $dbTask->find((int) $task['id']);

                        if ($dt) {
                            $dt->delete();
                        }

                        $instance->delete();

                        Model::Laterhistory()->create([
                            'task' => (array) $task,
                            'execution_time' => time()
                        ])->save();
                    }
                }
            }

            return true;
        }

        public function async($name, $closure, $callback, $args = [], $callbackArgs = [])
        {
            $task = $this->set($name, $closure, $args);
            $callbackTask = $this->set($name . '_cb', $callback, $callbackArgs, strtotime('+1 YEAR'));

            $task->setCallbackId($callbackTask->id)->save();

            $this->background();
        }

        public function background()
        {
            $file = realpath(APPLICATION_PATH . DS . '..' . DS . 'public' . DS . 'scripts' . DS . 'later.php');

            if (File::exists($file)) {
                $cmd = 'php ' . $file;
                lib('utils')->backgroundTask($cmd);
            }
        }
    }
