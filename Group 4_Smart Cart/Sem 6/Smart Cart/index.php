<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Cart</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

    <!-- Google Tag Manager -->
    <script>
        (function(w,d,s,l,i){
            w[l]=w[l]||[];w[l].push({
                'gtm.start':
                    new Date().getTime(),event:'gtm.js'
            }); var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-KPF2H97H');
    </script>
    <!-- End Google Tag Manager -->
     
    <style>
        header {
            position: relative;
            background-color: rgba(76, 175, 80, 0.5);
            backdrop-filter: blur(4px);
            z-index: 100;
        }
        footer {
            background-color: rgba(76, 175, 80, 0.5);
            backdrop-filter: blur(4px);
        }
        /* Video background styling */
        #bg-video {
            position: fixed;
            top: 50%;
            left: 50%;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            transform: translate(-50%, -50%) scale(0.5);
            z-index: -2;
            object-fit: cover;
        }
    </style>
</head>

<body>
    <!-- Video background -->
    <video autoplay loop muted preload="auto" id="bg-video">
        <source src="Assets/Videos/Sales_Background.mp4" type="video/mp4">
        Your browser does not support the video tag.
    </video>

    <!-- Header -->
    <header>
        <div class="container">
            <h1><i class="fas fa-store"></i> Smart Cart</h1>
            <nav>
                <ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#team">Team</a></li>
                    <li><a href="#contact">Contact Us</a></li>
                    <li><a href="login.php">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="container">
            <h2>Maximize Sales with Data-Driven Insights</h2>
            <p>Predict future sales trends and offer personalized product recommendations to your customers.</p>
            <a href="login.php" class="btn">Predict Now</a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <div class="container">
            <h2 style="color: white;">About Our Service</h2>
            <p style="color: white;">Our platform leverages advanced machine learning algorithms to provide accurate sales predictions and personalized product recommendations. By analyzing past sales data, we help supermarkets make informed decisions that boost sales and customer satisfaction.</p>
            <div class="features">
                <div class="feature-box" data-tilt data-tilt-max="5">
                    <i class="fas fa-chart-line"></i>
                    <h3>Sales Prediction</h3>
                    <p>Accurately forecast future sales based on historical data and trends.</p>
                </div>
                <div class="feature-box" data-tilt data-tilt-max="5">
                    <i class="fas fa-thumbs-up"></i>
                    <h3>Product Recommendations</h3>
                    <p>Offer personalized product suggestions to your customers based on their buying habits.</p>
                </div>
                <div class="feature-box" data-tilt data-tilt-max="5">
                    <i class="fas fa-users"></i>
                    <h3>Customer Insights</h3>
                    <p>Understand your customers better and tailor your offerings to meet their needs.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section id="team" class="team">
        <div class="container">
            <h2 style="color: white;">Meet Our Team</h2>
            <p style="color: white;">Our team consists of dedicated professionals who are passionate about helping supermarkets optimize their sales and improve customer satisfaction.</p>
            <div class="team-members">
                <div class="team-member" data-tilt data-tilt-max="5">
                    <img src="Assets/Images/ved.jpg" alt="Team Member 1">
                    <h3>Ved Shirur</h3>
                    <p>Data Scientist - Specializes in machine learning and predictive analytics.</p>
                    <p> <i class="fa fa-envelope" aria-hidden="true"></i> 2022.ved.shirur@ves.ac.in</p>
                    <div class="socials">
                        <a href="https://www.linkedin.com/in/johndoe" target="_blank" title="LinkedIn"><i class="fab fa-linkedin"></i></a>
                        <a href="https://github.com/johndoe" target="_blank" title="GitHub"><i class="fab fa-github"></i></a>
                        <a href="https://facebook.com/johndoe" target="_blank" title="Facebook Profile"><i class="fab fa-facebook"></i></a>
                    </div>
                </div>
                <div class="team-member" data-tilt data-tilt-max="5">
                    <img src="Assets/Images/honey.jpg" alt="Team Member 2">
                    <h3>Honey Kundla</h3>
                    <p>Product Manager - Expert in product recommendations and user experience.</p>
                    <p> <i class="fa fa-envelope" aria-hidden="true"></i> d2022.honey.kundla@ves.ac.in</p>
                    <div class="socials">
                        <a href="https://www.linkedin.com/in/johndoe" target="_blank" title="LinkedIn"><i class="fab fa-linkedin"></i></a>
                        <a href="https://github.com/johndoe" target="_blank" title="GitHub"><i class="fab fa-github"></i></a>
                        <a href="https://facebook.com/johndoe" target="_blank" title="Facebook Profile"><i class="fab fa-facebook"></i></a>
                    </div>
                </div>
                <div class="team-member" data-tilt data-tilt-max="5">
                    <img src="Assets/Images/aditya.jpg" alt="Team Member 3">
                    <h3>Aditya Joshi</h3>
                    <p>Software Engineer - Focuses on system integration and performance optimization.</p>
                    <p> <i class="fa fa-envelope" aria-hidden="true"></i> 2022.aditya.joshi@ves.ac.in</p>
                    <div class="socials">
                        <a href="https://www.linkedin.com/in/johndoe" target="_blank" title="LinkedIn"><i class="fab fa-linkedin"></i></a>
                        <a href="https://github.com/johndoe" target="_blank" title="GitHub"><i class="fab fa-github"></i></a>
                        <a href="https://facebook.com/johndoe" target="_blank" title="Facebook Profile"><i class="fab fa-facebook"></i></a>
                    </div>
                </div>
                <div class="team-member" data-tilt data-tilt-max="5">
                    <img src="Assets/Images/chetan.jpg" alt="Team Member 3">
                    <h3>Chetan Narang</h3>
                    <p>Marketing Strategist - Boosting brand awareness through innovative campaigns.</p>
                    <p> <i class="fa fa-envelope" aria-hidden="true"></i> 2022.chetan.narang@ves.ac.in</p>
                    <div class="socials">
                        <a href="https://www.linkedin.com/in/johndoe" target="_blank" title="LinkedIn"><i class="fab fa-linkedin"></i></a>
                        <a href="https://github.com/johndoe" target="_blank" title="GitHub"><i class="fab fa-github"></i></a>
                        <a href="https://facebook.com/johndoe" target="_blank" title="Facebook Profile"><i class="fab fa-facebook"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="container">
            <h2>Contact Us</h2>
            <p>Have any questions or need help? Feel free to reach out to us!</p>
            <form id="contactForm" action="submit_contact.php" method="POST" data-tilt data-tilt-max="5">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" placeholder="Your Name" required>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" placeholder="Your Email" required>
                <label for="message">Message:</label>
                <textarea id="message" name="message" placeholder="Your Message" required style="resize: vertical; min-height: 40px;"></textarea>
                <button type="submit" class="btn">Send Message</button>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>&copy; 2024 - <?= date("Y") ?> Smart Cart. All rights reserved. Designed by AJ.</p>
        </div>
    </footer>

    <!-- Scroll to Top Icon -->
    <a href="#" id="scrollToTopBtn"><i class="fas fa-chevron-up"></i></a>

    <script src="vanilla-tilt.js"></script>
    <script src="scripts.js"></script>
    
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KPF2H97H"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) --> 

</body>

</html>
