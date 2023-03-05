(async () => {
    const windowInfo = await browser.windows.getCurrent({ populate: true });    
    const input = document.getElementsByTagName('input')[0];
    for (const tab in windowInfo.tabs) {
        if (tab.active) {
            input.value = tab.url.replace(/^https?:\/\/(.+?)\/.*/, '$1');
        }
    }
    const list = document.getElementsByTagName('ul')[0];
    const search = () => {
        const notes = document.getElementsByClassName('note');
        const logins = document.getElementsByClassName('login');
        const reg = new RegExp(input.value, 'i');
        for (const note of notes) {
            note.removeAttribute('style');
            if (!reg.test(note.innerHTML)) {
                note.setAttribute('style', 'display:none');
            }
        }
        for (const login of logins) {
            login.removeAttribute('style');
            if (!reg.test(login.innerHTML)) {
                login.setAttribute('style', 'display:none');
            }
        }
    };
    let lastUpdated = 0;
    const fill = async () => {
        const lastModified = (await browser.storage.local.get('lastModified')).lastModified || 0;
        if (lastModified < lastUpdated) {
            return;
        }
        while(list.firstChild) {
            list.removeChild(list.firstChild);
        }
        const folders = (await browser.storage.local.get('folders')).folders || {};
        lastUpdated = Date.now();
        for (const folder of Object.keys(folders)) {
            list.appendChild(document.createElement('li'));
            list.lastChild.appendChild(document.createTextNode(folders[folder].name));
            list.lastChild.setAttribute('data-id', folder);
            list.lastChild.appendChild(document.createElement('ul'));
            const ul = list.lastChild.lastChild;
            for (const login of folders[folder].logins) {
                ul.appendChild(document.createElement('li'));
                ul.lastChild.appendChild(document.createTextNode(login.public));
                ul.lastChild.setAttribute('data-id', login.id);
                ul.lastChild.setAttribute('data-type', 'login');
                ul.lastChild.setAttribute('class', 'login');
            }
            for (const note of folders[folder].notes) {
                ul.appendChild(document.createElement('li'));
                ul.lastChild.appendChild(document.createTextNode(note.public));
                ul.lastChild.setAttribute('data-id', note.id);
                ul.lastChild.setAttribute('data-type', 'note');
                ul.lastChild.setAttribute('class', 'note');
            }
        }
        search();
    }
    setInterval(fill, 1000);
    await fill();
    input.onkeyup = search;
})();