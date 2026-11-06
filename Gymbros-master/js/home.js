/**
 * BoardingHub Home Page Client-Side Logic
 * Listing filters & sorting, property details dialog and reservation dialog.
 */

const AMENITY_FILTERS = {
  wifi: { label: 'Fast WiFi', terms: ['wifi', 'wi-fi', 'internet', 'fiber'] },
  aircon: { label: 'Aircon', terms: ['air con', 'aircon', 'inverter'] },
  ownbath: { label: 'Own CR', terms: ['private bathroom', 'private t&b', 'private cr', 'own cr', 'en-suite', 'ensuite'] }
};

const LOCATION_STOPWORDS = new Set(['near', 'the', 'in', 'at', 'to', 'of', 'and', 'by']);

const LISTING_SORTERS = {
  recommended: (a, b) => a.index - b.index,
  'price-asc': (a, b) => a.minPrice - b.minPrice || a.index - b.index,
  'price-desc': (a, b) => b.minPrice - a.minPrice || a.index - b.index,
  rating: (a, b) => b.rating - a.rating || a.index - b.index
};

const listingState = { tab: 'all', location: '', roomType: '', budget: '', amenity: '', sort: 'recommended' };
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
let listings = [];
let activeListing = null;

document.addEventListener('DOMContentLoaded', () => {
  const grid = document.getElementById('houses-grid');
  if (!grid) return;

  listings = [...grid.querySelectorAll('.house-card')].map(readListing);
  initListingControls();
  initDialogs();
  initReservationForm();
});

// ---------- Listings ----------

function readListing(card, index) {
  const d = card.dataset;
  let amenities = [];
  try {
    amenities = JSON.parse(d.amenities || '[]');
  } catch (e) {}
  if (!Array.isArray(amenities)) amenities = [];

  return {
    card,
    index,
    haystack: [d.name, d.address, d.city, d.proximity].join(' ').toLowerCase(),
    roomTypes: (d.roomtypes || '').toLowerCase(),
    gender: (d.gender || '').toLowerCase(),
    minPrice: parseFloat(d.minprice) || 0,
    maxPrice: parseFloat(d.maxprice) || 0,
    rating: parseFloat(d.rating) || 0,
    amenities: amenities.map(String),
    amenityText: amenities.join(' ').toLowerCase()
  };
}

function matchesTab(listing) {
  switch (listingState.tab) {
    case 'all': return true;
    case 'budget': return listing.minPrice <= 3500;
    case 'female': return listing.gender.includes('female');
    default: return listing.roomTypes.includes(listingState.tab.toLowerCase());
  }
}

function matchesLocation(listing) {
  const words = listingState.location.toLowerCase().split(/\s+/).filter(w => w && !LOCATION_STOPWORDS.has(w));
  return words.every(w => listing.haystack.includes(w));
}

function matchesBudget(listing) {
  const budget = listingState.budget;
  if (!budget) return true;
  // "7000+" means rooms at or above that rate; the rest are monthly ceilings
  return budget.endsWith('+') ? listing.maxPrice >= parseFloat(budget) : listing.minPrice <= parseFloat(budget);
}

function matchesListing(listing) {
  return matchesTab(listing)
    && matchesLocation(listing)
    && (!listingState.roomType || listing.roomTypes.includes(listingState.roomType.toLowerCase()))
    && matchesBudget(listing)
    && (!listingState.amenity || AMENITY_FILTERS[listingState.amenity].terms.some(t => listing.amenityText.includes(t)));
}

function renderListings({ animate = false } = {}) {
  const update = () => {
    const grid = document.getElementById('houses-grid');
    const sorted = [...listings].sort(LISTING_SORTERS[listingState.sort] || LISTING_SORTERS.recommended);
    let visibleCount = 0;

    sorted.forEach(listing => {
      const visible = matchesListing(listing);
      listing.card.hidden = !visible;
      if (visible) visibleCount++;
      grid.appendChild(listing.card);
    });

    updateListingSummary(visibleCount);
  };

  // Only animate in-place changes; a transition running while the page scrolls looks broken
  if (animate && document.startViewTransition && !reducedMotion.matches) {
    document.startViewTransition(update);
  } else {
    update();
  }
}

function updateListingSummary(visibleCount) {
  const total = listings.length;
  const noun = total === 1 ? 'boarding house' : 'boarding houses';
  const count = document.createElement('strong');
  count.textContent = visibleCount;

  const summary = document.getElementById('results-count');
  if (visibleCount === total) {
    summary.replaceChildren(count, ` ${noun}`);
  } else {
    summary.replaceChildren('Showing ', count, ` of ${total} ${noun}`);
  }

  const chips = getActiveFilterChips();
  document.getElementById('active-filters').replaceChildren(...chips.map(createFilterChip));
  document.querySelector('.btn-clear-filters').hidden = chips.length === 0 && listingState.tab === 'all';
  document.getElementById('listings-empty').hidden = visibleCount > 0;

  document.querySelectorAll('.filter-tab').forEach(tab => {
    const isActive = tab.dataset.tab === listingState.tab;
    tab.classList.toggle('active', isActive);
    tab.setAttribute('aria-pressed', isActive);
  });
}

