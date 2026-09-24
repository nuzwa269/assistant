const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');

// Exercise the shipped sender and API helper, with an in-memory transport.
const source = fs.readFileSync(require('node:path').join(__dirname, '../public/js/coachpro-frontend.js'), 'utf8');
let calls, saved, charges, mode, serial;
const context = {
  window: { crypto: webcrypto }, Uint8Array, console,
  document: { readyState: 'loading', addEventListener() {} },
  fetch: async (url, opts) => {
    const path = url.replace('https://local.test/', '');
    const body = JSON.parse(opts.body);
    calls.push({ path, body });
    const response = (status, data) => ({ ok: status < 400, json: async () => data });
    if (path === 'projects') return response(200, { id: 'project-' + ++serial });
    if (path === 'conversations') {
      if (mode === 'conversation-failure') { mode = ''; throw new Error('Connection lost'); }
      return response(200, { id: 'conversation-' + ++serial });
    }
    assert.equal(path, 'chat');
    assert.match(body.request_id, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
    if (saved.get(body.request_id) === 'failed') return response(409, { code: 'request_failed' });
    if (saved.has(body.request_id)) return response(200, saved.get(body.request_id));
    if (mode === 'provider-failure') {
      mode = ''; saved.set(body.request_id, 'failed'); return response(502, { code: 'ai_error' });
    }
    if (mode === 'busy') { mode = ''; return response(409, { code: 'chat_busy' }); }
    if (mode === 'pending') { mode = ''; return response(409, { code: 'request_processed' }); }
    const answer = { content: 'Answer', balance: 10 - ++charges };
    saved.set(body.request_id, answer);
    if (mode === 'lost-response') { mode = ''; throw new Error('Response lost after server committed'); }
    return response(200, answer);
  }
};
vm.createContext(context);
vm.runInContext(source.replace(/\}\(\)\);\s*$/, 'globalThis.sendQuickChat = sendQuickChat; }());'), context);
const send = context.sendQuickChat;
function reset(nextMode = '') {
  calls = []; saved = new Map(); charges = 0; serial = 0; mode = nextMode;
  return { restUrl: 'https://local.test', wpNonce: 'mock' };
}
const chatCalls = () => calls.filter(c => c.path === 'chat');
let passed = 0;
async function test(name, run) { await run(); passed++; console.log('PASS ' + name); }
(async () => {
  await test('Lost successful response retries the same conversation and ID; one bill', async () => {
    const cfg = reset('lost-response');
    await assert.rejects(send(cfg, 'Hello', 'assistant-1', ''));
    const result = await send(cfg, 'Hello', 'assistant-1', '');
    assert.equal(charges, 1);
    assert.deepEqual(chatCalls()[0].body, chatCalls()[1].body);
    assert.equal(calls.filter(c => c.path === 'projects').length, 1);
    assert.equal(calls.filter(c => c.path === 'conversations').length, 1);
    assert.equal(result.conversationId, chatCalls()[0].body.conversation_id);
    assert.equal(cfg.quickChatAttempt, null);
  });
  for (const error of ['busy', 'pending']) {
    await test(error + ' response preserves the ID for a later retry', async () => {
      const cfg = reset(error);
      await assert.rejects(send(cfg, 'Hello', 'assistant-1', 'project-1'));
      await send(cfg, 'Hello', 'assistant-1', 'project-1');
      assert.deepEqual(chatCalls()[0].body, chatCalls()[1].body);
      assert.equal(charges, 1);
    });
  }
  await test('Rerender discovering the auto-created project preserves an uncertain request', async () => {
    const cfg = reset('lost-response');
    await assert.rejects(send(cfg, 'Hello', 'assistant-1', ''));
    await send(cfg, 'Hello', 'assistant-1', cfg.quickChatAttempt.projectId);
    assert.deepEqual(chatCalls()[0].body, chatCalls()[1].body);
    assert.equal(charges, 1);
  });
  await test('Confirmed refunded failure permits a new ID without a new conversation', async () => {
    const cfg = reset('provider-failure');
    await assert.rejects(send(cfg, 'Hello', 'assistant-1', 'project-1'));
    assert.equal(charges, 0);
    await send(cfg, 'Hello', 'assistant-1', 'project-1');
    const chat = chatCalls();
    assert.equal(chat[0].body.request_id, chat[1].body.request_id);
    assert.notEqual(chat[1].body.request_id, chat[2].body.request_id);
    assert.equal(new Set(chat.map(c => c.body.conversation_id)).size, 1);
    assert.equal(charges, 1);
  });
  await test('Double submission shares one in-flight operation', async () => {
    const cfg = reset();
    const first = send(cfg, 'Hello', 'assistant-1', '');
    const second = send(cfg, 'Hello', 'assistant-1', '');
    assert.equal(first, second);
    await Promise.all([first, second]);
    assert.equal(chatCalls().length, 1); assert.equal(charges, 1);
  });
  await test('Conversation setup failure reuses the already-created project', async () => {
    const cfg = reset('conversation-failure');
    await assert.rejects(send(cfg, 'Hello', 'assistant-1', ''));
    await send(cfg, 'Hello', 'assistant-1', '');
    assert.equal(calls.filter(c => c.path === 'projects').length, 1);
    assert.equal(charges, 1);
  });
  for (const change of ['message', 'assistant', 'project']) {
    await test('Changing ' + change + ' starts a distinct request', async () => {
      const cfg = reset('lost-response');
      await assert.rejects(send(cfg, 'Hello', 'assistant-1', 'project-1'));
      await send(cfg, change === 'message' ? 'Different' : 'Hello', change === 'assistant' ? 'assistant-2' : 'assistant-1', change === 'project' ? 'project-2' : 'project-1');
      assert.notEqual(chatCalls()[0].body.request_id, chatCalls()[1].body.request_id);
      assert.notEqual(chatCalls()[0].body.conversation_id, chatCalls()[1].body.conversation_id);
      assert.equal(charges, 2);
    });
  }
  console.log(passed + ' Quick Chat checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
