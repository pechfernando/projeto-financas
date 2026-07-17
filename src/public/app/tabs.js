document.addEventListener('DOMContentLoaded', () => {
    const tabButtons = document.querySelectorAll('.tab-btn');
    if (tabButtons.length === 0) return;

    // Use pathname to store tab state uniquely per page
    const storageKey = `activeTab_${window.location.pathname}`;
    const savedTab = localStorage.getItem(storageKey);

    function switchTab(tabId) {
        // Deactivate all buttons and panels on the page
        tabButtons.forEach(btn => {
            if (btn.dataset.tab === tabId) {
                btn.classList.add('ativo');
            } else {
                btn.classList.remove('ativo');
            }
        });

        const panels = document.querySelectorAll('.tab-painel');
        panels.forEach(panel => {
            if (panel.id === `tab-${tabId}`) {
                panel.classList.add('ativo');
            } else {
                panel.classList.remove('ativo');
            }
        });

        localStorage.setItem(storageKey, tabId);
    }

    // Set initial tab
    let initialTab = tabButtons[0].dataset.tab;
    if (savedTab && Array.from(tabButtons).some(btn => btn.dataset.tab === savedTab)) {
        initialTab = savedTab;
    }
    switchTab(initialTab);

    // Bind click events
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            switchTab(btn.dataset.tab);
        });
    });
});
