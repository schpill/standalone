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

    use Closure;
    use ReflectionFunction;
    use SplFileObject;

    class ExeLib
    {
        public function save($name, Closure $closure)
        {
            $code = $this->extract();
            $key = sha1($code);

            return Raw::Closure()->create([
                'code' => $code,
                'key' => $key
            ])->save();
        }

        public function fire($id, $args = [])
        {
            $row = Raw::Closure()->findOrFail((int) $id);

            $closure = eval('return ' . $row->code . ';');

            if (is_callable($closure)) {
                return call_user_func_array($closure, $args);
            }

            throw new Exception('This closure does not exist.');
        }

        public function later($name, Closure $closure, $when = 0, $args = [])
        {
            $row = $this->save($name, $closure);

            return Raw::Later()->create([
                'closure_id'    => (int) $row->id,
                'arguments'     => serialize($args),
                'when'          => (int) $when
            ])->save();
        }

        public function listen()
        {
            set_time_limit(0);

            $rows = Raw::Later()->where(['when', '<', time()])->cursor();

            foreach ($rows as $row) {
                $closure = eval('return ' . $row['code'] . ';');
                $args = unserialize($row['arguments']);

                call_user_func_array($closure, $args);

                Raw::Later()->find((int) $row['id'])->delete();
            }
        }

        public function extract(Closure $callback)
        {
            $ref  = new ReflectionFunction($callback);
            $file = new SplFileObject($ref->getFileName());
            $file->seek($ref->getStartLine() - 1);

            $content = '';

            while ($file->key() < $ref->getEndLine()) {
                $content .= $file->current();
                $file->next();
            }

            if (fnmatch('*(function*', $content)) {
                list($dummy, $code) = explode('(function', $content, 2);
                $code   = 'function' . $code;
                $tab    = explode('}', $code);
                $last   = end($tab);
                $code   = str_replace('}' . $last, '}', $code);
            }

            return $code;
        }

        public function background()
        {
            $file = realpath(APPLICATION_PATH . DS . '..' . DS . 'public' . DS . 'scripts' . DS . 'exe.php');

            if (File::exists($file)) {
                $cmd = 'php ' . $file;

                lib('utils')->backgroundTask($cmd);
            }
        }
    }
