(() => {
    let lastDownloaded = 0;
    setInterval(async () => {
        const cooldown = Number.parseInt((await browser.storage.local.get('cooldown')).cooldown || '15');
        if (Date.now() - lastDownloaded > cooldown * 1000) {
            const email = (await browser.storage.local.get('email')).email || '';
            const apikey = (await browser.storage.local.get('apikey')).apikey || '';
            const url = (await browser.storage.local.get('url')).url || '';
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
                    const previous = (await browser.storage.local.get('folders')).folders || {};
                    if (!equal(previous, data)) {
                        browser.storage.local.set({
                            folders: data,
                            lastModified: Date.now(),
                        });
                    }
                } catch (e) {
                    console.log(e);
                }
            }
        }
    }, 5000);
})();