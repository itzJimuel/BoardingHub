/**
 * BoardingHub User Dashboard Client-Side Logic
 */

document.addEventListener('DOMContentLoaded', () => {
  initTabs();
  initModalCloseHandlers();

  // If URL has hash (e.g. #bookings), switch to it
  if (window.location.hash) {
    const hashTab = window.location.hash.replace('#', 'tab-');
    if (document.getElementById(hashTab)) {
      switchDashboardTab(hashTab);
    }
  }
});

// Toast notification helper
function showToast(message, isSuccess = true) {
  let toast = document.getElementById('dashToast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'dashToast';
    toast.className = 'dash-toast';
    document.body.appendChild(toast);
  }

  const iconClass = isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle';
  const color = isSuccess ? '#10b981' : '#ef4444';
  
  toast.innerHTML = `<i class="fas ${iconClass}" style="color: ${color}; font-size: 18px;"></i> <span>${message}</span>`;
  toast.style.borderColor = color;
  toast.classList.add('show');

  setTimeout(() => {
    toast.classList.remove('show');
  }, 4000);
}

// CSRF Token Helper
function getCSRFToken() {
  const tokenEl = document.getElementById('csrf_token_val');
  return tokenEl ? tokenEl.value : '';
}

// Tab Switching System
function initTabs() {
  const tabBtns = document.querySelectorAll('.dashboard-tab-btn');
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetTab = btn.getAttribute('data-tab');
      switchDashboardTab(targetTab);
    });
  });
}

function switchDashboardTab(tabId) {
  // Update Buttons
  document.querySelectorAll('.dashboard-tab-btn').forEach(btn => {
    if (btn.getAttribute('data-tab') === tabId) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });

  // Update Panes
  document.querySelectorAll('.tab-pane').forEach(pane => {
    if (pane.id === tabId) {
      pane.classList.add('active');
    } else {
      pane.classList.remove('active');
    }
  });

  // Scroll to top of tab smoothly if clicked from somewhere else
  const wrapper = document.querySelector('.dashboard-nav-tabs');
  if (wrapper) {
    wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

// Modal Control
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function initModalCloseHandlers() {
  document.querySelectorAll('.modal-close-btn, .btn-modal-cancel').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const modal = e.target.closest('.modal-overlay');
      if (modal) closeModal(modal.id);
    });
  });

  document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        closeModal(modal.id);
      }
    });
  });
}

// Filter Boarding Houses in Dashboard
function filterDashBoardingHouses() {
  const query = (document.getElementById('dash-bh-search')?.value || '').toLowerCase().trim();
  const cards = document.querySelectorAll('.dash-house-card');
  cards.forEach(c => {
    const name = c.getAttribute('data-name') || '';
    if (!query || name.includes(query)) {
      c.style.display = 'flex';
    } else {
      c.style.display = 'none';
    }
  });
}

// Quick Reservation Modal Trigger
function openReserveModalFromCard(houseId, houseName) {
  document.getElementById('dash-resv-house-id').value = houseId;
  document.getElementById('dash-resv-house-name').textContent = houseName;
  openModal('modal-reserve-house');
}

// Submit Reservation
async function submitDashReservationForm(e) {
  e.preventDefault();
  const form = e.target;
  const submitBtn = form.querySelector('button[type="submit"]');
  const origText = submitBtn.innerHTML;

  const houseId = document.getElementById('dash-resv-house-id').value;
  const moveInDate = document.getElementById('dash-resv-date').value;
  const duration = document.getElementById('dash-resv-duration').value;
  const phone = document.getElementById('dash-resv-phone').value;
  const notes = document.getElementById('dash-resv-notes').value;

  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

  try {
    const res = await fetch('user_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'reserve_boarding_house',
        csrf_token: getCSRFToken(),
        boarding_house_id: houseId,
        target_move_in_date: moveInDate,
        stay_duration_months: duration,
        contact_phone: phone,
        special_requests: notes
      })
    });
    const result = await res.json();
    if (result.success) {
      showToast(result.message, true);
      closeModal('modal-reserve-house');
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(result.message || 'Failed to submit reservation', false);
    }
  } catch (err) {
    showToast('Network error submitting reservation.', false);
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = origText;
  }
}

// Cancel Reservation
async function cancelReservation(resId) {
  if (!confirm('Are you sure you want to cancel this reservation request?')) return;

  try {
    const res = await fetch('user_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'cancel_room_reservation',
        csrf_token: getCSRFToken(),
        reservation_id: resId
      })
    });
    const result = await res.json();
    if (result.success) {
      showToast(result.message, true);
      const row = document.getElementById(`my-resv-row-${resId}`);
      if (row) row.remove();
      setTimeout(() => location.reload(), 800);
    } else {
      showToast(result.message, false);
    }
  } catch (err) {
    showToast('Network error cancelling reservation.', false);
  }
}

// Submit Maintenance Ticket
async function submitDashMaintenanceForm(e) {
  e.preventDefault();
  const form = e.target;
  const submitBtn = form.querySelector('button[type="submit"]');
  const origText = submitBtn.innerHTML;

  const bhName = document.getElementById('maint-bh-name').value;
  const roomNum = document.getElementById('maint-room-num').value;
  const category = document.getElementById('maint-category').value;
  const urgency = document.getElementById('maint-urgency').value;
  const description = document.getElementById('maint-desc').value;

  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

  try {
    const res = await fetch('user_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'submit_maintenance_request',
        csrf_token: getCSRFToken(),
        boarding_house_name: bhName,
        room_number: roomNum,
        category: category,
        urgency: urgency,
        description: description
      })
    });
    const result = await res.json();
    if (result.success) {
      showToast(result.message, true);
      closeModal('modal-submit-maintenance');
      form.reset();
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(result.message || 'Failed to submit ticket', false);
    }
  } catch (err) {
    showToast('Network error submitting ticket.', false);
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = origText;
  }
}

// Submit Profile Update
async function submitProfileForm(e) {
  e.preventDefault();
  const form = e.target;
  const submitBtn = form.querySelector('button[type="submit"]');
  const origText = submitBtn.innerHTML;

  const phone = document.getElementById('prof-phone').value;
  const fitnessGoal = document.getElementById('prof-fitness-goal').value;
  const bio = document.getElementById('prof-bio').value;
  const purok = document.getElementById('prof-purok').value;
  const barangay = document.getElementById('prof-barangay').value;
  const city = document.getElementById('prof-city').value;
  const province = document.getElementById('prof-province').value;
  const zip = document.getElementById('prof-zip').value;

  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  try {
    const res = await fetch('user_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update_profile',
        csrf_token: getCSRFToken(),
        phone_number: phone,
        fitness_goal: fitnessGoal,
        bio: bio,
        purok_street: purok,
        barangay: barangay,
        city_municipality: city,
        province: province,
        zip_code: zip
      })
    });
    const result = await res.json();
    if (result.success) {
      showToast('Profile updated successfully!', true);
      closeModal('modal-edit-profile');
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(result.message || 'Update failed', false);
    }
  } catch (err) {
    showToast('Network error updating profile.', false);
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerHTML = origText;
  }
}
