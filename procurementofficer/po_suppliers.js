// JS for po_suppliers.php
// All code moved from inline <script> tags

// Sidebar toggle
var el = document.getElementById("wrapper");
var toggleButton = document.getElementById("menu-toggle");
if (toggleButton) {
    toggleButton.onclick = function () {
        el.classList.toggle("toggled");
    };
}

// Edit Supplier Modal fill (vanilla JS)
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.edit-supplier-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.getElementById('edit_supplier_id').value = this.getAttribute('data-id');
      document.getElementById('edit_supplier_name').value = this.getAttribute('data-name');
      document.getElementById('edit_contact_person').value = this.getAttribute('data-person');
      document.getElementById('edit_contact_number').value = this.getAttribute('data-number');
      document.getElementById('edit_email').value = this.getAttribute('data-email');
      document.getElementById('edit_address').value = this.getAttribute('data-address');
      document.getElementById('edit_status').value = this.getAttribute('data-status');
      var modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
      modal.show();
    });
  });

  // Delete Supplier Modal logic
  document.querySelectorAll('.delete-supplier-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var supplierId = this.getAttribute('data-id');
      var supplierName = this.getAttribute('data-name');
      document.getElementById('supplierName').textContent = supplierName;
      var confirmDelete = document.getElementById('confirmDelete');
      // Set correct delete link
      confirmDelete.setAttribute('href', 'delete_supplier.php?id=' + supplierId);
      var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
      modal.show();
    });
  });
});

