<?php

class RESTFormatterTest_A extends RESTItem implements TestOnly {

	static $casting = array(
		'Foo' => 'Int',
		'Bar' => 'Varchar(255)'
	);

	static $nested = array(
		'B' => 'RESTFormatterTest_B'
	);

	function __construct($id) {
		$this->ID = $id;
		$this->Foo = 4;
		$this->Bar = 'Ape';
		parent::__construct();
	}

	function getID() { return $this->ID; }

	function getB() {
		return new RESTFormatterTest_Bs();
	}

}

class RESTFormatterTest_Bs extends RESTCollection implements TestOnly {

	static $casting = array(
		'How' => 'Varchar(255)'
	);

	function getItems(){
		return array(
			new RESTFormatterTest_B(1),
			new RESTFormatterTest_B(2)
		);
	}

	function getItem($id) {
		return new RESTFormatterTest_B($id);
	}

	function getHow() {
		return 'Why?';
	}

}

class RESTFormatterTest_B extends RESTItem implements TestOnly {
	static $casting = array(
		'Zap' => 'Int',
		'Zing' => 'Varchar(255)'
	);

	function __construct($id) {
		$this->ID = $id;
		parent::__construct();
	}

	function getID() {
		return $this->ID;
	}

	function getZap() {
		return 5;
	}

	function getZing() {
		return 'Bonobo';
	}
}

class RESTFormatterTest extends SapphireTest {

	protected function genRequest($accept = null, $method = 'GET') {
		$req = new SS_HTTPRequest($method, '/');
		if ($accept) $req->addHeader('Accept', $accept);
		return $req;
	}

	function testFormatterSelectionFromMimeType() {
		$formatter = RESTFormatter::get_formatter('application/json');
		$this->assertTrue($formatter instanceof RESTFormatter_JSON);

		$formatter = RESTFormatter::get_formatter('text/xml');
		$this->assertTrue($formatter instanceof RESTFormatter_XML);

		$formatter = RESTFormatter::get_formatter($this->genRequest('application/json'));
		$this->assertTrue($formatter instanceof RESTFormatter_JSON);

		$formatter = RESTFormatter::get_formatter($this->genRequest('text/xml'));
		$this->assertTrue($formatter instanceof RESTFormatter_XML);
	}

	function testFormattingDirectFields() {
		$formatter = RESTFormatter::get_formatter('application/json');
		$r = $formatter->format(new RESTFormatterTest_A(1), array('Foo', 'Bar'));

		$this->assertEquals($r->getHeader('Content-Type'), 'application/json');
		$this->assertEquals($r->getBody(), json_encode(array(
			'$type'=> 'RESTFormatterTest_A',
			'Foo' => 4,
			'Bar' => 'Ape'
		)));
	}

	function testFormattingNestedFields() {
		$formatter = RESTFormatter::get_formatter('application/json');
		$r = $formatter->format(new RESTFormatterTest_A(1), array('Foo', 'Bar', 'B.How', 'B.Items.Zap', 'B.Items.Zing'));

		$this->assertEquals($r->getHeader('Content-Type'), 'application/json');
		$this->assertEquals($r->getBody(), json_encode(array(
			'$type'=> 'RESTFormatterTest_A',
			'Foo' => 4,
			'Bar' => 'Ape',
			'B' => array(
				'$type'=> 'RESTFormatterTest_Bs',
				'How' => 'Why?',
				'Items' => array(
					array(
						'$type'=> 'RESTFormatterTest_B',
						'Zap' => 5,
						'Zing' => 'Bonobo'
					),
					array(
						'$type'=> 'RESTFormatterTest_B',
						'Zap' => 5,
						'Zing' => 'Bonobo'
					)
				)
			)
		)));
	}

}