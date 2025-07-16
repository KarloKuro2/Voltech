// Feedback Modal logic for po_warehouse_materials.php
function removeQueryParam(param) {
  const url = new URL(window.location);
  url.searchParams.delete(param);
  window.history.replaceState({}, document.title, url.pathname + url.search);
}
function showFeedbackModal(success, message, reason = '', paramToRemove = null) {
  var icon = document.getElementById('feedbackIcon');
  var title = document.getElementById('feedbackTitle');
  var msg = document.getElementById('feedbackMessage');
  if (success) {
    icon.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745"></i>';
    title.textContent = 'Success!';
    msg.textContent = message;
  } else {
    icon.innerHTML = '<i class="fas fa-times-circle" style="color:#dc3545"></i>';
    title.textContent = 'Error!';
    msg.textContent = message + (reason ? ' Reason: ' + reason : '');
  }
  var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
  feedbackModal.show();
  if (paramToRemove) {
    removeQueryParam(paramToRemove);
  }
}
// Optionally, focus handling after modal close (like po_suppliers.js)
document.addEventListener('DOMContentLoaded', function() {
  var feedbackModalEl = document.getElementById('feedbackModal');
  if (feedbackModalEl) {
    feedbackModalEl.addEventListener('hidden.bs.modal', function () {
      var addBtn = document.querySelector('.btn-success[data-bs-target="#addWarehouseModal"]');
      if (addBtn) {
        addBtn.focus();
      } else {
        document.body.focus();
      }
    });
  }
    var searchInput = document.getElementById('searchInput');
    var categoryFilter = document.getElementById('categoryFilter');
    var warehouseFilter = document.getElementById('warehouseFilter');
    var usedSlotsInput = document.querySelector('input[name="used_slots"]');
    var searchForm = document.getElementById('searchForm');
    if (searchInput && searchForm) {
        var searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                searchForm.submit();
            }, 400);
        });
    }
    if (categoryFilter && searchForm) {
        categoryFilter.addEventListener('change', function() {
            searchForm.submit();
        });
    }
    if (warehouseFilter && searchForm) {
        warehouseFilter.addEventListener('change', function() {
            searchForm.submit();
        });
    }
    if (usedSlotsInput && searchForm) {
        usedSlotsInput.addEventListener('change', function() {
            searchForm.submit();
        });
    }
}); 