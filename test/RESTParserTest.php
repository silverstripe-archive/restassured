<?php

class RESTParserTest_Result extends stdClass {
}

class RESTParserTest extends SapphireTest {

	protected function genRequest($accept = null, $body = '') {
		$req = new SS_HTTPRequest('POST', '/');
		if ($accept) $req->addHeader('Content-Type', $accept);
		if ($body) $req->setBody($body);
		return $req;
	}

	function testParserSelectionFromMimeType() {
		$parser = RESTParser::get_parser('application/json');
		$this->assertTrue($parser instanceof RESTParser_JSON);

		$parser = RESTParser::get_parser('text/xml');
		$this->assertTrue($parser instanceof RESTParser_XML);

		$parser = RESTParser::get_parser($this->genRequest('application/json'));
		$this->assertTrue($parser instanceof RESTParser_JSON);

		$parser = RESTParser::get_parser($this->genRequest('text/xml'));
		$this->assertTrue($parser instanceof RESTParser_XML);
	}

	static $json_body = '{
	"$type": "RESTParserTest_Result",
	"Foo": 1,
	"Bar": "Zam"
}';

	static $json_typeless_body = '{
	"Foo": 1,
	"Bar": "Zam"
}';

	static $xml_attrstyle_body = '<?xml version="1.0"?>
<RESTParserTest_Result Foo="1" Bar="Zam" />
';

	static $xml_elemstyle_body = '<?xml version="1.0"?>
<RESTParserTest_Result>
	<Foo>1</Foo>
	<Bar>Zam</Bar>
</RESTParserTest_Result>
';

	function testParserSelectionFromBody() {
		$parser = RESTParser::get_parser($this->genRequest(null, self::$json_body));
		$this->assertTrue($parser instanceof RESTParser_JSON);

		$parser = RESTParser::get_parser($this->genRequest(null, self::$xml_attrstyle_body));
		$this->assertTrue($parser instanceof RESTParser_XML);

		$parser = RESTParser::get_parser($this->genRequest(null, self::$xml_elemstyle_body));
		$this->assertTrue($parser instanceof RESTParser_XML);
	}

	function testParse() {
		foreach (array(self::$json_body, self::$xml_attrstyle_body, self::$xml_elemstyle_body) as $body) {
			$req = $this->genRequest(null, $body);
			$parser = RESTParser::get_parser($req);

			$res = new RESTParserTest_Result();
			$parser->parseInto($req, array('Foo', 'Bar'), $res);

			$this->assertEquals((int)@$res->Foo, 1);
			$this->assertEquals(@$res->Bar, 'Zam');
		}
	}

	/**
	 * @expectedException Exception
	 */
	function testJSONTypeMismatch() {
		$req = $this->genRequest(null, self::$json_body);
		$parser = RESTParser::get_parser($req);

		$res = new stdClass();
		$parser->parseInto($req, array('Foo', 'Bar'), $res);
	}

}