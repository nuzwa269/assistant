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
      var query = new URLSearchParams(window.location.search);
      cfg.projectId = query.get('project_id') || cfg.projectId;
      cfg.convId = query.get('conversation_id') || '';
      cfg.assistantId = query.get('assistant_id') || '';
      cfg.view = el.getAttribute('data-view') || cfg.view || 'dashboard';
      cfg.theme = el.getAttribute('data-theme') || cfg.theme || 'light';
      el.setAttribute('data-theme', cfg.theme);
      if (cfg.wpUserId && ['dashboard','projects','assistants','saved','buy_credits','help','settings','chat','transactions'].indexOf(cfg.view) !== -1) setupWorkspaceTypography(el);
      if (cfg.wpUserId && ['dashboard','projects','assistants','saved','buy_credits','help','settings','chat','transactions'].indexOf(cfg.view) !== -1) trackWorkspaceViewport(el);
      renderView(el, cfg);
    });
  }

  // Presentation only: language/direction annotations never alter stored message text.
  function setupWorkspaceTypography(root) {
    if (root.cpTypographyObserver) root.cpTypographyObserver.disconnect();
    if (root.cpTypographyEvents) root.cpTypographyEvents.abort();
    var events = new AbortController();
    root.cpTypographyEvents = events;
    var arabic = /[\u0600-\u06ff\u0750-\u077f\u08a0-\u08ff\ufb50-\ufdff\ufe70-\ufeff]/;
    var urdu = /[\u0679\u067e\u0686\u0688\u0691\u0698\u06a9\u06af\u06ba\u06be\u06c1\u06c2\u06d2\u06d3]/;
    function language(text, node) {
      if (node.hasAttribute('lang') && !node.hasAttribute('data-cp-auto-lang')) return node.lang;
      if (!arabic.test(text)) return 'en';
      return urdu.test(text) || /(?:^|\s)(?:\u0627\u0631\u062f\u0648|\u0622\u067e|\u0645\u06cc\u06ba|\u06c1\u06d2|\u06c1\u06cc\u06ba|\u06a9\u0627|\u06a9\u06cc|\u06a9\u06d2|\u06a9\u0648|\u0627\u0648\u0631|\u0633\u06d2)(?:\s|[\u060c\u06d4!?\u061f]|$)/.test(text) ? 'ur' : 'ar';
    }
    function annotate(node) {
      var control = node.matches('input,textarea,select');
      var text = control ? node.value : node.textContent;
      var lang = language(text || '',node);
      var authored = node.hasAttribute('lang') && !node.hasAttribute('data-cp-auto-lang');
      node.lang = lang;
      if (!authored) node.setAttribute('data-cp-auto-lang','true');
      node.setAttribute('dir', arabic.test(text || '') ? 'auto' : 'ltr');
      if (node.matches('.cp-msg-text-para,.cp-msg-content')) node.style.textAlign = 'start';
      // Isolate Latin phrases, URLs, emails and phone/numeric runs inside RTL prose.
      // Text nodes only: retain code, existing controls, handlers, and exact copied text.
      if (!control && arabic.test(text || '')) {
        var walker = document.createTreeWalker(node,NodeFilter.SHOW_TEXT);
        var nodes = []; var current;
        while ((current = walker.nextNode())) {
          if (!current.parentElement.closest('bdi,pre,code,svg,button,input,textarea,select')) nodes.push(current);
        }
        nodes.forEach(function(textNode) {
          var pattern = /https?:\/\/[^\s\u0600-\u06ff]+|www\.[^\s\u0600-\u06ff]+|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}|\+?[0-9\u0660-\u0669\u06f0-\u06f9][0-9\u0660-\u0669\u06f0-\u06f9 ()+.,:/-]*[0-9\u0660-\u0669\u06f0-\u06f9]|[A-Za-z][A-Za-z0-9_'/-]*(?:[ \t]+[A-Za-z][A-Za-z0-9_'/-]*)*/g;
          var value = textNode.nodeValue; var match; var last = 0;
          var fragment = document.createDocumentFragment();
          while ((match = pattern.exec(value))) {
            fragment.appendChild(document.createTextNode(value.slice(last,match.index)));
            var isolate = document.createElement('bdi'); isolate.lang = 'en'; isolate.dir = 'ltr';
            isolate.textContent = match[0]; fragment.appendChild(isolate); last = pattern.lastIndex;
          }
          if (last) { fragment.appendChild(document.createTextNode(value.slice(last))); textNode.replaceWith(fragment); }
        });
      }
    }
    var selector = 'h1,h2,h3,h4,p,label,summary,.cp-nav-label,.cp-conv-item,.cp-saved-content,.cp-msg-user,.cp-msg-meta,.cp-msg-content:not(.cp-msg-content-assistant),.cp-project-name,.cp-profile-name,option,input,textarea,select';
    var observer = new MutationObserver(update);
    root.cpTypographyObserver = observer;
    function update() {
      observer.disconnect();
      if (root.classList.contains('cp-workspace')) {
        root.lang = 'en'; root.dir = 'ltr';
        root.querySelectorAll(selector).forEach(function(node) {
          if (!node.closest('pre,code,svg') && !node.matches('input[type=password],input[type=hidden]')) annotate(node);
        });
        root.querySelectorAll('pre,code,.cp-code-label').forEach(function(node) { node.lang = 'en'; node.dir = 'ltr'; });
      }
      observer.observe(root,{subtree:true,childList:true,characterData:true});
    }
    root.addEventListener('input',function(e) { if (!e.isComposing && e.target.matches('textarea,input')) annotate(e.target); },{signal:events.signal});
    root.addEventListener('compositionend',function(e) { if(e.target.matches('textarea,input')) annotate(e.target); },{signal:events.signal});
    root.addEventListener('change',function(e) { if(e.target.matches('select')) annotate(e.target); },{signal:events.signal});
    update();
  }

  // Presentation only. Track the visible viewport when mobile keyboards/browser chrome resize it.
  function trackWorkspaceViewport(root) {
    var viewport = window.visualViewport;
    function update() {
      if (!root.isConnected) {
        window.removeEventListener('resize', update);
        if (viewport) { viewport.removeEventListener('resize', update); viewport.removeEventListener('scroll', update); }
        return;
      }
      var visibleHeight = viewport ? viewport.height : window.innerHeight;
      root.style.setProperty('--cp-viewport-height', visibleHeight + 'px');
      root.classList.toggle('cp-compact-viewport', visibleHeight <= 500);
      root.style.setProperty('--cp-keyboard-offset', Math.max(0, window.innerHeight - (viewport ? viewport.height + viewport.offsetTop : window.innerHeight)) + 'px');
    }
    window.addEventListener('resize', update, {passive:true});
    if (viewport) { viewport.addEventListener('resize', update, {passive:true}); viewport.addEventListener('scroll', update, {passive:true}); }
    update();
  }

  /* -----------------------------------------------------------------------
   * Router
   * --------------------------------------------------------------------- */
  function renderView(el, cfg) {
    el.classList.remove('cp-unified-chat');
    if (el.cpChatHistory) el.cpChatHistory.abort();
    if (el.cpProfileDismiss) el.cpProfileDismiss.abort();
    if (el.cpAssistantDismiss) el.cpAssistantDismiss.abort();
    el.classList.remove('cp-workspace');
    el.innerHTML = '<div class="cp-loading">Loading…</div>';

    // If user not logged in and view requires auth, show login prompt
    var publicViews = ['login', 'register', 'landing'];
    if (!cfg.wpUserId && publicViews.indexOf(cfg.view) === -1) {
      renderLogin(el, cfg);
      return;
    }

    switch (cfg.view) {
      case 'landing':      renderLanding(el, cfg); break;
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
    var listPath = /^(projects|conversations|saved-responses|transactions)(\?|$)/.test(path) && path.indexOf('page=') === -1 && (!method || method === 'GET');
    function fetchPage(target) {
      var controller = new AbortController();
      var requestOptions = Object.assign({}, opts, {signal:controller.signal});
      var timer;
      // Include body decoding in the deadline: headers alone do not finish a request.
      var deadline = new Promise(function(resolve, reject) {
        timer = setTimeout(function() {
          var error = new Error('The request timed out. Your draft is preserved. Check the conversation and Credit History before retrying.');
          error.code = 'request_timeout';
          reject(error);
          controller.abort();
        }, path === 'chat' ? 240000 : 30000);
      });
      var response = fetch(target, requestOptions).then(function(r) {
        return r.text().then(function(raw) {
          var data;
          try { data = raw ? JSON.parse(raw) : {}; }
          catch (parseError) {
            // PHP fatal errors / host time limits return HTML instead of JSON.
            var err = new Error('The server returned an unexpected response (HTTP ' + r.status + '). If this keeps happening, ask the site admin to check the PHP error log and the model test in CoachPro AI → AI Providers.');
            err.code = r.status >= 500 ? 'server_error' : 'invalid_response';
            return Promise.reject(err);
          }
          if (!r.ok) return Promise.reject(data);
          return data;
        });
      });
      return Promise.race([response, deadline]).finally(function() { clearTimeout(timer); });
    }
    function remaining(rows, page) {
      if (!Array.isArray(rows) || rows.length < 100) return Promise.resolve(rows);
      return fetchPage(url + (url.indexOf('?') === -1 ? '?' : '&') + 'page=' + page).then(function(next) {
        if (next.length < 100) return rows.concat(next);
        return remaining(next, page + 1).then(function(rest) { return rows.concat(rest); });
      });
    }
    if (listPath) return fetchPage(url).then(function(rows) { return remaining(rows, 2); });
    return fetchPage(url);
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

  var PERSON_ICON = '<svg viewBox="0 0 24 24" width="60%" height="60%" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a7 7 0 0 1 7-7h2a7 7 0 0 1 7 7v1"/></svg>';

  /** The user's uploaded picture/logo, or a neutral person icon when none is set. */
  function avatarNode(url, size) {
    var avatar = el('span', 'cp-profile-avatar');
    avatar.setAttribute('aria-hidden', 'true');
    avatar.style.display = 'inline-flex'; avatar.style.alignItems = 'center'; avatar.style.justifyContent = 'center';
    avatar.style.width = size + 'px'; avatar.style.height = size + 'px'; avatar.style.borderRadius = '50%'; avatar.style.overflow = 'hidden';
    avatar.innerHTML = PERSON_ICON;
    if (url) {
      try {
        var safe = new URL(url, window.location.href);
        if (/^https?:$/.test(safe.protocol)) {
          var photo = document.createElement('img');
          photo.alt = ''; photo.width = size; photo.height = size;
          photo.style.width = '100%'; photo.style.height = '100%'; photo.style.objectFit = 'cover';
          photo.addEventListener('load', function () { Array.prototype.slice.call(avatar.querySelectorAll('svg')).forEach(function (n) { n.remove(); }); });
          photo.addEventListener('error', function () { photo.remove(); });
          photo.src = safe.href;
          avatar.appendChild(photo);
        }
      } catch (e) { /* keep the person icon */ }
    }
    return avatar;
  }

  /** Credits are fractional (token billing); show up to 4 decimals, no trailing zeros. */
  function formatCredits(value) {
    var n = Number(value || 0);
    if (!isFinite(n)) n = 0;
    return n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 4 });
  }

  function stripHtml(str) {
    var parser = new DOMParser();
    var doc = parser.parseFromString(String(str || ''), 'text/html');
    return (doc.body && doc.body.textContent) ? doc.body.textContent : '';
  }

  function el(tag, cls, html) {
    var d = document.createElement(tag);
    if (cls) d.className = cls;
    if (html !== undefined) d.innerHTML = html;
    return d;
  }

  function showError(container, msg) {
    var d = el('div', 'cp-error', escHtml(stripHtml(msg)));
    container.appendChild(d);
  }

  function showSuccess(container, msg) {
    var d = el('div', 'cp-success', escHtml(stripHtml(msg)));
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

  // Curated Lucide icons (ISC license; see public/LUCIDE-LICENSE.txt).
  var workspaceIcons = {"Bot": "<path d=\"M12 8V4H8\"/><rect width=\"16\" height=\"12\" x=\"4\" y=\"8\" rx=\"2\"/><path d=\"M2 14h2m16 0h2M9 13v2m6-2v2\"/>", "LayoutDashboard": "<rect x=\"3\" y=\"3\" width=\"7\" height=\"9\" rx=\"1\"/><rect x=\"14\" y=\"3\" width=\"7\" height=\"5\" rx=\"1\"/><rect x=\"14\" y=\"12\" width=\"7\" height=\"9\" rx=\"1\"/><rect x=\"3\" y=\"16\" width=\"7\" height=\"5\" rx=\"1\"/>", "Folder": "<path d=\"M20 20H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5l2 2h9a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2Z\"/>", "Bookmark": "<path d=\"m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z\"/>", "CreditCard": "<rect width=\"20\" height=\"14\" x=\"2\" y=\"5\" rx=\"2\"/><path d=\"M2 10h20\"/>", "CircleHelp": "<circle cx=\"12\" cy=\"12\" r=\"10\"/><path d=\"M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3m.08 4h.01\"/>", "Settings": "<path d=\"M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z\"/><path d=\"m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.8-.7a8 8 0 0 1-2.7 1.6l-.3 1.9h-2.8l-.3-1.9a8 8 0 0 1-2.7-1.6l-1.8.7-1.4-2.4L5.2 15a8 8 0 0 1 0-3l-1.5-1.2 1.4-2.4 1.8.7a8 8 0 0 1 2.7-1.6L10 5.6h2.8l.3 1.9a8 8 0 0 1 2.7 1.6l1.8-.7 1.4 2.4-1.5 1.2a8 8 0 0 1 0 3Z\"/>", "LogOut": "<path d=\"M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9\"/>", "PanelLeft": "<rect x=\"3\" y=\"3\" width=\"18\" height=\"18\" rx=\"2\"/><path d=\"M9 3v18\"/>", "Ellipsis": "<circle cx=\"12\" cy=\"12\" r=\"1\"/><circle cx=\"5\" cy=\"12\" r=\"1\"/><circle cx=\"19\" cy=\"12\" r=\"1\"/>", "Code": "<path d=\"m16 18 6-6-6-6M8 6l-6 6 6 6\"/>", "Lightbulb": "<path d=\"M9 18h6m-5 4h4m1-8c.2-1 1-1.7 1.8-2.5a6 6 0 1 0-9.6 0C8 12.3 8.8 13 9 14v1h6Z\"/>", "GraduationCap": "<path d=\"m22 10-10-5L2 10l10 5 10-5ZM6 12v5c3 3 9 3 12 0v-5m4-2v6\"/>", "Rocket": "<path d=\"M4.5 16.5c-1.5 1.3-2 5-2 5s3.7-.5 5-2M12 15l-3-3a22 22 0 0 1 3-5c3-3 9-4 9-4s-1 6-4 9a22 22 0 0 1-5 3Z\"/><path d=\"M9 12H4s.6-3 2-4c1.6-1 5 0 5 0m1 7v5s3-.6 4-2c1-1.6 0-5 0-5\"/>", "ChartNoAxesColumn": "<path d=\"M8 20V10m4 10V4m4 16v-6\"/>", "Pencil": "<path d=\"m16 3 5 5M4 20l4-1L21 6a2 2 0 0 0-5-3L3 16l-1 6Z\"/>", "Globe": "<circle cx=\"12\" cy=\"12\" r=\"10\"/><path d=\"M2 12h20M12 2a18 18 0 0 1 0 20 18 18 0 0 1 0-20\"/>", "Heart": "<path d=\"M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z\"/>", "BriefcaseBusiness": "<rect x=\"2\" y=\"7\" width=\"20\" height=\"14\" rx=\"2\"/><path d=\"M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16M2 12h20\"/>", "MessageSquare": "<path d=\"M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z\"/>", "BookOpen": "<path d=\"M12 7v14m0-14C9 4 5 3 2 4v16c3-1 7 0 10 1 3-1 7-2 10-1V4c-3-1-7 0-10 3Z\"/>", "Terminal": "<path d=\"m4 17 6-5-6-5m8 12h8\"/>", "Shield": "<path d=\"M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11\"/>", "Smartphone": "<rect width=\"14\" height=\"20\" x=\"5\" y=\"2\" rx=\"2\"/><path d=\"M12 18h.01\"/>"};

  function workspaceIcon(name) {
    var legacy = {'🤖':'Bot','🎓':'GraduationCap','💡':'Lightbulb','🚀':'Rocket','💻':'Code','📱':'Smartphone','📈':'ChartNoAxesColumn'};
    return '<svg class="cp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (workspaceIcons[name] || workspaceIcons[legacy[name]] || workspaceIcons.Bot) + '</svg>';
  }
  function workspaceGo(root, cfg, view) {
    if (cfg.pageUrls && cfg.pageUrls[view]) { window.location.href = cfg.pageUrls[view]; return; }
    cfg.view = view; renderView(root, cfg);
  }
  function renderCreditBadge(badge, label) {
    badge.innerHTML = workspaceIcon('CreditCard') + '<span>' + escHtml(label) + '</span>';
  }
  function navBar(cfg, activeView) {

    var nav = el('nav', 'cp-nav cp-workspace-nav');
    nav.setAttribute('aria-label', 'Workspace');
    var brand = el('div', 'cp-workspace-brand', '<span class="cp-brand-mark">CP</span><span class="cp-nav-label">CoachPro<span class="cp-brand-sub">AI WORKSPACE</span></span>');
    nav.appendChild(brand);
    var collapse = btn('', 'cp-sidebar-toggle');
    collapse.innerHTML = workspaceIcon('PanelLeft');
    var collapsed = false;
    try { collapsed = localStorage.getItem('coachpro_sidebar_collapsed') === '1'; } catch(e) {}
    function setCollapsed(value) {
      collapsed = value; nav.classList.toggle('cp-nav-collapsed', value);
      collapse.setAttribute('aria-expanded', String(!value));
      collapse.setAttribute('aria-label', value ? 'Expand sidebar' : 'Collapse sidebar');
      collapse.title = value ? 'Expand sidebar' : 'Collapse sidebar';
    }
    setCollapsed(collapsed);
    collapse.addEventListener('click', function() { setCollapsed(!collapsed); try { localStorage.setItem('coachpro_sidebar_collapsed', collapsed ? '1' : '0'); } catch(e) {} });
    nav.appendChild(collapse);
    var links = [
      {view:'dashboard',label:'Dashboard',icon:'LayoutDashboard'},
      {view:'projects',label:'Projects',icon:'Folder'},
      {view:'assistants',label:'Assistants',icon:'Bot'},
      {view:'saved',label:'Saved',icon:'Bookmark'},
      {view:'buy_credits',label:'Credits',icon:'CreditCard'},
      {view:'help',label:'Help',icon:'CircleHelp'},
      {view:'settings',label:'Settings',icon:'Settings'}
    ];
    var more = el('details', 'cp-mobile-more');
    var summary = el('summary', '', workspaceIcon('Ellipsis') + '<span>More</span>');
    summary.setAttribute('aria-label', 'More workspace options');
    more.appendChild(summary);
    var morePanel = el('div', 'cp-more-panel');
    more.appendChild(morePanel);
    more.addEventListener('keydown', function(e) { if (e.key === 'Escape') { more.open = false; summary.focus(); } });
    more.addEventListener('focusout', function(e) { if (!more.contains(e.relatedTarget)) more.open = false; });
    links.forEach(function(lnk, index) {
      function makeLink() {
        var a = el('a', 'cp-nav-link' + (lnk.view === activeView ? ' active' : ''), workspaceIcon(lnk.icon) + '<span class="cp-nav-label">' + lnk.label + '</span>');
        a.href = (cfg.pageUrls && cfg.pageUrls[lnk.view]) || '#';
        a.title = lnk.label; a.setAttribute('aria-label', lnk.label);
        if (lnk.view === activeView) a.setAttribute('aria-current', 'page');
        a.addEventListener('click', function(e) { e.preventDefault(); workspaceGo(nav.closest('.coachpro-app'), cfg, lnk.view); });
        return a;
      }
      var a = makeLink();
      if (index > 3) { a.classList.add('cp-desktop-secondary'); morePanel.appendChild(makeLink()); }
      nav.appendChild(a);
    });
    nav.appendChild(more);
    // Mark the enclosing root before it is mounted, including SPA transitions.
    Promise.resolve().then(function() {
      var root = nav.closest('.coachpro-app');
      if (!root) return;
      root.classList.add('cp-workspace');
      root.setAttribute('data-workspace-view', activeView);
      var top = el('header', 'cp-workspace-header', '<span>Workspace <span class="cp-header-divider">/</span> <strong>' + escHtml(activeView.replace('_', ' ')) + '</strong></span>');
      var balance = el('span', 'cp-credit-balance', 'Credits: …');
      renderCreditBadge(balance, 'Credits: …');
      balance.setAttribute('aria-live', 'polite');
      var account = el('div', 'cp-header-account');
      account.appendChild(balance);
      top.appendChild(account);
      root.insertBefore(top, nav.nextSibling);
      var content = root.querySelector('.cp-main');
      if (content) { content.setAttribute('role', 'main'); content.id = 'cp-workspace-main-' + Array.prototype.indexOf.call(document.querySelectorAll('.coachpro-app'), root); content.tabIndex = -1; var skip = el('a', 'cp-skip-link', 'Skip to content'); skip.href = '#' + content.id; root.insertBefore(skip, nav); }
      api(cfg, 'auth/me').then(function(user) {
        renderCreditBadge(balance, formatCredits(user.credits) + ' credits');
        if (!top.isConnected) return;
        // No name is shown anywhere: only the user's own picture/logo, or a neutral person icon.
        var profile = el('details', 'cp-header-profile');
        var trigger = el('summary', 'cp-profile-trigger');
        trigger.setAttribute('aria-label', 'Account options');
        trigger.title = 'My account';
        trigger.appendChild(avatarNode(user.avatar_url, 34));
        trigger.appendChild(el('span', 'cp-profile-chevron', '<svg class="cp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>'));
        profile.appendChild(trigger);
        var panel = el('div', 'cp-profile-panel');
        panel.appendChild(el('div', 'cp-profile-identity', '<strong>My account</strong>'));
        // Profile editing already lives in Settings; no separate account feature is created.
        var myProfile = el('a', 'cp-profile-link', '<svg class="cp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2"/></svg><span>My Profile</span>');
        myProfile.href = ((cfg.pageUrls && cfg.pageUrls.settings) || '') + '#cp-profile-settings';
        myProfile.addEventListener('click', function(e) {
          e.preventDefault();
          if (cfg.pageUrls && cfg.pageUrls.settings) { window.location.href = myProfile.href; return; }
          cfg.focusProfile = true;
          workspaceGo(root, cfg, 'settings');
        });
        panel.appendChild(myProfile);
        var settings = el('a', 'cp-profile-link', workspaceIcon('Settings') + '<span>Settings</span>');
        settings.href = (cfg.pageUrls && cfg.pageUrls.settings) || '#';
        settings.addEventListener('click', function(e) { e.preventDefault(); workspaceGo(root, cfg, 'settings'); });
        panel.appendChild(settings);
        var signOut = el('button', 'cp-profile-link cp-profile-signout', workspaceIcon('LogOut') + '<span>Sign Out</span>');
        signOut.type = 'button';
        signOut.addEventListener('click', function() { profile.open = false; logoutBtn.click(); });
        panel.appendChild(signOut);
        profile.appendChild(panel);
        profile.addEventListener('keydown', function(e) {
          if (e.key === 'Escape') { e.preventDefault(); profile.open = false; trigger.focus(); }
          if (e.key === 'ArrowDown' && e.target === trigger) { e.preventDefault(); profile.open = true; myProfile.focus(); }
        });
        profile.addEventListener('focusout', function(e) { if (!profile.contains(e.relatedTarget)) profile.open = false; });
        // Replaced on every Workspace render, so outside-click listeners do not accumulate.
        if (root.cpProfileDismiss) root.cpProfileDismiss.abort();
        root.cpProfileDismiss = new AbortController();
        document.addEventListener('pointerdown', function(e) { if (!profile.contains(e.target)) profile.open = false; }, {signal:root.cpProfileDismiss.signal});
        account.appendChild(profile);
      }).catch(function() { renderCreditBadge(balance, 'Credits unavailable'); });
    });
    // Logout
    var logoutBtn = document.createElement('a');
    logoutBtn.href = '#';
    logoutBtn.innerHTML = workspaceIcon('LogOut') + '<span class="cp-nav-label">Sign Out</span>';
    logoutBtn.title = 'Sign Out'; logoutBtn.setAttribute('aria-label', 'Sign Out');
    logoutBtn.className = 'cp-nav-link cp-nav-logout';
    logoutBtn.addEventListener('click', function (e) {
      e.preventDefault();
      var fd = new FormData();
      fd.append('action', 'coachpro_logout');
      fd.append('nonce', cfg.wpNonce);
      fetch(cfg.ajaxUrl, { method: 'POST', body: fd }).then(function () {
        if (cfg.pageUrls && cfg.pageUrls.landing) {
          window.location.href = cfg.pageUrls.landing;
        } else {
          window.location.reload();
        }
      });
    });
    var mobileLogout = logoutBtn.cloneNode(true);
    mobileLogout.addEventListener('click', function(e) { e.preventDefault(); logoutBtn.click(); });
    morePanel.appendChild(mobileLogout);
    nav.appendChild(logoutBtn);
    return nav;
  }

  /* -----------------------------------------------------------------------
   * View: Landing Page (Lovable Index.tsx 100% Native WordPress Port)
   * --------------------------------------------------------------------- */
  function renderLanding(el_container, cfg) {
    el_container.innerHTML = '';

    var loginUrl = (cfg.pageUrls && cfg.pageUrls.login) || '#';
    var registerUrl = (cfg.pageUrls && cfg.pageUrls.register) || '#';
    var dashUrl = (cfg.pageUrls && cfg.pageUrls.dashboard) || '#';

    var wrap = el('div', 'cp-landing-wrap');

    // 1. Header / Navbar
    var nav = el('header', 'cp-landing-nav');
    nav.innerHTML =
      '<div class="cp-landing-nav-inner">' +
        '<div class="cp-landing-logo">' +
          '<span class="cp-landing-logo-icon">🎓</span>' +
          '<span class="cp-landing-logo-text">CoachPro AI</span>' +
        '</div>' +
        '<div class="cp-landing-nav-links">' +
          '<a href="#features" class="cp-landing-nav-link">Features</a>' +
          '<a href="#assistants" class="cp-landing-nav-link">Assistants</a>' +
          '<a href="#how-it-works" class="cp-landing-nav-link">How It Works</a>' +
          (cfg.wpUserId
            ? '<a href="' + escHtml(dashUrl) + '" class="cp-btn cp-btn-primary cp-btn-sm">Open Dashboard →</a>'
            : '<a href="' + escHtml(loginUrl) + '" class="cp-btn cp-btn-outline cp-btn-sm">Sign In</a>' +
              '<a href="' + escHtml(registerUrl) + '" class="cp-btn cp-btn-primary cp-btn-sm">Sign Up Free</a>') +
        '</div>' +
      '</div>';
    wrap.appendChild(nav);

    // 2. Hero Section
    var hero = el('section', 'cp-landing-hero');
    hero.innerHTML =
      '<div class="cp-landing-hero-content">' +
        '<div class="cp-landing-badge">✨ AI Specialist Workspace</div>' +
        '<h1 class="cp-landing-hero-title">Meet your personal team of AI coaches &amp; specialists.</h1>' +
        '<p class="cp-landing-hero-sub">Learn coding, design architecture, debug issues, create prompts, draft educational plans, and build projects faster with dedicated AI experts.</p>' +
        '<div class="cp-landing-hero-actions">' +
          (cfg.wpUserId
            ? '<a href="' + escHtml(dashUrl) + '" class="cp-btn cp-btn-primary cp-btn-lg">Go to Dashboard →</a>'
            : '<a href="' + escHtml(registerUrl) + '" class="cp-btn cp-btn-primary cp-btn-lg">Start Free — 20 Credits Included</a>' +
              '<a href="#assistants" class="cp-btn cp-btn-outline cp-btn-lg">Explore Coaches ↓</a>') +
        '</div>' +
        '<div class="cp-landing-hero-stats">' +
          '<div class="cp-landing-stat"><strong>24/7</strong><span>AI Coaching</span></div>' +
          '<div class="cp-landing-stat"><strong>20 Free</strong><span>Starter Credits</span></div>' +
          '<div class="cp-landing-stat"><strong>100% Native</strong><span>WordPress Powered</span></div>' +
        '</div>' +
      '</div>';
    wrap.appendChild(hero);

    // 3. Features Section
    var featSec = el('section', 'cp-landing-section cp-landing-features');
    featSec.id = 'features';
    featSec.innerHTML =
      '<div class="cp-landing-section-head">' +
        '<span class="cp-landing-badge">Platform Highlights</span>' +
        '<h2>Everything you need to learn &amp; build</h2>' +
        '<p>Private workspaces, custom expert personas, rolling conversation memory, and credit management.</p>' +
      '</div>' +
      '<div class="cp-landing-grid cp-landing-grid-3">' +
        '<div class="cp-landing-card">' +
          '<div class="cp-landing-card-icon">🧠</div>' +
          '<h3>AI-Powered Learning</h3>' +
          '<p>Learn any technical topic with personalized AI tutors that adapt explanations to your pace and skill level.</p>' +
        '</div>' +
        '<div class="cp-landing-card">' +
          '<div class="cp-landing-card-icon">📁</div>' +
          '<h3>Project Workspaces</h3>' +
          '<p>Organize chats and insights into dedicated project folders so client and personal work stay separated.</p>' +
        '</div>' +
        '<div class="cp-landing-card">' +
          '<div class="cp-landing-card-icon">🤖</div>' +
          '<h3>Specialist Assistants</h3>' +
          '<p>Switch between dedicated AI experts for coding, business planning, debugging, and lesson planning.</p>' +
        '</div>' +
        '<div class="cp-landing-card">' +
          '<div class="cp-landing-card-icon">🔖</div>' +
          '<h3>Save High-Value Answers</h3>' +
          '<p>Bookmark important AI replies, architecture prompts, and code snippets into your private knowledge library.</p>' +
        '</div>' +
        '<div class="cp-landing-card">' +
          '<div class="cp-landing-card-icon">💳</div>' +
          '<h3>Local Payment Gateways</h3>' +
          '<p>Credits are added only after administrative payment approval. Supports JazzCash, EasyPaisa, and Pakistani Bank Transfers natively.</p>' +
        '</div>' +
        '<div class="cp-landing-card">' +
          '<div class="cp-landing-card-icon">⚡</div>' +
          '<h3>1-Click Copy Prompts</h3>' +
          '<p>Ready-to-use prompts are highlighted in fenced cards with a single-click copy button for rapid paste.</p>' +
        '</div>' +
      '</div>';
    wrap.appendChild(featSec);

    // 4. Assistants Showcase
    var assistSec = el('section', 'cp-landing-section cp-landing-assistants');
    assistSec.id = 'assistants';
    assistSec.innerHTML =
      '<div class="cp-landing-section-head">' +
        '<span class="cp-landing-badge">Prebuilt Coaches</span>' +
        '<h2>10 specialist assistants ready to help</h2>' +
        '<p>Each assistant comes pre-configured with master system prompts to deliver deep, actionable results.</p>' +
      '</div>' +
      '<div class="cp-landing-grid cp-landing-grid-3">' +
        '<div class="cp-landing-assistant-card">' +
          '<div class="cp-as-top"><span class="cp-as-icon">🎓</span><span class="cp-as-tag">Education</span></div>' +
          '<h3>AI MASTER</h3>' +
          '<p>Your personal educator for coding, AI, SaaS, and WordPress concepts from beginner to pro.</p>' +
        '</div>' +
        '<div class="cp-landing-assistant-card">' +
          '<div class="cp-as-top"><span class="cp-as-icon">💡</span><span class="cp-as-tag">Business</span></div>' +
          '<h3>IDEA MASTER</h3>' +
          '<p>Generates realistic startup, SaaS, and side-hustle ideas matched to your budget and skills.</p>' +
        '</div>' +
        '<div class="cp-landing-assistant-card">' +
          '<div class="cp-as-top"><span class="cp-as-icon">🚀</span><span class="cp-as-tag">Startup</span></div>' +
          '<h3>STARTUP ROCKET</h3>' +
          '<p>Validation roadmaps, MVP architecture, pricing models, and practical launch strategies.</p>' +
        '</div>' +
        '<div class="cp-landing-assistant-card">' +
          '<div class="cp-as-top"><span class="cp-as-icon">📈</span><span class="cp-as-tag">Marketing</span></div>' +
          '<h3>MARKETING MASTER</h3>' +
          '<p>SEO frameworks, organic content tactics, paid ad funnels, and growth strategies for any budget.</p>' +
        '</div>' +
        '<div class="cp-landing-assistant-card">' +
          '<div class="cp-as-top"><span class="cp-as-icon">🏛</span><span class="cp-as-tag">Architecture</span></div>' +
          '<h3>BLUEPRINT ARCHITECT</h3>' +
          '<p>Comprehensive technical specifications, schema blueprints, and master prompts for enterprise apps.</p>' +
        '</div>' +
        '<div class="cp-landing-assistant-card">' +
          '<div class="cp-as-top"><span class="cp-as-icon">💻</span><span class="cp-as-tag">Coding</span></div>' +
          '<h3>CODESATHI</h3>' +
          '<p>Hands-on development partner for web apps, extensions, clean APIs, and modern JavaScript/PHP.</p>' +
        '</div>' +
        '<div class="cp-landing-assistant-card">' +
          '<div class="cp-as-top"><span class="cp-as-icon">🏢</span><span class="cp-as-tag">WordPress</span></div>' +
          '<h3>WORDPRESS BUSINESS ARCHITECT</h3>' +
          '<p>Custom business software and plugin blueprints using pure native PHP, MySQL, and WP APIs.</p>' +
        '</div>' +
        '<div class="cp-landing-assistant-card">' +
          '<div class="cp-as-top"><span class="cp-as-icon">📱</span><span class="cp-as-tag">Mobile</span></div>' +
          '<h3>MOBILE ARCHITECT PRO</h3>' +
          '<p>Cross-platform Flutter and native Android architecture guidance for high-performance apps.</p>' +
        '</div>' +
        '<div class="cp-landing-assistant-card">' +
          '<div class="cp-as-top"><span class="cp-as-icon">🐛</span><span class="cp-as-tag">Debugging</span></div>' +
          '<h3>BUG FIXER PRO</h3>' +
          '<p>Root cause diagnostic specialist that fixes tricky bugs without masking underlying symptoms.</p>' +
        '</div>' +
      '</div>';
    wrap.appendChild(assistSec);

    // 5. How It Works
    var howSec = el('section', 'cp-landing-section cp-landing-how');
    howSec.id = 'how-it-works';
    howSec.innerHTML =
      '<div class="cp-landing-section-head">' +
        '<span class="cp-landing-badge">Simple 3-Step Process</span>' +
        '<h2>How to get started</h2>' +
        '<p>Get up and running with your first AI coaching conversation in under two minutes.</p>' +
      '</div>' +
      '<div class="cp-landing-steps">' +
        '<div class="cp-landing-step">' +
          '<span class="cp-step-num">1</span>' +
          '<h3>Create Your Free Account</h3>' +
          '<p>Sign up in seconds. You instantly receive 20 complimentary credits — no credit card needed.</p>' +
        '</div>' +
        '<div class="cp-landing-step">' +
          '<span class="cp-step-num">2</span>' +
          '<h3>Pick an Assistant &amp; Ask</h3>' +
          '<p>Choose a specialist coach, click a quick conversation starter chip or type your custom goal.</p>' +
        '</div>' +
        '<div class="cp-landing-step">' +
          '<span class="cp-step-num">3</span>' +
          '<h3>Save &amp; Build Results</h3>' +
          '<p>Copy high-yield prompts with 1 click, bookmark actionable answers, and organize into projects.</p>' +
        '</div>' +
      '</div>';
    wrap.appendChild(howSec);

    // 6. Final Call to Action
    var cta = el('section', 'cp-landing-cta');
    cta.innerHTML =
      '<div class="cp-landing-cta-inner">' +
        '<h2>Ready to accelerate your learning &amp; building?</h2>' +
        '<p>Join now and experience smart, context-aware AI coaching right inside WordPress.</p>' +
        '<div class="cp-landing-cta-buttons">' +
          (cfg.wpUserId
            ? '<a href="' + escHtml(dashUrl) + '" class="cp-btn cp-btn-primary cp-btn-lg">Go to Dashboard →</a>'
            : '<a href="' + escHtml(registerUrl) + '" class="cp-btn cp-btn-primary cp-btn-lg">Sign Up Free (20 Credits)</a>' +
              '<a href="' + escHtml(loginUrl) + '" class="cp-btn cp-btn-outline cp-btn-lg">Sign In</a>') +
        '</div>' +
      '</div>';
    wrap.appendChild(cta);

    // 7. Footer
    var footer = el('footer', 'cp-landing-footer');
    footer.innerHTML = '<p>&copy; ' + (new Date()).getFullYear() + ' CoachPro AI Assistant. 100% WordPress Native.</p>';
    wrap.appendChild(footer);

    el_container.appendChild(wrap);
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

    var forgotLink = el('p', 'cp-auth-switch');
    var forgotAnchor = el('a', 'cp-link', 'Forgot Password?');
    forgotAnchor.href = '#';
    forgotLink.appendChild(forgotAnchor);
    card.appendChild(forgotLink);

    var forgotWrap = el('div', 'cp-hidden');
    var forgotHeading = el('h3', 'cp-field-label', 'Reset Password');
    var forgotEmailInput = input('email', 'you@example.com');
    forgotEmailInput.autocomplete = 'email';
    var forgotMsg = el('div', 'cp-error cp-hidden');
    var forgotSubmitBtn = btn('Send Reset Link', 'cp-btn-outline cp-full');
    var forgotBack = el('p', 'cp-auth-switch');
    var forgotBackAnchor = el('a', 'cp-link', 'Back to Login');
    forgotBackAnchor.href = '#';
    forgotBack.appendChild(forgotBackAnchor);
    [forgotHeading, forgotEmailInput, forgotMsg, forgotSubmitBtn, forgotBack].forEach(function (n) {
      forgotWrap.appendChild(n);
    });
    card.appendChild(forgotWrap);

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
            errDiv.textContent = 'Incorrect email or password. Please try again.';
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

    forgotAnchor.addEventListener('click', function (e) {
      e.preventDefault();
      forgotWrap.classList.remove('cp-hidden');
      forgotMsg.className = 'cp-error cp-hidden';
      forgotMsg.textContent = '';
      forgotEmailInput.value = emailInput.value.trim();
      forgotEmailInput.focus();
    });

    forgotBackAnchor.addEventListener('click', function (e) {
      e.preventDefault();
      forgotWrap.classList.add('cp-hidden');
      forgotMsg.className = 'cp-error cp-hidden';
      forgotMsg.textContent = '';
    });

    forgotSubmitBtn.addEventListener('click', function () {
      var forgotEmail = forgotEmailInput.value.trim();
      if (!forgotEmail || !forgotEmailInput.checkValidity()) {
        forgotMsg.className = 'cp-error';
        forgotMsg.textContent = 'Please enter a valid email address.';
        return;
      }

      forgotSubmitBtn.disabled = true;
      forgotSubmitBtn.textContent = 'Sending…';
      forgotMsg.className = 'cp-error cp-hidden';
      forgotMsg.textContent = '';

      var fd = new FormData();
      fd.append('action', 'coachpro_forgot_password');
      fd.append('nonce', cfg.wpNonce);
      fd.append('email', forgotEmail);

      fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            forgotMsg.className = 'cp-success';
            forgotMsg.textContent = '✅ If an account with that email exists, a password reset link has been sent. Please check your inbox.';
          } else {
            forgotMsg.className = 'cp-error';
            forgotMsg.textContent = stripHtml((data && data.data && data.data.message) || 'Unable to send reset link. Please try again.');
          }
        })
        .catch(function () {
          forgotMsg.className = 'cp-error';
          forgotMsg.textContent = 'Network error. Please try again.';
        })
        .finally(function () {
          forgotSubmitBtn.disabled = false;
          forgotSubmitBtn.textContent = 'Send Reset Link';
        });
    });

    forgotEmailInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); forgotSubmitBtn.click(); }
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
            errDiv.textContent = stripHtml((data && data.data && data.data.message) || 'Registration failed.');
            errDiv.classList.remove('cp-hidden');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Account';
          }
        });
    });
  }

  /* -----------------------------------------------------------------------
   * RTL / Bidirectional text detection
   * Ported from Lovable src/lib/text-direction.ts
   * --------------------------------------------------------------------- */
  var RTL_RE_GLOBAL = /[\u0590-\u08FF\uFB1D-\uFDFF\uFE70-\uFEFF]/g;
  var LTR_RE_GLOBAL = /[A-Za-z\u00C0-\u024F]/g;

  function getTextDir(text) {
    if (!text) return 'ltr';
    var sample = text.slice(0, 200);
    var rtl = (sample.match(RTL_RE_GLOBAL) || []).length;
    var ltr = (sample.match(LTR_RE_GLOBAL) || []).length;
    if (rtl === 0) return 'ltr';
    if (ltr === 0) return 'rtl';
    return rtl >= ltr ? 'rtl' : 'ltr';
  }

  /* -----------------------------------------------------------------------
   * Prompt/code-block message parser + renderer
   * Ported from Lovable src/components/chat/AssistantMessage.tsx
   * Detects ```prompt and ```<lang> fenced blocks; renders each with a
   * labelled header bar and a one-click "Copy" button.
   * --------------------------------------------------------------------- */
  var MSG_FENCE_RE = /```([\w+-]*)\n?([\s\S]*?)```/g;
  var MSG_LABELED_PROMPT_RE = /(^|\n)\s*(?:here(?:'|')?s\s+(?:the|your)\s+prompt|copy\s+this\s+prompt|use\s+this\s+prompt|prompt)\s*[:\-\u2014]\s*\n+([\s\S]+?)(?=\n\s*\n\S|\n#{1,6}\s|$)/i;

  function parseMessageParts(content) {
    var parts = [];
    var last = 0;
    var m;
    MSG_FENCE_RE.lastIndex = 0;
    while ((m = MSG_FENCE_RE.exec(content)) !== null) {
      var before = content.slice(last, m.index);
      var lang = (m[1] || '').trim().toLowerCase();
      var code = m[2].replace(/\n$/, '');
      var trailing = before.match(/(^|\n)\s*prompt\s*[:\-\u2014]\s*\n?\s*$/i);
      var cleanBefore = before;
      var isPrompt = (lang === 'prompt');
      if (trailing) {
        cleanBefore = before.slice(0, before.length - trailing[0].length);
        isPrompt = true;
      }
      if (cleanBefore.trim()) parts.push({ type: 'text', text: cleanBefore.replace(/\s+$/, '') });
      parts.push({ type: 'code', lang: isPrompt ? 'prompt' : (lang || 'code'), code: code, isPrompt: isPrompt });
      last = m.index + m[0].length;
    }
    var tail = content.slice(last);
    if (tail.trim()) parts.push({ type: 'text', text: tail.replace(/^\s+/, '') });
    if (parts.length === 0) parts.push({ type: 'text', text: content });

    // Backup heuristic: detect "Prompt:" label in plain text parts without fences.
    var expanded = [];
    for (var i = 0; i < parts.length; i++) {
      var p = parts[i];
      if (p.type !== 'text') { expanded.push(p); continue; }
      var match = p.text.match(MSG_LABELED_PROMPT_RE);
      var captured = (match && match[3]) ? match[3].trim() : '';
      if (match && captured.length >= 40) {
        var start = p.text.indexOf(match[0]);
        var beforeText = p.text.slice(0, start + (match[1] ? match[1].length : 0)).replace(/\s+$/, '');
        var afterText = p.text.slice(start + match[0].length).replace(/^\s+/, '');
        if (beforeText.trim()) expanded.push({ type: 'text', text: beforeText });
        expanded.push({ type: 'code', lang: 'prompt', code: captured, isPrompt: true });
        if (afterText.trim()) expanded.push({ type: 'text', text: afterText });
      } else {
        expanded.push(p);
      }
    }
    return expanded;
  }

  function buildCodeBlock(part) {
    var wrap = el('div', 'cp-code-block');
    var header = el('div', 'cp-code-header');
    var labelEl = el('span', 'cp-code-label', escHtml(part.isPrompt ? 'Prompt' : part.lang));
    var copyBtnText = part.isPrompt ? '📋 Copy Prompt' : '📋 Copy';
    var copyBtn = btn(copyBtnText, 'cp-btn-outline cp-btn-sm cp-copy-btn');
    copyBtn.setAttribute('dir', 'ltr');
    copyBtn.addEventListener('click', function () {
      var originalText = part.isPrompt ? '📋 Copy Prompt' : '📋 Copy';
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(part.code).then(function () {
          copyBtn.textContent = '✅ Copied!';
          setTimeout(function () { copyBtn.textContent = originalText; }, 1500);
        }).catch(function () {
          copyBtn.textContent = '❌ Failed';
          setTimeout(function () { copyBtn.textContent = originalText; }, 1500);
        });
      } else {
        var ta = document.createElement('textarea');
        ta.value = part.code;
        ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0;';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); copyBtn.textContent = '✅ Copied!'; }
        catch (e) { copyBtn.textContent = '❌ Failed'; }
        document.body.removeChild(ta);
        setTimeout(function () { copyBtn.textContent = originalText; }, 1500);
      }
    });
    header.appendChild(labelEl);
    header.appendChild(copyBtn);
    var pre = document.createElement('pre');
    pre.className = 'cp-code-pre' + (part.isPrompt ? ' cp-prompt-pre' : '');
    pre.setAttribute('dir', 'ltr');
    var codeEl = document.createElement('code');
    codeEl.textContent = part.code;
    pre.appendChild(codeEl);
    wrap.appendChild(header);
    wrap.appendChild(pre);
    return wrap;
  }

  function renderAssistantContent(content) {
    var container = el('div', 'cp-msg-parsed-content');
    var parts = parseMessageParts(content || '');
    for (var i = 0; i < parts.length; i++) {
      var p = parts[i];
      if (p.type === 'code') {
        container.appendChild(buildCodeBlock(p));
      } else {
        var textEl = document.createElement('p');
        textEl.className = 'cp-msg-text-para';
        textEl.textContent = p.text;
        var dir = getTextDir(p.text);
        textEl.setAttribute('dir', dir);
        textEl.style.textAlign = dir === 'rtl' ? 'right' : 'left';
        container.appendChild(textEl);
      }
    }
    return container;
  }

  /* -----------------------------------------------------------------------
   * View: Dashboard
   * --------------------------------------------------------------------- */
  function chatRequestId() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      var r = window.crypto.getRandomValues(new Uint8Array(1))[0] & 15;
      return (c === 'x' ? r : (r & 3 | 8)).toString(16);
    });
  }

  // Keep uncertain requests on the same conversation and ID across button retries/renders.
  // Dashboard and legacy Chat share the same renderer and request lifecycle.
  function renderDashboard(container, cfg) { renderChat(container, cfg); }

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
        var editBtn = btn('Edit', 'cp-btn-outline cp-btn-sm');
        editBtn.addEventListener('click', function() {
          var name = window.prompt('Project name', p.name);
          if (!name || !name.trim()) return;
          var description = window.prompt('Description', p.description || '');
          if (description === null) return;
          api(cfg, 'projects/' + p.id, 'PUT', {name:name, description:description}).then(function() {renderProjects(el_container,cfg);}).catch(function(e) {showError(main,e.message);});
        });
        actions.appendChild(editBtn);
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
      header.innerHTML = '<div><span class="cp-eyebrow">YOUR EXPERT TEAM</span><h1>Assistants</h1><p>Find the right specialist for your next idea.</p></div>';

      var newBtn = btn('+ Create Assistant', 'cp-btn-primary');
      header.appendChild(newBtn);
      main.appendChild(header);

      // Create form
      var formWrap  = el('div', 'cp-form-wrap cp-hidden');
      var nameIn    = input('text', 'Assistant name');
      var descIn    = textarea('Short description');
      var promptIn  = textarea('System prompt (instructions for the AI)');
      promptIn.rows = 6;
      var startersIn = textarea('Conversation starters (one per line)');

      var iconValue = 'Bot';
      var picker = el('fieldset', 'cp-icon-picker');
      picker.innerHTML = '<legend>Assistant icon</legend>';
      var iconSearch = input('search', 'Search icons');
      iconSearch.setAttribute('aria-label', 'Search assistant icons');
      var iconGrid = el('div', 'cp-icon-grid');
      var iconStatus = el('p', 'cp-icon-status');
      iconStatus.setAttribute('aria-live', 'polite');
      function drawIcons() {
        iconGrid.innerHTML = '';
        Object.keys(workspaceIcons).filter(function(name) { return name.toLowerCase().includes(iconSearch.value.toLowerCase()); }).forEach(function(name) {
          var choice = btn('', 'cp-icon-choice'); choice.type = 'button';
          choice.innerHTML = workspaceIcon(name); choice.title = name;
          choice.setAttribute('aria-label', name); choice.setAttribute('aria-pressed', String(iconValue === name));
          choice.addEventListener('click', function() { iconValue = name; drawIcons(); });
          iconGrid.appendChild(choice);
        });
        iconStatus.textContent = 'Selected: ' + iconValue + (iconGrid.children.length ? '' : '. No matching icons.');
      }
      iconSearch.addEventListener('input', drawIcons);
      picker.appendChild(iconSearch); picker.appendChild(iconGrid); picker.appendChild(iconStatus); drawIcons();
      var editingId = '';
      var saveBtn   = btn('Create', 'cp-btn-primary');
      var cancelBtn = btn('Cancel', '');
      [nameIn, descIn, picker, promptIn, startersIn, saveBtn, cancelBtn].forEach(function (n) { formWrap.appendChild(n); });
      [[nameIn,'Assistant name'],[descIn,'Description'],[promptIn,'System prompt'],[startersIn,'Conversation starters (one per line)']].forEach(function(pair) { var label = el('label', 'cp-field-label', pair[1]); pair[0].id = 'cp-assistant-' + pair[1].split(' ')[0].toLowerCase(); label.htmlFor = pair[0].id; formWrap.insertBefore(label, pair[0]); });
      main.appendChild(formWrap);

      newBtn.addEventListener('click', function () { editingId=''; iconValue='Bot'; iconSearch.value=''; drawIcons(); nameIn.value=''; descIn.value=''; promptIn.value=''; startersIn.value=''; saveBtn.textContent='Create'; formWrap.classList.remove('cp-hidden'); });
      cancelBtn.addEventListener('click', function () { formWrap.classList.add('cp-hidden'); });
      saveBtn.addEventListener('click', function () {
        api(cfg, editingId ? 'assistants/' + editingId : 'assistants', editingId ? 'PUT' : 'POST', {
          icon: iconValue,
          name: nameIn.value,
          description: descIn.value,
          system_prompt: promptIn.value,
          conversation_starters: startersIn.value.split('\n').map(function(v) {return v.trim();}).filter(Boolean),
        }).then(function () { renderAssistants(el_container, cfg); })
          .catch(function (e) { showError(main, (e && e.message) || 'Failed to create assistant.'); });
      });

      var search = input('search', 'Search your assistants'); search.setAttribute('aria-label', 'Search assistants');
      var filter = select([{value:'all',label:'All assistants'},{value:'prebuilt',label:'Prebuilt'},{value:'custom',label:'Custom'}], 'all'); filter.setAttribute('aria-label', 'Assistant type');
      var toolbar = el('div', 'cp-assistant-toolbar'); toolbar.appendChild(search); toolbar.appendChild(filter); main.appendChild(toolbar);
      var searchStatus = el('p', 'cp-search-status'); searchStatus.setAttribute('aria-live', 'polite'); main.appendChild(searchStatus);
      function filterCards() { var count = 0; grid.querySelectorAll('.cp-assistant-card').forEach(function(card) { var matches = card.dataset.search.includes(search.value.toLowerCase()) && (filter.value === 'all' || card.dataset.kind === filter.value); card.hidden = !matches; if(matches) count++; }); searchStatus.textContent = count ? count + ' assistants' : 'No assistants match your search.'; }
      search.addEventListener('input', filterCards); filter.addEventListener('change', filterCards);
      var grid = el('div', 'cp-cards-grid');
      assistants.forEach(function (a) {
        var activated = String(a.is_activated) === '1' || a.is_activated === true;
        var suspended = Number(a.is_activation_suspended) === 1;
        var card = el('div', 'cp-card cp-assistant-card');
        card.dataset.search = ((a.name || '') + ' ' + (a.description || '')).toLowerCase();
        card.dataset.kind = Number(a.is_prebuilt) === 1 ? 'prebuilt' : 'custom';
        card.innerHTML = '<div class="cp-assistant-icon">' + workspaceIcon(a.icon) + '</div>' +
          '<h3>' + escHtml(a.name) + (Number(a.is_prebuilt) === 1 ? ' <span class="cp-badge">Prebuilt</span>' : ' <span class="cp-badge">Custom</span>') + '</h3>' +
          '<p>' + escHtml(a.description || '') + '</p>';

        var toggleBtn = btn(activated ? 'Activated' : 'Activate', activated ? 'cp-btn-outline' : 'cp-btn-primary');
        if (suspended) {
          card.appendChild(el('p', '', 'Paused by your plan. Your conversations are preserved; upgrade to restore access.'));
          toggleBtn.textContent = 'Remove paused activation';
        }
        toggleBtn.addEventListener('click', function () {
          if (activated || suspended) {
            api(cfg, 'assistants/' + a.id + '/activate', 'DELETE').then(function () { renderAssistants(el_container, cfg); });
          } else {
            api(cfg, 'assistants/' + a.id + '/activate', 'POST').then(function () { renderAssistants(el_container, cfg); })
              .catch(function (e) { showError(main, (e && e.message) || 'Failed.'); });
          }
        });

        var cardActions = el('div', 'cp-card-actions');
        cardActions.appendChild(toggleBtn);

        if (Number(a.is_prebuilt) !== 1) {
          var editBtn = btn('Edit', 'cp-btn-outline cp-btn-sm');
          editBtn.addEventListener('click', function() {
            iconValue = a.icon || 'Bot'; iconSearch.value = ''; drawIcons();
            editingId = a.id; nameIn.value = a.name; descIn.value = a.description || ''; promptIn.value = a.system_prompt || '';
            try { startersIn.value = (Array.isArray(a.conversation_starters) ? a.conversation_starters : JSON.parse(a.conversation_starters || '[]')).join('\n'); } catch(e) { startersIn.value = ''; }
            saveBtn.textContent = 'Save'; formWrap.classList.remove('cp-hidden'); formWrap.scrollIntoView();
          });
          cardActions.appendChild(editBtn);
          var delBtn = btn('Delete', 'cp-btn-danger cp-btn-sm');
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
      filterCards();
    }).catch(function (e) {
      showError(main, (e && e.message) || 'Failed to load assistants.');
    });
  }

  /* -----------------------------------------------------------------------
   * View: Chat
   * --------------------------------------------------------------------- */
  function renderChat(el_container, cfg) {
    el_container.innerHTML = '';
    el_container.appendChild(navBar(cfg, cfg.view === 'dashboard' ? 'dashboard' : 'chat'));
    el_container.classList.add('cp-unified-chat');

    var main = el('div', 'cp-main');
    el_container.appendChild(main);

    var state = {
      projectId: cfg.projectId || '',
      convId: cfg.convId || '',
      assistantId: cfg.assistantId || '',
      modelId: cfg.defaultModelId || '',
      credits: 0,
      projects: [],
      assistants: [],
      conversations: [],
      messages: [],
      messagePage: 1,
      hasOlder: false,
      savedMap: {},
      sending: false,
      requestId: '',
      requestText: '',
      draft: '',
      userName: '',
      billingNotice: '',
      modelCatalog: [],
      modelOverrides: {},
      historyOpen: false
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
      state.modelId = state.modelOverrides[state.assistantId] || (a && a.default_model_id) || cfg.defaultModelId || '';
    }

    function refreshSavedMap() {
      return api(cfg, 'saved-responses').then(function (rows) {
        state.savedMap = {};
        (rows || []).forEach(function (r) { state.savedMap[r.message_id] = r.id; });
      }).catch(function () { state.savedMap = {}; });
    }

    function syncUrl(replace) {
      var url = new URL(window.location.href);
      [['project_id',state.projectId],['assistant_id',state.assistantId],['conversation_id',state.convId]].forEach(function(pair) {
        if (pair[1]) url.searchParams.set(pair[0],pair[1]); else url.searchParams.delete(pair[0]);
      });
      if (url.href !== window.location.href) window.history[replace ? 'replaceState' : 'pushState']({}, '', url);
    }
    function resetChat() {
      if (state.sending) return;
      state.convId = ''; state.messages = []; state.draft = ''; state.requestId = ''; state.hasOlder = false;
      syncUrl(); render();
    }
    function loadConversations() {
      return api(cfg, 'conversations' + (state.projectId ? '?project_id=' + encodeURIComponent(state.projectId) : '')).then(function(rows) {
        state.conversations = rows || [];
        var selected = selectedConversation();
        if (selected) { state.projectId = selected.project_id; state.assistantId = selected.assistant_id; }
      });
    }
    if (el_container.cpChatHistory) el_container.cpChatHistory.abort();
    var historyEvents = new AbortController();
    el_container.cpChatHistory = historyEvents;
    window.addEventListener('popstate', function() {
      if (state.sending) { syncUrl(true); return; }
      var query = new URLSearchParams(window.location.search);
      state.projectId = query.get('project_id') || '';
      state.assistantId = query.get('assistant_id') || state.assistantId;
      state.convId = query.get('conversation_id') || '';
      loadConversations().then(loadMessages).then(render).catch(function(e) { showError(main,e.message); });
    }, {signal:historyEvents.signal});

    function loadMessages() {
      if (!state.convId) {
        state.hasOlder = false; state.messagePage = 1;
        state.messages = [];
        render();
        return Promise.resolve();
      }
      state.messagePage = 1;
      return api(cfg, 'conversations/' + state.convId + '/messages?per_page=100&page=1').then(function (rows) {
        state.hasOlder = rows.length === 100;
        state.messages = rows || [];
      });
    }

    function createConversation(title) {
      if (!state.projectId && state.assistantId) {
        return api(cfg, 'projects', 'POST', {name:'Quick Chat',description:'Auto-created for dashboard chat'}).then(function(project) { state.projectId = project.id; state.projects.push(project); return createConversation(title); });
      }
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
        return loadConversations().then(function () { syncUrl(); return conv; });
      });
    }

    function showTooLongDialog() {
      var modal = el('div', 'cp-modal');
      var previousFocus = document.activeElement;
      modal.setAttribute('role', 'dialog');
      modal.setAttribute('aria-modal', 'true');
      var card = el('div', 'cp-card');
      card.innerHTML = '<h3>This chat has grown very long</h3><p>Start a fresh chat for better responses.</p>';
      card.querySelector('h3').id = main.id + '-long-chat-title';
      modal.setAttribute('aria-labelledby', card.querySelector('h3').id);
      var freshBtn = btn('Start Fresh Chat', 'cp-btn-primary');
      var closeBtn = btn('Close', 'cp-btn-outline');
      freshBtn.addEventListener('click', function () {
        createConversation('New conversation').then(function () {
          modal.remove();
          render();
        });
      });
      function closeDialog() { modal.remove(); if (previousFocus && previousFocus.isConnected) previousFocus.focus(); }
      closeBtn.addEventListener('click', closeDialog);
      modal.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { e.preventDefault(); closeDialog(); }
        if (e.key === 'Tab' && e.shiftKey && document.activeElement === freshBtn) { e.preventDefault(); closeBtn.focus(); }
        else if (e.key === 'Tab' && !e.shiftKey && document.activeElement === closeBtn) { e.preventDefault(); freshBtn.focus(); }
      });
      card.appendChild(freshBtn);
      card.appendChild(closeBtn);
      modal.appendChild(card);
      main.appendChild(modal);
      freshBtn.focus();
    }

    function render() {
      var workspaceBalance = el_container.querySelector('.cp-credit-balance');
      if (workspaceBalance) renderCreditBadge(workspaceBalance, formatCredits(state.credits) + ' credits');
      var project = selectedProject();
      var assistant = selectedAssistant();
      var conv = selectedConversation();
      syncModelFromAssistant();

      main.innerHTML = '';

      var toolbar = el('div','cp-workspace-chat-toolbar');
      var menuBtn = btn('History','cp-btn-outline cp-chat-menu-btn');
      menuBtn.setAttribute('aria-expanded', String(state.historyOpen));
      menuBtn.setAttribute('aria-controls','cp-workspace-history');
      toolbar.appendChild(menuBtn);
      var layout = el('div','cp-chat-layout');
      var sidebar = el('aside','cp-chat-sidebar' + (state.historyOpen ? ' cp-sidebar-open' : ''));
      sidebar.id = 'cp-workspace-history';
      sidebar.setAttribute('aria-label','Conversation history and projects');
      sidebar.hidden = !state.historyOpen;
      var area = el('section','cp-chat-area');
      area.setAttribute('aria-label','AI conversation');
      var right = el('aside','cp-chat-right');
      main.appendChild(toolbar); main.appendChild(layout);
      layout.appendChild(sidebar); layout.appendChild(area);
      menuBtn.addEventListener('click',function() { state.historyOpen = !state.historyOpen; sidebar.hidden = !state.historyOpen; sidebar.classList.toggle('cp-sidebar-open',state.historyOpen); menuBtn.setAttribute('aria-expanded',String(state.historyOpen)); });
      sidebar.addEventListener('keydown',function(e) { if(e.key === 'Escape') { state.historyOpen = false; sidebar.hidden = true; menuBtn.setAttribute('aria-expanded','false'); menuBtn.focus(); } });

      sidebar.appendChild(el('h3', '', escHtml((project && project.name) || 'Project')));
      sidebar.appendChild(el('p', '', escHtml((project && project.description) || 'Select a project to begin.')));

      var projectSel = select(state.projects.map(function (p) { return { value: p.id, label: p.name }; }), state.projectId || '');
      projectSel.addEventListener('change', function () {
        if (state.sending) return;
        state.projectId = this.value;
        state.draft = ''; state.requestId = '';
        state.convId = '';
        loadConversations().then(loadMessages).then(function() { syncUrl(); render(); }).catch(function(e) { showError(main,e.message); });
      });
      sidebar.appendChild(el('label', 'cp-label', 'Project'));
      sidebar.appendChild(projectSel);

      var assistantSel = select(state.assistants.map(function (a) { return { value: a.id, label: a.name }; }), state.assistantId || '');
      assistantSel.addEventListener('change', function () {
        if (state.sending) return;
        state.assistantId = this.value;
        state.convId = ''; state.messages = [];
        syncModelFromAssistant();
        state.draft = ''; state.requestId = ''; syncUrl(); render();
      });
      sidebar.appendChild(el('label', 'cp-label', 'Select Assistant'));
      assistantSel.setAttribute('aria-label','Select assistant');
      projectSel.setAttribute('aria-label','Select project');
      projectSel.disabled = assistantSel.disabled = state.sending;
      toolbar.appendChild(assistantSel);
      if (state.modelCatalog.length) {
        var modelOptions=state.modelCatalog.map(function(m){return {value:m.id,label:m.name};});
        if (!modelOptions.some(function(m){return m.value===state.modelId;})) modelOptions.unshift({value:state.modelId,label:'Assistant default model'});
        var modelSel=select(modelOptions,state.modelId);modelSel.classList.add('cp-billing-model-selector');modelSel.setAttribute('aria-label','Select permitted AI model');modelSel.disabled=state.sending;
        modelSel.addEventListener('change',function(){if(state.sending)return;state.modelOverrides[state.assistantId]=this.value;state.requestId='';syncModelFromAssistant();render();});
        toolbar.appendChild(modelSel);
      }
      var freshTop = btn('New Chat','cp-btn-outline'); freshTop.disabled = state.sending; freshTop.addEventListener('click',resetChat); toolbar.appendChild(freshTop);

      var newBtn = btn('+ New Chat', 'cp-btn-primary cp-full');
      newBtn.addEventListener('click', function () {
        resetChat();
      });
      sidebar.appendChild(newBtn);

      sidebar.appendChild(el('h4', '', 'Conversations'));
      var convList = el('div', 'cp-conv-list');
      (state.conversations || []).forEach(function (c) {
        var row = el('div', 'cp-conv-row');
        var item = el('button', 'cp-conv-item' + (c.id === state.convId ? ' active' : ''), escHtml(c.title || 'Untitled'));
        item.addEventListener('click', function () {
          if (state.sending) return;
          state.draft = '';
          state.convId = c.id;
          state.assistantId = c.assistant_id || state.assistantId;
          state.historyOpen = false; syncUrl(); loadMessages().then(render).catch(function(e) { showError(main,e.message); });
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
          }).then(function() { syncUrl(true); render(); }).catch(function(e) { showError(sidebar,e.message); });
        });
        item.disabled = del.disabled = state.sending;
        del.setAttribute('aria-label','Delete ' + (c.title || 'conversation'));
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
          resetChat();
        });
        header.appendChild(fresh);
      }
      if (state.messages.length && score >= 50) area.appendChild(header);

      if (state.hasOlder) {
        var older = btn('Load older messages', 'cp-btn-outline');
        older.addEventListener('click', function() {
          older.disabled = true;
          api(cfg, 'conversations/' + state.convId + '/messages?per_page=100&page=' + (state.messagePage + 1)).then(function(rows) { state.messagePage++; state.hasOlder = rows.length === 100; state.messages = rows.concat(state.messages); render(); }).catch(function(e) { older.disabled = false; showError(area,e.message); });
        });
        area.appendChild(older);
      }
      var msgList = el('div', 'cp-msg-list');
      if (!state.messages.length) {
        var welcome = el('div','cp-chat-welcome');
        welcome.innerHTML = '<h1>Welcome</h1><p>What would you like to work on today?</p>';
        msgList.appendChild(welcome);
      }
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
        if (starters.children.length) msgList.appendChild(starters);
      }

      (state.messages || []).forEach(function (m) {
        var bubble = el('div', 'cp-msg cp-msg-' + escHtml(m.role));
        var content;
        if (m.role === 'assistant') {
          // Rich rendering: parse prompt/code fenced blocks with copy buttons
          content = el('div', 'cp-msg-content cp-msg-content-assistant');
          content.appendChild(renderAssistantContent(m.content || ''));
        } else {
          // User / system messages: simple plain text
          content = el('div', 'cp-msg-content', escHtml(m.content || ''));
          content.setAttribute('dir', getTextDir(m.content || ''));
        }
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
      inputBox.rows = 2;
      inputBox.value = state.draft || '';
      inputBox.addEventListener('input',function(){state.draft=inputBox.value;});
      inputBox.disabled = state.sending;
      inputBox.setAttribute('dir','auto');
      var sendBtn = btn('Send ➤', 'cp-btn-primary');
      sendBtn.disabled = state.sending || !state.assistantId;
      if(state.sending) sendBtn.textContent = 'Sending…';
      inputArea.appendChild(inputBox);
      inputArea.appendChild(sendBtn);
      area.appendChild(inputArea);
      var billNote = el('p','cp-billing-notice');
      billNote.setAttribute('role','status');
      // Users see only their own balance and what the last reply deducted.
      billNote.textContent = 'Balance: '+formatCredits(state.credits)+' credits'+(state.billingNotice ? ' · '+state.billingNotice : '');
      area.appendChild(billNote);

      function handleSend() {
        var text = inputBox.value.trim();
        if (!text || state.sending) return;
        state.draft = text;
        state.sending = true;
        if (!state.requestId || state.requestText !== text) {
          state.requestId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) { var r = window.crypto.getRandomValues(new Uint8Array(1))[0] & 15; return (c === 'x' ? r : (r & 3 | 8)).toString(16); });
          state.requestText = text;
        }
        var ensure = state.convId ? Promise.resolve({ id: state.convId }) : createConversation('New conversation');
        ensure.then(function (convRow) {
          if (!convRow || !convRow.id) { state.sending = false; return; }
          var isFirst = !(state.messages || []).some(function (m) { return m.role === 'user'; });

          sendBtn.disabled = true;
          sendBtn.textContent = 'Sending…';

          inputBox.disabled = true;
          toolbar.querySelectorAll('button,select').forEach(function(control) { control.disabled = true; });
          sidebar.querySelectorAll('button,select').forEach(function(control) { control.disabled = true; });
          msgList.querySelectorAll('.cp-chat-welcome,.cp-starter-chips').forEach(function(node) { node.remove(); });
          var pending = el('div','cp-msg cp-msg-user',escHtml(text)); pending.setAttribute('dir','auto'); msgList.appendChild(pending);
          var status = el('p','cp-chat-status','Thinking…'); status.setAttribute('role','status'); msgList.appendChild(status); msgList.scrollTop = msgList.scrollHeight;

          // ISSUE-02 fix: Do NOT separately POST to conversations/.../messages here.
          // The /chat endpoint saves the user message atomically along with the AI response.
          // Doing both caused every user message to be duplicated in the database.

          // Optionally auto-title the conversation on first message.
          var titlePromise = isFirst
            ? api(cfg, 'conversations/' + convRow.id, 'PUT', { title: text.slice(0, 50) }).catch(function () { return null; })
            : Promise.resolve(null);

          return titlePromise.then(function () {
            return api(cfg, 'chat', 'POST', {
              conversation_id: convRow.id,
              request_id: state.requestId,
              model_id: state.modelId || cfg.defaultModelId || '',
              message: text
            });
          }).then(function (resp) {
            state.credits = Number(resp.balance);
            state.billingNotice = 'Last reply: −' + formatCredits(resp.credits_used);
            state.requestId = '';
            state.draft = '';
            inputBox.value = '';
            // /chat already committed both messages. Show its authoritative reply
            // before any optional history refresh can stall or fail.
            state.messages.push({role:'user',content:text}, {
              id:resp.message_id,role:'assistant',content:resp.content,model_id:resp.model_id,
              credits_used:resp.credits_used
            });
            state.sending = false;
            render();
            loadConversations().then(refreshSavedMap).catch(function() {});
          }).catch(function (e) {
            // Pending/transport failures are ambiguous: reuse the idempotency key.
            if (e && e.code && ['request_timeout','chat_busy','request_processed','database_error'].indexOf(e.code) === -1) state.requestId = '';
            if (e && e.code === 'chat_too_long') {
              showTooLongDialog();
            } else {
              alert((e && e.message) || 'Failed to send message.');
            }
          }).finally(function () {
            state.sending = false;
            if (pending) pending.remove(); if(status) status.remove();
            inputBox.disabled = false;
            toolbar.querySelectorAll('button,select').forEach(function(control) { control.disabled = false; });
            sidebar.querySelectorAll('button,select').forEach(function(control) { control.disabled = false; });
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send ➤';
          });
        }).catch(function(e) { state.sending = false; alert((e && e.message) || 'Unable to create conversation.'); });
      }

      sendBtn.addEventListener('click', handleSend);
      inputBox.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
          e.preventDefault();
          handleSend();
        }
      });

      if (!state.assistantId) { var unavailable = el('p','cp-empty','Activate an assistant in Assistants to start chatting.'); area.insertBefore(unavailable,inputArea); }
      if(state.messages.length && state.messagePage === 1) msgList.scrollTop = msgList.scrollHeight;
      right.innerHTML = '<div class="cp-card"><h4>Assistant</h4>' +
        '<p><strong>Name:</strong> ' + escHtml((assistant && assistant.name) || '—') + '</p>' +
        '<p><strong>Description:</strong> ' + escHtml((assistant && assistant.description) || '—') + '</p>' +
        '<p><strong>Category:</strong> ' + escHtml((assistant && assistant.category) || '—') + '</p></div>';
    }

    Promise.all([
      api(cfg, 'auth/me'),
      api(cfg, 'projects'),
      api(cfg, 'assistants?active=1').catch(function () { return api(cfg, 'assistants'); }),
      refreshSavedMap(),
      api(cfg,'models').catch(function(){return [];})
    ]).then(function (results) {
      var user = results[0] || {};
      state.userName = user.display_name || user.name || '';
      state.credits = Number(user.credits || 0);
      state.modelCatalog=Array.isArray(results[4]) ? results[4] : [];
      state.projects = results[1] || [];
      state.assistants = (results[2] || []).filter(function (a) {
        return Number(a.is_prebuilt) !== 1 || String(a.is_activated) === '1' || a.is_activated === true;
      });


      if (!state.projectId && !state.convId && state.projects.length) state.projectId = state.projects[0].id;
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
      api(cfg, 'auth/me'),
      api(cfg, 'payment-info').catch(function () { return cfg.paymentDetails || {}; })
    ]).then(function (results) {
      var plans = results[0] || [];
      var packs = results[1] || [];
      var user = results[2] || {};
      var pay = results[3] || {};

      main.innerHTML = '';

      // Credit balance header
      var balHeader = el('div', 'cp-credits-balance-bar');
      balHeader.innerHTML =
        '<span class="cp-balance-credits">Available credits</span>' +
        '<span class="cp-balance-raw">🪙 ' + escHtml(formatCredits(user.credits)) + ' credits</span>' +
        (user.plan ? '<span class="cp-balance-plan">👑 ' + escHtml(String(user.plan).charAt(0).toUpperCase() + String(user.plan).slice(1)) + ' plan' + (user.plan_renews ? ' (until ' + escHtml(String(user.plan_renews).slice(0, 10)) + ')' : '') + '</span>' : '');
      main.appendChild(balHeader);

      // How to pay (manual)
      var infoSection = el('div', 'cp-payment-info');
      infoSection.innerHTML = '<h3>📲 How to pay</h3>' +
        '<ol>' +
        '<li>Choose a plan or credit pack below.</li>' +
        '<li>Send the amount by JazzCash, EasyPaisa or bank transfer to the account shown here.</li>' +
        '<li>Send your payment screenshot / transaction ID' + (pay.contact ? ' on <strong>' + escHtml(pay.contact) + '</strong>' : ' to us') + ' with your registered email (' + escHtml(user.email || '') + ').</li>' +
        '<li>We add your credits or activate your plan manually after confirming the payment.</li>' +
        '</ol>';
      var labels = { jazzcash: 'JazzCash', easypaisa: 'EasyPaisa', bank_transfer: 'Bank account' };
      var shown = 0;
      Object.keys(labels).forEach(function (key) {
        if (!pay[key]) return;
        shown++;
        var row = el('div', 'cp-payment-account');
        row.appendChild(el('strong', '', escHtml(labels[key]) + ':'));
        var value = el('div', 'cp-payment-value');
        value.textContent = String(pay[key]);
        value.style.whiteSpace = 'pre-line';
        row.appendChild(value);
        infoSection.appendChild(row);
      });
      if (!shown) infoSection.appendChild(el('p', 'cp-empty', 'Payment details have not been added yet. Please contact support.'));
      if (pay.instructions) {
        var instr = el('p', 'cp-payment-instructions');
        instr.textContent = String(pay.instructions);
        instr.style.whiteSpace = 'pre-line';
        infoSection.appendChild(instr);
      }
      main.appendChild(infoSection);

      // Plans
      main.appendChild(el('h2', 'cp-heading', '💳 Subscription Plans'));
      var plansGrid = el('div', 'cp-plans-grid');
      plans.forEach(function (plan) {
        var features = [];
        try { features = JSON.parse(plan.features || '[]'); } catch (e) {}
        var isFree = String(plan.id) === 'free';
        var card = el('div', 'cp-plan-card' + (Number(plan.is_popular) === 1 ? ' cp-plan-popular' : ''));
        if (Number(plan.is_popular) === 1) card.innerHTML = '<div class="cp-plan-badge">Most Popular</div>';
        card.innerHTML += '<h3>' + escHtml(plan.name) + '</h3>' +
          '<div class="cp-plan-price">₨ ' + escHtml(String(plan.price_pkr)) + (isFree ? '' : '/mo') + '</div>' +
          '<div class="cp-plan-credits">' + (isFree ? '20 credits once on signup' : escHtml(String(plan.monthly_credits)) + ' credits per month') + '</div>' +
          '<ul class="cp-plan-features">' + features.map(function (f) { return '<li>' + escHtml(f) + '</li>'; }).join('') + '</ul>';
        card.appendChild(el('div', 'cp-plan-free-note', String(user.plan || 'free') === String(plan.id) ? 'Your current plan' : (isFree ? 'Free' : 'Pay ₨ ' + escHtml(String(plan.price_pkr)) + ' using the details above')));
        plansGrid.appendChild(card);
      });
      main.appendChild(plansGrid);

      // Credit packs
      if (packs.length) {
        main.appendChild(el('h2', 'cp-heading', '🪙 Credit Packs'));
        var packsGrid = el('div', 'cp-plans-grid');
        packs.forEach(function (pack) {
          var card = el('div', 'cp-plan-card' + (Number(pack.is_popular) === 1 ? ' cp-plan-popular' : ''));
          if (Number(pack.is_popular) === 1) card.innerHTML = '<div class="cp-plan-badge">Best Value</div>';
          card.innerHTML += '<h3>' + escHtml(pack.name) + '</h3>' +
            '<div class="cp-plan-price">₨ ' + escHtml(String(pack.price_pkr)) + '</div>' +
            '<div class="cp-plan-credits">' + escHtml(String(pack.credits)) + ' credits</div>';
          card.appendChild(el('div', 'cp-plan-free-note', 'Pay ₨ ' + escHtml(String(pack.price_pkr)) + ' using the details above'));
          packsGrid.appendChild(card);
        });
        main.appendChild(packsGrid);
      }

    }).catch(function (e) {
      showError(main, (e && e.message) || 'Failed to load.');
    });
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

      // Profile picture / logo
      var picCard = el('div', 'cp-card cp-avatar-settings');
      picCard.appendChild(el('h3', '', 'Profile picture / logo'));
      var picRow = el('div', 'cp-avatar-row');
      picRow.style.display = 'flex'; picRow.style.alignItems = 'center'; picRow.style.gap = '16px'; picRow.style.flexWrap = 'wrap';
      var preview = avatarNode(user.avatar_url, 72);
      preview.style.border = '1px solid rgba(127,127,127,.35)';
      picRow.appendChild(preview);
      var picControls = el('div', '');
      var fileIn = input('file', 'Picture'); fileIn.accept = 'image/png,image/jpeg,image/webp';
      fileIn.setAttribute('aria-label', 'Choose a picture or logo');
      var uploadBtn = btn('Upload picture', 'cp-btn-primary cp-btn-sm');
      var removeBtn = btn('Remove picture', 'cp-btn-outline cp-btn-sm');
      removeBtn.style.display = user.avatar_url ? '' : 'none';
      var picMsg = el('p', 'cp-empty', 'JPG, PNG or WebP, up to 2 MB. Without a picture, a simple person icon is shown.');
      var picButtons = el('div', ''); picButtons.style.display = 'flex'; picButtons.style.gap = '8px'; picButtons.style.marginTop = '8px';
      picButtons.appendChild(uploadBtn); picButtons.appendChild(removeBtn);
      picControls.style.flex = '1'; picControls.style.minWidth = '220px';
      [fileIn, picButtons, picMsg].forEach(function (n) { picControls.appendChild(n); });
      picRow.appendChild(picControls);
      picCard.appendChild(picRow);
      function refreshAvatar(url) {
        user.avatar_url = url;
        var fresh = avatarNode(url, 72); fresh.style.border = preview.style.border;
        picRow.replaceChild(fresh, preview); preview = fresh;
        removeBtn.style.display = url ? '' : 'none';
        // Update the header picture too.
        document.querySelectorAll('.cp-profile-trigger .cp-profile-avatar').forEach(function (old) { old.parentNode.replaceChild(avatarNode(url, 34), old); });
      }
      uploadBtn.addEventListener('click', function () {
        var f = fileIn.files && fileIn.files[0];
        if (!f) { picMsg.textContent = 'Choose an image first.'; return; }
        if (f.size > 2 * 1024 * 1024) { picMsg.textContent = 'The image must be smaller than 2 MB.'; return; }
        uploadBtn.disabled = true; picMsg.textContent = 'Uploading…';
        var body = new FormData(); body.append('avatar', f);
        fetch(cfg.restUrl.replace(/\/$/, '') + '/profile/avatar', { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.wpNonce }, body: body })
          .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { if (!r.ok) throw new Error(d.message || 'Upload failed.'); return d; }); })
          .then(function (d) { refreshAvatar(d.avatar_url || ''); fileIn.value = ''; picMsg.textContent = 'Picture updated.'; })
          .catch(function (e) { picMsg.textContent = e.message || 'Upload failed.'; })
          .finally(function () { uploadBtn.disabled = false; });
      });
      removeBtn.addEventListener('click', function () {
        removeBtn.disabled = true;
        api(cfg, 'profile/avatar', 'DELETE').then(function () { refreshAvatar(''); picMsg.textContent = 'Picture removed.'; })
          .catch(function (e) { picMsg.textContent = (e && e.message) || 'Could not remove the picture.'; })
          .finally(function () { removeBtn.disabled = false; });
      });
      main.appendChild(picCard);

      var form = el('div', 'cp-card');

      var nameIn  = input('text', 'Display name', user.name || '');
      var emailIn = input('email', 'Email', user.email || '');
      var passIn  = input('password', 'New password (leave blank to keep current)');
      var currentPassIn = input('password', 'Current password (required for email/password changes)');
      var saveBtn = btn('Save Changes', 'cp-btn-primary');
      var errDiv  = el('div', 'cp-error cp-hidden');

      [el('label', 'cp-label', 'Display Name'), nameIn,
       el('label', 'cp-label', 'Email'), emailIn,
       el('label', 'cp-label', 'Current Password'), currentPassIn,
       el('label', 'cp-label', 'New Password'), passIn,
       errDiv, saveBtn].forEach(function (n) { form.appendChild(n); });

      saveBtn.addEventListener('click', function () {
        var payload = { display_name: nameIn.value };
        if (emailIn.value !== user.email) payload.email = emailIn.value;
        if (currentPassIn.value) payload.current_password = currentPassIn.value;
        if (passIn.value) payload.password = passIn.value;
        api(cfg, 'profile', 'PUT', payload).then(function (updated) {
          user = updated;
          cfg.wpNonce = updated.nonce || cfg.wpNonce;
          passIn.value = ''; currentPassIn.value = '';
          errDiv.classList.add('cp-hidden');
          showSuccess(form, 'Profile updated!');
        }).catch(function (e) {
          errDiv.textContent = (e && e.message) || 'Failed to update.';
          errDiv.classList.remove('cp-hidden');
        });
      });

      main.appendChild(form);

      form.id = 'cp-profile-settings';
      if (cfg.focusProfile || window.location.hash === '#cp-profile-settings') {
        cfg.focusProfile = false;
        nameIn.focus();
        form.scrollIntoView({block: 'nearest'});
      }

      // Credit info
      var infoCard = el('div', 'cp-card');
      infoCard.innerHTML = '<h3>Account Info</h3>' +
        '<p><strong>Plan:</strong> ' + escHtml((user.plan || 'free').toUpperCase()) + '</p>' +
        '<p><strong>Credits:</strong> ' + escHtml(formatCredits(user.credits)) + '</p>';
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
      { q: 'How are credits used?', a: 'Each reply uses some credits. Your balance is shown at the top and under the chat box, and Credit History lists every change.' },
      { q: 'Where do I buy credits?', a: 'Open the Buy Credits page, pay by JazzCash, EasyPaisa or bank transfer to the details shown, then send your payment confirmation. The admin adds your credits manually.' }
    ];

    main.innerHTML = '<div class="cp-card"><span class="cp-eyebrow">A LITTLE GUIDANCE, A LOT OF POSSIBILITY</span><h1>How to use AI Assistants</h1><p>Follow these steps to get the best results.</p></div>';

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

    main.appendChild(el('div', 'cp-card', '<h2>Work smarter with your coach</h2><p>Keep each chat focused on one goal and start fresh when it becomes too long.</p>'));

    var faqWrap = el('div', 'cp-card');
    faqWrap.innerHTML = '<h2>Frequently asked questions</h2>';
    faqs.forEach(function (f) {
      var item = el('details', 'cp-faq-item');
      var q = document.createElement('summary');
      q.textContent = f.q;
      var a = el('div', '', '<p>' + escHtml(f.a) + '</p>');
      item.appendChild(q);
      item.appendChild(a);
      faqWrap.appendChild(item);
    });
    main.appendChild(faqWrap);

    var cta = el('div', 'cp-card cp-center');
    cta.innerHTML = '<h2>Ready to continue?</h2><p>Go to Dashboard and start your next conversation.</p>';
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
  var TX_LABELS = {
    signup_bonus: 'Welcome bonus',
    subscription_grant: 'Plan credits',
    pack_purchase: 'Credits added',
    message_deduct: 'AI usage',
    admin_adjust: 'Admin adjustment',
    refund: 'Refund'
  };

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
          '<td>' + escHtml(TX_LABELS[tx.kind] || tx.kind || '') + '</td>' +
          '<td class="' + (Number(tx.amount) > 0 ? 'cp-pos' : 'cp-neg') + '">' + (Number(tx.amount) > 0 ? '+' : '') + escHtml(formatCredits(tx.amount)) + '</td>' +
          '<td>' + escHtml(formatCredits(tx.balance_after)) + '</td>' +
          '<td>' + escHtml(tx.notes || '') + '</td>';
        tbody.appendChild(tr);
      });
      table.appendChild(tbody);
      var tableScroll = el('div', 'cp-table-scroll');
      tableScroll.setAttribute('role', 'region');
      tableScroll.setAttribute('aria-label', 'Credit history');
      tableScroll.tabIndex = 0;
      tableScroll.appendChild(table);
      main.appendChild(tableScroll);
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
