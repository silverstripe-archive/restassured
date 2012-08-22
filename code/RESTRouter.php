<?php

class RESTRouter extends Controller {

	protected $context;

	/**
	 * We don't want Controller's handling of URLs, but we do need this to appear to go through the usual
	 * startup procedure (push controller, call init, etc).
	 *
	 * So this is a duplication of Controller#handleRequest, except for the ending stanza
	 */
	function handleRequest(SS_HTTPRequest $request, DataModel $model) {
		if(!$request) user_error("Controller::handleRequest() not passed a request!", E_USER_ERROR);

		$this->pushCurrent();

		$this->urlParams = $request->allParams();
		$this->request = $request;
		$this->response = new SS_HTTPResponse();

		$this->extend('onBeforeInit');

		// Init
		$this->baseInitCalled = false;
		$this->init();
		if(!$this->baseInitCalled) user_error("init() method on class '$this->class' doesn't call Controller::init().  Make sure that you have parent::init() included.", E_USER_WARNING);

		$this->extend('onAfterInit');

		// If we had a redirection or something, halt processing.
		if(!$this->response->isFinished()) {
			$this->response = $this->routeRequest($request);
		}

		$this->popCurrent();
		return $this->response;
	}

	function routeRequest(SS_HTTPRequest $request) {
		// Handle the routing

		$noun = singleton('RESTRoot');

		while (!$request->allParsed()) {
			$matched = false;

			if($params = $request->match('$Next!', true)) {
				$matched = true;
				$next = $params['Next'];

				try {
					if (method_exists($noun, 'getItem')) $noun = $noun->getItem($next);
					else $noun = $noun->$next;
				} catch (Exception $e) {
					if ($e instanceof SS_HTTPResponse_Exception) {
						throw $e;
					} elseif ($e instanceof RESTException) {
						$handler = $this->getHandler($noun);
						$handler->respondWithError(array('code' => $e->getCode(), 'exception' => $e));
					} else {
						$handler = $this->getHandler($noun);
						$handler->respondWithError(array('code' => 500, 'exception' => $e));
					}
				}

				if (!$noun)	$this->httpError(404);
			}

			if (!$matched)	$this->httpError(404);
		}

		// Find the handler and call

		$handler = $this->getHandler($noun);
		return $handler->handleRequest($request);
	}

	function getHandler($noun) {
		$ancestry = array_reverse(ClassInfo::ancestry($noun->class));
		foreach ($ancestry as $class) {
			$class = $class . '_Handler';
			if (ClassInfo::exists($class)) return new $class($noun);
		}

		user_error("Couldn't find a handler for REST Noun ".$noun, E_USER_ERROR);
	}

}