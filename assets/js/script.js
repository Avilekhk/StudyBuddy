// Small client-side checks for StudyBuddy.
document.addEventListener("DOMContentLoaded", function () {
    const forms = document.querySelectorAll("form[data-confirm]");

    forms.forEach(function (form) {
        form.addEventListener("submit", function (event) {
            const message = form.getAttribute("data-confirm");
            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });

    // Dashboard tab switching (client-side): show panels without reload
    const tabs = document.querySelectorAll('.dashboard-tab');
    const panels = document.querySelectorAll('[data-tab-panel]');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const target = tab.getAttribute('data-tab-target');
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            panels.forEach(function (panel) {
                if (panel.getAttribute('data-tab-panel') === target) {
                    panel.classList.add('active');
                } else {
                    panel.classList.remove('active');
                }
            });
        });
    });

    // No global login role toggle here — student login is the default page.
});
