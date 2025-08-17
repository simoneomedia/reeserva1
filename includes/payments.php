
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_ajax_nopriv_rsv_stripe_checkout','rsv_stripe_checkout');
add_action('wp_ajax_rsv_stripe_checkout','rsv_stripe_checkout');
function rsv_stripe_checkout(){
    $p = rsv_get_payment_settings();
    if (empty($p['stripe_enabled'])) wp_send_json_error(['message'=>'Stripe disabled']);
    $accomm_id = intval($_POST['accomm'] ?? 0);
    $ci = sanitize_text_field($_POST['ci'] ?? '');
    $co = sanitize_text_field($_POST['co'] ?? '');
    $first = sanitize_text_field($_POST['first_name'] ?? '');
    $last  = sanitize_text_field($_POST['last_name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    if(!$accomm_id||!$ci||!$co||!$first||!$last||!$email||!$phone) wp_send_json_error(['message'=>'Missing fields']);

    $total = rsv_quote_total($accomm_id,$ci,$co);
    $amount = max(0, round($total*100)); // cents
    if ($amount < 50) $amount = 50;

    $body = [
        'amount' => $amount,
        'currency' => $p['currency'],
        'payment_method_types[]' => 'card',
        'metadata[accomm_id]' => $accomm_id,
        'metadata[ci]' => $ci,
        'metadata[co]' => $co,
        'metadata[guest_first]' => $first,
        'metadata[guest_last]' => $last,
        'metadata[guest_email]' => $email,
        'metadata[guest_phone]' => $phone,
    ];
    $headers = [
        'Authorization' => 'Bearer '.$p['stripe_sk'],
        'Content-Type'  => 'application/x-www-form-urlencoded'
    ];
    $resp = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
        'headers'=>$headers,'body'=>http_build_query($body),'timeout'=>20
    ]);
    if (is_wp_error($resp)) wp_send_json_error(['message'=>$resp->get_error_message()]);
    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code>=200 && $code<300 && !empty($json['client_secret']) && !empty($json['id'])){
        $return = add_query_arg([
            'rsv_stripe'=>'return','pi'=>$json['id'],
            'accomm'=>$accomm_id,'ci'=>$ci,'co'=>$co,
            'first_name'=>rawurlencode($first),'last_name'=>rawurlencode($last),
            'email'=>rawurlencode($email),'phone'=>rawurlencode($phone),'notes'=>rawurlencode($notes)
        ], rsv_checkout_url());
        wp_send_json_success(['client_secret'=>$json['client_secret'],'return_url'=>$return]);
    }
    wp_send_json_error(['message'=> 'Stripe error', 'details'=>$json]);
}

add_action('wp_ajax_nopriv_rsv_paypal_checkout','rsv_paypal_checkout');
add_action('wp_ajax_rsv_paypal_checkout','rsv_paypal_checkout');
function rsv_paypal_checkout(){
    $p = rsv_get_payment_settings();
    if (empty($p['paypal_enabled']) || empty($p['paypal_email'])) wp_send_json_error(['message'=>'PayPal disabled']);
    $accomm_id = intval($_POST['accomm'] ?? 0);
    $ci = sanitize_text_field($_POST['ci'] ?? '');
    $co = sanitize_text_field($_POST['co'] ?? '');
    $first = sanitize_text_field($_POST['first_name'] ?? '');
    $last  = sanitize_text_field($_POST['last_name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    if(!$accomm_id||!$ci||!$co||!$first||!$last||!$email||!$phone) wp_send_json_error(['message'=>'Missing fields']);

    $total = rsv_quote_total($accomm_id,$ci,$co);
    $base = !empty($p['test_mode']) ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';
    $return = add_query_arg([
        'rsv_paypal'=>'return','accomm'=>$accomm_id,'ci'=>$ci,'co'=>$co,
        'first_name'=>rawurlencode($first),'last_name'=>rawurlencode($last),
        'email'=>rawurlencode($email),'phone'=>rawurlencode($phone),'notes'=>rawurlencode($notes)
    ], rsv_checkout_url());
    $cancel = add_query_arg(['step'=>2,'accomm'=>$accomm_id,'ci'=>$ci,'co'=>$co], rsv_checkout_url());
    $query = [
        'cmd' => '_xclick',
        'business' => $p['paypal_email'],
        'item_name' => sprintf('%s (%s → %s)', get_the_title($accomm_id), $ci, $co),
        'amount' => round($total,2),
        'currency_code' => strtoupper($p['currency']),
        'return' => $return,
        'cancel_return' => $cancel,
    ];
    $url = $base.'?'.http_build_query($query);
    wp_send_json_success(['url'=>$url]);
}

function rsv_stripe_retrieve_intent($intent_id){
    $p = rsv_get_payment_settings();
    if (empty($p['stripe_sk'])) return null;
    $resp = wp_remote_get('https://api.stripe.com/v1/payment_intents/'.urlencode($intent_id), [
        'headers'=>['Authorization'=>'Bearer '.$p['stripe_sk']],
        'timeout'=>15
    ]);
    if (is_wp_error($resp)) return null;
    $code = wp_remote_retrieve_response_code($resp);
    if ($code<200 || $code>=300) return null;
    return json_decode(wp_remote_retrieve_body($resp), true);
}
