<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Model extends EDD_MailChimp_API {

	protected $_record = array();

	/**
	 * Fetch all remote MailChimp records based on the
	 * called class name.
	 *
	 * @return [type] [description]
	 */
	public static function all() {
		$klass = new static;
		return $klass->api->get( $klass->_endpoint, array( 'count' => 100 ) );
	}


	/**
	 * Find a single MailChimp record by ID based
	 * on the called class name.
	 *
	 * @return [type] [description]
	 */
	public static function find( $id = '' ) {
		$klass = new static;
		return $klass->api->get( $klass->_endpoint . '/' . $id );
	}


	/**
	 * Check to see if an individual resource exists.
	 *
	 * @return bool
	 */
	public function exists() {
		$this->api->get( $this->_resource );
		return $this->api->success();
	}


	/**
	 * Save the record.
	 *
	 * @return [type] [description]
	 */
	public function save() {
		if ( $this->exists() ) {
			return $this->api->patch( $this->_resource, $this->_record );
		} else {
			return $this->api->post( $this->_endpoint, $this->_record );
		}
	}

	/**
	 * Delete the record.
	 *
	 * @return [type] [description]
	 */
	public function delete() {
		$this->api->delete( $this->_resource );
	}


	/**
	 * Record Getter
	 * @return [type] [description]
	 */
	public function get_record() {
		return $this->_record;
	}


	/**
	 * Check the property being called to determine if we're
	 * trying to return an associated object
	 *
	 * If it is a normal or cached property, just return it
	 *
	 * @param  string $name      Property name
	 * @param  mixed  $arguments passed arguments
	 * @return [type]            [description]
	 */
	public function __get( $prop_name ) {

		if ( array_key_exists( $prop_name, $this->_record ) ) {
			return $this->_record[$prop_name];
		}

		if ( ! empty( $this->_has_many ) && array_key_exists( $prop_name, $this->_has_many ) ) {
			$klass = new EDD_MailChimp_Collection($this, $prop_name);
			return $klass;
		}

		return null;
	}


	/**
	 * Fetch all of a model's subresources if it
	 * has_many of the associated products.
	 */
	public function __call( $method_name, $args ) {
		if ( ! empty( $this->_has_many ) && array_key_exists( $method_name, $this->_has_many ) ) {
			$klass = new EDD_MailChimp_Collection($this, $method_name);
			return $klass->all();
		}
	}
}
