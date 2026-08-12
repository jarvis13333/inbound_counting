(function () {
  initCountingSession();
  initShipmentCombobox();
})();

function initCountingSession() {
  var form = document.getElementById('count-form');
  var sessionBtn = document.getElementById('counting_session_btn');
  if (!form || !sessionBtn) return;

  var dateInput = document.getElementById('counting_date');
  var startInput = document.getElementById('start_time');
  var endInput = document.getElementById('completion_time');

  function pad(n) {
    return String(n).padStart(2, '0');
  }

  function todayLocal() {
    var d = new Date();
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }

  function timeLocal() {
    var d = new Date();
    return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
  }

  function hasValue(input) {
    return input && String(input.value || '').trim() !== '';
  }

  function updateSessionButton() {
    var hasStart = hasValue(startInput);
    var hasEnd = hasValue(endInput);

    if (hasEnd) {
      sessionBtn.classList.add('cr-stamp-btn-hidden');
      sessionBtn.disabled = true;
      sessionBtn.setAttribute('aria-hidden', 'true');
      return;
    }

    sessionBtn.classList.remove('cr-stamp-btn-hidden');
    sessionBtn.disabled = false;
    sessionBtn.removeAttribute('aria-hidden');

    if (hasStart) {
      sessionBtn.textContent = 'End';
      sessionBtn.classList.add('btn-session-end');
      sessionBtn.classList.remove('btn-session-start');
    } else {
      sessionBtn.textContent = 'Start';
      sessionBtn.classList.add('btn-session-start');
      sessionBtn.classList.remove('btn-session-end');
    }
  }

  function recordStart() {
    if (dateInput) dateInput.value = todayLocal();
    if (startInput) startInput.value = timeLocal();
    updateSessionButton();
  }

  function recordEnd() {
    if (endInput) endInput.value = timeLocal();
    updateSessionButton();
  }

  sessionBtn.addEventListener('click', function () {
    if (hasValue(startInput) && !hasValue(endInput)) {
      recordEnd();
    } else if (!hasValue(startInput)) {
      recordStart();
    }
  });

  [dateInput, startInput, endInput].forEach(function (input) {
    if (!input) return;
    input.addEventListener('change', updateSessionButton);
    input.addEventListener('input', updateSessionButton);
  });

  updateSessionButton();

  form.addEventListener('submit', function (e) {
    if (dateInput && !dateInput.value) {
      e.preventDefault();
      alert('Please enter counting date.');
      return;
    }
    if (startInput && !startInput.value) {
      e.preventDefault();
      alert('Please enter counting start time (use Start button or pick a time).');
      return;
    }
    if (endInput && !endInput.value) {
      e.preventDefault();
      alert('Please enter completion time (use End button or pick a time).');
    }
  });
}

function initShipmentCombobox() {
  var combobox = document.getElementById('admin_shipment_combobox');
  if (!combobox) return;

  var hiddenInput = document.getElementById('admin_shipment_id');
  var trigger = document.getElementById('admin_shipment_trigger');
  var triggerLabel = document.getElementById('admin_shipment_trigger_label');
  var panel = document.getElementById('admin_shipment_panel');
  var searchInput = document.getElementById('admin_shipment_search');
  var list = document.getElementById('admin_shipment_list');
  var emptyHint = document.getElementById('admin_shipment_empty');
  var productDisplay = document.getElementById('product_name_display');
  var cartonDisplay = document.getElementById('total_carton_display');
  var options = list ? Array.prototype.slice.call(list.querySelectorAll('.shipment-combobox-option')) : [];

  function applyFromOption(opt) {
    if (!opt || !hiddenInput) return;
    var value = opt.getAttribute('data-value') || '';
    hiddenInput.value = value;
    if (triggerLabel) {
      triggerLabel.textContent = opt.getAttribute('data-label') || opt.textContent.trim();
    }
    options.forEach(function (item) {
      item.classList.toggle('is-selected', item === opt);
    });
    var product = opt.getAttribute('data-product') || '';
    if (productDisplay) {
      productDisplay.value = product;
    }
    var carton = opt.getAttribute('data-carton') || '';
    if (cartonDisplay) {
      cartonDisplay.value = carton;
    }
  }

  function filterOptions() {
    var q = (searchInput && searchInput.value) ? searchInput.value.trim().toLowerCase() : '';
    var visible = 0;
    options.forEach(function (opt) {
      var text = (opt.getAttribute('data-label') || opt.textContent || '').toLowerCase();
      var match = !q || text.indexOf(q) !== -1;
      opt.hidden = !match;
      if (match) visible++;
    });
    if (emptyHint) {
      emptyHint.hidden = !q || visible > 0;
    }
  }

  function openPanel() {
    if (!panel || !trigger) return;
    panel.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    combobox.classList.add('is-open');
    filterOptions();
    if (searchInput) {
      searchInput.value = '';
      filterOptions();
      searchInput.focus();
    }
  }

  function closePanel() {
    if (!panel || !trigger) return;
    panel.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    combobox.classList.remove('is-open');
    if (searchInput) searchInput.value = '';
    filterOptions();
  }

  function togglePanel() {
    if (panel && panel.hidden) {
      openPanel();
    } else {
      closePanel();
    }
  }

  if (trigger) {
    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      togglePanel();
    });
  }

  if (panel) {
    panel.addEventListener('click', function (e) {
      e.stopPropagation();
    });
  }

  if (searchInput) {
    searchInput.addEventListener('input', filterOptions);
    searchInput.addEventListener('search', filterOptions);
    searchInput.addEventListener('keydown', function (e) {
      e.stopPropagation();
    });
  }

  options.forEach(function (opt) {
    opt.addEventListener('click', function () {
      applyFromOption(opt);
      closePanel();
    });
  });

  document.addEventListener('click', function (e) {
    if (!combobox.contains(e.target)) {
      closePanel();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closePanel();
  });

  var initial = options.find(function (opt) {
    return opt.classList.contains('is-selected');
  });
  if (initial) {
    applyFromOption(initial);
  }

  var form = document.getElementById('count-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      if (!hiddenInput || !hiddenInput.value) {
        e.preventDefault();
        alert('Please select an inbound shipment number.');
      }
    });
  }
}
