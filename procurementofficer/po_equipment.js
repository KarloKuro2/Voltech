// Delete confirmation for equipment
// This script shows the delete modal and sets the correct link

document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.delete-equipment-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      // Only preventDefault if the link is # (modal), not if it's a direct link
      if (this.getAttribute('href') === '#') {
        e.preventDefault();
        var eqId = this.getAttribute('data-id');
        var eqName = this.getAttribute('data-name');
        document.getElementById('equipmentName').textContent = eqName;
        var confirmDelete = document.getElementById('confirmDeleteEquipment');
        confirmDelete.setAttribute('href', 'delete_equipment.php?id=' + eqId);
        var modal = new bootstrap.Modal(document.getElementById('deleteEquipmentModal'));
        modal.show();
      }
    });
  });
});

// Export PDF Confirmation Modal logic
// This triggers the modal when the export PDF button is clicked

// Feedback Modal logic for add, edit, delete actions
function showFeedbackModal(success, message, details, action) {
    var icon = document.getElementById('feedbackIcon');
    var title = document.getElementById('feedbackTitle');
    var msg = document.getElementById('feedbackMessage');
    if (success) {
        icon.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745;"></i>';
        title.textContent = 'Success!';
        msg.textContent = message;
    } else {
        icon.innerHTML = '<i class="fas fa-times-circle" style="color:#dc3545;"></i>';
        title.textContent = 'Error!';
        msg.textContent = message;
    }
    var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
    feedbackModal.show();
    window.history.replaceState({}, document.title, window.location.pathname);
}
(function() {
  var params = new URLSearchParams(window.location.search);
  if (params.get('success') === '1') {
    showFeedbackModal(true, 'Equipment added successfully!', '', 'added');
  } else if (params.get('updated') === '1') {
    showFeedbackModal(true, 'Equipment updated successfully!', '', 'updated');
  } else if (params.get('deleted') === '1') {
    showFeedbackModal(true, 'Equipment deleted successfully!', '', 'deleted');
  }
})();
