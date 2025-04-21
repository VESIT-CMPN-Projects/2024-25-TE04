<?php
session_start();

// Include your database configuration details.
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "superstore";

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

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

$all_products = array();

// Create a new database connection.
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query to fetch distinct product IDs and names.
$query = "SELECT DISTINCT `Product ID`, `Product Name` FROM products ORDER BY `Product Name` ASC";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $all_products[] = $row;
    }
}

$error = null;
$agg = null;
$purchases = array();

// Check if a product_id is provided in the URL.
if (isset($_GET['product_id']) && !empty($_GET['product_id'])) {
    $product_id = $_GET['product_id'];

    // Query aggregated details for the product.
    $stmt = $conn->prepare("
        SELECT 
            `Product Name`,
            `Category`,
            `Sub-Category`,
            `Product Image`, 
            SUM(`Sales`) AS total_sales, 
            SUM(`Profit`) AS total_profit, 
            SUM(`Quantity`) AS total_quantity, 
            AVG(`Discount`) AS avg_discount 
        FROM products 
        WHERE `Product ID` = ? 
        GROUP BY `Product Name`, `Product Image`
    ");
    $stmt->bind_param("s", $product_id);
    $stmt->execute();
    $agg_result = $stmt->get_result();

    if ($agg_result->num_rows > 0) {
        $agg = $agg_result->fetch_assoc();
    } else {
        $error = "Product not found.";
    }
    $stmt->close();

    // Query individual purchase details for this product.
    $stmt2 = $conn->prepare("
        SELECT 
            `Order Date`, 
            `Order ID`,
            `Customer Name`, 
            `City`, 
            `State`,
            `Postal Code`
        FROM products 
        WHERE `Product ID` = ?
        ORDER BY `Order Date` ASC
    ");
    $stmt2->bind_param("s", $product_id);
    $stmt2->execute();
    $purchase_result = $stmt2->get_result();

    while ($row = $purchase_result->fetch_assoc()) {
        $purchases[] = $row;
    }
    $stmt2->close();
} else {
    // If no product id was passed, set an error message.
    $error = "Use the search box to select a product.";
}

// Close the connection (you can reopen it later if needed).
$conn->close();

// Set a default placeholder image if the product image is empty.
if (!isset($error)) {
    $image_url = trim($agg['Product Image']);
    if (empty($image_url)) {
        $image_url = "Assets/Images/product_placeholder.jpg"; // Ensure this file exists.
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Details</title>
    <link rel="stylesheet" href="styles.css">
    <!-- Link to your CSS file or include inline styles -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
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
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            display: flex;
            flex-direction: column;
        }
        nav ul {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        .content {
            flex-grow: 1;
        }
        .search-bar {
            background-color: #f8f9fa;
            max-width: 800px;
            margin: 0 auto 20px auto;
            padding: 20px;
            text-align: center;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .search-input {
            display: flex;
            align-items: center;
            margin-top: 20px;
            width: 100%;
        }
        .search-input input {
            flex: 1;
            padding: 10px;
            font-size: 16px;
        }
        .search-bar form input[type="text"] {
            border: 1px solid #333;
            border-radius: 4px;
            font-size: 16px;
            padding: 10px;
            margin: 5px;
            width: 80%;
        }
        .search-bar form button {
            background-color: #28a745;
            color: white;
            border: 1px solid #28a745;
            border-radius: 4px;
            font-size: 16px;
            padding: 10px;
            margin: 5px;
        }
        .search-bar form button:hover {
            cursor: pointer;
        }
        .search-btn {
            width: 40px;
            height: 40px;
            padding: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .search-btn img {
            width: 30px;
            height: 30px;
        }
        /* Custom product list box styling */
        #product-container {
            display: none;
            max-height: 200px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-top: 5px;
            padding: 10px;
            text-align: left;
            width: 100%;
            margin-left: auto;
            margin-right: auto;
            transform: translateX(-11px);
        }
        .product-search{
            font-size: 24px;
            font-weight: bold;
        }
        .product-search-form {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        #product-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        #product-list li {
            padding: 5px;
            cursor: pointer;
        }
        #no-match {
            display: none;
            color: #000000;
            text-align: center;
            padding: 5px;
        }
        #product-list li:hover {
            background-color: #f0f0f0;
        }
        .product-details-container {
            max-width: 800px;
            margin: 0 auto;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
        }
        .image-container {
            display: flex;
            justify-content: center;
        }
        .product-image {
            width: 100%;
            max-width: 800px;
            max-height: 600px;
            height: 100%;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .btn {
            display: flex;
            justify-content: center;
            width: 100%;
            background: #28a745;
            color: #fff;
            font-size: 16px;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 3px;
            margin-top: 20px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #555555;
        }
        .label {
            font-weight: bold;
        }
        .product-detail {
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        table th {
            background: #28a745;
            color: white;
        }
        #scrollToTopBtn {
            display: none; /* Hidden by default */
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 99; /* Make sure it stays on top */
            border: none;
            outline: none;
            background-color: #45a049; /* Green background */
            color: #ffffff;
            border-radius: 99px;
            padding: 10px 14px;
            font-size: 20px;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }
        #scrollToTopBtn:hover {
            background-color: #555555;
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
    <header id="top">
        <div class="container">
        <h1 style="color: white;"><i class="fas fa-store"></i> Smart Cart</h1>
            <nav>
                <ul>
                <li><a href="#" class="<?= ($current_page == 'product_details.php') ? 'active' : '' ?>">Search Product</a></li>
                <li><a href="models.php" class="<?= ($current_page == 'models.php') ? 'active' : '' ?>">Our Models</a></li>
                <li><a href="homepage.php" class="<?= ($current_page == 'homepage.php') ? 'active' : '' ?>">Smart Cart</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="content">
        <!-- Search Bar -->
        <div class="search-bar">
            <form method="GET" action="product_details.php" onsubmit="return validateSearch()" class="product-search-form">
                <label for="product_search" class="product-search">Search a Product</label>
                <div class="search-input">
                    <input type="text" name="product_id" id="product_search" placeholder="Type product name..." required autocomplete="off">
                    <button type="submit" class="search-btn">
                        <img src="Assets/Images/search-icon.png" alt="Search Icon">
                    </button>
                </div>
            </form>
            <!-- Custom box showing all products -->
            <div id="product-container">
                <ul id="product-list">
                    <?php foreach ($all_products as $prod) { ?>
                        <li data-product-id="<?= htmlspecialchars($prod['Product ID']) ?>">
                            <?= htmlspecialchars($prod['Product Name']) ?>
                        </li>
                    <?php } ?>
                    <li id="no-match">No product found</li>
                </ul>
            </div>
        </div>
        <!-- Product Details -->
        <div class="product-details-container">
            <?php if (isset($error)) { ?>
                <h2 style="text-align: center; margin: 30px"><?php echo htmlspecialchars($error); ?></h2>
            <?php } else { ?>
                <h2 style="text-align: center"><?php echo htmlspecialchars($agg['Product Name']); ?></h2>
                <!-- Product Category & Sub-Category Table -->
                <table style="margin-bottom: 45px;">
                    <tr>
                        <th>Category</th>
                        <th>Sub-Category</th>
                    </tr>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($agg['Category']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($agg['Sub-Category']); ?>
                        </td>
                    </tr>
                </table>
                <!-- Product Image -->
                <div class="image-container">
                    <img src="<?php echo htmlspecialchars($image_url); ?>" alt="Product Image" class="product-image">
                </div>
                <!-- Aggregated Product Details Table -->
                <h3>Product Details</h3>
                <table style="margin-bottom: 24px;">
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Total Sales (₹)</th>
                            <th>Total Profit (₹)</th>
                            <th>Total Quantity</th>
                            <th>Average Discount (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo htmlspecialchars($product_id); ?></td>
                            <td><?php echo htmlspecialchars(number_format($agg['total_sales'], 2)); ?></td>
                            <td><?php echo htmlspecialchars(number_format($agg['total_profit'], 2)); ?></td>
                            <td><?php echo htmlspecialchars($agg['total_quantity']); ?></td>
                            <td><?php echo htmlspecialchars(number_format($agg['avg_discount'], 2)); ?></td>
                        </tr>
                    </tbody>
                </table>
                <hr>
                <h3>Purchase Details</h3>
                <?php if (!empty($purchases)) { ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Order Date</th>
                                <th>Order ID</th>
                                <th>Customer Name</th>
                                <th>City</th>
                                <th>State</th>
                                <th>Postal Code</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $purchase) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($purchase['Order Date']); ?></td>
                                    <td><?php echo htmlspecialchars($purchase['Order ID']); ?></td>
                                    <td><?php echo htmlspecialchars($purchase['Customer Name']); ?></td>
                                    <td><?php echo htmlspecialchars($purchase['City']); ?></td>
                                    <td><?php echo htmlspecialchars($purchase['State']); ?></td>
                                    <td><?php echo htmlspecialchars($purchase['Postal Code']); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } else { ?>
                    <p style="text-align: center;">No purchase details available for this product.</p>
                <?php } ?>
            <?php } ?>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>&copy; 2024 - <?= date("Y") ?> Smart Cart. All rights reserved. Designed by AJ.</p>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button id="scrollToTopBtn" title="Go to top"><i class="fas fa-chevron-up"></i></button>

    <script>
        var productSearch = document.getElementById('product_search');
        var productContainer = document.getElementById('product-container');

        // Display product container only when the user explicitly clicks the search input.
        productSearch.addEventListener('click', function() {
            productContainer.style.display = "block";
        });

        // Hide the product container when the input loses focus.
        productSearch.addEventListener('blur', function() {
            setTimeout(function(){
                productContainer.style.display = "none";
            }, 200);
        });

        // Filter the product list as the user types.
        productSearch.addEventListener('input', function() {
            var filter = this.value.toLowerCase();
            // Select all li items except the no-match item.
            var items = document.querySelectorAll('#product-list li:not(#no-match)');
            var visibleCount = 0;
            items.forEach(function(item) {
                var text = item.textContent.toLowerCase();
                if (text.includes(filter)) {
                    item.style.display = "";
                    visibleCount++;
                } else {
                    item.style.display = "none";
                }
            });
            // Show or hide the "No product found" message.
            var noMatch = document.getElementById("no-match");
            if (visibleCount === 0) {
                noMatch.style.display = "block";
            } else {
                noMatch.style.display = "none";
            }
        });

        // When a product is clicked, populate the search box.
        var items = document.querySelectorAll('#product-list li');
        items.forEach(function(item) {
            item.addEventListener('mousedown', function(e) {
                productSearch.value = this.getAttribute('data-product-id');
            });
        });

        // Validate search before submission. If the search field is empty, keep the product list hidden.
        function validateSearch() {
            var input = productSearch.value.trim();
            if (input === "") {
                productContainer.style.display = "none";
                return false;
            }
            return true;
        }

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
</body>
</html>
