<?php

/*
 +-----------------------------------------------------------------------+
 |                                                                       |
 | Copyright (c) 2012-2018 Alice Wonder Miscreations                     |
 |  May be used under terms of MIT license                               |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Purpose: HTML5 as XML page generation                                 |
 +-----------------------------------------------------------------------+
*/

namespace AliceWonderMiscreations\Utilities;

class html5domdoc {
  protected $dom;
  public $xmlHtml;
  public $xmlHead;
  public $xmlBody;
  protected $rtalabel = FALSE;
  protected $keywords = array();
  protected $description = '';
  protected $cspstring = '';
  protected $objectwhitelist = array('text/plain', 'text/html', 'image/webp', 'application/pdf', 'application/xhtml+xml');
  protected $xmlns = 'http://www.w3.org/1999/xhtml';
  protected $xmlLang = 'en';
  public  $expires = 0;

  /* any function that uses head / body should call this */
  protected function domNodes() {
    $this->xmlHtml = $this->dom->getElementsByTagName('html')->item(0);
    $this->xmlHead = $this->dom->getElementsByTagName('head')->item(0);
    $this->xmlBody = $this->dom->getElementsByTagName('body')->item(0);
  }

  /* Puts head elements in logical order, called by sendPage */
  protected function adjustHead() {
    $metaEquiv = array();
    $metaName  = array();
    $metaProperty = array();
    $links     = array();
    $scripts   = array();
    $misc      = array();
    $newHead = $this->dom->createElement('head');

    $children = $this->xmlHead->childNodes;
    foreach ($children as $child) {
      $newChild = $child->cloneNode(true);
      $tag = $newChild->tagName;
      switch ($tag) {
        case 'meta' :
          if ($newChild->hasAttribute('http-equiv')) {
            $equiv = $newChild->getAttribute('http-equiv');
            if (strcmp($equiv, 'X-Content-Security-Policy') === 0) {
              $newHead->appendChild($newChild);
            } else {
              $metaEquiv[] = $newChild;
            }
          } elseif($newChild->hasAttribute('name')) {
            $metaName[] = $newChild;
          } else {
            $metaProperty[] = $newChild;
          }
          break;
        case 'link' :
          $links[] = $newChild;
          break;
        case 'script' :
          $scripts[] = $newChild;
          break;
        case 'title' :
          $newTitle = $newChild;
          break;
        default :
          $misc[] = $newChild;
          break;
      }
    }

    $j = count($metaEquiv);
    for ($i=0; $i<$j; $i++) {
      $newHead->appendChild($metaEquiv[$i]);
    }

    $meta = $this->dom->createElement('meta');
      $meta->setAttribute('charset', 'UTF-8');
      $newHead->appendChild($meta);

    if ($this->rtalabel) {
      $meta = $this->dom->createElement('meta');
      $meta->setAttribute('name', 'RATING');
      $meta->setAttribute('content', 'RTA-5042-1996-1400-1577-RTA');
      $newHead->appendChild($meta);
    }

    $j = count($this->keywords);
    if ($j > 0) {
      $content = implode(',', array_unique($this->keywords));
      $meta = $this->dom->createElement('meta');
      $meta->setAttribute('name', 'keywords');
      $meta->setAttribute('content', $content);
      $newHead->appendChild($meta);
    }

    if (strlen($this->description) > 0) {
      $meta = $this->dom->createElement('meta');
      $meta->setAttribute('name', 'description');
      $meta->setAttribute('content', $this->description);
      $newHead->appendChild($meta);
    }

    $genstring = 'PHP ' . phpversion() . ' DOMDocument/libxml2 ' . LIBXML_DOTTED_VERSION;
    $meta = $this->dom->createElement('meta');
    $meta->setAttribute('name', 'generator');
    $meta->setAttribute('content', $genstring);
    $newHead->appendChild($meta);

    $j = count($metaName);
    for ($i=0; $i<$j; $i++) {
      $newHead->appendChild($metaName[$i]);
    }

    $j = count($metaProperty);
    for ($i=0; $i<$j; $i++) {
      $newHead->appendChild($metaProperty[$i]);
    }

    $j = count($links);
    for ($i=0; $i<$j; $i++) {
      //preload first
      $rel = $links[$i]->getAttribute('rel');
      if($rel == "preload")  {
        $newHead->appendChild($links[$i]);
      }
    }
    for ($i=0; $i<$j; $i++) {
      //stylesheet second
      $rel = $links[$i]->getAttribute('rel');
      if($rel == "stylesheet")  {
        $newHead->appendChild($links[$i]);
      }
    }
    for ($i=0; $i<$j; $i++) {
      //icon third
      $rel = $links[$i]->getAttribute('rel');
      if($rel == "icon")  {
        $newHead->appendChild($links[$i]);
      }
    }
    for ($i=0; $i<$j; $i++) {
      //shortcut icon fourth
      $rel = $links[$i]->getAttribute('rel');
      if($rel == "shortcut icon")  {
        $newHead->appendChild($links[$i]);
      }
    }
    for ($i=0; $i<$j; $i++) {
      //manifest fifth
      $rel = $links[$i]->getAttribute('rel');
		  if($rel == "manifest")  {
        $newHead->appendChild($links[$i]);
      }
    }
    for ($i=0; $i<$j; $i++) {
      //prefetch last
      $rel = $links[$i]->getAttribute('rel');
      if($rel == "prefetch")  {
        $newHead->appendChild($links[$i]);
      }
    }
    for ($i=0; $i<$j; $i++) {
      //canonical last
      $rel = $links[$i]->getAttribute('rel');
      if($rel == "canonical")  {
        $newHead->appendChild($links[$i]);
      }
    }
		
    $j = count($scripts);
    for ($i=0; $i<$j; $i++) {
      $newHead->appendChild($scripts[$i]);
    }

    $j = count($misc);
    for ($i=0; $i<$j; $i++) {
      $newHead->appendChild($misc[$i]);
    }

    if (! isset($newTitle)) {
      $newTitle = $this->dom->createElement('title', 'Page Title');
    }
    $newHead->appendChild($newTitle);

    $this->xmlHead->parentNode->replaceChild($newHead, $this->xmlHead);
  }

