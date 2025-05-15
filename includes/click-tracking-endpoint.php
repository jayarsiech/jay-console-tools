<?php
// ذخیره کلیک از طریق AJAX

add_action('wp_ajax_tk_track_click', 'tk_track_click_handler');
add_action('wp_ajax_nopriv_tk_track_click', 'tk_track_click_handler');

function tk_track_click_handler() {
    if (
        !isset($_POST['url']) ||
        !isset($_POST['text']) ||
        !isset($_POST['source'])
    ) {
        wp_send_json_error('پارامترهای ناقص');
        return;
    }

    global $wpdb;

    $table = $wpdb->prefix . 'jay_tk_clicks';
    $url = esc_url_raw($_POST['url']);
    $text = sanitize_text_field($_POST['text']);
    $source = esc_url_raw($_POST['source']);
    $today = current_time('Y-m-d');

    // بررسی اینکه ردیف قبلی وجود داره؟
    $existing = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM $table WHERE target_url = %s AND anchor_text = %s AND source_page = %s AND click_date = %s",
            $url,
            $text,
            $source,
            $today
        )
    );

    if ($existing) {
        // افزایش شمارش کلیک
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table SET click_count = click_count + 1 WHERE id = %d",
                $existing
            )
        );
    } else {
        // درج ردیف جدید
        $wpdb->insert($table, [
            'target_url' => $url,
            'anchor_text' => $text,
            'source_page' => $source,
            'click_date' => $today,
            'click_count' => 1
        ]);
    }

    wp_send_json_success('ثبت شد');
}
