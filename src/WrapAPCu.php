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
 | Purpose: Wrapper class for APCu caching that does not |
 |  puke if APCu gets disabled.                          |
 +-------------------------------------------------------+
*/

namespace AliceWonderMiscreations\Utilities

class WrapAPCu {
  protected $enabled = false;
  //this is needed for clearing a web applicatiion specific
  // cache w/o impacting other web apps on same server
  //should be all upper case ascii [A-Z] and end with _
  public $webappPrefix = 'CHANGEME_';
  
  protected function weakHash($key) {
    // purpose is not cryptographic, but to avoid odd characters
    //  in the actual apcu key. The salts are meaningless, but I
    //  think they are pretty. I suppose if someone got a dump of
    //  the APCu cache they would make it harder to figure out
    //  what the actual keys are unless they knew the salts used.
    // yet here there are published on github...
    $key = '6Dxypt3ePxbk16z173UcB2u-YG@' . $key . 'w2SM2zYzEVAFkDBQp89';
    $key = hash('ripemd160', $key);
    return substr($key, 17, 12);
  }
  
  public function fetch($key) {
    if($this->enabled) {
      $key = $this->webappPrefix . $this->weakHash($key);
      return apcu_fetch($key);
    } else {
      return false;
    }
  }
  
  public function store($key, $value, $seconds) {
    if($this->enabled) {
      $key = $this->webappPrefix . $this->weakHash($key);
      return apcu_store($key, $value, $seconds);
    }
  }
  
  public function delete($key) {
    if($this->enabled) {
      $key = $this->webappPrefix . $this->weakHash($key);
      return apcu_delete($key);
    }
  }
  
  public function clearCache() {
    if($this->enabled) {
      $info = apcu_cache_info();
      if(isset($info['cache_list'])) {
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
  }
  
  public function __construct() {
    if (extension_loaded('apcu') && ini_get('apc.enabled')) {
      $this->enabled = true;
    }
  }
} //end of class

?>
