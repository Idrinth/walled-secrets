document.getElementsByTagName('button')[0].onclick = async () => {
    document.getElementsByTagName('button')[0].disabled = true;
    document.getElementById('master').disabled = true;
    const master = document.getElementById('master').value;
    try {
        requestFromAPI('logins', master, location.hash.replace('#', ''), async (data) => {
            document.getElementById('username').value = data.login;
            document.getElementById('note').value = data.note;
            document.getElementById('password').value = data.pass;
            const h1 = document.getElementsByTagName('h1')[0];
            while (h1.firstChild) {
                h1.removeChild(h1.firstChild);
            }
            h1.appendChild(document.createTextNode(data.public));
            document.getElementsByTagName('div')[0].setAttribute('style', 'display:none');
        });
    } catch (e) {
        alert(e);
    }
    document.getElementsByTagName('button')[0].disabled = false;
    document.getElementById('master').disabled = false;
};