  protected function sanitizeBody() {
    $nodelist = $this->xmlBody->getElementsByTagName('script');
    $n = $nodelist->length;
    for($j = $n; --$j >= 0;) {
      $nodelist->item($j)->parentNode->removeChild($nodelist->item($j));
    }
    $nodelist = $this->xmlBody->getElementsByTagName('embed');
    $n = $nodelist->length;
    for($j = $n; --$j >= 0;) {
      $nodelist->item($j)->parentNode->removeChild($nodelist->item($j));
    }
    $nodelist = $this->xmlBody->getElementsByTagName('applet');
    $n = $nodelist->length;
    for($j = $n; --$j >= 0;) {
      $nodelist->item($j)->parentNode->removeChild($nodelist->item($j));
    }
    $nodelist = $this->xmlBody->getElementsByTagName('object');
    $n = $nodelist->length;
    for($j = $n; --$j >= 0;) {
      $node = $nodelist->item($j);
      $type = 'null';
      if($node->hasAttribute('type')) {
        $type = strtolower(trim($node->getAttribute('type')));
      }
      if(in_array($type, $this->objectwhitelist)) {
        $node->setAttribute('typemustmatch', 'typemustmatch');
      } else {
        $node->parentNode->removeChild($node);
      }
    }
  }
	
  protected function sanitizeTargetLinks() {
    $nodelist = $this->xmlBody->getElementsByTagName('a');
    $n = $nodelist->length;
    for($j = $n; --$j >= 0;) {
      $node = $nodelist->item($j);
      if($node->hasAttribute('target')) {
        $target = trim(strtolower($node->getAttribute('target')));
        if($target == "_blank") {
          if($node->hasAttribute('rel')) {
            $relString = preg_replace('/\s+/', ' ', trim(strtolower($node->getAttribute('rel'))));
            $relTags = explode(' ', $relString);
          } else {
            $relTags = array();
          }
          if(! in_array('noopener', $relTags)) {
            $relTags[] = 'noopener';
          }
          if(! in_array('noreferrer', $relTags)) {
            $href = strtolower($node->getAttribute('href'));
            $count = substr_count($href, 'dreamstime');
            if($count > 0) {
              $relTags[] = 'noreferrer';
            }
          }
          $relString = implode(' ', $relTags);
          $node->setAttribute('rel', $relString);
          if(! $node->hasAttribute('title')) {
            if($node->hasAttribute('href')) {
              $href = trim($node->getAttribute('href'));
              $tmp = preg_replace('/:\/\//', '', $href);
              if(strlen($tmp) < strlen($href)) {
                $node->setAttribute('title', '[Opens new window, external link]');
              } else {
                $node->setAttribute('title', '[Opens new window]');
              }
            }
          }
        }
      }
    }
  }
  
