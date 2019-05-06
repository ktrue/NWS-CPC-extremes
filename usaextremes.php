<?php
############################################################################
#
#   Project:    USA Extremes
#   Module:     usaextremes.php
#   Purpose:    Provides USA Extremes for Web page display
#   Authors:    Michael (michael@relayweather.com)
#               Ken True (webmaster@saratoga-weather.org) V2.01, V2.02, V4+
############################################################################
#      Usage:  Place the following on your webpage
#      CHMOD 666 for cacheFile2.php
#      include_once('usaextremes.php');
#      
#      Then call the following tags within the page where you would like them displayed:
#      $usahigh
#      $usalow
#      $usaprecip 
#      $reportDate (nicely formatted date of the report)
#
############################################################################
// version
// Version 2.00 - 14-Sep-2010 - initial release
// Version 2.01 - 21-Sep-2012 - adapted for WeatherUnderground use - K. True
// Version 2.02 - 23-Aug-2016 - adapted for www.wpc.ncep.noaa.gov - K. True
//
// Version 4.00 - 11-Sep-2016 - rewrite to use new URL:
//       http://www.cpc.noaa.gov/products/analysis_monitoring/cdus/prcp_temp_tables/dly_glob1.txt
// Version 4.01 - 14-Sep-2016 - added fix for malformed data record
// Version 4.02 - 17-Sep-2016 - added fixes for more malformed data records
// Version 4.03 - 13-Oct-2016 - added IgnoreStations feature
// Version 4.03c - 06-Feb-2019 - use https for www.cpc.noaa.gov
// Version 4.03d - 07-Feb-2019 - add curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0); for bad cert
// Version 4.03e - 09-Feb-2018 - corrected url to www.cpc.ncep.noaa.gov

$usaextremesverion = "4.03e";
/////////////////////////////////////////////////////////////////////////////
//SETTINGS START HERE////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////
// Site to Parse
$url2 = "https://www.cpc.ncep.noaa.gov/products/analysis_monitoring/cdus/prcp_temp_tables/dly_glob1.txt";
// Name of cache file  --  This file must be set to CHMOD-666
$cacheFileDir = './'; // directory to store cache file in.
$cacheFile2 = "usaextremesCache4.txt";  
// Age of cache file before re-fetch caching time, in seconds (3600 = 1 hour)
$cache_life = '3600';
$reportDateFormat = "l, F j, Y"; // Day, Month d, yyyy 
$tUOM = '&deg;F'; // or ='' for no temperature unit display
$rUOM = 'in';     // or ='' for no rain unit display
// optional: use $ignoreStations to exclude stations with problems
// $ignoreStations = array('99NRB','99HSE'); // Station numbers to ignore, Array of station numbers
//
// for lower-48 only, exclude Alaska, Hawaii and Puerto Rico stations
$ignoreStations = array(' AK ', ' HI ', ' PR '); // Station numbers to ignore, Array of station names
/////////////////////////////////////////////////////////////////////////////
//END SETTINGS///////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////
if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
   //--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');

   readfile($filenameReal);
   exit;
}
echo "<!-- USA Extremes Script Version $usaextremesverion saratoga-weather.org -->\n";
 
global $SITE;
if (isset($SITE['cacheFileDir']))   {$cacheFileDir = $SITE['cacheFileDir']; }
$cacheFile2 = $cacheFileDir . $cacheFile2;

if (file_exists($cacheFile2)) {
    $filemtime = filemtime($cacheFile2);
    $filesize = filesize($cacheFile2);
    if (0 == $filesize){
        $filemtime = 0;
    }
} else {
    $filemtime = 0;
}   
//   open the cache file and write the new data and then close the file.
$forceRefresh = (isset($_REQUEST['force']))?true:false;
$current_time = time();
$cache_age = $current_time - $filemtime;
if ($forceRefresh or $cache_age >= $cache_life){
   print "<!-- fetching from '$url2' -->\n";
   $html2 = curl_get_contents($url2); 
   $fp2 = fopen($cacheFile2, 'w');
   fwrite($fp2, $html2);
   fclose($fp2);
   echo "<!-- The cache life HAS expired and fresh data re-wrote the cache file -->\n";
} else {
   echo "<!-- The cache life HAS NOT expired and fresh data was not written to the cache file -->\n";
// Open the cache file, read it, then close it   
    $handle2 = fopen($cacheFile2, "r");
    $filesize = filesize($cacheFile2);
    $html2 = fread($handle2, $filesize);
    fclose($handle2);
}

