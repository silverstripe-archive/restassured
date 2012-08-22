<?php

class RESTAssured_Foo extends RESTItem implements TestOnly {
	protected $ID; protected $Baz; protected $Qux;

	static $casting = array (
		'Baz' => 'Integer',
		'Qux' => 'Varchar(255)'
	);

	function __construct($values = null) {
		if($values) foreach($values as $k => $v) $this->$k = $v;
		parent::__construct();
	}

	function getID() {
		return $this->ID;
	}

	function getBar() {
		return $this->markAsNested(new RESTAssured_Bars(), 'Bar');
	}
}


class RESTAssured_Foo_Handler extends RESTItem_Handler implements TestOnly {
	static $allowed_actions = array('GET');

	function GET(SS_HTTPRequest $request) {
		// If we get a request var set as 'Error', trigger an errpr
		if ($request->requestVar('Error')) {
			$this->addResponseHeader('x-exception-id', '1');
			return $this->respondWithError(404);
		}

		// Normal response - set a header, and respond
		$this->addResponseHeader('x-some-sideinfo', 'Zap!');
		return $this->respondWith('ID', 'Baz', 'Qux');
	}
}

class RESTAssured_Foos extends RESTCollection implements TestOnly {

	function getItems() {
		$res = array();
		for ($i = 1; $i < 5; $i++) {
			$res[] = new RESTAssured_Foo(array(
				'ID' => $i,
				'Baz' => '2',
				'Qux' => 'Zampf'
			));
		}
		return $res;
	}

	function getItem($id) {
		return $this->markAsNested(new RESTAssured_Foo(array(
			'ID' => $id,
			'Baz' => '2',
			'Qux' => 'Zampf'
		)));
	}
}

class RESTAssured_Foos_Handler extends RESTCollection_Handler implements TestOnly {
	static $allowed_actions = array('GET', 'POST');

	function GET() {
		return $this->respondWith('Items.ID', 'Items.Baz', 'Items.Qux');
	}

	function POST() {
		$res = $this->markAsNested(
			new RESTAssured_Foo(array('ID' => 6, 'Baz' => $_POST['Baz'], 'Qux' => $_POST['Qux']))
		);

		return $this->respondAs(array(
			'code' => 201,
			'location' => $res->Link(),
			'noun' => $res,
			'fields' => 'ID, Baz, Qux'
		));
	}
}