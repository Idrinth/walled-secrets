(() => {
    function onCreated() {
        if (chrome.runtime.lastError) {
            console.log(`Error: ${chrome.runtime.lastError}`);
        }
    }
    chrome.contextMenus.create(
            {
                id: 'walled-secrets-selection',
                title: 'Walled Secrets',
                contexts: ['all'],
            },
            onCreated
            );
    const items = [];
    chrome.runtime.onMessage.addListener(async (request, sender, sendResponse) => {
        if (request.type === 'master') {
            sendResponse('');
            chrome.storage.local.get(['tab', 'id'], async ({tab, id}) => {
                if (!request.master) {
                    chrome.tabs.sendMessage(tab, {type: 'error', error: 'No master password given.'});
                    return;
                }
                try {
                    requestFromAPI('logins', request.master, id, (data) => {
                        chrome.tabs.sendMessage(tab, {type: 'fill', pass: data.pass, user: data.login});
                    });
                } catch (e) {
                    chrome.tabs.sendMessage(tab, {type: 'error', error: e});
                }
            });
        }
        return false;
    });
    chrome.contextMenus.onClicked.addListener(async (info, tab) => {
        if (info.menuItemId !== 'walled-secrets-selection') {
            chrome.storage.local.set({tab: tab.id, id: info.menuItemId.replace(/^walled-secrets-/, '')});
            if (typeof chrome.action.openPopup === 'function') {
                chrome.action.openPopup();
            }
        }
    });
    const buildPublic = async (url) => {
        for (const id of items) {
            chrome.contextMenus.remove(id);
        }
        while (items.pop()) {
        }
        ;
        if (url) {
            const domain = url.replace(/^http?s:\/\/(.+?)(:[0-9]+)?(\/.*$|$)/, '$1');
            chrome.storage.local.get(['folders'], ({folders}) => {
                const reg = new RegExp(domain, 'i');
                for (const folder of Object.keys(folders)) {
                    if (folders[folder].logins) {
                        for (const login of folders[folder].logins) {
                            if (reg.test(login.public)) {
                                items.push('walled-secrets-' + login.id);
                                const org = folders[folder].organisation ? folders[folder].organisation + ':' : '';
                                chrome.contextMenus.create(
                                        {
                                            id: 'walled-secrets-' + login.id,
                                            title: login.public + ' (' + org + folders[folder].name + ')',
                                            parentId: 'walled-secrets-selection',
                                        },
                                        onCreated
                                        );
                            }
                        }
                    }
                }
                const top = domain.split('.');
                const toplevel = top[top.length - 2] + '.' + top[top.length - 1];
                if (toplevel === domain) {
                    return;
                }
                const reg2 = new RegExp(toplevel, 'i');
                for (const folder of Object.keys(folders)) {
                    if (folders[folder].logins) {
                        for (const login of folders[folder].logins) {
                            if (reg2.test(login.public) && !items.includes('walled-secrets-' + login.id)) {
                                items.push('walled-secrets-' + login.id);
                                const org = folders[folder].organisation ? folders[folder].organisation + ':' : '';
                                chrome.contextMenus.create(
                                        {
                                            id: 'walled-secrets-' + login.id,
                                            title: login.public + ' (' + org + folders[folder].name + ')',
                                            parentId: 'walled-secrets-selection',
                                        },
                                        onCreated
                                        );
                            }
                        }
                    }
                }
            });
        }
    };
    chrome.tabs.onActivated.addListener(async (activeInfo) => {
        chrome.tabs.query({ active: true, lastFocusedWindow: true }, async ([tab]) => {
            buildPublic(tab && tab.url ? tab.url : '');
        });
    });
    chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
        buildPublic(tab.url);
    });
})();