function curl_get_contents($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:19.0) Gecko/20100101 Firefox/19.0");
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
}

/* looking for:

*****NOTE***************NOTE*********************NOTE************************
FILE FORMAT:  FIRST RECORD IS DATE IN THE FORM YYYYMMDD. THE REMAINING RECORDS
 CONTAIN MAXT(F),MINT(F),REPORTED AND ESTIMATED PRECIP IN HUNDRETHS OF INCHES,
 9 WEATHER CHARACTERS,ID AND CITY NAMES WHERE AVAILABLE. 
*****NOTE***************NOTE*********************NOTE********************
                         20160910
---  0---|--- 10---|--- 20---|--- 30---|--- 40---|--- 50---|--- 60---|--- 70---|--- 80---|--- 90---|
123456789|123456789|123456789|123456789|123456789|123456789|123456789|123456789|123456789|123456789|
  37  32    1    1 9DSSS////70026 AK BARROW/W.POST W.ROGERS
  41  34   20   20 9R/RRSSR/70086 AK BARTER ISLAND/DEW STN 
  46  39    2    2 9MMM/RRRM70104 AK CAPE_LISBURNE(AWOS)   
  44  41    1    1 9/D/RMDDD70117 AK TIN_CITY_AFS_(AWOS)   
  51  46    0    0 9////////70133 AK KOTZEBUE/RALPH WIEN ME
  55  33    0    0 9----////70173 AK INDIAN_MTN_AFS_AWOS   
  90  75    0    0 8/T-//TTT78514 PR RAMEY AFB             
  90  78   15   15 8/////RR/78526 PR SAN JUAN              
  90  76    0    0 8///R/RRR78535 PU ROOSEVELT_ROADS_NAS   
  89  81  122  122 8-/-/-/--78543 IS CHARLOTTE AMALIE VIRGI
  84  74    1    1 8////////91066 HI MIDWAY ISLAND NAVAL AI
  88  73    0    0 8////////91162 HI KEKAHA, PACIFIC MISSIL
  87  78    0    0 8//R/////91165 HI LIHUE                 
  79  68    8    8 8///RR//R91170 HI WHEELER               
  83  75   12   12 8/////RRR91176 HI KANEOHE               
  88  73    0    0 8////////91178 HI BARBERS_PT_NAS/OAHU   
  86  73    4    4 8///R////91182 HI HONOLULU              
  87  73    0    0 8///////R91186 HI MOLOKUI               
  88  73    1    1 8///R////91190 HI KAHULUI               
  84  70    5    5 8/R/RRR//91285 HI HILO/LYMAN_FIELD      
  91  73    0    0 8//MS////9911R TX BRENHAM, BRENHAM MUNIC


*/

$usahighStation = '';
$usahighValue = -999;
$usalowStation = '';
$usalowValue = 999;
$usaprecipStation = '';
$usaprecipValue = 0;
$usaStations = array();
$reportDate = '';

list($headers2,$content2) = explode("\r\n\r\n",$html2);

$rawrecs = explode("\n",$content2); // process the file

$idx=0;
foreach ($rawrecs as $n => $rec) {
	if($reportDate == '' and preg_match('/^\s+(\d+)$/',$rec,$matches)) {
		$reportDate = gmdate("l, F j, Y",strtotime($matches[1].'T1200'));
		print "<!-- reportDate='$reportDate' -->\n";
		continue;
	}
	if(strlen($rec) < 30) { continue; }
	if(check_ignore_station($rec,$ignoreStations)) { 
	  //print "<!-- ignored $rec -->\n";
	  continue;
    }
	$recmarker = substr($rec,19,1);
	if($recmarker == '9' or $recmarker == '8') {
		list($tHigh,$tLow,$tPrecip,$tPrecipEstim,$tData,$tState,$tStation) = 
		  sscanf($rec,' %d %d %d %d %s %s %[^\n]s');
		$tStation = strtolower( trim($tStation) );
		$tStation = str_replace('/','| ',$tStation);
		
		$tStation = ucwords( str_replace('_',' ', $tStation) );
		$tStation = str_replace('| ','/',$tStation);
		if(isset($_REQUEST['debug'])) {
		  print "<!-- rec='$rec' -->\n";
          print "<!-- tHigh='$tHigh' tLow=$tLow, tPrecip='$tPrecip', tPrecipEstim='$tPrecipEstim',";
		  print " tData='$tData', tState='$tState', tStation='$tStation' -->\n";
		}
		if(preg_match('|STATION|i',$tState)) {
			if(isset($_REQUEST['debug'])) {
				print "<!-- OMITTED -- no station name -->\n";
			}
			continue;
		}
		$usaStations[$idx] = join("\t",array($tHigh,$tLow,$tPrecip,$tPrecipEstim,$tData,$tState,$tStation));
		$idx++;
	}
	
}

