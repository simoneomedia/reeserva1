
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function RSV_register_cpts(){
    register_post_type('rsv_accomm',[
        'label'=>__('Accommodation','reeserva'),
        'labels'=>['name'=>__('Accommodations','reeserva'),'singular_name'=>__('Accommodation','reeserva')],
        'public'=>true,'has_archive'=>true,'menu_icon'=>'dashicons-building','supports'=>['title','editor','thumbnail'],
    ]);
    register_post_type('rsv_booking',[
        'label'=>__('Booking','reeserva'),
        'labels'=>['name'=>__('Bookings','reeserva'),'singular_name'=>__('Booking','reeserva')],
        'public'=>false,'show_ui'=>true,'menu_icon'=>'dashicons-tickets','supports'=>['title','editor'],
    ]);
    register_post_type('rsv_season',[
        'label'=>__('Season','reeserva'),
        'public'=>false,'show_ui'=>false,'supports'=>['title'],
    ]);
    register_post_type('rsv_rate',[
        'label'=>__('Rate','reeserva'),
        'public'=>false,'show_ui'=>false,'supports'=>['title'],
    ]);
    register_post_status('confirmed',[
        'label'                     => _x('Confirmed','post status','reeserva'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Confirmed <span class="count">(%s)</span>',
                                             'Confirmed <span class="count">(%s)</span>','reeserva'),
    ]);
}
add_action('init','RSV_register_cpts');

