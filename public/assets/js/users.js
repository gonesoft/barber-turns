(function () {
  const root = document.querySelector('.view-users');
  if (!root) {
    return;
  }

  const ROLE_LABELS = {
    viewer: 'Viewer',
    frontdesk: 'Front-Desk',
    admin: 'Admin',
    owner: 'Owner',
  };

  const PROVIDER_LABELS = {
    google: 'Google OAuth',
    apple: 'Apple OAuth',
    local: 'Local',
  };

  const searchForm = root.querySelector('#users-search-form');
  const searchInput = root.querySelector('#users-search-input');
  const resultsList = root.querySelector('#users-results');
  const newButton = root.querySelector('#users-new-btn');
  const form = root.querySelector('#users-form');
  const resetButton = root.querySelector('#users-reset-btn');
  const deleteButton = root.querySelector('#users-delete-btn');
  const feedback = root.querySelector('#users-feedback');

  const idField = root.querySelector('#users-id');
  const nameField = root.querySelector('#users-name');
  const emailField = root.querySelector('#users-email');
  const usernameField = root.querySelector('#users-username');
  const roleSelect = root.querySelector('#users-role');
  const passwordField = root.querySelector('#users-password');
  const passwordHint = root.querySelector('#users-password-hint');
  const providerLabel = root.querySelector('#users-provider-label');

  const state = {
    currentId: null,
    provider: 'local',
    results: [],
    lastQuery: '',
    loading: false,
  };

  async function init() {
    await loadUsers();
    setForm(null);
  }

  function formatRole(role) {
    return ROLE_LABELS[role] || role || 'Unknown';
  }

  function formatProvider(provider) {
    const key = provider || 'local';
    return PROVIDER_LABELS[key] || key;
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
      resultsList.innerHTML = '<li class="users-results__empty">Loading…</li>';
    }
  }

  function renderResults(users) {
    resultsList.innerHTML = '';

    if (!users.length) {
      resultsList.innerHTML = '<li class="users-results__empty">No users found.</li>';
      return;
    }

    users.forEach((user) => {
      const item = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'users-result-button';
      button.dataset.userId = String(user.id);

      if (state.currentId && Number(state.currentId) === Number(user.id)) {
        button.classList.add('is-active');
      }

      const name = document.createElement('span');
      name.className = 'users-result-name';
      name.textContent = user.name || '(No name)';

      const meta = document.createElement('span');
      meta.className = 'users-result-meta';
      const metaParts = [];
      if (user.email) {
        metaParts.push(user.email);
      }
      metaParts.push(formatRole(user.role));
      if (user.username) {
        metaParts.push(`@${user.username}`);
      }
      meta.textContent = metaParts.join(' · ');

      button.appendChild(name);
      button.appendChild(meta);
      item.appendChild(button);
      resultsList.appendChild(item);
    });
  }

  async function loadUsers(query = '') {
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
      const response = await fetch(`/api/users.php?${params.toString()}`, {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        throw new Error(`Failed to load users (${response.status})`);
      }

      const payload = await response.json();
      const users = Array.isArray(payload?.data) ? payload.data : [];
      state.results = users;
      renderResults(users);
    } catch (error) {
      console.error(error);
      setFeedback('Unable to load users. Please retry.', 'error');
      resultsList.innerHTML = '<li class="users-results__empty">Could not load users.</li>';
    } finally {
      state.loading = false;
    }
  }

  function findUserById(userId) {
    return state.results.find((user) => Number(user.id) === Number(userId)) || null;
  }

  function updatePasswordState(provider, isNew) {
    const normalized = provider || 'local';
    state.provider = normalized;

    passwordField.value = '';
    if (isNew) {
      passwordField.disabled = false;
      passwordField.required = true;
      passwordField.placeholder = 'Enter a password';
      passwordHint.textContent = 'Required for new local users. Minimum 8 characters.';
      return;
    }

    if (normalized === 'local') {
      passwordField.disabled = false;
      passwordField.required = false;
      passwordField.placeholder = 'Leave blank to keep current password';
      passwordHint.textContent = 'Optional: leave blank to keep current password.';
    } else {
      passwordField.disabled = true;
      passwordField.required = false;
      passwordField.placeholder = 'Managed via OAuth';
      passwordHint.textContent = 'Password updates are managed by the OAuth provider.';
    }
  }

  function setActiveResult(userId) {
    const buttons = resultsList.querySelectorAll('.users-result-button');
    buttons.forEach((button) => {
      button.classList.toggle('is-active', Number(button.dataset.userId) === Number(userId));
    });
  }

  function setForm(user) {
    clearFeedback();

    if (!user) {
      state.currentId = null;
      state.provider = 'local';
      form.reset();
      idField.value = '';
      roleSelect.value = 'viewer';
      providerLabel.textContent = formatProvider('local');
      updatePasswordState('local', true);
      deleteButton.hidden = true;
      setActiveResult(null);
      return;
    }

    state.currentId = Number(user.id);
    idField.value = state.currentId;
    nameField.value = user.name || '';
    emailField.value = user.email || '';
    usernameField.value = user.username || '';
    roleSelect.value = user.role || 'viewer';
    providerLabel.textContent = formatProvider(user.oauth_provider);
    updatePasswordState(user.oauth_provider, false);
    deleteButton.hidden = false;
    setActiveResult(user.id);
  }

  async function handleSelect(userId) {
    const user = findUserById(userId);
    if (user) {
      setForm(user);
      return;
    }

    try {
      const params = new URLSearchParams({ action: 'get', user_id: userId });
      const response = await fetch(`/api/users.php?${params.toString()}`, {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });
      if (!response.ok) {
        throw new Error(`User fetch failed (${response.status})`);
      }
      const payload = await response.json();
      if (payload?.data) {
        state.results = state.results.concat(payload.data);
        setForm(payload.data);
      }
    } catch (error) {
      console.error(error);
      setFeedback('Unable to load the requested user.', 'error');
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
      email: emailField.value.trim(),
      role: roleSelect.value,
    };

    const username = usernameField.value.trim();
    if (username !== '') {
      payload.username = username;
    }

    if (!passwordField.disabled) {
      const password = passwordField.value.trim();
      if (password !== '') {
        payload.password = password;
      } else if (isNew) {
        setFeedback('Password is required for new users.', 'error');
        passwordField.focus();
        return;
      }
    }

    if (isNew) {
      payload.oauth_provider = 'local';
    }

    const action = isNew ? 'create' : 'update';
    const body = isNew ? payload : { ...payload, user_id: state.currentId };

    try {
      const response = await fetch(`/api/users.php?action=${action}`, {
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
      const user = payloadResponse?.data;
      if (user) {
        setFeedback(isNew ? 'User created successfully.' : 'User saved.');
        await loadUsers(state.lastQuery);
        setForm(user);
      } else {
        setFeedback('Request completed, but no user data returned.', 'error');
      }
    } catch (error) {
      console.error(error);
      setFeedback(error.message || 'Unable to save user.', 'error');
    }
  }

  async function handleDelete() {
    if (!state.currentId) {
      return;
    }
    const confirmed = window.confirm('Delete this user? This cannot be undone.');
    if (!confirmed) {
      return;
    }

    try {
      const response = await fetch('/api/users.php?action=delete', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: state.currentId }),
      });

      if (!response.ok) {
        const reason = await safeParseError(response);
        throw new Error(reason || 'Delete failed');
      }

      setFeedback('User deleted.');
      await loadUsers(state.lastQuery);
      setForm(null);
    } catch (error) {
      console.error(error);
      setFeedback(error.message || 'Unable to delete user.', 'error');
    }
  }

  function handleReset() {
    if (state.currentId) {
      const user = findUserById(state.currentId);
      setForm(user || null);
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
    const button = event.target.closest('.users-result-button');
    if (!button) {
      return;
    }
    const id = button.dataset.userId;
    if (id) {
      handleSelect(id);
    }
  });

  searchForm.addEventListener('submit', (event) => {
    event.preventDefault();
    loadUsers(searchInput.value.trim());
  });

  newButton.addEventListener('click', () => setForm(null));
  resetButton.addEventListener('click', handleReset);
  deleteButton.addEventListener('click', handleDelete);
  form.addEventListener('submit', handleSubmit);

  init();
})();