// now scan for the highs/lows 
if (count($usaStations) > 0) {
  $usahigh = '';
  $usalow = '';
  $usaprecip = '';
} else {
  $usahigh = 'N/A';
  $usalow = 'N/A';
  $usaprecip = 'N/A';
}

foreach ($usaStations as $n => $vals) {
	list($tHigh,$tLow,$tPrecip,$tPrecipEstim,$tData,$tState,$tStation) = explode("\t",$vals);
	if($tHigh < -90 or $tLow < -90 ) { continue; }
	
	if($tHigh > $usahighValue) {
		if(isset($_REQUEST['debug'])) {
			print "<!-- new HIGH: '$tHigh' $n - $tState $tStation -->\n";
		}
		$usahighValue = $tHigh;
		$usahighStation = $n; // remember for later
	}
	if($tLow < $usalowValue) {
		if(isset($_REQUEST['debug'])) {
			print "<!-- new LOW: '$tLow' $n - $tState $tStation -->\n";
		}
		$usalowValue = $tLow;
		$usalowStation = $n; // remember for later
	}
	if($tPrecip > $usaprecipValue) {
		if(isset($_REQUEST['debug'])) {
			print "<!-- new Precip: '$tPrecip' $n - $tState $tStation -->\n";
		}
		$usaprecipValue = $tPrecip;
		$usaprecipStation = $n;
		
	}
	
}
if(isset($_REQUEST['debug'])) {

  print "<!-- usahighValue=$usahighValue station=$usahighStation ".$usaStations[$usahighStation]." -->\n";
  print "<!-- usalowValue=$usalowValue station=$usalowStation ".$usaStations[$usalowStation]." -->\n";
  print "<!-- usaprecipValue=$usaprecipValue station=$usaprecipStation ".$usaStations[$usaprecipStation]." -->\n";
}

// now pass through to see if any duplicate high, low, high precip exists and format the strings
foreach ($usaStations as $n => $vals) {
	list($tHigh,$tLow,$tPrecip,$tPrecipEstim,$tData,$tState,$tStation) = explode("\t",$vals);
	if($tHigh < -90 or $tLow < -90 ) { continue; }
	
	if($tHigh == $usahighValue) {
		$usahigh .= "<b>$tHigh$tUOM at $tStation, $tState</b>\n";
	}
	if($tLow == $usalowValue) {
		$usalow .= "<b>$tLow$tUOM at $tStation, $tState</b>\n";
	}
	if($tPrecip == $usaprecipValue and $tPrecip > 0) {
		$tPrecipFmt = sprintf("%01.2f",$tPrecip/100);
		$usaprecip .= "<b>$tPrecipFmt$rUOM at $tStation, $tState</b>\n";
	}
}
// Make results HTML pretty

$usahigh   = str_replace("\n","<br/>\n",$usahigh);
$usalow    = str_replace("\n","<br/>\n",$usalow);
$usaprecip = str_replace("\n","<br/>\n",$usaprecip);

print "<!-- usahigh  ='$usahigh' -->\n";
print "<!-- usalow   ='$usalow' -->\n";
print "<!-- usaprecip='$usaprecip' -->\n";
print '<!-- $filemtime = '.$filemtime.' $cache_age = '.$cache_age.' seconds.' . " -->\n";
print "<!-- Cache refresh rate = $cache_life seconds.  Cache age = $cache_age seconds. -->\n";



function check_ignore_station($str, array $arr)
{
    foreach($arr as $a) {
        if (stripos($str,$a) !== false) {return true;}
    }
    return false;
}


?>