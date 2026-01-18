document.addEventListener('DOMContentLoaded', function () {
    const inputs = document.querySelectorAll('.address-autocomplete-input');

    inputs.forEach(input => {
        const wrapper = input.closest('.address-autocomplete-wrapper');
        const resultsList = wrapper.querySelector('.address-autocomplete-results');
        const displayDataInput = wrapper.querySelector('.address-data-input');

        let timeout = null;

        input.addEventListener('input', function () {
            clearTimeout(timeout);
            const query = this.value;

            if (query.length < 3) {
                resultsList.style.display = 'none';
                return;
            }

            timeout = setTimeout(() => {
                fetch(`https://api-adresse.data.gouv.fr/search/?q=${encodeURIComponent(query)}&limit=5`)
                    .then(response => response.json())
                    .then(data => {
                        resultsList.innerHTML = '';
                        if (data.features && data.features.length > 0) {
                            resultsList.style.display = 'block';
                            data.features.forEach(feature => {
                                const li = document.createElement('li');
                                li.textContent = feature.properties.label;
                                li.style.cursor = 'pointer';
                                li.style.padding = '5px';
                                li.addEventListener('click', () => {
                                    input.value = feature.properties.label;
                                    displayDataInput.value = JSON.stringify(feature);
                                    resultsList.style.display = 'none';
                                });
                                resultsList.appendChild(li);
                            });
                        } else {
                            resultsList.style.display = 'none';
                        }
                    });
            }, 300);
        });

        // Hide results when clicking outside
        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) {
                resultsList.style.display = 'none';
            }
        });
    });
});
