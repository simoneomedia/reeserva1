
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function rsv_booking_confirmation_html($bid){
    $accomm_id = (int) get_post_meta($bid,'rsv_booking_accomm',true);
    $ci = get_post_meta($bid,'rsv_check_in',true);
    $co = get_post_meta($bid,'rsv_check_out',true);
    $first = get_post_meta($bid,'rsv_guest_name',true);
    $last  = get_post_meta($bid,'rsv_guest_surname',true);
    $email = get_post_meta($bid,'rsv_guest_email',true);
    $phone = get_post_meta($bid,'rsv_guest_phone',true);
    $settings = rsv_get_email_settings();
    $text = $settings['confirmation_text'] ?? '';
    ob_start();
    echo '<div class="confirm"><div class="badge">✔</div><h3>'.esc_html__('Booking confirmed','reeserva').'</h3>';
    if($text) echo '<p>'.wp_kses_post($text).'</p>';
    if($accomm_id){
        $thumb = get_the_post_thumbnail($accomm_id,'medium',['style'=>'width:100%;max-width:300px;height:auto;border-radius:4px;margin-bottom:10px']);
        if($thumb) echo $thumb;
        echo '<p><strong>'.esc_html__('Accommodation','reeserva').':</strong> '.esc_html(get_the_title($accomm_id)).'</p>';
    }
    if($ci && $co) echo '<p><strong>'.esc_html__('Dates','reeserva').':</strong> '.esc_html($ci).' → '.esc_html($co).'</p>';
    $full = trim($first.' '.$last);
    if($full) echo '<p><strong>'.esc_html__('Guest','reeserva').':</strong> '.esc_html($full).'</p>';
    if($email) echo '<p><strong>'.esc_html__('Email','reeserva').':</strong> '.esc_html($email).'</p>';
    if($phone) echo '<p><strong>'.esc_html__('Phone','reeserva').':</strong> '.esc_html($phone).'</p>';
    echo '<p><strong>'.esc_html__('Reference','reeserva').':</strong> '.intval($bid).'</p>';
    if($accomm_id) echo '<a class="btn-secondary" href="'.esc_url(get_permalink($accomm_id)).'">'.esc_html__('Back to listing','reeserva').'</a>';
    echo '</div>';
    return ob_get_clean();
}

add_shortcode('rsv_search', function(){
    wp_enqueue_style('rsv-search', RSV_URL.'assets/css/search.css', [], RSV_VER);
    wp_enqueue_script('rsv-search', RSV_URL.'assets/js/search.js', [], RSV_VER, true);
    $ci = sanitize_text_field($_GET['ci'] ?? '');
    $co = sanitize_text_field($_GET['co'] ?? '');
    $guests = intval($_GET['guests'] ?? 0);
    ob_start(); ?>
    <form class="ehb-search-form" method="get">
      <label><?php esc_html_e('Guests','reeserva'); ?> <input type="number" name="guests" value="<?php echo esc_attr($guests); ?>" min="1" required></label>
      <label><?php esc_html_e('Check-in','reeserva'); ?> <input type="date" name="ci" value="<?php echo esc_attr($ci); ?>" required></label>
      <label><?php esc_html_e('Check-out','reeserva'); ?> <input type="date" name="co" value="<?php echo esc_attr($co); ?>" required></label>
      <button type="submit"><?php esc_html_e('Search','reeserva'); ?></button>
    </form>
    <?php
    if($ci && $co && $guests){
        $types = get_posts(['post_type'=>'rsv_accomm','post_status'=>'publish','numberposts'=>-1]);
        echo '<div class="rsv-results" style="display:grid;gap:20px">';
        $found=false;
        foreach($types as $t){
            $max = (int) get_post_meta($t->ID,'rsv_max_guests',true);
            if($max < $guests) continue;
            if(!rsv_is_accomm_available($t->ID,$ci,$co)) continue;
            $found=true;
            $url = add_query_arg(['step'=>2,'accomm'=>$t->ID,'ci'=>$ci,'co'=>$co], rsv_checkout_url());
            echo '<div class="rsv-result" style="border:1px solid #ddd;padding:10px;border-radius:6px">';
            $thumb = get_the_post_thumbnail($t->ID,'medium',['style'=>'width:100%;height:auto;border-radius:4px']);
            if($thumb) echo $thumb;
            echo '<h3>'.esc_html(get_the_title($t)).'</h3>';
            echo '<p><a class="btn-primary" href="'.esc_url($url).'">'.esc_html__('Book','reeserva').'</a></p>';
            echo '</div>';
        }
        if(!$found) echo '<p>'.esc_html__('No accommodations found','reeserva').'</p>';
        echo '</div>';
    }
    return ob_get_clean();
});

