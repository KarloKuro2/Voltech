// JS for po_materials.php
// All code moved from inline <script> tags

// Sidebar toggle
var el = document.getElementById("wrapper");
var toggleButton = document.getElementById("menu-toggle");
if (toggleButton) {
    toggleButton.onclick = function () {
        el.classList.toggle("toggled");
    };
}

// Search, filter, and debounce
// (searchInput, categoryFilter, statusFilter, supplierFilter, searchForm)
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('searchInput');
    var categoryFilter = document.getElementById('categoryFilter');
    var statusFilter = document.getElementById('statusFilter');
    var supplierFilter = document.getElementById('supplierFilter');
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
    if (statusFilter && searchForm) {
        statusFilter.addEventListener('change', function() {
            searchForm.submit();
        });
    }
    if (supplierFilter && searchForm) {
        supplierFilter.addEventListener('change', function() {
            searchForm.submit();
        });
    }
});

// Success/Error Modal feedback
// (successModal, errorModal)
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    var successModal = new bootstrap.Modal(document.getElementById('successModal'));
    var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
    if (urlParams.has('added')) {
        document.getElementById('successModalTitle').textContent = 'Success!';
        document.getElementById('successModalMsg').textContent = 'Material added successfully!';
        successModal.show();
        setTimeout(function() { successModal.hide(); }, 2000);
        document.getElementById('successModal').addEventListener('hidden.bs.modal', function() {
            window.location = window.location.pathname;
        });
    }
    if (urlParams.has('updated')) {
        document.getElementById('successModalTitle').textContent = 'Updated!';
        document.getElementById('successModalMsg').textContent = 'Material updated successfully!';
        successModal.show();
        setTimeout(function() { successModal.hide(); }, 2000);
        document.getElementById('successModal').addEventListener('hidden.bs.modal', function() {
            window.location = window.location.pathname;
        });
    }
    if (urlParams.has('deleted')) {
        document.getElementById('successModalTitle').textContent = 'Deleted!';
        document.getElementById('successModalMsg').textContent = 'Material deleted successfully!';
        successModal.show();
        setTimeout(function() { successModal.hide(); }, 2000);
        document.getElementById('successModal').addEventListener('hidden.bs.modal', function() {
            window.location = window.location.pathname;
        });
    }
    if (urlParams.has('error')) {
        document.getElementById('errorModalMsg').textContent = 'An error occurred. Please try again.';
        errorModal.show();
        setTimeout(function() { errorModal.hide(); }, 3000);
        document.getElementById('errorModal').addEventListener('hidden.bs.modal', function() {
            window.location = window.location.pathname;
        });
    }
});

// Delete Material Modal logic
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.delete-material-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var matId = this.getAttribute('data-id');
      var matName = this.getAttribute('data-name');
      document.getElementById('materialName').textContent = matName;
      var confirmDelete = document.getElementById('confirmDeleteMaterial');
      confirmDelete.setAttribute('href', 'delete_materials.php?id=' + matId);
      var modal = new bootstrap.Modal(document.getElementById('deleteMaterialModal'));
      modal.show();
    });
  });
});

// Change Password AJAX (like pm_profile.php)
document.addEventListener('DOMContentLoaded', function() {
  var changePasswordForm = document.getElementById('changePasswordForm');
  var feedbackDiv = document.getElementById('changePasswordFeedback');
  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', function(e) {
      e.preventDefault();
      feedbackDiv.innerHTML = '';
      var formData = new FormData(changePasswordForm);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '', true);
      xhr.onload = function() {
        try {
          var res = JSON.parse(xhr.responseText);
          if (res.success) {
            feedbackDiv.innerHTML = '<div class="alert alert-success">' + res.message + '</div>';
            changePasswordForm.reset();
            setTimeout(function() {
              var modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
              if (modal) modal.hide();
            }, 1200);
          } else {
            feedbackDiv.innerHTML = '<div class="alert alert-danger">' + res.message + '</div>';
          }
        } catch (err) {
          feedbackDiv.innerHTML = '<div class="alert alert-danger">Unexpected error. Please try again.</div>';
        }
      };
      formData.append('change_password', '1');
      xhr.send(formData);
    });
  }
});

// Export PDF Confirmation Modal logic
// This triggers the modal when the export PDF button is clicked
// (moved from inline script in po_materials.php)
