(function () {
  const root = document.querySelector('.view-barbers');
  if (!root) {
    return;
  }

  const STATUS_LABELS = {
    available: 'Available',
    busy_walkin: 'Busy · Walk-In',
    busy_appointment: 'Busy · Appointment',
    inactive: 'Inactive',
  };

  const searchForm = root.querySelector('#barbers-search-form');
  const searchInput = root.querySelector('#barbers-search-input');
  const resultsList = root.querySelector('#barbers-results');
  const newButton = root.querySelector('#barbers-new-btn');
  const form = root.querySelector('#barbers-form');
  const resetButton = root.querySelector('#barbers-reset-btn');
  const deleteButton = root.querySelector('#barbers-delete-btn');
  const feedback = root.querySelector('#barbers-feedback');

  const idField = root.querySelector('#barbers-id');
  const nameField = root.querySelector('#barbers-name');
  const statusSelect = root.querySelector('#barbers-status');
  const positionField = root.querySelector('#barbers-position');

  const state = {
    currentId: null,
    results: [],
    lastQuery: '',
    loading: false,
  };

  async function init() {
    await loadBarbers();
    setForm(null);
  }

  function formatStatus(status) {
    return STATUS_LABELS[status] || status || 'Unknown';
  }

  function setFeedback(message, type = 'success') {
    if (!feedback) {
      return;
    }
    feedback.textContent = message;
    feedback.classList.toggle('is-success', type === 'success');
    feedback.classList.toggle('is-error', type === 'error');
    feedback.hidden = false;
  }

  function clearFeedback() {
    if (!feedback) {
      return;
    }
    feedback.textContent = '';
    feedback.classList.remove('is-success', 'is-error');
    feedback.hidden = true;
  }

  function setLoading(flag) {
    state.loading = flag;
    if (flag) {
      resultsList.innerHTML = '<li class="barbers-results__empty">Loading…</li>';
    }
  }

  function renderResults(barbers) {
    resultsList.innerHTML = '';

    if (!barbers.length) {
      resultsList.innerHTML = '<li class="barbers-results__empty">No barbers found.</li>';
      return;
    }

    barbers.forEach((barber) => {
      const item = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'barbers-result-button';
      button.dataset.barberId = String(barber.id);

      if (state.currentId && Number(state.currentId) === Number(barber.id)) {
        button.classList.add('is-active');
      }

      const name = document.createElement('span');
      name.className = 'barbers-result-name';
      name.textContent = barber.name || '(No name)';

      const meta = document.createElement('span');
      meta.className = 'barbers-result-meta';
      const metaParts = [];
      metaParts.push(formatStatus(barber.status));
      if (typeof barber.position === 'number' && barber.position > 0) {
        metaParts.push(`Position #${barber.position}`);
      }
      meta.textContent = metaParts.join(' · ');

      button.appendChild(name);
      button.appendChild(meta);
      item.appendChild(button);
      resultsList.appendChild(item);
    });
  }

  async function loadBarbers(query = '') {
    if (state.loading) {
      return;
    }

    state.lastQuery = query;
    setLoading(true);
    clearFeedback();

    try {
      const params = new URLSearchParams({ action: 'list' });
      if (query) {
        params.set('q', query);
      }

      const response = await fetch(`/api/barbers.php?${params.toString()}`, {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        throw new Error(`Failed to load barbers (${response.status})`);
      }

      const payload = await response.json();
      const barbers = Array.isArray(payload?.data) ? payload.data : [];
      state.results = barbers;
      renderResults(barbers);
    } catch (error) {
      console.error(error);
      setFeedback('Unable to load barbers. Please retry.', 'error');
      resultsList.innerHTML = '<li class="barbers-results__empty">Could not load barbers.</li>';
    } finally {
      state.loading = false;
    }
  }

  function findBarberById(barberId) {
    return state.results.find((barber) => Number(barber.id) === Number(barberId)) || null;
  }

  function setActiveResult(barberId) {
    const buttons = resultsList.querySelectorAll('.barbers-result-button');
    buttons.forEach((button) => {
      button.classList.toggle('is-active', Number(button.dataset.barberId) === Number(barberId));
    });
  }

  function setForm(barber) {
    clearFeedback();

    if (!barber) {
      state.currentId = null;
      idField.value = '';
      nameField.value = '';
      statusSelect.value = 'available';
      positionField.value = '';
      deleteButton.hidden = true;
      setActiveResult(null);
      return;
    }

    state.currentId = Number(barber.id);
    idField.value = state.currentId;
    nameField.value = barber.name || '';
    statusSelect.value = barber.status || 'available';
    positionField.value = barber.position != null ? barber.position : '';
    deleteButton.hidden = false;
    setActiveResult(barber.id);
  }

  async function handleSelect(barberId) {
    const barber = findBarberById(barberId);
    if (barber) {
      setForm(barber);
      return;
    }

    try {
      const params = new URLSearchParams({ action: 'get', barber_id: barberId });
      const response = await fetch(`/api/barbers.php?${params.toString()}`, {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        throw new Error(`Barber fetch failed (${response.status})`);
      }

      const payload = await response.json();
      if (payload?.data) {
        state.results = state.results.concat(payload.data);
        setForm(payload.data);
      }
    } catch (error) {
      console.error(error);
      setFeedback('Unable to load the requested barber.', 'error');
    }
  }

  async function handleSubmit(event) {
    event.preventDefault();

    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    const isNew = !state.currentId;
    const payload = {
      name: nameField.value.trim(),
      status: statusSelect.value,
    };

    if (payload.name === '') {
      setFeedback('Name is required.', 'error');
      nameField.focus();
      return;
    }

    const action = isNew ? 'create' : 'update';
    const body = isNew ? payload : { ...payload, barber_id: state.currentId };

    try {
      const response = await fetch(`/api/barbers.php?action=${action}`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });

      if (!response.ok) {
        const reason = await safeParseError(response);
        throw new Error(reason || 'Request failed');
      }

      const payloadResponse = await response.json();
      const barber = payloadResponse?.data;
      if (barber) {
        setFeedback(isNew ? 'Barber created successfully.' : 'Barber saved.');
        await loadBarbers(state.lastQuery);
        setForm(barber);
      } else {
        setFeedback('Request completed, but no barber data returned.', 'error');
      }
    } catch (error) {
      console.error(error);
      setFeedback(error.message || 'Unable to save barber.', 'error');
    }
  }

  async function handleDelete() {
    if (!state.currentId) {
      return;
    }
    const confirmed = window.confirm('Delete this barber? This cannot be undone.');
    if (!confirmed) {
      return;
    }

    try {
      const response = await fetch('/api/barbers.php?action=delete', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ barber_id: state.currentId }),
      });

      if (!response.ok) {
        const reason = await safeParseError(response);
        throw new Error(reason || 'Delete failed');
      }

      setFeedback('Barber deleted.');
      await loadBarbers(state.lastQuery);
      setForm(null);
    } catch (error) {
      console.error(error);
      setFeedback(error.message || 'Unable to delete barber.', 'error');
    }
  }

  function handleReset() {
    if (state.currentId) {
      const barber = findBarberById(state.currentId);
      setForm(barber || null);
    } else {
      setForm(null);
    }
  }

  async function safeParseError(response) {
    try {
      const payload = await response.json();
      return payload?.reason || payload?.error || null;
    } catch (error) {
      console.error('Failed to parse error response', error);
      return null;
    }
  }

  resultsList.addEventListener('click', (event) => {
    const button = event.target.closest('.barbers-result-button');
    if (!button) {
      return;
    }
    const id = button.dataset.barberId;
    if (id) {
      handleSelect(id);
    }
  });

  searchForm.addEventListener('submit', (event) => {
    event.preventDefault();
    loadBarbers(searchInput.value.trim());
  });

  newButton.addEventListener('click', () => setForm(null));
  resetButton.addEventListener('click', handleReset);
  deleteButton.addEventListener('click', handleDelete);
  form.addEventListener('submit', handleSubmit);

  init();
})();