// Meta box for Accommodation
add_action('add_meta_boxes', function(){
    add_meta_box('rsv_accomm_meta', __('Accommodation details','reeserva'),'rsv_render_accomm_meta','rsv_accomm','normal','high');
});
function rsv_render_accomm_meta($post){
    $gallery = (array) get_post_meta($post->ID,'rsv_gallery',true) ?: [];
    $max_guests = (int) get_post_meta($post->ID,'rsv_max_guests',true);
    $amenities = (array) get_post_meta($post->ID,'rsv_amenities',true) ?: [];
    $checkin = esc_attr( get_post_meta($post->ID,'rsv_checkin',true) );
    $checkout= esc_attr( get_post_meta($post->ID,'rsv_checkout',true) );
    $rules = wp_kses_post( get_post_meta($post->ID,'rsv_house_rules',true) );
    $rooms = (array) get_post_meta($post->ID,'rsv_rooms',true) ?: [];
    $room_count = (int) get_post_meta($post->ID,'rsv_room_count',true);
    if($room_count < count($rooms)) $room_count = count($rooms);
    ?>
    <style>.ehb-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.ehb-card{background:#fff;border:1px solid #e3e3e3;border-radius:10px;padding:16px}</style>
    <div class="ehb-grid">
      <div class="ehb-card">
        <h3><?php esc_html_e('Basics','reeserva');?></h3>
        <p><label><?php esc_html_e('Max guests','reeserva');?> <input type="number" name="rsv_max_guests" value="<?php echo esc_attr($max_guests);?>" min="1"></label></p>
        <p><label><?php esc_html_e('Check-in time','reeserva');?> <input type="time" name="rsv_checkin" value="<?php echo $checkin;?>"></label></p>
        <p><label><?php esc_html_e('Check-out time','reeserva');?> <input type="time" name="rsv_checkout" value="<?php echo $checkout;?>"></label></p>
      </div>
      <div class="ehb-card">
        <h3><?php esc_html_e('Amenities','reeserva');?></h3>
        <?php foreach(rsv_default_amenities() as $k=>$label): ?>
          <label style="display:inline-block;margin-right:12px"><input type="checkbox" name="rsv_amenities[]" value="<?php echo esc_attr($k);?>" <?php checked(in_array($k,$amenities));?>> <?php echo esc_html($label);?></label>
        <?php endforeach; ?>
      </div>
      <div class="ehb-card">
        <h3><?php esc_html_e('Gallery','reeserva');?></h3>
        <p class="description"><?php esc_html_e('Use the Featured Image for the main photo. Paste additional image URLs (one per line) for the gallery.','reeserva');?></p>
        <textarea name="rsv_gallery" rows="4" style="width:100%"><?php echo esc_textarea(implode("\n",$gallery));?></textarea>
      </div>
      <div class="ehb-card">
        <h3><?php esc_html_e('House rules','reeserva');?></h3>
        <textarea name="rsv_house_rules" rows="4" style="width:100%"><?php echo esc_textarea($rules);?></textarea>
      </div>
      <div class="ehb-card">
        <h3><?php esc_html_e('Rooms & Beds','reeserva');?></h3>
        <p><label><?php esc_html_e('Number of rooms','reeserva');?> <input type="number" name="rsv_room_count" id="rsv-room-count" value="<?php echo esc_attr($room_count);?>" min="0"></label></p>
        <div id="rsv-rooms-wrapper">
          <?php for($i=0;$i<$room_count;$i++): $beds=(array)($rooms[$i]['beds'] ?? []); ?>
          <div class="rsv-room" data-index="<?php echo $i;?>">
            <h4><?php printf(esc_html__('Room %d','reeserva'), $i+1);?></h4>
            <div class="rsv-beds">
              <?php foreach($beds as $bed): ?>
              <div class="rsv-bed-row"><select name="rsv_rooms[<?php echo $i;?>][beds][]">
                <?php foreach(rsv_default_bed_types() as $k=>$label): ?>
                  <option value="<?php echo esc_attr($k);?>" <?php selected($bed,$k);?>><?php echo esc_html($label);?></option>
                <?php endforeach; ?>
              </select></div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="button add-bed"><?php esc_html_e('Add bed','reeserva');?></button>
          </div>
          <?php endfor; ?>
        </div>
      </div>
      <div class="ehb-card">
        <h3><?php esc_html_e('iCal Sync','reeserva');?></h3>
          <p class="desc"><?php esc_html_e('Paste external calendar URLs (one per line).','reeserva');?></p>
          <textarea name="rsv_ical_sources" rows="3" placeholder="https://calendar.airbnb.com/ical/....ics"><?php echo esc_textarea( implode("\n",(array) get_post_meta($post->ID,'rsv_ical_sources',true) ?: [] ) ); ?></textarea>
          <p class="desc"><?php printf( esc_html__('Export feed URL: %s','reeserva'), esc_url( add_query_arg(['rsv_ics'=>$post->ID,'key'=>get_option('rsv_ics_key') ?: 'set-after-save'], home_url('/') ) ) ); ?></p>
      </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
      var roomCount=document.getElementById('rsv-room-count');
      var wrapper=document.getElementById('rsv-rooms-wrapper');
      var bedOptions=`<?php foreach(rsv_default_bed_types() as $k=>$label){echo '<option value="'.esc_attr($k).'">'.esc_html($label).'</option>'; }?>`;
      function addBed(roomEl){
        var idx=roomEl.dataset.index;
        var beds=roomEl.querySelector('.rsv-beds');
        var div=document.createElement('div');
        div.className='rsv-bed-row';
        div.innerHTML='<select name="rsv_rooms['+idx+'][beds][]">'+bedOptions+'</select>';
        beds.appendChild(div);
      }
      function bind(roomEl){
        roomEl.querySelector('.add-bed').addEventListener('click',function(){addBed(roomEl);});
      }
      function renderRooms(){
        var count=parseInt(roomCount.value)||0;
        var current=wrapper.children.length;
        for(var i=current;i<count;i++){
          var room=document.createElement('div');
          room.className='rsv-room';
          room.dataset.index=i;
          room.innerHTML='<h4><?php echo esc_js(esc_html__('Room','reeserva'));?> '+(i+1)+'</h4><div class="rsv-beds"></div><button type="button" class="button add-bed"><?php echo esc_js(esc_html__('Add bed','reeserva'));?></button>';
          wrapper.appendChild(room);
          bind(room);
        }
        while(wrapper.children.length>count){wrapper.removeChild(wrapper.lastElementChild);}    
      }
      wrapper.querySelectorAll('.rsv-room').forEach(bind);
      roomCount.addEventListener('change',renderRooms);
    });
    </script>
    <?php
}
add_action('save_post_rsv_accomm', function($post_id){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    update_post_meta($post_id,'rsv_max_guests', intval($_POST['rsv_max_guests'] ?? 0));
    update_post_meta($post_id,'rsv_checkin', sanitize_text_field($_POST['rsv_checkin'] ?? ''));
    update_post_meta($post_id,'rsv_checkout', sanitize_text_field($_POST['rsv_checkout'] ?? ''));
    $amen = array_map('sanitize_text_field', (array) ($_POST['rsv_amenities'] ?? []));
    update_post_meta($post_id,'rsv_amenities',$amen);
    update_post_meta($post_id,'rsv_house_rules', wp_kses_post($_POST['rsv_house_rules'] ?? ''));
    update_post_meta($post_id,'rsv_room_count', intval($_POST['rsv_room_count'] ?? 0));
    if(isset($_POST['rsv_rooms'])){
        $rooms=[];
        foreach((array)$_POST['rsv_rooms'] as $r){
            $beds = array_map('sanitize_text_field', (array)($r['beds'] ?? []));
            $rooms[]=['beds'=>$beds];
        }
        update_post_meta($post_id,'rsv_rooms',$rooms);
    } else {
        delete_post_meta($post_id,'rsv_rooms');
    }
    if(isset($_POST['rsv_gallery'])){
        $lines = array_filter(array_map('trim', explode("\n", wp_kses_post($_POST['rsv_gallery']) )));
        update_post_meta($post_id,'rsv_gallery',$lines);
    }
    if(isset($_POST['rsv_ical_sources'])){
        $lines = array_filter(array_map('trim', explode("\n", wp_kses_post($_POST['rsv_ical_sources']) )));
        update_post_meta($post_id,'rsv_ical_sources',$lines);
    }
});

