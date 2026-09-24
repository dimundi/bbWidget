document.addEventListener('DOMContentLoaded', function () {
    const edition = document.getElementById('bbeditionId');
    const packages = document.getElementById('bbRacePackageId');
    if (edition && packages) {
        const options = Array.from(packages.options).map(option => option.cloneNode(true));
        const refresh = () => {
            const selected = packages.value;
            packages.replaceChildren(...options.filter(option => !option.value || option.dataset.edition === edition.value).map(option => option.cloneNode(true)));
            packages.value = Array.from(packages.options).some(option => option.value === selected) ? selected : '';
        };
        edition.addEventListener('change', refresh);
        refresh();
    }
    const consent = document.getElementById('irbEnabled');
    const nick = document.getElementById('irbNick');
    if (consent && nick) {
        const refresh = () => { nick.required = consent.checked; };
        consent.addEventListener('change', refresh);
        refresh();
    }
});