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
        chrome.storage.local.set({
            email,
            apikey,
            url,
        });
    };
    chrome.storage.local.get(['email','apikey','url'], ({email,apikey,url}) => {
        document.getElementById('email').value = email || '';
        document.getElementById('apikey').value = apikey || '';
        document.getElementById('url').value = url || '';
    });
})();