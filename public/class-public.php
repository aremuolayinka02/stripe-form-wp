<?php
class PFB_Public {
    public function __construct() {
        add_shortcode('payment_form', array($this, 'render_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function render_form($atts) {
        $atts = shortcode_atts(array(
            'id' => 0
        ), $atts);

        if (!$atts['id']) return '';

        $form_fields = get_post_meta($atts['id'], '_form_fields', true);
        $amount = get_post_meta($atts['id'], '_payment_amount', true);
        $currency = get_post_meta($atts['id'], '_payment_currency', true);

        ob_start();
        ?>
        <form id="payment-form-<?php echo $atts['id']; ?>" class="payment-form">
            <?php foreach ($form_fields as $field): ?>
                <div class="form-field">
                    <label>
                        <?php echo esc_html($field['label']); ?>
                        <?php if ($field['required']): ?>
                            <span class="required">*</span>
                        <?php endif; ?>
                    </label>
                    
                    <?php if ($field['type'] === 'textarea'): ?>
                        <textarea name="<?php echo esc_attr($field['label']); ?>"
                            <?php echo $field['required'] ? 'required' : ''; ?>></textarea>
                    <?php else: ?>
                        <input type="<?php echo esc_attr($field['type']); ?>"
                               name="<?php echo esc_attr($field['label']); ?>"
                               <?php echo $field['required'] ? 'required' : ''; ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="payment-section">
                <div id="card-element"></div>
                <div id="card-errors"></div>
            </div>

            <button type="submit">Pay <?php echo esc_html($amount . ' ' . strtoupper($currency)); ?></button>
        </form>
        <?php
        return ob_get_clean();
    }

   public function enqueue_scripts() {
    if (!is_singular()) return;

    global $post;
    if (!has_shortcode($post->post_content, 'payment_form')) return;

    wp_enqueue_style('pfb-public', 
        PFB_PLUGIN_URL . 'public/css/public.css', 
        array(), 
        PFB_VERSION
    );

    $test_mode = get_option('pfb_test_mode', true);
    $public_key = $test_mode 
        ? get_option('pfb_test_public_key')
        : get_option('pfb_live_public_key');

    wp_enqueue_script('stripe-js', 
        'https://js.stripe.com/v3/', 
        array(), 
        null
    );

    wp_enqueue_script('pfb-public', 
        PFB_PLUGIN_URL . 'public/js/public.js', 
        array('jquery', 'stripe-js'), 
        PFB_VERSION, 
        true
    );

    wp_localize_script('pfb-public', 'pfbData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'publicKey' => $public_key,
        'nonce' => wp_create_nonce('process_payment_form')
    ));
}
}