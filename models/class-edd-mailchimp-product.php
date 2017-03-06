<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_Mailchimp_Product extends EDD_Mailchimp_Model {

  public $download;

  public function __construct( $download ) {
    if ( is_integer($download) ) {
      $this->download = new EDD_Download($download)
    } elseif ( is_object( $download ) && get_class($download) === 'EDD_Download' ) {
      $this->download = $download;
    } else {
      $this->download = false;
    }
  }

  /**
   * [create description]
   * @return [type] [description]
   */
  public function create() {
    if ( ! $this->download ) {
      return false;
    }

    $product = array(
      'id'          => $this->download->ID,
      'title'       => $this->download->post_title,
      'handle'      => $this->download->post_name, // slug
      'url'         => get_permalink( $this->download->id ),
      'description' => $this->download->post_excerpt,
      'type'        => $this->_get_category_name(),
      'vendor'      => bloginfo('name'),
      'variants'    => $variants,
      'published_at_foreign' => $this->download->post_date
    );

    // Add Product Image URL from Download Featured Image, if it exists.
    if (has_post_thumbnail( $this->download->ID ) ) {
      $image = wp_get_attachment_image_src( get_post_thumbnail_id( $this->download->ID ), 'single-post-thumbnail' );
      $product['image_url'] = $image[0];
    }

    $ProductsBatch = $api->new_batch();
    $ProductsBatch->post("product_$i", 'ecommerce/stores/' . self::_get_store_id() . '/products', $product);
    // The result includes a batch ID. At a later point, you can check the status of your batch:
    $result = $ProductsBatch->execute();

    // Check in on it.
    $ProductsBatch = $api->new_batch($products_batch_id);
    $result = $ProductsBatch->check_status();
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
    $sku = $download->get_sku();

    if ( $my_download->has_variable_prices() ) {

      $variable_prices = $my_download->get_prices();

      foreach ( $variable_prices as $id => $data ) {
        $variant = array(
          'id'    => $download->ID . '_' . $id,
          'title' => $data['name'],
          'url'   => get_permalink( $download->ID ),
          'price' => $data['amount'],
        );

        if ( $sku !== '-' ) {
          $variant['sku'] = $sku . '_' . $id;
        }

        $variants[] = $variant;
      }

    } else {

      $variant = array(
        'id'    => (string) $download->ID . '_1',
        'title' => $download->post_title,
        'url'   => get_permalink( $download->ID ),
        'price' => $download->get_price(),
      );

      if ( $sku !== '-' ) {
        $variant['sku'] = $sku;
      }

      $variants[] = $variant;
    }

    return $variants;
  }
}