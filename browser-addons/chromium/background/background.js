(() => {
    let lastDownloaded = 0;
    setInterval(async () => {
        chrome.storage.local.get(['cooldown'], async ({cooldown}) => {
            if (!cooldown) {
                cooldown = '15';
            }
            if (Date.now() - lastDownloaded > Number.parseInt(cooldown) * 1000) {
                chrome.storage.local.get(['email','apikey','url'], async ({email,apikey,url}) => {
                    if (email && apikey && url) {
                        const response = await fetch(url + '/api/list-secrets', {
                            method: 'POST',
                            mode: 'cors',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-LAST-UPDATED': lastDownloaded,
                            },
                            referrerPolicy: "no-referrer",
                            body: `apikey=${apikey}&email=${email}`,
                        });
                        try {
                            if (response.status === 304) {
                                return;
                            }
                            const data = await response.json();
                            if (data.error) {
                                console.log(data.error);
                                return;
                            }
                            lastDownloaded = Date.now();
                            chrome.storage.local.get(['folders'], ({folders}) => {
                                if (!equal(folders, data)) {
                                    chrome.storage.local.set({
                                        folders: data,
                                        lastModified: Date.now(),
                                    });
                                }
                            });
                        } catch (e) {
                            console.log(e);
                        }
                    }
                });
            }
        });
    }, 5000);
})();