<?

/**
 * Plugin Name: WooCommerce railticketProduct Type
 */

 
class WC_Product_RailTicket extends WC_Product {

    public function __construct( $product ) {

        $this->product_type = 'railticket';

        $this->virtual = true;
        $this->downloadable = false;
        $this->manage_stock = true;   

        parent::__construct( $product );
        //$this->set_virtual(false);
       // $this->set_prop( 'virtual', true );
    }

    /**
     * Get the add to url used mainly in loops.
     *
     * @return string
     */
    public function add_to_cart_url() {
        $url = $this->is_purchasable() && $this->is_in_stock() ? remove_query_arg(
            'added-to-cart',
            add_query_arg(
                array(
                    'add-to-cart' => $this->get_id(),
                ),
                ( function_exists( 'is_feed' ) && is_feed() ) || ( function_exists( 'is_404' ) && is_404() ) ? $this->get_permalink() : ''
            )
        ) : $this->get_permalink();
        return apply_filters( 'woocommerce_product_add_to_cart_url', $url, $this );
    }

    /**
     * Get the add to cart button text.
     *
     * @return string
     */
    public function add_to_cart_text() {
        $text = $this->is_purchasable() && $this->is_in_stock() ? __( 'Add to cart', 'woocommerce' ) : __( 'Read more', 'woocommerce' );

        return apply_filters( 'woocommerce_product_add_to_cart_text', $text, $this );
    }

    /**
     * Get the add to cart button text description - used in aria tags.
     *
     * @since 3.3.0
     * @return string
     */
    public function add_to_cart_description() {
        /* translators: %s: Product title */
        $text = $this->is_purchasable() && $this->is_in_stock() ? __( 'Add &ldquo;%s&rdquo; to your cart', 'woocommerce' ) : __( 'Read more about &ldquo;%s&rdquo;', 'woocommerce' );

        return apply_filters( 'woocommerce_product_add_to_cart_description', sprintf( $text, $this->get_name() ), $this );
    }

    public function is_purchaseable() {
        return true;
    }

    public function is_in_stock() {
        return true;
    }

    public function get_sold_individually( $context = 'view' ) {
        return true;
    }

    public function get_virtual( $context = 'view' ) {
        return true;
    }


}