function getActiveFilterChips() {
  const chips = [];
  if (listingState.location) chips.push({ key: 'location', icon: 'fa-map-marker-alt', label: listingState.location });
  if (listingState.roomType) chips.push({ key: 'roomType', icon: 'fa-door-open', label: selectedOptionText('filter-room-type') });
  if (listingState.budget) chips.push({ key: 'budget', icon: 'fa-tag', label: selectedOptionText('filter-budget') });
  if (listingState.amenity) chips.push({ key: 'amenity', icon: 'fa-check', label: AMENITY_FILTERS[listingState.amenity].label });
  return chips;
}

function createFilterChip(chip) {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'filter-chip';
  button.dataset.clear = chip.key;
  button.setAttribute('aria-label', `Remove filter: ${chip.label}`);

  const label = document.createElement('span');
  label.textContent = chip.label;
  const remove = document.createElement('span');
  remove.className = 'chip-remove';
  remove.append(createIcon('fa-xmark'));

  button.append(createIcon(chip.icon), label, remove);
  return button;
}

function selectedOptionText(selectId) {
  const select = document.getElementById(selectId);
  return select.options[select.selectedIndex]?.text || select.value;
}

function syncHeroForm() {
  const form = document.getElementById('hero-search');
  form.elements.location.value = listingState.location;
  form.elements.room_type.value = listingState.roomType;
  form.elements.budget.value = listingState.budget;
}

function clearFilters() {
  Object.assign(listingState, { tab: 'all', location: '', roomType: '', budget: '', amenity: '' });
  syncHeroForm();
  renderListings({ animate: true });
}

function scrollToListings() {
  if (document.activeElement instanceof HTMLInputElement) document.activeElement.blur();
  // Measured by hand: the tab row is a horizontal scroller on phones, where scroll-margin is ignored
  const tabs = document.getElementById('listing-filters');
  const headerHeight = document.querySelector('body > header')?.offsetHeight || 0;
  window.scrollTo({ top: tabs.getBoundingClientRect().top + window.scrollY - headerHeight - 16 });
}

function initListingControls() {
  const heroForm = document.getElementById('hero-search');
  heroForm.addEventListener('submit', e => {
    e.preventDefault();
    listingState.location = heroForm.elements.location.value.trim();
    listingState.roomType = heroForm.elements.room_type.value;
    listingState.budget = heroForm.elements.budget.value;
    listingState.tab = 'all';
    renderListings();
    scrollToListings();
  });

  document.querySelectorAll('.quick-pill').forEach(pill => {
    pill.addEventListener('click', () => {
      if (pill.dataset.quick === 'female') {
        listingState.tab = 'female';
      } else {
        listingState.amenity = pill.dataset.quick;
      }
      renderListings();
      scrollToListings();
    });
  });

  document.getElementById('listing-filters').addEventListener('click', e => {
    const tab = e.target.closest('.filter-tab');
    if (!tab) return;
    listingState.tab = tab.dataset.tab;
    renderListings({ animate: true });
  });

  // Room type cards and footer links jump to the listings with a tab applied
  document.querySelectorAll('[data-show-tab]').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      listingState.tab = link.dataset.showTab;
      renderListings();
      scrollToListings();
    });
  });

  document.getElementById('sort-listings').addEventListener('change', e => {
    listingState.sort = e.target.value;
    renderListings({ animate: true });
  });

  document.querySelectorAll('[data-action="clear-filters"]').forEach(btn => btn.addEventListener('click', clearFilters));

  document.getElementById('active-filters').addEventListener('click', e => {
    const chip = e.target.closest('.filter-chip');
    if (!chip) return;
    listingState[chip.dataset.clear] = '';
    syncHeroForm();
    renderListings({ animate: true });
  });

  document.getElementById('houses-grid').addEventListener('click', e => {
    const trigger = e.target.closest('[data-action]');
    if (!trigger) return;
    const listing = listings.find(l => l.card === trigger.closest('.house-card'));
    if (!listing) return;

    if (trigger.dataset.action === 'details') openHouseDetails(listing);
    if (trigger.dataset.action === 'book') openReservation(listing);
  });
}

// ---------- Dialogs ----------

function initDialogs() {
  const houseDialog = document.getElementById('house-dialog');
  const reserveDialog = document.getElementById('reserve-dialog');

  houseDialog.querySelector('[data-action="book-from-details"]')?.addEventListener('click', () => {
    houseDialog.close();
    openReservation(activeListing);
  });
  reserveDialog.querySelector('[data-action="close-reserve"]').addEventListener('click', () => reserveDialog.close());

  // closedby="any" adds click-outside-to-close; Safari needs it done by hand
  if (!('closedBy' in HTMLDialogElement.prototype)) {
    const isOutside = (e, dialog) => {
      const r = dialog.getBoundingClientRect();
      return e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
    };
    document.querySelectorAll('dialog[closedby="any"]').forEach(dialog => {
      let pressedOutside = false;
      dialog.addEventListener('pointerdown', e => { pressedOutside = isOutside(e, dialog); });
      dialog.addEventListener('pointerup', e => {
        if (pressedOutside && isOutside(e, dialog)) dialog.close();
      });
    });
  }
}

