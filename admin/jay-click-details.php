<?php
if (!current_user_can('edit_published_posts')) {
    wp_die('شما اجازه دسترسی به این بخش را ندارید.');
}

global $wpdb;

$table = $wpdb->prefix . 'jay_tk_clicks';

$date_filter = '';
$where = '';
$params = [];

if (!empty($_GET['click_date'])) {
    $date_filter = sanitize_text_field($_GET['click_date']);
    list($jy, $jm, $jd) = explode('/', $date_filter);
    $gregorian = tk_jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd);
    $date_g = sprintf('%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2]);
    $where = "AND click_date = %s";
    $params[] = $date_g;
} elseif (!empty($_GET['range_days'])) {
    $days = intval($_GET['range_days']);
    $date_from = date('Y-m-d', strtotime("-$days days"));
    $where = "AND click_date >= %s";
    $params[] = $date_from;
}
$target_url = isset($_GET['target_url']) ? rtrim(sanitize_text_field($_GET['target_url']), '/') : '';

if (!empty($target_url)) {
    $where .= " AND TRIM(TRAILING '/' FROM target_url) = TRIM(TRAILING '/' FROM %s)";
    $params[] = $target_url;
}
$source_page = isset($_GET['source_page']) ? rtrim(sanitize_text_field($_GET['source_page']), '/') : '';
if (!empty($source_page)) {
    $where .= " AND TRIM(TRAILING '/' FROM source_page) = TRIM(TRAILING '/' FROM %s)";
    $params[] = $source_page;
}

$anchor_text = isset($_GET['anchor_text']) ? sanitize_text_field($_GET['anchor_text']) : '';

$order_by = ($_GET['orderby'] ?? '') === 'clicks' ? 'click_count' : 'click_date';
$order = ($_GET['order'] ?? '') === 'asc' ? 'ASC' : 'DESC';

$per_page = 10;
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($paged - 1) * $per_page;

// گرفتن مجموع رکوردها
$total_sql = "SELECT COUNT(*) FROM $table WHERE anchor_text = %s " . ($where ? " $where" : '');
$total_params = array_merge([$anchor_text], $params);
$total = $wpdb->get_var($wpdb->prepare($total_sql, ...$total_params));
$total_pages = ceil($total / $per_page);

// گرفتن نتایج با LIMIT
$sql = "SELECT * FROM $table WHERE anchor_text = %s " . ($where ? " $where" : '') . " ORDER BY $order_by $order LIMIT %d OFFSET %d";
$params = array_merge([$anchor_text], $params, [$per_page, $offset]);
$results = $wpdb->get_results($wpdb->prepare($sql, ...$params));

// گروه‌بندی
$grouped_details = [];

foreach ($results as $row) {
    $key = $row->target_url . '|' . $row->source_page . '|' . $row->click_date;
    
    if (!isset($grouped_details[$key])) {
        $grouped_details[$key] = [
            'target_url' => urldecode($row->target_url),
            'source_page' => urldecode($row->source_page),
            'click_date' => tk_jdate('Y/m/d', strtotime($row->click_date)),
            'click_count' => $row->click_count
        ];
    } else {
        $grouped_details[$key]['click_count'] += $row->click_count;
    }
}

$current_url = esc_url_raw(remove_query_arg(['orderby', 'order', 'paged'], $_SERVER['REQUEST_URI']));
$toggle_order = ($order === 'ASC') ? 'desc' : 'asc';
?>

<div class="wrap">
    <h1>📊 جزئیات کلیک‌ها برای: <?php echo esc_html($anchor_text); ?></h1>

    
<div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
    <a href="<?php echo admin_url('admin.php?page=jay_click_stats'); ?>" class="button">⬅️ بازگشت</a>
    <button id="filter-help-toggle" class="button button-secondary" title="راهنمای فیلترها" style="padding: 4px 8px; line-height:1;">❔</button>

    <div id="filter-help-box" style="display:none; position:absolute; top:40px; right:0; background:#fefefe; border:1px solid #ccc; padding:15px; max-width:400px; box-shadow:0 0 10px rgba(0,0,0,0.1); z-index:1000;">
        <p style="margin:0;">
            🔹 فیلترهای تاریخ و آدرس هرکدام به صورت مستقل قابل استفاده‌اند.<br>
            🔹 فیلدهای آدرس مقصد و صفحه کلیک‌شده می‌توانند همزمان یا جداگانه پر شوند.<br>
            🔹 آدرس‌ها نسبت به / در انتهای URL حساس نیستند.<br>
                        🔹 نتایج دقیق با توجه به فیلترها نشان داده می‌شوند.<br>

        </p>
    </div>
