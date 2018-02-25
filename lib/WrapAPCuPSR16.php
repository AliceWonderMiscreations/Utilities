<?php

// This is only in utilities namespace during testing / dev

/*
 +-------------------------------------------------------+
 |                                                       |
 | Copyright (c) 2018 Alice Wonder Miscreations          |
 |  May be used under terms of MIT license               |
 |                                                       |
 | When implementation of PSR-16 is finished I will port |
 |  coding style to PSR-2 except I will keep trailing ?> |
 |  and class name will change to SimpleCacheAPCu        |
 |                                                       |
 +-------------------------------------------------------+
 | Purpose: PSR-16 APCu Interface - not yet there...     |
 +-------------------------------------------------------+
*/

// method feature comments preceded by ** are not yet implemented

\\namespace AliceWonderMiscreations\Psr;
namespace AliceWonderMiscreations\Utilities;

\\class SimpleCacheAPCu implements \Psr\SimpleCache {
class WrapAPCuPSR16 {
  protected $enabled = false;
  protected $salt;
  protected $webappPrefix;
  // 0 tells APCu to store it as long as it can
  protected $defaultSeconds = 0;

  protected function weakHash( string $key ) {
    // purpose is not cryptographic, but to avoid odd characters
    //  in the actual apcu key.
    // salt is because I prefer salted hashes for private use hashes
    $key = $this->salt . $key;
    $key = hash('ripemd160', $key);
    // 16^16 should be enough of the hash to avoid collisions
    return substr($key, 17, 16);
  }

  protected function adjustKey( string $key ) {
    $key = trim($key);
    if(strlen($key) === 0) {
      // key can not be empty
      return false;
    }
    if(strlen($key) > 255) {
      // key should not be larger
      //  than 255 character
      return false;
    }
    if(preg_match('/\[\]\/\@\\/', $key) !== 0) {
      // PSR-16 says those characters not allowed
      return false;
    }
    $key = $this->webappPrefix . $this->weakHash($key);
    return $key;
  }

  protected function setWebAppPrefix( string $str ) {
    $str = strtoupper(trim($str));
    if(strlen($str) < 3) {
      // prefix must be at least three characters
      return false;
    }
    if(strlen($str) > 32) {
      // prefix must not be larger than 32 characters
      return false;
    }
    if(preg_match('/^[A-Z0-9]/', $str) !== 0) {
      // prefix must only contain letters and numbers
      return false;
    }
    $this->webappPrefix = $str . '_';
    return true;
  }
  
  protected function setHashSalt( string $str ) {
    $str = trim($str);
    if(strlen($str < 8)) {
      // salt must be at least eight characters
      return false;
    }
    $this->salt = $str;
    return true;
  }
  
  /*
   * If fed a number, this function attempts to determine if the integer is
   *  a UNIX timestamp in the future or instructions on how long to cache.
   * If fed a string, this function attempts to convert it to a unix
   *  timestamp.
   * The function will always return a positive integer number of seconds
   *  using the class default if it must.
   */
  protected function ttlToSeconds( $ttl ) {
    $now = time();
    if(is_numeric($ttl)) {
      try {
        $seconds = intval($ttl, 10);
      } catch (\Exception $e) {
        return $this->defaultSeconds;
      }
      if($seconds > $now) {
        return ($now - $seconds);
      } 
      if($seconds < 0) {
        return $this->defaultSeconds;
      }
      return $seconds;
    }
    // hope it is a date string
    if($seconds = strtotime($ttl, $now)) {
      if($seconds > $now) {
        return ($seconds - $now);
      }
    }
    return $this->defaultSeconds;
  }

  /**
   * Sets the default cache time in seconds. NOT part of PSR-16.
   *
   *
   * @param int $seconds The default seconds to cache entries
   *
   * @return bool true on success, false on failure
   *
  ** FIXME - throw an exception at invalid argument
   */
  public function setDefaultSeconds( int $seconds ) {
    if($seconds < 0) {
      // FIXME throw exception
      return false;
    }
    $this->defaultSeconds = $seconds;
    return true;
  }

  /**
   * Fetches a value from the cache.
   *
   * @param string $key     The unique key of this item in the cache.
   * @param mixed  $default Default value to return if the key does not exist.
   *
   * @return mixed The value of the item from the cache, or $default in case of cache miss.
   *
  ** @throws \Psr\SimpleCache\InvalidArgumentException
   *   MUST be thrown if the $key string is not a legal value.
   */
  public function get( string $key, $default = null ) {
    if(! $key = $this->adjustKey($key)) {
      // fixme throw exception
      return false;
    }
    if($this->enabled) {
      if($return = apcu_fetch($key)) {
        return $return;
      }
    }
    return $default;
  }

  /**
   * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
   *
   * @param string                 $key   The key of the item to store.
   * @param mixed                  $value The value of the item to store, must be serializable.
   * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
   *                                      the driver supports TTL then the library may set a default value
   *                                      for it or let the driver take care of that.
   *
   * @return bool True on success and false on failure.
   *
  ** @throws \Psr\SimpleCache\InvalidArgumentException
   *   MUST be thrown if the $key string is not a legal value.
   */
  public function set( string $key, $value, $ttl = null ) {
    if(! $key = $this->adjustKey($key)) {
      // fixme throw exception
      return false;
    }
    if($this->enabled) {
      $seconds = $this->ttlToSeconds($ttl);
      return apcu_store($key, $value, $seconds);
    }
    return false;
  }

  /**
    * Delete an item from the cache by its unique key.
    *
    * @param string $key The unique cache key of the item to delete.
    *
    * @return bool True if the item was successfully removed. False if there was an error.
    *
   ** @throws \Psr\SimpleCache\InvalidArgumentException
    *   MUST be thrown if the $key string is not a legal value.
    */
  public function delete( string $key ) {
    if(! $key = $this->adjustKey($key)) {
      // fixme throw exception
      return false;
    }
    if($this->enabled) {
      return apcu_delete($key);
    }
    return false;
  }

  /**
    * Wipes clean the entire cache's keys. This implementation
    *  only wipes for matching webappPrefix (custom NON PSR-16
    *  feature set during constructor)
    *
    * @return bool True on success and false on failure.
    */
  public function clear() {
    $return = false;
    if($this->enabled) {
      $info = apcu_cache_info();
      if(isset($info['cache_list'])) {
        $return = true;
        $cachelist = $info['cache_list'];
        foreach($cachelist as $item) {
          if(isset($item['key'])) {
            $key = $item['key'];
            if(strpos($key, $this->webappPrefix) === 0) {
              apcu_delete($key);
            }
          }
        }
      }
    }
    return $return;
  }

  /**
   * Wipes clean the entire cache's keys regardless of webappPrefix.
   *  NON PSR-16 method
   *
   * @return bool True on success and false on failure.
   */
  public function clearAll() {
    $return = false;
    if($this->enabled) {
      $return = true;
      if(! apcu_clear_cache()) {
        return false;
      }
    }
    return $return;
  }

  /**
   * Obtains multiple cache items by their unique keys.
   *
  ** @param iterable $keys    A list of keys that can obtained in a single operation.
   * @param mixed    $default Default value to return for keys that do not exist.
   *
   * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
   *
  ** @throws \Psr\SimpleCache\InvalidArgumentException
   *   MUST be thrown if $keys is neither an array nor a Traversable,
   *   or if any of the $keys are not a legal value.
   */
  public function getMultiple( array $keys, $default = null ) {
    if(count($keys === 0) {
      //FIXME throw exception if empty array
      return false;
    }
    $return = array();
    foreach($keys as $key) {
      $return[] = $this->get($key, $default);
    }
    return $return;
  }

  /**
   * Persists a set of key => value pairs in the cache, with an optional TTL.
   *
  ** @param iterable               $values A list of key => value pairs for a multiple-set operation.
   * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
   *                                       the driver supports TTL then the library may set a default value
   *                                       for it or let the driver take care of that.
   *
   * @return bool True on success and false on failure.
   *
  ** @throws \Psr\SimpleCache\InvalidArgumentException
   *   MUST be thrown if $values is neither an array nor a Traversable,
   *   or if any of the $values are not a legal value.
   */
  public function setMultiple( array $pairs, int $ttl = null ) {
    if(count($pairs === 0) {
      //FIXME throw exception if empty array
      return false;
    }
    $seconds = $this->ttlToSeconds($ttl);
    $success = 0;
    if($this->enabled) {
      foreach($pairs as $key => $value) {
        if ($this->set($key, $value, $seconds)) {
          $success++;
        }
      }
    }
    if(count($pairs === $success)) {
      return true;
    }
    return false;
  }

  /**
   * Deletes multiple cache items in a single operation.
   *
  ** @param iterable $keys A list of string-based keys to be deleted.
   *
   * @return bool True if the items were successfully removed. False if there was an error.
   *
  ** @throws \Psr\SimpleCache\InvalidArgumentException
   *   MUST be thrown if $keys is neither an array nor a Traversable,
   *   or if any of the $keys are not a legal value.
   */
  public function deleteMultiple( array $keys ) {
    if(count($keys === 0) {
      //FIXME throw exception if empty array
      return false;
    }
    $success = 0;
    foreach($keys as $key) {
      if ($this->delete($key)) {
        $success++;
      }
    }
    if(count($keys) === $success) {
      return true;
    }
    return false;
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
  ** @throws \Psr\SimpleCache\InvalidArgumentException
   *   MUST be thrown if the $key string is not a legal value.
   */
  public function has( string $key ) {
    if(! $key = $this->adjustKey($key)) {
      // fixme throw exception
      return false;
    }
    if($this->enabled) {
      return apcu_exists($key);
    }
    return false;
  }

  /**
   * Constructor function. Takes two arguments with defaults.
   * @param string $webappPrefix Sets the prefix to use for internal APCu key assignment. Useful
   *                             to avoid key collisions between web applications (think of it
   *                             like a namespace). String between 3 and 32 characters in length
   *                             containing only letters A-Z (NOT case sensitive) and numbers 0-9.
   *                             Defaults to "Default".
   * @param string $salt         A salt to use in the generation of the hash used as the internal
   *                             APCu key. Must be at least eight characters long. There is a
   *                             default salt that is used if you do not specify. Note that when
   *                             you change the salt, all the internal keys change.
   *
   * @return bool false on failure, class object on success.
   *
  ** @throw an exception on error
   *
   */
  public function __construct( string $webappPrefix='Default', string $salt='6Dxypt3ePw2SM2zYzEVAFkDBQpxbk16z1') {
    if(! $this->setHashSalt($salt)) {
      // FIXME throw exception
      return false;
    }
    if(! $this->setWebAppPrefix($webappPrefix)) {
      // FIXME throw exception
      return false;
    }
  }

}

?>