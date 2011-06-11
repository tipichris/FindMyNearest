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

######### geopostcode.org.uk driver ###############
class FindMyNearest_geopostcode extends FindMyNearest_WebServices {
    
  var $baseurl = "http://www.geopostcode.org.uk/api/";
  var $ext = "";
  var $codecache;
  var $cachefile = "geopostcodecache";
  var $cachettl = 604800;    // one week

  function FindMyNearest_geopostcode($params) {
    /* params for geopostcode driver: 
      cachefile: path to file for cache
      cachettl: time to live in seconds for cache entries
    */
    if ($params['cachefile']) $this->cachefile = $params['cachefile'];
    if (preg_match('/^\d+$/', $params['cachettl'])) $this->cachettl = $params['cachettl'];
    $this->inoutsep = ' ';
    $this->geotype = 'osgb36';
    return true;
  }

  function _fetchcodedata($postcode) {
    if (!isset($this->codecache[$postcode]) || $this->codecache[$postcode]['timestamp'] < time() - $this->cachettl){
      //print "Server lookup for $postcode \n";
      $url = $this->baseurl . rawurlencode(strtoupper($postcode)) . $this->ext;

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
        $Unserializer = new XML_Unserializer($unserializer_options);
        $status = $Unserializer->unserialize($page['content'], FALSE);

        // Check whether serialization worked
        if (PEAR::isError($status)) {
          $this->lasterr = "Failed to decode XML:" . $status->getMessage() ;
          return false;
        }

        $data = $Unserializer->getUnserializedData();

/*      $data = json_decode($page['content']);
        //print_r($data);
        $this->codecache[$postcode]['wgs84'] = array($data->{'wgs84'}->{'lat'}, $data->{'wgs84'}->{'lon'});
        $this->codecache[$postcode]['osgb36'] = array($data->{'osgb36'}->{'east'}, $data->{'osgb36'}->{'north'});
*/

        $this->codecache[$postcode]['wgs84'] = array($data['wgs84']['lat'], $data['wgs84']['lon']);
        $this->codecache[$postcode]['osgb36'] = array($data['osgb36']['east'], $data['osgb36']['north']);
        $this->codecache[$postcode]['timestamp'] = time();
        $this->codecache[$postcode]['match'] = $data['code'];

        if (!$this->samepostcode($data['code'], $postcode)) {
          $this->codecache[$data['code']]['wgs84'] = array($data['wgs84']['lat'], $data['wgs84']['lon']);
          $this->codecache[$data['code']]['osgb36'] = array($data['osgb36']['east'], $data['osgb36']['north']);
          $this->codecache[$data['code']]['timestamp'] = time();
          $this->codecache[$data['code']]['match'] = $data['code'];
        }
      } else {
        $this->lasterr = "Failed to retrieve data from server: http error " . $page['http_code'];
        return false;
      }

    }
    if (isset($this->codecache[$postcode])) {
      return $this->codecache[$postcode];
    } else {
      return false;
    }
  }

  function validcode($in_postcode, $exactmatch = false ) {
    /*
    Override parent function to make use of the fact that 
    geopostcode.org.uk returns the same fuzzy matches we do
    (because it uses FindMyNearest :))
    */

    if (! $normalised = $this->normalise($in_postcode) ) {
      return false;
    }
    $codedata = $this->_fetchcodedata($normalised);

    if (empty($codedata)) {
      $this->lasterr = "'$in_postcode' is not a known postcode";    
      return false;
    }

    if (!empty($exactmatch) && (!$this->samepostcode($codedata['match'], $normalised))) {
      $this->lasterr = "'$in_postcode' is not a known postcode";    
      return false;
    }

    return $codedata['match'];
  }

}
?>