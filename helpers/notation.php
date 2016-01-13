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

    class NotationLib
    {
          public function add($objectFrom, $objectTo, $note)
          {
               if (!is_object($objectFrom)) {
                    throw new Exception('the first argument must be an object');
               }

               if (!is_object($objectTo)) {
                    throw new Exception('the second argument must be an object');
               }

               if (!is_numeric($note)) {
                    throw new Exception('the third argument must be a number');
               }

               $from_table    = (string) $objectFrom->db()->table;
               $from_id       = (int) $objectFrom->id;

               $to_table      = (string) $objectTo->db()->table;
               $to_id         = (int) $objectTo->id;

               Model::Notation()->create([
                    'from_table'   => $from_table,
                    'from_id'      => $from_id,
                    'to_table'     => $to_table,
                    'to_id'        => $to_id,
                    'note'         => (double) $note
               ])->save();

               return true;
          }

          public function getAverage($objectTo)
          {
               if (!is_object($objectTo)) {
                    throw new Exception('the first argument must be an object');
               }

               $to_table = (string) $objectTo->db()->table;
               $to_id    = (int) $objectTo->id;

               return Model::Notation()
               ->where(['to_table', '=', $to_table])
               ->where(['to_id', '=', $to_id])
               ->avg('note');
          }
    }
