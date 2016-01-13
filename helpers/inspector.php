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

	class InspectorLib
	{
		/**
		 * Returns an array of all traits used by a class.
		 *
		 * @access  public
		 * @param   string|object  $class     Class name or class instance
		 * @param   boolean        $autoload  Autoload
		 * @return  array
		 */

		public static function getTraits($class, $autoload = true)
		{
			// Fetch all traits used by a class and its parents

			$traits = [];

			do {
				$traits += class_uses($class, $autoload);
			} while ($class = get_parent_class($class));

			// Find all traits used by the traits

			$search = $traits;

			$searched = [];

			while(!empty($search)) {
				$trait = array_pop($search);

				if(isset($searched[$trait])) {
					continue;
				}

				$traits += $search += class_uses($trait, $autoload);

				$searched[$trait] = $trait;
			}

			// Return complete list of traits used by the class

			return $traits;
		}
	}
