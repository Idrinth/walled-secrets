(() => {
    let lastDownloaded = 0;
    setInterval(async () => {
        if (Date.now() - lastDownloaded > 10000) {
            const email = (await browser.storage.local.get('email')).email || '';
            const apikey = (await browser.storage.local.get('apikey')).apikey || '';
            const url = (await browser.storage.local.get('url')).url || '';
            if (email && apikey && url) {
                const response = await fetch(url + '/api/list-secrets', {
                  method: "POST",
                  mode: 'no-cors',
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                  },
                  referrerPolicy: "no-referrer",
                  body: `apikey=${apikey}&email=${email}`,
                });
                const data = await response.json();
                if (data.error) {
                    return;
                }
                lastDownloaded = Date.now();
                browser.storage.local.set({
                    folders: data,
                });
            }
        }
    }, 2500);
})();