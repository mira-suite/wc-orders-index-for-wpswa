<?php

namespace MiraSuite\AlgoliaOrdersIndex\Indices;


class OrdersIndex extends \Algolia_Index
{

    /**
     * Get the admin name for this index.
     *
     * @return string The name displayed in the admin UI.
     */
    public function get_admin_name()
    {
        return __('Orders', 'wp-search-with-algolia-orders-index');
    }

    /**
     * Check if this index supports the given item.
     *
     * A performing function that return true if the item can potentially
     * be subject for indexation or not. This will be used to determine if an item is part of the index
     * As this function will be called synchronously during other operations,
     * it has to be as lightweight as possible. No db calls or huge loops.
     *
     * @param mixed $item The item to check against.
     *
     * @return bool
     *
     */
    public function supports($item)
    {
        return class_exists('\WC_Order') && $item instanceof \WC_Order;
    }

    /**
     * Check if the item should be indexed.
     *
     * @param \WC_Order $item The item to check.
     *
     * @return bool
     *
     */
    protected function should_index($item)
    {
        return true; // All orders should be indexed.
    }

    /**
     * Get records for the item.
     *
     * @param \WC_Order $item The item to get records for.
     *
     * @return array
     *
     */
    protected function get_records($item)
    {
        if ( ! defined( 'WC_VERSION' ) ) {
            return array();
        }

        if ( ! $item instanceof \WC_Order ) {
            // Only support default order type for now.
            return array();
        }

        $date_created           = $item->get_date_created();
        $date_created_timestamp = null !== $date_created ? $date_created->getTimestamp() : 0;
        $date_created_i18n      = null !== $date_created ? $date_created->date_i18n( get_option( 'date_format' ) ) : '';

        $record = array(
            'objectID'              => $this->get_object_id((int)$item->get_id(), 0),
            'id'                    => (int) $item->get_id(),
            'type'                  => $item->get_type(),
            'number'                => (string) $item->get_order_number(),
            'status'                => $item->get_status(),
            'status_name'           => wc_get_order_status_name( $item->get_status() ),
            'date_timestamp'        => $date_created_timestamp,
            'date_formatted'        => $date_created_i18n,
            'order_total'           => (float) $item->get_total(),
            'formatted_order_total' => $item->get_formatted_order_total(),
            'items_count'           => (int) $item->get_item_count(),
            'payment_method_title'  => $item->get_payment_method_title(),
            'shipping_method_title' => $item->get_shipping_method(),
        );

        // Add user info.
        $user = $item->get_user();
        if ( $user ) {
            $record['customer'] = array(
                'id'           => (int) $user->ID,
                'display_name' => $user->first_name . ' ' . $user->last_name,
                'email'        => $user->user_email,
            );
        }

        $billing_country   = $item->get_billing_country();
        $billing_country   = isset( WC()->countries->countries[ $billing_country ] ) ? WC()->countries->countries[ $billing_country ] : $billing_country;
        $record['billing'] = array(
            'display_name' => $item->get_formatted_billing_full_name(),
            'email'        => $item->get_billing_email(),
            'phone'        => $item->get_billing_phone(),
            'company'      => $item->get_billing_company(),
            'address_1'    => $item->get_billing_address_1(),
            'address_2'    => $item->get_billing_address_2(),
            'city'         => $item->get_billing_city(),
            'state'        => $item->get_billing_state(),
            'postcode'     => $item->get_billing_postcode(),
            'country'      => $billing_country,
        );

        $shipping_country   =  $item->get_shipping_country();
        $shipping_country   = isset( WC()->countries->countries[ $shipping_country ] ) ? WC()->countries->countries[ $shipping_country ] : $shipping_country;
        $record['shipping'] = array(
            'display_name' => $item->get_formatted_shipping_full_name(),
            'company'      =>  $item->get_shipping_company(),
            'address_1'    =>  $item->get_shipping_address_1(),
            'address_2'    =>  $item->get_shipping_address_2(),
            'city'         =>  $item->get_shipping_city(),
            'state'        =>  $item->get_shipping_state(),
            'postcode'     =>  $item->get_shipping_postcode(),
            'country'      => $shipping_country,
        );

        // Add items.
        $record['items'] = array();
        foreach ( $item->get_items() as $item_id => $order_item ) {

            /* @var \WC_Order_Item_Product $order_item */
            $product = $order_item->get_product();
            $record['items'][] = array(
                'id'   => (int) $item_id,
                'name' => apply_filters( 'woocommerce_order_item_name', esc_html( $order_item->get_name() ), $item, false ),
                'qty'  => (int) $order_item->get_quantity(),
                'sku'  => $product instanceof \WC_Product ? $product->get_sku() : '',
            );
        }

        return array( $record );
    }


