<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

/**
 * @package   Bank Indonesia
 * @author    Reynaldi Arya
 * @category  Checkout Page
 * @copyright Copyright (c) 2021
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 **/

// Begin creating the bank function
class WC_Gateway_OVO extends WC_Gateway_BACS {
	function __construct() {
		$this->id = "ovo";
		$this->method_title = __( "OVO", 'woocommerce' );
		$this->method_description = __( "Pembayaran melalui OVO", 'woocommerce' );
		$this->title = __( "Transfer OVO", 'woocommerce' );
		$this->icon = $this->enable_icon = 'yes' === $this->get_option( 'enable_icon' ) ? plugins_url('assets/logo-ovo.png',__FILE__) : null;
		$this->has_fields = false;
		$this->init_form_fields();
		$this->init_settings();

		foreach ( $this->settings as $setting_key => $value ) {
          $this->$setting_key = $value;
      }
      if ( is_admin() ) {
         add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
         add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
         $this -> generate_account_details_html();
     }
     add_action( 'woocommerce_thankyou_ovo', array( $this, 'thankyou_page' ) );
     add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

    // BACS account fields shown on the thanks page and in emails
     $this->account_details = get_option( 'woocommerce_ovo_accounts',
        array(
            array(
                'account_name'   => $this->get_option( 'account_name' ),
                'account_number' => $this->get_option( 'account_number' ),
                'sort_code'      => $this->get_option( 'sort_code' ),
                'bank_name'      => $this->get_option( 'bank_name' ),
                'iban'           => $this->get_option( 'iban' ),
                'bic'            => $this->get_option( 'bic' )
            )
        )
    );

 }

// Build the setting fields
 public function init_form_fields() {
    $this->form_fields = array(
        'enabled' => array(
            'title'     => __( 'Enable / Disable', 'woocommerce' ),
            'label'     => __( 'Enable <strong>OVO</strong> Transfer Payment Method', 'woocommerce' ),
            'type'      => 'checkbox',
            'default'   => 'no',
        ),
        'title' => array(
            'title'     => __( 'Title', 'woocommerce' ),
            'type'      => 'text',
            'desc_tip'  => __( 'Ini mengatur judul yang dilihat konsumen saat membayar', 'woocommerce' ),
            'default'   => __( 'Transfer OVO', 'woocommerce' ),
        ),
        'enable_icon' => array(
            'title' => __('Payment Icon', 'woocommerce'),
            'label' => __('Enable Icon', 'woocommerce'),
            'type' => 'checkbox',
            'description' => '<img src="'.plugins_url('assets/logo-ovo.png',__FILE__).'" style="height:100%;max-height:32px !important" />',
            'default' => 'no',
        ),
        'description' => array(
            'title'     => __( 'Description', 'woocommerce' ),
            'type'      => 'textarea',
            'desc_tip'  => __( 'Deskripsi metode pembayaran yang akan dilihat pelanggan di halaman checkout Anda.', 'woocommerce' ),
            'default'   => __( '', 'woocommerce' ),
            // 'css'       => 'max-width:350px;'
        ),
        'instructions' => array(
            'title'       => __( 'Instructions', 'woocommerce' ),
            'type'        => 'textarea',
            'description' => __( 'Instruksi yang akan ditambahkan ke halaman terima kasih dan email.', 'woocommerce' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'account_details' => array(
            'type'        => 'account_details'
        ),
    );      
}	

// Saving
public function save_account_details() {

    $accounts = array();

    if ( isset( $_POST['bacs_account_name'] ) ) {

        $account_names   = array_map( 'wc_clean', $_POST['bacs_account_name'] );
        $account_numbers = array_map( 'wc_clean', $_POST['bacs_account_number'] );
        $bank_names      = array_map( 'wc_clean', $_POST['bacs_bank_name'] );
        $sort_codes      = array_map( 'wc_clean', $_POST['bacs_sort_code'] );
        $ibans           = array_map( 'wc_clean', $_POST['bacs_iban'] );
        $bics            = array_map( 'wc_clean', $_POST['bacs_bic'] );

        foreach ( $account_names as $i => $name ) {
            if ( ! isset( $account_names[ $i ] ) ) {
                continue;
            }

            $accounts[] = array(
                'account_name'   => $account_names[ $i ],
                'account_number' => $account_numbers[ $i ],
                'bank_name'      => $bank_names[ $i ],
                'sort_code'      => $sort_codes[ $i ],
                'iban'           => $ibans[ $i ],
                'bic'            => $bics[ $i ]
            );
        }
    }

    update_option( 'woocommerce_ovo_accounts', $accounts );

}

private function bank_details( $order_id = '' ) {

    if ( empty( $this->account_details ) ) {
        return;
    }

    // Get order and store in $order
    $order          = wc_get_order( $order_id );

    // Get the order country and country $locale
    $country        = $order->get_billing_country();
    $locale         = $this->get_country_locale();

    // Get sortcode label in the $locale array and use appropriate one
    $sortcode = isset( $locale[ $country ]['sortcode']['label'] ) ? $locale[ $country ]['sortcode']['label'] : __( 'Sort Code', 'woocommerce' );

    echo '<strong><h2>' . __( 'Detail Rekening E-Money untuk Memproses Pembayaran:', 'woocommerce' ) . '</h2></strong>' . PHP_EOL;

    $bacs_accounts = apply_filters( 'woocommerce_bacs_accounts', $this->account_details );

    if ( ! empty( $bacs_accounts ) ) {
        foreach ( $bacs_accounts as $bacs_account ) {

            $bacs_account = (object) $bacs_account;

            if ( $bacs_account->account_name || $bacs_account->bank_name ) {
                echo '<h5>' . wp_unslash( implode( ' - ', array_filter( array( $bacs_account->account_name, $bacs_account->bank_name ) ) ) ) . '</h5>' . PHP_EOL;
            }

            echo '<ul class="order_details bacs_details">' . PHP_EOL;

            // BACS account fields shown on the thanks page and in emails
            $account_fields = apply_filters( 'woocommerce_bacs_account_fields', array(
                'account_number'=> array(
                    'label' => __( 'Nomor Rekening', 'woocommerce' ),
                    'value' => $bacs_account->account_number
                ),
                'sort_code'     => array(
                    'label' => $sortcode,
                    'value' => $bacs_account->sort_code
                ),
                'iban'          => array(
                    'label' => __( 'IBAN', 'woocommerce' ),
                    'value' => $bacs_account->iban
                ),
                'bic'           => array(
                    'label' => __( 'BIC', 'woocommerce' ),
                    'value' => $bacs_account->bic
                )
            ), $order_id );

            foreach ( $account_fields as $field_key => $field ) {
                    if ( ! empty( $field['value'] ) ) {
                        echo '<li class="' . esc_attr( $field_key ) . '">' . esc_attr( $field['label'] ) . ': <strong>' . wptexturize( $field['value'] ) . '</strong></li>' . PHP_EOL;
                    }
                }

            echo '</ul>';
        }
    }

}

// Instruction on Thank You page   
public function thankyou_page( $order_id ) {
    if ( $this->instructions ) {
        echo '<h3>Instruksi Pembayaran</h3>';
        echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
    }
    $this->bank_details( $order_id );
}
public function process_payment( $order_id ) {

    $order = wc_get_order( $order_id );

    // Mark as on-hold (we're awaiting the payment)
    $order->update_status( 'on-hold', __( 'Menunggu Pembayaran melalui OVO', 'woocommerce' ) );

    // Reduce stock levels
    $order->reduce_order_stock();

    // Remove cart
    WC()->cart->empty_cart();

    // Return thankyou redirect
    return array(
        'result'    => 'success',
        'redirect'  => $this->get_return_url( $order )
    );

}

// Email Instructions
public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

    if ( ! $sent_to_admin && 'ovo' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
        if ( $this->instructions ) {
            echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
        }
        $this->bank_details( $order->id );
    }

}


}


?>