// Meta box for Booking
add_action('add_meta_boxes', function(){
    add_meta_box('rsv_booking_meta', __('Booking details','reeserva'),'rsv_render_booking_meta','rsv_booking','normal','high');
});
function rsv_render_booking_meta($post){
    $accomm = intval(get_post_meta($post->ID,'rsv_booking_accomm',true));
    $ci = esc_attr(get_post_meta($post->ID,'rsv_check_in',true));
    $co = esc_attr(get_post_meta($post->ID,'rsv_check_out',true));
    $name = esc_attr(get_post_meta($post->ID,'rsv_guest_name',true));
    $surname = esc_attr(get_post_meta($post->ID,'rsv_guest_surname',true));
    $email = esc_attr(get_post_meta($post->ID,'rsv_guest_email',true));
    $phone = esc_attr(get_post_meta($post->ID,'rsv_guest_phone',true));
    $pay = esc_attr(get_post_meta($post->ID,'rsv_payment_status',true));
    $types = get_posts(['post_type'=>'rsv_accomm','post_status'=>'publish','numberposts'=>-1]);
    ?>
    <p><label><?php esc_html_e('Accommodation','reeserva');?>
        <select name="rsv_booking_accomm">
            <option value="0"><?php esc_html_e('- Select -','reeserva');?></option>
            <?php foreach($types as $t): ?>
                <option value="<?php echo esc_attr($t->ID);?>" <?php selected($accomm,$t->ID);?>><?php echo esc_html(get_the_title($t));?></option>
            <?php endforeach; ?>
        </select>
    </label></p>
    <p><label><?php esc_html_e('Check-in','reeserva');?> <input type="date" name="rsv_check_in" value="<?php echo $ci;?>"></label></p>
    <p><label><?php esc_html_e('Check-out','reeserva');?> <input type="date" name="rsv_check_out" value="<?php echo $co;?>"></label></p>
    <p><label><?php esc_html_e('Guest name','reeserva');?> <input type="text" name="rsv_guest_name" value="<?php echo $name;?>"></label></p>
    <p><label><?php esc_html_e('Guest surname','reeserva');?> <input type="text" name="rsv_guest_surname" value="<?php echo $surname;?>"></label></p>
    <p><label><?php esc_html_e('Guest email','reeserva');?> <input type="email" name="rsv_guest_email" value="<?php echo $email;?>"></label></p>
    <p><label><?php esc_html_e('Guest phone','reeserva');?> <input type="text" name="rsv_guest_phone" value="<?php echo $phone;?>"></label></p>
    <p><label><?php esc_html_e('Payment status','reeserva');?> <input type="text" name="rsv_payment_status" value="<?php echo $pay;?>"></label></p>
    <?php
}
add_action('save_post_rsv_booking', function($post_id){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    update_post_meta($post_id,'rsv_booking_accomm', intval($_POST['rsv_booking_accomm'] ?? 0));
    update_post_meta($post_id,'rsv_check_in', sanitize_text_field($_POST['rsv_check_in'] ?? ''));
    update_post_meta($post_id,'rsv_check_out', sanitize_text_field($_POST['rsv_check_out'] ?? ''));
    update_post_meta($post_id,'rsv_guest_name', sanitize_text_field($_POST['rsv_guest_name'] ?? ''));
    update_post_meta($post_id,'rsv_guest_surname', sanitize_text_field($_POST['rsv_guest_surname'] ?? ''));
    update_post_meta($post_id,'rsv_guest_email', sanitize_email($_POST['rsv_guest_email'] ?? ''));
    update_post_meta($post_id,'rsv_guest_phone', sanitize_text_field($_POST['rsv_guest_phone'] ?? ''));
    update_post_meta($post_id,'rsv_payment_status', sanitize_text_field($_POST['rsv_payment_status'] ?? ''));
});

