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

######### textfile driver ###############
class FindMyNearest_textfile extends FindMyNearest {
    
    var $codepoints;          // array of post code centroids
     
    function FindMyNearest_textfile ($params) {
      /* params for this driver are:
        datafile: location of the datafile
        fieldsep: field separator used in the datafile. Default is "\t" (tab)
      */
      $this->_dbparams = $params;
      return true;
    }
    
    function loaddata() {
    /* 
    Datafile is a delimited text file of either
    postcode east north
    or
    postcode lat long
    */
    $datafile = $this->_dbparams['datafile'];
    $fieldsep = empty($this->_dbparams['fieldsep'])?"\t":$this->_dbparams['fieldsep'];
    
    if($fp = @fopen($datafile, "r")){
      flock($fp, "1");

      while (!feof ($fp)) {
        $line = fgets($fp, 4096);
        $line = trim($line);
        if ( strpos($line, '#') == 1 ) { continue; }
        if (strlen($line) > 0) {
          /* Note: if using WGS84 Lat is loaded as e and Long as n */
          list($code, $e, $n) = explode($fieldsep, $line);
          $this->codepoints[$code]['e'] = $e;
          $this->codepoints[$code]['n'] = $n;
        }
      }

      fclose($fp);
      return true;
    } else {       
     
      ### TODO: Pear error handling
      $this->lasterr = "Failed to open $datafile";
      return false;
    }  
  }
  
  function getgeodata ($postcode) {
    /* returns an array containing:
     [0] Eastings or Latitude
     [1] Northings or Longtitude */
    return array($this->codepoints[$postcode]['e'], $this->codepoints[$postcode]['n']);
  }
  
  function _postcodeknown($postcode) {
    return (isset($this->codepoints[$postcode]));
  }
  
  function dumpdata() {
    print_r($this->codepoints);
  }  
}

?>