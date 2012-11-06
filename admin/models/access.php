<?php

class Model_Access extends RedBean_SimpleModel {

	function fields() {
		// Add fields here
		$fields['path']       = array('type'=>'text', 'label'=>'path', 'help'=>'');

		// Settings
		$fields['add']        = true;
		$fields['edit']       = true;
		$fields['delete']     = true;
		return $fields;
	}

	function settings() {
		$dict = App::getSettings($this->fields());
		return $dict;
	}

	function view() {
		global $module;
		$dict = App::view($module, __CLASS__); // Region optional
		return $dict;
	}

	function count() {
		global $module;
		$dict = App::count($module); // Region optional
		return $dict;
	}

	function add() {
		return App::buildForm($this->fields());
	}

	function edit($id) {
		global $module;
		sanitize($id);
		return App::buildEditform($this->fields(), $module, $id);
	}

	function trash($id) {
		global $module;
		sanitize($id);
		return App::trash($id, $module);
	}
}