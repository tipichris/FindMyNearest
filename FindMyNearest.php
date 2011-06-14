<?php

/**
 * This file is part of FindMyNearest, a PHP class for working with UK
 * postcodes.
 *
 * Copyright 2010 - 2011 Chris Hastie (http://www.oak-wood.co.uk/)
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


class FindMyNearest {
  
  var $VERSION = '0.5rc4';
 
  var $lasterr = '';        // the last error
  var $grain = 4;           // smallest checked unit (see setgrain)
  var $inoutsep = ' ';      // sepearator between outward and inward parts in data
  var $geotype = 'osgb36';  // alternatively wgs84
  
  function &factory($driver, $params = array())
  {
    /*
    Create in an instance of the required subclass
    $driver is the driver for data, currently
    either 'textfile', 'mysql', 'uk_postcodes', 'ukgeocode'
    or 'openlylocal'
    $params is an associative array of values required
    by the selected driver
    */
    $driver = basename($driver);
    require_once dirname(__FILE__) . '/drivers/' . $driver . '.php';
    $class = 'FindMyNearest_' . $driver;
    if (class_exists($class)) {
        $fmn = new $class($params);
    } else {
        $fmn = false;
    } 

    return $fmn;
  }


  /* #### stub functions provided by driver ##### */
  
  /* 
  given a postcode, returns the geospatial
  data about that postcode. Returns an array
  of (east, north) for osgb36, or (lat, long)
  for wgs84 
  */
  function getgeodata($postcode) {}
  
  /* debugging only */
  function dumpdata() {}
  
  /* 
  inititialise the database. eg for 'textfile', read
  parse and load the textfile. For 'mysql' set up the
  database connection 
  */
  function loaddata() { }
  
  /*
  returns true if the given postcode is known in the database,
  false otherwise. Used internally by validcode()
  */
  function _postcodeknown($postcode) {}

  /* ##### End of stub functions #### */
  
  function set_grain($grain) {
    /* 
    $grain dictates the smallest unit that is checked:
    1: Area only: SW 
    2: District: SW1A
    3: Sector: SW1A 1
    4: Unit: SW1A 1AA
    */  
    $this->grain = $grain;
  }
  
  function set_inoutsep ($sep) {
    /* 
    sets the separator used
    between outward and inward parts
    of the post code in the datafile.
    Default is a single space. Some 
    data use no separator - this is not 
    recommended because it is ambiguous
    */
    $this->inoutsep = $sep;
  }
  
  function use_wgs84 () {
    $this->geotype = 'wgs84';
  }

  function use_osgb36 () {
    $this->geotype = 'osgb36';
  }

  function validcode($in_postcode, $exactmatch = false ) {
    /*
    Checks if a postcode is known in some way
    in the database. First looks for full code, then 
    sector, then district, then area.
    Returns the code found on success, 0 on failure
    If $exactmatch is true, will match only the normalised
    postcode passed
    */

    if ($exactmatch) {
      if (! $normalised = $this->normalise($in_postcode) ) {
        return false;
      }
      $try = array($normalised);
    } else {
      if (! $postcode = $this->splitpostcode($in_postcode)) {
        return false;
      }    
      $try = array($postcode[0] . $postcode[1] . $this->inoutsep . $postcode[2] . $postcode[3],
        $postcode[0] . $postcode[1] . $this->inoutsep . $postcode[2],
        $postcode[0] . $postcode[1],
        $postcode[0]
      );

      if ($this->grain < 4) { array_shift($try); } 
      if ($this->grain < 3) { array_shift($try); } 
      if ($this->grain < 2) { array_shift($try); } 
    }
    
    foreach ($try as $thistry) {
      if ($this->_postcodeknown($thistry)) {
        return $thistry;
      }
    }
    
    ### TODO Pear error stuff
    $this->lasterr = "'$in_postcode' is not a known postcode";    
    return false;
  }

  function normalise($in_postcode) {
    if (! $postcode = $this->splitpostcode($in_postcode)) {
      return false;
    }
    $normalised = $postcode[0];
    if (!empty($postcode[1]) || $postcode[1] === '0') {
      $normalised .= $postcode[1];
      if (!empty($postcode[2]) || $postcode[2] === '0') {
        $normalised .= $this->inoutsep;
        $normalised .= $postcode[2];
        if (!empty($postcode[3])) {
          $normalised .= $postcode[3];
        }
      }
    }
    return $normalised;
  }

  function splitpostcode ($in_postcode) {
    /* 
    take a postcode and attempt to split it
    into component parts. Returns an array
      [0] => Area,
      [1] => District
      [2] => Sector
      [3] => Unit

    eg, for SW1A 1AA
      [0] => SW
      [1] => 1A
      [2] => 1
      [3] => AA

    returns false for unrecognised postcode formats
    */

    $postcode = trim($in_postcode);
    $postcode = strtoupper($postcode);  

    $area = '';
    $district = '';
    $sector = '';
    $unit = '';

    // full postcode, including typos with letter O as sector
    if (preg_match('/^([A-Z]{1,2})([0-9][0-9A-Z]?)\s*([0-9O])([A-Z]{2})$/', $postcode, $matches)) {         
      $area = $matches[1];
      $district = $matches[2];
      $sector = $matches[3];
      $unit = $matches[4];
    }
    
    // district only (potentially also (mis)matches if down to sector, single digit
    // district code and no space included, as this is ambiguous)
    elseif (preg_match('/^([A-Z]{1,2})([0-9][0-9A-Z]?)$/', $postcode, $matches)) {
      $area = $matches[1];
      $district = $matches[2];
    }
    
    // area only
    elseif (preg_match('/^([A-Z]{1,2})$/', $postcode, $matches)) {
      $area = $matches[1];    
    }
    
    // down to sector, unambiguous because of space
    elseif (preg_match('/^([A-Z]{1,2})([0-9][0-9A-Z]?)\s+([0-9O])$/', $postcode, $matches)) {
      $area = $matches[1];
      $district = $matches[2];
      $sector = $matches[3];    
    }  
    
    // probably down to sector, ambiguous without space
    elseif (preg_match('/^([A-Z]{1,2})([0-9][0-9A-Z]?)([0-9O])$/', $postcode, $matches)) {
      $area = $matches[1];
      $district = $matches[2];
      $sector = $matches[3];    
    } 
    
    // doesn't look like it's a valid postcode
    else {
      $this->lasterr = "'$in_postcode' is not a recognised postcode format";
      return false;
    }

    // handle letter O in sector
    $sector = str_replace("O", "0", $sector);

    return array($area, $district, $sector, $unit);
  }
  
  function samepostcode($a, $b) {
    $aparts = $this->splitpostcode($a);
    $bparts = $this->splitpostcode($b);
    $diff = array_diff($aparts, $bparts);
    return empty($diff)?true:false;
  }

  function calc_distance ($from, $to, $options = array('unit' => 'mile', 'decimal' => 0)) { 
    /* 
    Calculate the distance between two postcodes.
    $options is an optional array of options, currently supporting:
      unit: either 'mile' or 'km'
      decimal: an integer representing the number of decimal places
    BEWARE: Returns false on error. 
    0 is a valid, good response 
    indicating the distance is 
    0 miles / km 
    */
    $dec = 0;
    if (preg_match("/^\d{1,2}$/", $options['decimal'])) {
      $dec = $options['decimal'];
    }
    if (!$from_postcode = $this->validcode($from)) {
      return false;    
    }
    
    if (!$to_postcode = $this->validcode($to)) {
      return false;
    }
    
    $to_geodata = $this->getgeodata($to_postcode);
    $from_geodata = $this->getgeodata($from_postcode);

    if ($this->geotype == 'wgs84') {        // Longtitude and latitude
      $distance = $this->_gcdistance($to_geodata, $from_geodata);
    } else {                                // OS grid reference
      $distance = $this->_griddistance($to_geodata, $from_geodata);
    }
    
    $km = sprintf("%." . $dec . "f", $distance / 1000);
    $miles = sprintf("%." . $dec . "f", $distance / 1609);
    
    if (isset($options['unit']) && $options['unit'] == 'km') {
      return $km;
    } else {
      return $miles;
    }
  } 
  
  // great circle distance in meters
  function _gcdistance ($geo1, $geo2) {
    list ($lat1, $lon1) = $geo1;
    list ($lat2, $lon2) = $geo2;
    return (6372795*3.1415926*sqrt(($lat2-$lat1)*($lat2-$lat1) + cos($lat2/57.29578)*cos($lat1/57.29578)*($lon2-$lon1)*($lon2-$lon1))/180);
  }
  
  // distance by national grid in meters
  function _griddistance ($geo1, $geo2) {
    list ($e1, $n1) = $geo1;
    list ($e2, $n2) = $geo2;
    return sqrt( (($e1 - $e2)*($e1 - $e2)) + (($n1 - $n2)*($n1 - $n2)) );
  }
  
  // returns the last error generated by the class
  function lasterror() {
    return $this->lasterr;
  }

}

?>