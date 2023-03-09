(() => {
    const forms = document.getElementsByTagName('form');
    for (const form in forms) {
        form.onsubmit = (e) => {
            (e || event).preventDefault();
            return false;
        };
    }
})();