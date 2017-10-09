<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_MailChimp_Product extends EDD_MailChimp_Model {

	public $download = null;

	public function __construct( $download = false ) {
		parent::__construct();

		$this->_set_download( $download );
		$this->_build();
	}

	/**
	 * Assign the download which this product is associated with.
	 *
	 * @param mixed $list EDD_Download | int $download_id
	 */
	protected function _set_download( $download ) {
		if ( is_integer($download) ) {
			$this->download = new EDD_Download($download);
		} elseif ( is_object( $download ) && get_class($download) === 'EDD_Download' ) {
			$this->download = $download;
		}

		$this->id = apply_filters('edd.mailchimp.product.id', $this->download->ID, $this->download);
	}


	/**
	 * [build description]
	 * @return [type] [description]
	 */
	protected function _build() {
		$record = array(
			'id'          => (string) $this->download->ID,
			'title'       => $this->download->post_title,
			'handle'      => $this->download->post_name, // slug
			'url'         => get_permalink( $this->download->id ),
			'description' => $this->download->post_excerpt,
			'type'        => $this->_get_category_name(),
			'vendor'      => get_bloginfo('name'),
			'variants'    => $this->_build_variants(),
			'published_at_foreign' => $this->download->post_date
		);

		// Add Product Image URL from Download Featured Image, if it exists.
		if (has_post_thumbnail( $this->download->ID ) ) {
			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $this->download->ID ), 'single-post-thumbnail' );
			$this->_record['image_url'] = $image[0];
		}

		$this->_record = apply_filters('edd.mailchimp.product', $record, $this->download);
		return $this;
	}

	/**
	 * Generate a category name based on the categories that this download belongs to, if any
	 * @return [type] [description]
	 */
	private function _get_category_name() {
		$terms = get_the_terms( $this->download->ID, 'download_category' );

		if ( $terms && ! is_wp_error( $terms ) ) {
			$categories = array();

			foreach ( $terms as $term ) {
				$categories[] = $term->name;
			}

			$category_name = join( " - ", $categories );
		} else {
			$category_name = 'Download';
		}

		return $category_name;
	}


	/**
	 * Build a variant list in the format MailChimp expects
	 * from EDD's variable pricing.
	 *
	 * @return array
	 */
	private function _build_variants() {
		$variants = array();
		$sku = $this->download->get_sku();

		if ( $this->download->has_variable_prices() ) {

			$variable_prices = $this->download->get_prices();

			foreach ( $variable_prices as $id => $data ) {
				$variant = array(
					'id'    => $this->download->ID . '_' . $id,
					'title' => $data['name'],
					'url'   => get_permalink( $this->download->ID ),
					'price' => $data['amount'],
				);

				if ( $sku !== '-' ) {
					$variant['sku'] = $sku . '_' . $id;
				}

				$variants[] = $variant;
			}

		} else {

			$variant = array(
				'id'    => (string) $this->download->ID,
				'title' => $this->download->post_title,
				'url'   => get_permalink( $this->download->ID ),
				'price' => $this->download->get_price(),
			);

			if ( $sku !== '-' ) {
				$variant['sku'] = $sku;
			}

			$variants[] = $variant;
		}

		return $variants;
	}
}
