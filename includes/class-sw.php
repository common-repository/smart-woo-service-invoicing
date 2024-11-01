<?php
/**
 * File name class-sw.php.
 * Main Smart Woo Service Invoicing class file
 * 
 * @author Callistus
 * @package SmartWoo
 */

defined( 'ABSPATH' ) || exit; // Prevent direct access.

/**
 * Smart Woo Class
 * Represents this plugin.
 * 
 * @since 1.0.2
 * @author callistus.
 * @package SmartWoo\classes
 */
final class SmartWoo {
    /**
     * @var SmartWoo Single instance of this class
     */
    protected static $instance = null;

    /**
     * @var SmartWoo_Service instance of service subscription class.
     */
    protected $service;

    /**
     * @var SmartWoo_Invoice instance of invoice class.
     */
    protected $invoice;

    /**
     * @var SmartWoo_Product instance of Smart Woo Product
     */
    protected $product;

    /**
     * @var SmartWoo_Orders instance of Smart Woo Orders.
     */
    protected $orders;

    /**
     * Class constructor
     */
    public function __construct() {
        add_filter( 'plugin_row_meta', array( __CLASS__, 'smartwoo_row_meta' ), 10, 2 );
        add_action( 'smartwoo_download', array( $this, 'download_handler' ) );
        add_filter( 'plugin_action_links_' . SMARTWOO_PLUGIN_BASENAME, array( $this, 'options_page' ), 10, 2 );

        add_action( 'admin_post_nopriv_smartwoo_login_form', array( $this, 'login_form' ) );
        add_action( 'admin_post_smartwoo_login_form', array( $this, 'login_form' ) );
        add_action( 'admin_post_smartwoo_service_from_order', array( $this, 'new_service_from_order' ) );
        add_action( 'admin_post_smartwoo_add_service', 'smartwoo_process_new_service_form' );
        add_action( 'admin_post_smartwoo_edit_service', 'smartwoo_process_edit_service_form' );
        add_action( 'admin_post_smartwoo_admin_create_invoice_from_form', array( __CLASS__, 'new_invoice_form_handler' ), 10 );
        add_action( 'admin_post_smartwoo_admin_download_invoice', array( __CLASS__, 'admin_download_invoice' ) );

        add_action( 'woocommerce_order_details_before_order_table', array( $this, 'before_order_table' ) );
        add_action( 'smartwoo_service_scan', array( __CLASS__, 'count_all_services' ) );
        add_action( 'smartwoo_auto_service_renewal', array( __CLASS__, 'auto_renew_due' ) );
        add_action( 'template_redirect', array( __CLASS__, 'manual_renew_due' ) );
        add_action( 'template_redirect', array( __CLASS__, 'payment_link_handler' ) );

        // Service renewal action hooks.
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'paid_invoice_order_manager' ), 50, 1 );
        add_action( 'woocommerce_payment_complete', array( __CLASS__, 'paid_invoice_order_manager' ), 55, 1 );
        add_action( 'smartwoo_new_service_purchase_complete', array( __CLASS__, 'new_service_order_paid' ) );

        // Add Ajax actions
        add_action( 'wp_ajax_smartwoo_cancel_or_optout', array( __CLASS__, 'cancel_or_optout' ) );
        add_action( 'wp_ajax_nopriv_smartwoo_cancel_or_optout', array( __CLASS__, 'cancel_or_optout' ) );
        add_action( 'wp_ajax_smartwoo_asset_delete', array( 'SmartWoo_Service_Assets', 'ajax_delete' ) );
        add_action( 'wp_ajax_smartwoo_delete_service', 'smartwoo_delete_service' );
        add_action( 'wp_ajax_nopriv_smartwoo_delete_service', 'smartwoo_delete_service' );
        add_action( 'wp_ajax_smartwoo_dashboard', array( $this, 'dashboard_ajax' ) );
        add_action( 'wp_ajax_smartwoo_dashboard_bulk_action', array( $this, 'dashboard_ajax_bulk_action' ) );
        add_action( 'wp_ajax_smartwoo_ajax_logout', array( __CLASS__, 'ajax_logout' ) );
    }

    /** Service Subscription */
    public function service() {}

    /** Invoice */
    public function invoice() {}

    /**
     * Add useful links to our plugin row meta
     */
    public static function smartwoo_row_meta( $links, $file ) {

        if ( SMARTWOO_PLUGIN_BASENAME !== $file ) {
            return $links;
        }

        /**
         * Smart Woo Pro URL
         */
        $smartwoo_pro_url = apply_filters( 'smartwoopro_purchase_link', 'https://callismart.com.ng/smart-woo-service-invoicing' );

        /**
         * Plugin support link.
         */
        $support_url = apply_filters( 'smartwoo_support_url', 'https://callismart.com.ng/support-portal' );

        /**
         * Our github repository
         */
        $source_code    = apply_filters( 'smartwoo_source_code', 'https://github.com/CallismartLtd/smart-woo-service-invoicing' );

        /**
         * Other Products URL
         */
        $other_products = apply_filters( 'smartwoo_other_products', 'https://callismart.com.ng/pricing' );

        $smartwoo_row_meta = array(
            'smartwoo_pro'      => '<a href="' . esc_url( $smartwoo_pro_url ) . '" title="' . esc_attr__( 'Get Pro Version', 'smart-woo-service-invoicing' ) . '">' . esc_html__( 'Smart Woo Pro', 'smart-woo-service-invoicing' ) . '</a>',
            'smartwoo_support'  => '<a href="' . esc_url( $support_url ) . '" title="' . esc_attr__( 'Contact Support', 'smart-woo-service-invoicing' ) . '">' . esc_html__( 'Support', 'smart-woo-service-invoicing' ) . '</a>',
            'smartwoo_api'      => '<a href="' . esc_url( $source_code ) . '" aria-label="' . esc_attr__( 'View Source Code', 'smart-woo-service-invoicing' ) . '">' . esc_html__( 'API Documentation', 'smart-woo-service-invoicing' ) . '</a>',

        );

        return array_merge( $links, $smartwoo_row_meta );
    }

    /**
     * Add settings page URL to plugin action link
     */
    public static function options_page( $links ) {
        $setting_url = array(
			'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=sw-options' ) ) . '" aria-label="' . esc_attr__( 'View Smart Woo options', 'smart-woo-service-invoicing' ) . '">' . esc_html__( 'Settings', 'smart-woo-service-invoicing' ) . '</a>',
        );

        return array_merge( $setting_url, $links );
    }

    /**
     * Display Dashboard nav button when a configured order is checked out.
     * 
     * @param WC_Order
     */
    public function before_order_table( $order ) {
        $our_order  = apply_filters( 'smartwoo_order_details_buttons', smartwoo_check_if_configured( $order ) || $order->is_created_via( SMARTWOO ) );
        if ( $our_order ) {
            echo '<a href="' . esc_url( smartwoo_service_page_url() ) .'" class="sw-blue-button">Dashbaord</a>';
            echo '<a href="' . esc_url( smartwoo_invoice_preview_url( $order->get_meta( '_sw_invoice_id' ) ) ) .'" class="sw-blue-button">Invoice</a>';
        }
    
    }

    /*
    |------------------------------------
    | FORM POST HANDLERS
    |------------------------------------
    */

    /**
     * Login form handler
     */
    public function login_form() {
        if ( isset( $_POST['user_login'], $_POST['password'], $_POST['smartwoo_login_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smartwoo_login_nonce'] ) ), 'smartwoo_login_nonce') ) {
            $credentials = array(
                'user_login'    => sanitize_text_field( wp_unslash( $_POST['user_login'] ) ),
                'user_password' => $_POST['password'], // phpcs:disable -- Passwords shouldn't be mutilated
                'remember'      => isset( $_POST['remember_me'] )
            );


            $user = wp_signon( $credentials, false );

            if ( is_wp_error( $user ) ) {
                set_transient( 'smartwoo_login_error', $user->get_error_message(), 15 );
                wp_redirect( esc_url_raw( wp_get_referer() ) );
                exit;

            } else {
                wp_redirect( esc_url_raw( isset( $_POST['redirect'] ) ? wp_unslash( $_POST['redirect'] ): smartwoo_service_page_url() ) );
                exit;
            }
        }
    }

    /**
     * Handle the processing of new service orders.
     * 
     * @since 2.0.0
     * @since 2.0.0 Added support for service assets.
     */
    public function new_service_from_order() {
  
        if ( isset( $_POST['sw_process_new_service_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sw_process_new_service_nonce'] ) ), 'sw_process_new_service_nonce' ) ) {

            $product_id        	= isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
            $order_id          	= isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
            $service_url       	= isset( $_POST['service_url'] ) ? sanitize_url( wp_unslash( $_POST['service_url'] ), array( 'http', 'https' ) ) : '';
            $service_type      	= isset( $_POST['service_type'] ) ? sanitize_text_field( wp_unslash( $_POST['service_type'] ) ) : '';
            $user_id           	= isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : '';
            $start_date        	= isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
            $billing_cycle     	= isset( $_POST['billing_cycle'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_cycle'] ) ) : '';
            $next_payment_date 	= isset( $_POST['next_payment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['next_payment_date'] ) ) : '';
            $end_date          	= isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
            $status            	= isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
            $service_name 		= isset( $_POST['service_name'] ) ? sanitize_text_field( wp_unslash( $_POST['service_name']) ) : '';
            $service_id 		= isset( $_POST['service_id'] ) ? sanitize_text_field( wp_unslash( $_POST['service_id'] ) ) : '';
            $process_downloadable   = ! empty( $_POST['sw_downloadable_file_urls'][0] ) && ! empty( $_POST['sw_downloadable_file_names'][0] );
            $process_more_assets    = ! empty( $_POST['add_asset_types'][0] ) && ! empty( $_POST['add_asset_names'][0] ) && ! empty( $_POST['add_asset_values'][0] );

            // Validation.
            $validation_errors 	= array();

            if ( ! preg_match( '/^[A-Za-z0-9 ]+$/', $service_name ) ) {
                $validation_errors[] = 'Service name should only contain letters, and numbers.';
            }

            if ( empty( $product_id ) ) {
                $validation_errors[] = 'Product ID is missing';
            }

            if ( ! empty( $service_type ) && ! preg_match( '/^[A-Za-z0-9 ]+$/', $service_type ) ) {
                $validation_errors[] = 'Service type should only contain letters, numbers, and spaces.';
            }

            if ( ! empty( $service_url ) && filter_var( $service_url, FILTER_VALIDATE_URL ) === false ) {
                $validation_errors[] = 'Invalid service URL format.';
            }

            if ( empty( $service_id ) ) {
                $validation_errors[] = 'Service ID is required.';
            }

            if ( empty( $start_date ) || empty( $end_date ) || empty( $next_payment_date ) || empty( $billing_cycle ) ) {
                $validation_errors[] = 'All Dates must correspond to the billing circle';
            }

            if ( ! empty( $validation_errors ) ) {
                smartwoo_set_form_error( $validation_errors );
                wp_redirect( esc_url_raw( admin_url( 'admin.php?page=sw-admin&action=process-new-service&order_id=' . $order_id ) ) );
                exit;
            }

            $new_service = new SmartWoo_Service(
                $user_id,
                $product_id,
                $service_id,
                $service_name,
                $service_url,
                $service_type,
                null, // Invoice ID is null.
                $start_date,
                $end_date,
                $next_payment_date,
                $billing_cycle,
                $status
            );

                $saved_service_id = $new_service->save();

            if ( $saved_service_id ) {

                // Process downloadable assets first.
                if ( $process_downloadable ) {
                    $file_names     = array_map( 'sanitize_text_field', wp_unslash( $_POST['sw_downloadable_file_names'] ) );
                    $file_urls      = array_map( 'sanitize_url', wp_unslash( $_POST['sw_downloadable_file_urls'] ) );
                    $is_external    = isset( $_POST['is_external'] ) ? sanitize_text_field( wp_unslash( $_POST['is_external'] ) ) : 'no';
                    $asset_key      = isset( $_POST['asset_key'] ) ? sanitize_text_field( wp_unslash( $_POST['asset_key'] ) ) : '';
                    $access_limit	= isset( $_POST['access_limits'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['access_limits'] ) ) : array();

                    $downloadables  = array();
                    if ( count( $file_names ) === count( $file_urls ) ) {
                        $downloadables  = array_combine( $file_names, $file_urls );
                    }
                    
                    foreach ( $downloadables as $k => $v ) {
                        if ( empty( $k ) || empty( $v ) ) {
                            unset( $downloadables[$k] );
                        }
                    }

                    if ( ! empty( $downloadables ) ) {
                        $raw_assets = array(
                            'asset_name'    => 'downloads',
                            'service_id'    => $saved_service_id,
                            'asset_data'    => $downloadables,
                            'access_limit'  => isset( $access_limit[0] ) && '' !== $access_limit[0] ? intval( $access_limit[$index] ) : -1,
                            'is_external'   => $is_external,
                            'asset_key'     => $asset_key,
                            'expiry'        => $end_date,
                        );

                        $obj = SmartWoo_Service_Assets::convert_arrays( $raw_assets );
                        $obj->save();

                    } 
                }
                    
                if ( $process_more_assets ) {
                    /**
                     * Additional assets are grouped by their asset types, this is to say that
                     * an asset type will be stored with each asset data.
                     * 
                     * Asset data will be an extraction of a combination of each asset name and value
                     * in the form.
                     */
                    $asset_tpes     = array_map( 'sanitize_text_field', wp_unslash( $_POST['add_asset_types'] ) );
                    $the_keys       = array_map( 'sanitize_text_field', wp_unslash( $_POST['add_asset_names'] ) );
                    $the_values     = array_map( 'wp_kses_post', wp_unslash( $_POST['add_asset_values'] ) );
                    $access_limit	= isset( $_POST['access_limits'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['access_limits'] ) ) : array();

                    $asset_data = array();

                    // Attempt to pair asset names and values.
                    if ( count( $the_keys ) === count( $the_values ) ) {
                        $asset_data = array_combine( $the_keys, $the_values );
                    }

                    // If this pairing was successful.
                    if ( ! empty( $asset_data ) ) {
                        // The assets types are numerically indexed.
                        $index      = 0;
                        array_shift( $access_limit ); // Remove limit for downloadables which is already proceesed.

                        /**
                         * We loop through each part of the combined asset data to
                         * save it with an asset type in the database.
                         */
                        foreach ( $asset_data as $k => $v ) {
                            // Empty asset name or value will not be saved.
                            if ( empty( $k ) || empty( $v ) || empty( $asset_tpes[$index] ) ) {
                                unset( $asset_data[$k] );
                                unset( $asset_tpes[$index] );
                                unset( $access_limit[$index] );

                                $index++;
                                continue;
                                
                            }
    
                            // Proper asset data structure where asset name is used to identify the asset type.
                            $raw_assets = array(
                                'asset_data'    => array_map( 'wp_kses_post', wp_unslash( array( $k => $v ) ) ),
                                'asset_name'    => $asset_tpes[$index],
                                'expiry'        => $end_date,
                                'service_id'    => $saved_service_id,
                                'access_limit'  => isset( $access_limit[$index] ) && '' !== $access_limit[$index] ? intval( $access_limit[$index] ) : -1,
                            );

                            // Instantiation of SmartWoo_Service_Asset using the convert_array method.
                            $obj = SmartWoo_Service_Assets::convert_arrays( $raw_assets );
                            $obj->save();
                            $index++;
                        }
                    }
                }
                
                $order = wc_get_order( $order_id );
                
                if ( $order && 'processing' === $order->get_status()  ) {
                    $order->update_status( 'completed' );
                    $invoice_id = $order->get_meta( '_sw_invoice_id' );
                    SmartWoo_Invoice_Database::update_invoice_fields( $invoice_id, array( 'service_id' => $saved_service_id ) );
                }

                do_action( 'smartwoo_new_service_is_processed', $saved_service_id );
                wp_safe_redirect( esc_url_raw( smartwoo_service_preview_url( $saved_service_id ) ) );
                exit;
            }
        }
        smartwoo_set_form_error( 'We couldn\'t confirm the authenticity of this action.' );
        wp_safe_redirect( wp_get_referer() );
        exit;
    }

    /**
     * New invoice form handler.
     * 
     * @since 2.0.15
     */
    public static function new_invoice_form_handler() {
        if ( isset( $_POST['create_invoice'], $_POST['sw_create_invoice_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sw_create_invoice_nonce'] ) ), 'sw_create_invoice_nonce' ) ) {
            $user_id        = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
            $product_id     = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
            $invoice_type   = isset( $_POST['invoice_type'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice_type'] ) ) : '';
            $service_id     = isset( $_POST['service_id'] ) ? sanitize_text_field( wp_unslash( $_POST['service_id'] ) ) : '';
            $due_date       = isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '';				
            $fee            = isset( $_POST['fee'] ) ? floatval( $_POST['fee'] ) : 0;
            $payment_status = isset( $_POST['payment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_status'] ) ) : 'unpaid';
            // Check for a duplicate unpaid invoice for a service.
            $existing_invoice_type_for_a_service = smartwoo_evaluate_service_invoices( $service_id, $invoice_type, 'unpaid' );
    
            // Validate inputs.
            $errors = array();
            if ( $existing_invoice_type_for_a_service ) {
                $errors[] = 'This Service has "' . $invoice_type . '" That is ' . $payment_status;
            }
    
            if ( empty( $user_id ) ) {
                $errors[] = 'User ID is required.';
            }
    
            if ( empty( $product_id ) ) {
                $errors[] = 'Service Product is required.';
            }
    
            if ( empty( $invoice_type ) ) {
                $errors[] = 'Please select a valid Invoice Type.';
            }
    
            if ( empty( $payment_status ) ) {
                $errors[] = 'Please select Payment Status';
            }
    
            if ( empty( $due_date ) ) {
                $errors[] = 'Due Date is required';
            }

            $errors = apply_filters( 'smartwoo_handling_new_invoice_form_error', $errors );
    
            if ( ! empty( $errors ) ) {
                smartwoo_set_form_error( $errors );
                wp_safe_redirect( admin_url( 'admin.php?page=sw-invoices&tab=add-new-invoice' ) );
                exit;
            }

            $createdInvoiceID = smartwoo_create_invoice( $user_id, $product_id, $payment_status, $invoice_type, $service_id, $fee, $due_date );

            if ( $createdInvoiceID ) {
                do_action( 'smartwoo_handling_new_invoice_form_success', $createdInvoiceID );
                $detailsPageURL = esc_url( admin_url( "admin.php?page=sw-invoices&tab=view-invoice&invoice_id=$createdInvoiceID" ) );
                smartwoo_set_form_success( 'Invoice created successfully! <a href="' . esc_url( $detailsPageURL ) .'">' . __( 'View Invoice Details', 'smart-woo-service-invoicing' ) .'</a>' );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=sw-invoices&tab=add-new-invoice' ) );
            exit; 
        }
        smartwoo_set_form_error( 'Something went wrong' );
        wp_safe_redirect( admin_url( 'admin.php?page=sw-invoices&tab=add-new-invoice' ) );
        exit;
    }

    /**
     * Instance of current class.
     */
    public static function instance() {

        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
    }

    /**
     * File download handler
     */
    public function download_handler() {
        if ( ! isset( $_GET['smartwoo_action'] )  || $_GET['smartwoo_action'] !== 'smartwoo_download' ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) ), 'smartwoo_download_nonce' ) ) {
            wp_die( 'Authentication failed', 401 );
        }
    
        if ( ! is_user_logged_in() ) {
            return smartwoo_login_form( array( 'notice' => smartwoo_notice( 'You must be logged in to access this page.' ), 'redirect' => add_query_arg( array_map( 'rawurlencode', $_GET ) ) ) );
        }
        
        $asset_id       = ! empty( $_GET['asset_id'] ) ? absint( $_GET['asset_id'] ) : 0;
        $resource_id    = ! empty( $_GET['resource_id'] ) ? absint( wp_unslash( $_GET['resource_id'] ) ) : '';
        $asset_key      = ! empty( $_GET['key'] ) ? sanitize_key( wp_unslash( $_GET['key'] ) ): '';
        $service_id     = ! empty( $_GET['service_id'] ) ? sanitize_key( wp_unslash( $_GET['service_id'] ) ) : '';
        if ( empty( $resource_id ) || empty( $service_id ) || ! SmartWoo_Service_Assets::verify_key( $asset_key, $resource_id ) ) {
            wp_die( 'Unable to validate requested resource.', 403 );
        }

        // Check Asset validity via parent service.
        $service = SmartWoo_Service_Database::get_service_by_id( $service_id );
        if ( ! $service || ! $service->current_user_can_access() ) {
            wp_die( 'Invalid service subscription.', 404 );
        }

        // Check service status.
        $status = smartwoo_service_status( $service );
        if ( ! in_array( $status, smartwoo_active_service_statuses(), true ) ) {
            wp_die( 'Service is not active, please renew it.', 403 );
        }

        $asset_data = SmartWoo_Service_Assets::return_data( $asset_id, $asset_key, $obj );

        if ( ! is_array( $asset_data ) || empty( $asset_data ) ) {
            wp_die( 'Invalid data format returned', 403 );
        }

        $re_indexed_data    = array_values( (array) $asset_data );
        $resource_url       = array_key_exists( $resource_id - 1, $re_indexed_data ) ? $re_indexed_data[$resource_id - 1]: wp_die( 'File URL not found.', 404 );
        $is_external        = $obj->is_external();
        $this->serve_file( $resource_url, wc_string_to_bool( $is_external ), $asset_key );
    }
    
    /**
     * Serve file for download.
     */
    private function serve_file( $resource_url, $is_external = false, $asset_key = '' ) {
        
        // Serve files within the current site.
        if ( ! $is_external ) {
            $resource_url   = sanitize_url( $resource_url, array( 'http', 'https' ) );
            $file_headers   = @get_headers( $resource_url, 1 );
        
            if ( ! $file_headers || strpos( $file_headers[0], '200' ) === false ) {
                wp_die( 'File not found.', 404 );
            }
        
            $content_type   = $file_headers['Content-Type'] ?? 'application/octet-stream';
            $content_length = $file_headers['Content-Length'] ?? 0;
            $filename       = basename( wp_parse_url( $resource_url, PHP_URL_PATH ) );
        
            header( 'Content-Description: File Transfer' );
            header( 'Content-Type: ' . $content_type );
            header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
            header( 'Expires: 0' );
            header( 'Cache-Control: must-revalidate' );
            header( 'Pragma: public' );
            header( 'Content-Length: ' . $content_length );
        
            // Open the file and stream it to the browser.
            // phpcs:disable
            $handle = fopen( $resource_url, 'rb' );
            if ( $handle ) {
                while ( ! feof( $handle ) ) {
                    echo fread( $handle, 8192 );
                    ob_flush();
                    flush();
                }
                fclose( $handle );
                // phpcs:enable
            } else {
                wp_die( 'Unable to read the file.' );
            }
        
            exit;
        }
        
        if ( true === $is_external ) {

            // Serve download for remote URLs.
            if ( empty( $asset_key ) ) {
                wp_die( 'Asset key not found.' );
            }

            if ( ! function_exists( 'download_url' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $this->append_http_authorization( $asset_key, $resource_url );
            $file = download_url( $resource_url );

            if ( is_wp_error( $file ) ) {
                wp_die( wp_kses_post( $file->get_error_message() ) );
            }

            global $wp_filesystem;

            if ( ! WP_Filesystem() ) {
                wp_die( 'Could not initialize WP Filesystem.' );
            }

            // Check if the file exists and is accessible via WP_Filesystem
            if ( ! $wp_filesystem->exists( $file ) ) {
                wp_die( 'File not found or inaccessible.' );
            }
            
            // Determine the content type of the file
            $mime_type = 'application/octet-stream'; // Default MIME type.

            if ( function_exists( 'finfo_open' ) ) {
                $finfo     = finfo_open( FILEINFO_MIME_TYPE );
                $mime_type = finfo_file( $finfo, $file );
                finfo_close( $finfo );
            } elseif ( function_exists( 'mime_content_type' ) ) {
                $mime_type = mime_content_type( $file );
            }

            // Get the file size
            $file_size = $wp_filesystem->size( $file );
            $filename       = basename( wp_parse_url( $resource_url, PHP_URL_PATH ) );

            // Set headers
            header( 'Content-Description: File Transfer' );
            header( 'Content-Type: ' . $mime_type );
            header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
            header( 'Content-Length: ' . $file_size );
            header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
            header( 'Pragma: public' );
            header( 'Expires: 0' );

            // Output the file content using WP_Filesystem
            $file_content = $wp_filesystem->get_contents( $file );
            if ( false === $file_content ) {
                wp_die( 'Could not read the file, maybe corrupted.' );
            }

            echo $file_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

            $wp_filesystem->delete( $file );

        }

        exit;
        
    }

    /**
     * Set Authorization header for outgoing HTTP remote requests.
     * 
     * @param string $token The authorization bearer token.
     * @param string $resource_url The URL of the outgoing HTTP request.
     */
    private function append_http_authorization( $token = '', $resource_url = '' ) {
        add_filter( 'http_request_args', function( $args, $url ) use ( $token, $resource_url ) {
            if ( $resource_url === $url ) {
                $args['headers']['Authorization'] = 'Bearer ' . $token;
            }
            return $args;
        }, 10, 2 );
    }

    /**
     * Dashboard page Ajax handler
     * 
     * @since 2.0.12
     */
    public function dashboard_ajax() {
        if ( ! check_ajax_referer( sanitize_text_field( wp_unslash( 'smart_woo_nonce' ) ), 'security', false ) ) {
            wp_send_json_error( array( 'message' => 'Action failed basic authentication.' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have the required permission to perform this action' ) );

        }

        add_filter( 'smartwoo_is_frontend', '__return_false' );

        $allowed_actions   = apply_filters( 'smartwoo_allowed_dashboard_actions',
            array(
                'sw_search',
                'total_services',
                'total_pending_services',
                'total_active_services',
                'total_active_nr_services',
                'total_due_services',
                'total_on_grace_services',
                'total_expired_services',
                'total_cancelled_services',
                'total_suspended_services',
                'all_services_table',
                'all_pending_services_table',
                'all_active_services_table',
                'all_active_nr_services_table',
                'all_due_services_table',
                'all_on_grace_services_table',
                'all_expired_services_table',
                'all_cancelled_services_table',
                'all_suspended_services_table',
                
            )
        );

        $action = isset( $_GET['real_action'] ) ? sanitize_text_field( wp_unslash( $_GET['real_action'] ) ) : wp_die();

        if ( ! in_array( $action, $allowed_actions, true ) ){
            wp_send_json_error( array( 'message' => 'action is not allowed' ) );
        }
        
        if ( 'total_services' === $action ) {
            $total  = get_option( 'smartwoo_all_services_count', 0 );
            wp_send_json_success( array( 'total_services' =>  absint( $total ) ) );

        }
        
        if ( 'total_pending_services' === $action ) {
            $total  = smartwoo_count_unprocessed_orders();
            wp_send_json_success( array( 'total_pending_services' =>  absint( $total ) ) );

        }
        
        if ( 'total_active_services' === $action ) {
            $total  = smartwoo_count_active_services();
            wp_send_json_success( array( 'total_active_services' =>  absint( $total ) ) );
        } 
        
        if ( 'total_active_nr_services' === $action ) {
            $total = smartwoo_count_nr_services();
            wp_send_json_success( array( 'total_active_nr_services' =>  absint( $total ) ) );

        }
        
        if ( 'total_due_services' === $action ) {
            $total = smartwoo_count_due_for_renewal_services();
            wp_send_json_success( array( 'total_due_services' =>  absint( $total ) ) );

        } 
        
        if ( 'total_on_grace_services' === $action ) {
            $total = smartwoo_count_grace_period_services();
            wp_send_json_success( array( 'total_on_grace_services' =>  absint( $total ) ) );

        }
        
        if ( 'total_expired_services' === $action ) {
            $total = smartwoo_count_expired_services();
            wp_send_json_success( array( 'total_expired_services' =>  absint( $total ) ) );

        }
        
        if ( 'total_cancelled_services' === $action ) {
            $total = smartwoo_count_cancelled_services();
            wp_send_json_success( array( 'total_cancelled_services' =>  absint( $total ) ) );

        }
        
        if ( 'total_suspended_services' === $action ) {
            $total = smartwoo_count_suspended_services();
            wp_send_json_success( array( 'total_suspended_services' =>  absint( $total ) ) );

        }

        if ( 'all_pending_services_table' === $action ) {
            wp_safe_redirect( admin_url( 'admin.php?page=sw-service-orders') );
            exit;
        }

        $limit  = isset( $_GET['limit'] ) ? intval( $_GET['limit'] ) : 10;
        $paged  = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;

        /**
         * Send json data for table structures.
         */
        if ( 'sw_search' === $action ) {
            $all_services   = SmartWoo_Service_Database::search();
            $total_services = count( $all_services );
            
        } elseif ( 'all_services_table' === $action ) {
            $all_services   = SmartWoo_Service_Database::get_all();
            $total_services = absint( get_option( 'smartwoo_all_services_count', 0 ) );

        } elseif ( 'all_active_services_table' === $action ) {
            $all_services = SmartWoo_Service_Database::get_all_active( $paged, $limit );
            $total_services = smartwoo_count_active_services();

        } elseif ( 'all_active_nr_services_table' === $action ) {
            $all_services = SmartWoo_Service_Database::get_( array( 'status' => 'Active (NR)', 'page' => $paged, 'limit' => $limit ) );
            $total_services = smartwoo_count_nr_services();
        } elseif ( 'all_due_services_table' === $action ) {
            $all_services = SmartWoo_Service_Database::get_all_due( $paged, $limit );
            $total_services = smartwoo_count_due_for_renewal_services();
        } elseif ( 'all_on_grace_services_table' === $action ) {
            $all_services = SmartWoo_Service_Database::get_all_on_grace( $paged, $limit );
            $total_services = smartwoo_count_grace_period_services();
        } elseif ( 'all_expired_services_table' === $action ) {
            $all_services = SmartWoo_Service_Database::get_all_expired( $paged, $limit );
            $total_services = smartwoo_count_expired_services();
        } elseif ( 'all_cancelled_services_table' === $action ) {
            $all_services = SmartWoo_Service_Database::get_( array( 'status' => 'Cancelled', 'page' => $paged, 'limit' => $limit ) );
            $total_services = smartwoo_count_cancelled_services();
        } elseif ( 'all_suspended_services_table' === $action ) {
            $all_services = SmartWoo_Service_Database::get_( array( 'status' => 'Suspended', 'page' => $paged, 'limit' => $limit ) );
            $total_services = smartwoo_count_suspended_services();
        }

        $total_pages    = ceil( $total_services / $limit );
        $data           = array();
        $row_names      = array();

        if ( ! empty( $all_services ) ) {
            foreach ( $all_services as $service ) {
                $data[] = array( $service->getServiceName(), $service->getServiceId(), smartwoo_service_status( $service ) );
                $row_names[] = $service->getServiceId();
            }
            
        }

        $response   = array(
            'table_header'  => array(
                'Service Name',
                'Service ID',
                'Status',
            ),

            'table_body'    => $data,
            'row_names'     => $row_names,
            'total_pages'   => $total_pages,
            'current_page'  => $paged,
        );

        wp_send_json_success( array( 'all_services_table' => $response ) );
    }

    /**
     * Dashboard bulk action handler.
     */
    public function dashboard_ajax_bulk_action() {
        if ( ! check_ajax_referer( sanitize_text_field( wp_unslash( 'smart_woo_nonce' ) ), 'security', false ) ) {
            wp_send_json_error( array( 'message' => 'Action failed basic authentication.' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have the required permission to perform this action' ) );

        }

        add_filter( 'smartwoo_is_frontend', '__return_false' );
        $allowed_actions    = array(
            'auto_calc',
            'Active',
            'Active (NR)',
            'Suspended',
            'Cancelled',
            'Due for Renewal',
            'Expired',
            'delete'
        );
        
        $action = isset( $_POST['real_action'] ) ? sanitize_text_field( wp_unslash( $_POST['real_action'] ) ) : false;

        if ( ! $action ) {
            wp_send_json_error( array('message' => 'Real action missing.' ) );
        }
        
        if ( ! in_array($action, $allowed_actions, true ) ) {
            wp_send_json_error( array( 'message' => 'Action is not allowed' ) );
        }
        $service_ids    = isset( $_POST['service_ids'] ) && is_array( $_POST['service_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['service_ids'] ) ) : array();
        
        if ( empty( $service_ids ) ) {
            wp_send_json_error( array( 'message' => 'No service ID was provided.' ) );
        }

        $service_noun = ( count( $service_ids ) > 1 ) ? "Services": "Service";
        if ( 'auto_calc' === $action ) {
            $message        = "Automatic calculation applied to the selected " . $service_noun;
            $field_value    = null;

        } elseif ( 'Active' === $action ) {
            $message        = $service_noun . " has been activated.";
            $field_value    = 'Active';
        } elseif ( 'Active (NR)' === $action ) {
            $message        = $service_nounce . " has been activated but will not renew on next payment date";
            $field_value    = 'Active (NR)';
        } elseif ( 'Suspended' === $action ) {
            $message        = $service_noun . " has been suspended.";
            $field_value    = 'Suspended';
        } elseif ( 'Cancelled' === $action ) {
            $message        = $service_noun . " has been cancelled";
            $field_value    = 'Cancelled';
        } elseif ( 'Due for Renewal' === $action ) {
            $message        = $service_noun . " now Due for renewal";
            $field_value    = 'Due for Renewal';
        } elseif ( 'Expired' === $action ) {
            $message        = $service_noun . " has been expired";
            $field_value    = 'Expired';
        } elseif ( 'delete' === $action ) {
            $message        = $service_noun . " has been deleted";
        }

        
        foreach ( $service_ids as $service_id ) {
            if ( 'delete' !== $action ) {
                SmartWoo_Service_Database::update_service_fields( $service_id, array('status' => $field_value ) );
                continue;
            }
            
            if ( 'delete' === $action ) {
                SmartWoo_Service_Database::delete_service( $service_id );
            }
        }



        wp_send_json_success( array('message' => $message ) );
    }

    /**
     * Count all services in the database every five hours.
     * 
     * @since 2.0.12.
     */
    public static function count_all_services() {
        $count  = SmartWoo_Service_Database::count_all();
        update_option( 'smartwoo_all_services_count', $count );
    }

    /**
     * Logout ajax handler
     * 
     * @since 2.0.13
     */
    public static function ajax_logout() {
        check_ajax_referer( 'smart_woo_nonce', 'security' );
        wp_logout();
        wp_send_json_success();
    }

    /**
     * Initiates an automatic service renewal process by creating renewal invoice on due date
     * for services that are due.
     *
     * @Do_action "smartwoo_auto_invoice_created" triggers after successful invoice creation
     * @return bool False if no service is due | True otherwise
     */
    public static function auto_renew_due() {
        add_filter( 'smartwoo_is_frontend', '__return_false' ); // Ensures the process runs in backend context.

        $args = get_transient( 'smartwoo_auto_renew_args' );
        if ( false === $args ) {
            $args = array( 'page' => 1, 'limit' => 20 ); // Default pagination args
        }

        // Fetch due services
        $all_services = SmartWoo_Service_Database::get_all_due( $args['page'], $args['limit'] );
        
        if ( empty( $all_services ) ) {
            delete_transient( 'smartwoo_auto_renew_args' ); // Remove the transient if no more services are due
            return false;
        }

        $invoices_created = false;

        foreach ( $all_services as $service ) {
            $user_id        = $service->getUserId();
            $service_id     = $service->getServiceId();
            $service_name   = $service->getServiceName();
            $product_id     = $service->getProductId();
            $service_status = smartwoo_service_status( $service );

            // Check if the service is due for renewal
            if ( 'Due for Renewal' === $service_status ) {
                $existing_invoice_id = smartwoo_evaluate_service_invoices( $service_id, 'Service Renewal Invoice', 'unpaid' );
                
                if ( $existing_invoice_id ) {
                    continue; // Skip if unpaid renewal invoice already exists
                }

                // Prepare invoice data
                $payment_status = 'unpaid';
                $invoice_type   = 'Service Renewal Invoice';
                $date_due       = current_time( 'mysql' );

                // Create a new unpaid invoice
                $new_invoice_id = smartwoo_create_invoice( $user_id, $product_id, $payment_status, $invoice_type, $service_id, null, $date_due );
                
                if ( $new_invoice_id ) {
                    // Retrieve invoice object and trigger action
                    $newInvoice = SmartWoo_Invoice_Database::get_invoice_by_id( $new_invoice_id );
                    do_action( 'smartwoo_auto_invoice_created', $newInvoice, $service );
                    $invoices_created = true; // Mark that an invoice was created
                }
            }
        }

        // Increment page for next batch of services
        $args['page']++;
        set_transient( 'smartwoo_auto_renew_args', $args, 12 * HOUR_IN_SECONDS ); // Store pagination data

        return $invoices_created;
    }

    /**
     * Handles service renewal when the client clicks the renew button on
     * Service Details page
     */
    public static function manual_renew_due() {

        // Verify the nonce
        if ( isset( $_GET['renew_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['renew_nonce'] ) ), 'renew_service_nonce' ) ) {
            
            $service_id = isset( $_GET['service_id'] ) ? sanitize_text_field( wp_unslash( $_GET['service_id'] ) ): '';
            $service    = SmartWoo_Service_Database::get_service_by_id( $service_id );

            if ( ! $service || $service->getUserId() !== get_current_user_id() ) {
                wp_die( 'Error: Service does not exist.', 404 );
            }

            $service_status = smartwoo_service_status( $service );
            if ( 'Due for Renewal' === $service_status || 'Expired' === $service_status || 'Grace Period' === $service_status ) {
                $invoice_type   = 'Service Renewal Invoice';
                $has_invoice_id = smartwoo_evaluate_service_invoices( $service_id, $invoice_type, 'unpaid' );
                
                if ( $has_invoice_id ) {
                    smartwoo_redirect_to_invoice_preview( $has_invoice_id );
                }

                $product_id     = $service->getProductId();
                $payment_status = 'unpaid';
                $date_due       = current_time( 'mysql' );

                // Generate Unpaid invoice
                $new_invoice_id = smartwoo_create_invoice( get_current_user_id(), $product_id, $payment_status, $invoice_type, $service_id, null, $date_due );

                if ( $new_invoice_id ) {
                    $the_invoice   = SmartWoo_Invoice_Database::get_invoice_by_id( $new_invoice_id );
                    smartwoo_send_user_generated_invoice_mail( $the_invoice, $service );
                    $checkout_url = $the_invoice->pay_url();
                    wp_safe_redirect( $checkout_url );
                    exit;
                }
            }
        }
        
    }

    /**
     * Handle the payment link, verify the token, log in the user, and process the payment.
     */
    public static function payment_link_handler() {
        
        if ( isset( $_GET['action'] ) && 'sw_invoice_payment' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ): wp_die('Missing token' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            $payment_info = smartwoo_verify_token( $token );

            if ( ! $payment_info ) {
                // Token is invalid or expired.
                wp_die( 'Invalid or expired link', 401 );
            }
            // Extract relevant information.
            $invoice_id = $payment_info['invoice_id'];
            $user_email = $payment_info['user_email'];
            $user		= get_user_by( 'email', $user_email );

            if ( ! $user ) {
                wp_die( 'User not found', 403 );
            }
                
            // Make sure the SmartWoo_Invoice_Database class is defined and loaded.
            if ( ! class_exists( 'SmartWoo_Invoice_Database' ) ) {
                wp_die( 'Invoice is not fully loaded', 425 );
            }

            $invoice = SmartWoo_Invoice_Database::get_invoice_by_id( $invoice_id );

            if ( ! $invoice ) {
                wp_die( 'Invoice not found', 404 );
            }

            $user_id         = $user->ID;
            $invoice_status  = $invoice->getPaymentStatus();
            $invoice_user_id = $invoice->getUserId();

            if ( $invoice_user_id !== $user_id ) {
                wp_die( 'You don\'t have the required permission to pay for this invoice, contact us if you need help', 403 );
            }

            $order_id 	= $invoice->getOrderId();
            $order 		= wc_get_order( $order_id );

            if ( $order && 'pending' !== $order->get_status() || 'unpaid' !== $invoice_status ) {
                wp_die( 'Invoice cannot be paid for, contact us if you need further assistance' );
            }
            
            // Conditions has been met, user should be logged in.
            wp_set_current_user( $user->ID, $user->user_login );
            wp_set_auth_cookie( $user->ID );
            do_action( 'wp_login', $user->user_login, $user );
            // Redirect to the order pay page.
            wp_safe_redirect( smartwoo_invoice_pay_url( $order_id ) );
            exit();
        }
    }

    /**
     * Handle Quick Action button on the Service Details page (frontend).
     *
     * This function is hooked into WordPress template redirection to handle actions related
     * to service cancellation or billing cancellation based on the 'action' parameter in the URL.
     */

    public static function cancel_or_optout() {

        if ( ! check_ajax_referer( sanitize_text_field( wp_unslash( 'smart_woo_nonce' ) ), 'security' ) ) {
            wp_die( -1, 401 );
        }

        if ( ! is_user_logged_in() ) {
            wp_die( -1, 403 );
        }

        $action 				= isset( $_POST['selected_action'] ) ? sanitize_key( $_POST['selected_action'] ) : '';
        $ajax_service_id 		= isset( $_POST['service_id'] ) ? sanitize_key( $_POST['service_id'] ) : '';
        
        if ( empty( $action) && empty( $ajax_service_id ) ) {
            wp_die( -1, 406 );

        }

        $service	= SmartWoo_Service_Database::get_service_by_id( sanitize_text_field( $ajax_service_id ) );

        if ( ! $service || $service->getUserId() !== get_current_user_id() ) {
            wp_die( -1, 404 );
        }
        
        $user_id  				= get_current_user_id();
        $service_id				= $service->getServiceId();
        $next_service_status	= null;
        $user_cancelled_service	= false;
        $user_opted_out			= false;

        if ( 'sw_cancel_service' === $action ) {
            $next_service_status ='Cancelled';
            $user_cancelled_service = true;
        } elseif ( 'sw_cancel_billing' === $action ) {
            $next_service_status ='Active (NR)';
            $user_opted_out = true;

        }

        SmartWoo_Service_Database::update_service_fields( $service_id, array( 'status' => $next_service_status ) );

        if ( $user_cancelled_service ) {

            /**
             * @action_hook smartwoo_user_cancelled_service Fires When service is cancelled.
             * @action_hook smartwoo_service_deactivated Separate hooks which fire when Service 
             * is deactivated should be simulated.
             */
            do_action( 'smartwoo_user_cancelled_service', $service_id );
            do_action( 'smartwoo_service_deactivated', $service );

        } elseif ( $user_opted_out ) {
            do_action( 'smartwoo_user_opted_out', $service_id ); 
        }
    }

    /**
     * Invoice order payment handler.
     *
     * @param int $order_id    The paid invoice order.
     */
    public static function paid_invoice_order_manager( $order_id ) {
        $order		= wc_get_order( $order_id );
        $invoice_id = $order->get_meta( '_sw_invoice_id' );

        // Early termination if the order is not related to our plugin.
        if ( empty( $invoice_id ) ) {
            return;
        }
        // Prevent multiple function execution on single load
        if ( defined( 'SMARTWOO_PAID_INVOICE_MANAGER' ) && SMARTWOO_PAID_INVOICE_MANAGER ) {
            return;
        }
        define( 'SMARTWOO_PAID_INVOICE_MANAGER', true );

        $invoice = SmartWoo_Invoice_Database::get_invoice_by_id( $invoice_id );

        // Terminate if no invoice is gotten with the ID, which indicates invalid invoice ID.
        if ( empty( $invoice ) ) {
            return;
        }

        $invoice_type = $invoice->getInvoiceType();

        if ( 'New Service Invoice' ===  $invoice_type ) {
            /**
             * This action fires when order is for a new service order.
             */
            do_action( 'smartwoo_new_service_purchase_complete', $invoice_id );
            return;
        }

        $service_id		= $invoice->getServiceId();

        // If Service ID is available, this indicates an invoice for existing service.
        if ( ! empty( $service_id ) ) {
            $service_status = smartwoo_service_status( $service_id );
            /**
             * Determine if the invoice is for the renewal of a Due service.
             * Only invoices for services on this status are considered to be for renewal.
             */
            if ( 'Due for Renewal' === $service_status || 'Grace Period' === $service_status && 'Service Renewal Invoice' === $invoice_type ) {

                self::renew_service( $service_id, $invoice_id );
                
                /**
                 * Determine if the invoice is for the reactivation of an Expired service.
                 * Only invoices for services on this status are considered to be for reactivation.
                 */
            } elseif ( $service_status === 'Expired' && $invoice_type === 'Service Renewal Invoice' ) {
                // Call the function to reactivate the service.
                self::activate_expired_service( $service_id, $invoice_id );
                
            }

            /**
             * Fires when existing service has a paid invoice which is not handled here.
             * 
             * @since 1.0.4
             */
            do_action( 'smartwoo_invoice_for_existing_service_paid', $service_id, $invoice_id, $invoice_type  );
        }
    }

    /**
     * Renew a service.
     *
     * This performs service renewal, relying on the confirmation that
     * the invoice is paid. If the invoice is
     * not paid, the function will return early.
     *
     * @param string $service_id ID of the service to be renewed.
     * @param string $invoice_id ID of the invoice related.
     */
    public static function renew_service( $service_id, $invoice_id ) {
        $service = SmartWoo_Service_Database::get_service_by_id( $service_id );
        $invoice = SmartWoo_Invoice_Database::get_invoice_by_id( $invoice_id );
        // Mark the invoice as paid before renewing the service.
        $invoice_is_paid = smartwoo_mark_invoice_as_paid( $invoice_id );

        if ( false === $invoice_is_paid ) {
            // Invoice is already paid, or something went wrong.
            return;
        }

        if ( $service ) {

            // Add Action Hook Before Updating Service Information.
            do_action( 'smartwoo_before_service_renew', $service );

            // Calculate Renewal Dates based on Billing Cycle.
            $billing_cycle = $service->getBillingCycle();
            $old_end_date  = strtotime( $service->getEndDate() );

            switch ( $billing_cycle ) {
                case 'Monthly':
                    $interval = '+1 month';
                    break;
                case 'Quarterly':
                    $interval = '+3 months';
                    break;
                case 'Six Monthly':
                    $interval = '+6 months';
                    break;
                case 'Yearly':
                    $interval = '+1 year';
                    break;
                default:
                    break;
            }

            // Calculate new dates and implement.
            $new_start_date        = date_i18n( 'Y-m-d', $old_end_date );
            $new_end_date          = date_i18n( 'Y-m-d', strtotime( $interval, $old_end_date ) );
            $new_next_payment_date = date_i18n( 'Y-m-d', strtotime( '-7 days', strtotime( $new_end_date ) ) );
            $service->setStartDate( $new_start_date );
            $service->setNextPaymentDate( $new_next_payment_date );
            $service->setEndDate( $new_end_date );
            $service->setStatus( null ); // Renewed service will be automatically calculated.
            $updated = SmartWoo_Service_Database::update_service( $service );
            do_action( 'smartwoo_service_renewed', $service );

        }
    }

    /**
     * Activate an expired service.
     *
     * This performs service renewal, relying on the confirmation that
     * the invoice ID provided in the third parameter is paid. If the invoice is
     * not paid, the function will return early.
     *
     * @param string $service_id ID of the service to be renewed.
     * @param string $invoice_id ID of the invoice related to the service renewal.
     */
    public static function activate_expired_service( $service_id, $invoice_id ) {
        $expired_service = SmartWoo_Service_Database::get_service_by_id( $service_id );
        $invoice         = SmartWoo_Invoice_Database::get_invoice_by_id( $invoice_id );
        $invoice_is_paid = smartwoo_mark_invoice_as_paid( $invoice_id );

        if ( $invoice_is_paid === false ) {
            // Invoice is already paid or something went wrong.
            return;
        }

        if ( $expired_service ) {

            // Add Action Hook Before Updating Service Information.
            do_action( 'smartwoo_before_activate_expired_service', $expired_service );

            $order_id        = $invoice->getOrderId();
            $order           = wc_get_order( $order_id );
            $order_paid_date = $order->get_date_paid()->format( 'Y-m-d H:i:s' );

            // 4. Calculate Activation Dates based on Billing Cycle.
            $billing_cycle = $expired_service->getBillingCycle();

            switch ( $billing_cycle ) {
                case 'Monthly':
                    $interval = '+1 month';
                    break;
                case 'Quarterly':
                    $interval = '+3 months';
                    break;
                case 'Six Monthtly':
                    $interval = '+6 months';
                    break;
                case 'Yearly':
                    $interval = '+1 year';
                    break;
                default:
                    break;
            }

            // Calculate new dates and implement.
            $new_start_date        = $order_paid_date;
            $new_end_date          = date_i18n( 'Y-m-d', strtotime( $interval, strtotime( $new_start_date ) ) );
            $new_next_payment_date = date_i18n( 'Y-m-d', strtotime( '-7 days', strtotime( $new_end_date ) ) );
            $expired_service->setStartDate( $new_start_date );
            $expired_service->setNextPaymentDate( $new_next_payment_date );
            $expired_service->setEndDate( $new_end_date );
            $expired_service->setStatus( null );
            $updated = SmartWoo_Service_Database::update_service( $expired_service );
            smartwoo_renewal_sucess_email( $expired_service );

            // Add Action Hook After Service Activation.
            do_action( 'smartwoo_expired_service_activated', $expired_service );
            return true;
        }

        return false;
    }

    /**
     * Perform action when a new service purchase is complete
     *
     * @param string $invoice_id The invoice ID.
     */
    public static function new_service_order_paid( $invoice_id ) {
        // Mark invoice as paid.
        smartwoo_mark_invoice_as_paid( $invoice_id );
    }

    /**
     * Handle admin invoice download
     */
    public static function admin_download_invoice() {
        if ( isset( $_GET['_sw_download_token'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_sw_download_token'] ) ), '_sw_download_token' ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'You do not have the required permision to download this invoice' );
            }

            $invoice_id = isset( $_GET['invoice_id'] ) ? sanitize_text_field( wp_unslash( $_GET['invoice_id'] ) ) : wp_die( 'Missing Invoice ID' );
            $invoice    = SmartWoo_Invoice_Database::get_invoice_by_id( $invoice_id );

            if ( empty( $invoice ) ) {
                wp_die( 'Invalid or deleted invoice' );
            }

            smartwoo_pdf_invoice_template( $invoice_id, $invoice->getUserId() );
            exit;
        }

    }
    
}

SmartWoo::instance();