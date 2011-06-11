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

// WebServices.php contains helper functions for drivers which access
// data via a webservice
require_once dirname(__FILE__) . '/WebServices.php';

######### uk-postcodes.com driver ###############
class FindMyNearest_openlylocal extends FindMyNearest_WebServices {
    
  var $baseurl = "http://www.openlylocal.com/areas/postcodes/";
  var $ext = ".xml";
  var $codecache;
  var $cachefile = "openlylocalcache";
  var $cachettl = 604800;    // one week
  
  function FindMyNearest_openlylocal($params) {
    /* params for openlylocal driver: 
      cachefile: path to file for cache
      cachettl: time to live in seconds for cache entries
    */
    if ($params['cachefile']) $this->cachefile = $params['cachefile'];
    if (preg_match('/^\d+$/', $params['cachettl'])) $this->cachettl = $params['cachettl'];
    $this->inoutsep = ' ';
    $this->geotype = 'wgs84';
    return true;
  }

  // override as no osgb36 available
  function use_osgb36 () {
    return false;
  }

  function _fetchcodedata($postcode) {
    if (!preg_match('/^([A-Z]{1,2})([0-9][0-9A-Z]?)\s?([0-9O])([A-Z]{2})$/', $postcode)) {
      $this->lasterr = "Invalid postcode format $postcode";
      return false;
    }
    if (!isset($this->codecache[$postcode]) || $this->codecache[$postcode]['timestamp'] < time() - $this->cachettl){
      // print "Server lookup for $postcode \n";
      $url = $this->baseurl . rawurlencode(strtoupper($postcode)) . $this->ext;

      $page = $this->_hitserver($url);
      if ($page['errno']) {
        $this->lasterr =  "Failed to retrieve data from server: " . $page['errmsg'];
        return false;
      }

      if ($page['http_code'] == '200') {
        if (preg_match("/Couldn't find postcode/", $page['content'])) {
          $this->lasterr = "Unknown postcode";
          return false;
        }
        require_once 'XML/Unserializer.php';
        // Array of options
        $unserializer_options = array(); 

        // Instantiate the unserializer
        $Unserializer = new XML_Unserializer($unserializer_options);
        $status = $Unserializer->unserialize($page['content'], FALSE);

        // Check whether serialization worked
        if (PEAR::isError($status)) {
          $this->lasterr = "Failed to decode XML:" . $status->getMessage() ;
          return false;
        }

        $data = $Unserializer->getUnserializedData();
        //print_r($data);
        if ($this->samepostcode($data['code'], $postcode)) {
           $this->codecache[$postcode]['wgs84'] = array($data['lat'], $data['lng']);
           //$this->codecache[$postcode]['osgb36'] = array($data['geo']['easting'], $data['geo']['northing']);
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
      return $this->codecache[$postcode];
    } else {
      return false;
    }
  }

}
?>