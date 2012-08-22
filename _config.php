<?php

define('RESTASSURED_ROOT', 'rest');
define('RESTASSURED_DOCROOT', 'rest/docs');

Director::addRules(100, array(
	RESTASSURED_DOCROOT => 'RESTDocGenerator_Controller',
	RESTASSURED_ROOT => 'RESTRouter',
));

// SilverStripe autoloader cant currently handle resolving traits, so include directly
require_once dirname(__FILE__)."/code/RESTNoun.php";
require_once dirname(__FILE__)."/code/RESTItem.php";
require_once dirname(__FILE__)."/code/RESTCollection.php";
