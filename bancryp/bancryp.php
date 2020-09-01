<?php
/**
 * Plugin Name: Bancryp WooCommerce Checkout with Cryptocurrency
 * Plugin URI: http://www.bancryp.com
 * Description: Create WooCommerce checkout process with Bancryp.
 * Version: 1.0
 * Author: Bancryp
 */

if ( ! defined( 'ABSPATH' ) ): exit;endif;

//register_activation_hook( __FILE__, array( 'Bancryp', 'plugin_activation' ) );
register_activation_hook(__FILE__, 'bancryp_checkout_plugin_setup');
//register_deactivation_hook( __FILE__, array( 'Bancryp', 'plugin_deactivation' ) );

#autoloader
function BCP_autoloader($class)
{
    if (strpos($class, 'BCP_') !== false):
        if (!class_exists('BancrypLib/' . $class, false)):
            #doesnt exist so include it
            include 'BancrypLib/' . $class . '.php';
        endif;
    endif;
}

spl_autoload_register('BCP_autoloader');

#check and see if requirements are met for turning on plugin

function bancryp_checkout_woocommerce_bancryp_failed_requirements()
{
    global $wp_version;
    global $woocommerce;
    $errors = array();

    // WooCommerce required
    if (true === empty($woocommerce)) {
        $errors[] = 'The WooCommerce plugin for WordPress needs to be installed and activated. Please contact your web server administrator for assistance.';
    } elseif (true === version_compare($woocommerce->version, '2.2', '<')) {
        $errors[] = 'Your WooCommerce version is too old. The Bancryp payment plugin requires WooCommerce 2.2 or higher to function. Your version is ' . $woocommerce->version . '. Please contact your web server administrator for assistance.';
    }
    if (empty($errors)):
        return false;
    else:
        return implode("<br>\n", $errors);
    endif;
}

