- Install Node
- Install Yarn
`yarn install`

- Run build
`webpack`

# Examples

```
  $list = new EDD_MailChimp_List('list_id_goes_here');
  $store = EDD_MailChimp_Store::find_or_create( $list );

  $downloads = new WP_Query( array(
    'post_type'        => 'download',
    'posts_per_page'   => -1,
  ) );

  foreach ( $downloads->posts as $download ) {
    $product = new EDD_MailChimp_Product( $download->ID );
    $store->products->add( $product );
  }

  // $order = new EDD_MailChimp_Order( $payment );
  // $store->orders->add($order);
  // $store->carts->add($cart);
  // $store->customers->add($customer);

// Get all MailChimp Store Products
$products = $store->products();
```