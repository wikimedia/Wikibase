<?php
declare( strict_types = 1 );

namespace Wikibase\Lib;

use BagOStuff;
use DateInterval;
use DateTime;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * @license GPL-2.0-or-later
 */
class SimpleCacheWithBagOStuff implements CacheInterface {
	use LoggerAwareTrait;

	private BagOStuff $inner;
	private string $prefix;
	private string $secret;

	/**
	 * @param BagOStuff $inner
	 * @param string $prefix While setting and getting all keys will be prefixed with this string
	 * @param string $secret Will be used to create a signature for stored values
	 *
	 * @throws \InvalidArgumentException If prefix has wrong format or secret is not a string or empty
	 */
	public function __construct( BagOStuff $inner, string $prefix, string $secret ) {
		$this->assertKeyIsValid( $prefix );

		if ( $secret === '' ) {
			throw new \InvalidArgumentException( "Secret is required to be a nonempty string" );
		}

		$this->inner = $inner;
		$this->prefix = $prefix;
		$this->secret = $secret;
		$this->logger = new NullLogger();
	}

	/**
	 * Fetches a value from the cache.
	 *
	 * @param string $key The unique key of this item in the cache.
	 * @param mixed $default Default value to return if the key does not exist.
	 *
	 * @return mixed The value of the item from the cache, or $default in case of cache miss.
	 *
	 * @throws CacheInvalidArgumentException
	 *   MUST be thrown if the $key string is not a legal value.
	 */
	public function get( $key, $default = null ) {
		$this->assertKeyIsValid( $key );
		$key = $this->inner->makeKey( $this->prefix, $key );

		$result = $this->inner->get( $key );
		if ( $result === false ) {
			return $default;
		}

		return $this->unserialize( $result, $default, [ 'key' => $key, 'prefix' => $this->prefix ] );
	}

	/**
	 * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
	 *
	 * @param string $key The key of the item to store.
	 * @param mixed $value The value of the item to store, must be serializable.
	 * @param null|int|DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
	 *                                      the driver supports TTL then the library may set a default value
	 *                                      for it or let the driver take care of that.
	 *
	 * @return bool True on success and false on failure.
	 *
	 * @throws CacheInvalidArgumentException
	 *   MUST be thrown if the $key string is not a legal value.
	 */
	public function set( $key, $value, $ttl = null ) {
		$this->assertKeyIsValid( $key );
		$key = $this->inner->makeKey( $this->prefix, $key );
		$ttl = $this->normalizeTtl( $ttl );

		$value = $this->serialize( $value );

		return $this->inner->set( $key, $value, $ttl );
	}

	/**
	 * Delete an item from the cache by its unique key.
	 *
	 * @param string $key The unique cache key of the item to delete.
	 *
	 * @return bool True if the item was successfully removed. False if there was an error.
	 *
	 * @throws CacheInvalidArgumentException
	 *   MUST be thrown if the $key string is not a legal value.
	 */
	public function delete( $key ) {
		$this->assertKeyIsValid( $key );
		$key = $this->inner->makeKey( $this->prefix, $key );

		return $this->inner->delete( $key );
	}

	/**
	 * Wipes clean the entire cache's keys.
	 *
	 * @return bool True on success and false on failure.
	 */
	public function clear() {
		//Cannot be implemented
		return false;
	}

