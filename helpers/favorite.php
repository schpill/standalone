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

    use Illuminate\View\Compilers\BladeCompiler;
    use Illuminate\Filesystem\Filesystem;

    class FavoriteLib
    {
        public function add($table, $id, $account_id)
        {
            return Model::Favorite()->create([
                'table'          => $table,
                'table_id'      => (int) $id,
                'account_id'    => (int) $account_id,
            ])->save();
        }

        public function del($table, $id, $account_id)
        {
            return $this->delete($table, $id, $account_id);
        }

        public function delete($table, $id, $account_id)
        {
            $row = Model::Favorite()
            ->where(['table', '=', $table])
            ->where(['table_id', '=', (int) $id])
            ->where(['account_id', '=', (int) $account_id])
            ->first(true);

            if ($row) {
                $row->delete();

                return true;
            }

            return false;
        }

        public function has($table, $id, $account_id)
        {
            $count = Model::Favorite()
            ->where(['table', '=', $table])
            ->where(['table_id', '=', (int) $id])
            ->where(['account_id', '=', (int) $account_id])
            ->cursor()
            ->count();

            return $count > 0 ? true : false;
        }
    }
