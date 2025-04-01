<?php
class PFB_Form_Handler
{
    private $stripe;
    private $initialized = false; // Explicitly declare the property

    public function __construct()
    {
        try {
            $this->stripe = new PFB_Stripe();

            if ($this->stripe->is_ready()) {
                $this->initialized = true;
                add_action('wp_ajax_process_payment_form', array($this, 'process_form'));
                add_action('wp_ajax_nopriv_process_payment_form', array($this, 'process_form'));

                // Add webhook handler
                add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
            } else {
                add_action('admin_notices', array($this, 'display_stripe_errors'));
            }
        } catch (Exception $e) {
            error_log('Payment Form Builder Form Handler initialization error: ' . $e->getMessage());
        }
    }

    public function register_webhook_endpoint()
    {
        register_rest_route('payment-form-builder/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true'
        ));
    }

    public function handle_webhook($request)
    {
        if (!$this->initialized) {
            return new WP_Error('not_initialized', 'Payment system not properly configured', array('status' => 500));
        }

        $webhook_secret = get_option('pfb_webhook_secret');
        if (empty($webhook_secret)) {
            error_log('Webhook secret not configured');
            return new WP_Error('webhook_error', 'Webhook secret not configured', array('status' => 500));
        }

        try {
            $payload = file_get_contents('php://input');
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);

            // Handle the event
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $payment_intent = $event->data->object;
                    $this->record_transaction($payment_intent);
                    break;
                default:
                    error_log('Unhandled event type: ' . $event->type);
            }

            return new WP_REST_Response(array('status' => 'success'), 200);
        } catch (\UnexpectedValueException $e) {
            error_log('Invalid payload: ' . $e->getMessage());
            return new WP_Error('invalid_payload', $e->getMessage(), array('status' => 400));
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            error_log('Invalid signature: ' . $e->getMessage());
            return new WP_Error('invalid_signature', $e->getMessage(), array('status' => 400));
        } catch (Exception $e) {
            error_log('Webhook error: ' . $e->getMessage());
            return new WP_Error('webhook_error', $e->getMessage(), array('status' => 500));
        }
    }


    private function record_transaction($payment_intent)
    {
        global $wpdb;

        // Get form ID from metadata
        $form_id = isset($payment_intent->metadata->form_id) ? intval($payment_intent->metadata->form_id) : 0;
        if (!$form_id) {
            error_log('Form ID not found in payment intent metadata');
            return false;
        }

        // Determine mode
        $test_mode = get_option('pfb_test_mode', true);
        $mode = $test_mode ? 'test' : 'live';

        // Insert transaction record
        $result = $wpdb->insert(
            $wpdb->prefix . 'stripe_transactions',
            array(
                'form_id' => $form_id,
                'transaction_id' => $payment_intent->id,
                'amount' => $payment_intent->amount / 100, // Convert from cents
                'currency' => $payment_intent->currency,
                'status' => $payment_intent->status,
                'mode' => $mode,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%f', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            error_log('Failed to record transaction: ' . $wpdb->last_error);
            return false;
        }

        return true;
    }



    public function display_stripe_errors()
    {
        if ($this->stripe) {
            $errors = $this->stripe->get_errors();
            foreach ($errors as $error) {
                echo '<div class="error"><p>Payment Form Builder: ' . esc_html($error) . '</p></div>';
            }
        }
    }

    public function process_form()
    {
        if (!$this->initialized) {
            wp_send_json_error('Payment system not properly configured');
            return;
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'process_payment_form')) {
            error_log('Nonce verification failed');
            wp_send_json_error('Invalid security token');
            return;
        }

        // Verify form ID
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        if (!$form_id) {
            error_log('Invalid form ID');
            wp_send_json_error('Invalid form ID');
            return;
        }

        // Get form data
        $form_data = isset($_POST['form_data']) ? json_decode(stripslashes($_POST['form_data']), true) : array();
        if (empty($form_data)) {
            error_log('Empty form data');
            wp_send_json_error('Form data is required');
            return;
        }

        error_log('Form data received: ' . print_r($form_data, true));

        // Get payment details
        $amount = floatval(get_post_meta($form_id, '_payment_amount', true));
        $currency = get_post_meta($form_id, '_payment_currency', true) ?: 'usd';

        if (!$amount) {
            error_log('Invalid amount');
            wp_send_json_error('Invalid payment amount');
            return;
        }

        try {
            // Create payment intent
            $payment_intent = $this->stripe->create_payment_intent($amount, $currency, $form_id);

            if (is_wp_error($payment_intent)) {
        error_log('Stripe error: ' . $payment_intent->get_error_message());
        wp_send_json_error($payment_intent->get_error_message());
        return;
    }

            // Store form submission
            $submission_id = $this->store_submission($form_id, $form_data);

            wp_send_json_success(array(
                'client_secret' => $payment_intent->client_secret,
                'submission_id' => $submission_id
            ));
        } catch (Exception $e) {
            error_log('Payment processing error: ' . $e->getMessage());
            wp_send_json_error('Payment processing failed: ' . $e->getMessage());
        }
    }

    private function store_submission($form_id, $form_data)
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'pfb_submissions',
            array(
                'form_id' => $form_id,
                'submission_data' => json_encode($form_data),
                'payment_status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );

        return $wpdb->insert_id;
    }
}
