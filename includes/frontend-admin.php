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
    wp_enqueue_script('rsv-frontend-admin', RSV_URL.'assets/js/frontend-admin.js', [], RSV_VER, true);

    $step       = max(1, intval($_POST['step'] ?? 1));
    $title      = sanitize_text_field($_POST['title'] ?? '');
    $content    = wp_kses_post($_POST['content'] ?? '');
    $max_guests = intval($_POST['max_guests'] ?? 0);
    $checkin    = sanitize_text_field($_POST['checkin'] ?? '');
    $checkout   = sanitize_text_field($_POST['checkout'] ?? '');
    $amenities  = array_map('sanitize_text_field', (array) ($_POST['amenities'] ?? []));
    $rules      = wp_kses_post($_POST['rules'] ?? '');
    $room_count = intval($_POST['room_count'] ?? 0);
    $rooms      = [];
    if(isset($_POST['rooms'])){
        foreach((array)$_POST['rooms'] as $r){
            $beds = array_map('sanitize_text_field', (array)($r['beds'] ?? []));
            $rooms[]=['beds'=>$beds];
        }
    }

    $success_html = '';
    $error_html   = '';

    if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if ( ! $title ) {
            $error_html = '<p class="error">'.esc_html__('Title is required','reeserva').'</p>';
            $step = 3; // show gallery step again
        } else {
            $gallery = [];
            if ( ! empty( $_FILES['gallery_files']['name'][0] ) ) {
                require_once ABSPATH.'wp-admin/includes/file.php';
                require_once ABSPATH.'wp-admin/includes/image.php';
                require_once ABSPATH.'wp-admin/includes/media.php';
                $files = $_FILES['gallery_files'];
                foreach ( $files['name'] as $i => $name ) {
                    if ( $files['error'][$i] === UPLOAD_ERR_OK ) {
                        $file_array = [
                            'name'     => $name,
                            'type'     => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error'    => $files['error'][$i],
                            'size'     => $files['size'][$i],
                        ];
                        $attachment_id = media_handle_sideload( $file_array, 0 );
                        if ( ! is_wp_error( $attachment_id ) ) {
                            $url = wp_get_attachment_url( $attachment_id );
                            if ( $url ) {
                                $gallery[] = $url;
                            }
                        }
                    }
                }
            } elseif (isset($_POST['gallery'])) {
                $gallery = array_filter(array_map('esc_url_raw', explode("\n", $_POST['gallery'])));
            }
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
                update_post_meta($post_id,'rsv_house_rules',$rules);
                update_post_meta($post_id,'rsv_room_count',$room_count);
                update_post_meta($post_id,'rsv_rooms',$rooms);
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
        ?>
        <label style="grid-column:1/-1"><?php esc_html_e('House rules','reeserva');?><textarea name="rules" rows="4"><?php echo esc_textarea($rules);?></textarea></label>
        <label><?php esc_html_e('Number of rooms','reeserva');?> <input type="number" id="rsv-fe-room-count" name="room_count" value="<?php echo esc_attr($room_count);?>" min="0"></label>
        <div id="rsv-fe-rooms-wrapper" style="grid-column:1/-1">
        <?php for($i=0;$i<$room_count;$i++): $beds=(array)($rooms[$i]['beds'] ?? []); ?>
          <div class="fe-room" data-index="<?php echo $i;?>">
            <h4><?php printf(esc_html__('Room %d','reeserva'),$i+1);?></h4>
            <div class="fe-beds">
              <?php foreach($beds as $bed): ?>
              <div class="fe-bed-row"><select name="rooms[<?php echo $i;?>][beds][]">
                <?php foreach(rsv_default_bed_types() as $bk=>$bl): ?>
                  <option value="<?php echo esc_attr($bk);?>" <?php selected($bed,$bk);?>><?php echo esc_html($bl);?></option>
                <?php endforeach; ?>
              </select></div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn-secondary fe-add-bed"><?php esc_html_e('Add bed','reeserva');?></button>
          </div>
        <?php endfor; ?>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded',function(){
          var c=document.getElementById('rsv-fe-room-count');
          var w=document.getElementById('rsv-fe-rooms-wrapper');
          var o=`<?php foreach(rsv_default_bed_types() as $bk=>$bl){echo '<option value="'.esc_attr($bk).'">'.esc_html($bl).'</option>'; }?>`;
          function addBed(r){var i=r.dataset.index,b=r.querySelector('.fe-beds'),d=document.createElement('div');d.className='fe-bed-row';d.innerHTML='<select name="rooms['+i+'][beds][]">'+o+'</select>';b.appendChild(d);} 
          function bind(r){r.querySelector('.fe-add-bed').addEventListener('click',function(){addBed(r);});}
          function render(){var n=parseInt(c.value)||0,u=w.children.length;for(var i=u;i<n;i++){var r=document.createElement('div');r.className='fe-room';r.dataset.index=i;r.innerHTML='<h4><?php echo esc_js(esc_html__('Room','reeserva'));?> '+(i+1)+'</h4><div class="fe-beds"></div><button type="button" class="btn-secondary fe-add-bed"><?php echo esc_js(esc_html__('Add bed','reeserva'));?></button>';w.appendChild(r);bind(r);}while(w.children.length>n){w.removeChild(w.lastElementChild);} }
          w.querySelectorAll('.fe-room').forEach(bind);c.addEventListener('change',render);
        });
        </script>
        <?php
        echo '<button class="btn-primary" type="submit">'.esc_html__('Continue','reeserva').'</button>';
        echo '</form>';
    } else { // Step 3
        echo '<h2>'.esc_html__('Gallery','reeserva').'</h2>';
        echo '<form method="post" class="form-grid" enctype="multipart/form-data">';
        echo '<input type="hidden" name="step" value="4">';
        echo '<input type="hidden" name="title" value="'.esc_attr($title).'">';
        echo '<textarea style="display:none" name="content">'.esc_textarea($content).'</textarea>';
        echo '<input type="hidden" name="max_guests" value="'.esc_attr($max_guests).'">';
        echo '<input type="hidden" name="checkin" value="'.esc_attr($checkin).'">';
        echo '<input type="hidden" name="checkout" value="'.esc_attr($checkout).'">';
        foreach ($amenities as $a) {
            echo '<input type="hidden" name="amenities[]" value="'.esc_attr($a).'">';
        }
        echo '<input type="hidden" name="rules" value="'.esc_attr($rules).'">';
        echo '<input type="hidden" name="room_count" value="'.esc_attr($room_count).'">';
        for($i=0;$i<$room_count;$i++){
            foreach(($rooms[$i]['beds'] ?? []) as $bed){
                echo '<input type="hidden" name="rooms['.$i.'][beds][]" value="'.esc_attr($bed).'">';
            }
        }
        echo '<label style="grid-column:1/-1">'.esc_html__('Images','reeserva').'<input type="file" id="rsv-gallery-input" name="gallery_files[]" accept="image/*" multiple></label>';
        echo '<div id="rsv-gallery-preview" class="gallery-preview" style="grid-column:1/-1"></div>';
        echo '<button class="btn-primary" type="submit">'.esc_html__('Create','reeserva').'</button>';
        echo '</form>';
    }

    echo '</div></div>';
});
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
