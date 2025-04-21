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

// ----------------------------------------------------------------------
// Fetch distinct products, sub-categories, and categories from the database
// ----------------------------------------------------------------------
$all_products = array();
$all_subcategories = array();
$all_categories = array();
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "superstore";

$conn = new mysqli($servername, $username, $password, $dbname);
if (!$conn->connect_error) {
    // Fetch distinct products
    $query = "SELECT DISTINCT `Product Name` FROM products ORDER BY `Product Name` ASC";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $all_products[] = $row;
        }
    }
    // Fetch distinct sub-categories
    $query = "SELECT DISTINCT `Sub-Category` FROM products ORDER BY `Sub-Category` ASC";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $all_subcategories[] = $row;
        }
    }
    // Fetch distinct categories
    $query = "SELECT DISTINCT `Category` FROM products ORDER BY `Category` ASC";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $all_categories[] = $row;
        }
    }
    $conn->close();
}

// Initialize variables for API response.
$response_data = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['forecastFile'])) {
    $file = $_FILES['forecastFile'];
    $filename = $file['tmp_name'];
    
    // Get form fields for prediction type and value.
    $prediction_type = isset($_POST['prediction_type']) ? trim($_POST['prediction_type']) : '';
    $user_value = isset($_POST['user_value']) ? trim($_POST['user_value']) : '';
    
    if(empty($prediction_type) || empty($user_value)){
        $error = "Both prediction type and its value must be provided.";
    } else {
        // Use cURL to send the file and additional data to the Flask API.
        $ch = curl_init();
        $cfile = new CURLFile($filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $file['name']);
        $postData = array(
            'file' => $cfile,
            'prediction_type' => $prediction_type,
            'user_value' => $user_value
        );
        // Adjust the URL as needed (here Flask is assumed to run locally).
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:5000/sarimax_forecast');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = 'Error: ' . curl_error($ch);
        } else {
            $response_data = json_decode($response, true);
        }
        curl_close($ch);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SARIMAX Forecasting</title>
    <link rel="stylesheet" href="styles.css">
    <!-- Google Fonts and FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        /* Basic CSS styling */
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
            background-color: #f9f9f9;
            font-family: 'Roboto', sans-serif;
        }
        footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 20px;
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
        form.forecastform {
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #f8f9fa;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
            margin-bottom: 40px;
        }
        form.forecastform label {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: bold;
        }
        form.forecastform input[type="file"],
        form.forecastform select,
        form.forecastform input[type="text"] {
            font-size: 1rem;
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            width: 100%;
            margin-bottom: 20px;
            transition: border-color 0.3s ease-in-out;
        }
        form.forecastform button {
            padding: 10px 20px;
            color: #fff;
            background-color: #28a745;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        /* Loading Screen */
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
        /* Result container styling */
        #result-container {
            background-color: #fff;
            padding: 20px;
            margin: 30px auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 1300px;
            text-align: center;
        }
        #result-container h3 {
            font-size: 20px;
            color: #34495e;
            margin-bottom: 10px;
            margin-top: 20px;
        }
        #result-container img {
            width: 100%;
            max-width: 1000px;
            height: auto;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        table {
            width: 80%;
            margin: 0 auto 20px auto;
            border-collapse: collapse;
        }
        table th, table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: center;
        }
        table th {
            background-color: #28a745;
            color: #fff;
            font-weight: bold;
        }
        table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        /* Suggestion box styling for each prediction type */
        #value_input_container {
            display: none;
            width: 100%;
            text-align: center;
            transform: translateX(-11px);
        }
        #value_input_container label {
            display: block;
            margin-bottom: 10px;
            transform: translateX(11px);
        }
        #value_input_container input[type="text"] {
            margin-bottom: 20px;
        }
        .suggestion-container {
            display: none;
            max-height: 200px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            padding: 10px;
            text-align: left;
            width: 100%;
        }
        .suggestion-container ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .suggestion-container li {
            padding: 5px;
            cursor: pointer;
        }
        .suggestion-container li:hover {
            background-color: #f0f0f0;
        }
        .no-match {
            display: none;
            color: #000;
            text-align: center;
            padding: 5px;
        }
        /* Toggle button styling */
        .toggle-btn {
            display: block;
            margin: 20px auto;
            padding: 8px 16px;
            background-color: #28a745;
            border: none;
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
            font-size: 16px;
            text-align: center;
        }
        /* Animate the container for future-only graphs */
        #salesForecastOnlyContainer,
        #profitForecastOnlyContainer {
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: max-height 0.5s ease-out, opacity 0.5s ease-out;
        }
        /* When the container is open, expand it */
        #salesForecastOnlyContainer.open,
        #profitForecastOnlyContainer.open {
            max-height: 1000px; /* A large enough value to show the content */
            opacity: 1;
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
                    <li><a href="#prediction-sales">Prediction</a></li>
                    <li><a href="#forecast-result">Forecast</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <section class="content">
        <div class="container">
            <h2>Forecasting Sales & Profit using SARIMAX Model</h2>
            <!-- Loading Screen -->
            <div id="loading-screen">
                <div class="spinner"></div>
                <p>Processing your file, please wait...</p>
            </div>
            <form method="POST" enctype="multipart/form-data" id="uploadForm" class="forecastform">
                <label for="forecastFile">Upload Sales Data (Excel):</label>
                <input type="file" id="forecastFile" name="forecastFile" accept=".xlsx" required>
                
                <label for="prediction_type">Prediction For:</label>
                <select id="prediction_type" name="prediction_type" required>
                    <option value="">Select Prediction For</option>
                    <option value="category">Category</option>
                    <option value="sub-category">Sub-Category</option>
                    <option value="product">Product</option>
                </select>
                
                <!-- This container is initially hidden; it shows when a prediction type is selected -->
                <div id="value_input_container">
                    <label for="user_value">Enter Value:</label>
                    <input type="text" id="user_value" name="user_value" placeholder="Enter value">
                    
                    <!-- Suggestion container for Product -->
                    <div id="product-container" class="suggestion-container">
                        <ul id="product-list">
                            <?php foreach ($all_products as $prod) { ?>
                                <li data-product="<?= htmlspecialchars($prod['Product Name']) ?>">
                                    <?= htmlspecialchars($prod['Product Name']) ?>
                                </li>
                            <?php } ?>
                        </ul>
                        <div id="no-match-product" class="no-match">No product found</div>
                    </div>
                    
                    <!-- Suggestion container for Sub-Category -->
                    <div id="subcategory-container" class="suggestion-container">
                        <ul id="subcategory-list">
                            <?php foreach ($all_subcategories as $subcat) { ?>
                                <li data-subcategory="<?= htmlspecialchars($subcat['Sub-Category']) ?>">
                                    <?= htmlspecialchars($subcat['Sub-Category']) ?>
                                </li>
                            <?php } ?>
                        </ul>
                        <div id="no-match-subcategory" class="no-match">No sub-category found</div>
                    </div>
                    
                    <!-- Suggestion container for Category -->
                    <div id="category-container" class="suggestion-container">
                        <ul id="category-list">
                            <?php foreach ($all_categories as $cat) { ?>
                                <li data-category="<?= htmlspecialchars($cat['Category']) ?>">
                                    <?= htmlspecialchars($cat['Category']) ?>
                                </li>
                            <?php } ?>
                        </ul>
                        <div id="no-match-category" class="no-match">No category found</div>
                    </div>
                </div>
                
                <button type="submit" class="btn">Analyze</button>
            </form>
            <br>
            <?php if (!empty($response_data) && empty($error)) { ?>
                <h2 id="forecast-result">Forecast Results for <?= htmlspecialchars($_POST['user_value']) ?></h2>
            <?php } ?>
            <div id="result-container" style="<?php echo (empty($response_data) && empty($error)) ? 'display:none;' : 'display:block;'; ?>">
                <?php if (!empty($error)) { ?>
                    <h2>Error:</h2>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php } elseif (!empty($response_data)) { ?>                    
                    <h2>Sales Forecast</h2>
                    <img src="<?= htmlspecialchars($response_data['sales']['forecast_image']) ?>" alt="Sales Forecast">

                    <!-- Toggle button for Sales Future Only Graph -->
                    <button class="toggle-btn" id="toggleSalesForecastButton" onclick="toggleSalesForecastOnly()">Expand Prediction Graph</button>
                    <div id="salesForecastOnlyContainer">
                        <img src="<?= htmlspecialchars($response_data['sales']['forecast_only_image']) ?>" alt="Sales Forecast (Future Only)">
                    </div>
                    
                    <h3 style="margin-top: 20px;">Historical Sales Data</h3>
                    <?php if (isset($response_data['sales']['historical']) && count($response_data['sales']['historical']) > 0) { ?>
                        <table>
                            <tr>
                                <th>Date</th>
                                <th>Sales (₹)</th>
                            </tr>
                            <?php foreach($response_data['sales']['historical'] as $row) { ?>
                                <tr>
                                    <td><?= htmlspecialchars(date("Y-m-d", strtotime($row['Order Date']))) ?></td>
                                    <td><?= number_format($row['Sales'], 2) ?></td>
                                </tr>
                            <?php } ?>
                        </table>
                    <?php } else { ?>
                        <p>No historical sales data available.</p>
                    <?php } ?>
                    
                    <h3 id="prediction-sales">Forecast Sales Data</h3>
                    <?php if (isset($response_data['sales']['forecast']) && count($response_data['sales']['forecast']) > 0) { ?>
                        <table>
                            <tr>
                                <th>Date</th>
                                <th>Forecast Sales (₹)</th>
                                <th>Lower CI</th>
                                <th>Upper CI</th>
                            </tr>
                            <?php foreach($response_data['sales']['forecast'] as $row) { ?>
                                <tr>
                                    <td><?= htmlspecialchars(date("Y-m-d", strtotime($row['Date']))) ?></td>
                                    <td><?= number_format($row['Forecast Sales'], 2) ?></td>
                                    <td><?= number_format($row['Lower CI'], 2) ?></td>
                                    <td><?= number_format($row['Upper CI'], 2) ?></td>
                                </tr>
                            <?php } ?>
                        </table>
                    <?php } else { ?>
                        <p>No forecast sales data available.</p>
                    <?php } ?>
                    
                    <hr>
                    
                    <h2>Profit Forecast</h2>
                    <img src="<?= htmlspecialchars($response_data['profit']['forecast_image']) ?>" alt="Profit Forecast">

                    <!-- Toggle button for Profit Future Only Graph -->
                    <button class="toggle-btn" id="toggleProfitForecastButton" onclick="toggleProfitForecastOnly()">Expand Prediction Graph</button>
                    <div id="profitForecastOnlyContainer">
                        <img src="<?= htmlspecialchars($response_data['profit']['forecast_only_image']) ?>" alt="Profit Forecast (Future Only)">
                    </div>
                    
                    <h3 style="margin-top: 20px;">Historical Profit Data</h3>
                    <?php if (isset($response_data['profit']['historical']) && count($response_data['profit']['historical']) > 0) { ?>
                        <table>
                            <tr>
                                <th>Date</th>
                                <th>Profit (₹)</th>
                            </tr>
                            <?php foreach($response_data['profit']['historical'] as $row) { ?>
                                <tr>
                                    <td><?= htmlspecialchars(date("Y-m-d", strtotime($row['Order Date']))) ?></td>
                                    <td><?= number_format($row['Profit'], 2) ?></td>
                                </tr>
                            <?php } ?>
                        </table>
                    <?php } else { ?>
                        <p>No historical profit data available.</p>
                    <?php } ?>
                    
                    <h3>Forecast Profit Data</h3>
                    <?php if (isset($response_data['profit']['forecast']) && count($response_data['profit']['forecast']) > 0) { ?>
                        <table>
                            <tr>
                                <th>Date</th>
                                <th>Forecast Profit (₹)</th>
                                <th>Lower CI</th>
                                <th>Upper CI</th>
                            </tr>
                            <?php foreach($response_data['profit']['forecast'] as $row) { ?>
                                <tr>
                                    <td><?= htmlspecialchars(date("Y-m-d", strtotime($row['Date']))) ?></td>
                                    <td><?= number_format($row['Forecast Profit'], 2) ?></td>
                                    <td><?= number_format($row['Lower CI'], 2) ?></td>
                                    <td><?= number_format($row['Upper CI'], 2) ?></td>
                                </tr>
                            <?php } ?>
                        </table>
                    <?php } else { ?>
                        <p>No forecast profit data available.</p>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2024 - <?= date("Y") ?> Smart Cart. All rights reserved. Designed by AJ.</p>
        </div>
    </footer>

    <!-- Scroll to Top Icon -->
    <button id="scrollToTopBtn" title="Go to top"><i class="fas fa-chevron-up"></i></button>

    <script src="scripts.js"></script>
    <script>
        // Toggle display of Sales Forecast Only Graph with animation
        function toggleSalesForecastOnly() {
            var container = document.getElementById("salesForecastOnlyContainer");
            var btn = document.getElementById("toggleSalesForecastButton");
            if (container.classList.contains("open")) {
                container.classList.remove("open");
                btn.textContent = "Expand Prediction Graph";
            } else {
                container.classList.add("open");
                btn.textContent = "Hide Prediction Graph";
            }
        }

        // Toggle display of Profit Forecast Only Graph with animation
        function toggleProfitForecastOnly() {
            var container = document.getElementById("profitForecastOnlyContainer");
            var btn = document.getElementById("toggleProfitForecastButton");
            if (container.classList.contains("open")) {
                container.classList.remove("open");
                btn.textContent = "Expand Prediction Graph";
            } else {
                container.classList.add("open");
                btn.textContent = "Hide Prediction Graph";
            }
        }

        // Show the "Enter Value" container and the appropriate suggestion box when a prediction type is selected.
        document.getElementById("prediction_type").addEventListener("change", function() {
            var type = this.value;
            var container = document.getElementById("value_input_container");
            // Hide all suggestion boxes first.
            document.getElementById("product-container").style.display = "none";
            document.getElementById("subcategory-container").style.display = "none";
            document.getElementById("category-container").style.display = "none";
            if (type !== "") {
                container.style.display = "block";
                if (type === "product") {
                    document.getElementById("product-container").style.display = "block";
                } else if (type === "sub-category") {
                    document.getElementById("subcategory-container").style.display = "block";
                } else if (type === "category") {
                    document.getElementById("category-container").style.display = "block";
                }
            } else {
                container.style.display = "none";
            }
        });
        
        // Filter suggestions based on input text, depending on prediction type.
        document.getElementById("user_value").addEventListener("input", function() {
            var filter = this.value.toLowerCase();
            var predictionType = document.getElementById("prediction_type").value;
            if (predictionType === "product") {
                var items = document.querySelectorAll("#product-list li");
                var visibleCount = 0;
                items.forEach(function(item) {
                    if (item.textContent.toLowerCase().includes(filter)) {
                        item.style.display = "";
                        visibleCount++;
                    } else {
                        item.style.display = "none";
                    }
                });
                var noMatch = document.getElementById("no-match-product");
                noMatch.style.display = (visibleCount === 0) ? "block" : "none";
            } else if (predictionType === "sub-category") {
                var items = document.querySelectorAll("#subcategory-list li");
                var visibleCount = 0;
                items.forEach(function(item) {
                    if (item.textContent.toLowerCase().includes(filter)) {
                        item.style.display = "";
                        visibleCount++;
                    } else {
                        item.style.display = "none";
                    }
                });
                var noMatch = document.getElementById("no-match-subcategory");
                noMatch.style.display = (visibleCount === 0) ? "block" : "none";
            } else if (predictionType === "category") {
                var items = document.querySelectorAll("#category-list li");
                var visibleCount = 0;
                items.forEach(function(item) {
                    if (item.textContent.toLowerCase().includes(filter)) {
                        item.style.display = "";
                        visibleCount++;
                    } else {
                        item.style.display = "none";
                    }
                });
                var noMatch = document.getElementById("no-match-category");
                noMatch.style.display = (visibleCount === 0) ? "block" : "none";
            }
        });
        
        // When a suggestion is clicked, populate the input field and close the suggestion box.
        function addSuggestionListener(listSelector, dataAttr, containerId) {
        document.querySelectorAll(listSelector + " li").forEach(function(item) {
            item.addEventListener("mousedown", function() {
            document.getElementById("user_value").value = this.getAttribute(dataAttr);
            document.getElementById(containerId).style.display = "none";
            });
        });
        }
        addSuggestionListener("#product-list", "data-product", "product-container");
        addSuggestionListener("#subcategory-list", "data-subcategory", "subcategory-container");
        addSuggestionListener("#category-list", "data-category", "category-container");

        // Reopen suggestion box when the user clicks on the input field.
        document.getElementById("user_value").addEventListener("click", function() {
        var type = document.getElementById("prediction_type").value;
        if (type === "product") {
            document.getElementById("product-container").style.display = "block";
        } else if (type === "sub-category") {
            document.getElementById("subcategory-container").style.display = "block";
        } else if (type === "category") {
            document.getElementById("category-container").style.display = "block";
        }
        });

        // Show loading screen on form submission.
        document.getElementById("uploadForm").onsubmit = function() {
            document.getElementById("loading-screen").style.display = "block";
        };
    </script>
</body>
</html>