	/**
	 * Obtains multiple cache items by their unique keys.
	 *
	 * @param iterable $keys A list of keys that can obtained in a single operation.
	 * @param mixed $default Default value to return for keys that do not exist.
	 *
	 * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
	 *
	 * @throws CacheInvalidArgumentException
	 *   MUST be thrown if $keys is neither an array nor a Traversable,
	 *   or if any of the $keys are not a legal value.
	 */
	public function getMultiple( $keys, $default = null ) {
		$keys = $this->toArray( $keys );
		$this->assertKeysAreValid( $keys );
		$prefixedKeys = array_map(
			function ( $k ) {
				return $this->inner->makeKey( $this->prefix, $k );
			},
			$keys
		);

		$innerResult = $this->inner->getMulti( $prefixedKeys );
		$result = [];
		foreach ( $keys as $key ) {
			$prefixedKey = $this->inner->makeKey( $this->prefix, $key );
			if ( !array_key_exists( $prefixedKey, $innerResult ) ) {
				$result[ $key ] = $default;
			} else {
				$result[ $key ] = $this->unserialize(
					$innerResult[ $prefixedKey ],
					$default,
					[ 'key' => $key, 'prefix' => $this->prefix ]
				);
			}
		}

		return $result;
	}

	/**
	 * Persists a set of key => value pairs in the cache, with an optional TTL.
	 *
	 * @param iterable $values A list of key => value pairs for a multiple-set operation.
	 * @param null|int|DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
	 *                                       the driver supports TTL then the library may set a default value
	 *                                       for it or let the driver take care of that.
	 *
	 * @return bool True on success and false on failure.
	 *
	 * @throws CacheInvalidArgumentException
	 *   MUST be thrown if $values is neither an array nor a Traversable,
	 *   or if any of the $values are not a legal value.
	 */
	public function setMultiple( $values, $ttl = null ) {
		$values = $this->toAssociativeArray( $values );

		$ttl = $this->normalizeTtl( $ttl );

		foreach ( $values as $key => $value ) {
			$key = $this->inner->makeKey( $this->prefix, $key );
			$values[ $key ] = $this->serialize( $value );
		}

		return $this->inner->setMulti( $values, $ttl ?: 0 );
	}

	/**
	 * Deletes multiple cache items in a single operation.
	 *
	 * @param iterable $keys A list of string-based keys to be deleted.
	 *
	 * @return bool True if the items were successfully removed. False if there was an error.
	 *
	 * @throws CacheInvalidArgumentException
	 *   MUST be thrown if $keys is neither an array nor a Traversable,
	 *   or if any of the $keys are not a legal value.
	 */
	public function deleteMultiple( $keys ) {
		$keys = $this->toArray( $keys );
		$this->assertKeysAreValid( $keys );
		$result = true;
		foreach ( $keys as $key ) {
			$result = $this->delete( $key ) && $result;
		}
		return $result;
	}

	/**
	 * Determines whether an item is present in the cache.
	 *
	 * NOTE: It is recommended that has() is only to be used for cache warming type purposes
	 * and not to be used within your live applications operations for get/set, as this method
	 * is subject to a race condition where your has() will return true and immediately after,
	 * another script can remove it making the state of your app out of date.
	 *
	 * @param string $key The cache item key.
	 *
	 * @return bool
	 *
	 * @throws CacheInvalidArgumentException
	 *   MUST be thrown if the $key string is not a legal value.
	 */
	public function has( $key ) {
		$this->assertKeyIsValid( $key );
		$key = $this->inner->makeKey( $this->prefix, $key );
		$result = $this->inner->get( $key );
		return $result !== false;
	}

	private function assertKeysAreValid( $keys ): void {
		foreach ( $keys as $key ) {
			$this->assertKeyIsValid( $key );
		}
	}

	/**
	 * @throws CacheInvalidArgumentException
	 */
	private function assertKeyIsValid( $key ): void {
		if ( !is_string( $key ) ) {
			$type = gettype( $key );
			throw new CacheInvalidArgumentException( "Cache key should be string or integer, `{$type}` is given" );
		}

		if ( $key === '' ) {
			throw new CacheInvalidArgumentException( "Cache key cannot be an empty string" );
		}

		if ( preg_match( '/[{}()\/\\\\@:]/', $key ) ) {
			throw new CacheInvalidArgumentException( "Cache key contains characters that are not allowed: `{$key}`" );
		}
	}

