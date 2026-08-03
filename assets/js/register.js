document.addEventListener('DOMContentLoaded', function () {
    const accountCards = document.querySelectorAll('.select-card');
    const continueBtn = document.getElementById('continueBtn');
    let selectedAccount = 'patient';

    if (accountCards.length && continueBtn) {
        accountCards.forEach(card => {
            card.addEventListener('click', function () {
                accountCards.forEach(node => {
                    node.classList.remove('active');
                    node.setAttribute('aria-pressed', 'false');
                });
                card.classList.add('active');
                card.setAttribute('aria-pressed', 'true');
                selectedAccount = card.dataset.account;
            });
        });

        continueBtn.addEventListener('click', function () {
            if (selectedAccount === 'doctor') {
                window.location.href = 'doctor-registration.php';
            } else {
                window.location.href = 'patient-registration.php';
            }
        });
    }

    const patientForm = document.getElementById('patientForm');
    const doctorForm = document.getElementById('doctorForm');

    function handleFormSubmit(event, message) {
        event.preventDefault();
        alert(message);
    }

    if (patientForm) {
        patientForm.addEventListener('submit', function (event) {
            handleFormSubmit(event, 'Patient account request submitted.');
        });
    }

    if (doctorForm) {
        doctorForm.addEventListener('submit', function (event) {
            handleFormSubmit(event, 'Doctor application submitted for review.');
        });
    }
});