  protected function fixArticleLandmarks() {
    $articlelist = $this->xmlBody->getElementsByTagName('article');
    $nn = $articlelist->length;
    $x = new \DOMXPath($this->dom);
    for($ii = 0; $ii < $nn; $ii++) {
      $arnode = $articlelist->item($ii);
      $nodelist = $arnode->getElementsByTagName('aside');
      $n = $nodelist->length;
      for($i = 0; $i < $n; $i++) {
        $aside = $nodelist->item($i);
        if(! $aside->hasAttribute('role')) {
          $aside->setAttribute('role', 'region');
          if(! $aside->hasAttribute('aria-labelledby')) {
            if(! $aside->hasAttribute('aria-label')) {
              $count = 0;
              foreach($aside->childNodes as $child) {
                if(!($child instanceof \DomText)) {
                  $count++;
                }
              }
              if($count != 0) {
                $first = $x->query('*', $aside)->item(0);
                $tagname = $first->tagName;
                if(in_array($tagname, array ('h2', 'h3', 'h4', 'h5', 'h6'))) {
                  $labelid = "aside: " . trim($first->textContent);
                  $aside->setAttribute('aria-label', $labelid);
                }
              }
            }
          }
        }
      } // end for $i loop
      
      $hastoc = false;
      $nodelist = $arnode->getElementsByTagName('details');
      $n = $nodelist->length;
      for($i = 0; $i < $n; $i++) {
        $details = $nodelist->item($i);
        if($details->hasAttribute('class')) {
          $class = $details->getAttribute('class');
          if(strcmp($class, 'toc') == 0) {
            $details->setAttribute('role', 'navigation');
            $hastoc = true;
            if(! $details->hasAttribute('aria-labelledby')) {
              if(! $details->hasAttribute('aria-label')) {
                $details->setAttribute('aria-label', 'Table of Contents');
              }
            }
          }
        }
      } // end of for $i loop
      
      $nodelist = $arnode->getElementsByTagName('section');
      $n = $nodelist->length;
      for($i = 0; $i < $n; $i++) {
        $section = $nodelist->item($i);
        if(! $section->hasAttribute('role')) {
          if(! $hastoc) {
            $section->setAttribute('role', 'region');
          }
          if(! $section->hasAttribute('aria-labelledby')) {
            if(! $section->hasAttribute('aria-label')) {
              $count = 0;
              foreach($section->childNodes as $child) {
                if(!($section instanceof \DomText)) {
                  $count++;
                }
              }
              if($count != 0) {
                $first = $x->query('*', $section)->item(0);
                $tagname = $first->tagName;
                if($tagname == 'h2') {
                  $labelid = "section: " . trim($first->textContent);
                  $section->setAttribute('aria-label', $labelid);
                }
                elseif(in_array($tagname, array ('h3', 'h4', 'h5', 'h6'))) {
                  $labelid = "subsection: " . trim($first->textContent);
                  $section->setAttribute('aria-label', $labelid);
                }
              }
            }
          }
        }
      } // end for $i loop
      
    } // end for for $ii loop
    // fix header, main. footer
    $header = $this->dom->getElementsByTagName('header')->item(0);
    if(! is_null($header)) {
      if(! $header->hasAttribute('role')) {
        $header->setAttribute('role', 'banner');
      }
    }
    $main = $this->dom->getElementsByTagName('main')->item(0);
    if(! is_null($main)) {
      if(! $main->hasAttribute('role')) {
        $main->setAttribute('role', 'main');
      }
    }
    $footer = $this->dom->getElementsByTagName('footer')->item(0);
    if(! is_null($footer)) {
      if(! $footer->hasAttribute('role')) {
        $footer->setAttribute('role', 'contentinfo');
      }
    }
    $navlist = $this->dom->getElementsByTagName('nav');
    $j = $navlist->length;
    for($i = 0; $i < $j; $i++) {
      $nav = $navlist->item($i);
      if(! $nav->hasAttribute('role')) {
        $nav->setAttribute('role', 'navigation');
      }
    }
  } // end of function
	
  //sends the headers, called by sendPage
  protected function sendHeader($HTML, $status="") {
    $tstamp = time();
    $expires = $tstamp + $this->expires;
    if($status == "200") {
      header("HTTP/1.1 200 OK");
    }
    if(! strlen($this->cspstring)) {
      header('Content-Security-Policy: ' . $this->cspstring);
    }
    if ($this->rtalabel) {
      header('Rating: RTA-5042-1996-1400-1577-RTA');
    }
    if($HTML) {
      header('Content-Type: text/html; charset=utf-8');
    } else {
      header('Content-Type: application/xhtml+xml; charset=utf-8');
    }
    date_default_timezone_set('UTC');

    header('Last-Modified: ' . preg_replace('/\+0000$/', 'GMT', date('r', $tstamp)));
    header('Expires: ' . preg_replace('/\+0000$/', 'GMT', date('r', $expires)));
    if($this->expires == 0) {
      header('Cache-Control: private, no-cache, must-revalidate');
      header('Pragma: no-cache');
    }
    header_remove('X-Powered-By');
  }

  public function rtalabel() {
    $this->rtalabel = true;
  }

  public function whiteListObject($type) {
    $type = strtolower(trim($type));
    if(! in_array($type, $this->objectwhitelist)) {
      $this->objectwhitelist[] = $type;
    }
  }

