<?php
if (!current_user_can('edit_published_posts')) {
    wp_die('شما اجازه دسترسی به این بخش را ندارید.');
}

global $wpdb;
$table = $wpdb->prefix . 'jay_tk_clicks';
require_once plugin_dir_path(__FILE__) . '../includes/jdf.php'; // یا مسیر دقیق فایل jdf.php

$date_filter = '';
$where = '';

if (!empty($_GET['click_date'])) {
    $date_filter = sanitize_text_field($_GET['click_date']); // مثلاً 1403/03/01
    list($jy, $jm, $jd) = explode('/', $date_filter);
    $gregorian = tk_jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd);
    $date_g = sprintf('%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2]);
    $where = $wpdb->prepare("WHERE click_date = %s", $date_g);
} elseif (!empty($_GET['range_days'])) {
    $days = intval($_GET['range_days']);
    $date_from = date('Y-m-d', strtotime("-$days days"));
    $where = $wpdb->prepare("WHERE click_date >= %s", $date_from);
}


// حذف آمار قدیمی اگر دکمه کلیک شده بود
if (isset($_GET['delete_old_clicks']) && current_user_can('edit_published_posts')) {
    $six_months_ago = date('Y-m-d', strtotime('-6 months'));
    $deleted = $wpdb->query(
        $wpdb->prepare("DELETE FROM {$wpdb->prefix}jay_tk_clicks WHERE click_date < %s", $six_months_ago)
    );

    echo '<div class="notice notice-success"><p>' . esc_html($deleted) . ' ردیف قدیمی حذف شد.</p></div>';
}

$per_page = 10;
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($paged - 1) * $per_page;


// گرفتن آمار کلیک
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $table $where ORDER BY click_date DESC, click_count DESC"
    )
);

if (isset($_GET['orderby']) && $_GET['orderby'] === 'clicks') {
    usort($results, function ($a, $b) {
        return $b->click_count - $a->click_count;
    });
} else {
    usort($results, function ($a, $b) {
        return strcmp($a->anchor_text, $b->anchor_text);
    });
}

// گروه
// گروه‌بندی نتایج بر اساس anchor_text
$grouped_results = [];
foreach ($results as $row) {
    $key = $row->anchor_text . '|' . $row->target_url . '|' . $row->source_page . '|' . $row->click_date;
    
    if (!isset($grouped_results[$row->anchor_text])) {
        $grouped_results[$row->anchor_text] = [
            'anchor_text' => $row->anchor_text,
            'click_count' => 0,
            'details' => []
        ];
    }
    
    $grouped_results[$row->anchor_text]['click_count'] += $row->click_count;

    if (!isset($grouped_results[$row->anchor_text]['details'][$key])) {
        $grouped_results[$row->anchor_text]['details'][$key] = [
            'target_url' => urldecode($row->target_url),
            'source_page' => urldecode($row->source_page),
            'click_date' => tk_jdate('Y/m/d', strtotime($row->click_date)),
            'click_count' => $row->click_count
        ];
    } else {
        $grouped_results[$row->anchor_text]['details'][$key]['click_count'] += $row->click_count;
    }
}

// مرتب‌سازی details بر اساس click_date نزولی
foreach ($grouped_results as &$group) {
    uasort($group['details'], function ($a, $b) {
        return strtotime($b['click_date']) - strtotime($a['click_date']);
    });
}
unset($group);

// صفحه بندی
// مرتب‌سازی
$order_by = isset($_GET['orderby']) && $_GET['orderby'] === 'clicks' ? 'click_count' : 'anchor_text';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

uasort($grouped_results, function ($a, $b) use ($order_by, $order) {
    if ($order_by === 'click_count') {
        return $order === 'ASC' ? $a['click_count'] - $b['click_count'] : $b['click_count'] - $a['click_count'];
    } else {
        return $order === 'ASC' ? strcmp($a['anchor_text'], $b['anchor_text']) : strcmp($b['anchor_text'], $a['anchor_text']);
    }
});

$total_rows = count($grouped_results);
$total_pages = ceil($total_rows / $per_page);

$paged_results = array_slice($grouped_results, $offset, $per_page, true);


?>

<div class="wrap">
    <h1>📊 آمار کلیک‌ها</h1>

