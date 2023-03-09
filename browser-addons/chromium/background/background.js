(() => {
    let lastDownloaded = 0;
    setInterval(async () => {
        if (Date.now() - lastDownloaded > 15000) {
            chrome.storage.local.get(['email','apikey','url'], async ({email,apikey,url}) => {
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
    }, 5000);
})();