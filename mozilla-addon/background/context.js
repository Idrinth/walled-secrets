(() => {
    function onCreated() {
        if (browser.runtime.lastError) {
            console.log(`Error: ${browser.runtime.lastError}`);
        }
    }
    browser.contextMenus.create(
            {
                id: 'walled-secrets-selection',
                title: 'Walled Secrets',
                contexts: ['all'],
            },
            onCreated
            );
    const items = [];
    browser.runtime.onMessage.addListener(async (request, sender, sendResponse) => {
        if (request.type === 'master') {
            sendResponse('');
            const tab = (await browser.storage.local.get('tab')).tab;
            if (!request.master) {
                browser.tabs.sendMessage(tab, {type: 'error', error: 'No master password given.'});
                return;
            }
            const id = (await browser.storage.local.get('id')).id;
            try {
                const data = await requestFromAPI('logins', request.master, id);
                browser.tabs.sendMessage(tab, {type: 'fill', pass: data.pass, user: data.login});
            } catch (e) {
                browser.tabs.sendMessage(tab, {type: 'error', error: e});
            }
        }
        return false;
    });
    browser.contextMenus.onClicked.addListener(async (info, tab) => {
        if (info.menuItemId !== 'walled-secrets-selection') {
            browser.storage.local.set({tab: tab.id, id: info.menuItemId.replace(/^walled-secrets-/, '')});
            browser.browserAction.openPopup();
        }
    });
    const buildPublic = async (url) => {
        for (const id of items) {
            browser.contextMenus.remove(id);
        }
        while (items.pop()) {
        }
        ;
        if (url) {
            const domain = url.replace(/^http?s:\/\/(.+?)(\/.*$|$)/, '$1');
            const folders = (await browser.storage.local.get('folders')).folders || {};
            const reg = new RegExp(domain, 'i');
            for (const folder of Object.keys(folders)) {
                if (folders[folder].logins) {
                    for (const login of folders[folder].logins) {
                        if (reg.test(login.public)) {
                            items.push('walled-secrets-' + login.id);
                            browser.contextMenus.create(
                                    {
                                        id: 'walled-secrets-' + login.id,
                                        title: login.public + ' (' + folders[folder].name + ')',
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
                            browser.contextMenus.create(
                                    {
                                        id: 'walled-secrets-' + login.id,
                                        title: login.public + ' (' + folders[folder].name + ')',
                                        parentId: 'walled-secrets-selection',
                                    },
                                    onCreated
                                    );
                        }
                    }
                }
            }
        }
    };
    browser.tabs.onActivated.addListener(async (activeInfo) => {
        const tab = await browser.tabs.getCurrent();
        if (tab && tab.url) {
            buildPublic(tab.url);
        }
    });
    browser.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
        buildPublic(tab.url);
    }, {properties: ['url']});
})();