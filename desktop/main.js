/*
 * Desktop shell for the clinic PWA.
 *
 * The window loads <server>/app/ straight from the clinic server, so the PWA keeps working exactly as in
 * the browser: its service worker caches the shell (the app opens offline after the first launch),
 * IndexedDB holds the data, and the sync loop talks to the same /api. Nothing from public/app is copied here.
 *
 * Server address: CLINIC_SERVER_URL env > <userData>/config.json (set from the offline page) > bundled config.json.
 */
const { app, BrowserWindow, Menu, ipcMain, shell } = require('electron');
const fs = require('fs');
const path = require('path');

const userConfigPath = () => path.join(app.getPath('userData'), 'config.json');

function readJson(file) {
  try { return JSON.parse(fs.readFileSync(file, 'utf8')); } catch { return {}; }
}

function serverUrl() {
  const url = process.env.CLINIC_SERVER_URL
    || readJson(userConfigPath()).serverUrl
    || readJson(path.join(__dirname, 'config.json')).serverUrl
    || 'http://localhost:8000';
  return url.replace(/\/+$/, '');
}

let win = null;

function showOffline(reason) {
  win.loadFile(path.join(__dirname, 'offline.html'), { query: { server: serverUrl(), reason: reason || '' } });
}

function openApp() {
  win.loadURL(serverUrl() + '/app/');
}

function createWindow() {
  win = new BrowserWindow({
    width: 1366,
    height: 860,
    minWidth: 900,
    minHeight: 600,
    title: 'عيادتي',
    icon: path.join(__dirname, 'build', 'icon.png'),
    backgroundColor: '#EEF3F7',
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
    },
  });

  // Only the clinic server (and the local offline page) may be shown inside the window; anything else opens in the browser.
  const isInternal = url => url.startsWith(serverUrl() + '/') || url.startsWith('file:');
  win.webContents.setWindowOpenHandler(({ url }) => {
    if (!isInternal(url)) shell.openExternal(url);
    else win.loadURL(url);
    return { action: 'deny' };
  });
  win.webContents.on('will-navigate', (event, url) => {
    if (!isInternal(url)) {
      event.preventDefault();
      shell.openExternal(url);
    }
  });

  // Server unreachable and no cached shell yet (first launch offline, or wrong address).
  win.webContents.on('did-fail-load', (_e, code, description, url, isMainFrame) => {
    if (isMainFrame && code !== -3 /* ERR_ABORTED */ && !url.startsWith('file:')) showOffline(description);
  });

  openApp();
}

Menu.setApplicationMenu(Menu.buildFromTemplate([
  {
    label: 'البرنامج',
    submenu: [
      { label: 'إعادة تحميل', accelerator: 'F5', click: () => win && openApp() },
      { label: 'عنوان السيرفر…', click: () => win && showOffline('') },
      { type: 'separator' },
      { role: 'zoomIn', label: 'تكبير' },
      { role: 'zoomOut', label: 'تصغير' },
      { role: 'resetZoom', label: 'الحجم الطبيعي' },
      { role: 'togglefullscreen', label: 'ملء الشاشة' },
      { type: 'separator' },
      { role: 'toggleDevTools', label: 'أدوات المطور' },
      { role: 'quit', label: 'خروج' },
    ],
  },
]));

ipcMain.handle('clinic:get-server', () => serverUrl());
ipcMain.handle('clinic:set-server', (_e, url) => {
  const clean = String(url || '').trim().replace(/\/+$/, '');
  if (!/^https?:\/\/[^\s]+$/i.test(clean)) return false;
  fs.mkdirSync(path.dirname(userConfigPath()), { recursive: true });
  fs.writeFileSync(userConfigPath(), JSON.stringify({ serverUrl: clean }, null, 2));
  openApp();
  return true;
});
ipcMain.handle('clinic:retry', () => openApp());

// One window only: the PWA itself goes read-only ("dormant") in every copy but the newest.
if (!app.requestSingleInstanceLock()) {
  app.quit();
} else {
  app.on('second-instance', () => {
    if (!win) return;
    if (win.isMinimized()) win.restore();
    win.focus();
  });
  app.whenReady().then(createWindow);
  app.on('window-all-closed', () => app.quit());
}
