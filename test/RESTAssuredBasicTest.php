<?php

require_once 'testapi/RESTAssured_Foo.php';
require_once 'testapi/RESTAssured_Bar.php';

/**
 * TODO: Many of the tests are brittle, assuming they know the example data that is constructed by the above examples.
 */
class RESTAssuredBasicTest extends FunctionalTest {

	function setUp() {
		RESTRoot::register('Foo', 'RESTAssured_Foos');
		parent::setUp();
	}

	function tearDown() {
		RESTRoot::unregister('Foo');
		parent::tearDown();
	}

	function testBasicCollectionLookup() {
		$res = $this->get('/rest/Foo');
		$this->assertEquals($res->getStatusCode(), 200);

		$val = json_decode($res->getBody(), true);
		$this->assertEquals($val['$type'], 'RESTAssured_Foos');
		$this->assertEquals(count($val['Items']), 4);
	}

	function testBasicItemLookup() {
		$res = $this->get('/rest/Foo/1');
		$this->assertEquals($res->getStatusCode(), 200);

		$val = json_decode($res->getBody(), true);
		$this->assertEquals($val, array(
			'$type' => 'RESTAssured_Foo',
			'ID' => 1,
			'Baz' => 2,
			'Qux' => 'Zampf'
		));

		$this->assertEquals($res->getHeader('x-some-sideinfo'), 'Zap!');
	}

	function testMissingLookup() {
		$res = $this->get('/rest/Doesntexist');
		$this->assertEquals($res->getStatusCode(), 404);
	}

	function testUnacceptable() {
		$res = $this->get('/rest/Foo', null, array('Accept' => 'application/nosuchtype'));
		$this->assertEquals($res->getStatusCode(), 406);
	}

	function testForbidden() {
		$res = $this->post('/rest/Foo/1', array());
		$this->assertEquals($res->getStatusCode(), 403);
	}

	function testPostRedirectsToCreatedObjectAndIncludesObjectInBody() {
		$this->autoFollowRedirection = false;
		$res = $this->post('/rest/Foo', array('Baz' => 'ABaz', 'Qux' => 'AQux'));
		$this->autoFollowRedirection = true;

		// Make sure we got a redirection
		$this->assertEquals($res->getStatusCode(), 201);

		// Make sure it pointed where we expect
		$this->assertEquals($res->getHeader('Location'), Director::absoluteURL('rest/Foo/6'));

		// Make sure the content type is right
		$this->assertEquals($res->getHeader('Content-Type'), 'application/json');

		// Make sure we got the content we expect
		$val = json_decode($res->getBody(), true);
		$this->assertEquals($val, array(
			'$type' => 'RESTAssured_Foo',
			'ID' => 6,
			'Baz' => 'ABaz',
			'Qux' => 'AQux'
		));
	}

	function testNestedPostRedirectsToCreatedObjectAndIncludesObjectInBody() {
		$this->autoFollowRedirection = false;
		$res = $this->post('/rest/Foo/1/Bar', array('BarA' => 'ABarA', 'BarB' => 'ABarB'));
		$this->autoFollowRedirection = true;

		// Make sure we got a created
		$this->assertEquals($res->getStatusCode(), 201);

		// Make sure it pointed where we expect
		$this->assertEquals($res->getHeader('Location'), Director::absoluteURL('rest/Foo/1/Bar/1'));

		// Make sure the content type is right
		$this->assertEquals($res->getHeader('Content-Type'), 'application/json');

		// Make sure we got the content we expect
		$val = json_decode($res->getBody(), true);
		$this->assertEquals($val, array(
			'$type' => 'RESTAssured_Bar',
			'ID' => 1,
			'BarA' => 'ABarA',
			'BarB' => 'ABarB'
		));
	}
}