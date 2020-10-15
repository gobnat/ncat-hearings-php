<?php
// This is a template for a PHP scraper on morph.io (https://morph.io)
// including some code snippets below that you should find helpful
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';

//require 'scraperwiki.php';
require 'vendor/openaustralia/scraperwiki/scraperwiki.php';
require 'vendor/openaustralia/scraperwiki/simple_html_dom.php';
//require 'scraperwiki/simple_html_dom.php';
//
// // Read in a page
$html = scraperwiki::scrape("http://www.ncat.nsw.gov.au/Pages/going_to_the_tribunal/hearing_lists.aspx");
//
// Load the dom
$dom = new simple_html_dom();
$dom->load($html);

// Look through all tables and get an hyperlinks to venues.
// Currently only one table appears. Long term this will not be the case. Need a way to to properly grab only Consumer matters
foreach($dom->find('table tr td a') as $e){
  //store the Location, Link, Postcode
   $links[] = array(
     "location" => $e->innertext,
     "url" => $e->href,
     "postcode" => substr($e->href,-4)
   );
}

//Below is silly because PHP and dates is silly.
// Set timezone
date_default_timezone_set('Australia/Sydney');

//30 days cycle. eed start and end date
$startDate = new \DateTime(date('Y-m-d', time()));
$endDate = new \DateTime(date('Y-m-d', strtotime("+30 days")));
//Intervak for the loop
$interval = \DateInterval::createFromDateString('1 day');
$period = new \DatePeriod($startDate, $interval, $endDate);

//Loop through venues
foreach ($links as $link) {
  $url = $link["url"];
  $dom->load(scraperwiki::scrape($url));
  //Loop through each date and collect results
  foreach ($period as $date) {
    //Tell people what you are doing
    echo "Processing " . $date->format("d-m-Y") . " for " . $link["postcode"] . "<br>\n";
    //make the id used to find each hearing table
    $id = "dg" . $date->format("dmY");
    //Find and loop through each hearing table
    foreach($dom->find('table[id=' . $id . '] tr.clsGridItem') as $e){
      //This is a hack from the ruby scrapper to handle some inconsistent formatting on some pages.
      //May no longer bee needed - review in testing
      if (is_null($e->parent()->prev_sibling()->prev_sibling()->find('span'))) {
        $time_and_place = $e->parent()->prev_sibling()->innertext;
      }else {
        $time_and_place = $e->parent()->prev_sibling()->prev_sibling()->innertext;
      }
      //Need to split out time first with preg_match before using it in result array because... php... probably a better way to do this but I cant find it.
      preg_match('/(^.*(A|P)M)/', $time_and_place, $time_matches);

      //Create our NCAT case object/array
      $ncat_case = array(
        'unique_id'       => $date->format("d-m-Y") . $e->find('td')[0]->innertext,
        'case_number'     => $e->find('td')[0]->innertext,
        'party_a'         => $e->find('td')[1]->innertext,
        'party_b'         => $e->find('td')[2]->innertext,
        'date'            => $date->format("d-m-Y"),
        'time'            => $time_matches[0],
        'location'        => preg_split('/ at /', $time_and_place)[1],
        'venue'           => $link["location"],
        'venue_postcode'  => $link["postcode"]
      );

      // Write out to the sqlite database using scraperwiki library
      scraperwiki::save_sqlite(array($ncat_case['unique_id']), $ncat_case);
      //For testing locally print out result
      //print_r($ncat_case);
    } //each id
  } //end period
}//end link


// You don't have to do things with the ScraperWiki library.
// You can use whatever libraries you want: https://morph.io/documentation/php
// All that matters is that your final data is written to an SQLite database
// called "data.sqlite" in the current working directory which has at least a table
// called "data".

?>
