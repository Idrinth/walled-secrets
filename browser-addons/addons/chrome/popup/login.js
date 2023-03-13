(async() => {
    const button = document.getElementsByTagName('button')[0];
    button.onclick = () => {
        chrome.runtime.sendMessage(
                {
                    type: 'master',
                    master: document.getElementById('master').value
                }
        );
        button.disabled = true;
    };
})();