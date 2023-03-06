(() => {
    const forms = document.getElementsByTagName('form');
    for (const form in forms) {
        form.onsubmit = () => {
            return false;
        };
    }
})();