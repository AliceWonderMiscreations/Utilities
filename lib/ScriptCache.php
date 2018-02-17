<?php

//for custom charsetlist
//if(file_exists('/path/to/char.encodings.inc.php')) {
//  include_once('/path/to/char.encodings.inc.php');
//}

/* v0.7  */

namespace AliceWonderMiscreations\Utilities;

class ScriptCache {
  protected $Etag;
  protected $Lastmod;
  protected $timestamp;
  protected $REQHEADERS;
  
  protected $minify=false;
  protected $cacheok=false;
  //default to a month, reset to year if request has timestamp in filename
  protected $maxage=(60 * 60 * 24 * 30.5);
  protected $utf8=false;
  protected $charEnc='';
  
  protected $scriptname;
  protected $reqname;
  protected $scriptdir;
  protected $ext;
  
  protected $scriptexists=false;
  
  //non UTF-8 character encodings - override in char.encodings.inc.php
  protected $charsetlist = 'auto';
  
  protected function contentEncoding() {
    $accept = 'none';
    if(ini_get('zlib.output_compression')) {
      if(isset($this->REQHEADERS['accept-encoding'])) {
        $T = trim(strtolower($this->REQHEADERS['accept-encoding']));
        if(strpos($T, 'gzip') !== FALSE) {
          $accept = 'gzip';
        } elseif(strpos($T, 'deflate') !== FALSE) {
          $accept = 'deflate';
        }
      }
    }
    if($this->minify) {
      return(substr(md5($accept), 0, 7));
    } else {
      return(substr(md5($accept), 4, 7));
    }
  }
  
  protected function filedata() {
    $filename = $this->scriptdir . $this->scriptname;
    $this->timestamp = filemtime($filename);
    date_default_timezone_set('UTC');
    $this->Lastmod = preg_replace('/\+0000$/', 'GMT', date('r', $this->timestamp));
    $size  = filesize($filename);
    $inode = fileinode($filename);
    $this->Etag = sprintf("%x-%x-%x-%s", $inode, $size, $this->timestamp, $this->contentEncoding());
  }
  
  protected function cachecheck() {
    if(isset($this->REQHEADERS['if-none-match'])) {
      $reqETAG=trim($this->REQHEADERS['if-none-match'], '\'"');
      if (strcmp($reqETAG, $this->Etag) == 0) {
        $this->cacheok=true;
      }
    } elseif(isset($this->REQHEADERS['if-modified-since'])) {
      $reqLMOD=strtotime(trim($this->REQHEADERS['if-modified-since']));
      if ($reqLMOD == $this->timestamp) {
        $this->cacheok=true;
      }
    }
  }
    
  protected function baseheaders() {
    header('Cache-Control: max-age=' . round($this->maxage));
    header('Last-Modified: ' . $this->Lastmod);
    header('ETag: "' . $this->Etag . '"');
    if(ini_get('zlib.output_compression')) {
      header('Vary: Accept-Encoding');
    }
    header_remove('X-Powered-By');
  }
  
  /* this function cleans up line breaks and attempts to convert files to UTF8 */
  protected function cleanSource($content) {
    //nuke BOM when we definitely have UTF8
    $bom = pack('H*','EFBBBF');
    //DOS to UNIX
    $content = str_replace("\r\n", "\n", $content);
    //Classic Mac to UNIX
    $content = str_replace("\r", "\n", $content);
    if(function_exists('mb_detect_encoding')) {
      if(mb_detect_encoding($content, 'UTF-8', TRUE)) {
        $this->charEnc="UTF-8";
        $content = preg_replace("/^$bom/", '', $content);
      } elseif($ENC = mb_detect_encoding($content, $this->charsetlist, TRUE)) {
        $this->charEnc=$ENC;
        if(function_exists('iconv')) {
          if($new = iconv($ENC, 'UTF-8', $content)) {
            $this->charEnc="UTF-8";
            $content = preg_replace("/^$bom/", '', $new);
          } else {
            //conversion failed
            error_log('Could not convert ' . $this->scriptdir . $this->scriptname . ' to UTF-8');
          }
        }
      } else {
        //we could not detect character encoding
        error_log('Could not identify character encoding for ' . $this->scriptdir . $this->scriptname);
      }
    }
    return($content);
  }
  
  protected function jsminify($content) {
    $JSqueeze = new \Patchwork\JSqueeze();
    return($JSqueeze->squeeze($content, true, false));
  }
  
  //https://gist.github.com/brentonstrine/5f56a24c7d34bb2d4655
  protected function cssminify($content) {
    $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
    $content = str_replace(': ', ':', $content);
    $content = str_replace(array("\r\n", "\r", "\n", "\t"), '', $content);
    $content = preg_replace("/ {2,}/", ' ', $content);
    $content = str_replace(array('} '), '}', $content);
    $content = str_replace(array('{ '), '{', $content);
    $content = str_replace(array('; '), ';', $content);
    $content = str_replace(array(', '), ',', $content);
    $content = str_replace(array(' }'), '}', $content);
    $content = str_replace(array(' {'), '{', $content);
    $content = str_replace(array(' ;'), ';', $content);
    $content = str_replace(array(' ,'), ',', $content);
    return $content;
  }
  
