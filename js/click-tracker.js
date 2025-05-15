document.addEventListener("DOMContentLoaded", function () {
    document.body.addEventListener("click", function (e) {
        const link = e.target.closest("a");
        if (!link || !link.href) return;

        const targetUrl = link.href;
        const anchorText = link.textContent.trim();

        // فقط لینک‌های داخلی ردیابی بشن
        if (!targetUrl.startsWith(window.location.origin)) return;

        // صفحه‌ای که کلیک ازش انجام شده
        const sourcePage = window.location.href;

        // بررسی اینکه قبلاً همین کلیک ثبت نشده باشه در این سشن
        const clickKey = targetUrl + "|" + sourcePage + "|" + anchorText;
        
        // toggle storage & session
        
        const method = tk_click_tracker.storage || "session"; // 'session' or 'local'
        const storage = method === "local" ? localStorage : sessionStorage;
        
        const recentClicks = JSON.parse(storage.getItem("tk_recent_clicks") || "[]");
        
        if (recentClicks.includes(clickKey)) return;
        
        recentClicks.push(clickKey);
        if (recentClicks.length > 100) recentClicks.shift();
        storage.setItem("tk_recent_clicks", JSON.stringify(recentClicks));
        

        // کلیک سشن
        
        // const recentClicks = JSON.parse(sessionStorage.getItem("tk_recent_clicks") || "[]");
        // if (recentClicks.includes(clickKey)) return;
        // recentClicks.push(clickKey);
        // if (recentClicks.length > 100) recentClicks.shift();
        // sessionStorage.setItem("tk_recent_clicks", JSON.stringify(recentClicks));

        // کلیک storage
        
        // const recentClicks = JSON.parse(localStorage.getItem("tk_recent_clicks") || "[]");
        // if (recentClicks.includes(clickKey)) return;
        // recentClicks.push(clickKey);
        // if (recentClicks.length > 100) recentClicks.shift(); 
        // localStorage.setItem("tk_recent_clicks", JSON.stringify(recentClicks));

        // ارسال کلیک به وردپرس
        const formData = new FormData();
        formData.append("action", "tk_track_click");
        formData.append("url", targetUrl);
        formData.append("text", anchorText);
        formData.append("source", sourcePage);

        navigator.sendBeacon(tk_click_tracker.ajax_url, formData);
    });
});
