(function () {
  'use strict';

  var cfg = window.coachproAdmin;
  if (!cfg) {
    return;
  }

  function escHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function adminApi(path, method, body) {
    var url = String(cfg.restUrl || '').replace(/\/$/, '') + '/' + path.replace(/^\//, '');
    return fetch(url, {
      method: method || 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce
      },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok) {
          return Promise.reject(data);
        }
        return data;
      });
    });
  }

  function errorMessage(error, fallback) {
    if (error && error.message) {
      return error.message;
    }
    return fallback;
  }

  function providerDefinitions() {
    return cfg.providerDefinitions || {};
  }

  function normalizeProvider(provider) {
    var value = String(provider || '').toLowerCase().trim();
    if (value === 'openai' || value === 'open-ai') {
      return 'openai';
    }
    if (value === 'anthropic' || value === 'claude') {
      return 'anthropic';
    }
    if (value === 'gemini' || value === 'google' || value === 'google gemini' || value === 'google-gemini') {
      return 'gemini';
    }
    return '';
  }

  function providerOptions(selected) {
    return Object.keys(providerDefinitions()).map(function (key) {
      var def = providerDefinitions()[key];
      return '<option value="' + escHtml(def.label) + '"' + (key === normalizeProvider(selected) ? ' selected' : '') + '>' + escHtml(def.label) + '</option>';
    }).join('');
  }

  function modelOptions(models, selected) {
    return ['<option value="">Use global default model</option>'].concat((models || []).map(function (model) {
      var label = model.display_name + ' (' + model.id + ')';
      return '<option value="' + escHtml(model.id) + '"' + (String(selected || '') === String(model.id) ? ' selected' : '') + '>' + escHtml(label) + '</option>';
    })).join('');
  }

  function showNotice(state, type, message) {
    state.notice = { type: type, message: message };
  }

  function renderNotice(state) {
    if (!state.notice || !state.notice.message) {
      return '';
    }
    return '<div class="notice notice-' + escHtml(state.notice.type || 'info') + ' is-dismissible"><p>' + escHtml(state.notice.message) + '</p></div>';
  }

  function excerpt(text, length) {
    var value = String(text || '');
    if (value.length <= length) {
      return value;
    }
    return value.slice(0, length) + '…';
  }

  function initAssistantsPage() {
    var root = document.getElementById('coachpro-prebuilt-assistants-admin');
    if (!root) {
      return;
    }

    var state = {
      assistants: [],
      models: [],
      editingId: '',
      notice: null
    };

    function currentAssistant() {
      var found = null;
      (state.assistants || []).forEach(function (assistant) {
        if (assistant.id === state.editingId) {
          found = assistant;
        }
      });
      return found;
    }

    function render() {
      var editing = currentAssistant() || {};

      root.innerHTML = '' +
        renderNotice(state) +
        '<div class="coachpro-admin-card">' +
          '<h2>' + escHtml(state.editingId ? 'Edit prebuilt assistant' : 'Create prebuilt assistant') + '</h2>' +
          '<p class="description">If no model is assigned, CoachPro uses the global default model: <strong>' + escHtml(cfg.defaultModelId || 'Not configured') + '</strong>.</p>' +
          '<form id="coachpro-assistant-form" class="coachpro-admin-form">' +
            '<div class="coachpro-admin-grid">' +
              '<p><label><strong>Icon</strong><input type="text" name="icon" class="regular-text" value="' + escHtml(editing.icon || 'Bot') + '"></label></p>' +
              '<p><label><strong>Name</strong><input type="text" name="name" class="regular-text" value="' + escHtml(editing.name || '') + '" required></label></p>' +
              '<p><label><strong>Category</strong><input type="text" name="category" class="regular-text" value="' + escHtml(editing.category || '') + '"></label></p>' +
              '<p><label><strong>Provider</strong><select name="provider">' +
                '<option value="">Auto-select from model</option>' +
                providerOptions(editing.provider || '') +
              '</select></label></p>' +
              '<p><label><strong>Model</strong><select name="default_model_id">' + modelOptions(state.models, editing.default_model_id || '') + '</select></label></p>' +
              '<p><label><strong>Temperature</strong><input type="number" name="temperature" class="small-text" min="0" max="2" step="0.1" value="' + escHtml(editing.temperature || '0.7') + '"></label></p>' +
              '<p><label><strong>Max tokens</strong><input type="number" name="max_tokens" class="small-text" min="1" step="1" value="' + escHtml(editing.max_tokens || '1024') + '"></label></p>' +
              '<p><label><strong>Active</strong><br><input type="checkbox" name="is_active"' + ((editing.is_active === undefined || String(editing.is_active) === '1') ? ' checked' : '') + '> Available to frontend users</label></p>' +
            '</div>' +
            '<p><label><strong>Description</strong><textarea name="description" rows="3" class="large-text">' + escHtml(editing.description || '') + '</textarea></label></p>' +
            '<p><label><strong>System prompt</strong><textarea name="system_prompt" rows="8" class="large-text" required>' + escHtml(editing.system_prompt || '') + '</textarea></label></p>' +
            '<p class="submit">' +
              '<button type="submit" class="button button-primary">' + escHtml(state.editingId ? 'Save assistant' : 'Create assistant') + '</button> ' +
              '<button type="button" class="button" id="coachpro-assistant-reset">Cancel</button>' +
            '</p>' +
          '</form>' +
        '</div>' +
        '<div class="coachpro-admin-card">' +
          '<h2>Prebuilt assistants</h2>' +
          '<table class="widefat striped">' +
            '<thead><tr>' +
              '<th>Icon</th><th>Name</th><th>Category</th><th>Description</th><th>System Prompt</th><th>Active</th><th>Actions</th>' +
            '</tr></thead>' +
            '<tbody>' +
              (state.assistants.length ? state.assistants.map(function (assistant) {
                return '<tr>' +
                  '<td>' + escHtml(assistant.icon || '') + '</td>' +
                  '<td><strong>' + escHtml(assistant.name || '') + '</strong></td>' +
                  '<td>' + escHtml(assistant.category || '') + '</td>' +
                  '<td>' + escHtml(excerpt(assistant.description || '', 120)) + '</td>' +
                  '<td><code>' + escHtml(excerpt(assistant.system_prompt || '', 140)) + '</code></td>' +
                  '<td>' + (String(assistant.is_active) === '1' ? '✅ Active' : '❌ Inactive') + '</td>' +
                  '<td class="coachpro-admin-actions">' +
                    '<button type="button" class="button button-small" data-action="edit" data-id="' + escHtml(assistant.id) + '">Edit</button> ' +
                    '<button type="button" class="button button-small" data-action="toggle" data-id="' + escHtml(assistant.id) + '">' + (String(assistant.is_active) === '1' ? 'Deactivate' : 'Activate') + '</button> ' +
                    '<button type="button" class="button button-small button-link-delete" data-action="delete" data-id="' + escHtml(assistant.id) + '">Delete</button>' +
                  '</td>' +
                '</tr>';
              }).join('') : '<tr><td colspan="7">No prebuilt assistants found.</td></tr>') +
            '</tbody>' +
          '</table>' +
        '</div>';

      root.querySelector('#coachpro-assistant-form').addEventListener('submit', function (event) {
        event.preventDefault();
        var formData = new window.FormData(event.target);
        var payload = {
          icon: formData.get('icon'),
          name: formData.get('name'),
          category: formData.get('category'),
          provider: formData.get('provider'),
          default_model_id: formData.get('default_model_id'),
          temperature: formData.get('temperature'),
          max_tokens: formData.get('max_tokens'),
          is_active: root.querySelector('[name="is_active"]').checked ? 1 : 0,
          description: formData.get('description'),
          system_prompt: formData.get('system_prompt')
        };

        var request = state.editingId
          ? adminApi('assistants/' + encodeURIComponent(state.editingId), 'PUT', payload)
          : adminApi('assistants', 'POST', payload);
        var successMessage = state.editingId ? 'Assistant updated.' : 'Assistant created.';

        request.then(function () {
          state.editingId = '';
          showNotice(state, 'success', successMessage);
          load();
        }).catch(function (error) {
          showNotice(state, 'error', errorMessage(error, 'Unable to save the assistant.'));
          render();
        });
      });

      root.querySelector('#coachpro-assistant-reset').addEventListener('click', function () {
        state.editingId = '';
        state.notice = null;
        render();
      });

      Array.prototype.slice.call(root.querySelectorAll('[data-action]')).forEach(function (button) {
        button.addEventListener('click', function () {
          var id = button.getAttribute('data-id');
          var action = button.getAttribute('data-action');
          var assistant = null;
          state.assistants.forEach(function (row) {
            if (row.id === id) {
              assistant = row;
            }
          });

          if (!assistant) {
            return;
          }

          if (action === 'edit') {
            state.editingId = id;
            state.notice = null;
            render();
            return;
          }

          if (action === 'toggle') {
            adminApi('assistants/' + encodeURIComponent(id), 'PUT', {
              is_active: String(assistant.is_active) === '1' ? 0 : 1
            }).then(function () {
              showNotice(state, 'success', 'Assistant status updated.');
              load();
            }).catch(function (error) {
              showNotice(state, 'error', errorMessage(error, 'Unable to update assistant status.'));
              render();
            });
            return;
          }

          if (action === 'delete' && window.confirm('Delete "' + assistant.name + '"?')) {
            adminApi('assistants/' + encodeURIComponent(id), 'DELETE').then(function () {
              if (state.editingId === id) {
                state.editingId = '';
              }
              showNotice(state, 'success', 'Assistant deleted.');
              load();
            }).catch(function (error) {
              showNotice(state, 'error', errorMessage(error, 'Unable to delete the assistant.'));
              render();
            });
          }
        });
      });
    }

    function load() {
      return Promise.all([
        adminApi('assistants'),
        adminApi('models')
      ]).then(function (results) {
        state.assistants = results[0] || [];
        state.models = results[1] || [];
        render();
      }).catch(function (error) {
        showNotice(state, 'error', errorMessage(error, 'Unable to load assistants.'));
        render();
      });
    }

    load();
  }

  function initProvidersPage() {
    var root = document.getElementById('coachpro-ai-providers-admin');
    if (!root) {
      return;
    }

    var state = {
      providerSettings: { providers: [], default_model_id: cfg.defaultModelId || '' },
      models: [],
      editingId: '',
      notice: null
    };

    function currentModel() {
      var found = null;
      (state.models || []).forEach(function (model) {
        if (model.id === state.editingId) {
          found = model;
        }
      });
      return found;
    }

    function render() {
      var editing = currentModel() || {};
      var providers = state.providerSettings.providers || [];

      root.innerHTML = '' +
        renderNotice(state) +
        '<div class="coachpro-admin-card">' +
          '<h2>Provider API keys</h2>' +
          '<form id="coachpro-provider-settings-form" class="coachpro-admin-form">' +
            '<table class="widefat striped">' +
              '<thead><tr><th>Provider</th><th>Saved key</th><th>New key</th><th>Test Connection</th></tr></thead>' +
              '<tbody>' +
                providers.map(function (provider) {
                  return '<tr>' +
                    '<td><strong>' + escHtml(provider.label) + '</strong></td>' +
                    '<td>' + (provider.configured ? '<code>' + escHtml(provider.masked_key) + '</code>' : 'Not configured') + '</td>' +
                    '<td><input type="password" class="regular-text" name="provider-' + escHtml(provider.id) + '" placeholder="' + escHtml(provider.masked_key || 'Enter API key') + '" autocomplete="off"></td>' +
                    '<td><button type="button" class="button" data-provider-test="' + escHtml(provider.id) + '">Test Connection</button></td>' +
                  '</tr>';
                }).join('') +
              '</tbody>' +
            '</table>' +
            '<p class="submit"><button type="submit" class="button button-primary">Save API keys</button></p>' +
          '</form>' +
        '</div>' +
        '<div class="coachpro-admin-card">' +
          '<h2>' + escHtml(state.editingId ? 'Edit model' : 'Add model') + '</h2>' +
          '<p class="description">Exactly one model should be the global default. Current default: <strong>' + escHtml(state.providerSettings.default_model_id || cfg.defaultModelId || 'Not configured') + '</strong>.</p>' +
          '<form id="coachpro-model-form" class="coachpro-admin-form">' +
            '<div class="coachpro-admin-grid">' +
              '<p><label><strong>Provider</strong><select name="provider_name">' + providerOptions(editing.provider || '') + '</select></label></p>' +
              '<p><label><strong>Model ID</strong><input type="text" name="model_id" class="regular-text" value="' + escHtml(editing.id || '') + '"' + (state.editingId ? ' readonly' : ' required') + '></label></p>' +
              '<p><label><strong>Display name</strong><input type="text" name="display_name" class="regular-text" value="' + escHtml(editing.display_name || '') + '" required></label></p>' +
              '<p><label><strong>Active</strong><br><input type="checkbox" name="is_active"' + ((editing.is_active === undefined || String(editing.is_active) === '1') ? ' checked' : '') + '> Model can be used</label></p>' +
              '<p><label><strong>Default model</strong><br><input type="checkbox" name="is_default"' + (String(editing.is_default) === '1' ? ' checked' : '') + '> Use as the global fallback</label></p>' +
            '</div>' +
            '<p class="submit">' +
              '<button type="submit" class="button button-primary">' + escHtml(state.editingId ? 'Save model' : 'Add model') + '</button> ' +
              '<button type="button" class="button" id="coachpro-model-reset">Cancel</button>' +
            '</p>' +
          '</form>' +
        '</div>' +
        '<div class="coachpro-admin-card">' +
          '<h2>Configured models</h2>' +
          '<table class="widefat striped">' +
            '<thead><tr><th>Provider</th><th>Model ID</th><th>Display Name</th><th>Status</th><th>Default</th><th>Actions</th></tr></thead>' +
            '<tbody>' +
              (state.models.length ? state.models.map(function (model) {
                return '<tr>' +
                  '<td>' + escHtml(model.provider || '') + '</td>' +
                  '<td><code>' + escHtml(model.id || '') + '</code></td>' +
                  '<td>' + escHtml(model.display_name || '') + '</td>' +
                  '<td>' + (String(model.is_active) === '1' ? '✅ Active' : '❌ Inactive') + '</td>' +
                  '<td>' + (String(model.is_default) === '1' ? '⭐ Default' : '—') + '</td>' +
                  '<td class="coachpro-admin-actions">' +
                    '<button type="button" class="button button-small" data-model-action="edit" data-id="' + escHtml(model.id) + '">Edit</button> ' +
                    '<button type="button" class="button button-small button-link-delete" data-model-action="delete" data-id="' + escHtml(model.id) + '">Delete</button>' +
                  '</td>' +
                '</tr>';
              }).join('') : '<tr><td colspan="6">No models configured.</td></tr>') +
            '</tbody>' +
          '</table>' +
        '</div>';

      root.querySelector('#coachpro-provider-settings-form').addEventListener('submit', function (event) {
        event.preventDefault();
        var payload = { providers: {} };
        providers.forEach(function (provider) {
          var input = root.querySelector('[name="provider-' + provider.id + '"]');
          payload.providers[provider.id] = { api_key: input ? input.value.trim() : '' };
        });

        adminApi('provider-settings', 'POST', payload).then(function (response) {
          state.providerSettings = response || state.providerSettings;
          state.notice = { type: 'success', message: 'Provider settings saved.' };
          render();
        }).catch(function (error) {
          showNotice(state, 'error', errorMessage(error, 'Unable to save provider settings.'));
          render();
        });
      });

      Array.prototype.slice.call(root.querySelectorAll('[data-provider-test]')).forEach(function (button) {
        button.addEventListener('click', function () {
          adminApi('provider-settings/test', 'POST', {
            provider: button.getAttribute('data-provider-test')
          }).then(function (response) {
            showNotice(state, 'success', response.message || 'Connection successful.');
            render();
          }).catch(function (error) {
            showNotice(state, 'error', errorMessage(error, 'Connection failed.'));
            render();
          });
        });
      });

      root.querySelector('#coachpro-model-form').addEventListener('submit', function (event) {
        event.preventDefault();
        var formData = new window.FormData(event.target);
        var payload = {
          provider_name: formData.get('provider_name'),
          display_name: formData.get('display_name'),
          is_active: root.querySelector('[name="is_active"]').checked ? 1 : 0,
          is_default: root.querySelector('[name="is_default"]').checked ? 1 : 0
        };

        if (!state.editingId) {
          payload.model_id = formData.get('model_id');
        }

        var request = state.editingId
          ? adminApi('models/' + encodeURIComponent(state.editingId), 'PUT', payload)
          : adminApi('models', 'POST', payload);

        request.then(function () {
          state.editingId = '';
          state.notice = { type: 'success', message: 'Model saved.' };
          load();
        }).catch(function (error) {
          showNotice(state, 'error', errorMessage(error, 'Unable to save the model.'));
          render();
        });
      });

      root.querySelector('#coachpro-model-reset').addEventListener('click', function () {
        state.editingId = '';
        state.notice = null;
        render();
      });

      Array.prototype.slice.call(root.querySelectorAll('[data-model-action]')).forEach(function (button) {
        button.addEventListener('click', function () {
          var action = button.getAttribute('data-model-action');
          var id = button.getAttribute('data-id');

          if (action === 'edit') {
            state.editingId = id;
            state.notice = null;
            render();
            return;
          }

          if (action === 'delete' && window.confirm('Delete model "' + id + '"?')) {
            adminApi('models/' + encodeURIComponent(id), 'DELETE').then(function () {
              if (state.editingId === id) {
                state.editingId = '';
              }
              state.notice = { type: 'success', message: 'Model deleted.' };
              load();
            }).catch(function (error) {
              showNotice(state, 'error', errorMessage(error, 'Unable to delete the model.'));
              render();
            });
          }
        });
      });
    }

    function load() {
      return Promise.all([
        adminApi('provider-settings'),
        adminApi('models')
      ]).then(function (results) {
        state.providerSettings = results[0] || state.providerSettings;
        state.models = results[1] || [];
        cfg.defaultModelId = state.providerSettings.default_model_id || cfg.defaultModelId;
        render();
      }).catch(function (error) {
        showNotice(state, 'error', errorMessage(error, 'Unable to load provider settings.'));
        render();
      });
    }

    load();
  }

  if (cfg.page === 'coachpro-assistants') {
    initAssistantsPage();
  }

  if (cfg.page === 'coachpro-ai-providers') {
    initProvidersPage();
  }
}());