// Append booking details on single booking pages
add_filter('the_content', function($content){
    if('rsv_booking' !== get_post_type()) return $content;
    $ac = intval(get_post_meta(get_the_ID(),'rsv_booking_accomm',true));
    $ci = get_post_meta(get_the_ID(),'rsv_check_in',true);
    $co = get_post_meta(get_the_ID(),'rsv_check_out',true);
    $name = get_post_meta(get_the_ID(),'rsv_guest_name',true);
    $surname = get_post_meta(get_the_ID(),'rsv_guest_surname',true);
    $email = get_post_meta(get_the_ID(),'rsv_guest_email',true);
    $phone = get_post_meta(get_the_ID(),'rsv_guest_phone',true);
    $pay = get_post_meta(get_the_ID(),'rsv_payment_status',true);
    $out = '<h3>'.esc_html__('Booking details','reeserva').'</h3><ul class="rsv-booking-details">';
    if($ac) $out .= '<li><strong>'.esc_html__('Accommodation','reeserva').':</strong> '.esc_html(get_the_title($ac)).'</li>';
    if($ci) $out .= '<li><strong>'.esc_html__('Check-in','reeserva').':</strong> '.esc_html($ci).'</li>';
    if($co) $out .= '<li><strong>'.esc_html__('Check-out','reeserva').':</strong> '.esc_html($co).'</li>';
    if($name || $surname){
        $out .= '<li><strong>'.esc_html__('Guest','reeserva').':</strong> '.esc_html(trim($name.' '.$surname)).'</li>';
    }
    if($email) $out .= '<li><strong>'.esc_html__('Email','reeserva').':</strong> '.esc_html($email).'</li>';
    if($phone) $out .= '<li><strong>'.esc_html__('Phone','reeserva').':</strong> '.esc_html($phone).'</li>';
    if($pay) $out .= '<li><strong>'.esc_html__('Payment','reeserva').':</strong> '.esc_html($pay).'</li>';
    $out .= '</ul>';
    return $content.$out;
});
