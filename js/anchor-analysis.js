document.addEventListener("DOMContentLoaded", function () {
    // مدیریت مدال‌ها
    document.body.addEventListener("click", function (e) {
        if (e.target.classList.contains("tk-show-details")) {
            e.preventDefault();
            const key = e.target.getAttribute("data-target");
            const modal = document.getElementById("tk-modal-" + key);
            if (modal) {
                modal.querySelectorAll("tbody tr").forEach(row => row.style.display = "");
                modal.style.display = "flex";
            }
        }

        if (e.target.classList.contains("tk-show-duplicates")) {
            e.preventDefault();
            const key = e.target.getAttribute("data-target");
            const modal = document.getElementById("tk-duplicate-modal-" + key);
            if (modal) {
                modal.style.display = "flex";
            }
        }

        if (e.target.classList.contains("tk-close-modal")) {
            e.preventDefault();
            e.target.closest(".tk-anchor-modal").style.display = "none";
        }
    });

    // مرتب‌سازی تعداد استفاده
    document.getElementById("tk-sort-usage").addEventListener("click", function () {
        const currentUrl = new URL(window.location.href);
        const currentOrder = currentUrl.searchParams.get('order');
        
        currentUrl.searchParams.set('orderby', 'usage');
        currentUrl.searchParams.set('order', currentOrder === 'asc' ? 'desc' : 'asc');
        
        window.location.href = currentUrl.toString();
    });

    // مرتب‌سازی لینک‌های نادرست
    document.getElementById("tk-sort-duplicate").addEventListener("click", function () {
        const currentUrl = new URL(window.location.href);
        const currentOrder = currentUrl.searchParams.get('order');
        
        currentUrl.searchParams.set('orderby', 'duplicate');
        currentUrl.searchParams.set('order', currentOrder === 'asc' ? 'desc' : 'asc');
        
        window.location.href = currentUrl.toString();
    });
});


document.body.addEventListener('click', function (e) {
    if (e.target.classList.contains('tk-load-more')) {
        e.preventDefault();

        const btn = e.target;
        const offset = parseInt(btn.getAttribute('data-offset'), 10);
        const key = btn.getAttribute('data-key');

        btn.disabled = true;
        btn.textContent = 'در حال بارگذاری...';

        const data = new FormData();
        data.append('action', 'load_more_anchor_links');
        data.append('anchor_key', key);
        data.append('offset', offset);

        fetch(ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: data
        })
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                const tbody = document.getElementById('tk-modal-body-' + key);
                tbody.insertAdjacentHTML('beforeend', response.data.html);

                if (response.data.has_more) {
                    btn.setAttribute('data-offset', offset + 5);
                    btn.disabled = false;
                    btn.textContent = 'بارگذاری بیشتر...';
                } else {
                    btn.remove(); // اگر دیگه داده‌ای نیست، دکمه حذف شود
                }
            } else {
                btn.textContent = 'خطا در بارگذاری';
            }
        })
        .catch(err => {
            btn.textContent = 'خطا';
            console.error(err);
        });
    }
});

// هدنل لینک اشتباه
document.body.addEventListener('click', function (e) {
    // دکمه بارگذاری لینک‌های نادرست
    if (e.target.classList.contains('tk-load-more-duplicates')) {
        e.preventDefault();

        const btn   = e.target;
        const key   = btn.getAttribute('data-key');
        const offset = parseInt(btn.getAttribute('data-offset'), 10);

        btn.disabled = true;
        btn.textContent = 'در حال بارگذاری...';

        const data = new FormData();
        data.append('action', 'load_more_duplicate_links');
        data.append('anchor_key', key);
        data.append('offset', offset);

        fetch(ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: data
        })
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                const tbody = document.getElementById('tk-duplicate-body-' + key);
                tbody.insertAdjacentHTML('beforeend', response.data.html);

                if (response.data.has_more) {
                    btn.setAttribute('data-offset', offset + 5);
                    btn.disabled = false;
                    btn.textContent = 'بارگذاری بیشتر لینک‌های نادرست...';
                } else {
                    btn.remove();
                }
            } else {
                btn.textContent = 'خطا';
            }
        })
        .catch(err => {
            btn.textContent = 'خطا در ارتباط';
            console.error(err);
        });
    }
});
