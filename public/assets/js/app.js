/**
 * Barber Turns front-desk queue interactions.
 *
 * Handles polling, status transitions, and viewer restrictions.
 */

(function () {
  const queueSection = document.querySelector('.view-queue');
  if (!queueSection) {
    return;
  }

  const listEl = document.getElementById('barber-list');
  const alertEl = document.getElementById('queue-alert');
  const role = queueSection.dataset.role || 'viewer';
  const pollMs = parseInt(queueSection.dataset.poll || '3000', 10);
  const isViewer = role === 'viewer';

  queueSection.classList.toggle('is-viewer', isViewer);
  if (!isViewer && listEl) {
    listEl.addEventListener('dragover', (event) => {
      event.preventDefault();
    });
    listEl.addEventListener('drop', handleDrop);
  }

  let pollingTimeout = null;
  let timerInterval = null;
  let lastServerTime = null;
  let lastSyncTimestamp = 0;
  let actionLock = false;
  let reorderingLock = false;
  let draggedCard = null;

  fetchBarbers();
  startTimerLoop();

  function scheduleNextPoll(delay = pollMs) {
    if (pollingTimeout) {
      clearTimeout(pollingTimeout);
    }
    pollingTimeout = window.setTimeout(fetchBarbers, delay);
  }

  function startTimerLoop() {
    if (timerInterval) {
      clearInterval(timerInterval);
    }
    timerInterval = window.setInterval(updateTimers, 1000);
  }

  async function fetchBarbers() {
    try {
      const response = await fetch('/api/barbers.php?action=list', {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
      }

      const payload = await response.json();
      const barbers = Array.isArray(payload.data) ? payload.data : [];
      lastServerTime = payload.server_time ? new Date(payload.server_time) : null;
      lastSyncTimestamp = Date.now();

      renderBarbers(barbers);
      clearAlert();

      scheduleNextPoll(payload.poll_interval_ms || pollMs);
    } catch (err) {
      console.error('Failed to load barbers', err);
      showAlert('Unable to load queue. Retrying…');
      scheduleNextPoll(Math.max(pollMs, 5000));
    }
  }

  function renderBarbers(barbers) {
    if (!listEl) {
      return;
    }

    const existing = new Map();
    Array.from(listEl.children).forEach((child) => {
      if (child.dataset && child.dataset.id) {
        existing.set(Number(child.dataset.id), child);
      }
    });

    if (barbers.length === 0) {
      listEl.innerHTML = '<p class="queue-empty">No barbers in queue yet.</p>';
      return;
    }

    const fragment = document.createDocumentFragment();

    barbers.forEach((barber) => {
      const id = Number(barber.id);
      let card = existing.get(id);

      if (!card) {
        card = createCard(id);
        if (!isViewer) {
          card.addEventListener('click', () => onCardActivate(card));
          card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              onCardActivate(card);
            }
          });
        }
      } else {
        existing.delete(id);
      }

      updateCard(card, barber);
      fragment.appendChild(card);
    });

    existing.forEach((card) => {
      card.remove();
    });

    listEl.replaceChildren(fragment);
  }

  function createCard(id) {
    const card = document.createElement('article');
    card.className = 'barber-card';
    card.tabIndex = isViewer ? -1 : 0;
    card.dataset.id = String(id);

    const header = document.createElement('div');
    header.className = 'barber-card__header';

    const nameEl = document.createElement('span');
    nameEl.className = 'barber-name';

    const statusEl = document.createElement('span');
    statusEl.className = 'status-chip';

    header.appendChild(nameEl);
    header.appendChild(statusEl);

    const meta = document.createElement('div');
    meta.className = 'barber-card__meta';

    const positionEl = document.createElement('span');
    positionEl.className = 'barber-position';

    const timerEl = document.createElement('span');
    timerEl.className = 'barber-timer';
    timerEl.textContent = '--:--';

    meta.appendChild(positionEl);
    meta.appendChild(timerEl);

    card.appendChild(header);
    card.appendChild(meta);

    card._nameEl = nameEl;
    card._statusEl = statusEl;
    card._positionEl = positionEl;
    card._timerEl = timerEl;

    if (isViewer) {
      card.classList.add('is-disabled');
      card.draggable = false;
    }
    if (!isViewer && !card._dragBound) {
      card.draggable = true;
      card.addEventListener('dragstart', handleDragStart);
      card.addEventListener('dragover', handleDragOver);
      card.addEventListener('drop', handleDrop);
      card.addEventListener('dragend', handleDragEnd);
      card._dragBound = true;
    }
    return card;
  }

  function updateCard(card, barber) {
    card.dataset.id = String(barber.id);
    card.dataset.status = barber.status;
    card.dataset.busySince = barber.busy_since || '';
    if (barber.status !== 'available') {
      card.dataset.lastNonAvailable = barber.status;
    }

    if (isViewer) {
      card.classList.add('is-disabled');
      card.tabIndex = -1;
      card.draggable = false;
    } else {
      card.classList.remove('is-disabled');
      card.tabIndex = 0;
      card.draggable = true;
    }

    card._nameEl.textContent = barber.name;
    card._positionEl.textContent = `#${barber.position}`;

    card._statusEl.textContent = formatStatus(barber.status);
    card._statusEl.className = `status-chip status-${barber.status}`;

    if (!barber.busy_since || barber.status === 'available') {
      card._timerEl.textContent = '--:--';
    }
  }

  function onCardActivate(card) {
    if (isViewer || actionLock) {
      return;
    }

    const id = Number(card.dataset.id);
    const nextStatus = computeNextStatus(card);

    if (!nextStatus) {
      return;
    }

    actionLock = true;
    card.classList.add('is-disabled');

    fetch('/api/barbers.php?action=status', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        barber_id: id,
        status: nextStatus,
      }),
    })
      .then(async (response) => {
        if (!response.ok) {
          if (response.status === 403) {
            showAlert('You do not have permission to update statuses.', 'warn');
          } else {
            showAlert('Unable to update barber status. Please try again.');
          }
          return;
        }

        clearAlert();
        return response.json();
      })
      .then(() => fetchBarbers())
      .catch((err) => {
        console.error('Status update failed', err);
        showAlert('Status update failed. Please try again.');
      })
      .finally(() => {
        actionLock = false;
        card.classList.remove('is-disabled');
      });
  }

  function computeNextStatus(card) {
    const current = card.dataset.status || 'available';
    const lastNonAvailable = card.dataset.lastNonAvailable || null;

    if (current === 'available') {
      return lastNonAvailable === 'busy_appointment' ? 'busy_walkin' : 'busy_appointment';
    }
    if (current === 'busy_appointment') {
      return 'available';
    }
    if (current === 'busy_walkin') {
      return 'available';
    }
    return 'available';
  }

  function handleDragStart(event) {
    if (isViewer) {
      return;
    }
    draggedCard = event.currentTarget;
    queueSection.classList.add('is-reordering');
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', draggedCard.dataset.id || '');
    }
  }

  function handleDragOver(event) {
    if (isViewer || !draggedCard) {
      return;
    }
    event.preventDefault();
    const target = event.currentTarget;
    if (!target || target === draggedCard || !target.dataset.id) {
      return;
    }
    const rect = target.getBoundingClientRect();
    const shouldPlaceAfter = event.clientY - rect.top > rect.height / 2;
    if (shouldPlaceAfter) {
      target.after(draggedCard);
    } else {
      listEl.insertBefore(draggedCard, target);
    }
  }

  function handleDrop(event) {
    if (isViewer || !draggedCard) {
      return;
    }
    event.preventDefault();
    finalizeReorder();
  }

  function handleDragEnd() {
    queueSection.classList.remove('is-reordering');
    draggedCard = null;
  }

  function finalizeReorder() {
    if (reorderingLock) {
      return;
    }

    const order = Array.from(listEl.children)
      .map((el) => Number(el.dataset.id))
      .filter((id) => Number.isInteger(id) && id > 0);

    if (order.length === 0) {
      return;
    }

    reorderingLock = true;

    fetch('/api/barbers.php?action=order', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order }),
    })
      .then(async (response) => {
        if (!response.ok) {
          throw new Error('reorder_failed');
        }
        clearAlert();
        return response.json();
      })
      .catch((error) => {
        console.error('Manual reorder failed', error);
        showAlert('Unable to reorder the queue right now.', 'warn');
      })
      .finally(() => {
        reorderingLock = false;
        fetchBarbers();
      });
  }

  function updateTimers() {
    if (!lastServerTime) {
      return;
    }

    const now = Date.now();
    const elapsedSinceSync = now - lastSyncTimestamp;

    listEl.querySelectorAll('.barber-card').forEach((card) => {
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

  function showAlert(message, level = 'error') {
    if (!alertEl) {
      return;
    }
    alertEl.textContent = message;
    alertEl.hidden = false;
    alertEl.setAttribute('data-level', level);
  }

  function clearAlert() {
    if (!alertEl) {
      return;
    }
    alertEl.hidden = true;
    alertEl.textContent = '';
    alertEl.removeAttribute('data-level');
  }
})();
