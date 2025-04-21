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

// Check if user is logged in
if (!isset($_SESSION['Email']) && !isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['salesFile'])) {
    // File upload logic
    $file = $_FILES['salesFile'];
    $filename = $file['tmp_name'];

    // Use cURL to send the file to your Python Flask API
    $ch = curl_init();
    $cfile = new CURLFile($filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'salesFile');

    $data = array('file' => $cfile);

    // Set the URL to your Flask API endpoint
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:5000/clusterwise_correlation');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    } else {
        $response_data = json_decode($response, true);
    }
    curl_close($ch);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cluster-wise Correlation and Regression</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        /* Include your CSS here (see sample CSS from your provided page) */
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
            background-color: #f9f9f9;
            font-family: 'Roboto', sans-serif;
        }
        .content {
            flex-grow: 1;
        }
        nav ul {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 20px;
            position: relative;
            bottom: 0;
        }
        form.salesform {
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #f8f9fa;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        form.salesform label {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .salesform input[type="file"] {
            font-size: 1rem;
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            width: 100%;
            margin-bottom: 20px;
            transition: border-color 0.3s ease-in-out;
        }
        #loading-screen {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            text-align: center;
            font-size: 24px;
            padding-top: 20%;
        }
        .spinner {
            border: 16px solid #f3f3f3;
            border-radius: 50%;
            border-top: 16px solid #3498db;
            width: 80px;
            height: 80px;
            animation: spin 2s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        #predictionResult-container {
            background-color: #fff;
            padding: 20px;
            margin: 30px auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 1300px;
            text-align: center;
        }
        #predictionResult-container h3 {
            font-size: 20px;
            color: #34495e;
            margin-bottom: 10px;
            margin-top: 20px;
        }
        #predictionResult-container img {
            width: 100%;
            max-width: 1000px;
            height: auto;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        #clusterDetails {
            background-color: #fff;
            padding: 20px;
            margin: 30px auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 1000px;
            text-align: left;
        }
        #clusterDetails h2 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }
        #clusterDetails p {
            font-size: 18px;
            color: #333;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        .product-click {
            color: #000000;
            text-decoration: none;
        }
        .product-click:hover {
            text-decoration: underline;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }
        table th {
            background: #28a745;
            color: white;
            font-weight: bold;
        }
        table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <header id="top">
        <div class="container">
            <h1><i class="fas fa-store"></i> Smart Cart</h1>
            <nav>
                <ul>
                    <li><a href="homepage.php">Home</a></li>
                    <li><a href="#predictionResult">Analysis</a></li>
                    <li><a href="#recommendations">Recommendations</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section id="prediction" class="content">
        <div class="container">
            <h2 style="color: #333333">Cluster-wise Correlation and Linear Regression</h2>
            <!-- Loading Screen -->
            <div id="loading-screen">
                <div class="spinner"></div>
                <p>Processing your document, please wait...</p>
            </div>
            <form method="POST" enctype="multipart/form-data" id="uploadForm" class="salesform">
                <label for="salesFile">Upload Sales Data (Excel):</label>
                <input type="file" id="salesFile" name="salesFile" accept=".xlsx" required>
                <button type="submit" class="btn">Analyze</button>
            </form>
            <br>
            <div id="predictionResult" class="result">
                <?php if (isset($response_data)) { ?>
                    <h2 style="color: #333333">Sales vs. Profit Correlation Analysis</h2>
                    <div id="predictionResult-container">
                        <!-- Scatter Plot -->
                        <h3>Scatter Plot (Clusters of Sales vs. Profit):</h3>
                        <img src="<?= htmlspecialchars($response_data['scatter_plot']) ?>" alt="Cluster Scatter Plot">

                        <!-- For each cluster: Heatmap, Regression Plot, and Cluster Analysis -->
                        <?php if (isset($response_data['heatmaps']) && is_array($response_data['heatmaps'])) { ?>
                            <?php foreach ($response_data['heatmaps'] as $index => $heatmap) { ?>
                                <?php
                                    $clusterLabels = [
                                        'Most Profitable',
                                        '2nd Most Profitable',
                                        '3rd Most Profitable',
                                        'Least Profitable'
                                    ];
                                ?>
                                <div class="cluster-block">
                                    <h2 style="color: #333333">Cluster <?= $index + 1 ?> (<?= htmlspecialchars($clusterLabels[$index]) ?>) </h2>
                                    <h4 style="color: black">Correlation Heatmap</h4>
                                    <img src="<?= htmlspecialchars($heatmap) ?>" alt="Heatmap for Cluster <?= $index + 1 ?>">

                                    <?php if (isset($response_data['regression_plots'][$index])) { ?>
                                        <h4 style="color: black">Linear Regression Plot</h4>
                                        <img src="<?= htmlspecialchars($response_data['regression_plots'][$index]) ?>" alt="Regression Plot for Cluster <?= $index + 1 ?>">
                                    <?php } ?>
                                    
                                    <?php if (isset($response_data['cluster_details'][$index])) {
                                        $detail = $response_data['cluster_details'][$index]; ?>
                                        <div class="cluster-analysis">
                                            <h4 id="recommendations" style="color: #2c3e50">Cluster <?= $index + 1 ?> Analysis Details</h4>
                                            <table style="width:100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr>
                                                        <th>Metric</th>
                                                        <th>Sub-Category</th>
                                                        <th>Total Profit/Sales (₹)</th>
                                                        <th>Representative Product</th>
                                                        <th>Product Profit/Sales (₹)</th>
                                                        <th>Location</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Most Profitable Category -->
                                                    <tr style="color: black;">
                                                        <td>Most Profitable Category</td>
                                                        <td>
                                                            <?= htmlspecialchars($detail['most_profitable_category']['Sub-Category']) ?>
                                                        </td>
                                                        <td>
                                                            <?= number_format($detail['most_profitable_category']['Profit'], 2) ?>
                                                        </td>
                                                        <td>
                                                            <a class="product-click" href="product_details.php?product_id=<?= urlencode($detail['rep_most_profitable_product']['Product ID']) ?>">
                                                                <?= htmlspecialchars($detail['rep_most_profitable_product']['Product Name']) ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <?= number_format($detail['rep_most_profitable_product']['Profit'], 2) ?>
                                                        </td>
                                                        <td>
                                                            <?= htmlspecialchars($detail['rep_most_profitable_product']['City']) ?>, 
                                                            <?= htmlspecialchars($detail['rep_most_profitable_product']['State']) ?>
                                                        </td>
                                                    </tr>
                                                    <!-- Least Profitable Category -->
                                                    <tr style="color: black;">
                                                        <td>Least Profitable Category</td>
                                                        <td>
                                                            <?= htmlspecialchars($detail['least_profitable_category']['Sub-Category']) ?>
                                                        </td>
                                                        <td>
                                                            <?= number_format($detail['least_profitable_category']['Profit'], 2) ?>
                                                        </td>
                                                        <td>
                                                            <a class="product-click" href="product_details.php?product_id=<?= urlencode($detail['rep_least_profitable_product']['Product ID']) ?>">
                                                                <?= htmlspecialchars($detail['rep_least_profitable_product']['Product Name']) ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <?= number_format($detail['rep_least_profitable_product']['Profit'], 2) ?>
                                                        </td>
                                                        <td>
                                                            <?= htmlspecialchars($detail['rep_least_profitable_product']['City']) ?>, 
                                                            <?= htmlspecialchars($detail['rep_least_profitable_product']['State']) ?>
                                                        </td>
                                                    </tr>
                                                    <!-- Most Sold Category -->
                                                    <tr style="color: black;">
                                                        <td>Most Sold Category</td>
                                                        <td>
                                                            <?= htmlspecialchars($detail['most_sold_category']['Sub-Category']) ?>
                                                        </td>
                                                        <td>
                                                            <?= number_format($detail['most_sold_category']['Sales'], 2) ?>
                                                        </td>
                                                        <td>
                                                            <a class="product-click" href="product_details.php?product_id=<?= urlencode($detail['rep_most_sold_product']['Product ID']) ?>">
                                                                <?= htmlspecialchars($detail['rep_most_sold_product']['Product Name']) ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <?= number_format($detail['rep_most_sold_product']['Sales'], 2) ?>
                                                        </td>
                                                        <td>
                                                            <?= htmlspecialchars($detail['rep_most_sold_product']['City']) ?>, 
                                                            <?= htmlspecialchars($detail['rep_most_sold_product']['State']) ?>
                                                        </td>
                                                    </tr>
                                                    <!-- Least Sold Category -->
                                                    <tr style="color: black;">
                                                        <td>Least Sold Category</td>
                                                        <td>
                                                            <?= htmlspecialchars($detail['least_sold_category']['Sub-Category']) ?>
                                                        </td>
                                                        <td>
                                                            <?= number_format($detail['least_sold_category']['Sales'], 2) ?>
                                                        </td>
                                                        <td>
                                                            <a class="product-click" href="product_details.php?product_id=<?= urlencode($detail['rep_least_sold_product']['Product ID']) ?>">
                                                                <?= htmlspecialchars($detail['rep_least_sold_product']['Product Name']) ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <?= number_format($detail['rep_least_sold_product']['Sales'], 2) ?>
                                                        </td>
                                                        <td>
                                                            <?= htmlspecialchars($detail['rep_least_sold_product']['City']) ?>, 
                                                            <?= htmlspecialchars($detail['rep_least_sold_product']['State']) ?>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php } ?>

                                </div>
                                <hr>
                            <?php } ?>
                        <?php } ?>

                        <!-- Geographic Maps -->
                        <h3>Geographic Distribution of Products according to the Profit for each Cluster:</h3>
                        <iframe src="<?= htmlspecialchars($response_data['profit_map']) ?>" width="100%" height="500" frameborder="0"></iframe>

                        <h3>Geographic Distribution of Products according to the Sales for each Cluster:</h3>
                        <iframe src="<?= htmlspecialchars($response_data['sales_map']) ?>" width="100%" height="500" frameborder="0"></iframe>
                    </div>
                <?php } ?>
            </div>
            <br>
        </div>
    </section>


    <footer>
        <div class="container">
            <p>&copy; 2024 - <?= date("Y") ?> Smart Cart. All rights reserved. Designed by AJ.</p>
        </div>
    </footer>

    <!-- Scroll to Top Icon -->
    <button id="scrollToTopBtn" title="Go to top"><i class="fas fa-chevron-up"></i></button>

    <script>
        // Scroll to top button functionality
        var scrollToTopBtn = document.getElementById("scrollToTopBtn");

        // When the user scrolls down 20px from the top, show the button
        window.onscroll = function() {
            if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
                scrollToTopBtn.style.display = "block";
            } else {
                scrollToTopBtn.style.display = "none";
            }
        };

        // When the button is clicked, scroll to the top of the document smoothly
        scrollToTopBtn.addEventListener("click", function() {
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    </script>
    <script>
        // Show loading screen when form is submitted
        document.getElementById("uploadForm").onsubmit = function() {
            document.getElementById("loading-screen").style.display = "block";
        };
    </script>
</body>
</html>
