(function () {
  var base = window.BASE_URL || '';
  var TIMEOUT_MS = 5 * 60 * 1000;
  var WARNING_MS = 4 * 60 * 1000;
  var lastActivity = Date.now();
  var warned = false;
  var warningEl = document.getElementById('timeout-warning');

  function resetTimer() {
    lastActivity = Date.now();
    warned = false;
    if (warningEl) warningEl.style.display = 'none';
    pingServer();
  }

  function pingServer() {
    fetch(base + '/api/ping.php', { credentials: 'same-origin' }).catch(function () {});
  }

  var events = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart', 'click', 'input', 'change'];
  events.forEach(function (ev) {
    document.addEventListener(ev, resetTimer, { passive: true });
  });

  setInterval(function () {
    var idle = Date.now() - lastActivity;
    if (idle >= TIMEOUT_MS) {
      window.location.href = base + '/logout.php?timeout=1';
      return;
    }
    if (idle >= WARNING_MS && !warned && warningEl) {
      warned = true;
      warningEl.style.display = 'block';
      warningEl.textContent = 'You will be logged out in 1 minute due to inactivity.';
    }
  }, 10000);

  pingServer();
})();
