<?php

/**
 * This file is part of FindMyNearest, a PHP class for working with UK
 * postcodes.
 *
 * Copyright 2010 Chris Hastie (http://www.oak-wood.co.uk/)
 *
 * FindMyNearest is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * FindMyNearest is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with FindMyNearest.  If not, see <http://www.gnu.org/licenses/>.
 */

class FindMyNearest_WebServices extends FindMyNearest {
 /**
 * FindMyNearest_WebServices is not itself a driver, but provides a number
 * of common functions that can be used by drivers using a web service
 * backend.
 */

  var $codecache;
  var $cachefile = "fmncache";
  var $cachettl = 604800;    // one week
  
  function _hitserver( $url ) {
      $options = array(
          CURLOPT_RETURNTRANSFER => true,     // return web page
          CURLOPT_HEADER         => false,    // don't return headers
          CURLOPT_FOLLOWLOCATION => true,     // follow redirects
          CURLOPT_ENCODING       => "",       // handle all encodings
          CURLOPT_USERAGENT      => "FindMyNearest v" . $this->VERSION, // who am i
          CURLOPT_AUTOREFERER    => true,     // set referer on redirect
          CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
          CURLOPT_TIMEOUT        => 120,      // timeout on response
          CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
      );

      $ch      = curl_init( $url );
      curl_setopt_array( $ch, $options );
      $content = curl_exec( $ch );
      $err     = curl_errno( $ch );
      $errmsg  = curl_error( $ch );
      $header  = curl_getinfo( $ch );
      curl_close( $ch );

      $header['errno']   = $err;
      $header['errmsg']  = $errmsg;
      $header['content'] = $content;
      return $header;
  }
  
  function _loadcache() {
     if (!file_exists($this->cachefile)) {
       if (! @touch($this->cachefile)) {
         $this->lasterr = "Unable to create file " . $this->cachefile;
         return false;
       }
     }
     
     else {
       $serial = @file_get_contents($this->cachefile);
       if ($serial === false) {
         $this->lasterr = "Failed to read from cache file " . $this->cachefile;
         return false;
       }
       $this->codecache = unserialize($serial);
     }
     return true;
  }

  function _savecache() {
      //print "Destroying FindMyNearest_uk_postcodes\n";
      $serial = serialize($this->codecache);
      file_put_contents($this->cachefile, $serial);
  }

  function dumpdata() {
    print_r($this->codecache);
  }

  function loaddata() {
     return $this->_loadcache();
  }

  function getgeodata($postcode) {
    $codedata = $this->_fetchcodedata($postcode);
    if(!empty($codedata)) {
      if(isset($codedata[$this->geotype])) {
        return $codedata[$this->geotype];
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  function _postcodeknown($postcode) {
    if($this->_fetchcodedata($postcode)) {
      return true;
    } else {
      return false;
    }
  }

  function __destruct() {
    $this->_savecache();
  }

}

?>