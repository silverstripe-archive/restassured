<?php

// SilverStripe autoloader cant currently handle resolving traits, so include directly. Cant wait for
// restassured/_config because earlier _configs might call the static methods
require_once dirname(__FILE__)."/RESTNoun.php";

class RESTRoot extends ViewableData {
	use RESTNoun;

	static protected $registered_handlers = array();

	static function register($name, $collectionClass) {
		self::$registered_handlers[$name] = $collectionClass;
	}

	static function unregister($name) {
		unset(self::$registered_handlers[$name]);
	}

	static function get_registered() {
		return self::$registered_handlers;
	}

	function __get($name) {
		if (isset(self::$registered_handlers[$name])) {
			$collectionClass = self::$registered_handlers[$name];
			$res = new $collectionClass();
			$res->parent = $this;
			$res->linkFragment = $name;
			return $res;
		}
		return parent::__get($name);
	}

	function Link() {
		return RESTASSURED_ROOT;
	}

	function LinkFor($item) {
		if ($item->parent !== $this) user_error('Tried to get link for noun that is not a root item', E_USER_ERROR);
		return Controller::join_links($this->Link(), $item->linkFragment);
	}
}

class RESTRoot_Handler extends RESTNoun_Handler {

	protected function respondWith($fields = array()) {
		// Not implemented yet. Eventually probably want to have some sort of discovery support
	}

}