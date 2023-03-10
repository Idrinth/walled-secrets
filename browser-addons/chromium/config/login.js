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
        chrome.storage.local.set({
            email,
            apikey,
            url,
            cooldown,
        });
    };
    chrome.storage.local.get(['email','apikey','url','cooldown'], ({email,apikey,url,cooldown}) => {
        document.getElementById('email').value = email || '';
        document.getElementById('apikey').value = apikey || '';
        document.getElementById('url').value = url || '';
        document.getElementById('cooldown').value = cooldown || 15;
    });
})();