-- --------------------------------------------------------
-- This script will restore a datafeeds database to a 
-- clean state.
--
-- Before running this script, please remove all data tables
-- using the following commands:
--
-- mysql -u<USERNAME> -p<PASSWORD> -D datafeeds -e "show tables" -s |  egrep "^al_01_" |  xargs -I "@@" mysql -u<USERNAME> -p<PASSWORD> -D datafeeds -e "DROP TABLE @@"
-- mysql -u<USERNAME> -p<PASSWORD> -D datafeeds -e "show tables" -s |  egrep "^al_02_" |  xargs -I "@@" mysql -u<USERNAME> -p<PASSWORD> -D datafeeds -e "DROP TABLE @@"
-- mysql -u<USERNAME> -p<PASSWORD> -D datafeeds -e "show tables" -s |  egrep "^al_03_" |  xargs -I "@@" mysql -u<USERNAME> -p<PASSWORD> -D datafeeds -e "DROP TABLE @@"
-- mysql -u<USERNAME> -p<PASSWORD> -D datafeeds -e "show tables" -s |  egrep "^tmp_al_01_" |  xargs -I "@@" mysql -u<USERNAME> -p<PASSWORD> -D datafeeds -e "DROP TABLE @@"
-- mysql -u<USERNAME> -p<PASSWORD> -D datafeeds -e "show tables" -s |  egrep "^tmp_al_02_" |  xargs -I "@@" mysql -u<USERNAME> -p<PASSWORD> -D datafeeds -e "DROP TABLE @@"
-- mysql -u<USERNAME> -p<PASSWORD> -D datafeeds -e "show tables" -s |  egrep "^tmp_al_03_" |  xargs -I "@@" mysql -u<USERNAME> -p<PASSWORD> -D datafeeds -e "DROP TABLE @@"
--
-- The above commands must be run before the script below. Otherwise,
-- the al_0x_datapro tables will not be created.
-- --------------------------------------------------------

--
-- Table structure for table `activeProcesses`
--


