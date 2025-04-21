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

$response_data = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['salesFile'])) {
    // Retrieve file and form fields
    $file = $_FILES['salesFile'];
    $filename = $file['tmp_name'];
    $sub_category = $_POST['sub_category'];
    $product_name = isset($_POST['product_name']) ? $_POST['product_name'] : '';

    // Use cURL to send the file and additional data to the Flask API
    $ch = curl_init();
    $cfile = new CURLFile($filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $file['name']);

    $postData = array(
        'file' => $cfile,
        'sub_category' => $sub_category,
        'product_name' => $product_name
    );

    // Set the URL to your Flask API endpoint (adjust if necessary)
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:5000/linear_regression_forecast');
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Linear Regression Forecasting</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        /* Sample CSS styling (adjust as needed) */
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
        footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 20px;
        }
        nav ul {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        form.salesform {
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #f8f9fa;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        form.salesform label {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: bold;
        }
        form.salesform input[type="file"],
        form.salesform select {
            font-size: 1rem;
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            width: 100%;
            margin-bottom: 20px;
            transition: border-color 0.3s ease-in-out;
        }
        form.salesform button {
            padding: 10px 20px;
            color: #fff;
            background-color: #28a745;
            border: none;
            border-radius: 4px;
            cursor: pointer;
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
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .product-click {
            color: #000000;
            text-decoration: none;
        }
        .product-click:hover {
            text-decoration: underline;
        }
        table {
            width: 90%;
            margin: 0 auto 30px auto;
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
    </style>
</head>
<body>
    <header id="top">
        <div class="container">
            <h1><i class="fas fa-store"></i> Smart Cart</h1>
            <nav>
                <ul>
                    <li><a href="homepage.php">Home</a></li>
                    <li><a href="#prediction-forecast">Prediction</a></li>
                    <li><a href="#recommendations">Recommendations</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section id="prediction" class="content">
        <div class="container">
            <h2 style="color: #333333">Forecasting Sales & Profit using Linear Regression</h2>
            <!-- Loading Screen -->
            <div id="loading-screen">
                <div class="spinner"></div>
                <p>Processing your document, please wait...</p>
            </div>
            <form method="POST" enctype="multipart/form-data" id="uploadForm" class="salesform">
                <label for="salesFile">Upload Sales Data (Excel):</label>
                <input type="file" id="salesFile" name="salesFile" accept=".xlsx" required>
                
                <!-- Sub-Category select dropdown -->
                <label for="sub_category">Sub-Category:</label>
                <select id="sub_category" name="sub_category" required>
                    <option value="">Select Sub-Category</option>
                    <option value="Bookcases">Bookcases</option>
                    <option value="Chairs">Chairs</option>
                    <option value="Furnishings">Furnishings</option>
                    <option value="Tables">Tables</option>
                    <option value="Appliances">Appliances</option>
                    <option value="Art">Art</option>
                    <option value="Binders">Binders</option>
                    <option value="Envelopes">Envelopes</option>
                    <option value="Fasteners">Fasteners</option>
                    <option value="Labels">Labels</option>
                    <option value="Paper">Paper</option>
                    <option value="Storage">Storage</option>
                    <option value="Supplies">Supplies</option>
                    <option value="Accessories">Accessories</option>
                    <option value="Copiers">Copiers</option>
                    <option value="Machines">Machines</option>
                    <option value="Phones">Phones</option>
                    <!-- Add other sub-categories as needed -->
                </select>
                
                <!-- Product Name select dropdown -->
                <label for="product_name">Product Name (optional):</label>
                <select id="product_name" name="product_name">
                    <option value="">Select Product</option>
                </select>
                
                <button type="submit" class="btn">Analyze</button>
            </form>
            <br>
            <div id="predictionResult" class="result">
                <?php if (isset($error)) { ?>
                    <h2>Error:</h2>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php } elseif (isset($response_data)) { ?>
                    <h2 style="color: #333333">Forecast & Recommendations Result</h2>
                    <div id="predictionResult-container">                       
                        <!-- Overall Aggregated View Section -->
                        <?php if (isset($response_data['overall_view'])) { ?>
                            <h3>Overall Aggregated View</h3>
                            <img src="<?= htmlspecialchars($response_data['overall_view']['overall_view_image']) ?>" alt="Overall Aggregated View">
                            <h4 style="color: #34495e">Aggregated Metrics by Category & Sub-Category</h4>
                            <?php
                            $agg_metrics = $response_data['overall_view']['aggregated_metrics'];
                            if (is_array($agg_metrics) && count($agg_metrics) > 0) { ?>
                                <table style="color: black">
                                    <tr>
                                        <th>Category</th>
                                        <th>Sub-Category</th>
                                        <th>Sales (₹)</th>
                                        <th>Profit (₹)</th>
                                        <th>Quantity</th>
                                        <th>Discount (%)</th>
                                        <th>Order Count</th>
                                        <th>Profit Margin (%)</th>
                                    </tr>
                                    <?php foreach($agg_metrics as $row) { ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['Category']) ?></td>
                                            <td><?= htmlspecialchars($row['Sub-Category']) ?></td>
                                            <td><?= number_format($row['Sales'], 2) ?></td>
                                            <td><?= number_format($row['Profit'], 2) ?></td>
                                            <td><?= number_format($row['Quantity'], 2) ?></td>
                                            <td><?= number_format($row['Discount'], 2) ?></td>
                                            <td><?= number_format($row['Order Count'], 2) ?></td>
                                            <td><?= number_format($row['Profit Margin (%)'], 2) ?></td>
                                        </tr>
                                    <?php } ?>
                                </table>
                            <?php } else { ?>
                                <p>No aggregated metrics available.</p>
                            <?php } ?>
                        <?php } ?>
                        
                        <hr>

                        <!-- Sub-Category Forecast -->
                        <h2 id="prediction-forecast" style="color: #333333">Sub-Category Forecast</h2>
                        <p style="color: #34495e"><strong>Sales Forecast Graph:</strong></p>
                        <img src="<?= htmlspecialchars($response_data['sub_category_forecast']['sales_forecast_image']) ?>" alt="Sales Forecast">
                        <p style="color: #34495e"><strong>Profit Forecast Graph:</strong></p>
                        <img src="<?= htmlspecialchars($response_data['sub_category_forecast']['profit_forecast_image']) ?>" alt="Profit Forecast">

                        <!-- Display Historical Yearly Data -->
                        <h3>Historical Yearly Data for Sub-Category: <?= htmlspecialchars($sub_category) ?></h3>
                        <?php
                        $historical = $response_data['sub_category_forecast']['historical_data'];
                        if (is_array($historical) && count($historical) > 0) { ?>
                            <table style="color: black">
                                <tr>
                                    <th>Year</th>
                                    <th>Sales (₹)</th>
                                    <th>Profit (₹)</th>
                                </tr>
                                <?php foreach($historical as $row) { ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['Year']) ?></td>
                                        <td><?= number_format($row['Sales'], 2) ?></td>
                                        <td><?= number_format($row['Profit'], 2) ?></td>
                                    </tr>
                                <?php } ?>
                            </table>
                        <?php } else { ?>
                            <p>No historical data available.</p>
                        <?php } ?>

                        <!-- Display Forecast for Future Years -->
                        <h3>Forecast for Future Years</h3>
                        <?php
                        $forecast = $response_data['sub_category_forecast']['forecast_data'];
                        if (is_array($forecast) && count($forecast) > 0) { ?>
                            <table style="color: black">
                                <tr>
                                    <th>Year</th>
                                    <th>Predicted Sales (₹)</th>
                                    <th>Predicted Profit (₹)</th>
                                </tr>
                                <?php foreach($forecast as $row) { ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['Year']) ?></td>
                                        <td><?= number_format($row['Predicted Sales'], 2) ?></td>
                                        <td><?= number_format($row['Predicted Profit'], 2) ?></td>
                                    </tr>
                                <?php } ?>
                            </table>
                        <?php } else { ?>
                            <p>No forecast data available.</p>
                        <?php } ?>
                        
                        <!-- Recommended Products -->
                        <h3 id="recommendations">Recommended Products</h3>
                        <?php if (isset($response_data['recommended_products']) && is_array($response_data['recommended_products'])) { ?>
                            <table style="color: black">
                                <tr>
                                    <th>Product Name</th>
                                    <th>Sales (₹)</th>
                                    <th>Profit (₹)</th>
                                    <th>Profit Margin (%)</th>
                                </tr>
                                <?php foreach($response_data['recommended_products'] as $product) { ?>
                                    <tr>
                                        <td>
                                            <a class="product-click" href="product_details.php?product_id=<?= urlencode($product['Product ID']) ?>">
                                                <?= htmlspecialchars($product['Product Name']) ?>
                                            </a>
                                        </td>
                                        <td><?= number_format($product['Sales'], 2) ?></td>
                                        <td><?= number_format($product['Profit'], 2) ?></td>
                                        <td><?= number_format($product['Profit Margin (%)'], 2) ?></td>
                                    </tr>
                                <?php } ?>
                            </table>
                        <?php } else { ?>
                            <p>No recommended products found.</p>
                        <?php } ?>

                        <hr>

                        <!-- Optional: Specific Product Forecast -->
                        <?php if (isset($response_data['product_forecast']) && is_array($response_data['product_forecast'])) { ?>
                            <h2 style="color: #333333">Product Forecast (<?= htmlspecialchars($_POST['product_name']) ?>)</h2 >
                            <p style="color: #34495e"><strong>Sales Forecast Graph:</strong></p>
                            <img src="<?= htmlspecialchars($response_data['product_forecast']['sales_forecast_image']) ?>" alt="Product Sales Forecast">
                            <p style="color: #34495e"><strong>Profit Forecast Graph:</strong></p>
                            <img src="<?= htmlspecialchars($response_data['product_forecast']['profit_forecast_image']) ?>" alt="Product Profit Forecast">
                            
                            <!-- Historical Yearly Data for Product -->
                            <h4 style="color: #34495e">Historical Yearly Data for Product '<?= htmlspecialchars($_POST['product_name']) ?>'</h4>
                            <?php
                            $product_hist = $response_data['product_forecast']['historical_data'];
                            if (is_array($product_hist) && count($product_hist) > 0) { ?>
                                <table style="color: black">
                                    <tr>
                                        <th>Year</th>
                                        <th>Sales (₹)</th>
                                        <th>Profit (₹)</th>
                                    </tr>
                                    <?php foreach($product_hist as $row) { ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['Year']) ?></td>
                                            <td><?= number_format($row['Sales'], 2) ?></td>
                                            <td><?= number_format($row['Profit'], 2) ?></td>
                                        </tr>
                                    <?php } ?>
                                </table>
                            <?php } else { ?>
                                <p>No historical product data available.</p>
                            <?php } ?>
                            
                            <!-- Forecast for Future Years for Product -->
                            <h4 style="color: #34495e">Forecast for Product '<?= htmlspecialchars($_POST['product_name']) ?>'</h4>
                            <?php
                            $product_forecast = $response_data['product_forecast']['forecast_data'];
                            if (is_array($product_forecast) && count($product_forecast) > 0) { ?>
                                <table style="color: black">
                                    <tr>
                                        <th>Year</th>
                                        <th>Predicted Sales (₹)</th>
                                        <th>Predicted Profit (₹)</th>
                                    </tr>
                                    <?php foreach($product_forecast as $row) { ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['Year']) ?></td>
                                            <td><?= number_format($row['Predicted Sales'], 2) ?></td>
                                            <td><?= number_format($row['Predicted Profit'], 2) ?></td>
                                        </tr>
                                    <?php } ?>
                                </table>
                            <?php } else { ?>
                                <p>No forecast product data available.</p>
                            <?php } ?>
                        <?php } ?>
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

        // JavaScript mapping of sub-categories to product names (update these values with actual products)
        var productMapping = {
            "Bookcases": ["Atlantic Metals Mobile 3-Shelf Bookcases, Custom Colors", "O'Sullivan 2-Door Barrister Bookcase in Odessa Pine", `Rush Hierlooms Collection 1" Thick Stackable Bookcases`, "Hon Metal Bookcases, Putty", "Sauder Inglewood Library Bookcases"],
            "Chairs": ["Hon Deluxe Fabric Upholstered Stacking Chairs, Rounded Back", "Global Deluxe High-Back Manager's Chair", "Hon Pagoda Stacking Chairs", "Hon 4070 Series Pagoda Armless Upholstered Stacking Chairs", "Office Star - Professional Matrix Back Chair with 2-to-1 Synchro Tilt and Mesh Fabric Seat"],
            "Furnishings": [`GE 48" Fluorescent Tube, Cool White Energy Saver, 34 Watts, 30/Box`, "Electrix Architect's Clamp-On Swing Arm Lamp, Black", "3M Polarizing Task Lamp with Clamp Arm, Light Gray", `Howard Miller 11-1/2" Diameter Ridgewood Wall Clock`, `Howard Miller 14-1/2" Diameter Chrome Round Wall Clock`],
            "Tables": ["SAFCO PlanMaster Heigh-Adjustable Drafting Table Base, 43w x 30d x 30-37h, Black", "Hon Non-Folding Utility Tables", "Bretford CR8500 Series Meeting Room Furniture", "Bevis 36 x 72 Conference Tables", "Hon Practical Foundations 30 x 60 Training Table, Light Gray/Charcoal"],
            "Appliances": ["Honeywell Enviracaire Portable HEPA Air Cleaner for 17' x 22' Room", "Sanyo Counter Height Refrigerator with Crisper, 3.6 Cubic Foot, Stainless Steel/Black", "Honeywell Enviracaire Portable HEPA Air Cleaner for 16' x 20' Room", "Sanyo 2.5 Cubic Foot Mid-Size Office Refrigerators", "Hoover Shoulder Vac Commercial Portable Vacuum"],
            "Art": ["Hunt PowerHouse Electric Pencil Sharpener, Blue", "Boston Heavy-Duty Trimline Electric Pencil Sharpeners", "Boston 1645 Deluxe Heavier-Duty Electric Pencil Sharpener", "Dixon Ticonderoga Core-Lock Colored Pencils, 48-Color Set", "Prismacolor Color Pencil Set"],
            "Binders": ["Fellowes PB500 Electric Punch Plastic Comb Binding Machine with Manual Bind", "Ibico EPK-21 Electric Binding System", "Fellowes PB300 Plastic Comb Binding Machine", "Ibico Ibimaster 300 Manual Binding System", "GBC DocuBind TL300 Electric Binding System"],
            "Envelopes": ["Staple envelope", "Cameo Buff Policy Envelopes", "Tyvek Side-Opening Peel & Seel Expanding Envelopes", "Ames Color-File Green Diamond Border X-ray Mailers", "Airmail Envelopes"],
            "Fasteners": ["Staples", "Vinyl Coated Wire Paper Clips in Organizer Box, 800/Box", "Advantus Plastic Paper Clips", "OIC Binder Clips", "OIC Colored Binder Clips, Assorted Sizes"],
            "Labels": ["Dot Matrix Printer Tape Reel Labels, White, 5000/Box", "Avery 4027 File Folder Labels for Dot Matrix Printers, 5000 Labels per Box, White", "Avery 485", "Avery 477", "Alphabetical Labels for Top Tab Filing"],
            "Paper": ["Xerox 1915", "Easy-staple paper", "Multicolor Computer Printout Paper", "Xerox 1908", "Xerox 1945"],
            "Storage": ["Hot File 7-Pocket, Floor Stand", "Adjustable Depth Letter/Legal Cart", "Dual Level, Single-Width Filing Carts", "Tennsco 6- and 18-Compartment Lockers", "Iceberg Mobile Mega Data/Printer Cart"],
            "Supplies": [`Acme 10" Easy Grip Assistive Scissors`, `Acme Hot Forged Carbon Steel Scissors with Nickel-Plated Handles, 3 7/8" Cut, 8"L`, "Fiskars Softgrip Scissors", "Acme Box Cutter Scissors", "Acme Forged Steel Scissors with Black Enamel Handles"],
            "Accessories": ["Plantronics Savi W720 Multi-Device Wireless Headset System", "Plantronics CS510 - Over-the-Head monaural Wireless Headset System", "Logitech Z-906 Speaker sys - home theater - 5.1-CH", "Razer Tiamat Over Ear 7.1 Surround Sound PC Gaming Headset", "Logitech P710e Mobile Speakerphone"],
            "Copiers": ["Canon imageCLASS 2200 Advanced Copier", "Hewlett Packard LaserJet 3310 Copier", "Canon PC1060 Personal Laser Copier", "Hewlett Packard 610 Color Digital Copier / Printer", "Canon Imageclass D680 Copier / Fax"],
            "Machines": [`HP Designjet T520 Inkjet Large Format Printer - 24" Color`, "Ativa V4110MDD Micro-Cut Shredder", "3D Systems Cube Printer, 2nd Generation, Magenta", "Zebra ZM400 Thermal Label Printer", "Canon imageCLASS MF7460 Monochrome Digital Laser Multifunction Copier"],
            "Phones": ["Samsung Galaxy Mega 6.3", "Apple iPhone 5", "Panasonic KX-TG9471B", "Panasonic KX-TG9541B DECT 6.0 Digital 2-Line Expandable Cordless Phone With Digital Answering System", "Samsung Galaxy S4 Mini"],
            // Add other sub-categories and their respective products as needed
        };

        // When the sub_category select changes, update the product_name select options accordingly
        document.getElementById("sub_category").addEventListener("change", function() {
            var selectedSubCat = this.value;
            var productSelect = document.getElementById("product_name");
            // Clear previous options
            productSelect.innerHTML = '<option value="">Select Product</option>';
            if (selectedSubCat in productMapping) {
                productMapping[selectedSubCat].forEach(function(product) {
                    var opt = document.createElement("option");
                    opt.value = product;
                    opt.innerHTML = product;
                    productSelect.appendChild(opt);
                });
            }
        });
    </script>
</body>
</html>
