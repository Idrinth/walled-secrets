(async () => {
    const input = document.getElementsByTagName('input')[0];
    const list = document.getElementsByTagName('ul')[0];
    const search = () => {
        const notes = document.getElementsByClassName('note');
        const logins = document.getElementsByClassName('login');
        const folders = document.getElementsByClassName('folder');
        for (const folder of folders) {
            folder.setAttribute('data-count', '0');
        }
        const reg = new RegExp(input.value, 'i');
        for (const note of notes) {
            note.removeAttribute('style');
            if (!reg.test(note.children[0].innerHTML)) {
                note.setAttribute('style', 'display:none');
            } else {
                const folder = note.parentElement.parentElement;
                folder.setAttribute('data-count', Number.parseInt(folder.getAttribute('data-count')) + 1);
            }
        }
        for (const login of logins) {
            login.removeAttribute('style');
            if (!reg.test(login.children[0].innerHTML)) {
                login.setAttribute('style', 'display:none');
            } else {
                const folder = login.parentElement.parentElement;
                folder.setAttribute('data-count', Number.parseInt(folder.getAttribute('data-count')) + 1);
            }
        }
        for (const folder of folders) {
            folder.children[0].removeChild(folder.children[0].firstChild);
            folder.children[0].appendChild(document.createTextNode(`(${folder.getAttribute('data-count')})`));
        }
    };
    let lastUpdated = 0;
    const fill = async () => {
        chrome.storage.local.get(['lastModified', 'folders'], ({lastModified,folders}) => {
            if (lastModified < lastUpdated) {
                return;
            }
            while (list.firstChild) {
                list.removeChild(list.firstChild);
            }
            lastUpdated = Date.now();
            for (const folder of Object.keys(folders)) {
                list.appendChild(document.createElement('li'));
                list.lastChild.appendChild(document.createElement('out'));
                list.lastChild.lastChild.appendChild(document.createTextNode('(0)'));
                if (folders[folder].type === 'Account') {
                    list.lastChild.appendChild(document.createTextNode(folders[folder].name));
                } else if (folders[folder].organisation) {
                    list.lastChild.appendChild(document.createTextNode(folders[folder].name + ' (' + folders[folder].organisation + ')'));
                } else {
                    list.lastChild.appendChild(document.createTextNode(folders[folder].name + ' (Organisation)'));
                }
                list.lastChild.setAttribute('data-id', folder);
                list.lastChild.setAttribute('class', 'folder');
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
        });
    }
    setInterval(fill, 1000);
    await fill();
    input.onkeyup = search;
})();