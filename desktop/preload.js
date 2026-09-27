/*
 * Bridge for the local offline/settings page only. Pages served by the clinic server get nothing from here.
 */
const { contextBridge, ipcRenderer } = require('electron');

if (location.protocol === 'file:') {
  contextBridge.exposeInMainWorld('desktop', {
    getServer: () => ipcRenderer.invoke('clinic:get-server'),
    setServer: url => ipcRenderer.invoke('clinic:set-server', url),
    retry: () => ipcRenderer.invoke('clinic:retry'),
  });
}
