<?php

/**
 * @package restassured
 */
class RESTDocGenerator_MethodFilter {

	function __construct($classReflection) {
		$this->methods = $classReflection->getMethods();
	}

	function asArray() {
		$res = array();
		foreach ($this->methods as $method) $res[$method->getName()] = $method;
		return $res;
	}

	private $negate = false;

	function not() {
		$this->negate = true;
		return $this;
	}

	private $method;
	private $args;

	function __call($name, $arguments) {
		// Store the method to eventually call and the arguments
		$this->method = '_'.$name;
		$this->args = $arguments;
		// Call array filter, which will call _callback
		$this->methods = array_filter($this->methods, array($this, '_callback'));
		// Reset negate
		$this->negate = false;
		// And return self
		return $this;
	}

	protected function _callback($reflection) {
		// Merge the array item with the externally passed arguments
		$args = array_merge(array($reflection), $this->args);
		// Call the particular filtering checker
		$res = call_user_func_array(array($this, $this->method), $args);
		// Return that value, taking negate into account
		return $this->negate ? !$res : $res;
	}

	function _isSubclassOf($reflection, $filteringClass) {
		return $reflection->getDeclaringClass()->isSubclassOf($filteringClass);
	}

	function _isPublic($reflection) {
		return $reflection->isPublic();
	}

	function _isAbstract($reflection) {
		return $reflection->isAbstract();
	}

	function _isStatic($reflection) {
		return $reflection->isStatic();
	}

	function _isCallablePublicMethod($reflection) {
		return $reflection->isPublic() && !$reflection->isAbstract() && !$reflection->isStatic();
	}

	function _startsWith($reflection, $startsWithString) {
		return strpos($reflection->getName(), $startsWithString) === 0;
	}

	function _nameInArray($reflection, $filteringArray) {
		if (!$filteringArray) return false;
		return in_array($reflection->getName(), $filteringArray);
	}

	function _hasNumberOfParameters($reflection, $noOfParams) {
		return $reflection->getNumberOfParameters() == $noOfParams;
	}
}

/**
 * @package restassured
 */
class RESTDocGenerator_ActionInspector extends ViewableData {

	function __construct($nounInspector, $handlerInspector, $name, $reflection) {
		$this->nounInspector = $nounInspector;
		$this->handlerInspector = $handlerInspector;
		$this->name = $name;
		$this->reflection = $reflection;

		$this->block = RESTDocblockParser::parse($reflection);

		parent::__construct();
	}

	function getName() {
		return $this->name;
	}

	function getDescription() {
		return $this->block['body'];
	}

	function getData() {
		if (!isset($this->block['data'])) return;

		$request = $this->block['data'];

		$type = (string)$request['as']['details'];
		if (!$type) $type = $this->nounInspector->noun;

		return new ArrayData(array(
			'Type'   => $type,
			'Fields' => (string)$request['fields']['details'],
			'Body'   => (string)$request['body']
		));
	}

	function getResponse() {
		$response = $this->block['responds-with'];

		$type = (string)$response['as']['details'];
		if (!$type) $type = $this->nounInspector->noun;

		return new ArrayData(array(
			'Code'   => (string)$response['details'],
			'Type'   => $type,
			'Fields' => (string)$response['fields']['details'],
			'Body'   => (string)$response['body']
		));
	}

	function getErrorResponses() {
		$res = new ArrayList();

		if ($this->block['responds-with-error']) foreach ($this->block['responds-with-error'] as $response) {
			$res->push(new ArrayData(array(
				'Code' => (string)$response['details'],
				'Body' => (string)$response['body'],
				'Type' => (string)$response['as']['details'],
				'Fields' => (string)$response['fields']['details']
			)));
		}

		return $res;
	}

