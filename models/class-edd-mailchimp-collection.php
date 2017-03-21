<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Collection extends EDD_MailChimp_API {

	public function __construct($parent, $subresource) {
		parent::__construct();
		$this->_endpoint = $parent->_resource . '/' . $subresource;
	}

	/**
	 * Fetch all subresource records.
	 *
	 * @return array
	 */
	public function all() {
		return $this->api->get( $this->_endpoint, array( 'count' => 100 ) );
	}


	/**
	 * Adds an object to a parent collection.
	 *
	 * @todo consider returning the object class, eg. EDD_MailChimp_Product
	 *
	 * @return array
	 */
	public function add( $object ) {
		$object = $this->_set_resource($object);

		if ( $object->exists() ) {
			return $this->api->patch( $object->_resource, $object->get_record() );
		} else {
			return $this->api->post( $this->_endpoint, $object->get_record() );
		}
	}


	/**
	 * Delete the record.
	 *
	 * @return 204 | Error
	 */
	public function remove( $object ) {
		$this->api->delete( $this->_resource );
	}

	/**
	 * Set the resource on the passed object
	 *
	 * @param EDD_MailChimp_Model $object
	 */
	protected function _set_resource( $object ) {
		$object->_resource = $this->_endpoint . '/' . $object->id;
		return $object;
	}

}