	/**
	 * @param mixed $var the object to turn to array
	 * @return array
	 * @throws CacheInvalidArgumentException
	 */
	private function toArray( $var ) {
		if ( is_array( $var ) ) {
			return $var;
		} elseif ( !( $var instanceof \Traversable ) ) {
			$type = gettype( $var );
			throw new CacheInvalidArgumentException( "Expected iterable, `{$type}` given" );
		}

		return iterator_to_array( $var, false );
	}

	/**
	 * @param mixed $var the object to turn to associative array
	 * @return array
	 * @throws CacheInvalidArgumentException
	 */
	private function toAssociativeArray( $var ) {
		if ( is_array( $var ) ) {
			return $var;
		} elseif ( !is_iterable( $var ) ) {
			$type = gettype( $var );
			throw new CacheInvalidArgumentException( "Expected iterable, `{$type}` given" );
		}

		$result = [];
		foreach ( $var as $key => $value ) {
			$this->assertKeyIsValid( is_int( $key ) ? (string)$key : $key );
			$result[$key] = $value;
		}
		return $result;
	}

	/**
	 * @param null|int|DateInterval $ttl The TTL value of this item. If no value is sent and
	 *                                    the driver supports TTL then the library may set a default value
	 *                                    for it or let the driver take care of that.
	 *
	 * @return int Time to live in form of seconds from now
	 * @throws CacheInvalidArgumentException
	 */
	private function normalizeTtl( $ttl ) {
		// Addition of `1` to timestamp is required to avoid the issue when we read timestamp in
		// the very end of the pending second (lets say 57.999) so that effective TTL becomes
		// very small (in former example it will be 0.001). This issue makes tests flaky.
		// @see https://phabricator.wikimedia.org/T201453
		if ( $ttl instanceof DateInterval ) {
			return ( new DateTime() )->add( $ttl )->getTimestamp() - time() + 1;
		} elseif ( $ttl === 0 ) {
			// BagOStuff treats zero as indefinite while PSR requires this to be ttl meaning zero
			// is basically an expired entry (we have tests relying on this assumption)
			return -1;
		} elseif ( is_int( $ttl ) ) {
			return $ttl;
		} elseif ( $ttl === null ) {
			return BagOStuff::TTL_INDEFINITE;
		}

		$type = gettype( $ttl );
		throw new CacheInvalidArgumentException( "Invalid TTL: `null|int|\DateInterval` expected, `$type` given" );
	}

	private function serialize( $value ) {
		$dataToStore = utf8_encode( serialize( $value ) );

		$signature = hash_hmac( 'sha256', $dataToStore, $this->secret );
		return json_encode( [ $signature, $dataToStore ] );
	}

	/**
	 * @param string $string
	 * @param mixed $default
	 * @param array $loggingContext
	 * @return mixed
	 * @throws \Exception
	 *
	 * @note This implementation is so complicated because we cannot use PHP serialization due to
	 *            the possibility of Object Injection attack.
	 *
	 * @see https://phabricator.wikimedia.org/T161647
	 * @see https://secure.php.net/manual/en/function.unserialize.php
	 * @see https://www.owasp.org/index.php/PHP_Object_Injection
	 */
	private function unserialize( $string, $default, array $loggingContext ) {
		$result = json_decode( $string );

		[ $signatureToCheck, $data ] = $result;
		$correctSignature = hash_hmac( 'sha256', $data, $this->secret );
		$hashEquals = hash_equals( $correctSignature, $signatureToCheck );
		if ( !$hashEquals ) {
			$this->logger->alert( "Incorrect signature", $loggingContext );
			return $default;
		}
		$decodedData = utf8_decode( $data );

		if ( $decodedData === serialize( false ) ) {
			return false;
		}

		// phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
		$value = @unserialize(
			$decodedData,
			[
				'allowed_classes' => [ \stdClass::class ],
			]
		);

		if ( $value === false ) {
			$this->logger->alert( "Cannot deserialize stored value", $loggingContext );
			return $default;
		}

		return $value;
	}

}
