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
      case 'help':         renderHelp(el, cfg); break;
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

  function googleOauthUrl(cfg) {
    try {
      var url = new URL(String(cfg.restUrl || '').replace(/\/+$/, '') + '/auth/google', window.location.origin);
      url.searchParams.set('redirect', window.location.origin + window.location.pathname + window.location.search + window.location.hash);
      return url.toString();
    } catch (e) {
      return '';
    }
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
      { view: 'help',        label: '❓ Help' },
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
        if (cfg.pageUrls && cfg.pageUrls[lnk.view]) {
          window.location.href = cfg.pageUrls[lnk.view];
          return;
        }
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
        if (cfg.pageUrls && cfg.pageUrls.login) {
          window.location.href = cfg.pageUrls.login;
        } else {
          window.location.reload();
        }
      });
    });
    nav.appendChild(logoutBtn);
    return nav;
  }

  /* -----------------------------------------------------------------------
   * View: Login prompt (for non-logged-in users on protected pages)
   * --------------------------------------------------------------------- */
  function renderLogin(el_container, cfg) {
    if (cfg.pageUrls && cfg.pageUrls.login && window.location.href !== cfg.pageUrls.login) {
      window.location.href = cfg.pageUrls.login + '?redirect_to=' + encodeURIComponent(window.location.href);
      return;
    }
    renderLoginForm(el_container, cfg);
  }

  /* -----------------------------------------------------------------------
   * View: Login Form
   * --------------------------------------------------------------------- */
  function renderLoginForm(el_container, cfg) {
    el_container.innerHTML = '';
    var wrap = el('div', 'cp-auth-wrap');

    var brand = el('div', 'cp-auth-brand');
    brand.innerHTML =
      '<div class="cp-auth-logo">🎓</div>' +
      '<h1 class="cp-auth-title">CoachPro AI</h1>';
    wrap.appendChild(brand);

    var card = el('div', 'cp-auth-card');
    card.innerHTML =
      '<h2 class="cp-auth-heading">Welcome back</h2>' +
      '<p class="cp-auth-sub">Sign in to continue your AI-powered coaching journey.</p>';

    if (cfg.googleClientId) {
      var googleBtn = el('button', 'cp-btn-google');
      googleBtn.type = 'button';
      googleBtn.innerHTML =
        '<svg class="cp-google-icon" viewBox="0 0 24 24" width="20" height="20">' +
        '<path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>' +
        '<path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>' +
        '<path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>' +
        '<path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>' +
        '</svg>' +
        'Continue with Google';
      googleBtn.addEventListener('click', function () {
        var oauthUrl = googleOauthUrl(cfg);
        if (oauthUrl) {
          window.location.href = oauthUrl;
        }
      });
      card.appendChild(googleBtn);

      var divider = el('div', 'cp-auth-divider');
      divider.innerHTML = '<span>OR SIGN IN WITH EMAIL</span>';
      card.appendChild(divider);
    }

    var emailLabel = el('label', 'cp-field-label', 'Email');
    var emailInput = input('email', 'you@example.com');
    emailInput.autocomplete = 'email';

    var passLabel = el('label', 'cp-field-label', 'Password');
    var passWrap = el('div', 'cp-pass-wrap');
    var passInput = input('password', '••••••••');
    passInput.autocomplete = 'current-password';
    var passToggle = el('button', 'cp-pass-toggle', '👁');
    passToggle.type = 'button';
    passToggle.title = 'Show/hide password';
    passToggle.addEventListener('click', function () {
      passInput.type = passInput.type === 'password' ? 'text' : 'password';
      passToggle.textContent = passInput.type === 'password' ? '👁' : '🙈';
    });
    passWrap.appendChild(passInput);
    passWrap.appendChild(passToggle);

    var errDiv   = el('div', 'cp-error cp-hidden');
    var submitBtn = btn('Sign In', 'cp-btn-primary cp-full cp-btn-lg');

    [emailLabel, emailInput, passLabel, passWrap, errDiv, submitBtn].forEach(function (n) {
      card.appendChild(n);
    });

    var regLink = el('p', 'cp-auth-switch');
    regLink.innerHTML = 'Don\'t have an account? <a href="#" class="cp-link">Sign up</a>';
    regLink.querySelector('a').addEventListener('click', function (e) {
      e.preventDefault();
      if (cfg.pageUrls && cfg.pageUrls.register) {
        window.location.href = cfg.pageUrls.register;
      } else {
        cfg.view = 'register';
        renderView(el_container, cfg);
      }
    });
    card.appendChild(regLink);

    wrap.appendChild(card);
    el_container.appendChild(wrap);

    submitBtn.addEventListener('click', function () {
      var emailVal = emailInput.value.trim();
      var passVal  = passInput.value;
      if (!emailVal || !passVal) {
        errDiv.textContent = 'Please enter your email and password.';
        errDiv.classList.remove('cp-hidden');
        return;
      }
      submitBtn.disabled = true;
      submitBtn.textContent = 'Signing in…';
      errDiv.classList.add('cp-hidden');

      var fd = new FormData();
      fd.append('action',   'coachpro_login');
      fd.append('nonce',    cfg.wpNonce);
      fd.append('username', emailVal);
      fd.append('password', passVal);

      fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            cfg.wpUserId = data.data.id;
            cfg.wpNonce  = data.data.nonce;
            if (cfg.pageUrls && cfg.pageUrls.dashboard) {
              window.location.href = cfg.pageUrls.dashboard;
            } else {
              cfg.view = 'dashboard';
              renderView(el_container, cfg);
            }
          } else {
            errDiv.textContent = data.data.message || 'Login failed. Please check your credentials.';
            errDiv.classList.remove('cp-hidden');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Sign In';
          }
        })
        .catch(function () {
          errDiv.textContent = 'Network error. Please try again.';
          errDiv.classList.remove('cp-hidden');
          submitBtn.disabled = false;
          submitBtn.textContent = 'Sign In';
        });
    });

    [emailInput, passInput].forEach(function (inp) {
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); submitBtn.click(); }
      });
    });
  }

  /* -----------------------------------------------------------------------
   * View: Register Form
   * --------------------------------------------------------------------- */
  function renderRegisterForm(el_container, cfg) {
    el_container.innerHTML = '';
    var wrap = el('div', 'cp-auth-wrap');

    var brand = el('div', 'cp-auth-brand');
    brand.innerHTML =
      '<div class="cp-auth-logo">🎓</div>' +
      '<h1 class="cp-auth-title">CoachPro AI</h1>';
    wrap.appendChild(brand);

    var card = el('div', 'cp-auth-card');
    card.innerHTML =
      '<h2 class="cp-auth-heading">Create your account</h2>' +
      '<p class="cp-auth-sub">Start your AI-powered coaching journey today.</p>';

    if (cfg.googleClientId) {
      var googleBtn = el('button', 'cp-btn-google');
      googleBtn.type = 'button';
      googleBtn.innerHTML =
        '<svg class="cp-google-icon" viewBox="0 0 24 24" width="20" height="20">' +
        '<path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>' +
        '<path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>' +
        '<path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>' +
        '<path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>' +
        '</svg>' +
        'Continue with Google';
      googleBtn.addEventListener('click', function () {
        var oauthUrl = googleOauthUrl(cfg);
        if (oauthUrl) {
          window.location.href = oauthUrl;
        }
      });
      card.appendChild(googleBtn);

      var divider = el('div', 'cp-auth-divider');
      divider.innerHTML = '<span>OR SIGN UP WITH EMAIL</span>';
      card.appendChild(divider);
    }

    var unameLabel = el('label', 'cp-field-label', 'Username');
    var unameInput = input('text', 'johndoe');
    unameInput.autocomplete = 'username';

    var emailLabel = el('label', 'cp-field-label', 'Email');
    var emailInput = input('email', 'you@example.com');
    emailInput.autocomplete = 'email';

    var passLabel  = el('label', 'cp-field-label', 'Password');
    var passWrap   = el('div', 'cp-pass-wrap');
    var passInput  = input('password', '••••••••');
    passInput.autocomplete = 'new-password';
    var passToggle = el('button', 'cp-pass-toggle', '👁');
    passToggle.type = 'button';
    passToggle.addEventListener('click', function () {
      passInput.type = passInput.type === 'password' ? 'text' : 'password';
      passToggle.textContent = passInput.type === 'password' ? '👁' : '🙈';
    });
    passWrap.appendChild(passInput);
    passWrap.appendChild(passToggle);

    var errDiv    = el('div', 'cp-error cp-hidden');
    var submitBtn = btn('Create Account', 'cp-btn-primary cp-full cp-btn-lg');

    [unameLabel, unameInput, emailLabel, emailInput, passLabel, passWrap, errDiv, submitBtn].forEach(function (n) {
      card.appendChild(n);
    });

    var loginLink = el('p', 'cp-auth-switch');
    loginLink.innerHTML = 'Already have an account? <a href="#" class="cp-link">Sign in</a>';
    loginLink.querySelector('a').addEventListener('click', function (e) {
      e.preventDefault();
      if (cfg.pageUrls && cfg.pageUrls.login) {
        window.location.href = cfg.pageUrls.login;
      } else {
        cfg.view = 'login';
        renderView(el_container, cfg);
      }
    });
    card.appendChild(loginLink);

    wrap.appendChild(card);
    el_container.appendChild(wrap);

    submitBtn.addEventListener('click', function () {
      if (!unameInput.value.trim() || !emailInput.value.trim() || !passInput.value) {
        errDiv.textContent = 'All fields are required.';
        errDiv.classList.remove('cp-hidden');
        return;
      }
      if (passInput.value.length < 6) {
        errDiv.textContent = 'Password must be at least 6 characters.';
        errDiv.classList.remove('cp-hidden');
        return;
      }
      submitBtn.disabled = true;
      submitBtn.textContent = 'Creating account…';
      errDiv.classList.add('cp-hidden');

      var fd = new FormData();
      fd.append('action',   'coachpro_register');
      fd.append('nonce',    cfg.wpNonce);
      fd.append('username', unameInput.value.trim());
      fd.append('email',    emailInput.value.trim());
      fd.append('password', passInput.value);

      fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            cfg.wpUserId = data.data.id;
            cfg.wpNonce  = data.data.nonce;
            if (cfg.pageUrls && cfg.pageUrls.dashboard) {
              window.location.href = cfg.pageUrls.dashboard;
            } else {
              cfg.view = 'dashboard';
              renderView(el_container, cfg);
            }
          } else {
            errDiv.textContent = data.data.message || 'Registration failed.';
            errDiv.classList.remove('cp-hidden');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Account';
          }
        });
    });
  }

  /* -----------------------------------------------------------------------
   * View: Dashboard
   * --------------------------------------------------------------------- */
  function renderDashboard(el_container, cfg) {
    el_container.innerHTML = '';
    el_container.appendChild(navBar(cfg, 'dashboard'));

    var main = el('div', 'cp-main');
    el_container.appendChild(main);
    main.innerHTML = '<div class="cp-loading">Loading dashboard…</div>';

    function parseStarters(assistant) {
      if (!assistant || !assistant.conversation_starters) return [];
      if (Array.isArray(assistant.conversation_starters)) return assistant.conversation_starters;
      if (typeof assistant.conversation_starters === 'string') {
        try { return JSON.parse(assistant.conversation_starters); } catch (e) { return []; }
      }
      return [];
    }

    function goToChat(projectId, convId, assistantId) {
      cfg.projectId = projectId || '';
      cfg.convId = convId || '';
      cfg.assistantId = assistantId || '';

      if (cfg.pageUrls && cfg.pageUrls.chat) {
        window.location.href = cfg.pageUrls.chat;
        return;
      }

      cfg.view = 'chat';
      renderView(el_container, cfg);
    }

    Promise.all([
      api(cfg, 'auth/me'),
      api(cfg, 'projects?per_page=3&orderby=updated_at').catch(function () { return []; }),
      api(cfg, 'assistants?active=1').catch(function () { return []; })
    ]).then(function (results) {
      var user = results[0] || {};
      var projects = Array.isArray(results[1]) ? results[1] : [];
      var assistantsRaw = Array.isArray(results[2]) ? results[2] : [];
      var assistants = assistantsRaw.filter(function (a) {
        return String(a.is_activated) === '1' || a.is_activated === true;
      });
      if (!assistants.length) assistants = assistantsRaw.slice(0, 6);
      var selectedAssistant = assistants[0] || null;

      main.innerHTML = '';
      main.appendChild(el('h2', 'cp-heading', '👋 Welcome, ' + escHtml(user.name || user.username || 'there') + '!'));

      if (Number(user.credits || 0) <= 5) {
        var low = el('div', 'cp-low-credits-banner');
        low.innerHTML = '<strong>Running low on credits</strong>';
        var buyBtn = btn('Buy Credits', 'cp-btn-primary cp-btn-sm');
        buyBtn.addEventListener('click', function () {
          if (cfg.pageUrls && cfg.pageUrls.buy_credits) {
            window.location.href = cfg.pageUrls.buy_credits;
          } else {
            cfg.view = 'buy_credits';
            renderView(el_container, cfg);
          }
        });
        low.appendChild(buyBtn);
        main.appendChild(low);
      }

      var plan = String(user.plan || 'free').toLowerCase();
      var proDismissed = false;
      try { proDismissed = window.localStorage.getItem('coachpro_pro_banner_dismissed') === '1'; } catch (e) {}
      if (plan === 'free' && !proDismissed) {
        var pro = el('div', 'cp-pro-banner');
        pro.innerHTML = '<strong>Upgrade to Pro</strong> for more credits, assistants, and unlimited usage.';
        var wrap = el('div', '');
        var upBtn = btn('Upgrade', 'cp-btn-primary cp-btn-sm');
        var closeBtn = btn('Dismiss', 'cp-btn-outline cp-btn-sm');
        upBtn.addEventListener('click', function () {
          if (cfg.pageUrls && cfg.pageUrls.buy_credits) {
            window.location.href = cfg.pageUrls.buy_credits;
          } else {
            cfg.view = 'buy_credits';
            renderView(el_container, cfg);
          }
        });
        closeBtn.addEventListener('click', function () {
          try { window.localStorage.setItem('coachpro_pro_banner_dismissed', '1'); } catch (e) {}
          pro.remove();
        });
        wrap.appendChild(upBtn);
        wrap.appendChild(closeBtn);
        pro.appendChild(wrap);
        main.appendChild(pro);
      }

      var chipsWrap = el('div', 'cp-assistant-chips');
      chipsWrap.innerHTML = '<h3>Active Assistants</h3>';
      if (!assistants.length) {
        chipsWrap.appendChild(el('p', 'cp-empty', 'No active assistants. Activate one to start.'));
      }
      assistants.forEach(function (a, idx) {
        var c = btn('🤖 ' + (a.name || 'Assistant'), 'cp-btn-outline cp-btn-sm' + (idx === 0 ? ' active' : ''));
        c.addEventListener('click', function () {
          selectedAssistant = a;
          chipsWrap.querySelectorAll('.cp-btn').forEach(function (node) { node.classList.remove('active'); });
          c.classList.add('active');
          renderStarters();
        });
        chipsWrap.appendChild(c);
      });
      main.appendChild(chipsWrap);

      var quick = el('div', 'cp-card');
      quick.innerHTML = '<h3>⚡ Quick Chat</h3><p>Send a message and jump straight into chat.</p>';
      var quickInput = textarea('Type your message…');
      quickInput.rows = 3;
      var quickSend = btn('Send ➤', 'cp-btn-primary');
      var starters = el('div', 'cp-starter-chips');

      function renderStarters() {
        starters.innerHTML = '';
        parseStarters(selectedAssistant).forEach(function (s) {
          var t = typeof s === 'string' ? s : (s && (s.title || s.label || s.text)) || '';
          if (!t) return;
          var sb = btn(t, 'cp-btn-outline cp-btn-sm');
          sb.addEventListener('click', function () {
            quickInput.value = t;
            quickInput.focus();
          });
          starters.appendChild(sb);
        });
      }

      quickSend.addEventListener('click', function () {
        var text = quickInput.value.trim();
        if (!text) return;
        if (!selectedAssistant) {
          alert('Please activate an assistant first.');
          return;
        }

        quickSend.disabled = true;
        quickSend.textContent = 'Starting…';

        var ensureProject = Promise.resolve((cfg.projectId || (projects[0] && projects[0].id) || ''));
        if (!projects.length && !cfg.projectId) {
          ensureProject = api(cfg, 'projects', 'POST', { name: 'Quick Chat', description: 'Auto-created for quick dashboard chat' })
            .then(function (p) { return p.id; });
        }

        ensureProject.then(function (projectId) {
          return api(cfg, 'conversations', 'POST', {
            project_id: projectId,
            assistant_id: selectedAssistant.id,
            title: 'New conversation'
          }).then(function (conv) {
            return api(cfg, 'conversations/' + conv.id + '/messages', 'POST', {
              role: 'user',
              content: text
            }).then(function () {
              goToChat(projectId, conv.id, selectedAssistant.id);
            });
          });
        }).catch(function (e) {
          showError(quick, (e && e.message) || 'Unable to start quick chat.');
          quickSend.disabled = false;
          quickSend.textContent = 'Send ➤';
        });
      });

      quick.appendChild(quickInput);
      quick.appendChild(starters);
      quick.appendChild(quickSend);
      main.appendChild(quick);
      renderStarters();

      var pSection = el('div', 'cp-section');
      pSection.innerHTML = '<h3>Recent Projects</h3>';
      var pGrid = el('div', 'cp-cards-grid');
      projects.slice(0, 3).forEach(function (p) {
        var card = el('div', 'cp-card cp-project-card');
        card.innerHTML = '<h4>' + escHtml(p.name || 'Untitled') + '</h4><p>' + escHtml(p.description || '') + '</p>';
        var chatBtn = btn('Open Chat', 'cp-btn-outline cp-btn-sm');
        chatBtn.addEventListener('click', function () {
          goToChat(p.id, '', selectedAssistant && selectedAssistant.id);
        });
        card.appendChild(chatBtn);
        pGrid.appendChild(card);
      });
      if (!projects.length) pGrid.appendChild(el('p', 'cp-empty', 'No projects yet.'));
      pSection.appendChild(pGrid);
      main.appendChild(pSection);

      var aSection = el('div', 'cp-section');
      aSection.innerHTML = '<h3>Recent Assistants</h3>';
      var aGrid = el('div', 'cp-cards-grid');
      assistants.slice(0, 3).forEach(function (a) {
        var card = el('div', 'cp-card cp-project-card');
        card.innerHTML = '<h4>' + escHtml(a.name || 'Assistant') + '</h4><p>' + escHtml(a.description || '') + '</p>';
        card.addEventListener('click', function () {
          selectedAssistant = a;
          chipsWrap.querySelectorAll('.cp-btn').forEach(function (node) {
            if (node.textContent.indexOf(a.name) !== -1) node.classList.add('active');
            else node.classList.remove('active');
          });
          renderStarters();
          quickInput.focus();
        });
        aGrid.appendChild(card);
      });
      if (!assistants.length) aGrid.appendChild(el('p', 'cp-empty', 'No assistants available.'));
      aSection.appendChild(aGrid);
      main.appendChild(aSection);

    }).catch(function (err) {
      main.innerHTML = '';
      showError(main, (err && err.message) || 'Failed to load dashboard.');
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

    var main = el('div', 'cp-main');
    el_container.appendChild(main);

    var state = {
      projectId: cfg.projectId || '',
      convId: cfg.convId || '',
      assistantId: cfg.assistantId || '',
      modelId: cfg.defaultModelId || 'gpt-4o-mini',
      credits: 0,
      projects: [],
      assistants: [],
      conversations: [],
      messages: [],
      savedMap: {}
    };

    function getTextDir(text) {
      // Arabic/Persian/Urdu Unicode blocks for RTL detection.
      return /[؀-ۿݐ-ݿ]/.test(text) ? 'rtl' : 'ltr';
    }

    function parseStarters(assistant) {
      if (!assistant || !assistant.conversation_starters) return [];
      if (Array.isArray(assistant.conversation_starters)) return assistant.conversation_starters;
      if (typeof assistant.conversation_starters === 'string') {
        try { return JSON.parse(assistant.conversation_starters); } catch (e) { return []; }
      }
      return [];
    }

    function goTo(view, extra) {
      if (extra) {
        Object.keys(extra).forEach(function (k) { cfg[k] = extra[k]; });
      }
      if (cfg.pageUrls && cfg.pageUrls[view]) {
        window.location.href = cfg.pageUrls[view];
        return;
      }
      cfg.view = view;
      renderView(el_container, cfg);
    }

    function selectedAssistant() {
      return state.assistants.find(function (a) { return a.id === state.assistantId; }) || null;
    }

    function selectedProject() {
      return state.projects.find(function (p) { return p.id === state.projectId; }) || null;
    }

    function selectedConversation() {
      return state.conversations.find(function (c) { return c.id === state.convId; }) || null;
    }

    function healthScore(messages) {
      var len = (messages || []).reduce(function (acc, m) { return acc + String(m.content || '').length; }, 0);
      // Weighted score: +5 per message and +1 per ~200 chars of total content.
      return Math.round((messages || []).length * 5 + (len / 200));
    }

    function syncModelFromAssistant() {
      var a = selectedAssistant();
      state.modelId = (a && a.default_model_id) || cfg.defaultModelId || 'gpt-4o-mini';
    }

    function refreshSavedMap() {
      return api(cfg, 'saved-responses').then(function (rows) {
        state.savedMap = {};
        (rows || []).forEach(function (r) { state.savedMap[r.message_id] = r.id; });
      }).catch(function () { state.savedMap = {}; });
    }

    function loadConversations() {
      if (!state.projectId) {
        state.conversations = [];
        state.convId = '';
        state.messages = [];
        render();
        return Promise.resolve();
      }
      return api(cfg, 'conversations?project_id=' + encodeURIComponent(state.projectId)).then(function (rows) {
        state.conversations = rows || [];
        if (!state.convId && state.conversations.length) state.convId = state.conversations[0].id;
      });
    }

    function loadMessages() {
      if (!state.convId) {
        state.messages = [];
        render();
        return Promise.resolve();
      }
      return api(cfg, 'conversations/' + state.convId + '/messages').then(function (rows) {
        state.messages = rows || [];
      });
    }

    function createConversation(title) {
      if (!state.projectId || !state.assistantId) {
        alert('Please select a project and assistant first.');
        return Promise.resolve(null);
      }
      return api(cfg, 'conversations', 'POST', {
        project_id: state.projectId,
        assistant_id: state.assistantId,
        title: title || 'New conversation'
      }).then(function (conv) {
        state.convId = conv.id;
        state.messages = [];
        return loadConversations().then(function () { return loadMessages(); }).then(function () { return conv; });
      });
    }

    function showTooLongDialog() {
      var modal = el('div', 'cp-modal');
      var card = el('div', 'cp-card');
      card.innerHTML = '<h3>This chat has grown very long</h3><p>Start a fresh chat for better responses.</p>';
      var freshBtn = btn('Start Fresh Chat', 'cp-btn-primary');
      var closeBtn = btn('Close', 'cp-btn-outline');
      freshBtn.addEventListener('click', function () {
        createConversation('New conversation').then(function () {
          modal.remove();
          render();
        });
      });
      closeBtn.addEventListener('click', function () { modal.remove(); });
      card.appendChild(freshBtn);
      card.appendChild(closeBtn);
      modal.appendChild(card);
      main.appendChild(modal);
    }

    function render() {
      var project = selectedProject();
      var assistant = selectedAssistant();
      var conv = selectedConversation();
      syncModelFromAssistant();

      main.innerHTML = '';

      var top = el('div', 'cp-section-header');
      top.innerHTML = '<div><a href="#" class="cp-link cp-back-projects">← Projects</a> &nbsp; <a href="#" class="cp-link cp-back-dashboard">← Dashboard</a></div>' +
        '<div><strong>📁 ' + escHtml((project && project.name) || 'No project') + '</strong></div>' +
        '<div><strong>💳 Credits: ' + escHtml(String(state.credits || 0)) + '</strong></div>';
      main.appendChild(top);
      top.querySelector('.cp-back-projects').addEventListener('click', function (e) { e.preventDefault(); goTo('projects'); });
      top.querySelector('.cp-back-dashboard').addEventListener('click', function (e) { e.preventDefault(); goTo('dashboard'); });

      var menuBtn = btn('☰', 'cp-btn cp-chat-menu-btn');
      main.appendChild(menuBtn);

      var layout = el('div', 'cp-chat-layout');
      var sidebar = el('aside', 'cp-chat-sidebar');
      var area = el('section', 'cp-chat-area');
      var right = el('aside', 'cp-chat-right');
      layout.appendChild(sidebar);
      layout.appendChild(area);
      layout.appendChild(right);
      main.appendChild(layout);

      menuBtn.addEventListener('click', function () { sidebar.classList.toggle('cp-sidebar-open'); });

      sidebar.appendChild(el('h3', '', escHtml((project && project.name) || 'Project')));
      sidebar.appendChild(el('p', '', escHtml((project && project.description) || 'Select a project to begin.')));

      var projectSel = select(state.projects.map(function (p) { return { value: p.id, label: p.name }; }), state.projectId || '');
      projectSel.addEventListener('change', function () {
        state.projectId = this.value;
        state.convId = '';
        loadConversations().then(loadMessages).then(render);
      });
      sidebar.appendChild(el('label', 'cp-label', 'Project'));
      sidebar.appendChild(projectSel);

      var assistantSel = select(state.assistants.map(function (a) { return { value: a.id, label: a.name }; }), state.assistantId || '');
      assistantSel.addEventListener('change', function () {
        state.assistantId = this.value;
        syncModelFromAssistant();
        render();
      });
      sidebar.appendChild(el('label', 'cp-label', 'Select Assistant'));
      sidebar.appendChild(assistantSel);

      var newBtn = btn('+ New Chat', 'cp-btn-primary cp-full');
      newBtn.addEventListener('click', function () {
        createConversation('New conversation').then(render);
      });
      sidebar.appendChild(newBtn);

      sidebar.appendChild(el('h4', '', 'Conversations'));
      var convList = el('div', 'cp-conv-list');
      (state.conversations || []).forEach(function (c) {
        var row = el('div', 'cp-conv-row');
        var item = el('div', 'cp-conv-item' + (c.id === state.convId ? ' active' : ''), escHtml(c.title || 'Untitled'));
        item.addEventListener('click', function () {
          state.convId = c.id;
          state.assistantId = c.assistant_id || state.assistantId;
          loadMessages().then(render);
        });
        var del = btn('🗑', 'cp-btn-danger cp-btn-sm');
        del.addEventListener('click', function (e) {
          e.stopPropagation();
          if (!confirm('Delete this conversation?')) return;
          api(cfg, 'conversations/' + c.id, 'DELETE').then(function () {
            if (state.convId === c.id) {
              state.convId = '';
              state.messages = [];
            }
            return loadConversations();
          }).then(render);
        });
        row.appendChild(item);
        row.appendChild(del);
        convList.appendChild(row);
      });
      sidebar.appendChild(convList);

      var header = el('div', 'cp-card');
      var score = healthScore(state.messages || []);
      var healthClass = score < 20 ? 'healthy' : (score < 50 ? 'long' : 'very-long');
      var healthText = score < 20 ? '🟢 Healthy' : (score < 50 ? '🟡 Getting long' : '🔴 Very long');
      header.innerHTML = '<h3>🤖 ' + escHtml((assistant && assistant.name) || 'Assistant') + '</h3>' +
        '<p>' + escHtml((conv && conv.title) || 'New conversation') + '</p>' +
        '<div class="cp-health-badge ' + healthClass + '">' + healthText + '</div>';
      if (score >= 50) {
        var fresh = btn('Start Fresh', 'cp-btn-outline cp-btn-sm');
        fresh.addEventListener('click', function () {
          createConversation('New conversation').then(render);
        });
        header.appendChild(fresh);
      }
      area.appendChild(header);

      var msgList = el('div', 'cp-msg-list');
      if (!state.messages.length && assistant) {
        var starters = el('div', 'cp-starter-chips');
        parseStarters(assistant).forEach(function (s) {
          var t = typeof s === 'string' ? s : (s && (s.title || s.label || s.text)) || '';
          if (!t) return;
          var sb = btn(t, 'cp-btn-outline cp-btn-sm');
          sb.addEventListener('click', function () {
            inputBox.value = t;
            inputBox.focus();
          });
          starters.appendChild(sb);
        });
        if (starters.children.length) area.appendChild(starters);
      }

      (state.messages || []).forEach(function (m) {
        var bubble = el('div', 'cp-msg cp-msg-' + escHtml(m.role));
        var content = el('div', 'cp-msg-content', escHtml(m.content || ''));
        content.setAttribute('dir', getTextDir(m.content || ''));
        bubble.appendChild(content);
        bubble.appendChild(el('div', 'cp-msg-meta', escHtml(m.role || '')));

        if (m.role === 'assistant' && m.id) {
          var savedId = state.savedMap[m.id];
          var saveBtn = btn(savedId ? '✅ Saved' : '🔖 Save', 'cp-btn-outline cp-btn-sm');
          saveBtn.addEventListener('click', function () {
            if (savedId) {
              api(cfg, 'saved-responses/' + savedId, 'DELETE').then(function () { return refreshSavedMap(); }).then(render);
            } else {
              api(cfg, 'saved-responses', 'POST', { message_id: m.id }).then(function () { return refreshSavedMap(); }).then(render);
            }
          });
          bubble.appendChild(saveBtn);
        }

        msgList.appendChild(bubble);
      });
      area.appendChild(msgList);

      var inputArea = el('div', 'cp-chat-input-area');
      var inputBox = textarea('Type your message…');
      inputBox.rows = 3;
      var sendBtn = btn('Send ➤', 'cp-btn-primary');
      inputArea.appendChild(inputBox);
      inputArea.appendChild(sendBtn);
      area.appendChild(inputArea);

      function handleSend() {
        var text = inputBox.value.trim();
        if (!text) return;

        var ensure = state.convId ? Promise.resolve({ id: state.convId }) : createConversation('New conversation');
        ensure.then(function (convRow) {
          if (!convRow || !convRow.id) return;
          var isFirst = !(state.messages || []).some(function (m) { return m.role === 'user'; });

          sendBtn.disabled = true;
          sendBtn.textContent = 'Sending…';

          return api(cfg, 'conversations/' + convRow.id + '/messages', 'POST', {
            role: 'user',
            content: text
          }).then(function () {
            if (isFirst) {
              return api(cfg, 'conversations/' + convRow.id, 'PUT', { title: text.slice(0, 50) });
            }
            return null;
          }).catch(function () {
            return null;
          }).then(function () {
            return api(cfg, 'chat', 'POST', {
              conversation_id: convRow.id,
              model_id: state.modelId || cfg.defaultModelId || 'gpt-4o-mini',
              message: text
            });
          }).then(function (resp) {
            state.credits = Number(resp.balance || (state.credits - Number(resp.credits_used || 0)) || 0);
            inputBox.value = '';
            return loadConversations().then(loadMessages).then(refreshSavedMap).then(render);
          }).catch(function (e) {
            if (e && e.code === 'chat_too_long') {
              showTooLongDialog();
            } else {
              alert((e && e.message) || 'Failed to send message.');
            }
          }).finally(function () {
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send ➤';
          });
        });
      }

      sendBtn.addEventListener('click', handleSend);
      inputBox.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          handleSend();
        }
      });

      right.innerHTML = '<div class="cp-card"><h4>Assistant</h4>' +
        '<p><strong>Name:</strong> ' + escHtml((assistant && assistant.name) || '—') + '</p>' +
        '<p><strong>Description:</strong> ' + escHtml((assistant && assistant.description) || '—') + '</p>' +
        '<p><strong>Category:</strong> ' + escHtml((assistant && assistant.category) || '—') + '</p></div>';
    }

    Promise.all([
      api(cfg, 'auth/me'),
      api(cfg, 'projects'),
      api(cfg, 'assistants?active=1').catch(function () { return api(cfg, 'assistants'); }),
      refreshSavedMap()
    ]).then(function (results) {
      var user = results[0] || {};
      state.credits = Number(user.credits || 0);
      state.projects = results[1] || [];
      state.assistants = (results[2] || []).filter(function (a) {
        return String(a.is_activated) === '1' || a.is_activated === true;
      });
      if (!state.assistants.length) state.assistants = results[2] || [];

      if (!state.projectId && state.projects.length) state.projectId = state.projects[0].id;
      if (!state.assistantId && state.assistants.length) state.assistantId = state.assistants[0].id;
      syncModelFromAssistant();

      loadConversations().then(loadMessages).then(render).catch(function (e) {
        main.innerHTML = '';
        showError(main, (e && e.message) || 'Failed to load chat.');
      });
    }).catch(function (e) {
      main.innerHTML = '';
      showError(main, (e && e.message) || 'Failed to load chat.');
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
        model_id:        state.modelId || cfg.defaultModelId || 'gpt-4o-mini',
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
   * View: Help
   * --------------------------------------------------------------------- */
  function renderHelp(el_container, cfg) {
    el_container.innerHTML = '';
    el_container.appendChild(navBar(cfg, 'help'));

    var main = el('div', 'cp-main');
    el_container.appendChild(main);

    var faqs = [
      { q: 'How do I start?', a: 'Pick an assistant from the Assistants page, activate it, then open Dashboard or Chat.' },
      { q: 'How do conversation starters work?', a: 'When available, click a starter chip to fill your message quickly.' },
      { q: 'Can I create my own assistant?', a: 'Yes. Go to Assistants and use “Create Assistant”. Free plans have limits.' },
      { q: 'Why did my chat stop?', a: 'Your credits may be low, or the chat may be too long. Start a fresh chat when needed.' },
      { q: 'How do I save a response?', a: 'In chat, click 🔖 on an assistant message. It appears in the Saved section.' },
      { q: 'Where do I buy credits?', a: 'Open the Credits page from navigation to buy a plan or credit pack.' }
    ];

    main.innerHTML = '<div class="cp-card"><h2>How to use AI Assistants</h2><p>Follow these steps to get the best results.</p></div>';

    var steps = [
      'Pick assistant',
      'Activate assistant',
      'Start chatting',
      'Use Conversation Starters',
      'Create custom assistant',
      'Manage credits'
    ];
    var stepsGrid = el('div', 'cp-cards-grid');
    steps.forEach(function (s, i) {
      stepsGrid.appendChild(el('div', 'cp-card cp-help-step', '<strong>Step ' + (i + 1) + ':</strong> ' + escHtml(s)));
    });
    main.appendChild(stepsGrid);

    main.appendChild(el('div', 'cp-card', '<h3>💡 Pro Tip</h3><p>Keep each chat focused on one goal and start fresh when it becomes too long.</p>'));

    var faqWrap = el('div', 'cp-card');
    faqWrap.innerHTML = '<h3>FAQ</h3>';
    faqs.forEach(function (f) {
      var item = el('div', 'cp-faq-item');
      var q = document.createElement('button');
      q.className = 'cp-btn cp-btn-outline cp-full';
      q.textContent = '❓ ' + f.q;
      var a = el('div', 'cp-hidden', '<p>' + escHtml(f.a) + '</p>');
      q.addEventListener('click', function () { a.classList.toggle('cp-hidden'); });
      item.appendChild(q);
      item.appendChild(a);
      faqWrap.appendChild(item);
    });
    main.appendChild(faqWrap);

    var cta = el('div', 'cp-card cp-center');
    cta.innerHTML = '<h3>Ready to continue?</h3><p>Go to Dashboard and start your next conversation.</p>';
    var ctaBtn = btn('Go to Dashboard', 'cp-btn-primary');
    ctaBtn.addEventListener('click', function () {
      if (cfg.pageUrls && cfg.pageUrls.dashboard) {
        window.location.href = cfg.pageUrls.dashboard;
      } else {
        cfg.view = 'dashboard';
        renderView(el_container, cfg);
      }
    });
    cta.appendChild(ctaBtn);
    main.appendChild(cta);
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
