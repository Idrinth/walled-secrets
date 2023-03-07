browser.runtime.onMessage.addListener(async (request, senderId, sendResponse) => {
    if (request.type === 'error') {
        sendResponse('');
        alert(request.error);
    } else if (request.type === 'fill') {
        sendResponse('');
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
    }
    return false;
});