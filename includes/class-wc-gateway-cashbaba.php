<?php

/**
 * cashbaba Payment gateway
 *
 * @author Md Jahangir Alam
 */
class WC_Gateway_cashbaba extends WC_Payment_Gateway {

    /**
     * Initialize the gateway
     */
    public function __construct() {
        $this->id                 = 'cashbaba';
        $this->icon               = false;
        $this->has_fields         = true;
        $this->method_title       = __( 'cashbaba', 'wc-cashbaba' );
        $this->method_description = __( 'Pay via cashbaba payment', 'wc-cashbaba' );
        $this->icon               = apply_filters( 'woo_cashbaba_logo', plugins_url( 'images/cashbaba-logo.png', dirname( __FILE__ ) ) );

        $title                    = $this->get_option( 'title' );
        $this->title              = empty( $title ) ? __( 'cashbaba', 'wc-cashbaba' ) : $title;
        $this->description        = $this->get_option( 'description' );
        $this->instructions       = $this->get_option( 'instructions', $this->description );

        $this->init_form_fields();
        $this->init_settings();

        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        add_action( 'woocommerce_thankyou_cashbaba', array( $this, 'thankyou_page' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options') );
    }

    /**
     * Admin configuration parameters
     *
     * @return void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'wc-cashbaba' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable cashbaba', 'wc-cashbaba' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title'   => __( 'Title', 'wc-cashbaba' ),
                'type'    => 'text',
                'default' => __( 'cashbaba Payment', 'wc-cashbaba' ),
            ),
            // 'description' => array(
            //     'title'       => __( 'Description', 'wc-cashbaba' ),
            //     'type'        => 'textarea',
            //     'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-cashbaba' ),
            //     'default'     => __( 'Send your payment directly to +8801****** via cashbaba. Please use your Order ID as the payment reference. Your order won\'t be shipped until the funds have cleared in our account.', 'wc-cashbaba' ),
            //     'desc_tip'    => true,
            // ),
            // 'instructions' => array(
            //     'title'       => __( 'Instructions', 'wc-cashbaba' ),
            //     'type'        => 'textarea',
            //     'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-cashbaba' ),
            //     'default'     => __( 'Send your payment directly to +8801****** via cashbaba. Please use your Order ID as the payment reference. Your order won\'t be shipped until the funds have cleared in our account.', 'wc-cashbaba' ),
            //     'desc_tip'    => true,
            // ),
            // 'trans_help' => array(
            //     'title'       => __( 'Transaction Help Text', 'wc-cashbaba' ),
            //     'type'        => 'textarea',
            //     'description' => __( 'Instructions that will be added to the transaction form.', 'wc-cashbaba' ),
            //     'default'     => __( 'Please enter your transaction ID from the cashbaba payment to confirm the order.', 'wc-cashbaba' ),
            //     'desc_tip'    => true,
            // ),
            'clientid' => array(
                'title' => __( 'Client Id', 'wc-cashbaba' ),
                'type'  => 'text',
            ),
            'client_secret' => array(
                'title' => __( 'Client Secret', 'wc-cashbaba' ),
                'type'  => 'password',
            ),
            'merchantname' => array(
                'title'       => __( 'Merchant Name', 'wc-cashbaba' ),
                'type'        => 'text',
                'description' => __( 'Enter your Merchant Name.', 'wc-cashbaba' ),
            ),
            'merchantid' => array(
                'title'       => __( 'Merchant ID', 'wc-cashbaba' ),
                'type'        => 'text',
                'description' => __( 'Enter your Merchant ID.', 'wc-cashbaba' ),
            ),
            'companyname' => array(
                'title'       => __( 'Company Name', 'wc-cashbaba' ),
                'type'        => 'text',
                'description' => __( 'Enter your Company Name.', 'wc-cashbaba' ),
            ),
            'companylogourl' => array(
                'title'       => __( 'Company Logo URL', 'wc-cashbaba' ),
                'type'        => 'text',
                'description' => __( 'Enter your Company Logo URL.', 'wc-cashbaba' ),
            ),
        );
    }

    /**
     * Output for the order received page.
     *
     * @return void
     */
    public function thankyou_page( $order_id ) {

        if ( $this->instructions ) {
            echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
        }

        $order = wc_get_order( $order_id );

        if ( $order->has_status( 'on-hold' ) ) {
            WC_cashbaba::tranasaction_form( $order_id );
        }
    }

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     *
     * @return void
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

        if ( ! $sent_to_admin && 'cashbaba' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
            if ( $this->instructions ) {
                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            }
        }

    }

    /**
     * Process the gateway integration
     *
     * @param  int  $order_id
     *
     * @return void
     */
    public function process_payment( $order_id ) {

        $order = wc_get_order( $order_id );

        // Mark as on-hold (we're awaiting the payment)
        $order->update_status( 'on-hold', __( 'Awaiting cashbaba payment', 'wc-cashbaba' ) );

        // Remove cart
        WC()->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result'    => 'success',
            'redirect'  => $this->get_return_url( $order )
        );
    }

    /**
     * Validate place order submission
     *
     * @return bool
     */
    public function validate_fieldss() {
        global $woocommerce;

        if ( empty( $_POST['cashbaba_trxid'] ) ) {
            wc_add_notice( __( 'Please type the transaction ID.', 'wc-cashbaba' ), 'error' );
            return;
        }

        return true;
    }

}