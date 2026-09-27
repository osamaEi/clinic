/*
 * Offline-first data layer for the clinic PWA.
 *
 * The UI keeps mutating the in-memory `DB` object exactly as before and calls
 * Sync.commit() after each change. commit() diffs every collection against a
 * snapshot of the last committed state, turns the differences into an outbox
 * (upsert/delete per row), and saves everything to IndexedDB. A background loop
 * pushes the outbox to /api/sync, uploads queued files, and pulls changes made
 * on other devices. Conflicts resolve last-write-wins on the client timestamp.
 */
(function (global) {
  'use strict';

  const API = '/api';
  const ENTITIES = ['patients', 'appts', 'records', 'treatments', 'files', 'drugs', 'labs'];
  const PUSH_BATCH = 200;
  const PULL_EVERY_MS = 10000;
  const ACCOUNT_EVERY_CYCLES = 15;

  /* ── IndexedDB: one key/value store ── */
  const idb = (() => {
    let opening;
    const open = () => opening || (opening = new Promise((resolve, reject) => {
      const req = indexedDB.open('clinic-pwa', 1);
      req.onupgradeneeded = () => req.result.createObjectStore('kv');
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    }));
    const run = async (mode, work) => {
      const db = await open();
      return new Promise((resolve, reject) => {
        const tx = db.transaction('kv', mode);
        const result = work(tx.objectStore('kv'));
        tx.oncomplete = () => resolve(result);
        tx.onerror = tx.onabort = () => reject(tx.error);
      });
    };
    return {
      getMany: keys => run('readonly', store => {
        const out = {};
        keys.forEach(k => { store.get(k).onsuccess = e => { out[k] = e.target.result; }; });
        return out;
      }),
      putMany: entries => run('readwrite', store => {
        Object.entries(entries).forEach(([k, v]) => store.put(v, k));
      }),
      clear: () => run('readwrite', store => { store.clear(); }),
    };
  })();

  /* ── state ── */
  const S = { token: null, account: null, cursor: 0, outbox: {}, lastSync: null };
  const shadow = {};
  const dirty = new Set();
  let DB = null;
  let status = { online: navigator.onLine, syncing: false, error: '' };
  let saveTimer = null, syncTimer = null, running = null, rerun = false, cycles = 0;
  let remoteTouched = false, dormant = false;

  const hooks = {
    onStatus() {}, onRemote() {}, onAccount() {}, onAuthLost() {},
    onReadOnly() {}, onRejected() {}, onDormant() {},
  };

  /* ── ids: ms timestamp * 1000 + random, safe integers, unique enough across devices ── */
  let lastId = 0;
  function newId() {
    let id = Date.now() * 1000 + Math.floor(Math.random() * 1000);
    if (id <= lastId) id = lastId + 1;
    lastId = id;
    return id;
  }

  const isLocalData = v => String(v || '').startsWith('data:');
  function snap(entity, row) {
    if (entity !== 'files') return JSON.stringify(row);
    const { data, ...rest } = row;
    return JSON.stringify(rest) + (isLocalData(data) ? '|local' : '');
  }
  function rebuildShadow() {
    ENTITIES.forEach(e => { shadow[e] = new Map((DB[e] || []).map(r => [r.id, snap(e, r)])); });
  }
  const canWrite = () => !S.account || S.account.clinic.canWrite !== false;
  const pendingCount = () => Object.keys(S.outbox).length;

  function setStatus(patch) {
    status = { ...status, ...patch };
    hooks.onStatus({ ...status, pending: pendingCount(), lastSync: S.lastSync });
  }

  /* ── boot / persistence ── */
  async function boot(db) {
    DB = db;
    let saved = {};
    try {
      saved = await idb.getMany(['meta', 'db:settings', ...ENTITIES.map(e => 'db:' + e)]);
    } catch (err) {
      console.warn('IndexedDB unavailable, running in memory only', err);
    }
    const meta = saved.meta || {};
    S.token = meta.token || null;
    S.account = meta.account || null;
    S.cursor = meta.cursor || 0;
    S.outbox = meta.outbox || {};
    S.lastSync = meta.lastSync || null;
    ENTITIES.forEach(e => { if (Array.isArray(saved['db:' + e])) DB[e] = saved['db:' + e]; });
    if (saved['db:settings']) {
      Object.assign(DB.clinic, saved['db:settings'].clinic);
      DB.fees = saved['db:settings'].fees;
      if (saved['db:settings'].rx) DB.rx = saved['db:settings'].rx;
    }
    rebuildShadow();
    return S.account;
  }

  function diff() {
    const at = Date.now();
    const changes = [];
    for (const e of ENTITIES) {
      const seen = new Set();
      for (const row of DB[e]) {
        seen.add(row.id);
        const s = snap(e, row);
        if (shadow[e].get(row.id) !== s) changes.push({ e, op: 'upsert', id: row.id, s, at });
      }
      for (const id of shadow[e].keys()) {
        if (!seen.has(id)) changes.push({ e, op: 'delete', id, at });
      }
    }
    return changes;
  }

  /** Record whatever the UI changed since the last commit. */
  function commit() {
    if (!DB || !S.account || dormant) return false;
    const changes = diff();
    if (!changes.length) return false;
    if (!canWrite()) {
      revert();
      return false;
    }
    for (const c of changes) {
      if (c.op === 'upsert') shadow[c.e].set(c.id, c.s); else shadow[c.e].delete(c.id);
      S.outbox[c.e + ':' + c.id] = { entity: c.e, op: c.op, id: c.id, updatedAt: c.at };
      dirty.add('db:' + c.e);
    }
    scheduleSave();
    scheduleSync(1200);
    setStatus({});
    return true;
  }

  /** Read-only clinic: throw away the unsaved edit and redraw from the last saved state. */
  async function revert() {
    const saved = await idb.getMany(ENTITIES.map(e => 'db:' + e));
    ENTITIES.forEach(e => { DB[e] = saved['db:' + e] || []; });
    rebuildShadow();
    hooks.onReadOnly();
    hooks.onRemote();
  }

  function scheduleSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(save, 150);
  }

  async function save() {
    clearTimeout(saveTimer);
    saveTimer = null;
    const entries = { meta: { token: S.token, account: S.account, cursor: S.cursor, outbox: S.outbox, lastSync: S.lastSync } };
    dirty.forEach(k => { entries[k] = k === 'db:settings' ? { clinic: DB.clinic, fees: DB.fees, rx: DB.rx } : DB[k.slice(3)]; });
    dirty.clear();
    try {
      await idb.putMany(entries);
    } catch (err) {
      setStatus({ error: 'تعذر الحفظ على الجهاز: ' + (err && err.message) });
    }
  }

  /* ── HTTP ── */
  class HttpError extends Error {
    constructor(status, body) {
      super((body && body.message) || ('HTTP ' + status));
      this.status = status;
      this.body = body;
    }
  }

  async function api(path, opts = {}) {
    const headers = { Accept: 'application/json' };
    if (S.token) headers.Authorization = 'Bearer ' + S.token;
    let body = opts.body;
    if (body && !(body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(body);
    }
    let res;
    try {
      res = await fetch(API + path, { method: opts.method || 'GET', headers, body, cache: 'no-store' });
    } catch (err) {
      const offline = new Error('مفيش اتصال بالسيرفر');
      offline.offline = true;
      throw offline;
    }
    const data = res.status === 204 ? null : await res.json().catch(() => null);
    if (!res.ok) throw new HttpError(res.status, data);
    return data;
  }

  /* ── applying server rows ── */
  function applyServerRow(entity, row) {
    const clean = { ...row };
    delete clean.updatedAt;
    const list = DB[entity];
    const i = list.findIndex(r => r.id === row.id);
    const target = i >= 0 ? Object.assign(list[i], clean) : (list.push(clean), clean);
    shadow[entity].set(row.id, snap(entity, target));
    dirty.add('db:' + entity);
    remoteTouched = true;
  }

  function removeLocal(entity, id) {
    const list = DB[entity];
    const i = list.findIndex(r => r.id === id);
    if (i >= 0) list.splice(i, 1);
    shadow[entity].delete(id);
    dirty.add('db:' + entity);
    remoteTouched = true;
  }

  /** Reconcile one push/upload result with the outbox entry that produced it. */
  function settle(result, sent) {
    const key = result.entity + ':' + result.id;
    const pending = S.outbox[key];
    if (!pending || pending.updatedAt !== sent.updatedAt || pending.op !== sent.op) return; // edited again meanwhile
    delete S.outbox[key];
    if (result.status === 'ok') {
      if (result.row) applyServerRow(result.entity, result.row);
      return;
    }
    // stale or rejected: the server copy is the truth
    if (result.row) applyServerRow(result.entity, result.row); else removeLocal(result.entity, result.id);
    if (result.status === 'rejected') hooks.onRejected(result);
  }

  function rowOf(entity, id) {
    return DB[entity].find(r => r.id === id) || null;
  }

  const isQueuedUpload = c => c.entity === 'files' && c.op === 'upsert' && isLocalData((rowOf('files', c.id) || {}).data);

  async function pushChanges() {
    const queue = Object.values(S.outbox).filter(c => !isQueuedUpload(c));
    for (let i = 0; i < queue.length; i += PUSH_BATCH) {
      const batch = queue.slice(i, i + PUSH_BATCH)
        .map(c => ({ ...c, data: c.op === 'upsert' ? rowOf(c.entity, c.id) : null }))
        .filter(c => c.op === 'delete' || c.data);
      if (!batch.length) continue;
      const { results } = await api('/sync', { method: 'POST', body: { changes: batch } });
      results.forEach(r => settle(r, batch.find(c => c.entity === r.entity && c.id === r.id)));
    }
  }

  function dataUrlToBlob(url) {
    const [head, b64] = url.split(',');
    const mime = (head.match(/:(.*?);/) || [])[1] || 'application/octet-stream';
    const bin = atob(b64);
    const bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    return new Blob([bytes], { type: mime });
  }

  async function uploadFiles() {
    const jobs = Object.values(S.outbox).filter(isQueuedUpload);
    for (const job of jobs) {
      const f = rowOf('files', job.id);
      const form = new FormData();
      form.append('id', f.id);
      form.append('pid', f.pid);
      if (f.rid) form.append('rid', f.rid);
      form.append('kind', f.kind || '');
      form.append('note', f.note || '');
      form.append('date', f.date || '');
      form.append('updatedAt', job.updatedAt);
      form.append('file', dataUrlToBlob(f.data), f.name);
      try {
        const { row } = await api('/files', { method: 'POST', body: form });
        settle({ entity: 'files', id: f.id, status: row ? 'ok' : 'rejected', row, error: 'deleted' }, job);
      } catch (err) {
        if (err.body && err.body.error === 'plan_limit_storage') {
          hooks.onRejected({ entity: 'files', id: f.id, error: 'plan_limit_storage' });
          continue; // keep it queued until the plan is upgraded
        }
        if (err.status >= 400 && err.status < 500 && ![401, 402].includes(err.status)) {
          settle({ entity: 'files', id: f.id, status: 'rejected', row: null, error: err.message }, job);
          continue;
        }
        throw err;
      }
    }
  }

  async function pull() {
    const res = await api('/sync?since=' + S.cursor);
    for (const e of ENTITIES) {
      const rows = res.changes[e] || [];
      for (const row of rows) {
        if (!S.outbox[e + ':' + row.id]) applyServerRow(e, row);
      }
      for (const id of res.deleted[e] || []) {
        if (!S.outbox[e + ':' + id]) removeLocal(e, id);
      }
      if (res.reset) {
        const keep = new Set(rows.map(r => r.id));
        DB[e].filter(r => !keep.has(r.id) && !S.outbox[e + ':' + r.id]).forEach(r => removeLocal(e, r.id));
      }
    }
    if (res.settings) {
      Object.assign(DB.clinic, res.settings.clinic);
      DB.fees = res.settings.fees;
      if (res.settings.rx) {
        DB.rx = res.settings.rx;
        warmUrl(DB.rx.image); // so the prescription template prints offline too
      }
      dirty.add('db:settings');
      remoteTouched = true;
    }
    S.cursor = res.cursor;
    warmFiles(res.changes.files || []);
  }

  function warmUrl(url) {
    if (url && navigator.serviceWorker && navigator.serviceWorker.controller) fetch(url).catch(() => {});
  }

  /** Fetch new images once while online so the service worker can show them offline. */
  function warmFiles(rows) {
    if (!navigator.serviceWorker || !navigator.serviceWorker.controller) return;
    rows.filter(f => String(f.mime).startsWith('image/') && f.size < 8 * 1048576).slice(0, 30)
      .forEach(f => fetch(f.data).catch(() => {}));
  }

  async function refreshAccount() {
    S.account = await api('/me');
    hooks.onAccount(S.account);
  }

  function handleError(err) {
    if (err.offline) return setStatus({ online: false, error: '' });
    if (err.status === 401) {
      S.token = null;
      hooks.onAuthLost();
      return setStatus({ error: 'انتهت الجلسة — سجّل دخول تاني' });
    }
    if (err.status === 402) {
      if (S.account) S.account.clinic.canWrite = false;
      hooks.onAccount(S.account);
      return setStatus({ online: true, error: err.message });
    }
    console.error(err);
    setStatus({ online: true, error: err.message });
  }

  /* ── the sync loop ── */
  function scheduleSync(ms) {
    clearTimeout(syncTimer);
    syncTimer = setTimeout(sync, ms);
  }

  function sync() {
    if (!S.token || dormant) return Promise.resolve();
    if (running) {
      rerun = true;
      return running;
    }
    running = (async () => {
      setStatus({ syncing: true });
      remoteTouched = false;
      try {
        if (cycles++ % ACCOUNT_EVERY_CYCLES === 0) await refreshAccount();
        if (canWrite()) {
          await pushChanges();
          await uploadFiles();
        }
        await pull();
        S.lastSync = Date.now();
        setStatus({ online: true, error: '' });
      } catch (err) {
        handleError(err);
      } finally {
        await save();
        if (remoteTouched) hooks.onRemote();
        running = null;
        setStatus({ syncing: false });
        if (!dormant) scheduleSync(rerun ? 300 : PULL_EVERY_MS);
        rerun = false;
      }
    })();
    return running;
  }

  /* ── auth ── */
  async function wipeLocal() {
    ENTITIES.forEach(e => { DB[e] = []; });
    S.outbox = {};
    S.cursor = 0;
    S.lastSync = null;
    rebuildShadow();
    try { await idb.clear(); } catch (err) { /* nothing stored */ }
  }

  async function adopt(res) {
    // Same clinic (e.g. re-login after the token expired) keeps local data and unsynced edits.
    const sameClinic = S.account && S.account.clinic.id === res.clinic.id;
    if (!sameClinic) await wipeLocal();
    S.token = res.token;
    S.account = { user: res.user, clinic: res.clinic };
    cycles = 1; // account is fresh
    await save();
    await sync();
    return S.account;
  }

  const login = async (email, password) => adopt(await api('/login', { method: 'POST', body: { email, password } }));
  const register = async payload => adopt(await api('/register', { method: 'POST', body: payload }));

  /** Returns false (and does nothing) when unsynced edits would be lost, unless forced. */
  async function logout(force) {
    if (pendingCount()) await sync();
    if (pendingCount() && !force) return false;
    try { await api('/logout', { method: 'POST' }); } catch (err) { /* offline: token dies with the local wipe */ }
    await wipeLocal();
    S.token = null;
    S.account = null;
    return true;
  }

  /* ── one active tab: two tabs editing the same IndexedDB state would overwrite each other ── */
  const tabId = newId();
  const channel = 'BroadcastChannel' in global ? new BroadcastChannel('clinic-app') : null;
  if (channel) {
    channel.onmessage = e => {
      if (e.data && e.data.type === 'claim' && e.data.tabId !== tabId && !dormant) {
        commit();
        save();
        dormant = true;
        clearTimeout(syncTimer);
        hooks.onDormant();
      }
    };
  }

  function start() {
    if (channel) channel.postMessage({ type: 'claim', tabId });
    global.addEventListener('online', () => { setStatus({ online: true }); sync(); });
    global.addEventListener('offline', () => setStatus({ online: false }));
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') sync(); else save();
    });
    global.addEventListener('pagehide', () => save());
    if (navigator.storage && navigator.storage.persist) navigator.storage.persist().catch(() => {});
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/app/sw.js', { scope: '/app/' }).catch(err => console.warn('SW', err));
    }
    setStatus({});
    return sync();
  }

  global.Sync = {
    boot, start, commit, sync, newId, login, register, logout, api, canWrite, pendingCount, hooks,
    /** Store settings the owner just saved online (the next pull would bring them anyway). */
  saveSettingsLocally() { dirty.add('db:settings'); scheduleSave(); },
  warmUrl,
  get account() { return S.account; },
    get hasToken() { return !!S.token; },
    get status() { return { ...status, pending: pendingCount(), lastSync: S.lastSync }; },
  };
})(window);
