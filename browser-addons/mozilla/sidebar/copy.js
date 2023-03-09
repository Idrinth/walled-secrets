(() => {
    const inputs = document.getElementsByTagName('input');
    for (const input of inputs) {
        if (input.disabled) {
            input.addEventListener("click", () => {
                input.select();
                input.setSelectionRange(0, input.value.length);
                document.execCommand("copy");
            });
        }
        input.disabled = false;
    }
    const textareas = document.getElementsByTagName('textareas');
    for (const textarea of textareas) {
        if (textarea.disabled) {
            textarea.addEventListener("click", () => {
                textarea.select();
                textarea.setSelectionRange(0, textarea.value.length);
                document.execCommand("copy");
            });
        }
        textarea.disabled = false;
    }
})();