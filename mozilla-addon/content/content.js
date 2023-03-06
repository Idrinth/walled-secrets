browser.runtime.onMessage.addListener(async (request, senderId, sendResponse) => {
    sendResponse('');
    if (request.type==='master') {
        const dialog = document.createElement('dialog');
        dialog.setAttribute('id', 'idrinth-walled-secrets');
        dialog.appendChild(document.createElement('h1'));
        dialog.lastChild.appendChild(document.createTextNode('Walled Secrets'));
        dialog.appendChild(document.createElement('label'));
        dialog.lastChild.setAttribute('for', 'idrinth-walled-secrets-password');
        dialog.lastChild.appendChild(document.createTextNode('Your Master-Password'));
        dialog.appendChild(document.createElement('input'));
        dialog.lastChild.setAttribute('id', 'idrinth-walled-secrets-password');
        dialog.lastChild.setAttribute('type', 'password');
        dialog.lastChild.onchange = () => {
            browser.runtime.sendMessage(
                'secrets@idrinth.de',
                {type: 'master', master: document.getElementById('idrinth-walled-secrets-password').value, id: request.id, tab: request.tab},
                {}
            );
            dialog.removeChild(dialog.lastChild);
            dialog.removeChild(dialog.lastChild);
            dialog.appendChild(document.createElement('p'));
            dialog.lastChild.appendChild(document.createTextNode('Loading decripted password and login. This may take a while.'));
        };
        document.body.appendChild(dialog);
        dialog.showModal();
    } else if (request.type === 'fill') {
        const form = document.getElementById('idrinth-walled-secrets');
        if (form) {
            document.body.removeChild(form);
        }
        const inputs = document.getElementsByTagName('input');
        for (const input of inputs) {
            if ((input.getAttribute('type') || '').toLowerCase() === 'password') {
                input.value = request.pass;
                let parent = input.parentElement;
                do {
                    const inpts = parent.getElementsByTagName('input');
                    for (const inpt of inpts) {
                        if ((inpt.getAttribute('type') || 'text').toLowerCase() === 'text' || inpt.getAttribute('type').toLowerCase() === 'email') {
                            inpt.value = request.user;
                        }
                    }
                } while (parent = parent.parentElement);
            }
        }
    } else if (request.type === 'destroy') {
        const form = document.getElementById('idrinth-walled-secrets');
        if (form) {
            document.body.removeChild(form);
        }
        alert(request.error);
    }
});