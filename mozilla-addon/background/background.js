(() => {
    let lastDownloaded = 0;
    setInterval(async () => {
        if (Date.now() - lastDownloaded > 15000) {
            const email = (await browser.storage.local.get('email')).email || '';
            const apikey = (await browser.storage.local.get('apikey')).apikey || '';
            const url = (await browser.storage.local.get('url')).url || '';
            if (email && apikey && url) {
                const response = await fetch(url + '/api/list-secrets', {
                    method: 'POST',
                    mode: 'cors',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    referrerPolicy: "no-referrer",
                    body: `apikey=${apikey}&email=${email}`,
                });
                try {
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