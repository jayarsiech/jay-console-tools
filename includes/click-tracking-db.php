<?php
// ساخت جدول کلیک هنگام فعال‌سازی افزونه

function tk_create_clicks_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'jay_tk_clicks';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        anchor_text TEXT NOT NULL,
        target_url TEXT NOT NULL,
        source_page TEXT NOT NULL,
        click_date DATE NOT NULL,
        click_count INT DEFAULT 1,
        UNIQUE KEY unique_click (anchor_text(100), target_url(100), source_page(100), click_date)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
