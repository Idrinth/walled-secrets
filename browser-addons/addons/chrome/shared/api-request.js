const requestFromAPI = async (type, master, id, callback) => {
    chrome.storage.local.get(['email','apikey','url'], async ({email,apikey,url}) => {
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
        callback(data);
    });
};