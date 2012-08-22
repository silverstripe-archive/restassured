<?php

class RESTException extends Exception {

	/**
	 * RESTExceptions _must_ have a message and a code
	 * @param $message
	 * @param $code - An HTTP status code
	 * @param null $previous
	 */
	function __construct($message, $code, $previous = null) {
		parent::__construct($message, $code, $previous);
	}

}