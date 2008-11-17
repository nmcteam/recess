<?php
Library::import('recess.framework.controllers.Controller');
Library::import('recess.http.responses.NotFoundResponse');
Library::import('recess.http.responses.OkResponse');

Library::import('recess.apps.ide.models.RecessReflectorClass');
Library::import('recess.apps.ide.models.RecessReflectorPackage');
Library::import('recess.apps.ide.models.RecessReflectorProperty');
Library::import('recess.apps.ide.models.RecessReflectorMethod');

/**
 * !View Native, Prefix: reflector/
 */
class RecessReflectorController extends Controller {
	
	/** !Route GET, reflector/index/ */
	public function index() {
		$this->recursiveIndex($_ENV['dir.apps']);
		$this->recursiveIndex($_ENV['dir.lib']);
		exit;
	}
	
	private function recursiveIndex($base, $dir = '') {
		$dirInfo = scandir($base . $dir);
		foreach($dirInfo as $item) {
			$location = $base . $dir . '/' . $item;
			if(is_dir($location) && $item[0] != '.') {
				$this->recursiveIndex($base, $dir . '/' . $item);
			} else {
				if($item[0] == '.' || strrpos($item, '.class.php') === false) { continue; }
				$fullyQualified = str_replace('/', '.', $dir . '/' . $item);
				if($fullyQualified[0] == '.') {
					$fullyQualified = substr($fullyQualified, 1);
				}
				$fullyQualified = str_replace('..', '.', $fullyQualified);
				$fullyQualified = str_replace('.class.php','',$fullyQualified);
				
				$this->indexClass($fullyQualified, $dir . '/' . $item);
			}
		}
	}
	
	private function indexClass($fullyQualifiedClassName, $dir) {
		if(!Library::classExists($fullyQualifiedClassName)) {
			return false;
		}

		$model = Library::getClassName($fullyQualifiedClassName);
		$reflectorClass = new RecessReflectorClass();
		$reflectorClass->name = $model;
		if(!$reflectorClass->exists()) {
			$reflectorClass->fromClass($model, $dir);
		}
		
		return $reflectorClass;
	}
	
	/** !Route GET, reflector/class/$fullyQualifiedModel */
	public function classInfo($fullyQualifiedModel) {
		$result = $this->indexClass($fullyQualifiedModel, '');
		
		if($result === false) {
			return new NotFoundResponse($this->request);
		}
		
		$model = Library::getClassName($fullyQualifiedModel);
		$reflection = new RecessReflectionClass($model);
		
		$this->reflection = $reflection;
		$this->relationships = Model::getRelationships($model);
		$this->columns = Model::getColumns($model);
		$this->table = Model::tableFor($model);
		$this->source = Model::sourceNameFor($model);
		$this->reflector = $result;
	}
	 
	/** !Route GET, reflector/package/$package */
	function packageInfo ($package) {
		
		Library::import('recess.apps.ide.models.RecessReflectorPackage');
		$package = new RecessReflectorPackage($package);
		$this->package = $package->find()->first();
		
	}
	
	
	/** !Route GET, reflector/model/$fullyQualifiedModel/create */
	function createTable ($fullyQualifiedModel) {
		if(!Library::classExists($fullyQualifiedModel)) {
			return new NotFoundResponse($this->request);
		}	

		$this->sql = '';

		$class = Library::getClassName($fullyQualifiedModel);
		
		$props = Model::getProperties($class);
		
		$table = Model::tableFor($class);
		
		$this->sql = 'CREATE TABLE ' . $table . ' ( ';
		
		$first = true;
		foreach($props as $prop) {
			if($first) { $first = false; }
			else { $this->sql .= ', '; }
			
			$this->sql .= $prop->name . ' ' . $prop->type;
			
			if($prop->isPrimaryKey) { 
				$this->sql .= ' PRIMARY KEY';
				if($prop->autoincrement) {
					$this->sql .= ' AUTOINCREMENT';
				}
			}
		}
		
		$this->sql .= ' );';
		
		$source = Model::sourceFor($class);
		
		$source->executeStatement($this->sql, array());
		
	}
	
}

?>