(() => {
    const inputs = document.getElementsByTagName('input');
    for (const input of inputs) {
        input.addEventListener("click", () => {
            input.select();
            input.setSelectionRange(0, input.value.length);
            document.execCommand("copy");
        });
    }
    const textareas = document.getElementsByTagName('textareas');
    for (const textarea of textareas) {
        textarea.addEventListener("click", () => {
            textarea.select();
            textarea.setSelectionRange(0, textarea.value.length);
            document.execCommand("copy");
        });
    }
})();