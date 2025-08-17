<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
