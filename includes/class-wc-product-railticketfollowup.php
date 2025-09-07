<?php

/**
 * Plugin Name: WooCommerce railticketProduct Type
 */

 
class WC_Product_RailTicketFollowUp extends WC_Product {

    public function __construct( $product ) {

        $this->product_type = 'railticketfollowup';

        $this->virtual = true;
        $this->downloadable = false;
        $this->manage_stock = true;   

        parent::__construct( $product );
        //$this->set_virtual(false);
       // $this->set_prop( 'virtual', true );
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
