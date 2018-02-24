<?php

/*
 +-------------------------------------------------------+
 |                                                       |
 | Copyright (c) 2018 Alice Wonder Miscreations          |
 |  May be used under terms of MIT license               |
 |                                                       |
 | And fuck PSR-2 - I like two spaces for tabs, dammit!  |
 |                                                       |
 +-------------------------------------------------------+
 | Purpose: PSR-16 APCu Interface - not yet there...     |
 +-------------------------------------------------------+
*/

namespace AliceWonderMiscreations\Utilities;

\\class WrapAPCuPSR16  implements \Psr\SimpleCache {
class WrapAPCuPSR16 {
  protected $enabled = false;
  protected $salt;
  protected $webappPrefix = 'ChangeMe';

  protected function weakHash($key) {
    // purpose is not cryptographic, but to avoid odd characters
    //  in the actual apcu key. The salts are meaningless, but I
    //  think they are pretty. I suppose if someone got a dump of
    //  the APCu cache they would make it harder to figure out
    //  what the actual keys are unless they knew the salts used.
    // yet here there are published on github...
    $key = $this->salt . $key;
    $key = hash('ripemd160', $key);
    return substr($key, 17, 12);
  }

  public function get( string $key, $default = null) {
    if($this->enabled) {
      $key = $this->webappPrefix . $this->weakHash($key);
      if($return = apcu_fetch($key)) {
        return $return;
      }
    }
    return $default;
  }
  
  public function set( string $key, $value, int $ttl = 600) {
    if($this->enabled) {
      if($ttl < 0) {
        $ttl = 600;
      }
      $key = $this->webappPrefix . $this->weakHash($key);
      return apcu_store($key, $value, $seconds);
    }
    return false;
  }
  
  public function delete( string $key ) {
    if($this->enabled) {
      $key = $this->webappPrefix . $this->weakHash($key);
      return apcu_delete($key);
    }
    return false;
  }

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
            $kkey = preg_replace('/^' . $this->webappPrefix . '/', '', $key);
            if(strlen($key) > strlen($kkey)) {
              apcu_delete($key);
            }
          }
        }
      }
    }
    return $return;
  }

  public function getMultipleKeys( array $keys, $default = null ) {
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
  
  public function setMultipleKeys( array $pairs, int $ttl = 600 ) {
    if(count($pairs === 0) {
      //FIXME throw exception if empty array
      return false;
    }
    $success = 0;
    if($this->enabled) {
      foreach($pairs as $key => $value) {
        if ($this->set($key, $value, $ttl)) {
          $success++;
        }
      }
    }
    if(count($pairs === $success)) {
      return true;
    }
    return false;
  }
  
  public function deleteMultipleKeys( array $keys ) {
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

  public function __construct( string $webappPrefix='', string $salt='6Dxypt3ePw2SM2zYzEVAFkDBQpxbk16z1') {
    if(strlen($salt) < 6) {
      //FIXME - throw exception
      return false;
    }
    $this->webappPrefix = $webappPrefix . '_';
    $this->salt = $salt;
  }

}

?>