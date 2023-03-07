const requestFromAPI = async (type, master, id) => {
    const email = (await browser.storage.local.get('email')).email || '';
    const apikey = (await browser.storage.local.get('apikey')).apikey || '';
    const url = (await browser.storage.local.get('url')).url || '';
    const response = await fetch(url + '/api/' + type + '/' + id, {
        method: 'POST',
        mode: 'cors',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        referrerPolicy: "no-referrer",
        body: `apikey=${apikey}&email=${encodeURIComponent(email)}&master=${encodeURIComponent(master)}`,
    });
    const data = await response.json();
    if (data.error) {
        throw data.error;
    }
    return data;
};