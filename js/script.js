// Chart initialization and tab functionality
document.addEventListener('DOMContentLoaded', function() {
    let currentTab = 'malpay';
    let tabTimer = null;
    const switchInterval = 300000; // 5 minutes in milliseconds (300,000 ms)
    let timeUntilSwitch = switchInterval / 1000; // Convert to seconds for display

    // Initialize MALPAY as active tab
    activateTab('malpay');
    
    // Tab switching
    const tabs = document.querySelectorAll('.tab');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            const tabName = tab.getAttribute('data-tab');
            activateTab(tabName);
            resetTabTimer();
        });
    });
    
    // Refresh button functionality
    document.getElementById('refreshBtn').addEventListener('click', function() {
        location.reload();
    });
    
    // Filter functionality
    initializeFilters();
    
    // Start tab rotation timer
    startTabRotation();
    
    // Initialize countdown display
    updateCountdownDisplay();
    
    // Initialize charts
    initializeCharts();
});

function activateTab(tabName) {
    // Remove active class from all tabs and contents
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    // Add active class to clicked tab and corresponding content
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
    document.getElementById(tabName).classList.add('active');
    
    // Update tab timer display
    updateTabTimerDisplay(tabName);
    
    // Refresh charts when switching tabs
    setTimeout(initializeCharts, 100);
    
    console.log(`Switched to ${tabName.toUpperCase()} tab`);
}

function startTabRotation() {
    tabTimer = setInterval(() => {
        const currentTab = document.querySelector('.tab.active').getAttribute('data-tab');
        const tabs = Array.from(document.querySelectorAll('.tab'));
        const currentIndex = tabs.findIndex(tab => tab.getAttribute('data-tab') === currentTab);
        const nextIndex = (currentIndex + 1) % tabs.length;
        const nextTab = tabs[nextIndex].getAttribute('data-tab');
        
        console.log(`Auto-switching from ${currentTab} to ${nextTab}`);
        activateTab(nextTab);
        resetCountdown();
    }, 300000); // 5 minutes
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
    timeUntilSwitch = 300; // Reset to 5 minutes in seconds
    updateCountdownDisplay();
}

function updateCountdownDisplay() {
    // Update countdown every second
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

function initializeFilters() {
    const filterBtn = document.getElementById('applyFilter');
    if (filterBtn) {
        filterBtn.addEventListener('click', applyFilters);
    }
    
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('filterDate');
    if (dateInput) {
        dateInput.value = today;
    }
}

function applyFilters() {
    const date = document.getElementById('filterDate').value;
    const merchant = document.getElementById('filterMerchant').value;
    
    // Reload page with filter parameters
    const params = new URLSearchParams();
    if (date) params.set('date', date);
    if (merchant) params.set('merchant', merchant);
    
    window.location.href = window.location.pathname + '?' + params.toString();
}

function showMetadata(metadata) {
    // Create a modal-like popup
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0; color: #2c9caf;">Transaction Log Details</h3>
            <button onclick="this.parentElement.parentElement.remove(); document.querySelector('.modal-overlay').remove();" style="background: #2c9caf; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">Close</button>
        </div>
        <div class="metadata-popup">
            <pre style="white-space: pre-wrap; word-wrap: break-word;">${metadata}</pre>
        </div>
    `;
    
    document.body.appendChild(popup);
    
    // Add overlay
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
    // Chart initialization will be handled by PHP-generated JavaScript
    console.log('Charts initialized for active tab');
}

// Auto-refresh data every 5 minutes (separate from tab switching)
setInterval(() => {
    console.log('Auto-refreshing data...');
    document.getElementById('refreshBtn').click();
}, 300000);