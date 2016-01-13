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

	use ArrayAccess;
	use JsonSerializable;
	use Illuminate\Contracts\Support\Jsonable;
	use Illuminate\Contracts\Support\Arrayable;

	class FluentLib implements ArrayAccess, Arrayable, Jsonable, JsonSerializable
	{
		/**
		 * All of the attributes set on the container.
		 *
		 * @var array
		 */
		protected $attributes = [];

		/**
		 * Create a new fluent container instance.
		 *
		 * @param  array|object	$attributes
		 * @return void
		 */
		public function __construct($attributes = [])
		{
			$this->attributes = $attributes;

		}

		/**
		 * Get an attribute from the container.
		 *
		 * @param  string  $key
		 * @param  mixed   $default
		 * @return mixed
		 */
		public function get($key, $default = null)
		{
			if (array_key_exists($key, $this->attributes)) {
				return $this->attributes[$key];
			}

			return value($default);
		}

		public function del($k)
		{
			$offset = Inflector::uncamelize($k);

			unset($this->{$offset});

			return $this;
		}

		public function delete($k)
		{
			$offset = Inflector::uncamelize($k);

			unset($this->{$offset});

			return $this;
		}

		public function forget($k)
		{
			$offset = Inflector::uncamelize($k);

			unset($this->{$offset});

			return $this;
		}

		public function remove($k)
		{
			$offset = Inflector::uncamelize($k);

			unset($this->{$offset});

			return $this;
		}

		public function has($k)
		{
			$offset = Inflector::uncamelize($k);

			return isset($this->{$offset});
		}

		public function exists($k)
		{
			$offset = Inflector::uncamelize($k);

			return isset($this->{$offset});
		}

		/**
		 * Get the attributes from the container.
		 *
		 * @return array
		 */
		public function getAttributes()
		{
			return $this->attributes;
		}

		/**
		 * Convert the Fluent instance to an array.
		 *
		 * @return array
		 */
		public function toArray()
		{
			return $this->attributes;
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
		 * Convert the Fluent instance to JSON.
		 *
		 * @param  int  $options
		 * @return string
		 */
		public function toJson($options = 0)
		{
			return json_encode($this->toArray(), $options);
		}

		/**
		 * Determine if the given offset exists.
		 *
		 * @param  string  $offset
		 * @return bool
		 */
		public function offsetExists($offset)
		{
			$offset = Inflector::uncamelize($offset);

			return isset($this->{$offset});
		}

		/**
		 * Get the value for a given offset.
		 *
		 * @param  string  $offset
		 * @return mixed
		 */
		public function offsetGet($offset)
		{
			$offset = Inflector::uncamelize($offset);

			return $this->{$offset};
		}

		/**
		 * Set the value at the given offset.
		 *
		 * @param  string  $offset
		 * @param  mixed   $value
		 * @return void
		 */
		public function offsetSet($offset, $value)
		{
			$offset = Inflector::uncamelize($offset);
			$this->{$offset} = $value;

			return $this;
		}

		/**
		 * Unset the value at the given offset.
		 *
		 * @param  string  $offset
		 * @return void
		 */
		public function offsetUnset($offset)
		{
			$offset = Inflector::uncamelize($offset);

			unset($this->{$offset});

			return $this;
		}

		/**
		 * Handle dynamic calls to the container to set attributes.
		 *
		 * @param  string  $method
		 * @param  array   $args
		 * @return $this
		 */
		public function __call($m, $a)
		{
			if (fnmatch('get*', $m)) {
                $k = Inflector::lower(substr($m, 3));

                $default = empty($a) ? null : current($a);

                return $this->get($k, $default);
            } elseif (fnmatch('set*', $m)) {
                $k = Inflector::lower(substr($m, 3));

                return $this->set($k, current($a));
            } elseif (fnmatch('has*', $m)) {
                $k = Inflector::lower(substr($m, 3));

                return $this->has($k);
            } elseif (fnmatch('del*', $m)) {
                $k = Inflector::lower(substr($m, 3));

                return $this->del($k);
            } else {
				$method = Inflector::uncamelize($m);
				$this->attributes[$method] = !empty($a) ? reset($a) : true;

				return $this;
			}
		}

		/**
		 * Dynamically retrieve the value of an attribute.
		 *
		 * @param  string  $key
		 * @return mixed
		 */
		public function __get($key)
		{
			return $this->get($key);
		}

		/**
		 * Dynamically set the value of an attribute.
		 *
		 * @param  string  $key
		 * @param  mixed   $value
		 * @return void
		 */
		public function __set($key, $value)
		{
			$key = Inflector::uncamelize($key);
			$this->attributes[$key] = $value;

			return $this;
		}

		/**
		 * Dynamically check if an attribute is set.
		 *
		 * @param  string  $key
		 * @return void
		 */
		public function __isset($key)
		{
			$key = Inflector::uncamelize($key);

			return isset($this->attributes[$key]);
		}

		/**
		 * Dynamically unset an attribute.
		 *
		 * @param  string  $key
		 * @return void
		 */
		public function __unset($key)
		{
			$key = Inflector::uncamelize($key);

			unset($this->attributes[$key]);

			return $this;
		}
	}
