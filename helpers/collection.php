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
	use Countable;
	use ArrayAccess;
	use ArrayIterator;
	use CachingIterator;
	use JsonSerializable;
	use IteratorAggregate;
	use Illuminate\Contracts\Support\Jsonable;
	use Illuminate\Contracts\Support\Arrayable;

	class CollectionLib implements ArrayAccess, Arrayable, Countable, IteratorAggregate, Jsonable, JsonSerializable
	{
		use Macroable;

		/**
		 * The items contained in the collection.
		 *
		 * @var array
		 */
		protected $items = [];

		/**
		 * Create a new collection.
		 *
		 * @param  mixed  $items
		 * @return void
		 */
		public function __construct($items = [])
		{
			$items = is_null($items) ? [] : $this->getArrayableItems($items);

			$this->items = (array) $items;
		}

		/**
		 * Create a new collection instance if the value isn't one already.
		 *
		 * @param  mixed  $items
		 * @return static
		 */
		public static function make($items = null)
		{
			return new static($items);
		}

		/**
		 * Get all of the items in the collection.
		 *
		 * @return array
		 */
		public function all()
		{
			return $this->items;
		}

		/**
		 * Collapse the collection items into a single array.
		 *
		 * @return static
		 */
		public function collapse()
		{
			$results = [];

			foreach ($this->items as $values) {
				if ($values instanceof self) $values = $values->all();

				$results = array_merge($results, $values);
			}

			return $this->new($results);
		}

		/**
		 * Determine if an item exists in the collection.
		 *
		 * @param  mixed  $key
		 * @param  mixed  $value
		 * @return bool
		 */
		public function contains($key, $value = null)
		{
			if (func_num_args() == 2) {
				return $this->contains(function($k, $item) use ($key, $value) {
					return lib('array')->data_get($item, $key) == $value;
				});
			}

			if (is_callable($key)) {
				return ! is_null($this->first($key));
			}

			return in_array($key, $this->items);
		}

		/**
		 * Diff the collection with the given items.
		 *
		 * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Support\Arrayable|array  $items
		 * @return static
		 */
		public function diff($items)
		{
			return $this->new(array_diff($this->items, $this->getArrayableItems($items)));
		}

		/**
		 * Execute a callback over each item.
		 *
		 * @param  callable  $callback
		 * @return $this
		 */
		public function each(callable $callback)
		{
			array_map($callback, $this->items);

			return $this;
		}

		/**
		 * Fetch a nested element of the collection.
		 *
		 * @param  string  $key
		 * @return static
		 */
		public function fetch($key)
		{
			return $this->new(lib('array')->fetch($this->items, $key));
		}

		/**
		 * Run a filter over each of the items.
		 *
		 * @param  callable  $callback
		 * @return static
		 */
		public function filter(callable $callback)
		{
			return $this->new(array_filter($this->items, $callback));
		}

		/**
		 * Filter items by the given key value pair using loose comparison.
		 *
		 * @param  string  $key
		 * @param  mixed  $value
		 * @return static
		 */
		public function whereLoose($key, $value)
		{
			return $this->where($key, $value, false);
		}

		/**
		 * Get the first item from the collection.
		 *
		 * @param  callable   $callback
		 * @param  mixed      $default
		 * @return mixed|null
		 */
		public function first(callable $callback = null, $default = null)
		{
			if (is_null($callback)) {
				return !empty($this->items) ? reset($this->items) : $default;
			}

			return lib('array')->first($this->items, $callback, $default);
		}

		/**
		 * Get a flattened array of the items in the collection.
		 *
		 * @return static
		 */
		public function flatten()
		{
			return $this->new(lib('array')->flatten($this->items));
		}

		/**
		 * Flip the items in the collection.
		 *
		 * @return static
		 */
		public function flip()
		{
			return $this->new(array_flip($this->items));
		}

		/**
		 * Remove an item from the collection by key.
		 *
		 * @param  mixed  $key
		 * @return void
		 */
		public function forget($key)
		{
			$this->offsetUnset($key);
		}

		/**
		 * Get an item from the collection by key.
		 *
		 * @param  mixed  $key
		 * @param  mixed  $default
		 * @return mixed
		 */
		public function get($key, $default = null)
		{
			if ($this->offsetExists($key)) {
				return $this->items[$key];
			}

			return value($default);
		}

		/**
		 * Group an associative array by a field or using a callback.
		 *
		 * @param  callable|string  $groupBy
		 * @return static
		 */
		public function groupBy($groupBy)
		{
			if ( ! $this->useAsCallable($groupBy)) {
				return $this->groupBy($this->valueRetriever($groupBy));
			}

			$results = [];

			foreach ($this->items as $key => $value) {
				$groupKey = $groupBy($value, $key);

				if ( ! array_key_exists($groupKey, $results)) {
					$results[$groupKey] = $this->new([]);
				}

				$results[$groupKey]->push($value);
			}

			return $this->new($results);
		}

		/**
		 * [min description]
		 * @param  [type] $field [description]
		 * @return [type]        [description]
		 */
		public function min($field)
		{
			$row = $this->sortBy($field)->first();

			return isAke($row, $field, 0);
		}

		/**
		 * [max description]
		 * @param  [type] $field [description]
		 * @return [type]        [description]
		 */
		public function max($field)
		{
			$row = $this->sortByDesc($field)->first();

			return isAke($row, $field, 0);
		}

		/**
		 * Key an associative array by a field or using a callback.
		 *
		 * @param  callable|string  $keyBy
		 * @return static
		 */
		public function keyBy($keyBy)
		{
			if ( ! $this->useAsCallable($keyBy)) {
				return $this->keyBy($this->valueRetriever($keyBy));
			}

			$results = [];

			foreach ($this->items as $item) {
				$results[$keyBy($item)] = $item;
			}

			return $this->new($results);
		}

		/**
		 * [between description]
		 * @param  string  $field [description]
		 * @param  integer $min   [description]
		 * @param  integer $max   [description]
		 * @return [type]         [description]
		 */
		public function between($field = 'id', $min = 0, $max = 0)
		{
			if (!$this->useAsCallable($field)) {
				return $this->between($this->valueRetriever($field), $min, $max);
			}

			$results = [];

			foreach ($this->items as $key => $value) {
				$val = $field($value);

				if ($val >= $min && $val <= $max) {
					$results[] = $value;
				}
			}

			return $this->new($results);
		}

		/**
		 * Determine if an item exists in the collection by key.
		 *
		 * @param  mixed  $key
		 * @return bool
		 */
		public function has($key)
		{
			return $this->offsetExists($key);
		}

		/**
		 * Concatenate values of a given key as a string.
		 *
		 * @param  string  $value
		 * @param  string  $glue
		 * @return string
		 */
		public function implode($value, $glue = null)
		{
			$first = $this->first();

			if (is_array($first) || is_object($first)) {
				return implode($glue, $this->lists($value));
			}

			return implode($value, $this->items);
		}

		/**
		 * Intersect the collection with the given items.
		 *
	 	 * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Support\Arrayable|array  $items
		 * @return static
		 */
		public function intersect($items)
		{
			return $this->new(array_intersect($this->items, $this->getArrayableItems($items)));
		}

		/**
		 * Determine if the collection is empty or not.
		 *
		 * @return bool
		 */
		public function isEmpty()
		{
			return empty($this->items);
		}

		/**
		 * Determine if the given value is callable, but not a string.
		 *
		 * @param  mixed  $value
		 * @return bool
		 */
		protected function useAsCallable($value)
		{
			return !is_string($value) && is_callable($value);
		}

		/**
		 * Get the keys of the collection items.
		 *
		 * @return static
		 */
		public function keys()
		{
			return $this->new(array_keys($this->items));
		}

		/**
		* Get the last item from the collection.
		*
		* @return mixed|null
		*/
		public function last($default = null)
		{
			return !empty($this->items) ? end($this->items) : $default;
		}

		/**
		 * Get an array with the values of a given key.
		 *
		 * @param  string  $value
		 * @param  string  $key
		 * @return array
		 */
		public function lists($value, $key = null)
		{
			return lib('array')->pluck($this->items, $value, $key);
		}

		/**
		 * Run a map over each of the items.
		 *
		 * @param  callable  $callback
		 * @return static
		 */
		public function map(callable $callback)
		{
			return $this->new(array_map($callback, $this->items, array_keys($this->items)));
		}

		/**
		 * Merge the collection with the given items.
		 *
		 * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Support\Arrayable|array  $items
		 * @return static
		 */
		public function merge($items)
		{
			return $this->new(array_merge($this->items, $this->getArrayableItems($items)));
		}

		/**
		 * "Paginate" the collection by slicing it into a smaller collection.
		 *
		 * @param  int  $page
		 * @param  int  $perPage
		 * @return static
		 */
		public function forPage($page, $perPage)
		{
			return $this->new(array_slice($this->items, ($page - 1) * $perPage, $perPage));
		}

		/**
		 * Get and remove the last item from the collection.
		 *
		 * @return mixed|null
		 */
		public function pop()
		{
			return array_pop($this->items);
		}

		/**
		 * Push an item onto the beginning of the collection.
		 *
		 * @param  mixed  $value
		 * @return void
		 */
		public function prepend($value)
		{
			array_unshift($this->items, $value);
		}

		/**
		 * Push an item onto the end of the collection.
		 *
		 * @param  mixed  $value
		 * @return void
		 */
		public function push($value)
		{
			$this->offsetSet(null, $value);
		}

		/**
		 * Pulls an item from the collection.
		 *
		 * @param  mixed  $key
		 * @param  mixed  $default
		 * @return mixed
		 */
		public function pull($key, $default = null)
		{
			return lib('array')->pull($this->items, $key, $default);
		}

		/**
		 * Put an item in the collection by key.
		 *
		 * @param  mixed  $key
		 * @param  mixed  $value
		 * @return void
		 */
		public function put($key, $value)
		{
			$this->offsetSet($key, $value);
		}

		/**
		 * Get one or more items randomly from the collection.
		 *
		 * @param  int  $amount
		 * @return mixed
		 */
		public function random($amount = 1)
		{
			if ($this->isEmpty()) {
				return;
			}

			$keys = array_rand($this->items, $amount);

			return is_array($keys) ? array_intersect_key($this->items, array_flip($keys)) : $this->items[$keys];
		}

		/**
		 * Reduce the collection to a single value.
		 *
		 * @param  callable  $callback
		 * @param  mixed     $initial
		 * @return mixed
		 */
		public function reduce(callable $callback, $initial = null)
		{
			return array_reduce($this->items, $callback, $initial);
		}

		/**
		 * Create a collection of all elements that do not pass a given truth test.
		 *
		 * @param  callable|mixed  $callback
		 * @return static
		 */
		public function reject($callback)
		{
			if ($this->useAsCallable($callback)) {
				return $this->filter(function($item) use ($callback) {
					return !$callback($item);
				});
			}

			return $this->filter(function($item) use ($callback) {
				return $item != $callback;
			});
		}

		/**
		 * Reverse items order.
		 *
		 * @return static
		 */
		public function reverse()
		{
			return $this->new(array_reverse($this->items, true));
		}

		/**
	     * Search the collection for a given value and return the corresponding key if successful.
	     *
	     * @param  mixed  $value
	     * @param  bool   $strict
	     * @return mixed
	     */
	    public function search($value, $strict = false)
	    {
	        if (! $this->useAsCallable($value)) {
	            return array_search($value, $this->items, $strict);
	        }

	        foreach ($this->items as $key => $item) {
	            if (call_user_func($value, $item, $key)) {
	                return $key;
	            }
	        }

	        return false;
	    }

		/**
		 * Get and remove the first item from the collection.
		 *
		 * @return mixed|null
		 */
		public function shift()
		{
			return array_shift($this->items);
		}

		/**
		 * Shuffle the items in the collection.
		 *
		 * @return $this
		 */
		public function shuffle()
		{
			$items = $this->items;

	        shuffle($items);

	        return $this->new($items);
		}

		/**
		 * [sortByRand description]
		 * @return [type] [description]
		 */
		public function sortByRand()
		{
			$items = $this->items;

	        shuffle($items);

	        return $this->new($items);
		}

		/**
		 * Slice the underlying collection array.
		 *
		 * @param  int   $offset
		 * @param  int   $length
		 * @param  bool  $preserveKeys
		 * @return static
		 */
		public function slice($offset, $length = null)
	    {
	        return $this->new(array_slice($this->items, $offset, $length, true));
	    }

		/**
	     * Chunk the underlying collection array.
	     *
	     * @param  int   $size
	     * @return static
	     */
	    public function chunk($size)
	    {
	        $chunks = [];

	        foreach (array_chunk($this->items, $size, true) as $chunk) {
	            $chunks[] = $this->new($chunk);
	        }

	        return $this->new($chunks);
	    }

		/**
		 * Sort through each item with a callback.
		 *
		 * @param  callable  $callback
		 * @return $this
		 */
		public function sort(callable $callback = null)
	    {
	        $items = $this->items;

	        $callback ? uasort($items, $callback) : uasort($items, function ($a, $b) {
	            if ($a == $b) {
	                return 0;
	            }

	            return ($a < $b) ? -1 : 1;
	        });

	        return $this->new($items);
	    }

		/**
		 * Sort the collection using the given callback.
		 *
		 * @param  callable|string  $callback
		 * @param  int   $options
		 * @param  bool  $descending
		 * @return $this
		 */
		public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
		{
			$results = [];

			if (!$this->useAsCallable($callback)) {
				$callback = $this->valueRetriever($callback);
			}

			foreach ($this->items as $key => $value) {
				$results[$key] = $callback($value);
			}

			$descending ? arsort($results, $options) : asort($results, $options);

			foreach (array_keys($results) as $key) {
				$results[$key] = $this->items[$key];
			}

			$this->items = $results;

			return $this;
		}

		/**
		 * Sort the collection in descending order using the given callback.
		 *
		 * @param  callable|string  $callback
		 * @param  int  $options
		 * @return $this
		 */
		public function sortByDesc($callback, $options = SORT_REGULAR)
		{
			return $this->sortBy($callback, $options, true);
		}

		/**
	     * Splice a portion of the underlying collection array.
	     *
	     * @param  int  $offset
	     * @param  int|null  $length
	     * @param  mixed  $replacement
	     * @return static
	     */
	    public function splice($offset, $length = null, $replacement = [])
	    {
	        if (func_num_args() == 1) {
	            return $this->new(array_splice($this->items, $offset));
	        }

	        return $this->new(array_splice($this->items, $offset, $length, $replacement));
	    }

		/**
		 * Get the sum of the given values.
		 *
		 * @param  callable|string|null  $callback
		 * @return mixed
		 */
		public function sum($callback = null)
		{
			if (is_null($callback)) {
				return array_sum($this->items);
			}

			if (!$this->useAsCallable($callback)) {
				$callback = $this->valueRetriever($callback);
			}

			return $this->reduce(function($result, $item) use ($callback) {
				return $result += $callback($item);
			}, 0);
		}

		public function avg($field = null)
		{
			if ($count = $this->count()) {
	            return (double) $this->sum($field) / $count;
	        }

	        return 0;
		}

	    /**
	     * Alias for the "avg" method.
	     *
	     * @param  string|null  $key
	     * @return mixed
	     */
	    public function average($field = null)
	    {
	        return $this->avg($field);
	    }

		/**
		 * Take the first or last {$limit} items.
		 *
		 * @param  int  $limit
		 * @return static
		 */
		public function take($limit = null)
		{
			if ($limit < 0) {
				return $this->slice($limit, abs($limit));
			}

			return $this->slice(0, $limit);
		}

		/**
		 * Transform each item in the collection using a callback.
		 *
		 * @param  callable  $callback
		 * @return $this
		 */
		public function transform(callable $callback)
		{
			$this->items = array_map($callback, $this->items);

			return $this;
		}

		/**
		 * Return only unique items from the collection array.
		 *
		 * @return static
		 */
		public function unique($key = null)
	    {
	        if (is_null($key)) {
	            return $this->new(array_unique($this->items, SORT_REGULAR));
	        }

	        $key = $this->valueRetriever($key);

	        $exists = [];

	        return $this->reject(function ($item) use ($key, &$exists) {
	            if (in_array($id = $key($item), $exists)) {
	                return true;
	            }

	            $exists[] = $id;
	        });
	    }

		/**
		 * Reset the keys on the underlying array.
		 *
		 * @return static
		 */
		public function values()
		{
			return $this->new(array_values($this->items));
		}

		/**
		 * Get a value retrieving callback.
		 *
		 * @param  string  $value
		 * @return \Closure
		 */
		protected function valueRetriever($value)
		{
			return function($item) use ($value) {
				return lib('array')->data_get($item, $value);
			};
		}

	    /**
	     * Zip the collection together with one or more arrays.
	     *
	     * e.g. new Collection([1, 2, 3])->zip([4, 5, 6]);
	     *      => [[1, 4], [2, 5], [3, 6]]
	     *
	     * @param  mixed ...$items
	     * @return static
	     */
	    public function zip($items)
	    {
	        $arrayableItems = array_map(function ($items) {
	            return $this->getArrayableItems($items);
	        }, func_get_args());

	        $params = array_merge([function () {
	            return $this->new(func_get_args());
	        }, $this->items], $arrayableItems);

	        return $this->new(call_user_func_array('array_map', $params));
	    }

		/**
		 * Get the collection of items as a plain array.
		 *
		 * @return array
		 */
		public function toArray()
		{
			return array_map(function($value) {
				return $value instanceof Arrayable ? $value->toArray() : $value;

			}, $this->items);
		}

		/**
		 * Convert the object into something JSON serializable.
		 *
		 * @return array
		 */
		public function jsonSerialize()
		{
			return $this->toArray();
		}

		/**
		 * Get the collection of items as JSON.
		 *
		 * @param  int  $options
		 * @return string
		 */
		public function toJson($options = 0)
		{
			return json_encode($this->toArray(), $options);
		}

		/**
		 * Get an iterator for the items.
		 *
		 * @return \ArrayIterator
		 */
		public function getIterator()
		{
			return new ArrayIterator($this->items);
		}

		/**
		 * Get a CachingIterator instance.
		 *
		 * @param  int  $flags
		 * @return \CachingIterator
		 */
		public function getCachingIterator($flags = CachingIterator::CALL_TOSTRING)
		{
			return new CachingIterator($this->getIterator(), $flags);
		}

		/**
		 * Count the number of items in the collection.
		 *
		 * @return int
		 */
		public function count()
		{
			return count($this->items);
		}

		/**
		 * Determine if an item exists at an offset.
		 *
		 * @param  mixed  $key
		 * @return bool
		 */
		public function offsetExists($key)
		{
			return array_key_exists($key, $this->items);
		}

		/**
		 * Get an item at a given offset.
		 *
		 * @param  mixed  $key
		 * @return mixed
		 */
		public function offsetGet($key)
		{
			return $this->items[$key];
		}

		/**
		 * Set the item at a given offset.
		 *
		 * @param  mixed  $key
		 * @param  mixed  $value
		 * @return void
		 */
		public function offsetSet($key, $value)
		{
			if (is_null($key)) {
				$this->items[] = $value;
			} else {
				$this->items[$key] = $value;
			}
		}

		/**
		 * Unset the item at a given offset.
		 *
		 * @param  string  $key
		 * @return void
		 */
		public function offsetUnset($key)
		{
			unset($this->items[$key]);
		}

		/**
		 * Convert the collection to its string representation.
		 *
		 * @return string
		 */
		public function __toString()
		{
			return $this->toJson();
		}

		public function nth($key, $d = null)
	    {
	        return (isset($this->items[$key])) ? $this->items[$key] : $d;
	    }

		/**
		 * Results array of items from Collection or Arrayable.
		 *
	  	 * @param  mixed  $items
		 * @return array
		 */
		protected function getArrayableItems($items)
		{
			if ($items instanceof self) {
				$items = $items->all();
			} elseif ($items instanceof Arrayable) {
				$items = $items->toArray();
			}

			return $items;
		}

		public function like($field, $value)
		{
			return $this->where($field, 'like', $value);
		}

		public function notLike($field, $value)
		{
			return $this->where($field, 'not like', $value);
		}

		public function findBy($field, $value)
		{
			return $this->where($field, '=', $value);
		}

		public function firstBy($field, $value)
		{
			return $this->where($field, '=', $value)->first();
		}

		public function lastBy($field, $value)
		{
			return $this->where($field, '=', $value)->last();
		}

		public function in($field, array $values)
		{
			return $this->where($field, 'in', $values);
		}

		public function notIn($field, array $values)
		{
			return $this->where($field, 'not in', $values);
		}

		public function rand($default = null)
		{
			if (!empty($this->items)) {
				shuffle($this->items);

				return current($this->items);
			}

			return $default;
		}

		public function isBetween($field, $min, $max)
		{
			return $this->where($field, 'between', [$min, $max]);
		}

		public function isNotBetween($field, $min, $max)
		{
			return $this->where($field, 'not between', [$min, $max]);
		}

		public function isNull($field)
		{
			return $this->where($field, 'is', 'null');
		}

		public function isNotNull($field)
		{
			return $this->where($field, 'is not', 'null');
		}

		/**
		 * [where description]
		 * @param  [type] $key      [description]
		 * @param  [type] $operator [description]
		 * @param  [type] $value    [description]
		 * @return [type]           [description]
		 */
		public function where($key, $operator = null, $value = null)
		{
			if (func_num_args() == 1) {
				if (is_array($key)) {
					list($key, $operator, $value) = $key;
					$operator = strtolower($operator);
				}
			}

			if (func_num_args() == 2) {
				list($value, $operator) = [$operator, '='];
			}

			return $this->filter(function($item) use ($key, $operator, $value) {
				$item = (object) $item;
				$actual = $item->{$key};

				$insensitive = in_array($operator, ['=i', 'like i', 'not like i']);

				if ((!is_array($actual) || !is_object($actual)) && $insensitive) {
					$actual	= Inflector::lower(Inflector::unaccent($actual));
				}

				if ((!is_array($value) || !is_object($value)) && $insensitive) {
					$value	= Inflector::lower(Inflector::unaccent($value));
				}

				if ($insensitive) {
					$operator = str_replace(['=i', 'like i'], ['=', 'like'], $operator);
				}

				switch ($operator) {
					case '<>':
					case '!=':
						return sha1(serialize($actual)) != sha1(serialize($value));
					case '>':
						return $actual > $value;
					case '<':
						return $actual < $value;
					case '>=':
						return $actual >= $value;
					case '<=':
						return $actual <= $value;
					case 'between':
						return $actual >= $value[0] && $actual <= $value[1];
					case 'not between':
						return $actual < $value[0] || $actual > $value[1];
					case 'in':
						return in_array($actual, $value);
					case 'not in':
						return !in_array($actual, $value);
					case 'like':
                        $value  = str_replace("'", '', $value);
                        $value  = str_replace('%', '*', $value);

                        return fnmatch($value, $actual);
                    case 'not like':
                        $value  = str_replace("'", '', $value);
                        $value  = str_replace('%', '*', $value);

                        $check  = fnmatch($value, $actual);

                        return !$check;
                    case 'is':
                    	return is_null($actual);
                    case 'is not':
                    	return !is_null($actual);
					case '=':
					default:
						return sha1(serialize($actual)) == sha1(serialize($value));
				}
			});
		}

		public function getSchema()
        {
            $row = $this->first();

            if (!$row) {
                return [];
            }

            $fields = [];

            foreach ($row as $k => $v) {
                $type = gettype($v);

                if (strlen($v) > 255 && $type == 'string') {
                    $type = 'text';
                }

                $fields[$k] = $type;
            }

            $collection = [];

            $collection['id'] = 'primary key integer';

            ksort($fields);

            foreach ($fields as $k => $v) {
                if (fnmatch('*_id', $k)) {
                    $collection[$k] = 'foreign key integer';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*ed_at', $k)) {
                    $collection[$k] = 'timestamp integer';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*tel*', $k)) {
                    $collection[$k] = 'phone string';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*phone*', $k)) {
                    $collection[$k] = 'phone string';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*mobile*', $k)) {
                    $collection[$k] = 'phone string';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*cellular*', $k)) {
                    $collection[$k] = 'phone string';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*fax*', $k)) {
                    $collection[$k] = 'phone string';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*mail*', $k) && fnmatch('*@*', $v)) {
                    $collection[$k] = 'email string';
                }
            }

            foreach ($fields as $k => $v) {
                if (fnmatch('*courriel*', $k) && fnmatch('*@*', $v)) {
                    $collection[$k] = 'email string';
                }
            }

            foreach ($fields as $k => $v) {
                if (!isset($collection[$k])) {
                    $collection[$k] = $v;
                }
            }

            return $collection;
        }

        public function lookfor(array $criterias)
        {
            $collection = $this;

            foreach ($criterias as $field => $value) {
                $collection = $collection->where($field, '=', $value);
            }

            return $collection;
        }

        public function q()
        {
            $collection = $this;
            $conditions = array_chunk(func_get_args(), 3);

            foreach ($conditions as $condition) {
            	list($f, $o, $v) = $condition;
                $collection = $collection->where($f, $o, $v);
            }

            return $collection;
        }

        public function query()
        {
        	return call_user_func_array([$this, 'q'], func_get_args());
        }

        public function save($file)
        {
        	$array = array_values($this->toArray());

        	File::delete($file);
        	File::put($file, "<?php\nreturn " . var_export($array, 1) . ';');
        }

        public function load($file)
        {
        	if (File::exists($file)) {
        		$items = include($file);
        		$items = is_null($items) ? [] : $this->getArrayableItems($items);

				$this->items = (array) $items;
        	}

        	return $this;
        }

        public function fromJson($json)
        {
        	return $this->new(json_decode($json, true));
        }

        public function multisort($criteria)
        {
 			$comparer = function ($first, $second) use ($criteria) {
    			foreach ($criteria as $key => $orderType) {
      				$orderType = strtolower($orderType);

      				if (!isset($first[$key]) || !isset($second[$key])) {
      					return false;
      				}

			      	if ($first[$key] < $second[$key]) {
			        	return $orderType === "asc" ? -1 : 1;
			      	} else if ($first[$key] > $second[$key]) {
			        	return $orderType === "asc" ? 1 : -1;
			      	}
    			}

    			return false;
  			};

  			$sorted = $this->sort($comparer);

			return $this->new($sorted->values()->toArray());
		}

        public function __call($m, $a)
        {
        	if ($m == 'new') {
        		return new self(current($a));
        	} elseif ($m == 'list') {
        		return call_user_func_array([$this, 'lists'], $a);
        	}
        }

        public function __set($key, $value)
        {
        	$this->items[$key] = $value;

        	return $this;
        }

        public function __isset($key)
        {
        	return $this->offsetExists($key);
        }

        public function __unset($key)
        {
        	return $this->forget($key);
        }

        public function __get($key)
        {
        	return isAke($this->items, $key, null);
        }
	}
