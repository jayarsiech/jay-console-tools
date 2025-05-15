document.addEventListener("DOMContentLoaded", function () {
    // فرم جستجو Ajax (ریدایرکت با GET)
    const form = document.getElementById("tk-search-form");
    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            const params = new URLSearchParams(new FormData(form));
            window.location.href = `${location.pathname}?${params.toString()}`;
        });
    }

    // دکمه کپی به کلیپ‌برد
    document.querySelectorAll(".tk-copy-button").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            const link = btn.getAttribute("data-link");
            navigator.clipboard.writeText(link).then(() => {
                btn.textContent = "✅";
                setTimeout(() => btn.textContent = "📋", 1000);
            });
        });
    });
});