  public function addKeywords($arg=array()) {
    if (is_array($arg)) {
      $this->keywords = array_merge($this->keywords, $arg);
    } else {
      $this->keywords[] = $arg;
    }
  }
	
  public function addOpenGraph($property, $content, $set='', $model='', $orig='') {
    if((strlen($property) * strlen($content)) == 0) {
      return FALSE;
    }
    if(substr($property, 0, 3) != 'og:') {
      $property = 'og:' . $property;
    }
    $set = trim($set);
    $model = trim($model);
    $orig = trim($orig);
    $meta = $this->dom->createElement('meta');
    $meta->setAttribute('property', $property);
    $meta->setAttribute('content', $content);
    if((strlen($set) * strlen($model)) != 0) {
      $meta->setAttribute('data-2257', $set);
      $meta->setAttribute('data-model', $model);
    }
    if(strlen($orig) > 0) {
      $meta->setAttribute('data-orig', $orig);
    }
    $this->xmlHead->appendChild($meta);
  }
	
  public function addTwitterCard($property, $content, $set='', $model='', $orig='') {
    if((strlen($property) * strlen($content)) == 0) {
      return FALSE;
    }
    if(substr($property, 0, 8) != 'twitter:') {
      $property = 'twitter:' . $property;
    }
    $set = trim($set);
    $model = trim($model);
    $orig = trim($orig);
    $meta = $this->dom->createElement('meta');
    $meta->setAttribute('name', $property);
    $meta->setAttribute('content', $content);
    if((strlen($set) * strlen($model)) != 0) {
      $meta->setAttribute('data-2257', $set);
      $meta->setAttribute('data-model', $model);
    }
    if(strlen($orig) > 0) {
      $meta->setAttribute('data-orig', $orig);
    }
    $this->xmlHead->appendChild($meta);
  }
	
  public function addDescription($desc) {
  	$this->description = $desc;
  }

  public function addStyleSheet($stylename, $serverpath, $fspath="") {
    $stylename = trim($stylename);
    $serverpath = trim($serverpath);
    $fspath = trim($fspath);
    $this->domNodes();
    if(strlen($fspath) > 0) {
      $fullpath = $fspath . $stylename;
      if(file_exists($fullpath)) {
        $modtime = filemtime($fullpath);
        $stylename = preg_replace('/\.css$/', '-' . $modtime . '.css', $stylename);
      }
    }
    $style = $this->dom->createElement('link');
    $style->setAttribute('rel', 'stylesheet');
    $style->setAttribute('type', 'text/css');
    $style->setAttribute('href', $serverpath . $stylename);	  
    $this->xmlHead->appendChild($style);
  }

  public function addJavaScript($scriptname, $serverpath, $fspath="") {
    $scriptname = trim($scriptname);
    $serverpath = trim($serverpath);
    $fspath = trim($fspath);
    $this->domNodes();
    if(strlen($fspath) > 0) {
      $fullpath = $fspath . $scriptname;
      if(file_exists($fullpath)) {
        $modtime = filemtime($fullpath);
        $scriptname = preg_replace('/\.js$/', '-' . $modtime . '.js', $scriptname);
      }
    }
    $script = $this->dom->createElement('script');
    $script->setAttribute('type', 'application/javascript');
    $script->setAttribute('src', $serverpath . $scriptname);
    $this->xmlHead->appendChild($script);
  }

  public function sendPage($HTML=FALSE, $status="") {
    $this->domNodes();
    $this->sanitizeBody();
    $this->sanitizeTargetLinks();
    $this->fixArticleLandmarks();
    $this->adjustHead();
    if($HTML) {
      $this->xmlHtml->setAttribute('lang', $this->xmlLang);
		} else {
      $this->xmlHtml->setAttribute('xmlns', $this->xmlns);
      $this->xmlHtml->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:lang', $this->xmlLang);
    }
    if($status == "200") {
      $this->sendHeader($HTML, "200");
    } else {
      $this->sendHeader($HTML);
    }
    $search[] = '/audio\/x-matroska;codecs=matroska/'; $replace[] = 'audio/x-matroska;codecs=aac';
    if($HTML) {
      $search[] = '/<\/source>/'; $replace[] = '';
      $search[] = '/<\/track>/'; $replace[] = '';
      $search[] = '/<\/script></'; $replace[] = "</script>\n<";
      print preg_replace($search, $replace, $this->dom->saveHTML());
    } else {
      $search[] = '/<default:/'; $replace[] = '<';
      $search[] = '/<\/default:/'; $replace[] = '</';
      print preg_replace($search, $replace, $this->dom->saveXML());
    }
  }

  public function html5domdoc($dom, $xmlLang="en") {
    $this->xmlLang = $xmlLang;
    $this->dom = $dom;
    $docstring = '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE html><html><head /><body /></html>';
    $this->dom->loadXML($docstring);
    $this->domNodes();
  }
}

?>