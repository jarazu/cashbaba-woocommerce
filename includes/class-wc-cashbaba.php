<?php

/**
 * cashbaba Class
 */
class WC_cashbaba {
    const base_url = 'https://api.cashbaba.com.bd/';
    // const base_url = 'https://dev.cash-baba.com:11443/';
    private $table = 'wc_cashbaba';

    function __construct() {
        add_action( 'wp_ajax_wc-cashbaba-confirm-trx', array($this, 'process_form') );

        add_action( 'woocommerce_order_details_after_order_table', array($this, 'transaction_form_order_view') );
    }

    function transaction_form_order_view( $order ) {

        if ( $order->has_status( 'on-hold' ) && $order->payment_method == 'cashbaba' && ( is_view_order_page() || is_order_received_page() ) ) {
            self::tranasaction_form( $order->id );
        }
        $tokenval = $this->gettoken();
        $accesstoken = $tokenval->access_token;
        $checkoutDataCreate = $this->checkOutDataCreate($accesstoken, $order->id, $order->total);
        $this->sendFinalRequest($checkoutDataCreate);

    }

    function gettoken(){
        $cbtokensettings = get_option( 'woocommerce_cashbaba_settings' );
        $clientid = $cbtokensettings["clientid"];
        $client_secret = $cbtokensettings["client_secret"];
        
        $header= array(                                                                          
            'Content-Type: application/x-www-form-urlencoded',                                                                               
               "Accept: application/json");                                                                       
           
           $builder= http_build_query([
              'grant_type'=>"client_credentials", 
               'client_id'=>$clientid,
               'client_secret'=>$client_secret]);
           
           $tokenurl = self::base_url."api/v1/connect/token";
           $ch = curl_init();
           curl_setopt($ch, CURLOPT_URL, $tokenurl);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $builder);
           
           curl_setopt($ch, CURLOPT_HTTPHEADER, $header);  
           curl_setopt($ch, CURLOPT_CUSTOMREQUEST,'POST');
           
           curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
           curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
            $result = curl_exec($ch);
            //access_token
            // handle curl error
            // if ($result === false) {
                // throw new Exception('Curl error: ' . curl_error($crl));
                //print_r('Curl error: ' . curl_error($crl));
               // $result_noti = 0; //die();
            // } else {
              //  $result_noti = 1; //die();
            // }
            // Close cURL session handle
            curl_close($ch);
            return json_decode($result);
    }
 
    function checkOutDataCreate($accesstoken, $orderid, $orderamount){
        $cbsettings = get_option( 'woocommerce_cashbaba_settings' );
        $merchantid = $cbsettings["merchantid"];
        $merchantname = $cbsettings["merchantname"];
        $companylogourl = $cbsettings["companylogourl"];
        $companyname = $cbsettings["companyname"];
        $date   = new DateTime(); //this returns the current date time
        $requestdatetime = $date->format('Y-m-d H:i:s');
        $checkouturl = self::base_url.'api/v1/ecommerce/checkout/create';

        $data = array(
            'MID'=>$merchantname,
            'CustomerID'=>$merchantid,
            'CompanyLogo'=>$companylogourl,
            'CompanyName'=>$companyname,
            'Currency'=>'bdt',
            'OrderAmount'=>$orderamount,
            'Intent'=>'sale',
            'OrderId'=>$orderid,
            'SuccessCallBackUrl' => get_option( 'home' ),
            'FailureCallBackUrl'=> get_option( 'home' ),
            'CancelCallBackUrl' => get_option( 'home' ),
            'RequestDateTime'=> $requestdatetime
            );

        $ch = curl_init($checkouturl); // Initialise cURL
        $post = json_encode($data); // Encode the data array into a JSON string
        $authorization = "Authorization: Bearer ".$accesstoken; // Prepare the authorisation token
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization )); // Inject the token into the header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1); // Specify the request method as POST
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // Set the posted fields
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // This will follow any redirects
        $result = curl_exec($ch); // Execute the cURL statement
        curl_close($ch); // Close the cURL connection
        return json_decode($result); // Return the received data
    }


    public function sendFinalRequest($data){
        $paymenturl = self::base_url."payment";
        ?>

        <div class="wc-cashbaba-form-wrap" style="background: #eee;padding: 15px;border: 1px solid #ddd; margin: 15px 0;">
            <div id="wc-cashbaba-result"></div>
            <form action="<?php echo $paymenturl; ?>" method="post" id="wc-cashbaba-confirm" class="wc-cashbaba-form">
                <p class="form-row validate-required">
                    <input class="input-text" type="hidden" name="paymentId" placeholder="paymentId" value="<?php echo $data->paymentId; ?>"/>
                    <input class="input-text" type="hidden" name="referenceId" placeholder="referenceId"  value="<?php echo $data->referenceId; ?>"/>
                    <input class="input-text" type="hidden" name="orderId" placeholder="orderId"  value="<?php echo $data->orderId; ?>"/>
                </p>
                <p class="form-row">
                    <?php wp_nonce_field( 'wc-cashbaba-confirm-trx' ); ?>
                    <?php $pay_order_button_text = apply_filters( 'wc_cashbaba_pay_order_button_text', __( 'Confirm Payment', 'wc-cashbaba' ) ); ?>
                    <input type="submit" class="button alt" id="wc-cashbaba-submit" value="<?php echo esc_attr( $pay_order_button_text ); ?>" />
                </p>
            </form>
        </div>

        <script type="text/javascript">
            jQuery(function($) {
               $('#wc-cashbaba-submit').click(); // ############### this button should visible later
            });
        </script>
        <?php

    }
    /**
     * Show the payment field in checkout
     *
     * @return void
     */
    public static function tranasaction_form( $order_id ) {
        $option = get_option( 'woocommerce_cashbaba_settings', array() );
        ?>

        <!-- <div class="wc-cashbaba-form-wrap" style="background: #eee;padding: 15px;border: 1px solid #ddd; margin: 15px 0;">
            <div id="wc-cashbaba-result"></div>
            <form action="" method="post" id="wc-cashbaba-confirm" class="wc-cashbaba-form">
                <p class="form-row validate-required">
                    <label><?php _e( 'Transaction ID', 'wc-cashbaba' ) ?>: <span class="required">*</span></label>

                    <input class="input-text" type="text" name="cashbaba_trxid" required />
                    <span class="description"><?php echo isset( $option['trans_help'] ) ? $option['trans_help'] : ''; ?></span>
                </p>

                <p class="form-row">
                    <?php wp_nonce_field( 'wc-cashbaba-confirm-trx' ); ?>
                    <input type="hidden" name="action" value="wc-cashbaba-confirm-trx">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">

                    <?php $pay_order_button_text = apply_filters( 'wc_cashbaba_pay_order_button_text', __( 'Confirm Payment', 'wc-cashbaba' ) ); ?>
                    <input type="submit" class="button alt" id="wc-cashbaba-submit" value="<?php echo esc_attr( $pay_order_button_text ); ?>" />
                </p>
            </form>
        </div> -->

        <!-- <script type="text/javascript">
            jQuery(function($) {
                $('form#wc-cashbaba-confirm').on('submit', function(event) {
                    event.preventDefault();

                    var submit = $(this).find('input[type=submit]');
                    submit.attr('disabled', 'disabled');

                    $.post('<?php echo admin_url( 'admin-ajax.php'); ?>', $(this).serialize(), function(data, textStatus, xhr) {
                        submit.removeAttr('disabled');

                        if ( data.success ) {
                            window.location.href = data.data;
                        } else {
                            $('#wc-cashbaba-result').html('<ul class="woocommerce-error"><li>' + data.data + '</li></ul>');
                        }
                    });
                });
            });
        </script> -->
        <?php
    }

    public function process_form() {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'wc-cashbaba-confirm-trx' ) ) {
            wp_send_json_error( __( 'Are you cheating?', 'wc-cashbaba' ) );
        }

        $order_id       = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
        $transaction_id = sanitize_key( $_POST['cashbaba_trxid'] );

        $order          = wc_get_order( $order_id );
        $response       = $this->do_request( $transaction_id );

        if ( ! $response ) {
            wp_send_json_error( __( 'Something went wrong submitting the request', 'wc-cashbaba' ) );
            return;
        }

        if ( $this->transaction_exists( $response->trxId ) ) {
            wp_send_json_error( __('This transaction has already been used!', 'wc-cashbaba' ) );
            return;
        }

        switch ($response->trxStatus) {

            case '0010':
            case '0011':
                wp_send_json_error( __( 'Transaction is pending, please try again later', 'wc-cashbaba' ) );
                return;

            case '0100':
                wp_send_json_error( __( 'Transaction ID is valid but transaction has been reversed. ', 'wc-cashbaba' ) );
                return;

            case '0111':
                wp_send_json_error( __( 'Transaction is failed.', 'wc-cashbaba' ) );
                return;

            case '1001':
                wp_send_json_error( __( 'Invalid MSISDN input. Try with correct mobile no.', 'wc-cashbaba' ) );
                break;

            case '1002':
                wp_send_json_error( __( 'Invalid transaction ID', 'wc-cashbaba' ) );
                return;

            case '1003':
                wp_send_json_error( __( 'Authorization Error, please contact site admin.', 'wc-cashbaba' ) );
                return;

            case '1004':
                wp_send_json_error( __( 'Transaction ID not found.', 'wc-cashbaba' ) );
                return;

            case '9999':
                wp_send_json_error( __( 'System error, could not process request. Please contact site admin.', 'wc-cashbaba' ) );
                return;

            case '0000':
                $price = (float) $order->get_total();

                // check for BDT if exists
                $bdt_price = get_post_meta( $order->id, '_bdt', true );
                if ( $bdt_price != '' ) {
                    $price = $bdt_price;
                }

                if ( $price > (float) $response->amount ) {
                    wp_send_json_error( __( 'Transaction amount didn\'t match, are you cheating?', 'wc-cashbaba' ) );
                    return;
                }

                $this->insert_transaction( $response );

                $order->add_order_note( sprintf( __( 'cashbaba payment completed with TrxID#%s! cashbaba amount: %s', 'wc-cashbaba' ), $response->trxId, $response->amount ) );
                $order->payment_complete();

                wp_send_json_success( $order->get_view_order_url() );

                break;
        }

        wp_send_json_error();
    }

    /**
     * Do the remote request
     *
     * For some reason, WP_HTTP doesn't work here. May be
     * some implementation related problem in their side.
     *
     * @param  string  $transaction_id
     *
     * @return object
     */
    function do_request( $transaction_id ) {

        $option = get_option( 'woocommerce_cashbaba_settings', array() );
        $query = array(
            'user'   => isset( $option['username'] ) ? $option['username'] : '',
            'pass'   => isset( $option['pass'] ) ? $option['pass'] : '',
            'msisdn' => isset( $option['mobile'] ) ? $option['mobile'] : '',
            'trxid'  => $transaction_id
        );

        $url      = self::base_url . '?' . http_build_query( $query, '', '&' );
        $response = file_get_contents( $url );

        if ( false !== $response ) {
            $response = json_decode( $response );

            return $response->transaction;
        }

        return false;
    }

    /**
     * Insert transaction info in the db table
     *
     * @param  object  $response
     *
     * @return void
     */
    function insert_transaction( $response ) {
        global $wpdb;

        $wpdb->insert( $wpdb->prefix . $this->table, array(
            'trxId'  => $response->trxId,
            'sender' => $response->sender,
            'ref'    => $response->reference,
            'amount' => $response->amount
        ), array(
            '%d',
            '%s',
            '%s',
            '%s'
        ) );
    }

    /**
     * Check if a transaction exists
     *
     * @param  string  $transaction_id
     *
     * @return bool
     */
    function transaction_exists( $transaction_id ) {
        global $wpdb;

        $query  = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$this->table} WHERE trxId = %d", $transaction_id );
        $result = $wpdb->get_row( $query );

        if ( $result ) {
            return true;
        }

        return false;
    }
}

new WC_cashbaba();