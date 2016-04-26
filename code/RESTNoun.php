<?php

trait RESTNoun {
	public $parent; public $linkFragment;

	// static $default_fields = array();

	function Link() {
		return $this->parent->LinkFor($this);
	}

	abstract function LinkFor($item);
}

class RESTNoun_Handler extends RequestHandler {
	function __construct($noun) {
		$this->failover = $noun;
		parent::__construct();
	}

	protected $request;

	function handleRequest(SS_HTTPRequest $request, DataModel $model) {
		$this->request = $request;

		$method = $request->httpMethod();

		if($this->checkAccessAction($method)) {
			try {
				$request = $this->$method($request);
				// TODO: Abstract this out to API module, as it's application specific
				Session::save();
				return $request;
			} catch (Exception $e) {
				if ($e instanceof SS_HTTPResponse_Exception) {
					throw $e;
				} elseif ($e instanceof RESTException) {
					$this->respondWithError(array('code' => $e->getCode(), 'exception' => $e));
				} else {
					$this->respondWithError(array('code' => 500, 'exception' => $e));
				}
			}
		}
		$this->respondWithError(array('code' => 403, 'exception' => new Exception('Method not allowed')));
	}

	protected function parseRequest($args) {
		// Make args' keys lowercase
		$args = array_combine(array_map('strtolower', array_keys($args)), array_values($args));

		// Deal with request specially, since we need it to determine the default noun in the next step
		$request = isset($args['request']) ? $args['request'] : $this->request;

		// Add defaults
		$args = array_merge(array(
			// Where to decode into. On PATCH, defaults to existing object. Otherwise defaults to empty
			'noun' => ($request->httpMethod() == 'PATCH' ? $this->failover : null),
			// Default type to create
			'defaulttype' => null,
			// Fields specification
			'fields' => array('*')
		), $args);

		$parser = RESTParser::get_parser($request);
		if (!$parser) $this->respondWithError(array('code' => 415, 'exception' => new Exception('Couldnt find parser for body')));

		$noun = $parser->parseInto($request, $args['fields'], $args['noun'], $args['defaulttype']);
		return $noun;
	}

	protected $headers = array();

	protected function addResponseHeader($header, $value) {
		$this->headers[] = array($header, $value);
	}

	protected function resetResponseHeaders() {
		$this->headers = array();
	}

	protected function respondAs($args) {
		// Make args' keys lowercase
		$args = array_combine(array_map('strtolower', array_keys($args)), array_values($args));

		// Add defaults
		$args = array_merge(array(
			 // HTTP response code & description.
			'code' => 200,
			'codeDescription' => null,
			 // Specify a location to set as a Location: header. Useful if you make code a 3xx and don't want to call addHeader
			'location' => null,
			 // Response body. Overrides the calculation from noun
			'body' => null,
			// Noun to respond with
			'noun' => $this->failover,
			'fields' => '*'
		), $args);

		$response = new SS_HTTPResponse();

		// If a noun was provided, do the conversion
		if ($args['noun']) {
			// First. find a formatter. Give a 406 (not acceptable) if we can't find one
			$formatter = RESTFormatter::get_formatter($this->request);
			if (!$formatter) $this->respondWithError(array('code' => 406, 'exception' => new Exception('Couldnt find formatter for response')));

			// Split the fields if it's a string
			$fields = $args['fields'];
			if (!is_array($fields)) $fields = preg_split('/[,\s]+/', $fields);

			// Format the response, and throw a 500 is we didn't get anything
			$response = $formatter->format($args['noun'], $fields);
			if (!$response) $this->respondWithError(array('code' => 500, 'exception' => new Exception('Response formatter returned NULL')));
		}

		// If a specific body was provided, use that
		if ($args['body']) $response->setBody($args['body']);
		
		// Use the code specified
		if ($args['code']) $response->setStatusCode($args['code'], $args['codeDescription']);

		// Add any headers
		foreach ($this->headers as $header) $response->addHeader($header[0], $header[1]);

		// And then the location header
		if ($args['location']) $response->addHeader('Location', Director::absoluteURL($args['location'], true));

		// Clean up any pre-set headers
		foreach (headers_list() as $header) {
			$parts = explode(':', $header);
			$name = trim($parts[0]);

			if (function_exists('header_remove')) header_remove($name);
			else header($name.':');
		}

		return $response;
	}

	protected function respondWith() {
		return $this->respondAs(array('fields' => func_get_args()));
	}

	public static $exception_noun;

	public function respondWithError($args) {
		// Make args' keys lowercase
		$args = array_combine(array_map('strtolower', array_keys($args)), array_values($args));

		// See if an exception is passed (no default)
		$exception = null;
		if (isset($args['exception'])) $exception = $args['exception'];

		// Get the default exception noun if set
		$exceptionNoun = self::$exception_noun;

		// Add defaults
		$args = array_merge(array(
			 // HTTP response code & description.
			'code' => 500,
			'description' => null,
			 // Response body. Overrides the calculation from noun
			'body' => null,
			// Noun to respond with
			'noun' => ($exception && $exceptionNoun) ? new $exceptionNoun($exception) : null
		), $args);

		// Default "response" which we build into. Not returned, because the exception has it's own response
		$response = new SS_HTTPResponse();

		// If there's a formatter
		if ($args['noun'] && ($formatter = RESTFormatter::get_formatter($this->request))) {
			// Format the response. Revert back to default response if we got nothing.
			$response = $formatter->format($args['noun'], array('*'));
			if (!$response) $response = new SS_HTTPResponse();
		}
		else if ($exception) {
			$exception = $args['exception'];
			$response->setBody($exception->getMessage()."\n");
			$response->addHeader('Content-Type', 'text/plain');
		}

		// If a specific body was provided, use that
		if ($args['body']) $response->setBody($args['body']);

		// Build an exception with those details
		$e = new SS_HTTPResponse_Exception($response->getBody(), $args['code'], $args['description']);
		$exceptionResponse = $e->getResponse();

		// Add user specified headers
		foreach ($response->getHeaders() as $k => $v) $exceptionResponse->addHeader($k, $v);

		throw $e;
	}

	public function GET(SS_HTTPRequest $request) { $this->httpError(403); }
	public function POST(SS_HTTPRequest $request) { $this->httpError(403); }
	public function PUT(SS_HTTPRequest $request) { $this->httpError(403); }
	public function DELETE(SS_HTTPRequest $request) { $this->httpError(403); }
}