    /**
     * Get re-index items count.
     *
     * @return int
     *
     */
    protected function get_re_index_items_count()
    {
        global $wpdb;
        if($this->is_hpos_enabled()) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders");
        } else {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} where post_type='shop_order'");
        }
        return $count;
    }

    /**
     * Get settings.
     *
     * @return array
     *
     */
    protected function get_settings()
    {
        return array(
            'searchableAttributes'             => array(
                'id',
                'number',
                'customer.display_name',
                'customer.email',
                'billing.display_name',
                'shipping.display_name',
                'billing.email',
                'billing.phone',
                'billing.company',
                'shipping.company',
                'billing.address_1',
                'shipping.address_1',
                'billing.address_2',
                'shipping.address_2',
                'billing.city',
                'shipping.city',
                'billing.state',
                'shipping.state',
                'billing.postcode',
                'shipping.postcode',
                'billing.country',
                'shipping.country',
                'items.sku',
                'status_name',
                'order_total',
            ),
            'disableTypoToleranceOnAttributes' => array(
                'id',
                'number',
                'items.sku',
                'billing.phone',
                'order_total',
                'billing.postcode',
                'shipping.postcode',
            ),
            'customRanking'                    => array(
                'desc(date_timestamp)',
            ),
            'attributesForFaceting'            => array(
                'customer.display_name',
                'type',
                'items.sku',
                'order_total',
            ),
        );
    }

    /**
     * Get synonyms.
     *
     * @return array
     *
     */
    protected function get_synonyms()
    {
        return (array) apply_filters( 'algolia_orders_index_synonyms', array() );

    }

    /**
     * Get ID.
     *
     * @return string
     */
    public function get_id()
    {
        return 'orders';
    }

    /**
     * Get items.
     *
     * @param int $page       The page.
     * @param int $batch_size The batch size.
     *
     * @return array
     */
    protected function get_items($page, $batch_size)
    {
        $records = wc_get_orders([
            'page'  => $page,
            'limit' => $batch_size,
        ]);

        return $records;
    }

    /**
     * Delete item.
     *
     * @param \WC_Order $item The item to delete.
     */
    public function delete_item($item, $wait = false)
    {
        $this->assert_is_supported( $item );

        $records_count = $this->get_post_records_count( $item->get_id() );
        $object_ids    = array();
        for ( $i = 0; $i < $records_count; $i++ ) {
            $object_ids[] = $this->get_object_id( $item->get_id(), $i );
        }

        if ( empty( $object_ids ) ) {
            return;
        }

        if ( $wait ) {
            $this->get_index()->deleteObjects( $object_ids )->wait();
            return;
        }

        $this->get_index()->deleteObjects( $object_ids );
    }


    /**
     * Get post object ID.
     *
     * @param int $order_id      The WC_Order ID.
     * @param int $record_index The split record index.
     *
     * @return string
     */
    private function get_object_id( $order_id, $record_index ) {
        /**
         * Allow filtering of the post object ID.
         *
         * @param string $post_object_id The Algolia objectID.
         * @param int    $post_id        The WordPress post ID.
         * @param int    $record_index   Index of the split post record.
         */
        return apply_filters(
            'algolia_get_order_object_id',
            $order_id . '-' . $record_index,
            $order_id,
            $record_index
        );
    }


    /**
     * Update the records
     * @param \WC_Order $item
     * @param array $records
     *
     * @return void
     */
    protected function update_records($item, array $records)
    {
        // If there are no records, parent `update_records` will take care of the deletion.
        // In case of posts, we ALWAYS need to delete existing records.
        if ( ! empty($records)) {
            /**
             * Filters whether or not to use synchronous wait on record update operations.
             *
             * @param bool      $value   Whether or not to use synchronous wait. Default false.
             * @param \WC_Order $item    Current post object being updated.
             * @param array     $records The records
             *
             * @return bool
             *
             */
            $should_wait = (bool)apply_filters('algolia_should_wait_on_delete_item', false, $item, $records);
            $this->delete_item($item, $should_wait);
        }

        parent::update_records($item, $records);

        // Keep track of the new record count for future updates relying on the objectID's naming convention .
        $new_records_count = count($records);
        $this->set_post_records_count($item, $new_records_count);

    }


    /**
     * Get post records count.
     *
     * @param int $post_id The post ID.
     *
     * @return int
     */
    private function get_post_records_count( $post_id ) {
        $order = wc_get_order($post_id);
        return $order ? (int) $order->get_meta('algolia_' . $this->get_id() . '_records_count') : 0;
    }

    /**
     * Get post records count.
     *
     * @param \WC_Order $item  The post.
     * @param int     $count The count of records.
     */
    private function set_post_records_count( $item, $count ) {
        $item->update_meta_data('algolia_' . $this->get_id() . '_records_count', (int) $count);
        $item->save();
    }


    /**
     * Is high performance order storage enabled?
     * @return bool
     */
    private function is_hpos_enabled() {
        return class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
            && method_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class, 'custom_orders_table_usage_is_enabled')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }
}