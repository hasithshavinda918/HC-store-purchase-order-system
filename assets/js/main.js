// HC Store Stock Management - Main JavaScript

// Show/Hide alerts
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} fixed top-4 right-4 z-50 max-w-sm`;
    
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
    
    alertDiv.innerHTML = `
        <div class="${bgColor} text-white px-6 py-4 rounded-lg shadow-lg">
            <div class="flex items-center">
                <div class="flex-1">
                    ${message}
                </div>
                <button onclick="this.parentElement.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

// Confirm deletion
function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-LK', {
        style: 'currency',
        currency: 'LKR'
    }).format(amount);
}

// Search functionality
function initializeSearch(inputId, tableId) {
    const searchInput = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (searchInput && table) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        });
    }
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('border-red-500');
            isValid = false;
        } else {
            field.classList.remove('border-red-500');
        }
    });
    
    return isValid;
}

// Stock level indicator
function getStockLevelClass(current, minimum) {
    if (current <= 0) return 'text-red-600 font-bold';
    if (current <= minimum) return 'text-yellow-600 font-semibold';
    return 'text-green-600';
}

// Auto-refresh functionality for dashboard
function autoRefresh(interval = 60000) {
    setInterval(() => {
        if (document.querySelector('.dashboard-stats')) {
            location.reload();
        }
    }, interval);
}

// Initialize tooltips
function initializeTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'absolute bg-black text-white text-xs px-2 py-1 rounded z-50';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.style.top = this.offsetTop + this.offsetHeight + 5 + 'px';
            tooltip.style.left = this.offsetLeft + 'px';
            this.appendChild(tooltip);
        });
        
        element.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.absolute.bg-black');
            if (tooltip) tooltip.remove();
        });
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeTooltips();
    
    // Initialize search if search input exists
    if (document.getElementById('searchInput')) {
        initializeSearch('searchInput', 'dataTable');
    }
    
    // Show flash messages if any
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success')) {
        showAlert(decodeURIComponent(urlParams.get('success')), 'success');
    }
    if (urlParams.get('error')) {
        showAlert(decodeURIComponent(urlParams.get('error')), 'error');
    }
});