//add_action('plugins_loaded', 'wc_bancryp_checkout_gateway_init', 11);
#create the table if it doesnt exist
function bancryp_checkout_plugin_setup()
{

    $failed = bancryp_checkout_woocommerce_bancryp_failed_requirements();
   // echo "yes";
    //exit;
    $plugins_url = admin_url('plugins.php');

    if ($failed === false) {

        global $wpdb;
        $table_name = '_bancryp_checkout_transactions';

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name(
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `order_id` int(11) NOT NULL,
        `transaction_id` varchar(255) NOT NULL,
        `api_data` varchar(500) NOT NULL,
        `transaction_status` varchar(50) NOT NULL DEFAULT 'new',
        `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        #check out of date plugins
        $plugins = get_plugins();
        foreach ($plugins as $file => $plugin) {
            if ('Bancryp Woocommerce' === $plugin['Name'] && true === is_plugin_active($file)) {
                deactivate_plugins(plugin_basename(__FILE__));
                wp_die('Bancryp for WooCommerce requires that the old plugin, <b>Bancryp Woocommerce</b>, is deactivated and deleted.<br><a href="' . $plugins_url . '">Return to plugins screen</a>');
            }
        }

    } else {

        // Requirements not met, return an error message
        wp_die($failed . '<br><a href="' . $plugins_url . '">Return to plugins screen</a>');

    }

}

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'bancryp_add_plugin_page_settings_link');
function bancryp_add_plugin_page_settings_link( $links ) {
    $links[] = '<a href="' .
        admin_url( 'admin.php?page=wc-settings&tab=checkout&section=bancryp' ) .
        '">' . __('Settings') . '</a>';
    return $links;
}

function bancryp_checkout_insert_order_note($order_id, $transaction_id,$api_data)
{
    global $wpdb;
    $table_name = '_bancryp_checkout_transactions';
    $wpdb->insert(
        $table_name,
        array(
            'order_id' => $order_id,
            'transaction_id' => $transaction_id,
            'api_data' => $api_data,
        )
    );
}

function bancryp_checkout_update_order_note($order_id, $transaction_id, $transaction_status)
{
    global $wpdb;
    $table_name = '_bancryp_checkout_transactions';
    $wpdb->update($table_name, array('transaction_status' => $transaction_status), array("order_id" => $order_id, 'transaction_id' => $transaction_id));
}

function bancryp_checkout_get_order_transaction($order_id, $transaction_id)
{
    global $wpdb;
    $table_name = '_bancryp_checkout_transactions';
    $rowcount = $wpdb->get_var("SELECT COUNT(order_id) FROM $table_name WHERE order_id = '$order_id'
    AND transaction_id = '$transaction_id' LIMIT 1");
    return $rowcount;

}

function bancryp_checkout_get_order_detail($order_id, $transaction_id)
{
    global $wpdb;
    $table_name = '_bancryp_checkout_transactions';
    $row = $wpdb->get_results("SELECT * FROM $table_name WHERE order_id = '$order_id'
    AND transaction_id = '$transaction_id'");
    return $row;

}

function bancryp_checkout_delete_order_transaction($order_id)
{
    global $wpdb;
    $table_name = '_bancryp_checkout_transactions';
    $wpdb->query("DELETE FROM $table_name WHERE order_id = '$order_id'");

}

//hook into the order recieved page and re-add to cart of modal canceled
add_action('woocommerce_thankyou', 'bancryp_checkout_thankyou_page', 10, 1);
function bancryp_checkout_thankyou_page($order_id)
{
    //echo "dfgagasdfgsdfgds";
   global $woocommerce;
    $_SESSION['wcbp'] = $woocommerce;
    $order = new WC_Order($order_id);

    $restore_url = get_home_url() . '/wp-json/bancryp/cartfix/restore';
    $api_status = get_home_url() . '/wp-json/bancryp/ipn/status';
    $cart_url = get_home_url() . '/cart';
        #use the modal
    if ($order->payment_method == 'bancryp' )
    {
        $invoiceID = $_COOKIE['bancryp-invoice-id'];
        //echo $invoiceID;
            //echo $invoiceID;
          //echo "<br/>";
          //echo $order_id;
          $order_trans_detail = bancryp_checkout_get_order_detail($order_id,$invoiceID);
          //echo "<pre>";
         // print_r($order_trans_detail);
          //echo "</pre>";
          $api_data = json_decode($order_trans_detail[0]->api_data);
        ?>
        <!-- jQuery Modal -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.js"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.css" />
        <!-- Modal HTML embedded directly into document -->
        <div id="bancryp_payment" class="modal">
             <h3>Complete your payment through given QRCODE</h3>
             <?php
             $arr = explode(',"', $order_trans_detail[0]->api_data);
             $btc_coin = explode('":', $arr[2]);
            
             if($api_data->coin_value===$btc_coin[1])
             {
               $coin_value = $api_data->coin_value;
             }else{
               $coin_value = $btc_coin[1];
             }
             ?>
            <div class="modal_con" id="modal_con">
             <div style="" class="inner_con">
              <p><div style='font-size:20px; width: 10%;float: left;border: 2px solid;border-radius: 50%;cursor: pointer;' id="more_info">&#10071;</div><span style="font-size: 1.625rem;font-weight: 500;padding-left: 20px;"><?=$coin_value?>  <?=$api_data->coin?></span><span style="float: right;width: 20%;" ><div class="pay_timer"></div></span></p>
              <div id="bancryp_qrcode" style="clear: both;padding-top: 35px;"><a href="javascript:void(0);"></a></div>
              <!--a href="#" rel="modal:close">Close</a-->
            </div>
            <div id="info_modal" class="modal" style="text-align: center;">
            <p style="font-weight: bold; color: #757575;">Please send your payment within <span style="color: #ff0000;" class="pay_timer"></span></p>
            <p style="font-weight: bold; color: #757575; font-size: 20px;">1 <?=$api_data->coin?> = <?=$api_data->coin_rate_brl?> BRL</p>
            <p style="font-size: 20px;">Total : <?=$coin_value?>  <?=$api_data->coin?></p>
            </div>

            </div>
            <img style="margin-top: 12%; background: #111;" src="<?=plugin_dir_url( __FILE__ )?>assest/bancryp.jpeg">


        </div>

        <div id="bancryp_warning_close" class="modal">
            <p>Ao cancelar este pagamento você estará cancelando o pedido, tem certeza que deseja continuar?</p>
            <a class="warning_bt" rel="modal:close">Não</a><a href="#" id="close_war" class="warning_bt" rel="modal:close">Sim</a>
        </div>

            <script type='text/javascript'>
                jQuery('#inner_con').hide();
                jQuery('#bancryp_qrcode a').qrcode({ 
                    render: 'image',
                    text: "bitcoin:<?php echo $api_data->coin_addr.'?amount='. $coin_value.'&quote='.$invoiceID; ?>",
                    ecLevel: 'L',
                    size: "203"
                });
        		jQuery("#primary").hide();
                jQuery('#bancryp_payment').modal({
                  escapeClose: false,
                  clickClose: false,
                  closeClass: 'bancryp_payment_remove'
                });

                if(jQuery('#bancryp_payment').is(':visible'))
                {
                    function Apisend(){
                        
                        var api_url = '<?php echo $api_status; ?>';
                        var ajaxdata = {
                            invoiceID: '<?php echo $invoiceID; ?>',order_id: '<?php echo $order_id; ?>'
                        }
                        jQuery.ajax({
                            type: "post",
                            url: api_url,
                            data: ajaxdata,
                            dataType: "json",
                            success:function(data)
                            {
                                //console.log the response
                               var dta = jQuery.parseJSON(data);
                                console.log(dta);
                                //console.log(dta.message);
                                if(dta.status=='PENDING'){
                                    setTimeout(function(){
                                        Apisend();
                                    }, 10000);
                                }else if(dta.status=='SUCCESS')
                                {
                                    jQuery('a.bancryp_payment_remove').hide();
                                    jQuery('#bancryp_qrcode').html('<img src="<?=plugin_dir_url( __FILE__ )?>assest/success.png"><br/><p>This Invoice has been paid</p>');
                                    
                                    setTimeout(function(){
                                       jQuery.modal.close();
                                    }, 3000);
                                    jQuery("#primary").show();
                                }
                            }
                        });
                    }
                    Apisend();
                }

                jQuery("a.bancryp_payment_remove").attr('rel', '');
                jQuery("a.bancryp_payment_remove").click(function(){
                    jQuery('#bancryp_warning_close').modal({
                    escapeClose: false,
                    clickClose: false,
                    showClose: false,
                    closeExisting: false
                    });
                });
                
                jQuery('#more_info').click(function()
                {
                    jQuery('#info_modal').modal({
                    escapeClose: false,
                    closeExisting: false
                    });
                });
                // Are you sure you want to cancel the transaction
                jQuery('#close_war').click(function()
                {
                    jQuery('#bancryp_warning_close').on(jQuery.modal.AFTER_CLOSE, function(event, modal) {
                      // jQuery("#bancryp_payment").modal.close();
                       jQuery.modal.close();
                       //jQuery("#primary").show();
                       var myKeyVals = {
                    orderid: '<?php echo $order_id; ?>'
                }
                //console.log('payment_status','cancel');
                var redirect = '<?php echo $cart_url; ?>';
                var api = '<?php echo $restore_url; ?>';
                jQuery.ajax({
                    type: 'POST',
                    url: api,
                    data: myKeyVals,
                    dataType: "text",
                    success: function(resultData) {
                        window.location = redirect;
                    }
                 });
                    });
                });
                // Click on after expire transaction
                jQuery('#bancryp_payment').on('click', '#close_n_return', function() 
                {
                    //jQuery('#bancryp_warning_close').on(jQuery.modal.AFTER_CLOSE, function(event, modal) {
                      // jQuery("#bancryp_payment").modal.close();
                       jQuery.modal.close();
                       //jQuery("#primary").show();
                       var myKeyVals = {
                    orderid: '<?php echo $order_id; ?>'
                    }
                    //alert('ggggggg')
                //console.log('payment_status expire','expired')
                var redirect = '<?php echo $cart_url; ?>';
                var api = '<?php echo $restore_url; ?>';
                jQuery.ajax({
                    type: 'POST',
                    url: api,
                    data: myKeyVals,
                    dataType: "text",
                    success: function(resultData) {
                        window.location = redirect;
                    }
                 });
                    //});
                });
                

                    var deadline = new Date("<?=$order_trans_detail[0]->date_added?>").getTime()+ 15*60000; 
                    var x = setInterval(function() { 
                    var now = new Date().getTime(); 
                    var t = deadline - now; 
                    var t_second = t / 1000;
                    //console.log(parseInt(t_second));
                    var days = Math.floor(t / (1000 * 60 * 60 * 24)); 
                    var hours = Math.floor((t%(1000 * 60 * 60 * 24))/(1000 * 60 * 60)); 
                    var minutes = Math.floor((t % (1000 * 60 * 60)) / (1000 * 60)); 
                    var seconds = Math.floor((t % (1000 * 60)) / 1000); 
                    jQuery('.pay_timer').html( minutes + "m " + seconds + "s ");
                    //document.getElementById("pay_timer").innerHTML = minutes + "m " + seconds + "s "; 
                        if (t < 0) { 
                            clearInterval(x); 
                             jQuery('.pay_timer').html("EXPIRED");
                            //document.getElementById("pay_timer").innerHTML = "EXPIRED"; 
                            jQuery('#modal_con').html('<p><span style="font-size:15px; color:red;border:2px solid #ff0000;">&#10006;</span></p><p>Invoice Expired</p><p>An invoice is only valid for 15 minutes. Return to the merchant if you would like to resubmit a payment.</p><p>Invoice id<br/><?=$invoiceID?></p><br/><p><a href="#" id="close_n_return" class="close_n_return warning_bt">Try Again</a></p>');
                        }else{
                            jQuery('#inner_con').show();
                        } 
                    }, 1000); 
            </script> 
        <?php
    }
}


#custom info for Bancryp
add_action('woocommerce_thankyou', 'bancryp_checkout_custom_message');
function bancryp_checkout_custom_message($order_id)
{
    $order = new WC_Order($order_id);
    if( $order->payment_method == 'bancryp'):
        $bancryp_checkout_options = get_option('woocommerce_bancryp_settings');
        $checkout_message = $bancryp_checkout_options['bancryp_checkout_checkout_message'];
        if ($checkout_message != ''):
            echo '<hr><b>' . $checkout_message . '</b><br><br><hr>';
        endif;
    endif;
}


/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'bancryp_add_gateway_class' );
function bancryp_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Bancryp_Gateway'; // your class name is here
	return $gateways;
}
 
 add_action( 'wp_enqueue_scripts', 'my_custom_script_load' );
	function my_custom_script_load(){
     wp_register_style( 'namespace', plugin_dir_url( __FILE__ ) . 'bancryp.css' );
     wp_enqueue_style( 'namespace' );
	  wp_enqueue_script( 'my-custom-script', plugin_dir_url( __FILE__ ) . 'qrcode_lib.js', array( 'jquery' ) );
	}
#http://<host>/wp-json/bancryp/ipn/status
add_action('rest_api_init', function () {
    register_rest_route('bancryp/ipn', '/status', array(
        'methods' => 'POST,GET',
        'callback' => 'bancryp_checkout_ipn',
    ));
    register_rest_route('bancryp/cartfix', '/restore', array(
        'methods' => 'POST,GET',
        'callback' => 'bancryp_checkout_cart_restore',
    ));
});

function bancryp_checkout_cart_restore(WP_REST_Request $request)
{
    WC()->frontend_includes();
    WC()->cart = new WC_Cart();
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
    $data = $request->get_params();
    //print_r( $data);
    //die;
    $order_id = $data['orderid'];
    $order = new WC_Order($order_id);
    $items = $order->get_items();
    //print_r( $items);
    //die;
    //clear the cart first so things dont double up
    WC()->cart->empty_cart();
/*    foreach ($items as $item) {
        //now insert for each quantity
        $item_count = $item->get_quantity();
        echo $item->get_product_id();
        for ($i = 0; $i < $item_count; $i++){
            WC()->cart->add_to_cart($item->get_product_id(),1);
        }
    }*/
    //echo $order_id;
    //die;
    //delete the previous order
    wp_delete_post($order_id, true);
    bancryp_checkout_delete_order_transaction($order_id);
    setcookie("bancryp-invoice-id", "", time() - 3600);
}

//http://<host>/wp-json/bancryp/ipn/status
function bancryp_checkout_ipn(WP_REST_Request $request)
{
    //echo "ssdsdASDAdsdasdasd dsdfdfs f";
    global $woocommerce;
    WC()->frontend_includes();
    WC()->cart = new WC_Cart();
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
    $data = $request->get_params();
    //print_r($data);
    //echo $data['invoiceID'];
    $order_id = $data['order_id'];
    $order = new WC_Order($order_id);
    $items = $order->get_items();


    $bancryp_checkout_option = get_option('woocommerce_bancryp_settings');

    $live = new WC_Bancryp_Gateway();
    if($bancryp_checkout_option['testmode']=='yes'){
        $api_url = "https://sandbox.bancryp.com/apiext/v1/";
    }else{
        $api_url = "https://api.bancryp.com/apiext/v1/";
    }
     $api_key = $live->publishable_key;
     $secret_key = $live->private_key;
     $params = new stdClass();
            //$current_user = wp_get_current_user();

            $params->api_url = $api_url;
            $params->api_key = $api_key;
            $params->secret_key = $secret_key;
            $params->invoiceID = $data['invoiceID'];
           

            $invoice = new BCP_Invoice($params);
            //this creates the invoice with all of the config params from the item
            $invoice->BCP_invoiceresponse();
           $invoiceData = json_decode($invoice->BCP_getInvoiceData());
           $api_data = $invoice->BCP_getInvoiceData();

            #update the lookup table
            bancryp_checkout_update_order_note($order_id, $data['invoiceID'], $invoiceData->status);

           $response = array();
           if($invoiceData->status == 0){

            $response['status']= "PENDING";
            $response['message']= "Your payment is still pending";  

           }elseif($invoiceData->status == 1){
                //private order note with the invoice id
                $order->add_order_note('Bancryp Invoice ID: ' . $data['invoiceID'] . ' processing has been completed.');

                $order->update_status('completed', __('Bancryp payment complete', 'woocommerce'));
                // Reduce stock levels
                $order->reduce_order_stock();

                // Remove cart
                WC()->cart->empty_cart();

            $response['status']= "SUCCESS";
            $response['message']= "Your transaction has been completed successfully";

           }elseif($invoiceData->status == -1)
           {
                $response['status']= "CANCELED";
                $response['message']= "Your transaction has been canceled";
                
           }

           return json_encode($response);
       }

add_action('template_redirect', 'woo_custom_redirect_after_purchase_bancryp');
function woo_custom_redirect_after_purchase_bancryp()
{
    global $wp;

    if (is_checkout() && !empty($wp->query_vars['order-received'])) {
        //if($_COOKIE['bancryp-invoice-id']){
            //clear the cookie
         //   setcookie("bancryp-invoice-id", "", time() - 3600);
       // }

        $order_id = $wp->query_vars['order-received'];
        $order = new WC_Order($order_id);

        $bancryp_checkout_option = get_option('woocommerce_bancryp_settings');

        $live = new WC_Bancryp_Gateway();
         if($bancryp_checkout_option['testmode']=='yes'){
		 	$api_url = "https://sandbox.bancryp.com/apiext/v1/";
		 }else{
		 	$api_url = "https://api.bancryp.com/apiext/v1/";
		 }
		 $api_key = $live->publishable_key;
		 $secret_key = $live->private_key;

        //this means if the user is using bancryp AND this is not the redirect
        $show_bancryp = true;

        if (isset($_GET['status']) && $_GET['status'] == 'false'):
            $show_bancryp = false;
            $invoiceID = $_COOKIE['bancryp-invoice-id'];

            //clear the cookie
            setcookie("bancryp-invoice-id", "", time() - 3600);
        endif;
        if ($order->payment_method == 'bancryp' && $show_bancryp == true):
        	$params = new stdClass();
            $current_user = wp_get_current_user();
            $params->price = $order->total;
            $params->currency = $order->currency; //set as needed

            $params->api_url = $api_url;
            $params->api_key = $api_key;
            $params->secret_key = $secret_key;

        	if ($current_user->user_email){
                    $buyerInfo = new stdClass();
                    $buyerInfo->name = $current_user->display_name;
                    $buyerInfo->email = $current_user->user_email;
                    $params->buyer = $buyerInfo;
            }
        
        	//orderid
            $params->orderId = trim($order_id);
            //redirect and ipn stuff
            $checkout_slug = 'checkout';
            if(empty($checkout_slug)):
                $checkout_slug = 'checkout';
            endif;
            $params->redirectURL = get_home_url() . '/'.$checkout_slug.'/order-received/' . $order_id . '/?key=' . $order->order_key . '&status=false';
            $params->notificationURL = get_home_url() . '/wp-json/bancryp/ipn/status';
            //echo $params->notificationURL;
           // die;
            #http://<host>/wp-json/bancryp/ipn/status
            $params->extendedNotifications = true;
            $params->transactionSpeed = 'medium';
            $params->acceptanceWindow = 1200000;

            //$item = new BCP_Item($params);
            $invoice = new BCP_Invoice($params);
            //this creates the invoice with all of the config params from the item
            $invoice->BCP_createInvoice();
           $invoiceData = json_decode($invoice->BCP_getInvoiceData());
           $api_data = $invoice->BCP_getInvoiceData();
          // echo "sdfafsas<pre>".$invoice->BCP_getInvoiceData();
           //print_r($invoiceData);
           //die;
           if(empty($invoiceData))
           {
            echo "There is some issue in Bancryp System Please try again.";
             
             //$invoiceData = json_decode('{"sequence_id":null,"ts_event":null,"coin_value":0.00000636,"coin_rate_brl":31427.99,"coin_addr":"1CNG6cTLFnDUGmtqRpx699vaBjTNz4uYsM","value_brl":0.20,"payment_id":572,"coin":"BTC"}');
             //$api_data = '{"sequence_id":null,"ts_event":null,"coin_value":0.00000636,"coin_rate_brl":31427.99,"coin_addr":"1CNG6cTLFnDUGmtqRpx699vaBjTNz4uYsM","value_brl":0.20,"payment_id":572,"coin":"BTC"}';
             //print_r($invoiceData);
            exit;

           }
            //now we have to append the invoice transaction id for the callback verification

            $invoiceID = $invoiceData->payment_id;
            //$invoiceID = '123456780';
            //set a cookie for redirects and updating the order status
            $cookie_name = "bancryp-invoice-id";
            $cookie_value = $invoiceID;
            setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/");
            //echo "<pre>";
            //print_r($_COOKIE);
            #insert into the database
            bancryp_checkout_insert_order_note($order_id, $invoiceID,$api_data);
            $use_modal = 1;
            //use the modal if '1', otherwise redirect
            if ($use_modal == 2):
                wp_redirect($invoice->BPC_getInvoiceURL());
            else:
                wp_redirect($params->redirectURL);

            endif;

            exit;
        endif;

	//echo "<br/>test etetaetertwe rtert";
	//die('dfsdfsf');
	}

}


/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'bancryp_init_gateway_class' );
function bancryp_init_gateway_class()
{
 
	class WC_Bancryp_Gateway extends WC_Payment_Gateway
	{
 
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
		public function __construct() {
		 
			$this->id = 'bancryp'; // payment gateway plugin ID
			//$this->icon = 'https://bancryp.com/images/logo_bancryp.png'; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->icon = plugin_dir_url( __FILE__ ).'assest/bancryp.jpeg'; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = true; // in case you need a custom credit card form
			$this->method_title = 'Bancryp';
			$this->method_description = 'Expand your world by accepting instant BTC and XBANC payments without any risk'; // will be displayed on the options page
		 
			// gateways can support subscriptions, refunds, saved payment methods,
			// but in this tutorial we begin with simple payments
			$this->supports = array(
				'products'
			);
		 
			// Method with all the options fields
			$this->init_form_fields();
		 
			// Load the settings.
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->testmode = 'yes' === $this->get_option( 'testmode' );
			$this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
			$this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
		 
			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		 
			// We need custom JavaScript to obtain a token
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		 
			// You can also register a webhook here
			// add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
		 }
 
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
		public function init_form_fields(){
		 
			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable Bancryp Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Bancryp',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay with your credit card via our super-cool payment gateway.',
				),
				'testmode' => array(
					'title'       => 'Test mode',
					'label'       => 'Enable Test Mode',
					'type'        => 'checkbox',
					'description' => 'Place the payment gateway in test mode using test API keys.',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'test_publishable_key' => array(
					'title'       => 'Test APIKEY',
					'type'        => 'text'
				),
				'test_private_key' => array(
					'title'       => 'Test SECRET KEY',
					'type'        => 'password',
				),
				'publishable_key' => array(
					'title'       => 'Live APIKEY',
					'type'        => 'text'
				),
				'private_key' => array(
					'title'       => 'Live SECRET KEY',
					'type'        => 'password'
				),
                'bancryp_checkout_checkout_message' => array(
                    'title' => __('Checkout Message', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Insert your custom message for the <b>Order Received</b> page, so the customer knows that the order will not be completed until Bancryp releases the funds.', 'woocommerce'),
                    'default' => 'Thank you.  We will notify you when Bancryp has processed your transaction.',
                ),
			);
		}
 
		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		/*public function payment_fields() {
		 
			// ok, let's display some description before the payment form
			if ( $this->description ) {
				// you can instructions for test mode, I mean test card numbers etc.
				if ( $this->testmode ) {
					$this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#" target="_blank" rel="noopener noreferrer">documentation</a>.';
					$this->description  = trim( $this->description );
				}
				// display the description with <p> tags etc.
				echo wpautop( wp_kses_post( $this->description ) );
			}
		 
			// I will echo() the form, but you can close PHP tags and print it directly in HTML
			echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
		 
			// Add this action hook if you want your custom payment gateway to support it
			do_action( 'woocommerce_credit_card_form_start', $this->id );
		 
			// I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
			echo '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
				<input id="bancryp_ccNo" type="text" autocomplete="off">
				</div>
				<div class="form-row form-row-first">
					<label>Expiry Date <span class="required">*</span></label>
					<input id="bancryp_expdate" type="text" autocomplete="off" placeholder="MM / YY">
				</div>
				<div class="form-row form-row-last">
					<label>Card Code (CVC) <span class="required">*</span></label>
					<input id="bancryp_cvv" type="password" autocomplete="off" placeholder="CVC">
				</div>
				<div class="clear"></div>';
		 
			do_action( 'woocommerce_credit_card_form_end', $this->id );
		 
			echo '<div class="clear"></div></fieldset>';
		 
		}*/
 
		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
		public function payment_scripts() {
		 
			// we need JavaScript to process a token only on cart/checkout pages, right?
			if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
				return;
			}
		 
			// if our payment gateway is disabled, we do not have to enqueue JS too
			if ( 'no' === $this->enabled ) {
				return;
			}
		 
			// no reason to enqueue JavaScript if API keys are not set
			if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
				return;
			}
		 
			// do not work with card detailes without SSL unless your website is in a test mode
			if ( ! $this->testmode && ! is_ssl() ) {
				return;
			}
		 
			// let's suppose it is our payment processor JavaScript that allows to obtain a token
			//wp_enqueue_script( 'bancryp_js', 'https://www.test.com/api/token.js' );
		 
			// and this is our custom JS in your plugin directory that works with token.js
			//wp_register_script( 'woocommerce_bancryp', plugins_url( 'bancryp.js', __FILE__ ), array( 'jquery', 'bancryp_js' ) );
		 
			// in most payment processors you have to use PUBLIC KEY to obtain a token
			wp_localize_script( 'woocommerce_bancryp', 'bancryp_params', array(
				'publishableKey' => $this->publishable_key
			) );
		 
			//wp_enqueue_script( 'woocommerce_bancryp' );

			
		 
		}
 
		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields(){
		 
			/*if( empty( $_POST[ 'billing_first_name' ]) ) {
				wc_add_notice(  'First name is required!', 'error' );
				return false;
			}
			return true;*/
		 
		}
 
		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
		 
			global $woocommerce;
			//echo $this->testmode;
		 //echo $this->publishable_key;
			// we need it to get any order detailes
			$order = wc_get_order( $order_id );
		
		 //print_r($order);
			//echo $this->get_return_url($order);
			//die;

		 if($this->testmode==1){
		 	$api_url = "https://sandbox.bancryp.com/apiext/v1/";
		 }else{
		 	$api_url = "https://api.bancryp.com/apiext/v1/";
		 }

		 return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
			

			/*$curl = curl_init();

			curl_setopt_array($curl, array(
			  CURLOPT_URL => $api_url."payment/start",
			  CURLOPT_RETURNTRANSFER => true,
			  CURLOPT_ENCODING => "",
			  CURLOPT_MAXREDIRS => 10,
			  CURLOPT_TIMEOUT => 0,
			  CURLOPT_FOLLOWLOCATION => false,
			  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			  CURLOPT_CUSTOMREQUEST => "POST",
			  CURLOPT_POSTFIELDS =>"{\n\t\n  \"sequence_id\": '',\n  \"coin\": \"BTC\",\n  \"value_brl\": ".$order->get_total().",\n  \"api_key\": ".$this->publishable_key.",\n  \"secret_key\": ".$this->private_key.",\n  \"client_version\": \"1.0.0.0\"\n\n}",
			  CURLOPT_HTTPHEADER => array(
			    "Content-Type: application/json"
			  ),
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);

			curl_close($curl);

			if ($err) {
			  //echo "cURL Error #:" . $err;
			  wc_add_notice(  'Connection error.'.$err, 'error' );
				return;
			} else {
			  //echo $response;
			  //die;
			  $resbancryp = json_decode($response, true);
			  if($resbancryp['coin_addr']!='')
			  {
			  	// we received the payment
					//$order->payment_complete();
					//$order->reduce_order_stock();
		 
					// some notes to customer (replace true with false to make it private)
					//$order->add_order_note( 'Hey, your order is paid! Thank you!', true );
		 
					// Empty cart
					//$woocommerce->cart->empty_cart();
		 
					// Redirect to the thank you page
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
			  }else{
				wc_add_notice(  'Connection error.', 'error' );
				return;
				}
			}*/
		 
			/*
			 * Your API interaction could be built with wp_remote_post()
		 	 */
			/*
		 	 * Array with parameters for API interaction
			 */
			//$args = array(
		 
				
		 
			//);
			 //$response = wp_remote_post( '{payment processor endpoint}', $args );
		 
		 
			/* if( !is_wp_error( $response ) ) {
		 
				 $body = json_decode( $response['body'], true );
		 
				 // it could be different depending on your payment processor
				 if ( $body['response']['responseCode'] == 'APPROVED' ) {
		 
					// we received the payment
					$order->payment_complete();
					$order->reduce_order_stock();
		 
					// some notes to customer (replace true with false to make it private)
					$order->add_order_note( 'Hey, your order is paid! Thank you!', true );
		 
					// Empty cart
					$woocommerce->cart->empty_cart();
		 
					// Redirect to the thank you page
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
		 
				 } else {
					wc_add_notice(  'Please try again.', 'error' );
					return;
				}
		 
			} else {
				wc_add_notice(  'Connection error.', 'error' );
				return;
			}*/
		 
		}
 
		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {
 
		
 
	 	}
 	}
}