DROP TABLE IF EXISTS `activeProcesses`;
CREATE TABLE `activeProcesses` (
  `pid` int(11) DEFAULT NULL,
  `processName` varchar(256) DEFAULT NULL,
  `unixStartTime` int(11) DEFAULT NULL,
  `status` int(50) NOT NULL,
  UNIQUE KEY `processName` (`processName`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `al_01_datapro`
--

DROP TABLE IF EXISTS `al_01_datapro`;
CREATE TABLE IF NOT EXISTS `al_01_datapro` (
  `ProgramName` varchar(100) NOT NULL DEFAULT '',
  `ProgramURL` varchar(2000) NOT NULL DEFAULT '',
  `CatalogName` varchar(130) NOT NULL default '',
  `LastUpdated` varchar(255) NOT NULL DEFAULT '',
  `Name` varchar(255) NOT NULL DEFAULT '',
  `Keywords` varchar(500) NOT NULL,
  `Description` varchar(3000) NOT NULL,
  `SKU` varchar(100) NOT NULL DEFAULT '',
  `Manufacturer` varchar(250) NOT NULL DEFAULT '',
  `ManufacturerID` varchar(64) NOT NULL DEFAULT '',
  `UPC` varchar(15) NOT NULL DEFAULT '',
  `ISBN` varchar(64) NOT NULL DEFAULT '',
  `Currency` varchar(3) NOT NULL DEFAULT 'USD',
  `SalePrice` decimal(11,2) DEFAULT NULL,
  `Price` decimal(11,2) DEFAULT NULL,
  `RetailPrice` decimal(11,2) DEFAULT NULL,
  `FromPrice` varchar(255) NOT NULL DEFAULT '',
  `BuyURL` varchar(2000) NOT NULL DEFAULT '',
  `ImpressionURL` varchar(2000) NOT NULL DEFAULT '',
  `ImageURL` varchar(2000) NOT NULL DEFAULT '',
  `AdvertiserCategory` varchar(300) NOT NULL DEFAULT '',
  `ThirdPartyID` varchar(64) NOT NULL DEFAULT '',
  `ThirdPartyCategory` varchar(300) NOT NULL DEFAULT '',
  `Author` varchar(130) NOT NULL DEFAULT '',
  `Artist` varchar(130) NOT NULL DEFAULT '',
  `Title` varchar(130) NOT NULL DEFAULT '',
  `Publisher` varchar(130) NOT NULL DEFAULT '',
  `Label` varchar(130) NOT NULL DEFAULT '',
  `Format` varchar(64) NOT NULL DEFAULT '',
  `Special` varchar(3) NOT NULL DEFAULT '',
  `Gift` varchar(3) NOT NULL DEFAULT '',
  `PromotionalText` varchar(300) NOT NULL DEFAULT '',
  `StartDate` datetime DEFAULT NULL,
  `EndDate` datetime DEFAULT NULL,
  `Offline` varchar(3) NOT NULL DEFAULT '',
  `Online` varchar(3) NOT NULL DEFAULT '',
  KEY `ProgramName` (`ProgramName`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `al_02_datapro`
--

DROP TABLE IF EXISTS `al_02_datapro`;
CREATE TABLE IF NOT EXISTS `al_02_datapro` (
  `ProductID` varchar(255) NOT NULL DEFAULT '',
  `ProductName` varchar(255) NOT NULL DEFAULT '',
  `Sku Number` varchar(255) NOT NULL DEFAULT '',
  `PrimaryCategory` varchar(255) NOT NULL DEFAULT '',
  `SecondaryCategories` varchar(255) NOT NULL DEFAULT '',
  `ProductURL` varchar(255) NOT NULL DEFAULT '',
  `ProductImageURL` varchar(255) NOT NULL DEFAULT '',
  `BuyURL` varchar(255) NOT NULL DEFAULT '',
  `ShortProductDescription` text NOT NULL,
  `LongProductDescription` varchar(255) NOT NULL DEFAULT '',
  `Discount` varchar(255) NOT NULL DEFAULT '',
  `DiscountType` varchar(255) NOT NULL DEFAULT '',
  `SalePrice` decimal(11,2) NOT NULL,
  `RetailPrice` decimal(11,2) NOT NULL,
  `BeginDate` varchar(255) NOT NULL DEFAULT '',
  `EndDate` varchar(255) NOT NULL DEFAULT '',
  `Brand` varchar(255) NOT NULL DEFAULT '',
  `Shipping` varchar(255) NOT NULL DEFAULT '',
  `Keywords` varchar(255) NOT NULL DEFAULT '',
  `ManufacturerPartNumber` varchar(255) NOT NULL DEFAULT '',
  `ManufacturerName` varchar(255) NOT NULL DEFAULT '',
  `ShippingInformation` varchar(255) NOT NULL DEFAULT '',
  `Availability` varchar(255) NOT NULL DEFAULT '',
  `UniversalPricingCode` varchar(255) NOT NULL DEFAULT '',
  `ClassID` varchar(255) NOT NULL DEFAULT '',
  `Currency` varchar(255) NOT NULL DEFAULT '',
  `M1` varchar(255) NOT NULL DEFAULT '',
  `Pixel` varchar(255) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `al_03_datapro`
--

DROP TABLE IF EXISTS `al_03_datapro`;
CREATE TABLE IF NOT EXISTS `al_03_datapro` (
  `Product_ID` varchar(100) DEFAULT NULL,
  `Product_name` text NOT NULL,
  `Product_URL` text NOT NULL,
  `Buy_URL` text,
  `Image_URL` text,
  `Category` text,
  `Category_ID` text,
  `PFX_Category` int(11) DEFAULT NULL,
  `Brief_desc` varchar(200) NOT NULL DEFAULT '',
  `Short_desc` varchar(256) DEFAULT NULL,
  `Interim_desc` varchar(512) DEFAULT NULL,
  `Long_desc` text,
  `Product_Keyword` text,
  `Brand` text,
  `Manufacturer` text,
  `Manf_ID` text,
  `Manufacture_model` text,
  `UPC` varchar(30) DEFAULT NULL,
  `Platform` varchar(50) DEFAULT NULL,
  `Media_type_desc` text,
  `Merchandise_type` text,
  `Price` decimal(11,2) NOT NULL DEFAULT '0.00',
  `Sale_price` decimal(11,2) DEFAULT NULL,
  `Variable_Commission` varchar(3) DEFAULT NULL,
  `Sub_FeedID` varchar(255) DEFAULT NULL,
  `In_Stock` varchar(3) DEFAULT NULL,
  `Inventory` int(10) unsigned DEFAULT NULL,
  `Remove_date` varchar(10) DEFAULT NULL,
  `Rew_points` int(10) unsigned DEFAULT NULL,
  `Publisher_Specific` varchar(3) DEFAULT NULL,
  `Ship_avail` varchar(50) DEFAULT NULL,
  `Ship_Cost` decimal(11,2) DEFAULT NULL,
  `Shipping_is_absolute` varchar(3) DEFAULT NULL,
  `Shipping_weight` varchar(50) DEFAULT NULL,
  `Ship_needs` text,
  `Ship_promo_text` text,
  `Product_promo_text` text,
  `Daily_specials_indicator` varchar(3) DEFAULT NULL,
  `Gift_boxing` varchar(3) DEFAULT NULL,
  `Gift_wrapping` varchar(3) DEFAULT NULL,
  `Gift_messaging` varchar(3) DEFAULT NULL,
  `Product_container_name` text,
  `Cross_selling_reference` text,
  `Alt_image_prompt` text,
  `Alt_image_URL` text,
  `Age_range_min` text,
  `Age_range_max` text,
  `ISBN` int(10) unsigned DEFAULT NULL,
  `Title` text,
  `Publisher` text,
  `Author` text,
  `Genre` text,
  `Media` text,
  `Material` text,
  `Permutation_color` text,
  `Permutation_size` text,
  `Permutation_weight` text,
  `Permutation_item_price` text,
  `Permutation_sale_price` text,
  `Permutation_inventory_status` text,
  `Permutation` text,
  `Permutation_SKU` text,
  `BaseProductID` int(10) unsigned DEFAULT NULL,
  `Option1_Value` text,
  `Option2_Value` text,
  `Option3_Value` text,
  `Option4_Value` text,
  `Option5_Value` text,
  `Option6_Value` text,
  `Option7_Value` text,
  `Option8_Value` text,
  `Option9_Value` text,
  `Option10_Value` text,
  `Option11_Value` text,
  `Option12_Value` text,
  `Option13_Value` text,
  `Option14_Value` text,
  `Option15_Value` text,
  `Option16_Value` text,
  `Option17_Value` text,
  `Option18_Value` text,
  `Option19_Value` text,
  `Option20_Value` text
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


-- --------------------------------------------------------

--
-- Table structure for table `compchecker`
--

DROP TABLE IF EXISTS `compchecker`;
CREATE TABLE `compchecker` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compname` varchar(255) NOT NULL DEFAULT '',
  `catalog` varchar(130) NOT NULL DEFAULT '',
  `vardatetime` int(11) NOT NULL DEFAULT '0',
  `file` int(11) NOT NULL DEFAULT '0',
  `norecs` int(11) NOT NULL DEFAULT '0',
  `tablename` varchar(50) NOT NULL DEFAULT '',
  `companyname` varchar(255) NOT NULL DEFAULT '',
  `approved` tinyint(4) NOT NULL DEFAULT '0',
  `filename` varchar(1024) NOT NULL DEFAULT '',
  `working` tinyint(4) NOT NULL DEFAULT '0',
  `reload` tinyint(4) NOT NULL DEFAULT '0',
  `df_lines` bigint(20) NOT NULL DEFAULT '0',
  `df_size` bigint(20) NOT NULL DEFAULT '0',
  `reindex` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tablename` (`tablename`),
  KEY `compname` (`compname`),
  KEY `file` (`file`),
  KEY `compname_2` (`compname`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `db_creation`
--

DROP TABLE IF EXISTS `db_creation`;
CREATE TABLE `db_creation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dbset` int(11) NOT NULL DEFAULT '0',
  `numcount` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `filesitter`
--

DROP TABLE IF EXISTS `filesitter`;
CREATE TABLE `filesitter` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `original` varchar(1024) NOT NULL DEFAULT '',
  `file` varchar(1024) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT '0',
  `filestart` int(11) NOT NULL DEFAULT '0',
  `fileend` int(11) NOT NULL DEFAULT '0',
  `datastart` int(11) NOT NULL DEFAULT '0',
  `dataend` int(11) NOT NULL DEFAULT '0',
  `serial` varchar(32) NOT NULL DEFAULT '',
  `size` varchar(25) NOT NULL DEFAULT '',
  `modifiedtime` int(11) NOT NULL,
  `postsize` varchar(25) NOT NULL DEFAULT '',
  `process` tinyint(4) NOT NULL DEFAULT '0',
  `feedfile` int(11) NOT NULL DEFAULT '0',
  `feedRevision` int(10) NOT NULL DEFAULT '1',
  `storage` varchar(1024) NOT NULL DEFAULT '',
  `datalines` bigint(20) NOT NULL DEFAULT '0',
  `notes` text NOT NULL,
  `compcheckerid` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `frontend_sites_vendors`
--

DROP TABLE IF EXISTS `frontend_sites_vendors`;
CREATE TABLE `frontend_sites_vendors` (
  `frontend_site_id` int(11) NOT NULL,
  `vendor_table_name` varchar(50) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `linkshare`
--

DROP TABLE IF EXISTS `linkshare`;
CREATE TABLE `linkshare` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `al_filename` varchar(150) NOT NULL DEFAULT '',
  `al_datetime` varchar(12) NOT NULL DEFAULT '0',
  `al_lastcheck` bigint(20) NOT NULL DEFAULT '0',
  `al_lastrun` bigint(20) NOT NULL DEFAULT '0',
  `al_process` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `logfile`
--

DROP TABLE IF EXISTS `logfile`;
CREATE TABLE `logfile` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `original` varchar(1024) NOT NULL DEFAULT '',
  `file` varchar(1024) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT '0',
  `filestart` int(11) NOT NULL DEFAULT '0',
  `fileend` int(11) NOT NULL DEFAULT '0',
  `datastart` int(11) NOT NULL DEFAULT '0',
  `dataend` int(11) NOT NULL DEFAULT '0',
  `serial` varchar(32) NOT NULL DEFAULT '',
  `size` varchar(25) NOT NULL DEFAULT '',
  `postsize` varchar(25) NOT NULL DEFAULT '0',
  `process` tinyint(4) NOT NULL DEFAULT '0',
  `feedfile` int(11) NOT NULL DEFAULT '0',
  `storage` varchar(1024) NOT NULL DEFAULT '',
  `datalines` bigint(20) NOT NULL DEFAULT '0',
  `notes` text NOT NULL,
  `compcheckerid` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `time` (`time`),
  KEY `process` (`process`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `restrictedProcessFiles`
--

DROP TABLE IF EXISTS `restrictedProcessFiles`;
CREATE TABLE `restrictedProcessFiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(512) NOT NULL,
  `do_not_download` tinyint(4) NOT NULL DEFAULT '0',
  `do_not_store` tinyint(4) NOT NULL DEFAULT '0',
  `do_not_upload_to_database` tinyint(4) NOT NULL DEFAULT '0',
  `delete_if_exists` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `searchLog`
--

DROP TABLE IF EXISTS `searchLog`;
CREATE TABLE `searchLog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ipAddress` varchar(15) NOT NULL,
  `searchTerm` varchar(150) NOT NULL,
  `totalResults` int(11) NOT NULL,
  `pageNumber` int(11) NOT NULL,
  `loadTime` varchar(15) NOT NULL,
  `replaceMissingImages` tinyint(1) NOT NULL,
  `newImagesCached` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sphinxTableList`
--

DROP TABLE IF EXISTS `sphinxTableList`;
CREATE TABLE `sphinxTableList` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tablename` varchar(256) NOT NULL,
  `updated` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `stats_feedfieldmap`
--

DROP TABLE IF EXISTS `stats_feedfieldmap`;
CREATE TABLE `stats_feedfieldmap` (
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `vendors_processed` int(11) NOT NULL,
  `records_processed` int(11) NOT NULL,
  `available_vendors` int(11) NOT NULL COMMENT 'The number of vendor tables available after feed mapping'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `stats_frontendfilegenerator`
--

DROP TABLE IF EXISTS `stats_frontendfilegenerator`;
CREATE TABLE `stats_frontendfilegenerator` (
  `startTime` int(11) NOT NULL COMMENT 'Unix timestamp',
  `endTime` int(11) NOT NULL COMMENT 'Unix timestamp',
  `siteName` varchar(256) NOT NULL,
  `filesCreated` int(11) NOT NULL,
  `sleepTime` int(11) NOT NULL COMMENT 'Number of seconds to sleep until next run for this site'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `stats_imagecache`
--

DROP TABLE IF EXISTS `stats_imagecache`;
CREATE TABLE `stats_imagecache` (
  `datetime` datetime NOT NULL,
  `number_of_images` int(11) NOT NULL,
  `download_size` int(11) NOT NULL,
  `number_of_misses` int(11) NOT NULL,
  `table_name` varchar(512) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `stats_linksharemonitor`
--

DROP TABLE IF EXISTS `stats_linksharemonitor`;
CREATE TABLE `stats_linksharemonitor` (
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `successful_ftp_login_one` tinyint(4) NOT NULL,
  `successful_ftp_login_two` int(10) unsigned NOT NULL,
  `files_in_filelist` int(10) unsigned NOT NULL,
  `files_to_retrieve` int(10) unsigned NOT NULL,
  `failed_downloads` int(10) unsigned NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `status_reports`
--

DROP TABLE IF EXISTS `status_reports`;
CREATE TABLE `status_reports` (
  `processName` varchar(20) NOT NULL,
  `type` varchar(20) NOT NULL,
  `note` text NOT NULL,
  `timestamp` bigint(20) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `tablenameMap`
--

DROP TABLE IF EXISTS `tablenameMap`;
CREATE TABLE `tablenameMap` (
  `number` int(11) NOT NULL,
  `tablename` varchar(30) NOT NULL,
  PRIMARY KEY (`number`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `tmpcompare`
--

DROP TABLE IF EXISTS `tmpcompare`;
CREATE TABLE `tmpcompare` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compname` varchar(255) NOT NULL DEFAULT '',
  `catalog` varchar(130) NOT NULL DEFAULT '',
  `vardatetime` int(11) NOT NULL DEFAULT '0',
  `file` int(11) NOT NULL DEFAULT '0',
  `norecs` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `tmp_compchecker`
--

DROP TABLE IF EXISTS `tmp_compchecker`;
CREATE TABLE `tmp_compchecker` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compname` varchar(255) NOT NULL DEFAULT '',
  `catalog` varchar(130) NOT NULL DEFAULT '',
  `vardatetime` int(11) NOT NULL DEFAULT '0',
  `file` int(11) NOT NULL DEFAULT '0',
  `norecs` int(11) NOT NULL DEFAULT '0',
  `tablename` varchar(50) NOT NULL DEFAULT '',
  `companyname` varchar(255) NOT NULL DEFAULT '',
  `approved` tinyint(4) NOT NULL DEFAULT '0',
  `filename` varchar(1024) NOT NULL DEFAULT '',
  `working` tinyint(4) NOT NULL DEFAULT '0',
  `reload` tinyint(4) NOT NULL DEFAULT '0',
  `df_lines` bigint(20) NOT NULL DEFAULT '0',
  `df_size` bigint(20) NOT NULL DEFAULT '0',
  `reindex` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `compname` (`compname`),
  KEY `tbl` (`tablename`),
  KEY `tablename` (`tablename`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

DROP TABLE IF EXISTS `vendors`;
CREATE TABLE `vendors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `feed_id` tinyint(4) NOT NULL,
  `name` varchar(256) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
