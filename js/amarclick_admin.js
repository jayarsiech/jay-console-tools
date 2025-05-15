
// مرتب‌سازی تعداد کلیک‌ها
document.addEventListener('DOMContentLoaded', function () {
    const clickSortButton = document.getElementById('tk-sort-clicks');
    const currentUrl = new URL(window.location.href);

    if (clickSortButton) {
        clickSortButton.addEventListener('click', function () {
            const orderby = currentUrl.searchParams.get('orderby');
            const order = currentUrl.searchParams.get('order');

            if (orderby === 'clicks') {
                currentUrl.searchParams.set('order', order === 'asc' ? 'desc' : 'asc');
            } else {
                currentUrl.searchParams.set('orderby', 'clicks');
                currentUrl.searchParams.set('order', 'desc');
            }

            window.location.href = currentUrl.toString();
        });
    }
});
// filter baze
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.quick-range').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const days = btn.getAttribute('data-days');
            const url = new URL(window.location.href);
            url.searchParams.delete('click_date'); // حذف فیلتر شمسی
            url.searchParams.set('range_days', days);
            url.searchParams.set('paged', 1); // بازگشت به صفحه اول
            window.location.href = url.toString();
        });
    });
});


// راهنما

document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('filter-help-toggle');
    const helpBox = document.getElementById('filter-help-box');

    toggleBtn.addEventListener('click', function(e) {
        e.stopPropagation(); // نذاره کلیک روی خودش باعث بسته شدن بشه
        helpBox.style.display = helpBox.style.display === 'block' ? 'none' : 'block';
    });

    document.addEventListener('click', function(e) {
        if (helpBox.style.display === 'block' && !helpBox.contains(e.target) && e.target !== toggleBtn) {
            helpBox.style.display = 'none';
        }
    });
});