// Feedback Modal logic (like employee_list.php)
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
// Search debounce (vanilla JS)
document.addEventListener('DOMContentLoaded', function() {
  var searchInput = document.getElementById('searchInput');
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
});
document.addEventListener('DOMContentLoaded', function() {
  var feedbackModalEl = document.getElementById('feedbackModal');
  if (feedbackModalEl) {
    feedbackModalEl.addEventListener('hidden.bs.modal', function () {
      // Move focus to the Add Supplier button if available, else to body
      var addBtn = document.querySelector('.btn-success[data-bs-target="#addSupplierModal"]');
      if (addBtn) {
        addBtn.focus();
      } else {
        document.body.focus();
      }
    });
  }
});
// --- Supplier Modal Validation ---
function filterSupplierNameInput(e) {
  let value = e.target.value;
  // Allow letters, numbers, spaces, . , - & ()
  value = value.replace(/[^A-Za-z0-9 .,'\-()]/g, '');
  if (value.length > 50) value = value.slice(0, 50);
  e.target.value = value;
}
function filterContactPersonInput(e) {
  let value = e.target.value;
  value = value.replace(/[^A-Za-z ]+/g, '');
  if (value.length > 50) value = value.slice(0, 50);
  e.target.value = value;
}
function filterContactNumberInput(e) {
  let value = e.target.value.replace(/\D/g, '');
  if (value.length > 11) value = value.slice(0, 11);
  e.target.value = value;
}
function filterEmailInput(e) {
  let value = e.target.value;
  if (value.length > 50) value = value.slice(0, 50);
  e.target.value = value;
}
function filterAddressInput(e) {
  let value = e.target.value;
  if (value.length > 100) value = value.slice(0, 100);
  e.target.value = value;
}
// Add Modal
var addForm = document.querySelector('#addSupplierModal form');
if (addForm) {
  var addName = addForm.querySelector('input[name="supplier_name"]');
  var addPerson = addForm.querySelector('input[name="contact_person"]');
  var addNumber = addForm.querySelector('input[name="contact_number"]');
  var addEmail = addForm.querySelector('input[name="email"]');
  var addAddress = addForm.querySelector('textarea[name="address"]');
  var addStatus = addForm.querySelector('select[name="status"]');
  if (addName) addName.addEventListener('input', filterSupplierNameInput);
  if (addPerson) addPerson.addEventListener('input', filterContactPersonInput);
  if (addNumber) addNumber.addEventListener('input', filterContactNumberInput);
  if (addEmail) addEmail.addEventListener('input', filterEmailInput);
  if (addAddress) addAddress.addEventListener('input', filterAddressInput);
  addForm.addEventListener('submit', function(e) {
    // Supplier Name: required, max 50, allowed chars
    if (!addName.value.trim()) {
      addName.setCustomValidity('Supplier Name is required.');
      addName.reportValidity();
      e.preventDefault(); return;
    } else if (addName.value.length > 50) {
      addName.setCustomValidity('Supplier Name must be at most 50 characters.');
      addName.reportValidity();
      e.preventDefault(); return;
    } else {
      addName.setCustomValidity('');
    }
    // Contact Person: optional, only letters/spaces, max 50
    if (addPerson.value && !/^[A-Za-z ]+$/.test(addPerson.value)) {
      addPerson.setCustomValidity('Contact Person must only contain letters and spaces.');
      addPerson.reportValidity();
      e.preventDefault(); return;
    } else if (addPerson.value.length > 50) {
      addPerson.setCustomValidity('Contact Person must be at most 50 characters.');
      addPerson.reportValidity();
      e.preventDefault(); return;
    } else {
      addPerson.setCustomValidity('');
    }
    // Contact Number: optional, if filled must be 11 digits and start with 09
    if (addNumber.value && !/^09\d{9}$/.test(addNumber.value)) {
      addNumber.setCustomValidity('Contact Number must start with 09 and be exactly 11 digits.');
      addNumber.reportValidity();
      e.preventDefault(); return;
    } else {
      addNumber.setCustomValidity('');
    }
    // Email: optional, if filled must be valid
    if (addEmail.value && !/^([A-Za-z0-9._%+-]+)@([A-Za-z0-9.-]+)\.([A-Za-z]{2,})$/.test(addEmail.value)) {
      addEmail.setCustomValidity('Email must be a valid email address.');
      addEmail.reportValidity();
      e.preventDefault(); return;
    } else {
      addEmail.setCustomValidity('');
    }
    // Address: max 100
    if (addAddress.value.length > 100) {
      addAddress.setCustomValidity('Address must be at most 100 characters.');
      addAddress.reportValidity();
      e.preventDefault(); return;
    } else {
      addAddress.setCustomValidity('');
    }
    // Status: required
    if (!addStatus.value) {
      addStatus.setCustomValidity('Status is required.');
      addStatus.reportValidity();
      e.preventDefault(); return;
    } else {
      addStatus.setCustomValidity('');
    }
  });
  [addName, addPerson, addNumber, addEmail, addAddress, addStatus].forEach(function(input) {
    if (input) input.addEventListener('input', function() { input.setCustomValidity(''); });
  });
}
// Edit Modal
var editForm = document.querySelector('#editSupplierModal form');
if (editForm) {
  var editName = editForm.querySelector('input[name="supplier_name"]');
  var editPerson = editForm.querySelector('input[name="contact_person"]');
  var editNumber = editForm.querySelector('input[name="contact_number"]');
  var editEmail = editForm.querySelector('input[name="email"]');
  var editAddress = editForm.querySelector('textarea[name="address"]');
  var editStatus = editForm.querySelector('select[name="status"]');
  if (editName) editName.addEventListener('input', filterSupplierNameInput);
  if (editPerson) editPerson.addEventListener('input', filterContactPersonInput);
  if (editNumber) editNumber.addEventListener('input', filterContactNumberInput);
  if (editEmail) editEmail.addEventListener('input', filterEmailInput);
  if (editAddress) editAddress.addEventListener('input', filterAddressInput);
  editForm.addEventListener('submit', function(e) {
    // Supplier Name: required, max 50, allowed chars
    if (!editName.value.trim()) {
      editName.setCustomValidity('Supplier Name is required.');
      editName.reportValidity();
      e.preventDefault(); return;
    } else if (editName.value.length > 50) {
      editName.setCustomValidity('Supplier Name must be at most 50 characters.');
      editName.reportValidity();
      e.preventDefault(); return;
    } else {
      editName.setCustomValidity('');
    }
    // Contact Person: optional, only letters/spaces, max 50
    if (editPerson.value && !/^[A-Za-z ]+$/.test(editPerson.value)) {
      editPerson.setCustomValidity('Contact Person must only contain letters and spaces.');
      editPerson.reportValidity();
      e.preventDefault(); return;
    } else if (editPerson.value.length > 50) {
      editPerson.setCustomValidity('Contact Person must be at most 50 characters.');
      editPerson.reportValidity();
      e.preventDefault(); return;
    } else {
      editPerson.setCustomValidity('');
    }
    // Contact Number: optional, if filled must be 11 digits and start with 09
    if (editNumber.value && !/^09\d{9}$/.test(editNumber.value)) {
      editNumber.setCustomValidity('Contact Number must start with 09 and be exactly 11 digits.');
      editNumber.reportValidity();
      e.preventDefault(); return;
    } else {
      editNumber.setCustomValidity('');
    }
    // Email: optional, if filled must be valid
    if (editEmail.value && !/^([A-Za-z0-9._%+-]+)@([A-Za-z0-9.-]+)\.([A-Za-z]{2,})$/.test(editEmail.value)) {
      editEmail.setCustomValidity('Email must be a valid email address.');
      editEmail.reportValidity();
      e.preventDefault(); return;
    } else {
      editEmail.setCustomValidity('');
    }
    // Address: max 100
    if (editAddress.value.length > 100) {
      editAddress.setCustomValidity('Address must be at most 100 characters.');
      editAddress.reportValidity();
      e.preventDefault(); return;
    } else {
      editAddress.setCustomValidity('');
    }
    // Status: required
    if (!editStatus.value) {
      editStatus.setCustomValidity('Status is required.');
      editStatus.reportValidity();
      e.preventDefault(); return;
    } else {
      editStatus.setCustomValidity('');
    }
  });
  [editName, editPerson, editNumber, editEmail, editAddress, editStatus].forEach(function(input) {
    if (input) input.addEventListener('input', function() { input.setCustomValidity(''); });
  });
}
// --- Contact Number and Email Auto-fill for Add Modal ---
document.addEventListener('DOMContentLoaded', function() {
  var addNumber = document.querySelector('#addSupplierModal input[name="contact_number"]');
  var addEmail = document.querySelector('#addSupplierModal input[name="email"]');
  var addSupplierModal = document.getElementById('addSupplierModal');
  if (addSupplierModal) {
    addSupplierModal.addEventListener('show.bs.modal', function() {
      if (addNumber && !addNumber.value) addNumber.value = '09';
      if (addEmail && !addEmail.value) addEmail.value = '@gmail.com';
    });
  }
  if (addNumber) {
    addNumber.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (!value.startsWith('09')) value = '09' + value.replace(/^0+/, '').replace(/^9+/, '');
      if (value.length > 11) value = value.slice(0, 11);
      e.target.value = value;
    });
    addNumber.addEventListener('focus', function(e) {
      if (!e.target.value.startsWith('09')) e.target.value = '09';
    });
  }
  if (addEmail) {
    addEmail.addEventListener('input', function(e) {
      let value = e.target.value;
      let atGmail = value.indexOf('@gmail.com');
      if (atGmail !== -1) value = value.substring(0, atGmail);
      value = value.replace(/[^A-Za-z0-9._-]/g, '');
      e.target.value = value + '@gmail.com';
    });
    addEmail.addEventListener('focus', function(e) {
      let value = e.target.value;
      if (!value.endsWith('@gmail.com')) {
        value = value.split('@')[0];
        e.target.value = value + '@gmail.com';
      }
    });
  }
  // --- Contact Number and Email Auto-fill for Edit Modal ---
  var editNumber = document.querySelector('#editSupplierModal input[name="contact_number"]');
  var editEmail = document.querySelector('#editSupplierModal input[name="email"]');
  var editSupplierModal = document.getElementById('editSupplierModal');
  if (editSupplierModal) {
    editSupplierModal.addEventListener('show.bs.modal', function() {
      if (editNumber && !editNumber.value) editNumber.value = '09';
      if (editEmail && !editEmail.value) editEmail.value = '@gmail.com';
    });
  }
  if (editNumber) {
    editNumber.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (!value.startsWith('09')) value = '09' + value.replace(/^0+/, '').replace(/^9+/, '');
      if (value.length > 11) value = value.slice(0, 11);
      e.target.value = value;
    });
    editNumber.addEventListener('focus', function(e) {
      if (!e.target.value.startsWith('09')) e.target.value = '09';
    });
  }
  if (editEmail) {
    editEmail.addEventListener('input', function(e) {
      let value = e.target.value;
      let atGmail = value.indexOf('@gmail.com');
      if (atGmail !== -1) value = value.substring(0, atGmail);
      value = value.replace(/[^A-Za-z0-9._-]/g, '');
      e.target.value = value + '@gmail.com';
    });
    editEmail.addEventListener('focus', function(e) {
      let value = e.target.value;
      if (!value.endsWith('@gmail.com')) {
        value = value.split('@')[0];
        e.target.value = value + '@gmail.com';
      }
    });
  }
});
// --- Philippines Address Dropdowns ---
async function loadPhilippinesJSON() {
  const response = await fetch('philippines.json');
  return await response.json();
}
function setDropdownOptions(select, options, placeholder) {
  select.innerHTML = `<option value="${placeholder}">${placeholder}</option>`;
  options.forEach(opt => {
    const option = document.createElement('option');
    option.value = opt;
    option.textContent = opt;
    select.appendChild(option);
  });
}
function setDropdownDisabled(select, disabled) {
  select.disabled = disabled;
  if (disabled) select.value = '';
}
function updateAddressHidden(region, province, city, barangay, hiddenInput) {
  if (region && province && city && barangay) {
    hiddenInput.value = `${barangay}, ${city}, ${province}, ${region}`;
  } else {
    hiddenInput.value = '';
  }
}
document.addEventListener('DOMContentLoaded', function() {
  loadPhilippinesJSON().then(data => {
    // --- Add Modal ---
    const addRegion = document.getElementById('add_region');
    const addProvince = document.getElementById('add_province');
    const addCity = document.getElementById('add_city');
    const addBarangay = document.getElementById('add_barangay');
    const addAddressHidden = document.getElementById('add_address_hidden');
    // Populate regions
    setDropdownOptions(addRegion, Object.values(data).map(r => r.region_name), 'Select Region');
    addRegion.addEventListener('change', function() {
      setDropdownDisabled(addProvince, true);
      setDropdownDisabled(addCity, true);
      setDropdownDisabled(addBarangay, true);
      addProvince.innerHTML = '<option value="">Select Province</option>';
      addCity.innerHTML = '<option value="">Select City/Municipality</option>';
      addBarangay.innerHTML = '<option value="">Select Barangay</option>';
      if (!this.value) return;
      // Find region code
      let regionCode = Object.keys(data).find(code => data[code].region_name === this.value);
      let provinces = Object.keys(data[regionCode].province_list);
      setDropdownOptions(addProvince, provinces, 'Select Province');
      setDropdownDisabled(addProvince, false);
    });
    addProvince.addEventListener('change', function() {
      setDropdownDisabled(addCity, true);
      setDropdownDisabled(addBarangay, true);
      addCity.innerHTML = '<option value="">Select City/Municipality</option>';
      addBarangay.innerHTML = '<option value="">Select Barangay</option>';
      if (!this.value || !addRegion.value) return;
      let regionCode = Object.keys(data).find(code => data[code].region_name === addRegion.value);
      let cities = Object.keys(data[regionCode].province_list[this.value].municipality_list);
      setDropdownOptions(addCity, cities, 'Select City/Municipality');
      setDropdownDisabled(addCity, false);
    });
    addCity.addEventListener('change', function() {
      setDropdownDisabled(addBarangay, true);
      addBarangay.innerHTML = '<option value="">Select Barangay</option>';
      if (!this.value || !addRegion.value || !addProvince.value) return;
      let regionCode = Object.keys(data).find(code => data[code].region_name === addRegion.value);
      let barangays = data[regionCode].province_list[addProvince.value].municipality_list[this.value].barangay_list;
      setDropdownOptions(addBarangay, barangays, 'Select Barangay');
      setDropdownDisabled(addBarangay, false);
    });
    [addRegion, addProvince, addCity, addBarangay].forEach(sel => {
      sel.addEventListener('change', function() {
        updateAddressHidden(addRegion.value, addProvince.value, addCity.value, addBarangay.value, addAddressHidden);
      });
    });
    // --- Edit Modal ---
    const editRegion = document.getElementById('edit_region');
    const editProvince = document.getElementById('edit_province');
    const editCity = document.getElementById('edit_city');
    const editBarangay = document.getElementById('edit_barangay');
    const editAddressHidden = document.getElementById('edit_address_hidden');
    setDropdownOptions(editRegion, Object.values(data).map(r => r.region_name), 'Select Region');
    editRegion.addEventListener('change', function() {
      setDropdownDisabled(editProvince, true);
      setDropdownDisabled(editCity, true);
      setDropdownDisabled(editBarangay, true);
      editProvince.innerHTML = '<option value="">Select Province</option>';
      editCity.innerHTML = '<option value="">Select City/Municipality</option>';
      editBarangay.innerHTML = '<option value="">Select Barangay</option>';
      if (!this.value) return;
      let regionCode = Object.keys(data).find(code => data[code].region_name === this.value);
      let provinces = Object.keys(data[regionCode].province_list);
      setDropdownOptions(editProvince, provinces, 'Select Province');
      setDropdownDisabled(editProvince, false);
    });
    editProvince.addEventListener('change', function() {
      setDropdownDisabled(editCity, true);
      setDropdownDisabled(editBarangay, true);
      editCity.innerHTML = '<option value="">Select City/Municipality</option>';
      editBarangay.innerHTML = '<option value="">Select Barangay</option>';
      if (!this.value || !editRegion.value) return;
      let regionCode = Object.keys(data).find(code => data[code].region_name === editRegion.value);
      let cities = Object.keys(data[regionCode].province_list[this.value].municipality_list);
      setDropdownOptions(editCity, cities, 'Select City/Municipality');
      setDropdownDisabled(editCity, false);
    });
    editCity.addEventListener('change', function() {
      setDropdownDisabled(editBarangay, true);
      editBarangay.innerHTML = '<option value="">Select Barangay</option>';
      if (!this.value || !editRegion.value || !editProvince.value) return;
      let regionCode = Object.keys(data).find(code => data[code].region_name === editRegion.value);
      let barangays = data[regionCode].province_list[editProvince.value].municipality_list[this.value].barangay_list;
      setDropdownOptions(editBarangay, barangays, 'Select Barangay');
      setDropdownDisabled(editBarangay, false);
    });
    [editRegion, editProvince, editCity, editBarangay].forEach(sel => {
      sel.addEventListener('change', function() {
        updateAddressHidden(editRegion.value, editProvince.value, editCity.value, editBarangay.value, editAddressHidden);
      });
    });
  });
});
// Show feedback modal for add, edit, delete actions
(function() {
  var params = new URLSearchParams(window.location.search);
  if (params.get('success') === '1') {
    showFeedbackModal(true, 'Supplier added successfully!', '', 'success');
  } else if (params.get('updated') === '1') {
    showFeedbackModal(true, 'Supplier updated successfully!', '', 'updated');
  } else if (params.get('deleted') === '1') {
    showFeedbackModal(true, 'Supplier deleted successfully!', '', 'deleted');
  }
})(); 