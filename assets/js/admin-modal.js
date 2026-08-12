(function () {
  function bindModal(modal) {
    var listUrl = modal.getAttribute('data-list-url') || '';

    function closeModal() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      if (!document.querySelector('.modal-overlay.is-open')) {
        document.body.classList.remove('modal-open');
      }
      if (listUrl) {
        window.location.replace(listUrl);
      }
    }

    modal.querySelectorAll('[data-modal-close]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (el.tagName === 'A') {
          e.preventDefault();
        }
        closeModal();
      });
    });

    modal.addEventListener('click', function (e) {
      if (e.target === modal) {
        closeModal();
      }
    });

    return closeModal;
  }

  var closers = [];
  document.querySelectorAll('.modal-overlay[data-list-url]').forEach(function (modal) {
    if (modal.classList.contains('is-open')) {
      document.body.classList.add('modal-open');
    }
    closers.push(bindModal(modal));
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var openModal = document.querySelector('.modal-overlay.is-open');
    if (!openModal) return;
    var listUrl = openModal.getAttribute('data-list-url') || '';
    openModal.classList.remove('is-open');
    openModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    if (listUrl) {
      window.location.replace(listUrl);
    }
  });
})();
