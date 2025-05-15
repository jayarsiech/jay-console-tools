<?php
// هندلر انکر مودال
function load_more_anchor_links() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized');
    }

    $anchor_key = sanitize_text_field($_POST['anchor_key']);
    $offset = intval($_POST['offset']);

    $anchors = get_transient('jay_anchor_analysis_data');
    if (!isset($anchors[$anchor_key])) {
        wp_send_json_error('Anchor not found');
    }

    $links = $anchors[$anchor_key]['links'];
    $slice = array_slice($links, $offset, 5);

    ob_start();
   foreach ($slice as $index => $link) {
    $row_number = $offset + $index + 1;

        ?>
        <tr>
                <td><?php echo $row_number; ?></td>

            <td>
                <a href="<?php echo esc_url($link['source_url']); ?>" target="_blank">
                    <?php echo esc_html($link['source_title']); ?>
                </a>
                <br>
                <small style="color:#666;">منبع: <?php echo $link['source_type'] === 'acf' ? 'ACF' : 'محتوا'; ?></small>
            </td>
            <td>
                <a href="<?php echo esc_url($link['target_url']); ?>" target="_blank">
                    <?php echo esc_html(urldecode($link['target_url'])); ?>
                </a>
            </td>
        </tr>
        <?php
    }

    wp_send_json_success([
        'html' => ob_get_clean(),
        'has_more' => ($offset + 5) < count($links)
    ]);
}
add_action('wp_ajax_load_more_anchor_links', 'load_more_anchor_links');


// لینک اشتباه
function tk_load_more_duplicate_links() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized');
    }

    $anchor_key = sanitize_text_field($_POST['anchor_key']);
    $offset     = intval($_POST['offset']);

    $anchors = get_transient('jay_anchor_analysis_data');
    if (!isset($anchors[$anchor_key])) {
        wp_send_json_error('Anchor not found');
    }

    $links = $anchors[$anchor_key]['links'];
    // تعیین مقصد اصلی
    $link_counts = array_count_values(array_column($links, 'target_url'));
    arsort($link_counts);
    $main_target = key($link_counts);

    // استخراج لینک‌های نادرست
    $unexpected_links = array_filter($links, function($link) use ($main_target) {
        return $link['target_url'] !== $main_target;
    });

    // بریدن ۵ تای بعدی
    $slice = array_slice(array_values($unexpected_links), $offset, 5);

    ob_start();
    foreach ($slice as $index => $link) {
    $row_number = $offset + $index + 1;

        ?>
        <tr>
                <td><?php echo $row_number; ?></td>

            <td>
                <a href="<?php echo esc_url($link['source_url']); ?>" target="_blank">
                    <?php echo esc_html($link['source_title']); ?>
                </a>
                <br>
                <small style="color:#666;">منبع: <?php echo $link['source_type'] === 'acf' ? 'ACF' : 'محتوا'; ?></small>
            </td>
            <td>
                <a href="<?php echo esc_url($link['target_url']); ?>" target="_blank">
                    <?php echo esc_html(urldecode($link['target_url'])); ?>
                </a>
            </td>
        </tr>
        <?php
    }

    wp_send_json_success([
        'html'     => ob_get_clean(),
        'has_more' => ($offset + 5) < count($unexpected_links)
    ]);
}
add_action('wp_ajax_load_more_duplicate_links', 'tk_load_more_duplicate_links');