function openHouseDetails(listing) {
  const d = listing.card.dataset;
  const dialog = document.getElementById('house-dialog');
  activeListing = listing;

  const img = document.getElementById('hd-img');
  img.src = d.image;
  img.alt = `Photo of ${d.name}`;

  const gender = document.getElementById('hd-gender');
  gender.textContent = d.gender;
  gender.className = 'hd-gender ' + (listing.gender.includes('female') ? 'female' : listing.gender.startsWith('male') ? 'male' : 'coed');

  const rating = document.getElementById('hd-rating');
  if (d.rating === 'New') {
    rating.className = 'hd-rating is-new';
    rating.replaceChildren(createIcon('fa-certificate'), ' New listing');
  } else {
    const reviews = document.createElement('small');
    reviews.textContent = `· ${d.reviews} reviews`;
    rating.className = 'hd-rating';
    rating.replaceChildren(createIcon('fa-star'), ` ${Number(d.rating).toFixed(1)} `, reviews);
  }

  setText('hd-title', d.name);
  setText('hd-address', [d.address, d.city].filter(Boolean).join(', '));
  setText('hd-proximity', d.proximity);
  setText('hd-price', d.price);
  setText('hd-avail', availabilityText(parseInt(d.avail, 10) || 0));
  setText('hd-roomtypes', d.roomtypes);
  setText('hd-desc', d.desc);
  setText('hd-landlord', d.landlord);

  const phone = document.getElementById('hd-phone');
  phone.textContent = d.phone;
  phone.href = 'tel:' + (d.phone || '').replace(/[^\d+]/g, '');
  phone.hidden = !d.phone;

  // Amenities come from landlords, so they are only ever inserted as text
  document.getElementById('hd-amenities').replaceChildren(...listing.amenities.map(amenity => {
    const item = document.createElement('li');
    item.append(createIcon('fa-check'), document.createTextNode(amenity));
    return item;
  }));

  dialog.showModal();
  dialog.querySelector('.hd-body').scrollTop = 0;
}

function openReservation(listing) {
  if (document.body.dataset.auth !== '1') {
    window.location.href = 'login.php';
    return;
  }

  const d = listing.card.dataset;
  const form = document.getElementById('reserve-form');
  activeListing = listing;

  // Notes are written for one house, so start fresh when switching houses
  if (form.elements.boarding_house_id.value !== d.id) form.elements.special_requests.value = '';
  form.elements.boarding_house_id.value = d.id;

  const img = document.getElementById('rd-img');
  img.src = d.image;
  setText('rd-title', d.name);
  setText('rd-price', `${d.price} · ${availabilityText(parseInt(d.avail, 10) || 0)}`);
  showReservationStatus(null);

  document.getElementById('reserve-dialog').showModal();
}

// ---------- Reservation form ----------

function initReservationForm() {
  const form = document.getElementById('reserve-form');
  if (!form) return;

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const submitBtn = document.getElementById('btn-submit-reserve');
    const payload = Object.fromEntries(new FormData(form));
    payload.action = 'reserve_boarding_house';

    setSubmitState(submitBtn, 'loading');
    let succeeded = false;

    try {
      const res = await fetch('user_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      succeeded = Boolean(data.success);
      showReservationStatus(succeeded ? 'success' : 'error', data.message || 'Something went wrong. Please try again.');

      if (succeeded) {
        setSubmitState(submitBtn, 'done');
        setTimeout(() => {
          window.location.href = 'dashboard.php#bookings';
        }, 1500);
      }
    } catch (err) {
      showReservationStatus('error', 'Network error. Check your connection and try again.');
    } finally {
      if (!succeeded) setSubmitState(submitBtn, 'idle');
    }
  });
}

function setSubmitState(button, state) {
  const states = {
    idle: { icon: 'fa-paper-plane', label: 'Send request' },
    loading: { icon: 'fa-spinner fa-spin', label: 'Sending...' },
    done: { icon: 'fa-check', label: 'Request sent' }
  };
  button.disabled = state !== 'idle';
  button.querySelector('i').className = 'fas ' + states[state].icon;
  button.querySelector('span').textContent = states[state].label;
}

function showReservationStatus(type, message) {
  const status = document.getElementById('reserve-status');
  if (!type) {
    status.className = 'rd-status';
    status.replaceChildren();
    return;
  }
  status.className = `rd-status is-${type}`;
  status.replaceChildren(createIcon(type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'), document.createTextNode(message));
}

// ---------- Helpers ----------

function availabilityText(rooms) {
  if (rooms === 0) return 'Fully booked';
  return rooms === 1 ? '1 room left' : `${rooms} rooms left`;
}

function setText(id, text) {
  document.getElementById(id).textContent = text || '';
}

function createIcon(name) {
  const icon = document.createElement('i');
  icon.className = 'fas ' + name;
  icon.setAttribute('aria-hidden', 'true');
  return icon;
}
