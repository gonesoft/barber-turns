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

  const dropIndicator = document.createElement('div');
  dropIndicator.className = 'queue-drop-indicator';

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.status-menu') && !event.target.closest('.status-chip')) {
      closeStatusMenu();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeStatusMenu();
    }
  });

  if (!isViewer && listEl) {
    listEl.addEventListener('dragover', handleListDragOver);
    listEl.addEventListener('drop', handleListDrop);
    listEl.addEventListener('dragleave', handleListDragLeave);
  }

  let pollingTimeout = null;
  let timerInterval = null;
  let lastServerTime = null;
  let lastSyncTimestamp = 0;
  let actionLock = false;
  let reorderingLock = false;
  let draggedCard = null;
  let draggedId = null;
  let dropIndex = null;
  let currentOrder = [];
  let statusMenuOpenCardId = null;

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
      if (statusMenuOpenCardId !== null) {
        scheduleNextPoll(pollMs);
        return;
      }

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

    currentOrder = barbers.map((barber) => Number(barber.id));
    dropIndex = null;
    draggedId = null;
    dropIndicator.remove();

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
          card._statusEl.addEventListener('click', (event) => {
            event.stopPropagation();
            toggleStatusMenu(card);
          });
          card._statusEl.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              toggleStatusMenu(card);
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
      card.addEventListener('dragend', handleDragEnd);
      card.addEventListener('dragover', handleCardDragOver);
      card.addEventListener('drop', handleCardDrop);
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
    card._statusEl.tabIndex = isViewer ? -1 : 0;
    card.dataset.status = barber.status;

    buildStatusMenu(card);

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

  function buildStatusMenu(card) {
    if (card._statusMenu) {
      card._statusMenu.remove();
    }

    const currentStatus = card.dataset.status || 'available';

    const menu = document.createElement('div');
    menu.className = 'status-menu';
    menu.tabIndex = -1;
    menu.hidden = true;
    menu.addEventListener('click', (event) => event.stopPropagation());

    const statuses = [
      { value: 'available', label: 'Available' },
      { value: 'busy_appointment', label: 'Busy · Appointment' },
      { value: 'busy_walkin', label: 'Busy · Walk-In' },
      { value: 'inactive', label: 'Inactive' },
    ];

    statuses.forEach((status) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'status-menu__option';
      option.textContent = status.label;
      option.dataset.value = status.value;
      if (status.value === currentStatus) {
        option.classList.add('is-active');
      }
      option.addEventListener('click', () => {
        applyStatus(card, status.value);
        closeStatusMenu();
      });
      menu.appendChild(option);
    });

    card._statusMenu = menu;
    card.appendChild(menu);
  }

  function handleDragStart(event) {
    if (isViewer) {
      return;
    }
    draggedCard = event.currentTarget;
    draggedId = Number(draggedCard.dataset.id);
    dropIndex = null;
    queueSection.classList.add('is-reordering');
    dropIndicator.style.maxWidth = `${draggedCard.offsetWidth}px`;
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', draggedCard.dataset.id || '');
    }
  }

  function handleListDragOver(event) {
    if (isViewer || !draggedCard) {
      return;
    }
    event.preventDefault();
    updateDropIndicator(event.clientY);
  }

  function handleListDrop(event) {
    if (isViewer || !draggedCard) {
      return;
    }
    event.preventDefault();
    placeDraggedCard();
  }

  function handleListDragLeave(event) {
    if (!dropIndicator.isConnected) {
      return;
    }
    const related = event.relatedTarget;
    if (!related || !listEl.contains(related)) {
      dropIndicator.remove();
    }
  }

  function handleCardDragOver(event) {
    if (isViewer || !draggedCard) {
      return;
    }
    if (event.target.closest('.status-menu')) {
      return;
    }
    event.preventDefault();
    updateDropIndicator(event.clientY, event.currentTarget);
  }

  function handleCardDrop(event) {
    if (isViewer || !draggedCard) {
      return;
    }
    if (event.target.closest('.status-menu')) {
      return;
    }
    event.preventDefault();
    placeDraggedCard();
  }

  function handleDragEnd() {
    queueSection.classList.remove('is-reordering');
    dropIndicator.remove();
    draggedCard = null;
    dropIndex = null;
  }

  function finalizeReorder(order) {
    if (reorderingLock) {
      return;
    }

    dropIndicator.remove();

    if (!Array.isArray(order) || order.length === 0) {
      queueSection.classList.remove('is-reordering');
      draggedCard = null;
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
        const text = await response.text();
        if (!response.ok) {
          throw new Error(text || 'reorder_failed');
        }
        clearAlert();
        return text ? JSON.parse(text) : {};
      })
      .catch((error) => {
        console.error('Manual reorder failed', error);
        showAlert('Unable to reorder the queue right now.', 'warn');
      })
      .finally(() => {
        reorderingLock = false;
        queueSection.classList.remove('is-reordering');
        draggedCard = null;
        fetchBarbers();
      });
  }

  function updateDropIndicator(clientY, target) {
    const cards = Array.from(listEl.querySelectorAll('.barber-card')).filter((el) => el !== draggedCard);
    let closest = { offset: Number.NEGATIVE_INFINITY, element: null, index: cards.length };

    if (target && target !== dropIndicator && target !== draggedCard) {
      const targetIndex = cards.indexOf(target);
      if (targetIndex !== -1) {
        const rect = target.getBoundingClientRect();
        const shouldPlaceAfter = clientY - rect.top > rect.height / 2;
        closest.element = shouldPlaceAfter ? target.nextElementSibling : target;
        closest.index = shouldPlaceAfter ? targetIndex + 1 : targetIndex;
      }
    } else {
      cards.forEach((card, index) => {
        const box = card.getBoundingClientRect();
        const offset = clientY - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
          closest = { offset, element: card, index };
        }
      });
    }

    dropIndex = closest.element ? Math.max(closest.index, 0) : cards.length;

    if (closest.element === null || closest.element === undefined) {
      listEl.appendChild(dropIndicator);
    } else {
      listEl.insertBefore(dropIndicator, closest.element);
    }
  }

  function placeDraggedCard() {
    if (!draggedCard) {
      return;
    }
    closeStatusMenu();
    if (dropIndex === null) {
      dropIndex = currentOrder.length;
    }

    const order = currentOrder.filter((id) => id !== draggedId);
    const insertIndex = Math.min(dropIndex, order.length);
    order.splice(insertIndex, 0, draggedId);
    currentOrder = order.slice();

    if (dropIndicator.isConnected) {
      listEl.insertBefore(draggedCard, dropIndicator);
    } else {
      const cards = listEl.querySelectorAll('.barber-card');
      if (cards[insertIndex]) {
        listEl.insertBefore(draggedCard, cards[insertIndex]);
      } else {
        listEl.appendChild(draggedCard);
      }
    }

    finalizeReorder(order);
  }

  // getDragAfterElement no longer used; logic moved into updateDropIndicator

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
  function toggleStatusMenu(card) {
    if (isViewer) {
      return;
    }
    if (card.classList.contains('status-menu-open')) {
      closeStatusMenu();
      return;
    }
    closeStatusMenu();
    card.classList.add('status-menu-open');
    statusMenuOpenCardId = Number(card.dataset.id);
    if (card._statusMenu) {
      card._statusMenu.hidden = false;
      card._statusMenu.focus();
    }
  }

  function closeStatusMenu() {
    if (!listEl) {
      return;
    }
    const open = listEl.querySelector('.status-menu-open');
    if (open) {
      open.classList.remove('status-menu-open');
      if (open._statusMenu) {
        open._statusMenu.hidden = true;
      }
    }
    statusMenuOpenCardId = null;
  }

  function applyStatus(card, status) {
    if (isViewer) {
      return;
    }
    const id = Number(card.dataset.id);
    if (!status || Number.isNaN(id)) {
      return;
    }

    if (status !== 'available') {
      card.dataset.lastNonAvailable = status;
    }

    fetch('/api/barbers.php?action=status', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ barber_id: id, status }),
    })
      .then(async (response) => {
        if (!response.ok) {
          throw new Error('status_failed');
        }
        clearAlert();
        return response.json();
      })
      .then(() => fetchBarbers())
      .catch((err) => {
        console.error('Status update failed', err);
        showAlert('Unable to update barber status. Please try again.');
      });
  }
})();