  //http://stackoverflow.com/questions/3825226/multi-byte-safe-wordwrap-function-for-utf-8
  // until php gets an actual mb_wordwrap function
  protected function mb_wordwrap($str, $width = 75, $break = "\n", $cut = false) {
    $lines = explode($break, $str);
    foreach ($lines as &$line) {
      $line = rtrim($line);
      if (mb_strlen($line) <= $width)
        continue;
      $words = explode(' ', $line);
      $line = '';
      $actual = '';
      foreach ($words as $word) {
        if (mb_strlen($actual.$word) <= $width)
          $actual .= $word.' ';
        else {
          if ($actual != '')
            $line .= rtrim($actual).$break;
          $actual = $word;
          if ($cut) {
            while (mb_strlen($actual) > $width) {
              $line .= mb_substr($actual, 0, $width).$break;
              $actual = mb_substr($actual, $width);
            }
          }
          $actual .= ' ';
        }
      }
     $line .= trim($actual);
    }
    return implode($break, $lines);
  }
  
  protected function textwordwrap($content) {
    $tmp = explode("\n", $content);
    $currmax = 0;
    foreach($tmp as $line) {
      if(function_exists('mb_strlen')) {
        $lw = mb_strlen($line);
        if($lw === FALSE) {
          $lw = strlen($line);
        }
      } else {
        $lw = strlen($line);
      }
      if($lw > $currmax) {
        $currmax = $lw;
      }
    }
    if($currmax > 120) {
      $n = count($tmp);
      for($i=0; $i<$n; $i++) {
        if(function_exists('mb_wordwrap')) {
          $tmp[$i] = mb_wordwrap($tmp[$i], 80, "\n", TRUE);
        } elseif(function_exists('mb_strlen')) {
          $tmp[$i] = $this->mb_wordwrap($tmp[$i], 80, "\n", TRUE);
        } else {
          $tmp[$i] = wordwrap($tmp[$i], 80, "\n", TRUE);
        }
      }
      $content = implode("\n", $tmp);
    }
    return $content;
  }
  
  protected function getcontent() {
    $content = file_get_contents($this->scriptdir . $this->scriptname);
    if($this->utf8) {
      $content = $this->cleanSource($content);
    }
    if($this->minify) {
      switch($this->ext) {
        case "js":
          $content=$this->jsminify($content);
          break;
        case "css":
          $content=$this->cssminify($content);
          break;
        default:
          $content=$this->textwordwrap($content);
          break;
      }
    }
    //serve the header
    $charset='';
    if(strlen($this->charEnc) > 0) {
      $charset='; charset=' . $this->charEnc; 
    }
    switch($this->ext) {
      case "js":
        header('Content-Type: application/javascript' . $charset);
        break;
      case "css":
        header('Content-Type: text/css' . $charset);
        break;
      default:
        header('Content-Type: text/plain' . $charset);
        break;
    }
    //send the content
    print($content);
  }
  
  public function sendResponse() {
    if($this->scriptexists) {
      $this->baseheaders();
      if($this->cacheok) {
        header("HTTP/1.1 304 Not Modified");
      } else {
        $this->getcontent();
      }
    } else {
      header("HTTP/1.1 404 File Not Found");
    }
  }

  /* Constructor */
  public function scriptCache($reqname, $scriptdir, $fixnonutf8=TRUE) {
    $this->REQHEADERS=array_change_key_case(getallheaders(), CASE_LOWER);
    $this->reqname = trim($reqname);
    $this->scriptdir = trim($scriptdir);
    $this->utf8 = $fixnonutf8;
    $arr = explode('.', $this->reqname);
    $this->ext = end($arr);

    $search = '/-[0-9][0-9][0-9][0-9][0-9][0-9]+\.' . $this->ext . '$/';
    $replace = '.' . $this->ext;
    $this->scriptname = preg_replace($search, $replace, $this->reqname);
    if(file_exists($this->scriptdir . $this->scriptname)) {
      $this->scriptexists=true;
      if(function_exists('getCharsetList')) {
        $this->charsetlist=getCharsetList();
      }
      if(strcmp($this->scriptname, $this->reqname) != 0) {
        $this->minify=true;
        $this->maxage=(60 * 60 * 24 * 365.25);
      }
      if($this->reqname == "service-worker.js") {
        // make this longer after debug
        $this->maxage=(3 * 60);
      }
      $this->filedata();
      $this->cachecheck();
    }
  }

}
?>
