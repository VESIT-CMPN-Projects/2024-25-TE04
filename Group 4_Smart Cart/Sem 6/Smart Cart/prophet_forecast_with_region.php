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
// Fetch distinct products, sub-categories, categories, and region values from the database
// ----------------------------------------------------------------------
$all_products = array();
$all_subcategories = array();
$all_categories = array();
$all_cities = array();
$all_states = array();
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
    // Fetch distinct cities
    $query = "SELECT DISTINCT City FROM products ORDER BY City ASC";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $all_cities[] = $row;
        }
    }
    // Fetch distinct states
    $query = "SELECT DISTINCT State FROM products ORDER BY State ASC";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $all_states[] = $row;
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
    
    // Get form fields: region_type, region_value, prediction_level, level_value.
    $region_type      = isset($_POST['region_type']) ? trim($_POST['region_type']) : '';
    $region_value     = isset($_POST['region_value']) ? trim($_POST['region_value']) : '';
    $prediction_level = isset($_POST['prediction_level']) ? trim($_POST['prediction_level']) : '';
    $level_value      = isset($_POST['level_value']) ? trim($_POST['level_value']) : '';
    
    if(empty($region_type) || empty($region_value) || empty($prediction_level) || empty($level_value)){
        $error = "All fields (region type, region value, prediction level, and level value) must be provided.";
    } else {
        // Use cURL to send the file and additional data to the Flask API.
        $ch = curl_init();
        $cfile = new CURLFile($filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $file['name']);
        $postData = array(
            'file' => $cfile,
            'region_type' => $region_type,
            'region_value' => $region_value,
            'prediction_level' => $prediction_level,
            'level_value' => $level_value
        );
        // Adjust the URL as needed (Flask API endpoint for Prophet forecasting with region filtering).
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:5000/prophet_forecast_with_region');
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
    <title>Prophet Forecasting</title>
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
        /* Form styling */
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
            background-color: rgba(0,0,0,0.7);
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
        /* Table styling */
        table {
            width: 80%;
            margin: 20px auto;
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
        /* Plotly container */
        #plotContainer {
            transform: translateX(52px);
        }
        .modebar {
            transform: translateX(-5%) !important;
            transform: translateY(-35px) !important;
        }
        /* Suggestion box styling for each prediction type */
        #value_input_container, #region_value_container {
            display: none;
            width: 100%;
            text-align: center;
            transform: translateX(-11px);
        }
        #value_input_container label, #region_value_container label {
            display: block;
            margin-bottom: 10px;
            transform: translateX(11px);
        }
        #value_input_container input[type="text"], #region_value_container input[type="text"] {
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
        /* Footer styling */
        footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 20px 0;
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
                    <li><a href="#prediction-sales">Sales Prediction</a></li>
                    <li><a href="#prediction-profit">Profit Prediction</a></li>
                    <li><a href="#forecast-result">Forecast</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <section class="content">
        <div class="container">
            <h2>Forecasting Sales and Profit Region-wise using PROPHET Model</h2>
            <!-- Loading Screen -->
            <div id="loading-screen">
                <div class="spinner"></div>
                <p>Processing your file, please wait...</p>
            </div>
            <form method="POST" enctype="multipart/form-data" id="uploadForm" class="forecastform">
                <label for="forecastFile">Upload Sales Data (Excel):</label>
                <input type="file" id="forecastFile" name="forecastFile" accept=".xlsx" required>

                <label for="region_type">Region Type:</label>
                <select id="region_type" name="region_type" required>
                    <option value="">Select Region Type</option>
                    <option value="state">State</option>
                    <option value="city">City</option>
                </select>

                <!-- Hidden region value container -->
                <div id="region_value_container">
                    <label for="region_value">Region Value:</label>
                    <input type="text" id="region_value" name="region_value" placeholder="Enter region value" required>
                    
                    <!-- Suggestion container for City -->
                    <div id="city-container" class="suggestion-container">
                        <ul id="city-list">
                            <?php foreach ($all_cities as $city) { ?>
                                <li data-city="<?= htmlspecialchars($city['City']) ?>">
                                    <?= htmlspecialchars($city['City']) ?>
                                </li>
                            <?php } ?>
                        </ul>
                        <div id="no-match-city" class="no-match">No city found</div>
                    </div>

                    <!-- Suggestion container for State -->
                    <div id="state-container" class="suggestion-container">
                        <ul id="state-list">
                            <?php foreach ($all_states as $state) { ?>
                                <li data-state="<?= htmlspecialchars($state['State']) ?>">
                                    <?= htmlspecialchars($state['State']) ?>
                                </li>
                            <?php } ?>
                        </ul>
                        <div id="no-match-state" class="no-match">No state found</div>
                    </div>
                </div>

                <label for="prediction_level">Prediction Level:</label>
                <select id="prediction_level" name="prediction_level" required>
                    <option value="">Select Level</option>
                    <option value="category">Category</option>
                    <option value="sub-category">Sub-Category</option>
                    <option value="product">Product</option>
                </select>

                <!-- Hidden value input container -->                
                <div id="value_input_container">
                    <label for="level_value">Level Value:</label>
                    <input type="text" id="level_value" name="level_value" placeholder="Enter value" required>
                    
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
                <h2 id="forecast-result">Forecast Results for <?= htmlspecialchars($_POST['level_value']) ?></h2>
            <?php } ?>
            <div id="result-container" style="<?php echo (empty($response_data) && empty($error)) ? 'display:none;' : 'display:block;'; ?>">
                <?php if (!empty($error)) { ?>
                    <h2>Error:</h2>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php } elseif (isset($response_data['error'])) { ?>
                    <h2>Error:</h2>
                    <p><?= htmlspecialchars($response_data['error']) ?></p>
                <?php } elseif (!empty($response_data)) { ?>
                    <!-- Display Interactive Plotly Forecasts -->
                    <h3>Sales Forecast Plot</h3>
                    <div id="plotContainer">
                        <?= isset($response_data['sales']['plot_html']) ? $response_data['sales']['plot_html'] : '<p>No sales plot available.</p>' ?>
                    </div>
                    
                    <!-- Historical Sales Data Table -->
                    <h3>Historical Sales Data</h3>
                    <?php if (isset($response_data['sales']['historical']) && count($response_data['sales']['historical']) > 0) { ?>
                        <table>
                            <tr>
                                <th>Date</th>
                                <th>Sales (₹)</th>
                            </tr>
                            <?php foreach ($response_data['sales']['historical'] as $row) { ?>
                                <tr>
                                    <td><?= htmlspecialchars(date("Y-m-d", strtotime($row['Order Date']))) ?></td>
                                    <td><?= number_format($row['Sales'], 2) ?></td>
                                </tr>
                            <?php } ?>
                        </table>
                    <?php } else { ?>
                        <p>No historical sales data available.</p>
                    <?php } ?>
                    
                    <!-- Forecast Sales Data Table -->
                    <h3 id="prediction-sales">Forecast Sales Data</h3>
                    <?php if (isset($response_data['sales']['forecast']) && count($response_data['sales']['forecast']) > 0) { ?>
                        <table>
                            <tr>
                                <th>Date</th>
                                <th>Forecast Sales (₹)</th>
                                <th>Lower CI</th>
                                <th>Upper CI</th>
                            </tr>
                            <?php foreach ($response_data['sales']['forecast'] as $row) { ?>
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

                    <h3>Profit Forecast Plot</h3>
                    <div id="plotContainer">
                        <?= isset($response_data['profit']['plot_html']) ? $response_data['profit']['plot_html'] : '<p>No profit plot available.</p>' ?>
                    </div>
                    
                    <!-- Historical Profit Data Table -->
                    <h3>Historical Profit Data</h3>
                    <?php if (isset($response_data['profit']['historical']) && count($response_data['profit']['historical']) > 0) { ?>
                        <table>
                            <tr>
                                <th>Date</th>
                                <th>Profit (₹)</th>
                            </tr>
                            <?php foreach ($response_data['profit']['historical'] as $row) { ?>
                                <tr>
                                    <td><?= htmlspecialchars(date("Y-m-d", strtotime($row['Order Date']))) ?></td>
                                    <td><?= number_format($row['Profit'], 2) ?></td>
                                </tr>
                            <?php } ?>
                        </table>
                    <?php } else { ?>
                        <p>No historical profit data available.</p>
                    <?php } ?>
                    
                    <!-- Forecast Profit Data Table -->
                    <h3 id="prediction-profit">Forecast Profit Data</h3>
                    <?php if (isset($response_data['profit']['forecast']) && count($response_data['profit']['forecast']) > 0) { ?>
                        <table>
                            <tr>
                                <th>Date</th>
                                <th>Forecast Profit (₹)</th>
                                <th>Lower CI</th>
                                <th>Upper CI</th>
                            </tr>
                            <?php foreach ($response_data['profit']['forecast'] as $row) { ?>
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
                    
                    <!-- Combined Accuracy Matrix -->
                    <h3>Accuracy Metrics</h3>
                    <table>
                        <tr>
                            <th></th>
                            <th>MAE</th>
                            <th>RMSE</th>
                            <th>R2</th>
                        </tr>
                        <tr>
                            <td>Sales - Train</td>
                            <td><?= isset($response_data['sales']['metrics']['train']['MAE']) ? number_format($response_data['sales']['metrics']['train']['MAE'], 2) : 'N/A' ?></td>
                            <td><?= isset($response_data['sales']['metrics']['train']['RMSE']) ? number_format($response_data['sales']['metrics']['train']['RMSE'], 2) : 'N/A' ?></td>
                            <td><?= isset($response_data['sales']['metrics']['train']['R2']) ? number_format($response_data['sales']['metrics']['train']['R2'], 4) : 'N/A' ?></td>
                        </tr>
                        <tr>
                            <td>Sales - Test</td>
                            <td><?= isset($response_data['sales']['metrics']['test']['MAE']) ? number_format($response_data['sales']['metrics']['test']['MAE'], 2) : 'N/A' ?></td>
                            <td><?= isset($response_data['sales']['metrics']['test']['RMSE']) ? number_format($response_data['sales']['metrics']['test']['RMSE'], 2) : 'N/A' ?></td>
                            <td><?= isset($response_data['sales']['metrics']['test']['R2']) ? number_format($response_data['sales']['metrics']['test']['R2'], 4) : 'N/A' ?></td>
                        </tr>
                        <tr>
                            <td>Profit - Train</td>
                            <td><?= isset($response_data['profit']['metrics']['train']['MAE']) ? number_format($response_data['profit']['metrics']['train']['MAE'], 2) : 'N/A' ?></td>
                            <td><?= isset($response_data['profit']['metrics']['train']['RMSE']) ? number_format($response_data['profit']['metrics']['train']['RMSE'], 2) : 'N/A' ?></td>
                            <td><?= isset($response_data['profit']['metrics']['train']['R2']) ? number_format($response_data['profit']['metrics']['train']['R2'], 4) : 'N/A' ?></td>
                        </tr>
                        <tr>
                            <td>Profit - Test</td>
                            <td><?= isset($response_data['profit']['metrics']['test']['MAE']) ? number_format($response_data['profit']['metrics']['test']['MAE'], 2) : 'N/A' ?></td>
                            <td><?= isset($response_data['profit']['metrics']['test']['RMSE']) ? number_format($response_data['profit']['metrics']['test']['RMSE'], 2) : 'N/A' ?></td>
                            <td><?= isset($response_data['profit']['metrics']['test']['R2']) ? number_format($response_data['profit']['metrics']['test']['R2'], 4) : 'N/A' ?></td>
                        </tr>
                    </table>
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
        // Region Type change: show region value container and proper suggestion box.
        document.getElementById("region_type").addEventListener("change", function() {
            var type = this.value;
            var container = document.getElementById("region_value_container");
            // Hide all suggestion boxes first.
            document.getElementById("city-container").style.display = "none";
            document.getElementById("state-container").style.display = "none";
            if (type !== "") {
                container.style.display = "block";
                if (type === "city") {
                    document.getElementById("city-container").style.display = "block";
                } else if (type === "state") {
                    document.getElementById("state-container").style.display = "block";
                }
            } else {
                container.style.display = "none";
            }
        });
        
        // Filter suggestions for region value.
        document.getElementById("region_value").addEventListener("input", function() {
            var filter = this.value.toLowerCase();
            var regionType = document.getElementById("region_type").value;
            var items, visibleCount = 0, noMatch;
            if (regionType === "city") {
                items = document.querySelectorAll("#city-list li");
                noMatch = document.getElementById("no-match-city");
            } else if (regionType === "state") {
                items = document.querySelectorAll("#state-list li");
                noMatch = document.getElementById("no-match-state");
            }
            if (items) {
                items.forEach(function(item) {
                    if (item.textContent.toLowerCase().includes(filter)) {
                        item.style.display = "";
                        visibleCount++;
                    } else {
                        item.style.display = "none";
                    }
                });
                noMatch.style.display = (visibleCount === 0) ? "block" : "none";
            }
        });
        
        // Suggestion listeners for region value.
        function addRegionSuggestionListener(listSelector, dataAttr, containerId) {
            document.querySelectorAll(listSelector + " li").forEach(function(item) {
                item.addEventListener("mousedown", function() {
                    document.getElementById("region_value").value = this.getAttribute(dataAttr);
                    document.getElementById(containerId).style.display = "none";
                });
            });
        }
        addRegionSuggestionListener("#city-list", "data-city", "city-container");
        addRegionSuggestionListener("#state-list", "data-state", "state-container");

        // Reopen suggestion box when the user clicks on the input field.
        document.getElementById("region_value").addEventListener("click", function() {
            var type = document.getElementById("region_type").value;
            if (type === "city") {
                document.getElementById("city-container").style.display = "block";
            } else if (type === "state") {
                document.getElementById("state-container").style.display = "block";
            }
        });
        
        // Show the "Enter Value" container and suggestion boxes when prediction level is selected.
        document.getElementById("prediction_level").addEventListener("change", function() {
            var type = this.value;
            var container = document.getElementById("value_input_container");
            // Hide all suggestion containers first.
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
        
        // Filter suggestions based on input text for level value.
        document.getElementById("level_value").addEventListener("input", function() {
            var filter = this.value.toLowerCase();
            var level = document.getElementById("prediction_level").value;
            var items, visibleCount = 0, noMatch;
            if (level === "product") {
                items = document.querySelectorAll("#product-list li");
                noMatch = document.getElementById("no-match-product");
            } else if (level === "sub-category") {
                items = document.querySelectorAll("#subcategory-list li");
                noMatch = document.getElementById("no-match-subcategory");
            } else if (level === "category") {
                items = document.querySelectorAll("#category-list li");
                noMatch = document.getElementById("no-match-category");
            }
            if (items) {
                items.forEach(function(item) {
                    if (item.textContent.toLowerCase().includes(filter)) {
                        item.style.display = "";
                        visibleCount++;
                    } else {
                        item.style.display = "none";
                    }
                });
                noMatch.style.display = (visibleCount === 0) ? "block" : "none";
            }
        });
        
        // When a suggestion is clicked, populate the input field for level value.
        function addSuggestionListener(listSelector, dataAttr, containerId) {
            document.querySelectorAll(listSelector + " li").forEach(function(item) {
                item.addEventListener("mousedown", function() {
                    document.getElementById("level_value").value = this.getAttribute(dataAttr);
                    document.getElementById(containerId).style.display = "none";
                });
            });
        }
        addSuggestionListener("#product-list", "data-product", "product-container");
        addSuggestionListener("#subcategory-list", "data-subcategory", "subcategory-container");
        addSuggestionListener("#category-list", "data-category", "category-container");

        // Reopen suggestion box on level value input click.
        document.getElementById("level_value").addEventListener("click", function() {
            var type = document.getElementById("prediction_level").value;
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
        
        // Scroll to top button functionality.
        window.onscroll = function() {
            var btn = document.getElementById("scrollToTopBtn");
            if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
                btn.style.display = "flex";
            } else {
                btn.style.display = "none";
            }
        };
        document.getElementById("scrollToTopBtn").addEventListener("click", function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>
</body>
</html>