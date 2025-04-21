<?php
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

include("connect.php");

// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

// Check if user is logged in either through regular login or Google Sign-In
if (!isset($_SESSION['Email']) && !isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Models Page</title>
  <link rel="stylesheet" href="styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <style>
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
      z-index: -1;
      object-fit: cover;
    }
    body {
      font-family: 'Roboto', sans-serif;
      height: 100%;
      margin: 0;
      display: flex;
      flex-direction: column;
      background-color: #f9f9f9;
    }
    header {
      background-color: rgba(76, 175, 80, 0.5);
      backdrop-filter: blur(4px);
    }
    footer {
      background-color: #333;
      color: white;
      text-align: center;
      padding: 20px;
      background-color: rgba(76, 175, 80, 0.5);
      backdrop-filter: blur(4px);
    }
    nav ul {
      list-style: none;
      display: flex;
      align-items: center;
      gap: 30px;
    }
    h1, h2 {
      text-align: center;
      color: white;
    }
    .model-title {
      font-size: 40px;
      color: #0FFF50;
    }
    .model-info {
      font-size: 20px;
      color: white;
      text-align: center;
      padding-left: 230px;
      padding-right: 230px;
    }
    .section {
      margin-bottom: 40px;
    }
    .page-title {
      display: flex;
      justify-content: center;
      align-items: center;
      flex-direction: column;
      background-color: rgba(51, 51, 51, 0.5);
      backdrop-filter: blur(4px);
    }
    .page-title h1 {
      font-size: 60px;
      margin-top: 40px;
      margin-bottom: 20px;
      padding: 10px;
      color: #63cf73;
    }
    .page-title p {
      font-size: 20px;
      color: white;
      text-align: center;
      padding-left: 50px;
      padding-right: 50px;
      margin-top: 0;
      margin-bottom: 50px;
    }
    .cards-container {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 20px;
    }
    .card {
      background-color: #fff;
      border: none;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      width: 500px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      background-color: rgba(255, 255, 255, 0.5);
      backdrop-filter: blur(4px);
    }
    /* Media container to hold both thumbnail and animated gif */
    .media {
      position: relative;
      width: 100%;
      height: 280px;
      overflow: hidden;
      cursor: pointer;
    }
    .media img.thumb {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: opacity 0.3s ease;
      z-index: 1;
    }
    .media video.anim {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: opacity 0.3s ease;
      opacity: 0;
      z-index: 2;
    }
    .media:hover img.thumb {
      opacity: 0;
    }
    .media:hover video.anim {
      opacity: 1;
    }
    .card-content {
      padding: 15px;
      flex-grow: 1;
    }
    .card-content h3 {
      margin: 0 0 10px;
      font-size: 18px;
      color: #333333;
    }
    .card-content p {
      margin: 10px 0;
      font-size: 14px;
      color: #333333;
    }
    .card button {
      background-color: #0FFF50;
      color: #fff;
      border: none;
      padding: 10px 15px;
      border-radius: 4px;
      cursor: pointer;
      margin: 15px;
      transition: background-color 0.3s ease;
    }
    .card button:hover {
      background-color: #4CAF50;
    }
    /* Modal overlay styles */
    #modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.85);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 1000;
    }
    #modal-overlay video {
      max-width: 90%;
      max-height: 90%;
      object-fit: contain;
    }
    #modal-video {
      width: 960px;
      height: auto;
    }
  </style>
