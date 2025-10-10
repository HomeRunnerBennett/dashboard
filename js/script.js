// Global variables to track current state
let currentTab = 'malpay';
let currentFilters = {
    date: new Date().toISOString().split('T')[0],
    date_end: new Date().toISOString().split('T')[0],
    merchant: 'all',
    status: 'all'
};

// Initialize the dashboard
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    setupEventListeners();
    loadInitialState();
});

function initializeDashboard() {
    console.log('Initializing dashboard...');
    
    // Set initial active tab based on URL or localStorage
    const urlParams = new URLSearchParams(window.location.search);
    const tabFromUrl = urlParams.get('tab');
    const savedTab = localStorage.getItem('currentTab');
    
    if (tabFromUrl) {
        currentTab = tabFromUrl;
    } else if (savedTab) {
        currentTab = savedTab;
    }
    
    // Show the correct tab
    showTab(currentTab);
    
    // Load saved filters
    loadSavedFilters();
    
    // Apply initial filters
    applyFilters();
}

function setupEventListeners() {
    // Tab navigation
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            showTab(tabName);
            saveCurrentState();
        });
    });
    
    // Filter inputs - using event delegation for dynamic elements
    document.addEventListener('change', function(e) {
        if (e.target.matches('#filterDate, #filterDateEnd, #filterMerchant, #filterStatus')) {
            updateFilters();
        }
    });
    
    // Apply filter button
    document.addEventListener('click', function(e) {
        if (e.target.matches('#applyFilter')) {
            applyFilters();
        }
    });
    
    // Enter key in filter inputs
    document.addEventListener('keypress', function(e) {
        if (e.target.matches('#filterDate, #filterDateEnd, #filterMerchant, #filterStatus') && e.key === 'Enter') {
            applyFilters();
        }
    });
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab') || 'malpay';
        const date = urlParams.get('date') || currentFilters.date;
        const date_end = urlParams.get('date_end') || currentFilters.date_end;
        const merchant = urlParams.get('merchant') || 'all';
        const status = urlParams.get('status') || 'all';
        
        currentTab = tab;
        currentFilters = { date, date_end, merchant, status };
        
        showTab(currentTab);
        updateFilterInputs();
        applyFilters(false); // Don't push state to avoid loop
    });
}

function loadInitialState() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Load filters from URL
    const date = urlParams.get('date');
    const date_end = urlParams.get('date_end');
    const merchant = urlParams.get('merchant');
    const status = urlParams.get('status');
    
    if (date) currentFilters.date = date;
    if (date_end) currentFilters.date_end = date_end;
    if (merchant) currentFilters.merchant = merchant;
    if (status) currentFilters.status = status;
    
    updateFilterInputs();
}

function loadSavedFilters() {
    const savedFilters = localStorage.getItem('dashboardFilters');
    if (savedFilters) {
        try {
            const filters = JSON.parse(savedFilters);
            currentFilters = { ...currentFilters, ...filters };
            updateFilterInputs();
        } catch (e) {
            console.error('Error loading saved filters:', e);
        }
    }
}

function updateFilters() {
    const dateInput = document.getElementById('filterDate');
    const dateEndInput = document.getElementById('filterDateEnd');
    const merchantInput = document.getElementById('filterMerchant');
    const statusInput = document.getElementById('filterStatus');
    
    if (dateInput) currentFilters.date = dateInput.value;
    if (dateEndInput) currentFilters.date_end = dateEndInput.value;
    if (merchantInput) currentFilters.merchant = merchantInput.value;
    if (statusInput) currentFilters.status = statusInput.value;
    
    // Validate date range
    if (currentFilters.date_end < currentFilters.date) {
        currentFilters.date_end = currentFilters.date;
        if (dateEndInput) dateEndInput.value = currentFilters.date_end;
    }
    
    saveCurrentState();
}

function updateFilterInputs() {
    const dateInput = document.getElementById('filterDate');
    const dateEndInput = document.getElementById('filterDateEnd');
    const merchantInput = document.getElementById('filterMerchant');
    const statusInput = document.getElementById('filterStatus');
    
    if (dateInput) dateInput.value = currentFilters.date;
    if (dateEndInput) dateEndInput.value = currentFilters.date_end;
    if (merchantInput && currentFilters.merchant) {
        merchantInput.value = currentFilters.merchant;
    }
    if (statusInput && currentFilters.status) {
        statusInput.value = currentFilters.status;
    }
}

function applyFilters(pushState = true) {
    console.log('Applying filters:', currentFilters);
    
    // Update URL with current state
    const urlParams = new URLSearchParams();
    urlParams.set('tab', currentTab);
    urlParams.set('date', currentFilters.date);
    urlParams.set('date_end', currentFilters.date_end);
    
    if (currentTab === 'malpay' && currentFilters.merchant !== 'all') {
        urlParams.set('merchant', currentFilters.merchant);
    }
    
    if (currentTab === 'npms' && currentFilters.status !== 'all') {
        urlParams.set('status', currentFilters.status);
    }
    
    const newUrl = `${window.location.pathname}?${urlParams.toString()}`;
    
    if (pushState) {
        window.history.pushState({ tab: currentTab, filters: currentFilters }, '', newUrl);
    }
    
    // Save to localStorage
    localStorage.setItem('currentTab', currentTab);
    localStorage.setItem('dashboardFilters', JSON.stringify(currentFilters));
    
    // Reload the page to apply filters
    window.location.href = newUrl;
}

