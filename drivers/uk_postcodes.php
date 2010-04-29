<?php

######### uk-postcodes.com driver ###############
class FindMyNearest_uk_postcodes extends FindMyNearest {
    
  var $baseurl = "http://www.uk-postcodes.com/postcode/";
  var $ext = ".xml";
  var $codecache;
  var $cachefile = "ukpcache";
  var $cachettl = 604800;    // one week
  
  function FindMyNearest_uk_postcodes($params) {
    /* params for uk-postcode driver: 
      cachefile: path to file for cache
      cachettl: time to live in seconds for cache entries
    */
    if ($params['cachefile']) $this->cachefile = $params['cachefile'];
    if (preg_match('/^\d+$/', $params['cachettl'])) $this->cachettl = $params['cachettl'];
    $this->inoutsep = '';
    return true;
  }
  
  function loaddata() {
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
  
  function getgeodata($postcode) {
    return $this->_fetchcodedata($postcode);
  }
  
  function _postcodeknown($postcode) {
    if($this->_fetchcodedata($postcode)) {
      return true;
    } else {
      return false;
    }
  }
  
  function _fetchcodedata($postcode) {
    if (!preg_match('/^([A-Z]{1,2})([0-9][0-9A-Z]?)([0-9O])([A-Z]{2})$/', $postcode)) {
      $this->lasterr = "Invalid postcode format $postcode";
      return false;
    }
    if (!isset($this->codecache[$postcode]) || $this->codecache[$postcode]['timestamp'] < time() - $this->cachettl){
      // print "Server lookup for $postcode \n";
      $url = $this->baseurl . strtoupper($postcode) . $this->ext;

      $page = $this->_hitserver($url);
      if ($page['errno']) {
        $this->lasterr =  "Failed to retrieve data from server: " . $page['errmsg'];
        return false;
      }

      if ($page['http_code'] == '200') {
        
        require_once 'XML/Unserializer.php';
        // Array of options
        $unserializer_options = array(); 

        // Instantiate the unserializer
        $Unserializer = &new XML_Unserializer($unserializer_options);
        $status = $Unserializer->unserialize($page['content'], FALSE);

        // Check whether serialization worked
        if (PEAR::isError($status)) {
          $this->lasterr = "Failed to decode XML:" . $status->getMessage() ;
          return false;
        }

        $data = $Unserializer->getUnserializedData();  
        //print_r($data);
        if ($data['postcode'] == $postcode) {
           $this->codecache[$postcode]['wgs84'] = array($data['geo']['lat'], $data['geo']['lng']);
           $this->codecache[$postcode]['osgb36'] = array($data['geo']['easting'], $data['geo']['northing']);
           $this->codecache[$postcode]['timestamp'] = time();
        }
        else {
          $this->lasterr = "Unexpected error in response";
          return false;
        }
      } else {
        $this->lasterr = "Failed to retrieve data from server: http error " . $page['http_code'];
        return false;
      }

    }
    if (isset($this->codecache[$postcode][$this->geotype])) {
      return $this->codecache[$postcode][$this->geotype];
    } else {
      return false;
    }
  }
  
  function dumpdata() {
    print_r($this->codecache);
  }

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

   function __destruct() {
       //print "Destroying FindMyNearest_uk_postcodes\n";
       $serial = serialize($this->codecache);
       file_put_contents($this->cachefile, $serial);
   }
}
?>