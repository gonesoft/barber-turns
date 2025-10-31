/**
 * Barber Turns front-desk queue interactions.
 *
 * Handles polling, status transitions, and viewer restrictions.
 */

(function () {
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.getElementById('site-nav-menu');

  if (!toggle || !nav) {
    return;
  }

  const closeOnOutsideClick = (event) => {
    if (!nav.classList.contains('is-open')) {
      return;
    }
    if (event.target === toggle || toggle.contains(event.target)) {
      return;
    }
    if (nav.contains(event.target)) {
      return;
    }
    setExpanded(false);
  };

  const setExpanded = (expanded) => {
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    toggle.classList.toggle('is-open', expanded);
    nav.classList.toggle('is-open', expanded);
  };

  toggle.addEventListener('click', () => {
    const next = toggle.getAttribute('aria-expanded') !== 'true';
    setExpanded(next);
  });

  document.addEventListener('click', closeOnOutsideClick);
  window.addEventListener('resize', () => {
    if (window.innerWidth >= 900) {
      setExpanded(false);
    }
  });
})();

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

  document.addEventListener('pointerdown', handleGlobalPointerDown, true);
  document.addEventListener('keydown', handleGlobalKeydown, true);

  let timerInterval = null;
  let lastServerTime = null;
  let lastSyncTimestamp = 0;
  let actionLock = false;
  let reorderingLock = false;
  let draggedCard = null;
  let draggedId = null;
  let dropIndex = null;
  let currentOrder = [];
  let pointerDragActive = false;
  let activePointerId = null;
  let pendingPointerCard = null;
  let pendingPointerHandle = null;
  let pointerStartX = 0;
  let pointerStartY = 0;
  let draggedHandle = null;
  let flippedCard = null;
  let flippedCardId = null;
  let flipTeardown = null;

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
    if (!bufferedPayload || flippedCard) {
      return;
    }
    const payload = bufferedPayload;
    bufferedPayload = null;
    console.debug('[render] applying buffered payload');
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
      closeFlips();
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

      if (dragPhase !== 'idle' || isDragging || flippedCard) {
        bufferedPayload = payload;
        console.debug('[poll] payload buffered (%s)', flippedCard ? 'flip active' : dragPhase);
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
      teardownFlipUI();
      flippedCard = null;
      flippedCardId = null;
      return;
    }

    const fragment = document.createDocumentFragment();

    barbers.forEach((barber) => {
      const id = Number(barber.id);
      let card = existing.get(id);

      if (!card) {
        card = createCard(id);
      } else {
        existing.delete(id);
      }

      updateCard(card, barber);
      fragment.appendChild(card);
    });

    existing.forEach((card) => {
      if (card === flippedCard) {
        flippedCard = null;
        flippedCardId = null;
      }
      card.remove();
    });

    teardownFlipUI();
    listEl.replaceChildren(fragment);
    initFlipUI(listEl);

    if (flippedCardId !== null) {
      const possible = listEl.querySelector(`.barber-card[data-id="${flippedCardId}"] .card-3d`);
      if (!possible) {
        flippedCard = null;
        flippedCardId = null;
      }
    }
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

    const card3d = document.createElement('div');
    card3d.className = 'card-3d';

    const cardInner = document.createElement('div');
    cardInner.className = 'card-3d-inner';

    const cardFront = document.createElement('div');
    cardFront.className = 'card-face card-front';

    const frontContent = document.createElement('div');
    frontContent.className = 'card-front-content';

    const header = document.createElement('div');
    header.className = 'barber-card__header';

    const nameEl = document.createElement('span');
    nameEl.className = 'barber-name';

    const statusToggle = document.createElement('button');
    statusToggle.type = 'button';
    statusToggle.className = 'status-chip status-toggle';
    statusToggle.setAttribute('aria-haspopup', 'true');
    statusToggle.setAttribute('aria-expanded', 'false');
    statusToggle.tabIndex = isViewer ? -1 : 0;

    header.appendChild(nameEl);
    header.appendChild(statusToggle);

    const meta = document.createElement('div');
    meta.className = 'barber-card__meta';
    meta.classList.add('front-meta');

    const positionEl = document.createElement('span');
    positionEl.className = 'barber-position';

    const timerEl = document.createElement('span');
    timerEl.className = 'barber-timer';
    timerEl.textContent = '--:--';

    meta.appendChild(positionEl);
    meta.appendChild(timerEl);

    frontContent.appendChild(header);
    frontContent.appendChild(meta);
    cardFront.appendChild(frontContent);

    const cardBack = document.createElement('div');
    cardBack.className = 'card-face card-back';

    const statusPanel = document.createElement('div');
    statusPanel.className = 'status-panel';

    const statusOptions = document.createElement('div');
    statusOptions.className = 'status-options';

    const statuses = [
      { value: 'available', label: 'Available' },
      { value: 'busy_walkin', label: 'Busy · Walk-In' },
      { value: 'busy_appointment', label: 'Busy · Appointment' },
      { value: 'inactive', label: 'Inactive' },
    ];

    statuses.forEach(({ value, label }) => {
      const optionBtn = document.createElement('button');
      optionBtn.type = 'button';
      optionBtn.dataset.status = value;
      optionBtn.className = `status-chip status-option status-${value}`;
      optionBtn.textContent = label;
      statusOptions.appendChild(optionBtn);
    });

    statusPanel.appendChild(statusOptions);
    cardBack.appendChild(statusPanel);

    cardInner.appendChild(cardFront);
    cardInner.appendChild(cardBack);
    card3d.appendChild(cardInner);

    card.appendChild(handle);
    card.appendChild(card3d);

    card._nameEl = nameEl;
    card._statusToggle = statusToggle;
    card._positionEl = positionEl;
    card._timerEl = timerEl;
    card._dragHandle = handle;
    card._card3d = card3d;
    card._cardInner = cardInner;
    card._cardFront = cardFront;
    card._cardBack = cardBack;
    card._statusButtons = statusOptions.querySelectorAll('button[data-status]');

    if (isViewer) {
      card.classList.add('is-disabled');
      card.draggable = false;
      statusToggle.disabled = true;
      card._statusButtons.forEach((btn) => {
        btn.disabled = true;
      });
    } else {
      card.draggable = false;
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

    if (card._nameEl) {
      card._nameEl.textContent = barber.name;
    }
    if (card._positionEl) {
      card._positionEl.textContent = `#${barber.position}`;
    }

    if (card._statusToggle) {
      card._statusToggle.textContent = formatStatus(barber.status);
      card._statusToggle.className = `status-chip status-toggle status-${barber.status}`;
      card._statusToggle.tabIndex = isViewer ? -1 : 0;
      card._statusToggle.disabled = isViewer;
      const expanded = card._card3d?.classList.contains('is-flipped');
      card._statusToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    if (card._statusButtons) {
      card._statusButtons.forEach((btn) => {
        btn.disabled = isViewer;
        if (btn.dataset.status === barber.status) {
          btn.classList.add('is-active');
        } else {
          btn.classList.remove('is-active');
        }
      });
    }

    card.dataset.status = barber.status;

    if (!barber.busy_since || barber.status === 'available') {
      if (card._timerEl) {
        card._timerEl.textContent = '--:--';
      }
    } else {
      const busyDate = new Date(barber.busy_since);
      if (Number.isNaN(busyDate.getTime())) {
        if (card._timerEl) {
          card._timerEl.textContent = '--:--';
        }
      } else {
        const elapsed = lastServerTime ? Math.max(0, lastServerTime.getTime() - busyDate.getTime()) : 0;
        if (card._timerEl) {
          card._timerEl.textContent = formatDuration(elapsed);
        }
      }
    }
  }

  function startDragSession(card, handle) {
    if (!card || !listEl) {
      return false;
    }

    closeFlips();

    draggedCard = card;
    draggedHandle = handle || card._dragHandle || null;
    draggedId = Number(card.dataset.id);
    const currentPosition = currentOrder.indexOf(draggedId);
    dropIndex = currentPosition === -1 ? null : currentPosition;
    queueSection.classList.add('is-reordering');

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

    closeFlips();
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

  function toggleFlip(card) {
    if (!card || isViewer) {
      return;
    }
    if (!card._card3d) {
      return;
    }

    const alreadyOpen = flippedCard && flippedCard === card;
    closeFlips();

    if (alreadyOpen) {
      return;
    }

    card._card3d.classList.add('is-flipped');
    if (card._statusToggle) {
      card._statusToggle.setAttribute('aria-expanded', 'true');
    }
    flippedCard = card;
    flippedCardId = Number(card.dataset.id) || null;
  }

  function closeFlips({ restoreFocus = false } = {}) {
    if (!flippedCard) {
      return;
    }

    if (flippedCard._card3d) {
      flippedCard._card3d.classList.remove('is-flipped');
    }
    if (flippedCard._statusToggle) {
      flippedCard._statusToggle.setAttribute('aria-expanded', 'false');
      if (restoreFocus) {
        flippedCard._statusToggle.focus({ preventScroll: true });
      }
    }
    flippedCard = null;
    flippedCardId = null;
    flushBufferedPayload();
  }

  function handleGlobalPointerDown(event) {
    if (!flippedCard) {
      return;
    }
    if (event.target && flippedCard.contains(event.target)) {
      return;
    }
    closeFlips();
  }

  function handleGlobalKeydown(event) {
    if (event.key === 'Escape') {
      closeFlips({ restoreFocus: true });
    }
  }

  function initFlipUI(root) {
    teardownFlipUI();
    if (!root || isViewer) {
      return;
    }

    const handleClick = (event) => {
      const toggle = event.target.closest('.status-toggle');
      if (toggle && root.contains(toggle)) {
        event.preventDefault();
        event.stopPropagation();
        const card = toggle.closest('.barber-card');
        toggleFlip(card);
        return;
      }

      const option = event.target.closest('.status-option');
      if (option && root.contains(option)) {
        event.preventDefault();
        const card = option.closest('.barber-card');
        if (!card || card.classList.contains('is-disabled')) {
          return;
        }
        submitStatus(card, option.dataset.status);
      }
    };

    const handleFocusOut = (event) => {
      if (!flippedCard) {
        return;
      }
      if (event.relatedTarget && flippedCard.contains(event.relatedTarget)) {
        return;
      }
      if (event.target && flippedCard.contains(event.target)) {
        requestAnimationFrame(() => closeFlips());
      }
    };

    root.addEventListener('click', handleClick);
    root.addEventListener('focusout', handleFocusOut);

    flipTeardown = () => {
      root.removeEventListener('click', handleClick);
      root.removeEventListener('focusout', handleFocusOut);
    };
  }

  function teardownFlipUI() {
    if (typeof flipTeardown === 'function') {
      flipTeardown();
      flipTeardown = null;
    }
  }

  function submitStatus(card, status) {
    if (isViewer || !status || actionLock) {
      return;
    }

    const id = Number(card.dataset.id);
    if (Number.isNaN(id)) {
      return;
    }

    if (status !== 'available') {
      card.dataset.lastNonAvailable = status;
    }

    actionLock = true;
    card.classList.add('is-disabled');

    const buttons = card._statusButtons ? Array.from(card._statusButtons) : [];
    buttons.forEach((btn) => {
      btn.disabled = true;
    });
    if (card._statusToggle) {
      card._statusToggle.disabled = true;
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
      .then(() => {
        closeFlips({ restoreFocus: true });
        fetchBarbers(true);
      })
      .catch((err) => {
        console.error('Status update failed', err);
        showAlert('Unable to update barber status. Please try again.');
      })
      .finally(() => {
        actionLock = false;
        card.classList.remove('is-disabled');
        buttons.forEach((btn) => {
          btn.disabled = isViewer;
        });
        if (card._statusToggle) {
          card._statusToggle.disabled = isViewer;
        }
      });
  }
})();
