document.addEventListener('DOMContentLoaded', () => {
    /* ==============================
       Login / Sign Up Functionality
       ============================== */
    const loginForm = document.getElementById('loginForm');
    const signupForm = document.getElementById('signupForm');
    const loginBtn = document.getElementById('loginBtn');
    const signupBtn = document.getElementById('signupBtn');
    const switchToSignup = document.getElementById('switchToSignup');
    const switchToLogin = document.getElementById('switchToLogin');

    if (loginForm && signupForm && loginBtn && signupBtn && switchToSignup && switchToLogin) {
        loginBtn.addEventListener('click', () => {
            loginForm.classList.add('active');
            signupForm.classList.remove('active');
            loginBtn.classList.add('active');
            signupBtn.classList.remove('active');
        });

        signupBtn.addEventListener('click', () => {
            signupForm.classList.add('active');
            loginForm.classList.remove('active');
            signupBtn.classList.add('active');
            loginBtn.classList.remove('active');
        });

        switchToSignup.addEventListener('click', (e) => {
            e.preventDefault();
            signupForm.classList.add('active');
            loginForm.classList.remove('active');
            signupBtn.classList.add('active');
            loginBtn.classList.remove('active');
        });

        switchToLogin.addEventListener('click', (e) => {
            e.preventDefault();
            loginForm.classList.add('active');
            signupForm.classList.remove('active');
            loginBtn.classList.add('active');
            signupBtn.classList.remove('active');
        });
    }

    const toggleLoginPassword = document.getElementById('toggleLoginPassword');
    const toggleSignupPassword = document.getElementById('toggleSignupPassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');

    function togglePasswordVisibility(passwordField, toggleIcon) {
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordField.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    if (toggleLoginPassword && document.getElementById('loginPassword')) {
        toggleLoginPassword.addEventListener('click', () => {
            const passwordField = document.getElementById('loginPassword');
            togglePasswordVisibility(passwordField, toggleLoginPassword);
        });
    }

    if (toggleSignupPassword && document.getElementById('signupPassword')) {
        toggleSignupPassword.addEventListener('click', () => {
            const passwordField = document.getElementById('signupPassword');
            togglePasswordVisibility(passwordField, toggleSignupPassword);
        });
    }

    if (toggleConfirmPassword && document.getElementById('signupConfirmPassword')) {
        toggleConfirmPassword.addEventListener('click', () => {
            const passwordField = document.getElementById('signupConfirmPassword');
            togglePasswordVisibility(passwordField, toggleConfirmPassword);
        });
    }

    /* ==============================
       Contact Form Submission
       ============================== */
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function (event) {
            event.preventDefault(); // Prevent the default form submission

            // Collect form data
            const formData = new FormData(this);

            // Send an AJAX request
            fetch('submit_contact.php', {
                method: 'POST',
                body: formData,
            })
                .then((response) => response.text())
                .then((data) => {
                    if (data === 'success') {
                        alert('Thank you for your message! We will get back to you soon.');
                        contactForm.reset(); // Reset the form
                    } else {
                        alert('There was an error submitting your message: ' + data);
                    }
                })
                .catch((error) => {
                    alert('An error occurred: ' + error.message);
                });
        });
    }

    /* ==============================
       Scroll to Top Functionality
       ============================== */
    const scrollToTopBtn = document.getElementById("scrollToTopBtn");
    if (scrollToTopBtn) {
        function toggleScrollToTopBtn() {
            if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
                scrollToTopBtn.style.display = "block";
            } else {
                scrollToTopBtn.style.display = "none";
            }
        }

        window.addEventListener('scroll', toggleScrollToTopBtn);

        scrollToTopBtn.addEventListener('click', function (event) {
            event.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: "smooth",
            });
        });
    }

    /* ==============================
       Predict Now Modal Functionality
       ============================== */
    const modal = document.getElementById("predictionModal");
    const predictBtn = document.getElementById("predictBtn");
    const closeModalSpan = document.getElementsByClassName("close")[0];

    if (modal && predictBtn && closeModalSpan) {
        function closeModal() {
            modal.style.display = "none";
            window.removeEventListener('scroll', onScrollCloseModal);
        }

        predictBtn.onclick = function () {
            modal.style.display = "block";
            window.addEventListener('scroll', onScrollCloseModal);
        };

        closeModalSpan.onclick = function () {
            closeModal();
        };

        window.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        function onScrollCloseModal() {
            closeModal();
        }
    }

    /* ==============================
       VanillaTilt Pause on Input Focus
       ============================== */
    if (contactForm) {
        // Initialize VanillaTilt on the contact form with your desired options
        VanillaTilt.init(contactForm, {
            max: 5,        // maximum tilt angle
            reverse: false // change if you want to invert the tilt
        });

        // Get all input and textarea elements within the form
        const formElements = contactForm.querySelectorAll("input, textarea");

        formElements.forEach(el => {
            el.addEventListener("focus", () => {
                // On focus, destroy the tilt effect so it stops tilting
                if (contactForm.vanillaTilt) {
                    contactForm.vanillaTilt.destroy();
                }
            });

            el.addEventListener("blur", () => {
                // Use a timeout to wait for focus to settle
                setTimeout(() => {
                    // If no element within the form is focused, reinitialize the tilt
                    if (!contactForm.contains(document.activeElement)) {
                        VanillaTilt.init(contactForm, {
                            max: 5,
                            reverse: false
                        });
                    }
                }, 50);
            });
        });
    }

    /* ==============================
       Dropdown Menu Functionality
       ============================== */
    const dropdown = document.querySelector('.dropdown');
    const dropdownBtn = document.querySelector('.dropbtn');
    const dropdownContent = document.querySelector('.dropdown-content');

    if (dropdownBtn && dropdownContent) {
        // Toggle dropdown on click of the button using a class
        dropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownContent.classList.toggle('open');
        });

        // Close dropdown when clicking outside of the entire dropdown element
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                dropdownContent.classList.remove('open');
            }
        });

        // Close dropdown when clicking on any link inside it (if desired)
        const dropdownLinks = dropdownContent.querySelectorAll('a');
        dropdownLinks.forEach(link => {
            link.addEventListener('click', function() {
                dropdownContent.classList.remove('open');
            });
        });
    }
});
