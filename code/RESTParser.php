<?php

abstract class RESTParser extends Object {

	static $mimetypes = array();

	private static function get_content_type_mimetypes($request, $includeQuality = false) {
	   $mimetypes = array();
	   $mimetypesWithQuality = explode(',',$request->getHeader('Content-Type'));

	   foreach($mimetypesWithQuality as $mimetypeWithQuality) {
	      $mimetypes[] = ($includeQuality) ? $mimetypeWithQuality : preg_replace('/;.*/', '', $mimetypeWithQuality);
	   }
	   return $mimetypes;
	}

	private static function useful_mimetype($type) {
		return $type && $type != '*/*';
	}

	/**
	 * Factory that returns a RESTParser subclass that parses data in a particular format based on mimetype or content
	 *
	 * TODO: This is pretty copy-pasta from RESTFormatter. Maybe consolidate this code with that?
	 *
	 * @static
	 * @param SS_HTTPRequest | string - $request
	 * @return RESTParser - an instance of a RESTParser that can do the job, or null if none can.
	 */
	public static function get_parser($request = null) {
		// Request can be an SS_HTTPRequest object
		if($request instanceof SS_HTTPRequest) {
			// Try and get the mimetype from the request's Accept header
			$mimetypes = self::get_content_type_mimetypes($request);
			// Alternatively the type might be auto-detected from the request
			$body = $request->getBody();
		}
		// Request can alternatively be a string which might be a mimetype or an extension
		else {
			$mimetypes = array($request); $body = '';
		}

		// Filter out empty items and */*
		$mimetypes = array_filter($mimetypes, array(__CLASS__, 'useful_mimetype'));

		// Now step through looking for matches on any specified mimetype or exception
		$byMimeType = null; $bySignature = null;

		foreach (ClassInfo::subclassesFor(__CLASS__) as $class) {
			if ($class == __CLASS__) continue;

			if($mimetypes && count(array_intersect($mimetypes, Object::get_static($class, 'mimetypes')))) {
				$byMimeType = $class;
				break; // Mimetypes take priority over extensions. If we get a match we're done.
			}

			if($body && call_user_func(array($class, 'matches_signature'), $body)) {
				$bySignature = $class;
				if (!$mimetypes) break; // We're only done on a match if we don't have a mimetype to look for.
			}
		}

		// Mime type match gets priority over extension
		if ($byMimeType) return new $byMimeType();
		if ($bySignature) return new $bySignature();
	}

	static function matches_signature($body) { /* NOP */ }

	function parseInto($request, $fields, $noun = null, $defaultType = null) {
		$parsed = $this->parse($request->getBody());

		$type = $this->getObjectType($parsed);
		if (!$type) $type = $defaultType;

		if ($noun) {
			if ($type && !($noun instanceof $type)) throw new Exception('Mismatched API types while parsing request', 400);
		} else {
			if (!$type) { throw new Exception('No API type specified', 400); }
			if(!class_exists($type)) { throw new Exception('Invalid API type "'.$type.'"', 400); }
			$noun = new $type();
		}
		if (in_array('*', $fields)) {
			$fields = array_merge($fields, Config::inst()->get($noun->class, 'default_write_fields'));
			$fields = array_diff($fields, array('*'));
		}

		foreach ($fields as $field) {
			$required = false;
			
			// Check for a '!' at the end of the field, which means "required"
			if ($field[strlen($field)-1] == '!') {
				$field = substr($field, 0, -1);
				$required = true;
			}

			if ($this->getFieldExists($parsed, $field)) {
				$noun->{$field} = $this->getFieldFromObject($parsed, $field);
			} elseif ($required) {
				throw new Exception('Required field missing');
			}
		}

		return $noun;
	}

	abstract protected function parse($body);

	abstract protected function getObjectType($object);
	abstract protected function getFieldExists($object, $field);
	abstract protected function getFieldFromObject($object, $field);
}

class RESTParser_JSON extends RESTParser {
	static $mimetypes = array('application/json');

	static function matches_signature($body) {
		$body = trim($body);
		$firstchar = $body[0];

		return ($firstchar == '{' || $firstchar == '[') && json_decode($body) !== null;
	}

	/**
	 *
	 * @param string $body
	 * @return string - JSON
	 */
	protected function parse($body) {
		$parsedBody = json_decode($body);
		if(json_last_error()) {
			$this->handleError(json_last_error());
		}
		return $parsedBody;
	}

	/**
	 * Throw exception depending that correlates to the json_last_error() passed
	 * in.
	 *
	 * @param int $errorCode
	 * @throws Exception
	 */
	protected function handleError($errorCode) {
		switch ($errorCode) {
			case JSON_ERROR_DEPTH:
				throw new Exception('Exceeded maximum stack depth while parsing JSON payload', 400);
			break;
			case JSON_ERROR_STATE_MISMATCH:
				throw new Exception('Underflow or the modes mismatch when parsing JSON payload', 400);
			break;
			case JSON_ERROR_CTRL_CHAR:
				throw new Exception('Found an unexpected control character in JSON payload', 400);
			break;
			case JSON_ERROR_SYNTAX:
				throw new Exception('JSON payload is malformed', 400);
			break;
			case JSON_ERROR_UTF8:
				throw new Exception('JSON payload has malformed UTF-8 characters, possibly incorrectly encoded', 400);
			break;
			default:
				throw new Exception('Unknown json error', 400);
			break;
		}
	}

	protected function getObjectType($object) {
		$typeField = RESTFormatter_JSON::$type_attribute;
		if (isset($object->$typeField)) return $object->$typeField;
	}

	protected function getFieldExists($object, $field) {
		return isset($object->$field);
	}

	protected function getFieldFromObject($object, $field) {
		return $object->$field;
	}
}

class RESTParser_XML extends RESTParser {
	static $mimetypes = array('text/xml');

	static function matches_signature($body) {
		if (!preg_match('/^\s*<\?xml/', $body)) return false;

		libxml_use_internal_errors(false);
		$parsed = simplexml_load_string($body);
		libxml_use_internal_errors(true);

		return $parsed !== null;
	}

	protected function parse($body) {
		$doc = new DOMDocument();
		$doc->loadXML($body);
		return $doc->firstChild;
	}

	protected function getObjectType($object) {
		return $object->tagName;
	}

	protected function getFieldExists($object, $field) {
		return $object->hasAttribute($field) || $object->getElementsByTagName($field)->length > 0;
	}

	protected function getFieldFromObject($object, $field) {
		if ($object->hasAttribute($field)) {
			return $object->getAttribute($field);
		}
		else {
			return $object->getElementsByTagName($field)->item(0)->textContent;
		}
	}
}

class RESTParser_FormData extends RESTParser {
	static $mimetypes = array('application/x-www-form-urlencoded', 'multipart/form-data');

	static function matches_signature($body) {
		// Only use this parser if mime-type matches
		return false;
	}

	protected function parse($body) {
		return $_REQUEST;
	}

	protected function getObjectType($object) {
		if (isset($object['type'])) return $object['$type'];
	}

	protected function getFieldExists($object, $field) {
		return isset($object[$field]);
	}

	protected function getFieldFromObject($object, $field) {
		return $object[$field];
	}
}
