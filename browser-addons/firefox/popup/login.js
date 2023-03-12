(async() => {
    const button = document.getElementsByTagName('button')[0];
    button.onclick = () => {
        browser.runtime.sendMessage(
                'secrets@idrinth.de',
                {
                    type: 'master',
                    master: document.getElementById('master').value
                },
                {}
        );
        button.disabled = true;
    };
})();