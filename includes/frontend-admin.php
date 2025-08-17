<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Frontend administration helpers and shortcodes.
 *
 * This file adds a multi-step form allowing administrators to create new
 * accommodations from the frontend, similar to the Airbnb listing flow. The
 * entire process runs on a single page using a shortcode and step wizard.
 */

add_shortcode('rsv_new_accommodation', function(){
    if ( ! current_user_can('manage_options') ) {
        return '';
    }

    wp_enqueue_style('rsv-checkout', RSV_URL.'assets/css/checkout.css', [], RSV_VER);

    $step       = max(1, intval($_POST['step'] ?? 1));
    $title      = sanitize_text_field($_POST['title'] ?? '');
    $content    = wp_kses_post($_POST['content'] ?? '');
    $max_guests = intval($_POST['max_guests'] ?? 0);
    $checkin    = sanitize_text_field($_POST['checkin'] ?? '');
    $checkout   = sanitize_text_field($_POST['checkout'] ?? '');
    $amenities  = array_map('sanitize_text_field', (array) ($_POST['amenities'] ?? []));
    $gallery    = [];
    if (isset($_POST['gallery'])) {
        $gallery = array_filter(array_map('esc_url_raw', explode("\n", $_POST['gallery'])));
    }

    $success_html = '';
    $error_html   = '';

    if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if ( ! $title ) {
            $error_html = '<p class="error">'.esc_html__('Title is required','reeserva').'</p>';
            $step = 3; // show gallery step again
        } else {
            $post_id = wp_insert_post([
                'post_type'   => 'rsv_accomm',
                'post_status' => 'publish',
                'post_title'  => $title,
                'post_content'=> $content,
            ]);
            if ( ! is_wp_error($post_id) && $post_id ) {
                update_post_meta($post_id,'rsv_max_guests',$max_guests);
                update_post_meta($post_id,'rsv_checkin',$checkin);
                update_post_meta($post_id,'rsv_checkout',$checkout);
                update_post_meta($post_id,'rsv_amenities',$amenities);
                update_post_meta($post_id,'rsv_gallery',$gallery);
                $success_html = '<div class="confirm"><div class="badge">✔</div><h3>'.esc_html__('Accommodation created','reeserva').'</h3><p><a class="btn-secondary" href="'.esc_url(get_permalink($post_id)).'">'.esc_html__('View listing','reeserva').'</a></p></div>';
            } else {
                $error_html = '<p class="error">'.esc_html__('Could not create accommodation.','reeserva').'</p>';
                $step = 3;
            }
        }
    }

    ob_start();
    echo '<div class="ehb-wizard">';
    echo '<div class="steps">';
    for ($i = 1; $i <= 4; $i++) {
        echo '<div class="step '.($step >= $i ? 'active' : '').'">'.$i.'</div>';
        if ($i < 4) echo '<div class="line '.($step >= $i+1 ? 'active' : '').'"></div>';
    }
    echo '</div>';

    if ($success_html) {
        echo '<div class="card">'.$success_html.'</div></div>';
        return ob_get_clean();
    }

    echo '<div class="card">';
    echo $error_html;

    if ($step === 1) {
        echo '<h2>'.esc_html__('Basics','reeserva').'</h2>';
        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="step" value="2">';
        echo '<label>'.esc_html__('Title','reeserva').'<input type="text" name="title" value="'.esc_attr($title).'" required></label>';
        echo '<label style="grid-column:1/-1">'.esc_html__('Description','reeserva').'<textarea name="content" rows="4">'.esc_textarea($content).'</textarea></label>';
        echo '<button class="btn-primary" type="submit">'.esc_html__('Continue','reeserva').'</button>';
        echo '</form>';
    } elseif ($step === 2) {
        echo '<h2>'.esc_html__('Details','reeserva').'</h2>';
        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="step" value="3">';
        echo '<input type="hidden" name="title" value="'.esc_attr($title).'">';
        echo '<textarea style="display:none" name="content">'.esc_textarea($content).'</textarea>';
        echo '<label>'.esc_html__('Max guests','reeserva').'<input type="number" name="max_guests" value="'.esc_attr($max_guests).'" min="1"></label>';
        echo '<label>'.esc_html__('Check-in time','reeserva').'<input type="time" name="checkin" value="'.esc_attr($checkin).'"></label>';
        echo '<label>'.esc_html__('Check-out time','reeserva').'<input type="time" name="checkout" value="'.esc_attr($checkout).'"></label>';
        echo '<div style="grid-column:1/-1"><strong>'.esc_html__('Amenities','reeserva').'</strong><br>';
        foreach (rsv_default_amenities() as $k => $label) {
            echo '<label style="margin-right:12px"><input type="checkbox" name="amenities[]" value="'.esc_attr($k).'" '.(in_array($k,$amenities) ? 'checked' : '').'> '.esc_html($label).'</label>';
        }
        echo '</div>';
        echo '<button class="btn-primary" type="submit">'.esc_html__('Continue','reeserva').'</button>';
        echo '</form>';
    } else { // Step 3
        echo '<h2>'.esc_html__('Gallery','reeserva').'</h2>';
        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="step" value="4">';
        echo '<input type="hidden" name="title" value="'.esc_attr($title).'">';
        echo '<textarea style="display:none" name="content">'.esc_textarea($content).'</textarea>';
        echo '<input type="hidden" name="max_guests" value="'.esc_attr($max_guests).'">';
        echo '<input type="hidden" name="checkin" value="'.esc_attr($checkin).'">';
        echo '<input type="hidden" name="checkout" value="'.esc_attr($checkout).'">';
        foreach ($amenities as $a) {
            echo '<input type="hidden" name="amenities[]" value="'.esc_attr($a).'">';
        }
        echo '<label style="grid-column:1/-1">'.esc_html__('Image URLs (one per line)','reeserva').'<textarea name="gallery" rows="4">'.esc_textarea(implode("\n", $gallery)).'</textarea></label>';
        echo '<button class="btn-primary" type="submit">'.esc_html__('Create','reeserva').'</button>';
        echo '</form>';
    }

    echo '</div></div>';
=======
function rsv_frontend_admin_assets(){
    if ( ! current_user_can('manage_options') ) return;
    if ( function_exists('rsv_admin_enqueue') ) rsv_admin_enqueue();
    wp_enqueue_style('rsv-frontend-admin', RSV_URL.'assets/css/frontend-admin.css', ['rsv-admin'], RSV_VER);
    wp_enqueue_script('rsv-frontend-admin', RSV_URL.'assets/js/frontend-admin.js', ['rsv-admin-calendar'], RSV_VER, true);
}

add_shortcode('rsv_admin_dashboard', function(){
    if ( ! current_user_can('manage_options') ) return '';
    rsv_frontend_admin_assets();
    ob_start(); ?>
    <div class="rsv-admin-dashboard">
      <button class="rsv-admin-menu-toggle" aria-label="<?php esc_attr_e('Menu','reeserva');?>">☰</button>
      <aside class="rsv-admin-sidebar">
        <h2><?php esc_html_e('Admin','reeserva');?></h2>
        <nav>
          <a href="#" class="active" data-section="calendar"><?php esc_html_e('Calendar','reeserva');?></a>
          <a href="#" data-section="emails"><?php esc_html_e('Emails','reeserva');?></a>
          <a href="#" data-section="payments"><?php esc_html_e('Payments','reeserva');?></a>
        </nav>
      </aside>
      <div class="rsv-admin-main">
        <section id="rsv-section-calendar" class="rsv-admin-section"><?php rsv_render_calendar(); ?></section>
        <section id="rsv-section-emails" class="rsv-admin-section" style="display:none"><?php rsv_render_email_form(); ?></section>
        <section id="rsv-section-payments" class="rsv-admin-section" style="display:none"><?php rsv_render_payment_form(); ?></section>
      </div>
    </div>
    <?php
    return ob_get_clean();
});