</div>

    <form method="get" style="margin-bottom:20px; display:flex; align-items:center; gap:10px; flex-wrap: wrap;">
        <input type="hidden" name="page" value="jay-click-details">
        <input type="hidden" name="anchor_text" value="<?php echo esc_attr($anchor_text); ?>">
        <label>تاریخ (شمسی):</label>
        <input type="text" name="click_date" class="persian-date" value="<?php echo esc_attr($date_filter); ?>" placeholder="مثلاً 1403/03/01">
        <button class="button">فیلتر</button>

        <?php if ($date_filter): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=jay-click-details&anchor_text=' . urlencode($anchor_text))); ?>" class="button">ریست</a>
        <?php endif; ?>

        <?php
        $active_range = isset($_GET['range_days']) ? intval($_GET['range_days']) : 0;
        ?>

        <button type="button" class="button button-secondary quick-range <?php echo ($active_range === 1) ? 'active' : ''; ?>" data-days="1">🕐 ۲۴ ساعت</button>
        <button type="button" class="button button-secondary quick-range <?php echo ($active_range === 7) ? 'active' : ''; ?>" data-days="7">🗓 ۷ روز</button>
        <button type="button" class="button button-secondary quick-range <?php echo ($active_range === 28) ? 'active' : ''; ?>" data-days="28">📅 ۲۸ روز</button>
        <button type="button" class="button button-secondary quick-range <?php echo ($active_range === 90) ? 'active' : ''; ?>" data-days="90">🗓 ۳ ماه</button>
    </form>

<form method="get" style="margin-bottom:20px; display:flex; align-items:center; gap:10px; flex-wrap: wrap;">
    <input type="hidden" name="page" value="jay-click-details">
    <input type="hidden" name="anchor_text" value="<?php echo esc_attr($anchor_text); ?>">
    <label>فیلتر آدرس مقصد:</label>
    <input type="text" name="target_url" value="<?php echo isset($_GET['target_url']) ? esc_attr($_GET['target_url']) : ''; ?>" placeholder="مثلاً https://dastgahelaser.ir/products/metal-laser-cutting-machine">
   <label>فیلتر صفحه کلیک‌شده:</label>
<input type="text" name="source_page" value="<?php echo isset($_GET['source_page']) ? esc_attr($_GET['source_page']) : ''; ?>" placeholder="مثلاً https://dastgahelaser.ir/blog/laser-types">

    <button class="button">اعمال فیلتر آدرس</button>

<?php if (!empty($_GET['target_url']) || !empty($_GET['source_page'])): ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=jay-click-details&anchor_text=' . urlencode($anchor_text))); ?>" class="button">ریست فیلترهای URL</a>
<?php endif; ?>

</form>



    <?php if (!empty($grouped_details)): ?>
        <p>🔢 تعداد رکوردها: <?php echo esc_html($total); ?></p>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>🎯 آدرس مقصد</th>
                    <th>📄 صفحه کلیک‌شده</th>
                    <th>📅 تاریخ</th>
                    <th>
                        <a href="<?php echo esc_url(add_query_arg(['orderby' => 'clicks', 'order' => $toggle_order], $current_url)); ?>">
                            🔢 تعداد کلیک <?php echo ($order_by === 'click_count') ? ($order === 'ASC' ? '⬆️' : '⬇️') : ''; ?>
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                $row_num = $offset + 1;
                foreach ($grouped_details as $detail):
                ?>
                <tr>
                    <td><?php echo $row_num++; ?></td>
                    <td><a href="<?php echo esc_url($detail['target_url']); ?>" target="_blank"><?php echo esc_html($detail['target_url']); ?></a></td>
                    <td><a href="<?php echo esc_url($detail['source_page']); ?>" target="_blank"><?php echo esc_html($detail['source_page']); ?></a></td>
                    <td><?php echo esc_html($detail['click_date']); ?></td>
                    <td><?php echo esc_html($detail['click_count']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div style="margin-top: 20px; text-align:center;display: flex;justify-content: center;align-items: center;gap: 5px;">
                <?php
                $base_url = remove_query_arg('paged');
                $range = 2;
                if ($paged > 1) {
                    echo '<a class="button" href="' . esc_url(add_query_arg('paged', $paged - 1, $base_url)) . '">◀ قبلی</a>';
                }

                for ($i = max(1, $paged - $range); $i <= min($total_pages, $paged + $range); $i++) {
                    $class = ($i === $paged) ? 'button button-primary' : 'button';
                    echo '<a class="' . $class . '" href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . $i . '</a>';
                }

                if ($paged < $total_pages) {
                    echo '<a class="button" href="' . esc_url(add_query_arg('paged', $paged + 1, $base_url)) . '">بعدی ▶</a>';
                }
                ?>
            </div>
        <?php endif; ?>
<?php else: ?>
    <p>
        هیچ رکوردی یافت نشد.
        <?php if (!empty($target_url)): ?>
            برای آدرس:
            <strong><?php echo esc_html($target_url); ?></strong>
        <?php endif; ?>
        <?php if (!empty($source_page)): ?>
    در صفحه:
    <strong><?php echo esc_html($source_page); ?></strong>
<?php endif; ?>

    </p>
<?php endif; ?>

</div>

