document.getElementsByTagName('button')[0].onclick = async () => {
    document.getElementsByTagName('button')[0].disabled = true;
    document.getElementById('master').disabled = true;
    const master = document.getElementById('master').value;
    try {
        const data = await requestFromAPI('notes', master, location.hash.replace('#',''));
        document.getElementById('id').value = data.id;
        document.getElementById('name').value = data.name;
        document.getElementById('content').value = data.content;
        const h1 = document.getElementsByTagName('h1')[0];
        while (h1.firstChild) {
            h1.removeChild(h1.firstChild);
        }
        h1.appendChild(document.createTextNode(data.public));
        document.getElementsByTagName('div')[0].setAttribute('style', 'display:none');
    } catch (e) {
        alert(e);
    }
    document.getElementsByTagName('button')[0].disabled = false;
    document.getElementById('master').disabled = false;
};