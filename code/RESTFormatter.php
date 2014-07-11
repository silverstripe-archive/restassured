<?php

abstract class RESTFormatter {

	static $default = 'application/json';

	private static function useful_mimetype($type) {
		return $type && $type != '*/*';
	}

	/**
	 * Factory that returns a RESTFormatter subclass that formats in a particular format based on mimetype or extension
	 *
	 * @static
	 * @param SS_HTTPRequest | string - $request
	 * @return RESTFormatter - an instance of a RESTFormatter that can do the job, or null if none can.
	 */
	public static function get_formatter($request = null) {
		// Request can be an SS_HTTPRequest object
		if($request instanceof SS_HTTPRequest) {
			// Try and get the mimetype from the request's Accept header
			$mimetypes = $request->getAcceptMimetypes();
			// Alternatively the type might be specified by the client as an extension on the URL
			$extension = $request->getExtension();
		}
		// Request can alternatively be a string which might be a mimetype or an extension
		else {
			$mimetypes = array($request); $extension = $request;
		}

		// Filter out empty items and */*
		$mimetypes = array_filter($mimetypes, array(__CLASS__, 'useful_mimetype'));

		// If we didn't get a mimetype _or_ an extension, use the default
		if (!$mimetypes && !$extension) $mimetypes = array(self::$default);

		// Now step through looking for matches on any specified mimetype or exception
		$byMimeType = null; $byExtension = null;

		foreach (ClassInfo::subclassesFor(__CLASS__) as $class) {
			if ($class == __CLASS__) continue;

			if($mimetypes && count(array_intersect($mimetypes, Object::get_static($class, 'mimetypes')))) {
				$byMimeType = $class;
				break; // Mimetypes take priority over extensions. If we get a match we're done.
			}

			if($extension && in_array($extension, Object::get_static($class, 'url_extensions'))) {
				$byExtension = $class;
				if (!$mimetypes) break; // We're only done on a match if we don't have a mimetype to look for.
			}
		}

		// Mime type match gets priority over extension
		if ($byMimeType) return new $byMimeType();
		if ($byExtension) return new $byExtension();
	}

	/**
	 * Takes a possibly nested set of stdClass objects and turns it into a nested associative array
	 * @static
	 * @param $d stdClass - The object to convert
	 * @return array - An array with every property of the passed object converted to a key => value pair in the array recursively
	 */
	private static function object_to_array($d) {
		if (is_object($d)) {
			$d = get_object_vars($d);
		}

		if (is_array($d)) {
			return array_map(array(__CLASS__, __FUNCTION__), $d);
		}
		else {
			return $d;
		}
	}

	/**
	 * Takes a list of fields - a list of "." seperated strings - and turns it into a fieldspec (a nested associative array),
	 * where the key is the field, and the value is an array of nested fields or false if no nesting, i.e.
	 *
	 * array('Foo', 'Bar.A', 'Bar.B')
	 *
	 * becomes
	 *
	 * array(
	 *    'Foo' => false,
	 *    'Bar' => array(
	 *       'A' => false,
	 *       'B' => false
	 *    )
	 * );
	 *
	 * @param $fields [string] - the list of fields
	 * @return array - th
	 */
	function decodeFields($fields) {
		/* Turn fields (a list of "." separated strings) into a field spec (a nested array) */

		$fieldspec = new stdClass();

		foreach ($fields as $field) {
			$parts = explode('.', $field);
			$dest = $fieldspec;

			while(count($parts) > 1) {
				$part = array_shift($parts);

				if (!isset($dest->$part)) $dest->$part = new stdClass();
				$dest = $dest->$part;
			}

			$part = $parts[0];
			$dest->$part = false;
		}

		return self::object_to_array($fieldspec);
	}

	/**
	 * format is the main public entry point for formatting RESTNouns as output
	 * @param $noun
	 * @param $fields
	 * @return void
	 */
	function format($noun, $fields) {
		if (!is_array($fields)) $fields = preg_split('/[,\s]+/', $fields);

		$data = $this->collectFields($noun, $this->decodeFields($fields));
		return $this->buildResponse($noun, $data);
	}



