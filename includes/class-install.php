<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Stripe_Form_Install
{
    public static function install()
    {
        self::create_submissions_table();
        self::create_transactions_table();
    }


    /*
    1. Created when a form is submitted
    2. Updated when payment status changes
    3. Contains form field data
    */

    private static function create_submissions_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pfb_submissions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            submission_data longtext NOT NULL, /* Stores the actual form submission data */
            payment_status varchar(50) NOT NULL, /* pending, completed, failed */
            payment_intent varchar(255), /* Stripe payment intent ID */
            amount decimal(10,2),
            currency varchar(3),
            created_at datetime NOT NULL,
            updated_at datetime,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY payment_status (payment_status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }




    /*
    1. Created after successful Stripe payment
    2. Contains payment-specific details
    3. Used for transaction reporting
    4. Links back to the submission via submission_id
    */

    private static function create_transactions_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'stripe_transactions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            submission_id bigint(20) NOT NULL, /* Reference to pfb_submissions table */
            transaction_id varchar(255) NOT NULL, /* Stripe transaction/charge ID */
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL,
            status varchar(50) NOT NULL, /* succeeded, failed, pending */
            mode varchar(10) NOT NULL, /* live or test */
            customer_email varchar(255),
            customer_name varchar(255),
            payment_method varchar(50),
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            metadata longtext,
            PRIMARY KEY (id),
            KEY transaction_id (transaction_id),
            KEY submission_id (submission_id),
            KEY mode (mode)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
