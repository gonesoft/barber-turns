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
  const POINTER_DRAG_THRESHOLD = 6;

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
  let pointerDragActive = false;
  let activePointerId = null;
  let pendingPointerCard = null;
  let pendingPointerHandle = null;
  let pointerStartX = 0;
  let pointerStartY = 0;
  let draggedHandle = null;

  let pollIntervalId = null;
  let resumeTimeoutId = null;
  let fetchInFlight = false;
  let bufferedPayload = null;
  let isDragging = false;
  let dragPhase = 'idle';
  let currentPollMs = pollMs;

  initDnD(listEl);

  startTimerLoop();
  (async () => {
    await fetchBarbers(true);
    startPolling();
  })();

  function setDragPhase(next) {
    if (dragPhase === next) {
      return;
    }
    console.debug('[dnd] %s -> %s', dragPhase, next);
    dragPhase = next;
  }

  function startPolling() {
    if (pollIntervalId) {
      clearInterval(pollIntervalId);
    }
    pollIntervalId = window.setInterval(() => {
      if (isDragging || dragPhase !== 'idle') {
        console.debug('[poll] skipped (dragging)');
        return;
      }
      fetchBarbers();
    }, currentPollMs);
    console.debug('[poll] started (%dms)', currentPollMs);
  }

  function pausePolling() {
    if (pollIntervalId) {
      clearInterval(pollIntervalId);
      pollIntervalId = null;
      console.debug('[poll] paused');
    }
    if (resumeTimeoutId) {
      clearTimeout(resumeTimeoutId);
      resumeTimeoutId = null;
    }
  }

  function resumePolling({ immediateFetch = true } = {}) {
    if (resumeTimeoutId) {
      clearTimeout(resumeTimeoutId);
    }
    resumeTimeoutId = window.setTimeout(() => {
      resumeTimeoutId = null;
      startPolling();
      if (immediateFetch) {
        fetchBarbers(true);
      }
    }, 300);
  }

  function flushBufferedPayload() {
    if (!bufferedPayload) {
      return;
    }
    const payload = bufferedPayload;
    bufferedPayload = null;
    console.debug('[dnd] applying buffered payload');
    applyPayload(payload);
  }

  function initDnD(root) {
    if (!root || isViewer) {
      return () => {};
    }

    const delegatedPointerDown = (event) => {
      const handle = event.target.closest('.drag-handle');
      if (!handle || !root.contains(handle)) {
        return;
      }
      handleDragPointerDown(handle, event);
    };

    root.addEventListener('pointerdown', delegatedPointerDown);

    return () => {
      root.removeEventListener('pointerdown', delegatedPointerDown);
    };
  }

  function startTimerLoop() {
    if (timerInterval) {
      clearInterval(timerInterval);
    }
    timerInterval = window.setInterval(updateTimers, 1000);
  }

  async function fetchBarbers(force = false) {
    if (fetchInFlight && !force) {
      return;
    }

    if (statusMenuOpenCardId !== null && !force) {
      console.debug('[poll] fetch skipped (status menu open)');
      return;
    }

    if ((isDragging || dragPhase !== 'idle') && !force) {
      console.debug('[poll] fetch skipped during drag');
      return;
    }

    fetchInFlight = true;

    try {
      const response = await fetch('/api/barbers.php?action=list', {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
      }

      const payload = await response.json();

      if (dragPhase !== 'idle' || isDragging) {
        bufferedPayload = payload;
        console.debug('[dnd] payload buffered during %s phase', dragPhase);
        return;
      }

      applyPayload(payload);
      clearAlert();
    } catch (err) {
      console.error('Failed to load barbers', err);
      showAlert('Unable to load queue. Retrying…');
    } finally {
      fetchInFlight = false;
    }
  }

  function applyPayload(payload) {
    const barbers = Array.isArray(payload?.data) ? payload.data : [];
    lastServerTime = payload?.server_time ? new Date(payload.server_time) : null;
    lastSyncTimestamp = Date.now();
    if (typeof payload?.poll_interval_ms === 'number' && payload.poll_interval_ms > 0 && payload.poll_interval_ms !== currentPollMs) {
      currentPollMs = payload.poll_interval_ms;
      console.debug('[poll] interval updated to %dms', currentPollMs);
      startPolling();
    }
    renderQueue(barbers);
    clearAlert();
  }

  function renderQueue(barbers) {
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

    const handle = document.createElement('div');
    handle.className = 'drag-handle';
    handle.setAttribute('aria-grabbed', 'false');
    handle.setAttribute('title', 'Drag to reorder');
    handle.setAttribute('role', 'button');
    handle.setAttribute('aria-label', 'Drag to reorder');
    handle.tabIndex = isViewer ? -1 : 0;
    if (isViewer) {
      handle.setAttribute('aria-hidden', 'true');
      handle.hidden = true;
    }

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

    card.appendChild(handle);
    card.appendChild(header);
    card.appendChild(meta);

    card._nameEl = nameEl;
    card._statusEl = statusEl;
    card._positionEl = positionEl;
    card._timerEl = timerEl;
    card._dragHandle = handle;

    if (isViewer) {
      card.classList.add('is-disabled');
      card.draggable = false;
    }
    if (!isViewer) {
      card.draggable = false;
      card._statusEl.draggable = false;
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

    if (card._dragHandle) {
      if (isViewer) {
        card._dragHandle.setAttribute('aria-hidden', 'true');
        card._dragHandle.setAttribute('aria-grabbed', 'false');
        card._dragHandle.tabIndex = -1;
        card._dragHandle.hidden = true;
      } else {
        card._dragHandle.removeAttribute('aria-hidden');
        card._dragHandle.setAttribute('aria-grabbed', 'false');
        card._dragHandle.tabIndex = 0;
        card._dragHandle.hidden = false;
      }
    }

    if (isViewer) {
      card.classList.add('is-disabled');
      card.tabIndex = -1;
      card.draggable = false;
    } else {
      card.classList.remove('is-disabled');
      card.tabIndex = 0;
      card.draggable = false;
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

  function startDragSession(card, handle) {
    if (!card || !listEl) {
      return false;
    }

    draggedCard = card;
    draggedHandle = handle || card._dragHandle || null;
    draggedId = Number(card.dataset.id);
    const currentPosition = currentOrder.indexOf(draggedId);
    dropIndex = currentPosition === -1 ? null : currentPosition;
    queueSection.classList.add('is-reordering');
    closeStatusMenu();

    const cardRect = card.getBoundingClientRect();
    const listRect = listEl.getBoundingClientRect();
    const scrollTop = listEl.scrollTop;

    if (draggedHandle) {
      draggedHandle.setAttribute('aria-grabbed', 'true');
    }

    card.classList.add('dragging');
    card.style.pointerEvents = 'none';
    card.style.width = `${cardRect.width}px`;
    card.style.left = `${cardRect.left - listRect.left}px`;
    card.style.top = `${cardRect.top - listRect.top + scrollTop}px`;
    card.style.position = 'absolute';
    card.style.zIndex = '100';
    card.style.transform = 'translate3d(0, 0, 0)';
    card.style.willChange = 'transform, opacity';
    card.style.opacity = '0.95';

    dropIndicator.style.height = `${cardRect.height}px`;
    dropIndicator.style.maxWidth = `${cardRect.width}px`;

    if (!dropIndicator.isConnected) {
      listEl.insertBefore(dropIndicator, card.nextElementSibling);
    }

    updateDropIndicator(cardRect.top + cardRect.height / 2);
    pendingPointerCard = null;
    pendingPointerHandle = null;
    isDragging = true;
    setDragPhase('dragging');
    pausePolling();
    return true;
  }

  function resetPointerState(handle, pointerId) {
    if (handle) {
      handle.setAttribute('aria-grabbed', 'false');
      if (
        typeof pointerId === 'number'
        && typeof handle.hasPointerCapture === 'function'
        && typeof handle.releasePointerCapture === 'function'
      ) {
        try {
          if (handle.hasPointerCapture(pointerId)) {
            handle.releasePointerCapture(pointerId);
          }
        } catch (err) {
          // ignore pointer capture release errors
        }
      }
    }

    pointerDragActive = false;
    activePointerId = null;
    pendingPointerCard = null;
    pendingPointerHandle = null;
    pointerStartX = 0;
    pointerStartY = 0;
  }

  function addGlobalPointerListeners() {
    window.addEventListener('pointermove', handleDragPointerMove, { passive: false });
    window.addEventListener('pointerup', handleDragPointerUp);
    window.addEventListener('pointercancel', handleDragPointerCancel);
  }

  function removeGlobalPointerListeners() {
    window.removeEventListener('pointermove', handleDragPointerMove, { passive: false });
    window.removeEventListener('pointerup', handleDragPointerUp);
    window.removeEventListener('pointercancel', handleDragPointerCancel);
  }

  function handleDragPointerDown(handle, event) {
    if (isViewer || pointerDragActive) {
      return;
    }
    if (event.pointerType === 'mouse' && event.button !== 0) {
      return;
    }

    const card = handle.closest('.barber-card');
    if (!card) {
      return;
    }

    event.stopPropagation();

    pointerDragActive = true;
    activePointerId = event.pointerId;
    pendingPointerCard = card;
    pendingPointerHandle = handle;
    pointerStartX = event.clientX;
    pointerStartY = event.clientY;
    setDragPhase('pending');

    if (typeof handle.setPointerCapture === 'function') {
      try {
        handle.setPointerCapture(event.pointerId);
      } catch (err) {
        // ignore pointer capture errors
      }
    }

    addGlobalPointerListeners();
    event.preventDefault();
  }

  function handleDragPointerMove(event) {
    if (!pointerDragActive || event.pointerId !== activePointerId) {
      return;
    }

    const handle = pendingPointerHandle || draggedHandle;
    const card = pendingPointerCard || draggedCard;

    if (!card || isViewer) {
      return;
    }

    if (!draggedCard) {
      const deltaX = Math.abs(event.clientX - pointerStartX);
      const deltaY = Math.abs(event.clientY - pointerStartY);
      if (deltaX + deltaY < POINTER_DRAG_THRESHOLD) {
        return;
      }
      if (!startDragSession(card, handle)) {
        resetPointerState(handle, event.pointerId);
        removeGlobalPointerListeners();
        setDragPhase('idle');
        return;
      }
      pointerStartX = event.clientX;
      pointerStartY = event.clientY;
    }

    event.preventDefault();

    const translateY = event.clientY - pointerStartY;
    draggedCard.style.transform = `translate3d(0, ${translateY}px, 0)`;

    const cardRect = draggedCard.getBoundingClientRect();
    const midpoint = cardRect.top + cardRect.height / 2;
    updateDropIndicator(midpoint);
  }

  function handleDragPointerUp(event) {
    if (!pointerDragActive || event.pointerId !== activePointerId) {
      return;
    }

    const handle = draggedHandle || pendingPointerHandle;
    resetPointerState(handle, event.pointerId);
    removeGlobalPointerListeners();

    if (!draggedCard) {
      setDragPhase('idle');
      return;
    }

    event.preventDefault();
    setDragPhase('dropping');
    placeDraggedCard();
  }

  function handleDragPointerCancel(event) {
    if (!pointerDragActive || event.pointerId !== activePointerId) {
      return;
    }

    const handle = draggedHandle || pendingPointerHandle;
    resetPointerState(handle, event.pointerId);
    removeGlobalPointerListeners();

    queueSection.classList.remove('is-reordering');
    dropIndicator.style.height = '';
    dropIndicator.style.maxWidth = '';
    dropIndicator.remove();

    if (draggedCard) {
      cleanupDraggedCardStyles(draggedCard);
    }

    draggedCard = null;
    draggedId = null;
    dropIndex = null;
    if (draggedHandle) {
      draggedHandle.setAttribute('aria-grabbed', 'false');
      draggedHandle = null;
    }
    isDragging = false;
    setDragPhase('idle');
    flushBufferedPayload();
    resumePolling();
  }

  function cleanupDraggedCardStyles(card) {
    card.classList.remove('dragging');
    card.style.pointerEvents = '';
    card.style.position = '';
    card.style.width = '';
    card.style.left = '';
    card.style.top = '';
    card.style.transform = '';
    card.style.opacity = '';
    card.style.willChange = '';
    card.style.zIndex = '';
  }

  function finalizeReorder(order) {
    if (reorderingLock) {
      return;
    }

    if (!Array.isArray(order) || order.length === 0) {
      queueSection.classList.remove('is-reordering');
      if (draggedCard) {
        cleanupDraggedCardStyles(draggedCard);
      }
      draggedCard = null;
      draggedId = null;
      dropIndex = null;
      draggedHandle = null;
      isDragging = false;
      setDragPhase('idle');
      flushBufferedPayload();
      resumePolling();
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
        if (draggedCard) {
          cleanupDraggedCardStyles(draggedCard);
        }
        draggedCard = null;
        draggedId = null;
        dropIndex = null;
        if (draggedHandle) {
          draggedHandle.setAttribute('aria-grabbed', 'false');
        }
        draggedHandle = null;
        isDragging = false;
        setDragPhase('idle');
        flushBufferedPayload();
        resumePolling();
      });
  }

  function updateDropIndicator(clientY) {
    const cards = Array.from(listEl.querySelectorAll('.barber-card')).filter((el) => el !== draggedCard);

    if (cards.length === 0) {
      dropIndex = 0;
      listEl.appendChild(dropIndicator);
      return;
    }

    let insertIndex = cards.length;

    for (let index = 0; index < cards.length; index += 1) {
      const rect = cards[index].getBoundingClientRect();
      const midpoint = rect.top + rect.height / 2;
      if (clientY < midpoint) {
        insertIndex = index;
        break;
      }
    }

    dropIndex = insertIndex;
    const referenceNode = insertIndex < cards.length ? cards[insertIndex] : null;
    if (referenceNode) {
      listEl.insertBefore(dropIndicator, referenceNode);
    } else {
      listEl.appendChild(dropIndicator);
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

    cleanupDraggedCardStyles(draggedCard);
    dropIndicator.style.height = '';
    dropIndicator.style.maxWidth = '';
    dropIndicator.remove();

    if (draggedHandle) {
      draggedHandle.setAttribute('aria-grabbed', 'false');
      draggedHandle = null;
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
