<?php
 
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

$sql = array();



/*
$sql[] = "
CREATE TABLE {$wpdb->prefix}railtimetable_dates (
  id int(11) NOT NULL AUTO_INCREMENT,
  timetableid int(11) NOT NULL,
  date date NOT NULL,
  PRIMARY KEY (id),
  KEY date_index (date)
)
".$charset_collate;

*/


$sql[] = "
CREATE TABLE `wp_wc_railticket_bookable` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `daytype` varchar(10) NOT NULL,
  `allocateby` varchar(10) NOT NULL,
  `date` date NOT NULL,
  `timetableid` int(11) NOT NULL,
  `ttrevision` int(11) NOT NULL,
  `pricerevision` int(11) NOT NULL,
  `composition` text NOT NULL,
  `bays` text NOT NULL,
  `bookclose` text NOT NULL,
  `limits` text NOT NULL,
  `bookable` int(1) NOT NULL,
  `soldout` int(11) NOT NULL,
  `override` varchar(6) NOT NULL,
  `sameservicereturn` int(1) NOT NULL,
  `reserve` text NOT NULL,
  `sellreserve` int(1) NOT NULL,
  `specialonly` int(1) NOT NULL,
  `minprice` double NOT NULL,
  `discountexclude` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `date` (`date`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_booking_bays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bookingid` int(11) NOT NULL,
  `num` int(2) NOT NULL,
  `baysize` int(2) NOT NULL,
  `priority` int(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `bookingid` (`bookingid`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wooorderid` int(11) NOT NULL,
  `wooorderitem` int(11) NOT NULL,
  `woocartitem` varchar(50) NOT NULL,
  `manual` int(11) NOT NULL DEFAULT 0,
  `date` date NOT NULL,
  `time` varchar(8) NOT NULL,
  `fromstation` int(4) NOT NULL,
  `tostation` int(4) NOT NULL,
  `direction` varchar(4) NOT NULL,
  `seats` int(2) NOT NULL,
  `priority` int(1) NOT NULL,
  `usebays` int(1) NOT NULL,
  `collected` int(1) NOT NULL,
  `created` int(10) NOT NULL,
  `expiring` int(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `bookid` (`wooorderid`),
  KEY `cartitem` (`woocartitem`),
  KEY `created` (`created`),
  KEY `datetime` (`date`,`time`),
  KEY `manual` (`manual`),
  KEY `date` (`date`),
  KEY `fromstation` (`fromstation`),
  KEY `tostation` (`tostation`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_bookings_expired` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wooorderid` int(11) NOT NULL,
  `wooorderitem` int(11) NOT NULL,
  `woocartitem` varchar(50) NOT NULL,
  `manual` int(11) NOT NULL DEFAULT 0,
  `date` date NOT NULL,
  `time` varchar(8) NOT NULL,
  `fromstation` int(4) NOT NULL,
  `tostation` int(4) NOT NULL,
  `direction` varchar(4) NOT NULL,
  `seats` int(2) NOT NULL,
  `priority` int(1) NOT NULL,
  `usebays` int(1) NOT NULL,
  `collected` int(1) NOT NULL,
  `created` int(10) NOT NULL,
  `expiring` int(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `bookid` (`wooorderid`),
  KEY `cartitem` (`woocartitem`),
  KEY `created` (`created`),
  KEY `datetime` (`date`,`time`),
  KEY `manual` (`manual`),
  KEY `date` (`date`),
  KEY `fromstation` (`fromstation`),
  KEY `tostation` (`tostation`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_coachtypes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `capacity` int(3) NOT NULL,
  `maxcapacity` int(3) NOT NULL,
  `priority` int(2) NOT NULL,
  `composition` text NOT NULL,
  `image` varchar(255) NOT NULL,
  `hidden` int(1) NOT NULL,
  PRIMARY KEY (`id`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_dates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timetableid` int(11) NOT NULL,
  `date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `date` (`date`),
  KEY `date_index` (`date`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_discountcodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shortname` varchar(10) NOT NULL,
  `code` varchar(20) NOT NULL,
  `start` date DEFAULT NULL,
  `end` date DEFAULT NULL,
  `single` int(1) NOT NULL,
  `disabled` int(1) NOT NULL,
  `notes` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shortname` (`shortname`),
  KEY `code` (`code`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_discounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shortname` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `basefare` varchar(10) NOT NULL,
  `customtype` int(1) NOT NULL,
  `inheritdeps` int(1) NOT NULL,
  `maxseats` int(3) NOT NULL,
  `triptype` varchar(8) NOT NULL,
  `rules` text NOT NULL,
  `comment` text NOT NULL,
  `shownotes` int(1) NOT NULL,
  `noteinstructions` text NOT NULL,
  `notetype` varchar(50) NOT NULL,
  `pattern` varchar(255) NOT NULL,
  `notguard` int(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shortname` (`shortname`) USING BTREE
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_manualbook` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journeytype` varchar(6) NOT NULL,
  `price` double NOT NULL,
  `supplement` double NOT NULL,
  `seats` int(4) NOT NULL,
  `travellers` text NOT NULL,
  `tickets` text NOT NULL,
  `ticketprices` text NOT NULL,
  `notes` text NOT NULL,
  `createdby` int(20) NOT NULL,
  `discountnote` varchar(255) NOT NULL,
  `discountcode` varchar(12) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `discountcode` (`discountcode`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_pricerevisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datefrom` date NOT NULL,
  `dateto` date NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `revision` int(11) NOT NULL,
  `stationone` int(11) NOT NULL,
  `stationtwo` int(11) NOT NULL,
  `journeytype` varchar(10) NOT NULL,
  `tickettype` varchar(10) NOT NULL,
  `price` double NOT NULL,
  `localprice` double NOT NULL,
  `disabled` int(1) NOT NULL,
  `image` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `stationfrom` (`stationone`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_specials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `name` varchar(60) NOT NULL,
  `description` text NOT NULL,
  `tickettypes` text NOT NULL,
  `fromstation` int(4) NOT NULL,
  `tostation` int(4) NOT NULL,
  `onsale` int(1) NOT NULL,
  `colour` varchar(6) NOT NULL,
  `background` varchar(6) NOT NULL,
  `longdesc` text NOT NULL,
  `survey` varchar(15) NOT NULL,
  `surveyconfig` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `date` (`date`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_stations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stnid` int(11) NOT NULL,
  `revision` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `sequence` int(2) NOT NULL,
  `requeststop` int(1) NOT NULL,
  `closed` int(1) NOT NULL,
  `hidden` int(1) NOT NULL,
  `principal` int(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sequence` (`sequence`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_stats` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `passengers` int(6) NOT NULL,
  `orders` int(5) NOT NULL,
  `totalonline` int(5) NOT NULL,
  `totalmanual` int(5) NOT NULL,
  `revenue` double NOT NULL,
  `maxload` int(5) NOT NULL,
  `prebook1` int(6) NOT NULL,
  `prebook2` int(6) NOT NULL,
  `postcodes` text NOT NULL,
  `postcodefirst` text NOT NULL,
  `postcodezone` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_stntimes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `station` tinyint(4) NOT NULL,
  `timetableid` int(11) NOT NULL,
  `revision` int(11) NOT NULL,
  `down_deps` text NOT NULL,
  `down_arrs` text NOT NULL,
  `up_deps` text NOT NULL,
  `up_arrs` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `timetable` (`timetableid`),
  KEY `revision` (`revision`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_surveyresp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wooorderid` int(11) NOT NULL DEFAULT 0,
  `woocartitem` varchar(50) NOT NULL DEFAULT '',
  `manual` int(11) NOT NULL DEFAULT 0,
  `type` varchar(15) NOT NULL,
  `response` text NOT NULL,
  `timecreated` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `wooorderid` (`wooorderid`),
  KEY `manual` (`manual`),
  KEY `type` (`type`),
  KEY `woocartitem` (`woocartitem`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_tickettypes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `sequence` int(4) NOT NULL,
  `name` varchar(25) NOT NULL,
  `description` varchar(255) NOT NULL,
  `composition` text NOT NULL,
  `depends` varchar(255) NOT NULL,
  `guardonly` int(1) NOT NULL,
  `special` int(1) NOT NULL,
  `hidden` int(1) NOT NULL,
  `discounttype` varchar(255) NOT NULL,
  `tkoption` int(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `code` (`code`),
  KEY `discounttype` (`discounttype`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_timetables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timetableid` int(11) NOT NULL,
  `timetable` varchar(12) NOT NULL,
  `revision` int(11) NOT NULL,
  `background` varchar(6) NOT NULL,
  `colour` varchar(6) NOT NULL,
  `html` text NOT NULL,
  `totaltrains` tinyint(4) NOT NULL,
  `colsmeta` text NOT NULL,
  `buylink` varchar(255) NOT NULL,
  `hidden` int(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `timetable` (`timetable`),
  KEY `revision` (`revision`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_travellers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `name` varchar(25) NOT NULL,
  `description` varchar(255) NOT NULL,
  `seats` int(2) NOT NULL,
  `guardonly` int(1) NOT NULL,
  `tkoption` int(1) NOT NULL,
  `special` int(1) NOT NULL,
  PRIMARY KEY (`id`)
)
".$charset_collate;

$sql[] = "
CREATE TABLE `wp_wc_railticket_ttrevisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datefrom` date NOT NULL,
  `dateto` date NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
)
".$charset_collate;