<form method="get" style="margin-bottom:20px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
    <input type="hidden" name="page" value="jay_click_stats">
    <label>فیلتر تاریخ (شمسی):</label>
    <input type="text" name="click_date" id="click-date-input" class="persian-date" value="<?php echo esc_attr($date_filter); ?>" placeholder="مثلاً 1403/03/01" autocomplete="off">
    <button class="button">فیلتر</button>
    <?php if ($date_filter): ?>
        <a href="<?php echo admin_url('admin.php?page=jay_click_stats'); ?>" class="button">ریست</a>
    <?php endif; ?>

    <!-- دکمه‌های بازه زمانی -->
<?php
$active_range = isset($_GET['range_days']) ? intval($_GET['range_days']) : 0;
?>

<button type="button" class="button button-secondary quick-range <?php echo ($active_range === 1) ? 'active' : ''; ?>" data-days="1">🕐 ۲۴ ساعت</button>
<button type="button" class="button button-secondary quick-range <?php echo ($active_range === 7) ? 'active' : ''; ?>" data-days="7">🗓 ۷ روز</button>
<button type="button" class="button button-secondary quick-range <?php echo ($active_range === 28) ? 'active' : ''; ?>" data-days="28">📅 ۲۸ روز</button>
<button type="button" class="button button-secondary quick-range <?php echo ($active_range === 90) ? 'active' : ''; ?>" data-days="90">🗓 ۳ ماه</button>

</form>


<form method="get" style="margin-top: 10px;">
    <input type="hidden" name="page" value="jay_click_stats">
    <input type="hidden" name="delete_old_clicks" value="1">
    <button class="button button-secondary" onclick="return confirm('آیا مطمئن هستید؟ این کار قابل بازگشت نیست.')">🧹 حذف آمار قدیمی‌تر از ۶ ماه</button>
</form>

    <?php if (!empty($paged_results)): ?>
    <p style="margin:10px 0; font-weight:bold;">🔢 تعداد رکوردهای یافت‌شده: <?php echo esc_html($total_rows); ?></p>

<table class="widefat striped" id="tk-clicks-table">
    <thead>
        <tr>
            <th>#</th>
            <th>📌 متن لینک</th>
<th id="tk-sort-clicks" style="cursor: pointer; color: #0073aa;" title="مرتب‌سازی تعداد کلیک">
    🔢 تعداد کلیک
</th>
<th>جزئیات</th>
        </tr>
    </thead>
    <tbody>
        <?php $row_number = ($paged - 1) * $per_page + 1; ?>
        <?php foreach ($paged_results as $anchor_text => $data): ?>
            <tr>
                <td><?php echo $row_number++; ?></td>
                <td><?php echo esc_html($data['anchor_text']); ?></td>
                <td><?php echo esc_html($data['click_count']); ?></td>
<td>
    <?php
$filter_query = '';
if (!empty($_GET['click_date'])) {
    $filter_query = '&click_date=' . urlencode($_GET['click_date']);
} elseif (!empty($_GET['range_days'])) {
    $filter_query = '&range_days=' . intval($_GET['range_days']);
}
?>

<a href="<?php echo esc_url(admin_url('admin.php?page=jay-click-details&anchor_text=' . urlencode($anchor_text) . $filter_query)); ?>" class="button">مشاهده</a>

</td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

        <?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px;">
     
     <?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; text-align:center;display: flex;justify-content: center;align-items: center;gap: 5px;">
        <?php
        $base_url = remove_query_arg('paged');
        $range = 2;

        // دکمه قبلی
        if ($paged > 1) {
            echo '<a class="button" href="' . add_query_arg('paged', $paged - 1, $base_url) . '">◀ قبلی</a>';
        }

        // اعداد وسط
        for ($i = max(1, $paged - $range); $i <= min($total_pages, $paged + $range); $i++) {
            $class = ($i == $paged) ? 'button button-primary' : 'button';
            echo '<a class="' . $class . '" href="' . add_query_arg('paged', $i, $base_url) . '">' . $i . '</a>';
        }

        // دکمه بعدی
        if ($paged < $total_pages) {
            echo '<a class="button" href="' . add_query_arg('paged', $paged + 1, $base_url) . '">بعدی ▶</a>';
        }
        ?>
    </div>
<?php endif; ?>

     
     
     
    </div>
<?php endif; ?>

    <?php else: ?>
        <p>هیچ کلیکی یافت نشد.</p>
    <?php endif; ?>
</div>
 
 
