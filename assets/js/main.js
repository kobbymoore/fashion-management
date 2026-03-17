// ============================================================
//  Fashion Studio GH – Main JavaScript
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

  // ─── Sidebar Toggle (mobile) ─────────────────────────────
  const sidebarToggle  = document.getElementById('sidebarToggle');
  const sidebar        = document.getElementById('mainSidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  function openSidebar() {
    sidebar?.classList.add('open');
    sidebarOverlay?.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar?.classList.remove('open');
    sidebarOverlay?.classList.remove('active');
    document.body.style.overflow = '';
  }

  sidebarToggle?.addEventListener('click', openSidebar);
  sidebarOverlay?.addEventListener('click', closeSidebar);

  // ─── Auto-dismiss Alerts ─────────────────────────────────
  document.querySelectorAll('.alert.auto-dismiss').forEach(el => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert.close();
    }, 4000);
  });

  // ─── Live Table Search ───────────────────────────────────
  const searchInputs = document.querySelectorAll('[data-search-table]');
  searchInputs.forEach(input => {
    const tableId  = input.getAttribute('data-search-table');
    const table    = document.getElementById(tableId);
    const rows     = table?.querySelectorAll('tbody tr');
    if (!rows) return;
    input.addEventListener('input', function () {
      const q = this.value.toLowerCase().trim();
      rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  });

  // ─── Measurement Form Validation ─────────────────────────
  const measureForm = document.getElementById('measurementForm');
  measureForm?.addEventListener('submit', function (e) {
    let valid = true;
    const numFields = this.querySelectorAll('input[data-measure]');
    numFields.forEach(f => {
      const val = parseFloat(f.value);
      const min = parseFloat(f.dataset.min || 0);
      const max = parseFloat(f.dataset.max || 999);
      f.classList.remove('is-invalid', 'is-valid');
      if (f.value && (isNaN(val) || val < min || val > max)) {
        f.classList.add('is-invalid');
        valid = false;
      } else if (f.value) {
        f.classList.add('is-valid');
      }
    });
    if (!valid) {
      e.preventDefault();
      showToast('Please check measurement values – they must be realistic numbers.', 'danger');
    }
  });

  // ─── Order Form: Price Estimate ──────────────────────────
  const styleSelect  = document.getElementById('style_id');
  const qtyInput     = document.getElementById('quantity');
  const priceDisplay = document.getElementById('priceEstimate');
  const priceData    = window.stylePrices || {};

  function updatePrice() {
    if (!styleSelect || !qtyInput || !priceDisplay) return;
    const price = parseFloat(priceData[styleSelect.value] || 0);
    const qty   = parseInt(qtyInput.value || 1);
    const total = price * qty;
    priceDisplay.textContent = 'GH₵ ' + total.toFixed(2);
    priceDisplay.closest('.price-estimate-box')?.classList.toggle('d-none', total === 0);
  }
  styleSelect?.addEventListener('change', updatePrice);
  qtyInput?.addEventListener('input', updatePrice);

  // ─── Password Strength ───────────────────────────────────
  const pwInput  = document.getElementById('password');
  const strengthBar = document.getElementById('passwordStrength');

  pwInput?.addEventListener('input', function () {
    if (!strengthBar) return;
    const pw = this.value;
    let score = 0;
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^a-zA-Z0-9]/.test(pw)) score++;
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    const colors = ['', 'danger', 'warning', 'info', 'success'];
    strengthBar.className = 'progress-bar bg-' + (colors[score] || 'secondary');
    strengthBar.style.width = (score * 25) + '%';
    strengthBar.textContent = labels[score] || '';
  });

  // ─── Confirm Delete ───────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function (e) {
      if (!confirm(this.dataset.confirm || 'Are you sure?')) {
        e.preventDefault();
      }
    });
  });

  // ─── Toast Helper ─────────────────────────────────────────
  window.showToast = function (message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toastContainer';
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      container.style.zIndex = 9999;
      document.body.appendChild(container);
    }
    const icons = { success:'check-circle-fill', danger:'exclamation-triangle-fill', warning:'exclamation-circle-fill', info:'info-circle-fill' };
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type} border-0 show`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2">
          <i class="bi bi-${icons[type] || 'info-circle-fill'}"></i>
          ${message}
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4500);
  };

  // ─── Chart helpers (Chart.js) ─────────────────────────────
  window.makeLineChart = function (canvasId, labels, data, label) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label,
          data,
          borderColor: '#e91e8c',
          backgroundColor: 'rgba(233,30,140,.08)',
          tension: .4,
          fill: true,
          pointBackgroundColor: '#e91e8c',
          pointRadius: 4,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, grid: { color: '#f0e6ec' } }, x: { grid: { display: false } } }
      }
    });
  };

  window.makeBarChart = function (canvasId, labels, data, label) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label,
          data,
          backgroundColor: 'rgba(233,30,140,.7)',
          borderRadius: 6,
          borderColor: '#e91e8c',
          borderWidth: 1,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, grid: { color: '#f0e6ec' } }, x: { grid: { display: false } } }
      }
    });
  };

  window.makeDoughnutChart = function(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{ data, backgroundColor: ['#e91e8c','#c9a96e','#3b82f6','#10b981','#ef4444'], borderWidth: 0 }]
      },
      options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } } }, cutout: '65%' }
    });
  };

  // ─── Smooth scroll for anchor links ─────────────────────
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function (e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  });

  // ─── Animate Stat Numbers ────────────────────────────────
  document.querySelectorAll('[data-count]').forEach(el => {
    const target = parseFloat(el.dataset.count);
    const prefix = el.dataset.prefix || '';
    const suffix = el.dataset.suffix || '';
    let start = 0;
    const duration = 1200;
    const step = target / (duration / 16);
    const timer = setInterval(() => {
      start += step;
      if (start >= target) { start = target; clearInterval(timer); }
      el.textContent = prefix + (Number.isInteger(target) ? Math.floor(start) : start.toFixed(2)) + suffix;
    }, 16);
  });

});