	function getReturnedTypes() {
		$res = array();

		$type = (string)$this->block['data']['as']['details'];
		if ($type) $res[$type] = new RESTDocGenerator_NestingInspector($type);

		$type = (string)$this->block['responds-with']['as']['details'];
		if ($type) $res[$type] = new RESTDocGenerator_NestingInspector($type);

		if ($this->block['responds-with-error']) foreach ($this->block['responds-with-error'] as $response) {
			$type = (string)$response['as']['details'];
			if ($type) $res[] = new RESTDocGenerator_NestingInspector($type);
		}

		return $res;
	}
}

/**
 * @package restassured
 */
class RESTDocGenerator_HandlerInspector extends ViewableData {

	function __construct($nounInspector, $handler) {
		$this->nounInspector = $nounInspector;

		$this->handler = $handler;
		$this->reflection = new ReflectionClass($handler);

		$this->actionBlocks = array();
		foreach ($this->getActionMethodReflections() as $name => $reflection) {
			$this->actionBlocks[$name] = RESTDocblockParser::parse($reflection);
		}

		parent::__construct();
	}

	protected function getActionMethodReflections() {
		$allow = Object::get_static($this->handler, 'allowed_actions');
		$res = new RESTDocGenerator_MethodFilter($this->reflection);

		return $res->isSubclassOf('RESTNoun_Handler')->isCallablePublicMethod()->nameInArray($allow)->asArray();
	}

	function getActions() {
		$res = new ArrayList();
		foreach ($this->getActionMethodReflections() as $name => $reflection) {
			$res->push(new RESTDocGenerator_ActionInspector($this->nounInspector, $this, $name, $reflection));
		}
		return $res;
	}


}

/**
 * @package restassured
 */
class RESTDocGenerator_NestingInspector extends ViewableData {

	protected $parent;
	protected $link;
	
	function __construct($noun, $parent = null, $link = RESTASSURED_ROOT) {
		// Store the class, and a reflector since we'll use those a lot
		$this->noun = $noun;
		$this->reflection = new ReflectionClass($noun);

		// Store the parent & link from parent to here, used to get URL  & ID)
		$this->parent = $parent;
		$this->link = $link;

		// Parse the main class block
		$this->classBlock = RESTDocblockParser::parse($this->reflection);
		
		// Parse the method blocks
		$this->methodBlocks = array();
		foreach ($this->getPropertyMethodReflections() as $name => $methodReflection) {
			$this->methodBlocks[$name] = RESTDocblockParser::parse($methodReflection);
		}

		parent::__construct();
	}

	protected function getPropertyMethodReflections() {
		$res = new RESTDocGenerator_MethodFilter($this->reflection);
		return $res->isSubclassOf('RESTNoun')->isCallablePublicMethod()->startsWith('get')->hasNumberOfParameters(0)->asArray();
	}

	function getID() {
		$id = Convert::raw2att($this->noun);
		return ($this->parent) ? $this->parent->getID() . $id : $id;
	}

	function getURL() {
		return $this->parent ? Controller::join_links($this->parent->URL, $this->link) : $this->link;
	}
	
	function getName() {
		$stat = Object::get_static($this->noun, 'name');
		return $stat ? $stat : $this->noun;
	}

	function getClass() {
		return $this->noun;
	}

	function getType() {
		return is_subclass_of($this->noun, 'RESTItem') ? 'Item' : 'Collection';
	}

	function getDescription() {
		return $this->classBlock['body'];
	}

	function getNounChildrenStatic($stat) {
		$res = array();

		foreach (ClassInfo::ancestry($this->noun) as $class) {
			if (!is_subclass_of($class, 'RESTNoun')) continue;

			$local = Object::uninherited_static($class, $stat);

			if ($local) {
				if (is_array($res) && is_array($local)) $res = array_merge($res, $local);
				else $res = $local;
			}
		}

		return $res;
	}

