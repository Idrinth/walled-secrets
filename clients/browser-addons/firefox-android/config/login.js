(async () => {
    const form = document.getElementsByTagName('form')[0];
    document.getElementsByTagName('button')[0].onclick = () => {
        if (!form.reportValidity()) {
            alert('Please fix your inputs.');
            return;
        }
        const url = document.getElementById('url').value;
        const apikey = document.getElementById('apikey').value;
        const email = document.getElementById('email').value;
        const cooldown = document.getElementById('cooldown').value;
        browser.storage.local.set({
            email,
            apikey,
            url,
            cooldown,
        });
    };
    document.getElementById('email').value = (await browser.storage.local.get('email')).email || '';
    document.getElementById('apikey').value = (await browser.storage.local.get('apikey')).apikey || '';
    document.getElementById('url').value = (await browser.storage.local.get('url')).url || '';
    document.getElementById('cooldown').value = (await browser.storage.local.get('cooldown')).cooldown || 15;
})();