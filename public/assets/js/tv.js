/**
 * Barber Turns TV mode poller.
 *
 * Renders a read-only queue driven by the public TV token.
 */

(function () {
  const tvRoot = document.querySelector('.view-tv');
  if (!tvRoot) {
    return;
  }

  document.body.classList.add('layout-tv');

  const token = tvRoot.dataset.token || '';
  const pollMs = parseInt(tvRoot.dataset.poll || '3000', 10);
  const listEl = document.getElementById('tv-queue');
  const alertEl = document.getElementById('tv-alert');
  const shopNameEl = document.getElementById('tv-shop-name');
  const updatedEl = document.getElementById('tv-last-updated');
  const layoutToggle = document.getElementById('tv-layout-toggle');
  const layoutStateEl = layoutToggle ? layoutToggle.querySelector('.tv-toggle__state') : null;

  if (!token) {
    showAlert('Missing TV token. Ask the owner to regenerate a valid link.');
    return;
  }

  let pollTimeout = null;
  let timerInterval = null;
  let lastServerTime = null;
  let lastSyncTimestamp = 0;
  let halted = false;
  let isVertical = false;

  setVerticalLayout(false);

  if (layoutToggle) {
    layoutToggle.addEventListener('click', () => {
      setVerticalLayout(!isVertical);
    });
  }

  fetchQueue();
  startTimerLoop();

  function scheduleNextPoll(delay = pollMs) {
    if (pollTimeout) {
      clearTimeout(pollTimeout);
    }
    pollTimeout = window.setTimeout(fetchQueue, delay);
  }

  function stopPolling() {
    halted = true;
    if (pollTimeout) {
      clearTimeout(pollTimeout);
      pollTimeout = null;
    }
    if (timerInterval) {
      clearInterval(timerInterval);
      timerInterval = null;
    }
  }

  function startTimerLoop() {
    if (timerInterval) {
      clearInterval(timerInterval);
    }
    timerInterval = window.setInterval(updateTimers, 1000);
  }

  async function fetchQueue() {
    if (halted) {
      return;
    }

    try {
      const response = await fetch(`/api/barbers.php?action=list&token=${encodeURIComponent(token)}`, {
        headers: { Accept: 'application/json' },
      });

      if (response.status === 403) {
        showAlert('TV token is invalid or has expired. Please request a new link.', 'error');
        stopPolling();
        return;
      }

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const payload = await response.json();
      const barbers = Array.isArray(payload.data) ? payload.data : [];
      lastServerTime = payload.server_time ? new Date(payload.server_time) : null;
      lastSyncTimestamp = Date.now();

      const settings = payload.settings || {};
      if (shopNameEl && settings.shop_name) {
        shopNameEl.textContent = settings.shop_name;
      }

      if (updatedEl && payload.server_time) {
        updatedEl.textContent = `Updated ${formatUpdatedTime(lastServerTime)}`;
      }

      renderBarbers(barbers);
      clearAlert();

      scheduleNextPoll(payload.poll_interval_ms || pollMs);
    } catch (error) {
      console.error('TV queue fetch failed', error);
      showAlert('Connection lost. Attempting to reconnect…', 'warn');
      scheduleNextPoll(Math.max(pollMs, 5000));
    }
  }

  function setVerticalLayout(enable) {
    isVertical = Boolean(enable);
    if (listEl) {
      listEl.classList.toggle('is-vertical', isVertical);
    }
    if (layoutToggle) {
      layoutToggle.setAttribute('aria-pressed', isVertical ? 'true' : 'false');
    }
    if (layoutStateEl) {
      layoutStateEl.textContent = isVertical ? 'On' : 'Off';
    }
  }

  function renderBarbers(barbers) {
    if (!listEl) {
      return;
    }

    if (barbers.length === 0) {
      listEl.innerHTML = '<p class="tv-empty">No barbers in queue.</p>';
      return;
    }

    const fragment = document.createDocumentFragment();

    barbers.forEach((barber) => {
      const card = document.createElement('article');
      card.className = 'tv-card';
      card.dataset.status = barber.status;
      card.dataset.busySince = barber.busy_since || '';

      const positionEl = document.createElement('p');
      positionEl.className = 'tv-position';
      positionEl.textContent = `#${barber.position}`;

      const header = document.createElement('div');
      header.className = 'tv-card__header';

      const nameEl = document.createElement('h3');
      nameEl.className = 'tv-name';
      nameEl.textContent = barber.name;

      header.appendChild(nameEl);
      header.appendChild(positionEl);

      const footer = document.createElement('div');
      footer.className = 'tv-card__footer';

      const statusEl = document.createElement('p');
      statusEl.className = 'tv-status';
      statusEl.textContent = formatStatus(barber.status);

      const timerEl = document.createElement('p');
      timerEl.className = 'tv-timer';
      timerEl.textContent = '--:--';

      footer.appendChild(statusEl);
      footer.appendChild(timerEl);

      card.appendChild(header);
      card.appendChild(footer);

      card._timerEl = timerEl;

      fragment.appendChild(card);
    });

    listEl.replaceChildren(fragment);
  }

  function updateTimers() {
    if (!lastServerTime || !listEl) {
      return;
    }

    const elapsedSinceSync = Date.now() - lastSyncTimestamp;

    listEl.querySelectorAll('.tv-card').forEach((card) => {
      const status = card.dataset.status;
      const busySince = card.dataset.busySince;
      const timerEl = card._timerEl;

      if (!timerEl) {
        return;
      }

      if (!busySince || status === 'available' || status === 'inactive') {
        timerEl.textContent = '--:--';
        return;
      }

      const busyDate = new Date(busySince);
      if (Number.isNaN(busyDate.getTime())) {
        timerEl.textContent = '--:--';
        return;
      }

      const baseElapsed = lastServerTime.getTime() - busyDate.getTime();
      const totalElapsed = Math.max(0, baseElapsed + elapsedSinceSync);
      timerEl.textContent = formatDuration(totalElapsed);
    });
  }

  function formatDuration(milliseconds) {
    const totalSeconds = Math.floor(milliseconds / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
  }

  function formatStatus(status) {
    switch (status) {
      case 'available':
        return 'Available';
      case 'busy_walkin':
        return 'Busy · Walk-In';
      case 'busy_appointment':
        return 'Busy · Appointment';
      case 'inactive':
        return 'Inactive';
      default:
        return status;
    }
  }

  function formatUpdatedTime(date) {
    if (!(date instanceof Date)) {
      return 'Updating…';
    }

    return date.toLocaleTimeString(undefined, {
      hour: 'numeric',
      minute: '2-digit',
    });
  }

  function showAlert(message, level = 'error') {
    if (!alertEl) {
      return;
    }
    alertEl.textContent = message;
    alertEl.hidden = false;
    alertEl.dataset.level = level;
  }

  function clearAlert() {
    if (!alertEl) {
      return;
    }
    alertEl.hidden = true;
    alertEl.textContent = '';
    delete alertEl.dataset.level;
  }
})();