	/**
	 * Given a fieldspec and a noun, recursively collect the specified fields into a "data" element - a subclass specific
	 * object that can then be trivially converted into the expected response.
	 * @param $noun RESTNoun - The noun we're currently collecting fields from
	 * @param $fieldspec array - The nested fields specification that is the result of #format's conversion from a set of "." separated strings
	 * @return any - An opaque object that is understood by the particular buildResponse method of the subclass
	 */
	protected function collectFields($noun, $fieldspec) {
		$res = $this->dataItem($noun->class);

		if (array_key_exists('*', $fieldspec)) {
			$fieldspec = array_merge($fieldspec, $this->decodeFields(Config::inst()->get($noun->class, 'default_fields')));
			unset($fieldspec['*']);
		}

		foreach ($fieldspec as $field => $nesting) {
			if ($nesting) {
				$sub = $noun->$field;
				if (is_array($sub)) {
					$col = $this->addCollectionToItem($res, $field);
					foreach ($noun->$field as $item) $this->appendToCollection($res, $col, $field, $this->collectFields($item, $nesting));
				}
				elseif($sub) {
					$this->addToItem($res, $field, $this->collectFields($noun->$field, $nesting));
				}
			}
			else {
				$this->addToItem($res, $field, $noun->$field);
			}
		}

		return $res;
	}

	/**
	 * These methods are overridden by the specific formatter subclasses
	 */

	/** Given a class as a string, return a "data" item - some object that can hold data during collection */
	abstract function dataItem($class);
	/** Add a key / value pair to an object as returned by dataItem */
	abstract function addToItem($dataItem, $field, $value);

	/**
	 * Works in concert with #appendToCollection to handle sequences.
	 * This is called once to allow the construction of any sequence object, then #appendToCollection is called repeatedly with
	 * the same arguments, plus any handle this function returns and the values to add
	 * @abstract
	 * @param $dataItem any - The object as returned by #dataItem to add a sequence to
	 * @param $field string - The name of the sequence in the object
	 * @return any - An optional handle. Will be passed to #appendToCollection without change
	 */
	abstract function addCollectionToItem($dataItem, $field);

	/**
	 * Add a value to a sequence
	 * @abstract
	 * @param $dataItem any - same as passed to #addCollectionToItem
	 * @param $dataCollection any - handle returned from #addCollectionToItem
	 * @param $field string - same as passed to #addCollectionToItem
	 * @param $value any - value to add to sequence
	 * @return void
	 */
	abstract function appendToCollection($dataItem, $dataCollection, $field, $value);

	/**
	 * Takes the noun we build the data from and the data as built by collectFields, and returns an HTTPResponse object
	 * that contains the finally formatted data
	 * @abstract
	 * @param $noun RESTNoun - the noun as passed to #format
	 * @param $data any - the data element as generated by #dataItem inside #collectFields
	 * @return SS_HTTPResponse - the response
	 */
	abstract protected function buildResponse($noun, $data);
}

class RESTFormatter_JSON extends RESTFormatter {
	static $mimetypes = array('application/json');
	static $url_extensions = array('js', 'json');

	static $type_attribute = '$type';

	function dataItem($class) {
		$res = new stdClass();

		$field = self::$type_attribute;
		if ($field) $res->$field = $class;
		
		return $res;
	}

	function addToItem($dataItem, $field, $value) {
		$dataItem->$field = $value;
	}

	function addCollectionToItem($dataItem, $field) {
		$dataItem->$field = array();
	}

	function appendToCollection($dataItem, $dataCollection, $field, $value) {
		$array =& $dataItem->$field;
		$array[] = $value;
	}

	function buildResponse($noun, $data) {
		$response = new SS_HTTPResponse(json_encode($data));
		$response->addHeader('Content-Type', 'application/json');

		return $response;
	}
}

class RESTFormatter_XML extends RESTFormatter {
	static $mimetypes = array('text/xml');
	static $url_extensions = array('xml');

	static $scalar_style = 'elem'; // 'elem' or 'attr'

	function format($noun, $fields) {
		$this->document = new DOMDocument();
		return parent::format($noun, $fields);
	}

	function dataItem($class) {
		return $this->document->createElement($class);
	}

	function addToItem($dataItem, $field, $value) {
		if ($value instanceof DOMNode) {
			$dataItem->appendChild($value);
		}
		elseif(self::$scalar_style == 'elem') {
			$sub = $this->document->createElement($field, $value);
			$dataItem->appendChild($sub);
		}
		else {
			$dataItem->setAttribute($field, $value);
		}
	}

	function addCollectionToItem($dataItem, $field) {
		$node = $this->document->createElement($field);
		$dataItem->appendChild($node);
		return $node;
	}

	function appendToCollection($dataItem, $dataCollection, $field, $value) {
		$dataCollection->appendChild($value);
	}

	function buildResponse($noun, $data) {
		$this->document->appendChild($data);
		$response = new SS_HTTPResponse($this->document->saveXML());
		$response->addHeader('Content-Type', 'text/xml');

		return $response;
	}
}


