<?php
######### mysql driver ###############
class FindMyNearest_mysql extends FindMyNearest {
    
  var $db;
  var $codecache;
  
  function FindMyNearest_mysql ($params) {
    /* params for mysql driver:
      host: mysql host to connect to
      user: user name for mysql connection
      pass: password for mysql connection
      db:   database to use
      sql:  the SQL statement to execute. This should take a single substitution,
            %s, which is replaced by the postcode being searched. It should return 
            two fields, eastings and northings or latitude and logtitude. Defaults are
            "SELECT lat,long FROM postcodes WHERE postcode = '%s'" for wgs84 and
            "SELECT east,north FROM postcodes WHERE postcode = '%s' for osgb36
    */
    $this->_dbparams = $params;
    if (empty($this->_dbparams['sql'])){
      if ($this->geotype == 'wgs84') { 
        $this->_dbparams['sql'] = "SELECT lat,long FROM postcodes WHERE postcode = '%s'";
      } else {
        $this->_dbparams['sql'] = "SELECT east,north FROM postcodes WHERE postcode = '%s'";
      }
    }
    return true;
  }

  function loaddata() {
    /* initialise connection to MySQL server */
    if (! $this->db = @mysql_connect($this->_dbparams['host'], $this->_dbparams['user'], $this->_dbparams['pass'])) {
      $this->lasterr = "Failed to connect to SQL server: " . mysql_error();
      return false;
    }
    if (! @mysql_select_db($this->_dbparams['db'], $this->db)) {
      $this->lasterr = "Failed to select database " . $this->_dbparams['db'] . " "  . mysql_error();
      return false;
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
    if (!isset($this->codecache[$postcode])){
      //print "SQL lookup for $postcode \n";
      $sql = sprintf($this->_dbparams['sql'], mysql_escape_string($postcode));
      if (!$result = @mysql_query($sql, $this->db)) {
        $this->lasterr = "Failed to execute query: " . mysql_error();
        return false;
      }
      $row = @mysql_fetch_row($result);
      if (empty($row)) {
        return false;
      } else {
        $this->codecache[$postcode] = array($row[0], $row[1]);
      }
    }
    return $this->codecache[$postcode];
  }
  
  function dumpdata() {
    print_r($this->codecache);
  }
  
}

?>
