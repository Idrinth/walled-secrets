document.getElementsByTagName('button')[0].onclick = async () => {
    document.getElementsByTagName('button')[0].disabled = true;
    document.getElementById('master').disabled = true;
    const email = (await browser.storage.local.get('email')).email || '';
    const apikey = (await browser.storage.local.get('apikey')).apikey || '';
    const url = (await browser.storage.local.get('url')).url || '';
    const master = document.getElementById('master').value;
    try {
        const response = await fetch(url + '/api/logins/'+location.hash.replace('#',''), {
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
        document.getElementById('id').value = data.id;
        document.getElementById('username').value = data.login;
        document.getElementById('domain').value = data.domain;
        document.getElementById('note').value = data.note;
        document.getElementById('password').value = data.password;
        document.getElementsByTagName('h1')[0].innerHTML = data.public;
        document.getElementsByTagName('div')[0].setAttribute('style', 'display:none');
    } catch (e) {
        alert(e);
    }
    document.getElementsByTagName('button')[0].disabled = false;
    document.getElementById('master').disabled = false;
};