	function getFields() {
		$fields = array();

		foreach($this->getNounChildrenStatic('casting') as $name => $type) {
			$fields[$name] = new ArrayData(array('Name' => $name, 'Type' => $type));
		}

		foreach ($this->getPropertyMethodReflections() as $name => $reflection) {
			$field = preg_replace('/^get/', '', $name);
			$doc = $this->methodBlocks[$name];

			// Decode the return type
			$return = $doc['return']['details'];
			$words = explode(' ', $return);
			$type = count($words) ? $words[0] : '';

			// If not in the fields yet (because it's not in $casting), add new type using return tag
			if (!isset($fields[$field])) $fields[$field] = new ArrayData(array('Name' => $field, 'Type' => $type));

			// Set the body
			$fields[$field]->Description = $doc['body'];
		}

		foreach ($fields as $name => $details) {
			$type = preg_replace('/\[([^\]]+)\]/', '$1', $details->Type);
			if (ClassInfo::is_subclass_of($type, 'RESTNoun')) {
				$fields[$name]->Link = $type;
			}
		}

		return new ArrayList($fields);
	}

	function getHandler() {
		$ancestry = array_reverse(ClassInfo::ancestry($this->noun));
		foreach ($ancestry as $class) {
			$class = $class . '_Handler';
			if (ClassInfo::exists($class)) return new RESTDocGenerator_HandlerInspector($this, $class);
		}
	}

	function getReturnedTypes() {
		$classes = array();

		if ($this->noun == 'RESTRoot') {
			$classes = RESTRoot::get_registered();
		}
		else {
			foreach ($this->methodBlocks as $name => $block) {
				$return = $block['return']['details']; $words = explode(' ', $return); $type = count($words) ? $words[0] : '';

				$name = preg_replace('/^get([A-Z])/', '$1', $name);
				if ($name == 'Items') $name = "{id}";
				$type = preg_replace('/^\[([^\]]+)\]$/', '$1', $type);


				if ($type && ClassInfo::is_subclass_of($type, 'RESTNoun')) {
					$classes[$name] = $type;
				}
			}
		}

		// Build a new class inspector for each
		$res = new ArrayList();
		foreach ($classes as $func => $class) {
			$inspect = new RESTDocGenerator_NestingInspector($class, $this, $func);
			$res->push($inspect);
		}

		return $res;
	}

	function getSubClasses($includeSelf = false) {
		$res = new ArrayList();

		foreach (ClassInfo::subclassesFor($this->noun) as $subClass) {
			if ($subClass == $this->noun) {
				if ($includeSelf) $res->push($this);
			}
			else {
				$res->push(new RESTDocGenerator_NestingInspector($subClass, $this->parent, $this->link));
			}
		}

		return $res;
	}
}

class RESTDocGenerator_TypeInspector extends ViewableData {

	protected $typeList = array();

	function getTypes() {
		$this->recursivelyCollectTypes(new RESTDocGenerator_NestingInspector('RESTRoot'));
		return new ArrayList($this->typeList);
	}

	function recursivelyCollectTypes($base) {
		foreach ($base->getSubClasses(true) as $inspector) {
			foreach ($inspector->getReturnedTypes() as $returned) {
				$noun = $returned->noun;

				if (!isset($this->typeList[$noun])) {
					$this->typeList[$noun] = $returned;
					$this->recursivelyCollectTypes($returned);
				}
			}

			foreach ($inspector->getHandler()->getActions() as $action) {
				foreach ($action->getReturnedTypes() as $returned) {
					$noun = $returned->noun;

					if (!isset($this->typeList[$noun])) {
						$this->typeList[$noun] = $returned;
						$this->recursivelyCollectTypes($returned);
					}
				}
			}
		}
	}

}

/**
 * @package restassured
 */
class RESTDocGenerator extends BuildTask {
	static function render() {
		$data = new ArrayData(array(
			'Types' => new RESTDocGenerator_TypeInspector(),
			'Actions' =>  new RESTDocGenerator_NestingInspector('RESTRoot')
		));

		return $data->renderWith('RESTDocGenerator');
	}

	function run($request) {
		file_put_contents(ASSETS_PATH . '/apidocs.html',  self::render());
	}
}

/**
 * @package restassured
 */
class RESTDocGenerator_Controller extends Controller {
	function index() {
		return RESTDocGenerator::render();
	}
}