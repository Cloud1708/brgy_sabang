// Statistics placeholders (simulate fetch – later replace with AJAX)
document.addEventListener('DOMContentLoaded', () => {
  const stats = {
    immunizations: 18,
    mothers: 42,
    children: 120
  };
  const animateCounter = (el, target) => {
    let current = 0;
    const step = Math.max(1, Math.floor(target / 50));
    const interval = setInterval(() => {
      current += step;
      if (current >= target) { current = target; clearInterval(interval); }
      el.textContent = current;
    }, 25);
  };
  ['Immunizations','Mothers','Children'].forEach(k => {
    const el = document.getElementById('stat' + k);
    if (el) animateCounter(el, stats[k.toLowerCase()]);
  });

  // Filter announcements
  const filterButtons = document.querySelectorAll('.filter-controls button');
  const cards = document.querySelectorAll('.announcement-card');
  filterButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      filterButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const f = btn.dataset.filter;
      cards.forEach(c => {
        if (f === 'all' || c.dataset.type === f) {
          c.classList.remove('d-none');
        } else {
          c.classList.add('d-none');
        }
      });
    });
  });

  // Announcement modal
  const modalEl = document.getElementById('announcementModal');
  if (modalEl) {
    const bsModal = new bootstrap.Modal(modalEl);
    document.querySelectorAll('.view-announcement').forEach(btn => {
      btn.addEventListener('click', () => {
        modalEl.querySelector('.announcement-modal-title').textContent = btn.dataset.title;
        modalEl.querySelector('.announcement-modal-date').textContent = btn.dataset.date;
        modalEl.querySelector('.announcement-modal-time').textContent = btn.dataset.time;
        const locEl = modalEl.querySelector('.announcement-modal-location');
        if (btn.dataset.location) {
          locEl.textContent = btn.dataset.location;
          locEl.classList.remove('d-none');
        } else {
          locEl.classList.add('d-none');
        }
        modalEl.querySelector('.announcement-modal-body').textContent = btn.dataset.body;
        bsModal.show();
      });
    });
  }

  // Secret staff login shortcut Alt+L
  document.addEventListener('keydown', (e) => {
    if (e.altKey && e.key.toLowerCase() === 'l') {
      window.location.href = 'staff_login';
    }
  });

  // Feedback form demo
  const feedbackForm = document.getElementById('feedbackForm');
  if (feedbackForm) {
    feedbackForm.addEventListener('submit', e => {
      e.preventDefault();
      feedbackForm.classList.add('was-validated');
      if (!feedbackForm.checkValidity()) return;
      // Simulate send
      setTimeout(() => {
        document.getElementById('feedbackSuccess').classList.remove('d-none');
        feedbackForm.reset();
        feedbackForm.classList.remove('was-validated');
      }, 600);
    });
  }
});