function showTab(tabName) {
    console.log('Showing tab:', tabName);
    
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show the selected tab content
    const activeTabContent = document.getElementById(tabName);
    if (activeTabContent) {
        activeTabContent.classList.add('active');
    }
    
    // Activate the selected tab
    const activeTab = document.querySelector(`.tab[data-tab="${tabName}"]`);
    if (activeTab) {
        activeTab.classList.add('active');
    }
    
    currentTab = tabName;
    
    // Update filter visibility based on tab
    updateFilterVisibility();
    
    // Dispatch custom event for tab change
    window.dispatchEvent(new CustomEvent('tabChanged', { 
        detail: { tabName: tabName }
    }));
}

function updateFilterVisibility() {
    const merchantFilter = document.querySelector('.filter-group label[for="filterMerchant"]');
    const merchantSelect = document.getElementById('filterMerchant');
    const statusFilter = document.querySelector('.filter-group label[for="filterStatus"]');
    const statusSelect = document.getElementById('filterStatus');
    
    if (merchantFilter && merchantSelect) {
        if (currentTab === 'malpay') {
            merchantFilter.style.display = '';
            merchantSelect.style.display = '';
        } else {
            merchantFilter.style.display = 'none';
            merchantSelect.style.display = 'none';
        }
    }
    
    if (statusFilter && statusSelect) {
        if (currentTab === 'npms') {
            statusFilter.style.display = '';
            statusSelect.style.display = '';
        } else {
            statusFilter.style.display = 'none';
            statusSelect.style.display = 'none';
        }
    }
}

function saveCurrentState() {
    localStorage.setItem('currentTab', currentTab);
    localStorage.setItem('dashboardFilters', JSON.stringify(currentFilters));
}

// Utility functions for metadata and response modals
function showMetadata(metadata) {
    showModal('Transaction Metadata', metadata);
}

function showResponse(response) {
    showModal('Response Message', response);
}

function showModal(title, content) {
    // Remove existing modal if any
    const existingModal = document.getElementById('customModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create modal
    const modal = document.createElement('div');
    modal.id = 'customModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10000;
    `;
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: white;
        padding: 20px;
        border-radius: 8px;
        max-width: 90%;
        max-height: 90%;
        overflow: auto;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    `;
    
    const modalTitle = document.createElement('h3');
    modalTitle.textContent = title;
    modalTitle.style.marginTop = '0';
    
    const modalBody = document.createElement('div');
    modalBody.style.cssText = `
        margin: 20px 0;
        max-height: 400px;
        overflow: auto;
        font-family: monospace;
        white-space: pre-wrap;
        background: #f8f9fa;
        padding: 15px;
        border-radius: 4px;
        border: 1px solid #e9ecef;
    `;
    modalBody.textContent = content;
    
    const closeButton = document.createElement('button');
    closeButton.textContent = 'Close';
    closeButton.style.cssText = `
        background: #6c757d;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        float: right;
    `;
    closeButton.onclick = function() {
        modal.remove();
    };
    
    modalContent.appendChild(modalTitle);
    modalContent.appendChild(modalBody);
    modalContent.appendChild(closeButton);
    modal.appendChild(modalContent);
    
    // Close modal when clicking outside
    modal.onclick = function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    };
    
    document.body.appendChild(modal);
}

// Export to PDF function
function exportToPDF(dashboardType) {
    const exportDataElement = document.getElementById(`exportData${dashboardType.charAt(0).toUpperCase() + dashboardType.slice(1)}`);
    if (!exportDataElement) return;
    
    const exportData = JSON.parse(exportDataElement.dataset.export);
    
    // Create a form and submit it to the PDF generation endpoint
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'generate_pdf.php';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'export_data';
    input.value = JSON.stringify(exportData);
    form.appendChild(input);
    
    const dashboardInput = document.createElement('input');
    dashboardInput.type = 'hidden';
    dashboardInput.name = 'dashboard';
    dashboardInput.value = dashboardType;
    form.appendChild(dashboardInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Handle page refresh - restore state
window.addEventListener('beforeunload', function() {
    saveCurrentState();
});

// Handle page load - check for URL parameters
window.addEventListener('load', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    const date = urlParams.get('date');
    const date_end = urlParams.get('date_end');
    const merchant = urlParams.get('merchant');
    const status = urlParams.get('status');
    
    if (tab) {
        currentTab = tab;
    }
    
    if (date) currentFilters.date = date;
    if (date_end) currentFilters.date_end = date_end;
    if (merchant) currentFilters.merchant = merchant;
    if (status) currentFilters.status = status;
    
    showTab(currentTab);
    updateFilterInputs();
});