// Chart initialization and tab functionality
document.addEventListener('DOMContentLoaded', function() {
    const switchInterval = 300000; // 5 minutes in milliseconds
    let timeUntilSwitch = switchInterval / 1000;
    let tabTimer = null;

    // Restore last active tab or default to MALPAY
    const lastTab = localStorage.getItem('activeTab') || 'malpay';
    activateTab(lastTab);

    // Initialize Filters
    //initializeFilters();

    // Start tab rotation and countdown
    startTabRotation();
    updateCountdownDisplay();

    // Refresh button functionality
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            reloadCurrentTab();
        });
    }

    // Tab switching functionality
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            const tabName = tab.getAttribute('data-tab');
            activateTab(tabName);
            localStorage.setItem('activeTab', tabName);
            resetTabTimer();
        });
    });

    // Initialize charts
    initializeCharts();
});

function activateTab(tabName) {
    // Remove active classes
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    // Add active class to the selected tab
    const tab = document.querySelector(`[data-tab="${tabName}"]`);
    const content = document.getElementById(tabName);

    if (tab && content) {
        tab.classList.add('active');
        content.classList.add('active');
        localStorage.setItem('activeTab', tabName);
        updateTabTimerDisplay(tabName);
        setTimeout(initializeCharts, 100);
        console.log(`Switched to ${tabName.toUpperCase()} tab`);
    }
}

function startTabRotation() {
    tabTimer = setInterval(() => {
        const tabs = Array.from(document.querySelectorAll('.tab'));
        const currentTab = document.querySelector('.tab.active').getAttribute('data-tab');
        const currentIndex = tabs.findIndex(tab => tab.getAttribute('data-tab') === currentTab);
        const nextIndex = (currentIndex + 1) % tabs.length;
        const nextTab = tabs[nextIndex].getAttribute('data-tab');
        activateTab(nextTab);
        resetCountdown();
    }, 300000);
}

function resetTabTimer() {
    if (tabTimer) {
        clearInterval(tabTimer);
        startTabRotation();
        resetCountdown();
        console.log('Tab timer reset by manual switch');
    }
}

function resetCountdown() {
    timeUntilSwitch = 300;
}

function updateCountdownDisplay() {
    setInterval(() => {
        if (timeUntilSwitch > 0) {
            timeUntilSwitch--;
            const minutes = Math.floor(timeUntilSwitch / 60);
            const seconds = timeUntilSwitch % 60;
            const timerDisplay = document.querySelector('.tab-timer');
            if (timerDisplay) {
                const currentTab = document.querySelector('.tab.active').getAttribute('data-tab');
                const nextTab = currentTab === 'npms' ? 'MALPAY' : 'NPMS';
                timerDisplay.innerHTML = `🔄 Auto-switch to ${nextTab} in ${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
        }
    }, 1000);
}

function updateTabTimerDisplay(currentTab) {
    const timerDisplay = document.querySelector('.tab-timer');
    if (timerDisplay) {
        const nextTab = currentTab === 'npms' ? 'MALPAY' : 'NPMS';
        timerDisplay.innerHTML = `🔄 Auto-switch to ${nextTab} in 5:00`;
    }
}

// ================= FILTER LOGIC =================
function initializeFilters() {
    // This function is now empty since each dashboard handles its own filters
}

function reloadCurrentTab() {
    // Simple page reload maintaining current URL parameters
    window.location.reload();
}

// ================= POPUP =================
function showMetadata(metadata) {
    const popup = document.createElement('div');
    popup.style.position = 'fixed';
    popup.style.top = '50%';
    popup.style.left = '50%';
    popup.style.transform = 'translate(-50%, -50%)';
    popup.style.background = 'white';
    popup.style.padding = '20px';
    popup.style.borderRadius = '10px';
    popup.style.boxShadow = '0 10px 30px rgba(0,0,0,0.3)';
    popup.style.zIndex = '1000';
    popup.style.maxWidth = '80%';
    popup.style.maxHeight = '80%';
    popup.style.overflow = 'auto';

    popup.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
            <h3 style="margin:0;color:#2c9caf;">Transaction Log Details</h3>
            <button onclick="this.parentElement.parentElement.remove();document.querySelector('.modal-overlay').remove();" style="background:#2c9caf;color:white;border:none;padding:5px 10px;border-radius:5px;cursor:pointer;">Close</button>
        </div>
        <div class="metadata-popup">
            <pre style="white-space: pre-wrap; word-wrap: break-word;">${metadata}</pre>
        </div>
    `;

    document.body.appendChild(popup);

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100%';
    overlay.style.height = '100%';
    overlay.style.background = 'rgba(0,0,0,0.5)';
    overlay.style.zIndex = '999';
    overlay.onclick = function() {
        popup.remove();
        overlay.remove();
    };

    document.body.appendChild(overlay);
}

function initializeCharts() {
    console.log('Charts initialized for active tab');
}

// Auto-refresh every 5 minutes, keeping current tab
setInterval(() => {
    console.log('Auto-refreshing data...');
    reloadCurrentTab();
}, 300000);
