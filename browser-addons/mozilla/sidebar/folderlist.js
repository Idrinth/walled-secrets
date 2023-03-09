(async () => {
    const input = document.getElementsByTagName('input')[0];
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
        while (list.firstChild) {
            list.removeChild(list.firstChild);
        }
        const folders = (await browser.storage.local.get('folders')).folders || {};
        lastUpdated = Date.now();
        for (const folder of Object.keys(folders)) {
            list.appendChild(document.createElement('li'));
            list.lastChild.appendChild(document.createTextNode(folders[folder].name + ' (' + folders[folder].type + ')'));
            list.lastChild.setAttribute('data-id', folder);
            list.lastChild.appendChild(document.createElement('ul'));
            const ul = list.lastChild.lastChild;
            ul.setAttribute('class', 'inactive');
            list.lastChild.onclick = () => {
                ul.classList.toggle('inactive');
            };
            for (const login of folders[folder].logins) {
                ul.appendChild(document.createElement('li'));
                ul.lastChild.setAttribute('class', 'login');
                ul.lastChild.appendChild(document.createElement('a'));
                ul.lastChild.lastChild.appendChild(document.createTextNode(login.public));
                ul.lastChild.lastChild.setAttribute('href', 'login.html#' + login.id);
            }
            for (const note of folders[folder].notes) {
                ul.appendChild(document.createElement('li'));
                ul.lastChild.setAttribute('class', 'note');
                ul.lastChild.appendChild(document.createElement('a'));
                ul.lastChild.lastChild.appendChild(document.createTextNode(note.public));
                ul.lastChild.lastChild.setAttribute('href', 'note.html#' + note.id);
            }
        }
        search();
    }
    setInterval(fill, 1000);
    await fill();
    input.onkeyup = search;
})();