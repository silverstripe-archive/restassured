<?php

class RESTAssured_Bar extends RESTItem implements TestOnly {
	protected $ID; protected $BarA; protected $BarB;

	static $casting = array (
		'BarA' => 'Varchar',
		'BarB' => 'Varchar',
	);

	function __construct($values = null) {
		if($values) foreach($values as $k => $v) $this->$k = $v;
		parent::__construct();
	}

	function getID() {
		return $this->ID;
	}
}

class RESTAssured_Bar_Handler extends RESTItem_Handler implements TestOnly {
	static $allowed_actions = array('GET');

	function GET() {
		return $this->respondWith('ID', 'BarA', 'BarB');
	}
}

class RESTAssured_Bars extends RESTCollection implements TestOnly {
	function getItems() {
		return array();
	}

	function getItem($id) {
		return null;
	}
}

class RESTAssured_Bars_Handler extends RESTCollection_Handler implements TestOnly {
	static $allowed_actions = array('POST');

	function POST() {
		$res = $this->markAsNested(
			new RESTAssured_Bar(array('ID' => 1, 'BarA' => $_POST['BarA'], 'BarB' => $_POST['BarB']))
		);

		return $this->respondAs(array(
			'code' => 201,
			'location' => $res->Link(),
			'noun' => $res,
			'fields' => 'ID, BarA, BarB'
		));
	}
}