add_shortcode('rsv_checkout', function(){
    wp_enqueue_style('rsv-checkout', RSV_URL.'assets/css/checkout.css', [], RSV_VER);
    $step = max(1, intval($_GET['step'] ?? ($_POST['step'] ?? 1)));
    $accomm_id = intval($_GET['accomm'] ?? ($_POST['accomm'] ?? 0));
    $ci = sanitize_text_field($_GET['ci'] ?? ($_POST['ci'] ?? ''));
    $co = sanitize_text_field($_GET['co'] ?? ($_POST['co'] ?? ''));

    // Handle Stripe return
    if (isset($_GET['rsv_stripe']) && $_GET['rsv_stripe']==='return' && !empty($_GET['pi'])){
        $intent = rsv_stripe_retrieve_intent(sanitize_text_field($_GET['pi']));
        echo '<div class="ehb-wizard"><div class="steps"><div class="step active">1</div><div class="line active"></div><div class="step active">2</div><div class="line active"></div><div class="step active">3</div></div>';
        echo '<div class="card">';
        if($intent && ($intent['status'] ?? '') === 'succeeded'){
            $accomm_id = intval($_GET['accomm'] ?? 0);
            $ci = sanitize_text_field($_GET['ci'] ?? '');
            $co = sanitize_text_field($_GET['co'] ?? '');
            $first = sanitize_text_field($_GET['first_name'] ?? '');
            $last  = sanitize_text_field($_GET['last_name'] ?? '');
            $email = sanitize_email($_GET['email'] ?? '');
            $phone = sanitize_text_field($_GET['phone'] ?? '');
            $notes = sanitize_text_field($_GET['notes'] ?? '');
            $full = trim($first.' '.$last);
            // Create booking if not exists with same intent id
            $exists = get_posts(['post_type'=>'rsv_booking','post_status'=>['confirmed','publish'],'numberposts'=>1,
                'meta_query'=>[['key'=>'rsv_stripe_intent','value'=>sanitize_text_field($_GET['pi']),'compare'=>'=']]]);
            if(!$exists){
                $bid = wp_insert_post(['post_type'=>'rsv_booking','post_status'=>'confirmed','post_title'=>sprintf(__('Booking: %s %s','reeserva'), $first,$last),'post_content'=>$notes]);
                if(!is_wp_error($bid) && $bid){
                    update_post_meta($bid,'rsv_booking_accomm',$accomm_id);
                    update_post_meta($bid,'rsv_check_in',$ci);
                    update_post_meta($bid,'rsv_check_out',$co);
                    update_post_meta($bid,'rsv_guest_name',$first);
                    update_post_meta($bid,'rsv_guest_surname',$last);
                    update_post_meta($bid,'rsv_guest_email',$email);
                    update_post_meta($bid,'rsv_guest_phone',$phone);
                    update_post_meta($bid,'rsv_payment_status','paid');
                    update_post_meta($bid,'rsv_payment_method','stripe');
                    update_post_meta($bid,'rsv_stripe_intent',sanitize_text_field($_GET['pi']));
                    do_action('rsv_booking_confirmed', $bid, ['accomm'=>$accomm_id,'ci'=>$ci,'co'=>$co,'first_name'=>$first,'last_name'=>$last,'email'=>$email,'phone'=>$phone]);
                    echo rsv_booking_confirmation_html($bid);
                } else {
                    echo '<p class="error">'.esc_html__('Could not create booking, but payment succeeded. Please contact support.','reeserva').'</p>';
                }
            } else {
                $b=$exists[0];
                echo rsv_booking_confirmation_html($b->ID);
            }
        } else {
            echo '<p class="error">'.esc_html__('Payment not verified. If you were charged, contact support.','reeserva').'</p>';
        }
        echo '</div></div>';
        return '';
    }

    // Handle PayPal return
    if (isset($_GET['rsv_paypal']) && $_GET['rsv_paypal']==='return'){
        echo '<div class="ehb-wizard"><div class="steps"><div class="step active">1</div><div class="line active"></div><div class="step active">2</div><div class="line active"></div><div class="step active">3</div></div>';
        echo '<div class="card">';
        if (isset($_GET['st']) && strtolower(sanitize_text_field($_GET['st'])) === 'completed'){
            $accomm_id = intval($_GET['accomm'] ?? 0);
            $ci = sanitize_text_field($_GET['ci'] ?? '');
            $co = sanitize_text_field($_GET['co'] ?? '');
            $first = sanitize_text_field($_GET['first_name'] ?? '');
            $last  = sanitize_text_field($_GET['last_name'] ?? '');
            $email = sanitize_email($_GET['email'] ?? '');
            $phone = sanitize_text_field($_GET['phone'] ?? '');
            $notes = sanitize_text_field($_GET['notes'] ?? '');
            if($accomm_id && $ci && $co && $first && $last && $email && $phone){
                $bid = wp_insert_post(['post_type'=>'rsv_booking','post_status'=>'confirmed','post_title'=>sprintf(__('Booking: %s %s','reeserva'), $first,$last),'post_content'=>$notes]);
                if(!is_wp_error($bid) && $bid){
                    update_post_meta($bid,'rsv_booking_accomm',$accomm_id);
                    update_post_meta($bid,'rsv_check_in',$ci);
                    update_post_meta($bid,'rsv_check_out',$co);
                    update_post_meta($bid,'rsv_guest_name',$first);
                    update_post_meta($bid,'rsv_guest_surname',$last);
                    update_post_meta($bid,'rsv_guest_email',$email);
                    update_post_meta($bid,'rsv_guest_phone',$phone);
                    update_post_meta($bid,'rsv_payment_status','paid');
                    update_post_meta($bid,'rsv_payment_method','paypal');
                    if(!empty($_GET['tx'])) update_post_meta($bid,'rsv_paypal_txn', sanitize_text_field($_GET['tx']));
                    do_action('rsv_booking_confirmed',$bid,['accomm'=>$accomm_id,'ci'=>$ci,'co'=>$co,'first_name'=>$first,'last_name'=>$last,'email'=>$email,'phone'=>$phone]);
                    echo rsv_booking_confirmation_html($bid);
                } else {
                    echo '<p class="error">'.esc_html__('Could not create booking.','reeserva').'</p>';
                }
            } else {
                echo '<p class="error">'.esc_html__('Missing data.','reeserva').'</p>';
            }
        } else {
            echo '<p class="error">'.esc_html__('Payment not verified. If you were charged, contact support.','reeserva').'</p>';
        }
        echo '</div></div>';
        return '';
    }

    $success_html = '';
    $error_html = '';
    if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST'){
        $first = sanitize_text_field($_POST['first_name'] ?? '');
        $last  = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $method = sanitize_text_field($_POST['method'] ?? 'arrival');
        if(!$first || !$last || !$email || !$phone){
            $error_html = '<p class="error">'.esc_html__('Please fill all required fields.','reeserva').'</p>';
        } elseif(!rsv_is_accomm_available($accomm_id,$ci,$co)){
            $error_html = '<p class="error">'.esc_html__('Sorry, these dates are no longer available.','reeserva').'</p>';
        } else {
            $total = rsv_quote_total($accomm_id,$ci,$co);
            $bid = wp_insert_post(['post_type'=>'rsv_booking','post_status'=>'confirmed','post_title'=>sprintf(__('Booking: %s %s','reeserva'), $first,$last),'post_content'=>$notes]);
            if(!is_wp_error($bid) && $bid){
                update_post_meta($bid,'rsv_booking_accomm',$accomm_id);
                update_post_meta($bid,'rsv_check_in',$ci);
                update_post_meta($bid,'rsv_check_out',$co);
                update_post_meta($bid,'rsv_guest_name',$first);
                update_post_meta($bid,'rsv_guest_surname',$last);
                update_post_meta($bid,'rsv_guest_email',$email);
                update_post_meta($bid,'rsv_guest_phone',$phone);
                update_post_meta($bid,'rsv_payment_status',$method==='arrival'?'pending':'confirmed');
                update_post_meta($bid,'rsv_payment_method',$method);
                do_action('rsv_booking_confirmed',$bid,['accomm'=>$accomm_id,'ci'=>$ci,'co'=>$co,'first_name'=>$first,'last_name'=>$last,'email'=>$email,'phone'=>$phone,'total'=>$total]);
                $success_html = rsv_booking_confirmation_html($bid);
            } else {
                $error_html = '<p class="error">'.esc_html__('Could not create booking. Please try again.','reeserva').'</p>';
            }
        }
        $step = 3;
    }

    ob_start();
    echo '<div class="ehb-wizard">';
    echo '<div class="steps"><div class="step '.($step>=1?'active':'').'">1</div><div class="line '.($step>=2?'active':'').'"></div><div class="step '.($step>=2?'active':'').'">2</div><div class="line '.($step>=3?'active':'').'"></div><div class="step '.($step>=3?'active':'').'">3</div></div>';

    if ($step === 1) {
        echo '<div class="card"><h2>'.esc_html__('Your stay','reeserva').'</h2>';
        $types = get_posts(['post_type'=>'rsv_accomm','post_status'=>'publish','numberposts'=>-1]);
        echo '<form method="get" class="form-grid">';
        echo '<input type="hidden" name="step" value="2">';
        echo '<label>'.esc_html__('Accommodation','reeserva').'<select name="accomm" required>';
        foreach($types as $t){ printf('<option value="%d" %s>%s</option>', $t->ID, selected($accomm_id,$t->ID,false), esc_html(get_the_title($t))); }
        echo '</select></label>';
        echo '<label>'.esc_html__('Check-in','reeserva').'<input type="date" name="ci" value="'.esc_attr($ci).'" required></label>';
        echo '<label>'.esc_html__('Check-out','reeserva').'<input type="date" name="co" value="'.esc_attr($co).'" required></label>';
        echo '<button class="btn-primary" type="submit">'.esc_html__('Continue','reeserva').'</button></form></div></div>';
        return ob_get_clean();
    }

    if (!$accomm_id || !$ci || !$co) { echo '<div class="card"><p>'.esc_html__('Missing data. Please start again.','reeserva').'</p></div></div>'; return ob_get_clean(); }

    if ($step === 2) {
        echo '<div class="card"><h2>'.esc_html__('Guest details','reeserva').'</h2>';
        echo '<div class="summary">'.esc_html(get_the_title($accomm_id)).' • '.esc_html($ci).' → '.esc_html($co).'</div>';
        if (!rsv_is_accomm_available($accomm_id,$ci,$co)) { echo '<p class="error">'.esc_html__('Sorry, these dates are no longer available.','reeserva').'</p></div></div>'; return ob_get_clean(); }
        echo '<form id="rsv-booking-form" method="post" class="form-grid">';
        echo '<input type="hidden" name="accomm" value="'.esc_attr($accomm_id).'"><input type="hidden" name="ci" value="'.esc_attr($ci).'"><input type="hidden" name="co" value="'.esc_attr($co).'"><input type="hidden" name="step" value="2">';
        echo '<label>'.esc_html__('First name','reeserva').'<input type="text" name="first_name" required></label>';
        echo '<label>'.esc_html__('Surname','reeserva').'<input type="text" name="last_name" required></label>';
        echo '<label>'.esc_html__('Email','reeserva').'<input type="email" name="email" required></label>';
        echo '<label>'.esc_html__('Phone','reeserva').'<input type="text" name="phone" required></label>';
        echo '<label>'.esc_html__('Notes (optional)','reeserva').'<textarea name="notes" rows="3"></textarea></label>';
        $p = rsv_get_payment_settings();
        if ($p['stripe_enabled']){
            echo '<div id="rsv-card-element" style="margin-bottom:15px"></div>';
            echo '<button type="button" id="rsv-pay" class="btn-primary">'.esc_html__('Pay with card','reeserva').'</button>';
            echo '<script src="https://js.stripe.com/v3/"></script>';
            echo '<script>(function(){var stripe=Stripe("'.esc_js($p['stripe_pk']).'");var elements=stripe.elements();var card=elements.create("card",{hidePostalCode:true});card.mount("#rsv-card-element");document.getElementById("rsv-pay").addEventListener("click",function(){var f=document.getElementById("rsv-booking-form");var fd=new FormData(f);fd.append("action","rsv_stripe_checkout");fetch("'.esc_js(admin_url('admin-ajax.php')).'",{method:"POST",body:fd,credentials:"same-origin"}).then(r=>r.json()).then(function(res){if(res&&res.success&&res.data&&res.data.client_secret&&res.data.return_url){stripe.confirmCardPayment(res.data.client_secret,{payment_method:{card:card,billing_details:{name:f.first_name.value+" "+f.last_name.value,email:f.email.value}}}).then(function(result){if(result.error){alert(result.error.message);}else{window.location=res.data.return_url;}});}else{alert("Stripe error: "+(res&&(res.data&&res.data.message||res.message)||"unknown"));}}).catch(function(){alert("Network error");});});})();</script>';
        }
        if ($p['paypal_enabled']){
            echo '<button type="button" id="rsv-paypal" class="btn-primary">'.esc_html__('Pay with PayPal','reeserva').'</button>';
            echo '<script>document.getElementById("rsv-paypal").addEventListener("click", function(){var f=document.getElementById("rsv-booking-form");var fd=new FormData(f);fd.append("action","rsv_paypal_checkout");fetch("'.esc_js(admin_url('admin-ajax.php')).'",{method:"POST",body:fd,credentials:"same-origin"}).then(r=>r.json()).then(function(res){if(res&&res.success&&res.data&&res.data.url){window.location=res.data.url;}else{alert("PayPal error: "+(res&&(res.data&&res.data.message||res.message)||"unknown"));}}).catch(function(){alert("Network error");});});</script>';
        }
        if ($p['arrival_enabled'] || (!$p['stripe_enabled'] && !$p['paypal_enabled'])){
            echo '<input type="hidden" name="method" value="arrival">';
            echo '<button class="btn-primary" type="submit">'.esc_html__('Confirm booking','reeserva').'</button>';
        }
        echo '</form>';
        echo '</div></div>';
        return ob_get_clean();
    }

    if ($step === 3) {
        echo '<div class="card">';
        if($success_html) echo $success_html; else echo $error_html;
        echo '</div></div>'; return ob_get_clean();
    }
    echo '</div>'; return ob_get_clean();
});