</head>
<body>
  <header id="top">
    <div class="container">
      <h1 style="color: white;"><i class="fas fa-store"></i> Smart Cart</h1>
      <nav>
        <ul>
          <li><a href="product_details.php" class="<?= ($current_page == 'product_details.php') ? 'active' : '' ?>">Search Product</a></li>
          <li><a href="#" class="<?= ($current_page == 'models.php') ? 'active' : '' ?>">Our Models</a></li>
          <li><a href="homepage.php" class="<?= ($current_page == 'homepage.php') ? 'active' : '' ?>">Smart Cart</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Video background -->
  <video autoplay loop muted preload="auto" id="bg-video">
    <source src="Assets/Videos/Sales_Background.mp4" type="video/mp4">
    Your browser does not support the video tag.
  </video>

  <!-- Modal overlay for expanded video -->
  <div id="modal-overlay">
    <video id="modal-video" autoplay loop>
      <source src="" type="video/mp4">
      Your browser does not support the video tag.
    </video>
  </div>

  <!-- Clustering Models Section -->
  <div class="section">
    <div class="page-title">
      <h1>Our Models</h1>
      <p>
        At Smart Cart, our advanced models turn data into actionable insights. Our Cluster Models reveal hidden patterns with K-Means and Hierarchical clustering, Regression Models forecast trends with statistical precision, and Prophet Models predict future sales using state-of-the-art forecasting techniques.
      </p>
    </div>
    <h2 class="model-title">Clustering Models</h2>
    <p class="model-info">Our Clustering Models, including K-Means and Hierarchical Clustering, group data to reveal hidden patterns and insights, helping you understand    complex data relationships.</p>
    <div class="cards-container">
      <!-- K-Means Clustering Model Card -->
      <div class="card">
        <div class="media" data-video-src="Assets/Videos/KMeans.mp4">
          <img class="thumb" src="static/clusterwise_correlation/cluster_analysis.png" alt="K-Means Clustering Thumbnail">
          <video class="anim" muted loop playsinline preload="auto">
            <source src="Assets/Videos/KMeans.mp4" type="video/mp4">
          </video>
        </div>
        <div class="card-content">
          <h3>K-Means Clustering Model</h3>
          <p>This model uses the K-Means algorithm to segment data into clusters, revealing underlying patterns.</p>
          <button onclick="window.location.href='prediction.php'">View Model</button>
        </div>
      </div>
      <!-- Hierarchical Clustering Model Card -->
      <div class="card">
        <div class="media" data-video-src="Assets/Videos/Hierarchical.mp4">
          <img class="thumb" src="static/dendrogram_plot.png" alt="Hierarchical Clustering Thumbnail">
          <video class="anim" muted loop playsinline preload="auto">
            <source src="Assets/Videos/Hierarchical.mp4" type="video/mp4">
          </video>
        </div>
        <div class="card-content">
          <h3>Hierarchical Clustering Model</h3>
          <p>This model applies hierarchical clustering, building a tree-like structure (dendrogram) of the data relationships.</p>
          <button onclick="window.location.href='prediction2.php'">View Model</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Linear Regression Models Section -->
  <div class="section">
    <h2 class="model-title">Linear Regression Models</h2>
    <p class="model-info">Our Linear Regression Models use statistical techniques to forecast trends and measure key relationships, offering precise predictive insights from historical data.</p>
    <div class="cards-container">
      <!-- Clustering-wise Correlation and Linear Regression Model Card -->
      <div class="card">
        <div class="media" data-video-src="Assets/Videos/Cluster_wise_Correlation.mp4">
          <img class="thumb" src="static/clusterwise_correlation/correlation_heatmap_cluster_1.png" alt="Clustering-wise Correlation Thumbnail">
          <video class="anim" muted loop playsinline preload="auto">
            <source src="Assets/Videos/Cluster_wise_Correlation.mp4" type="video/mp4">
          </video>
        </div>
        <div class="card-content">
          <h3>Clustering-wise Correlation and Linear Regression</h3>
          <p>This model examines correlations within clusters and applies linear regression for predictive insights.</p>
          <button onclick="window.location.href='clusterwise_correlation.php'">View Model</button>
        </div>
      </div>
      <!-- Forecasting Sales & Profit using Linear Regression Model Card -->
      <div class="card">
        <div class="media" data-video-src="Assets/Videos/Linear_Regression.mp4">
          <img class="thumb" src="Assets/Images/Linear_Regression.png" alt="Linear Regression Thumbnail">
          <video class="anim" muted loop playsinline preload="auto">
            <source src="Assets/Videos/Linear_Regression.mp4" type="video/mp4">
          </video>
        </div>
        <div class="card-content">
          <h3>Forecasting Sales & Profit using Linear Regression</h3>
          <p>This model forecasts sales and profit trends using linear regression based on historical data.</p>
          <button onclick="window.location.href='linear_regression_forecast.php'">View Model</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Prophet Models Section -->
  <div class="section">
    <h2 class="model-title">Prophet Models</h2>
    <p class="model-info">Leveraging Facebook's Prophet, our Prophet Models deliver reliable forecasts for future sales and market trends, empowering effective strategic planning.</p>
    <div class="cards-container">
      <!-- Forecasting Sales & Profit using PROPHET Model Card -->
      <div class="card">
        <div class="media" data-video-src="Assets/Videos/PROPHET.mp4">
          <img class="thumb" src="Assets/Images/PROPHET_Thumbnail.png" alt="Prophet Thumbnail">
          <video class="anim" muted loop playsinline preload="auto">
            <source src="Assets/Videos/PROPHET.mp4" type="video/mp4">
          </video>
        </div>
        <div class="card-content">
          <h3>Forecasting Sales using PROPHET</h3>
          <p>This model leverages Facebook's Prophet to generate accurate sales forecasts using historical data patterns.</p>
          <button onclick="window.location.href='prophet_forecast.php'">View Model</button>
        </div>
      </div>
      <!-- Forecasting Sales & Profit Region-wise using PROPHET Model Card -->
      <div class="card">
        <div class="media" data-video-src="Assets/Videos/PROPHET_Region.mp4">
          <img class="thumb" src="Assets/Images/PROPHET_Region_Thumbnail.png" alt="Prophet Region Thumbnail">
          <video class="anim" muted loop playsinline preload="auto">
            <source src="Assets/Videos/PROPHET_Region.mp4" type="video/mp4">
          </video>
        </div>
        <div class="card-content">
          <h3>Forecasting Sales & Profit Region-wise using PROPHET</h3>
          <p>This model applies Prophet to forecast sales and profit by region, allowing for localized insights.</p>
          <button onclick="window.location.href='prophet_forecast_with_region.php'">View Model</button>
        </div>
      </div>
    </div>
  </div>

  <footer>
      <div class="container">
          <p>&copy; 2024 - <?= date("Y") ?> Smart Cart. All rights reserved. Designed by AJ.</p>
      </div>
  </footer>

  <!-- Scroll to Top Icon -->
  <button id="scrollToTopBtn" title="Go to top"><i class="fas fa-chevron-up"></i></button>

  <!-- JavaScript to reset GIF animation on hover -->
  <script>
    document.querySelectorAll('.media').forEach(function(media) {
      // Optionally reset video preview on hover (if needed)
      media.addEventListener('mouseenter', function() {
        var previewVideo = media.querySelector('video.anim');
        if(previewVideo) {
          // Restart the preview video
          previewVideo.currentTime = 0;
          previewVideo.play();
        }
      });
      // When the media container is clicked, open modal with video
      media.addEventListener('click', function() {
        var videoSrc = media.getAttribute('data-video-src');
        if(videoSrc) {
          var modalVideo = document.getElementById('modal-video');
          modalVideo.querySelector('source').src = videoSrc;
          modalVideo.load(); // reload video so it starts from beginning
          document.getElementById('modal-overlay').style.display = 'flex';
        }
      });
    });
    
    // Function to close the modal
    function closeModal() {
      var modalOverlay = document.getElementById('modal-overlay');
      var modalVideo = document.getElementById('modal-video');
      modalVideo.pause();
      modalOverlay.style.display = 'none';
    }

    // Close modal when clicking anywhere outside the video
    document.getElementById('modal-overlay').addEventListener('click', function(e) {
      if (e.target === this) { // ensure click is on the overlay, not on the video itself
        closeModal();
      }
    });

    // Prevent clicks on the video from propagating to the overlay
    document.getElementById('modal-video').addEventListener('click', function(e) {
      e.stopPropagation();
    });

    // Close modal when user scrolls
    window.addEventListener('scroll', function() {
      var modalOverlay = document.getElementById('modal-overlay');
      if (modalOverlay.style.display === 'flex') {
        closeModal();
      }
    });
  </script>
  <script src="scripts.js"></script>
</body>
</html>