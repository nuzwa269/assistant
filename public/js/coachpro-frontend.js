/**
 * CoachPro AI Assistant — Frontend JavaScript
 * Vanilla JS SPA rendered via [coachpro_*] shortcodes.
 *
 * @package CoachPro_AI_Assistant
 * @version 1.0.0
 */

(function () {
  'use strict';

  /* -----------------------------------------------------------------------
   * Bootstrap: find all [data-view] containers and render each one
   * --------------------------------------------------------------------- */
  function boot() {
    document.querySelectorAll('.coachpro-app[data-view]').forEach(function (el) {
      var raw = el.getAttribute('data-config') || '{}';
      var cfg = {};
      try { cfg = JSON.parse(raw); } catch (e) { console.error('CoachPro config parse error', e); }
      cfg.view = el.getAttribute('data-view') || cfg.view || 'dashboard';
      cfg.theme = el.getAttribute('data-theme') || cfg.theme || 'light';
      el.setAttribute('data-theme', cfg.theme);
      renderView(el, cfg);
    });
  }

  /* -----------------------------------------------------------------------
   * Router
   * --------------------------------------------------------------------- */
  function renderView(el, cfg) {
    el.innerHTML = '<div class="cp-loading">Loading…</div>';

    // If user not logged in and view requires auth, show login prompt
    var publicViews = ['login', 'register'];
    if (!cfg.wpUserId && publicViews.indexOf(cfg.view) === -1) {
      renderLogin(el, cfg);
      return;
    }

    switch (cfg.view) {
      case 'login':        renderLoginForm(el, cfg); break;
      case 'register':     renderRegisterForm(el, cfg); break;
      case 'dashboard':    renderDashboard(el, cfg); break;
      case 'chat':         renderChat(el, cfg); break;
      case 'projects':     renderProjects(el, cfg); break;
      case 'assistants':   renderAssistants(el, cfg); break;
      case 'saved':        renderSaved(el, cfg); break;
      case 'buy_credits':  renderBuyCredits(el, cfg); break;
      case 'settings':     renderSettings(el, cfg); break;
      case 'transactions': renderTransactions(el, cfg); break;
      default:             el.innerHTML = '<p class="cp-error">Unknown view: ' + escHtml(cfg.view) + '</p>';
    }
  }

  /* -----------------------------------------------------------------------
   * API helper
   * --------------------------------------------------------------------- */
  function api(cfg, path, method, body) {
    var url = cfg.restUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
    var opts = {
      method: method || 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.wpNonce,
      },
    };
    if (body) opts.body = JSON.stringify(body);
    return fetch(url, opts).then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok) return Promise.reject(data);
        return data;
      });
    });
  }

  /* -----------------------------------------------------------------------
   * Utilities
   * --------------------------------------------------------------------- */
  function escHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function el(tag, cls, html) {
    var d = document.createElement(tag);
    if (cls) d.className = cls;
    if (html !== undefined) d.innerHTML = html;
    return d;
  }

  function showError(container, msg) {
    var d = el('div', 'cp-error', escHtml(msg));
    container.appendChild(d);
  }

  function showSuccess(container, msg) {
    var d = el('div', 'cp-success', escHtml(msg));
    container.appendChild(d);
    setTimeout(function () { d.remove(); }, 4000);
  }

  function btn(label, cls) {
    var b = document.createElement('button');
    b.className = 'cp-btn ' + (cls || '');
    b.textContent = label;
    return b;
  }

  function input(type, placeholder, value) {
    var i = document.createElement('input');
    i.type = type || 'text';
    i.placeholder = placeholder || '';
    i.value = value || '';
    i.className = 'cp-input';
    return i;
  }

  function textarea(placeholder, value) {
    var t = document.createElement('textarea');
    t.placeholder = placeholder || '';
    t.value = value || '';
    t.className = 'cp-textarea';
    return t;
  }

  function select(options, selected) {
    var s = document.createElement('select');
    s.className = 'cp-select';
    options.forEach(function (o) {
      var opt = document.createElement('option');
      opt.value = o.value;
      opt.textContent = o.label;
      if (o.value === selected) opt.selected = true;
      s.appendChild(opt);
    });
    return s;
  }

  function navBar(cfg, activeView) {
    var nav = el('nav', 'cp-nav');
    var links = [
      { view: 'dashboard',   label: '🏠 Dashboard' },
      { view: 'projects',    label: '📁 Projects' },
      { view: 'assistants',  label: '🤖 Assistants' },
      { view: 'saved',       label: '🔖 Saved' },
      { view: 'buy_credits', label: '💳 Credits' },
      { view: 'settings',    label: '⚙ Settings' },
    ];
    links.forEach(function (lnk) {
      var a = document.createElement('a');
      a.href = '#';
      a.textContent = lnk.label;
      a.className = 'cp-nav-link' + (lnk.view === activeView ? ' active' : '');
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var parent = nav.closest('.coachpro-app');
        cfg.view = lnk.view;
        renderView(parent, cfg);
      });
      nav.appendChild(a);
    });

    // Logout
    var logoutBtn = document.createElement('a');
    logoutBtn.href = '#';
    logoutBtn.textContent = '🚪 Logout';
    logoutBtn.className = 'cp-nav-link cp-nav-logout';
    logoutBtn.addEventListener('click', function (e) {
      e.preventDefault();
      var fd = new FormData();
      fd.append('action', 'coachpro_logout');
      fd.append('nonce', cfg.wpNonce);
      fetch(cfg.ajaxUrl, { method: 'POST', body: fd }).then(function () {
        window.location.reload();
      });
    });
    nav.appendChild(logoutBtn);
    return nav;
  }

  /* -----------------------------------------------------------------------
   * View: Login prompt (for non-logged-in users on protected pages)
   * --------------------------------------------------------------------- */
  function renderLogin(el_container, cfg) {
    el_container.innerHTML = '';
    var wrap = el('div', 'cp-card cp-center');
    wrap.innerHTML = '<h2>Please log in</h2><p>You need to be logged in to use CoachPro AI.</p>';
    var loginBtn = btn('Login', 'cp-btn-primary');
    loginBtn.addEventListener('click', function () {
      cfg.view = 'login';
      renderView(el_container, cfg);
    });
    var regBtn = btn('Register');
    regBtn.addEventListener('click', function () {
      cfg.view = 'register';
      renderView(el_container, cfg);
    });
    wrap.appendChild(loginBtn);
    wrap.appendChild(regBtn);
    el_container.appendChild(wrap);
  }

  /* -----------------------------------------------------------------------
   * View: Login Form
   * --------------------------------------------------------------------- */
  function renderLoginForm(el_container, cfg) {
    el_container.innerHTML = '';
    var wrap = el('div', 'cp-card cp-auth-form');
    wrap.innerHTML = '<h2>🔐 Login to CoachPro AI</h2>';

    var usernameInput = input('text', 'Username or Email');
    var passwordInput = input('password', 'Password');
    var submitBtn     = btn('Login', 'cp-btn-primary cp-full');
    var errDiv        = el('div', 'cp-error cp-hidden');

    wrap.appendChild(usernameInput);
    wrap.appendChild(passwordInput);
    wrap.appendChild(errDiv);
    wrap.appendChild(submitBtn);

    var regLink = document.createElement('p');
    regLink.innerHTML = 'No account? <a href="#" class="cp-link">Register</a>';
    regLink.querySelector('a').addEventListener('click', function (e) {
      e.preventDefault();
      cfg.view = 'register';
      renderView(el_container, cfg);
    });
    wrap.appendChild(regLink);

    submitBtn.addEventListener('click', function () {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Logging in…';
      errDiv.classList.add('cp-hidden');

      var fd = new FormData();
      fd.append('action', 'coachpro_login');
      fd.append('nonce', cfg.wpNonce);
      fd.append('username', usernameInput.value);
      fd.append('password', passwordInput.value);

      fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            cfg.wpUserId = data.data.id;
            cfg.wpNonce  = data.data.nonce;
            cfg.view     = 'dashboard';
            renderView(el_container, cfg);
          } else {
            errDiv.textContent = data.data.message || 'Login failed.';
            errDiv.classList.remove('cp-hidden');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Login';
          }
        })
        .catch(function () {
          errDiv.textContent = 'Network error. Please try again.';
          errDiv.classList.remove('cp-hidden');
          submitBtn.disabled = false;
          submitBtn.textContent = 'Login';
        });
    });

    el_container.appendChild(wrap);
  }

  /* -----------------------------------------------------------------------
   * View: Register Form
   * --------------------------------------------------------------------- */
  function renderRegisterForm(el_container, cfg) {
    el_container.innerHTML = '';
    var wrap = el('div', 'cp-card cp-auth-form');
    wrap.innerHTML = '<h2>📝 Create Account</h2>';

    var unameInput  = input('text', 'Username');
    var emailInput  = input('email', 'Email');
    var passInput   = input('password', 'Password');
    var submitBtn   = btn('Register', 'cp-btn-primary cp-full');
    var errDiv      = el('div', 'cp-error cp-hidden');

    [unameInput, emailInput, passInput, errDiv, submitBtn].forEach(function (node) {
      wrap.appendChild(node);
    });

    var loginLink = document.createElement('p');
    loginLink.innerHTML = 'Have an account? <a href="#" class="cp-link">Login</a>';
    loginLink.querySelector('a').addEventListener('click', function (e) {
      e.preventDefault();
      cfg.view = 'login';
      renderView(el_container, cfg);
    });
    wrap.appendChild(loginLink);

    submitBtn.addEventListener('click', function () {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Registering…';
      errDiv.classList.add('cp-hidden');

      var fd = new FormData();
      fd.append('action', 'coachpro_register');
      fd.append('nonce', cfg.wpNonce);
      fd.append('username', unameInput.value);
      fd.append('email', emailInput.value);
      fd.append('password', passInput.value);

      fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            cfg.wpUserId = data.data.id;
            cfg.wpNonce  = data.data.nonce;
            cfg.view     = 'dashboard';
            renderView(el_container, cfg);
          } else {
            errDiv.textContent = data.data.message || 'Registration failed.';
            errDiv.classList.remove('cp-hidden');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Register';
          }
        });
    });

    el_container.appendChild(wrap);
  }

  /* -----------------------------------------------------------------------
   * View: Dashboard
   * --------------------------------------------------------------------- */
  function renderDashboard(el_container, cfg) {
    el_container.innerHTML = '';
    el_container.appendChild(navBar(cfg, 'dashboard'));

    var main = el('div', 'cp-main');
    el_container.appendChild(main);

    api(cfg, 'auth/me').then(function (data) {
      var user = data;
      main.innerHTML = '';

      var heading = el('h2', 'cp-heading', '👋 Welcome, ' + escHtml(user.name || user.username) + '!');
      main.appendChild(heading);

      // Stats cards
      var stats = el('div', 'cp-stats-grid');
      var statItems = [
        { label: 'Credits', value: user.credits, icon: '💰' },
        { label: 'Plan',    value: (user.plan || 'free').toUpperCase(), icon: '🏅' },
      ];
      statItems.forEach(function (s) {
        var card = el('div', 'cp-stat-card');
        card.innerHTML = '<div class="cp-stat-icon">' + s.icon + '</div>' +
          '<div class="cp-stat-value">' + escHtml(String(s.value)) + '</div>' +
          '<div class="cp-stat-label">' + escHtml(s.label) + '</div>';
        stats.appendChild(card);
      });
      main.appendChild(stats);

      // Quick actions
      var actions = el('div', 'cp-actions');
      actions.innerHTML = '<h3>Quick Actions</h3>';
      var actList = [
        { label: '📁 My Projects',  view: 'projects' },
        { label: '🤖 Assistants',   view: 'assistants' },
        { label: '💳 Buy Credits',  view: 'buy_credits' },
        { label: '⚙ Settings',     view: 'settings' },
      ];
      actList.forEach(function (a) {
        var b = btn(a.label, 'cp-btn-outline');
        b.addEventListener('click', function () {
          cfg.view = a.view;
          renderView(el_container, cfg);
        });
        actions.appendChild(b);
      });
      main.appendChild(actions);

      // Recent conversations
      api(cfg, 'conversations?per_page=5').then(function (convs) {
        if (!Array.isArray(convs) || !convs.length) return;
        var section = el('div', 'cp-section');
        section.innerHTML = '<h3>Recent Conversations</h3>';
        var list = el('ul', 'cp-list');
        convs.slice(0, 5).forEach(function (c) {
          var li = el('li', 'cp-list-item', escHtml(c.title));
          list.appendChild(li);
        });
        section.appendChild(list);
        main.appendChild(section);
      }).catch(function () {});

    }).catch(function (err) {
      showError(main, (err && err.message) || 'Failed to load profile.');
    });
  }

  /* -----------------------------------------------------------------------
   * View: Projects
   * --------------------------------------------------------------------- */
  function renderProjects(el_container, cfg) {
    el_container.innerHTML = '';
    el_container.appendChild(navBar(cfg, 'projects'));

    var main = el('div', 'cp-main');
    el_container.appendChild(main);
    main.innerHTML = '<div class="cp-loading">Loading projects…</div>';

    api(cfg, 'projects').then(function (projects) {
      main.innerHTML = '';
      var header = el('div', 'cp-section-header');
      header.innerHTML = '<h2>📁 My Projects</h2>';
      var newBtn = btn('+ New Project', 'cp-btn-primary');
      header.appendChild(newBtn);
      main.appendChild(header);

      // New project form (hidden)
      var formWrap = el('div', 'cp-form-wrap cp-hidden');
      var nameIn   = input('text', 'Project name');
      var descIn   = textarea('Description (optional)');
      var saveBtn  = btn('Create', 'cp-btn-primary');
      var cancelBtn = btn('Cancel', '');
      formWrap.appendChild(nameIn);
      formWrap.appendChild(descIn);
      formWrap.appendChild(saveBtn);
      formWrap.appendChild(cancelBtn);
      main.appendChild(formWrap);

      newBtn.addEventListener('click', function () {
        formWrap.classList.toggle('cp-hidden');
        nameIn.focus();
      });
      cancelBtn.addEventListener('click', function () {
        formWrap.classList.add('cp-hidden');
      });
      saveBtn.addEventListener('click', function () {
        if (!nameIn.value.trim()) return;
        api(cfg, 'projects', 'POST', { name: nameIn.value.trim(), description: descIn.value }).then(function () {
          renderProjects(el_container, cfg);
        }).catch(function (e) {
          showError(main, (e && e.message) || 'Failed to create project.');
        });
      });

      if (!projects.length) {
        main.appendChild(el('p', 'cp-empty', 'No projects yet. Create your first project!'));
        return;
      }

      var grid = el('div', 'cp-cards-grid');
      projects.forEach(function (p) {
        var card = el('div', 'cp-card cp-project-card');
        card.innerHTML = '<h3>' + escHtml(p.name) + '</h3>' +
          '<p>' + escHtml(p.description || '') + '</p>' +
          '<small>Created: ' + escHtml(p.created_at || '') + '</small>';

        var chatBtn = btn('💬 Chat', 'cp-btn-primary cp-btn-sm');
        chatBtn.addEventListener('click', function () {
          cfg.view = 'chat';
          cfg.projectId = p.id;
          renderView(el_container, cfg);
        });
        var delBtn = btn('🗑', 'cp-btn-danger cp-btn-sm');
        delBtn.addEventListener('click', function () {
          if (!confirm('Delete project "' + p.name + '"?')) return;
          api(cfg, 'projects/' + p.id, 'DELETE').then(function () {
            renderProjects(el_container, cfg);
          });
        });
        var actions = el('div', 'cp-card-actions');
        actions.appendChild(chatBtn);
        actions.appendChild(delBtn);
        card.appendChild(actions);
        grid.appendChild(card);
      });
      main.appendChild(grid);
    }).catch(function (e) {
      showError(main, (e && e.message) || 'Failed to load projects.');
    });
  }

  /* -----------------------------------------------------------------------
   * View: Assistants
   * --------------------------------------------------------------------- */
  function renderAssistants(el_container, cfg) {
    el_container.innerHTML = '';
    el_container.appendChild(navBar(cfg, 'assistants'));

    var main = el('div', 'cp-main');
    el_container.appendChild(main);
    main.innerHTML = '<div class="cp-loading">Loading assistants…</div>';

    api(cfg, 'assistants').then(function (assistants) {
      main.innerHTML = '';
      var header = el('div', 'cp-section-header');
      header.innerHTML = '<h2>🤖 Assistants</h2>';

      var newBtn = btn('+ Create Assistant', 'cp-btn-primary');
      header.appendChild(newBtn);
      main.appendChild(header);

      // Create form
      var formWrap  = el('div', 'cp-form-wrap cp-hidden');
      var nameIn    = input('text', 'Assistant name');
      var descIn    = textarea('Short description');
      var promptIn  = textarea('System prompt (instructions for the AI)');
      promptIn.rows = 6;
      var saveBtn   = btn('Create', 'cp-btn-primary');
      var cancelBtn = btn('Cancel', '');
      [nameIn, descIn, promptIn, saveBtn, cancelBtn].forEach(function (n) { formWrap.appendChild(n); });
      main.appendChild(formWrap);

      newBtn.addEventListener('click', function () { formWrap.classList.toggle('cp-hidden'); });
      cancelBtn.addEventListener('click', function () { formWrap.classList.add('cp-hidden'); });
      saveBtn.addEventListener('click', function () {
        api(cfg, 'assistants', 'POST', {
          name: nameIn.value,
          description: descIn.value,
          system_prompt: promptIn.value,
        }).then(function () { renderAssistants(el_container, cfg); })
          .catch(function (e) { showError(main, (e && e.message) || 'Failed to create assistant.'); });
      });

      var grid = el('div', 'cp-cards-grid');
      assistants.forEach(function (a) {
        var activated = String(a.is_activated) === '1' || a.is_activated === true;
        var card = el('div', 'cp-card cp-assistant-card');
        card.innerHTML = '<div class="cp-assistant-icon">' + escHtml(a.icon || '🤖') + '</div>' +
          '<h3>' + escHtml(a.name) + (a.is_prebuilt ? ' <span class="cp-badge">Prebuilt</span>' : '') + '</h3>' +
          '<p>' + escHtml(a.description || '') + '</p>';

        var toggleBtn = btn(activated ? '✅ Activated' : 'Activate', activated ? 'cp-btn-outline' : 'cp-btn-primary');
        toggleBtn.addEventListener('click', function () {
          if (activated) {
            api(cfg, 'assistants/' + a.id + '/activate', 'DELETE').then(function () { renderAssistants(el_container, cfg); });
          } else {
            api(cfg, 'assistants/' + a.id + '/activate', 'POST').then(function () { renderAssistants(el_container, cfg); })
              .catch(function (e) { showError(main, (e && e.message) || 'Failed.'); });
          }
        });

        var cardActions = el('div', 'cp-card-actions');
        cardActions.appendChild(toggleBtn);

        if (!a.is_prebuilt) {
          var delBtn = btn('🗑', 'cp-btn-danger cp-btn-sm');
          delBtn.addEventListener('click', function () {
            if (!confirm('Delete assistant?')) return;
            api(cfg, 'assistants/' + a.id, 'DELETE').then(function () { renderAssistants(el_container, cfg); });
          });
          cardActions.appendChild(delBtn);
        }
        card.appendChild(cardActions);
        grid.appendChild(card);
      });
      main.appendChild(grid);
    }).catch(function (e) {
      showError(main, (e && e.message) || 'Failed to load assistants.');
    });
  }

  /* -----------------------------------------------------------------------
   * View: Chat
   * --------------------------------------------------------------------- */
  function renderChat(el_container, cfg) {
    el_container.innerHTML = '';
    el_container.appendChild(navBar(cfg, 'chat'));

    var main = el('div', 'cp-main cp-chat-layout');
    el_container.appendChild(main);

    var state = { convId: null, messages: [], modelId: null, assistantId: null, projectId: cfg.projectId || null };

    // Sidebar: project + assistant + model selectors
    var sidebar = el('div', 'cp-chat-sidebar');
    var chatArea = el('div', 'cp-chat-area');
    main.appendChild(sidebar);
    main.appendChild(chatArea);

    // Load projects for selector
    Promise.all([
      api(cfg, 'projects'),
      api(cfg, 'assistants'),
      api(cfg, 'conversations'),
    ]).then(function (results) {
      var projects    = results[0] || [];
      var assistants  = (results[1] || []).filter(function (a) { return String(a.is_activated) === '1' || a.is_activated === true; });
      var convs       = results[2] || [];

      sidebar.innerHTML = '<h3>💬 Chat</h3>';

      // Project select
      if (projects.length) {
        var projOpts = projects.map(function (p) { return { value: p.id, label: p.name }; });
        projOpts.unshift({ value: '', label: '— Select Project —' });
        var projSel = select(projOpts, state.projectId || '');
        projSel.addEventListener('change', function () { state.projectId = this.value; loadConversations(); });
        sidebar.appendChild(el('label', 'cp-label', 'Project'));
        sidebar.appendChild(projSel);
      }

      // Assistant select
      var asstOpts = assistants.map(function (a) { return { value: a.id, label: a.name }; });
      asstOpts.unshift({ value: '', label: '— Select Assistant —' });
      var asstSel = select(asstOpts, '');
      asstSel.addEventListener('change', function () { state.assistantId = this.value; });
      sidebar.appendChild(el('label', 'cp-label', 'Assistant'));
      sidebar.appendChild(asstSel);

      // New conversation button
      var newConvBtn = btn('+ New Chat', 'cp-btn-primary cp-full');
      newConvBtn.addEventListener('click', function () {
        if (!state.projectId || !state.assistantId) {
          alert('Please select a project and assistant first.');
          return;
        }
        api(cfg, 'conversations', 'POST', {
          project_id:   state.projectId,
          assistant_id: state.assistantId,
          title:        'New conversation',
        }).then(function (conv) {
          state.convId   = conv.id;
          state.messages = [];
          renderChatMessages(chatArea, cfg, state);
          loadConversations();
        });
      });
      sidebar.appendChild(newConvBtn);

      // Conversations list
      var convList = el('div', 'cp-conv-list');
      sidebar.appendChild(convList);

      function loadConversations() {
        var path = 'conversations';
        if (state.projectId) path += '?project_id=' + encodeURIComponent(state.projectId);
        api(cfg, path).then(function (list) {
          convList.innerHTML = '';
          (list || []).forEach(function (c) {
            var item = el('div', 'cp-conv-item' + (c.id === state.convId ? ' active' : ''), escHtml(c.title || 'Untitled'));
            item.addEventListener('click', function () {
              state.convId = c.id;
              api(cfg, 'conversations/' + c.id + '/messages').then(function (msgs) {
                state.messages = msgs || [];
                renderChatMessages(chatArea, cfg, state);
                document.querySelectorAll('.cp-conv-item').forEach(function (el) { el.classList.remove('active'); });
                item.classList.add('active');
              });
            });
            convList.appendChild(item);
          });
        });
      }

      // Load active assistant's model
      api(cfg, 'auth/me').then(function (u) {
        state.userId = u.id;
        state.credits = u.credits;
      }).catch(function () {});

      // Default model
      api(cfg, 'admin/models').then(function (models) {
        if (models && models.length) state.modelId = models[0].id;
      }).catch(function () {
        // Fallback — try public endpoint is not available, use default
        state.modelId = 'gpt-4o-mini';
      });

      loadConversations();
      chatArea.innerHTML = '<p class="cp-empty">Select a conversation or create a new one.</p>';
    });
  }

  function renderChatMessages(chatArea, cfg, state) {
    chatArea.innerHTML = '';

    var msgList = el('div', 'cp-msg-list');
    chatArea.appendChild(msgList);

    function renderMessages() {
      msgList.innerHTML = '';
      state.messages.forEach(function (m) {
        var bubble = el('div', 'cp-msg cp-msg-' + escHtml(m.role));
        bubble.innerHTML = '<div class="cp-msg-content">' + escHtml(m.content) + '</div>' +
          '<div class="cp-msg-meta">' + escHtml(m.role) + (m.model_id ? ' · ' + escHtml(m.model_id) : '') + '</div>';

        if (m.role === 'assistant') {
          var saveBtn = document.createElement('button');
          saveBtn.className = 'cp-save-btn';
          saveBtn.textContent = '🔖 Save';
          saveBtn.addEventListener('click', function () {
            api(cfg, 'saved-responses', 'POST', { message_id: m.id }).then(function () {
              saveBtn.textContent = '✅ Saved';
            }).catch(function (e) {
              alert((e && e.message) || 'Failed to save.');
            });
          });
          bubble.appendChild(saveBtn);
        }
        msgList.appendChild(bubble);
      });
      msgList.scrollTop = msgList.scrollHeight;
    }

    renderMessages();

    // Input area
    var inputArea = el('div', 'cp-chat-input-area');
    var msgInput  = textarea('Type your message…');
    msgInput.rows = 3;
    var sendBtn   = btn('Send ➤', 'cp-btn-primary');
    inputArea.appendChild(msgInput);
    inputArea.appendChild(sendBtn);
    chatArea.appendChild(inputArea);

    sendBtn.addEventListener('click', function () {
      var text = msgInput.value.trim();
      if (!text || !state.convId) return;

      sendBtn.disabled = true;
      sendBtn.textContent = 'Sending…';

      var userMsg = { role: 'user', content: text, id: 'tmp-' + Date.now() };
      state.messages.push(userMsg);
      renderMessages();
      msgInput.value = '';

      api(cfg, 'chat', 'POST', {
        conversation_id: state.convId,
        model_id:        state.modelId || 'gpt-4o-mini',
        message:         text,
      }).then(function (resp) {
        state.messages.push({
          id:      resp.message_id,
          role:    'assistant',
          content: resp.content,
          model_id: resp.model_id,
        });
        renderMessages();
        sendBtn.disabled = false;
        sendBtn.textContent = 'Send ➤';
      }).catch(function (e) {
        alert((e && e.message) || 'Chat failed. Check your credits or API key.');
        sendBtn.disabled = false;
        sendBtn.textContent = 'Send ➤';
      });
    });

    // Allow Enter to send (Shift+Enter for newline)
    msgInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendBtn.click();
      }
    });
  }

  /* -----------------------------------------------------------------------
   * View: Saved Responses
   * --------------------------------------------------------------------- */
  function renderSaved(el_container, cfg) {
    el_container.innerHTML = '';
    el_container.appendChild(navBar(cfg, 'saved'));

    var main = el('div', 'cp-main');
    el_container.appendChild(main);
    main.innerHTML = '<div class="cp-loading">Loading…</div>';

    api(cfg, 'saved-responses').then(function (rows) {
      main.innerHTML = '';
      main.appendChild(el('h2', 'cp-heading', '🔖 Saved Responses'));

      if (!rows || !rows.length) {
        main.appendChild(el('p', 'cp-empty', 'No saved responses yet. Save AI replies from the chat view.'));
        return;
      }

      rows.forEach(function (r) {
        var card = el('div', 'cp-card cp-saved-card');
        card.innerHTML = '<div class="cp-saved-content">' + escHtml(r.content || '') + '</div>' +
          '<div class="cp-saved-meta">' +
          (r.note ? '<em>' + escHtml(r.note) + '</em> · ' : '') +
          escHtml(r.created_at || '') + '</div>';

        var delBtn = btn('🗑 Remove', 'cp-btn-danger cp-btn-sm');
        delBtn.addEventListener('click', function () {
          api(cfg, 'saved-responses/' + r.id, 'DELETE').then(function () { renderSaved(el_container, cfg); });
        });
        card.appendChild(delBtn);
        main.appendChild(card);
      });
    }).catch(function (e) {
      showError(main, (e && e.message) || 'Failed to load.');
    });
  }

  /* -----------------------------------------------------------------------
   * View: Buy Credits
   * --------------------------------------------------------------------- */
  function renderBuyCredits(el_container, cfg) {
    el_container.innerHTML = '';
    el_container.appendChild(navBar(cfg, 'buy_credits'));

    var main = el('div', 'cp-main');
    el_container.appendChild(main);
    main.innerHTML = '<div class="cp-loading">Loading…</div>';

    Promise.all([
      api(cfg, 'plans'),
      api(cfg, 'credit-packs'),
    ]).then(function (results) {
      var plans = results[0] || [];
      var packs = results[1] || [];

      main.innerHTML = '';
      main.appendChild(el('h2', 'cp-heading', '💳 Subscription Plans'));

      // Plans grid
      var plansGrid = el('div', 'cp-plans-grid');
      plans.forEach(function (plan) {
        var features = [];
        try { features = JSON.parse(plan.features || '[]'); } catch (e) {}
        var card = el('div', 'cp-plan-card' + (plan.is_popular ? ' cp-plan-popular' : ''));
        if (plan.is_popular) card.innerHTML = '<div class="cp-plan-badge">Most Popular</div>';
        card.innerHTML += '<h3>' + escHtml(plan.name) + '</h3>' +
          '<div class="cp-plan-price">₨ ' + escHtml(String(plan.price_pkr)) + '/mo</div>' +
          '<div class="cp-plan-credits">' + escHtml(String(plan.monthly_credits)) + ' credits/month</div>' +
          '<ul class="cp-plan-features">' + features.map(function (f) { return '<li>' + escHtml(f) + '</li>'; }).join('') + '</ul>';

        if (plan.price_pkr > 0) {
          var subBtn = btn('Subscribe', 'cp-btn-primary cp-full');
          subBtn.addEventListener('click', function () {
            showPaymentForm(main, cfg, 'subscription', plan.id, null, plan.price_pkr, plan.name);
          });
          card.appendChild(subBtn);
        } else {
          card.appendChild(el('div', 'cp-plan-free-note', 'Current free plan'));
        }
        plansGrid.appendChild(card);
      });
      main.appendChild(plansGrid);

      // Credit packs
      main.appendChild(el('h2', 'cp-heading', '🪙 Credit Packs'));
      var packsGrid = el('div', 'cp-plans-grid');
      packs.forEach(function (pack) {
        var card = el('div', 'cp-plan-card' + (pack.is_popular ? ' cp-plan-popular' : ''));
        if (pack.is_popular) card.innerHTML = '<div class="cp-plan-badge">Best Value</div>';
        card.innerHTML += '<h3>' + escHtml(pack.name) + '</h3>' +
          '<div class="cp-plan-price">₨ ' + escHtml(String(pack.price_pkr)) + '</div>' +
          '<div class="cp-plan-credits">' + escHtml(String(pack.credits)) + ' credits</div>';
        var buyBtn = btn('Buy Pack', 'cp-btn-primary cp-full');
        buyBtn.addEventListener('click', function () {
          showPaymentForm(main, cfg, 'credit_pack', null, pack.id, pack.price_pkr, pack.name);
        });
        card.appendChild(buyBtn);
        packsGrid.appendChild(card);
      });
      main.appendChild(packsGrid);

      // Payment info (JazzCash / EasyPaisa)
      var infoSection = el('div', 'cp-payment-info');
      infoSection.innerHTML = '<h3>📲 How to Pay</h3>' +
        '<ol>' +
        '<li>Choose a plan or pack above and click the button.</li>' +
        '<li>Send payment via JazzCash / EasyPaisa / Bank Transfer to the numbers shown.</li>' +
        '<li>Fill in the payment form with your transaction details.</li>' +
        '<li>Admin will approve within 24 hours and your credits will be added.</li>' +
        '</ol>';
      main.appendChild(infoSection);

    }).catch(function (e) {
      showError(main, (e && e.message) || 'Failed to load.');
    });
  }

  function showPaymentForm(container, cfg, kind, planId, packId, amount, itemName) {
    // Scroll to a payment form rendered at the bottom
    var existing = container.querySelector('.cp-payment-form');
    if (existing) existing.remove();

    var form = el('div', 'cp-card cp-payment-form');
    form.innerHTML = '<h3>💳 Payment for: ' + escHtml(itemName) + '</h3>' +
      '<p>Amount: <strong>₨ ' + escHtml(String(amount)) + '</strong></p>';

    var methodSel = select([
      { value: 'jazzcash',      label: 'JazzCash' },
      { value: 'easypaisa',     label: 'EasyPaisa' },
      { value: 'bank_transfer', label: 'Bank Transfer' },
    ], 'jazzcash');
    var senderName  = input('text', 'Your name');
    var senderPhone = input('tel',  'Your phone / JazzCash / EasyPaisa number');
    var refNo       = input('text', 'Transaction / Reference number');
    var notes       = textarea('Additional notes (optional)');
    var submitBtn   = btn('Submit Payment Request', 'cp-btn-primary cp-full');
    var errDiv      = el('div', 'cp-error cp-hidden');
    var successDiv  = el('div', 'cp-success cp-hidden');

    [methodSel, senderName, senderPhone, refNo, notes, errDiv, successDiv, submitBtn].forEach(function (n) {
      form.appendChild(n);
    });

    submitBtn.addEventListener('click', function () {
      submitBtn.disabled = true;
      errDiv.classList.add('cp-hidden');

      api(cfg, 'payments', 'POST', {
        kind:         kind,
        plan_id:      planId,
        pack_id:      packId,
        amount_pkr:   amount,
        method:       methodSel.value,
        sender_name:  senderName.value,
        sender_phone: senderPhone.value,
        reference_no: refNo.value,
        notes:        notes.value,
      }).then(function () {
        successDiv.textContent = '✅ Payment request submitted! Admin will review within 24 hours.';
        successDiv.classList.remove('cp-hidden');
        submitBtn.remove();
      }).catch(function (e) {
        errDiv.textContent = (e && e.message) || 'Failed to submit payment.';
        errDiv.classList.remove('cp-hidden');
        submitBtn.disabled = false;
      });
    });

    container.appendChild(form);
    form.scrollIntoView({ behavior: 'smooth' });
  }

  /* -----------------------------------------------------------------------
   * View: Settings
   * --------------------------------------------------------------------- */
  function renderSettings(el_container, cfg) {
    el_container.innerHTML = '';
    el_container.appendChild(navBar(cfg, 'settings'));

    var main = el('div', 'cp-main');
    el_container.appendChild(main);
    main.innerHTML = '<div class="cp-loading">Loading…</div>';

    api(cfg, 'profile').then(function (user) {
      main.innerHTML = '';
      main.appendChild(el('h2', 'cp-heading', '⚙ Profile Settings'));

      var form = el('div', 'cp-card');

      var nameIn  = input('text', 'Display name', user.name || '');
      var emailIn = input('email', 'Email', user.email || '');
      var passIn  = input('password', 'New password (leave blank to keep current)');
      var saveBtn = btn('Save Changes', 'cp-btn-primary');
      var errDiv  = el('div', 'cp-error cp-hidden');

      [el('label', 'cp-label', 'Display Name'), nameIn,
       el('label', 'cp-label', 'Email'), emailIn,
       el('label', 'cp-label', 'Password'), passIn,
       errDiv, saveBtn].forEach(function (n) { form.appendChild(n); });

      saveBtn.addEventListener('click', function () {
        var payload = { display_name: nameIn.value, email: emailIn.value };
        if (passIn.value) payload.password = passIn.value;
        api(cfg, 'profile', 'PUT', payload).then(function () {
          errDiv.classList.add('cp-hidden');
          showSuccess(form, 'Profile updated!');
        }).catch(function (e) {
          errDiv.textContent = (e && e.message) || 'Failed to update.';
          errDiv.classList.remove('cp-hidden');
        });
      });

      main.appendChild(form);

      // Credit info
      var infoCard = el('div', 'cp-card');
      infoCard.innerHTML = '<h3>Account Info</h3>' +
        '<p><strong>Plan:</strong> ' + escHtml((user.plan || 'free').toUpperCase()) + '</p>' +
        '<p><strong>Credits:</strong> ' + escHtml(String(user.credits)) + '</p>';
      main.appendChild(infoCard);

    }).catch(function (e) {
      showError(main, (e && e.message) || 'Failed to load profile.');
    });
  }

  /* -----------------------------------------------------------------------
   * View: Transactions
   * --------------------------------------------------------------------- */
  function renderTransactions(el_container, cfg) {
    el_container.innerHTML = '';
    el_container.appendChild(navBar(cfg, 'transactions'));

    var main = el('div', 'cp-main');
    el_container.appendChild(main);
    main.innerHTML = '<div class="cp-loading">Loading…</div>';

    api(cfg, 'transactions').then(function (rows) {
      main.innerHTML = '';
      main.appendChild(el('h2', 'cp-heading', '💰 Credit History'));

      if (!rows || !rows.length) {
        main.appendChild(el('p', 'cp-empty', 'No transactions yet.'));
        return;
      }

      var table = document.createElement('table');
      table.className = 'cp-table';
      table.innerHTML = '<thead><tr>' +
        '<th>Date</th><th>Type</th><th>Amount</th><th>Balance</th><th>Notes</th>' +
        '</tr></thead>';
      var tbody = document.createElement('tbody');
      rows.forEach(function (tx) {
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td>' + escHtml(tx.created_at || '') + '</td>' +
          '<td>' + escHtml(tx.kind || '') + '</td>' +
          '<td class="' + (tx.amount > 0 ? 'cp-pos' : 'cp-neg') + '">' + (tx.amount > 0 ? '+' : '') + escHtml(String(tx.amount)) + '</td>' +
          '<td>' + escHtml(String(tx.balance_after)) + '</td>' +
          '<td>' + escHtml(tx.notes || '') + '</td>';
        tbody.appendChild(tr);
      });
      table.appendChild(tbody);
      main.appendChild(table);
    }).catch(function (e) {
      showError(main, (e && e.message) || 'Failed to load transactions.');
    });
  }

  /* -----------------------------------------------------------------------
   * Init
   * --------------------------------------------------------------------- */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

}());
