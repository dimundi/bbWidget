document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-bbw-copy]');
    if (!button) return;
    const input = document.getElementById(button.dataset.bbwCopy);
    const status = button.parentElement.querySelector('[role="status"]');
    try {
        await navigator.clipboard.writeText(input.value);
        status.textContent = 'Skopiowano.';
    } catch {
        input.focus();
        input.select();
        status.textContent = 'Kod zaznaczony — skopiuj go skrótem Ctrl+C lub Cmd+C.';
    }
});
