<div class="tab-guard-indicator" id="tab-guard-indicator">
    <div class="tab-count-display">
        <span class="current-tabs">0</span> / <span class="max-tabs">{{ $maxTabs ?? 5 }}</span> tabs
    </div>
    
    @if($showWarning ?? true)
    <div class="tab-warning" style="display: none;">
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong>Warning!</strong> You are approaching your tab limit. Consider closing some tabs.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    @endif
</div>

<style>
.tab-guard-indicator {
    position: fixed;
    top: 10px;
    right: 10px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 12px;
    z-index: 1050;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tab-count-display {
    font-weight: 600;
    color: #495057;
}

.current-tabs.warning {
    color: #f39c12;
}

.current-tabs.danger {
    color: #e74c3c;
}

.tab-warning {
    margin-top: 10px;
}

@media (max-width: 768px) {
    .tab-guard-indicator {
        position: static;
        margin: 10px;
        text-align: center;
    }
}
</style>

<script>
(function() {
    const indicator = document.getElementById('tab-guard-indicator');
    const currentTabsSpan = indicator.querySelector('.current-tabs');
    const maxTabsSpan = indicator.querySelector('.max-tabs');
    const warningDiv = indicator.querySelector('.tab-warning');
    
    function updateTabCount() {
        fetch('/tab-guard/tab-info')
            .then(response => response.json())
            .then(data => {
                const current = data.total_tabs || 0;
                const max = data.global_limit || parseInt(maxTabsSpan.textContent);
                
                currentTabsSpan.textContent = current;
                maxTabsSpan.textContent = max;
                
                // Update styling based on usage
                currentTabsSpan.className = 'current-tabs';
                if (current >= max * 0.8) {
                    currentTabsSpan.classList.add('warning');
                }
                if (current >= max * 0.9) {
                    currentTabsSpan.classList.add('danger');
                }
                
                // Show warning if approaching limit
                if (warningDiv && current >= max - 1) {
                    warningDiv.style.display = 'block';
                } else if (warningDiv) {
                    warningDiv.style.display = 'none';
                }
            })
            .catch(error => {
                console.warn('Failed to fetch tab info:', error);
            });
    }
    
    // Update on page load
    updateTabCount();
    
    // Update periodically
    setInterval(updateTabCount, 10000); // Every 10 seconds
    
    // Update on window focus
    window.addEventListener('focus', updateTabCount);
})();
</script>
