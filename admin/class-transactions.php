<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Stripe_Form_Transactions {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_transactions_page' ) );
        add_action( 'wp_ajax_filter_transactions', array( $this, 'filter_transactions' ) );
    }

    public function add_transactions_page() {
        add_menu_page(
            'Transactions',
            'Transactions',
            'manage_options',
            'stripe-transactions',
            array( $this, 'render_transactions_page' ),
            'dashicons-list-view',
            25
        );
    }

    public function render_transactions_page() {
        ?>
        <div class="wrap">
            <h1>Transactions</h1>
            <form id="transaction-filters">
                <select name="mode" id="mode">
                    <option value="live" selected>Live</option>
                    <option value="test">Test</option>
                </select>
                <select name="form_id" id="form_id">
                    <option value="">All Forms</option>
                    <?php
                    global $wpdb;
                    $forms = $wpdb->get_results( "SELECT DISTINCT form_id FROM {$wpdb->prefix}stripe_transactions" );
                    foreach ( $forms as $form ) {
                        echo '<option value="' . esc_attr( $form->form_id ) . '">Form ' . esc_html( $form->form_id ) . '</option>';
                    }
                    ?>
                </select>
                <input type="date" name="start_date" id="start_date">
                <input type="date" name="end_date" id="end_date">
                <button type="button" id="filter-button" class="button">Filter</button>
            </form>
            <div id="transactions-table">
                <?php $this->load_transactions_table(); ?>
            </div>
        </div>
        <?php
    }

    public function load_transactions_table( $mode = 'live', $form_id = '', $start_date = '', $end_date = '' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stripe_transactions';

        $query = "SELECT * FROM $table_name WHERE mode = %s";
        $params = array( $mode );

        if ( ! empty( $form_id ) ) {
            $query .= " AND form_id = %d";
            $params[] = $form_id;
        }

        if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
            $query .= " AND created_at BETWEEN %s AND %s";
            $params[] = $start_date;
            $params[] = $end_date;
        }

        $transactions = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Form ID</th><th>Transaction ID</th><th>Amount</th><th>Currency</th><th>Status</th><th>Mode</th><th>Created At</th></tr></thead>';
        echo '<tbody>';
        foreach ( $transactions as $transaction ) {
            echo '<tr>';
            echo '<td>' . esc_html( $transaction->id ) . '</td>';
            echo '<td>' . esc_html( $transaction->form_id ) . '</td>';
            echo '<td>' . esc_html( $transaction->transaction_id ) . '</td>';
            echo '<td>' . esc_html( $transaction->amount ) . '</td>';
            echo '<td>' . esc_html( $transaction->currency ) . '</td>';
            echo '<td>' . esc_html( $transaction->status ) . '</td>';
            echo '<td>' . esc_html( $transaction->mode ) . '</td>';
            echo '<td>' . esc_html( $transaction->created_at ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    public function filter_transactions() {
        check_ajax_referer( 'filter_transactions_nonce', 'nonce' );

        $mode = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : 'live';
        $form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : '';
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
        $end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';

        ob_start();
        $this->load_transactions_table( $mode, $form_id, $start_date, $end_date );
        $html = ob_get_clean();

        wp_send_json_success( $html );
    }
}

new Stripe_Form_Transactions();