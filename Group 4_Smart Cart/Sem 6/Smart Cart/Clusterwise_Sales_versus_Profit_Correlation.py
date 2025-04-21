import pandas as pd
import matplotlib.pyplot as plt
import seaborn as sns
import numpy as np
import folium
from folium.plugins import MarkerCluster
from IPython.display import display
from sklearn.preprocessing import StandardScaler
from sklearn.cluster import KMeans

# ---------------------------
# 1. Load Data and Clustering
# ---------------------------
file_path = "Assets/Dataset/Superstore 3.0.xlsx"
xls = pd.ExcelFile(file_path)
orders_df = pd.read_excel(xls, sheet_name="Orders")

# Selecting relevant columns for clustering
cluster_data = orders_df[['Sales', 'Profit', 'Quantity', 'Discount']].copy()
cluster_data = cluster_data.dropna()

# Standardize the data
scaler = StandardScaler()
scaled_data = scaler.fit_transform(cluster_data)

# Apply K-Means clustering (using 4 clusters)
optimal_clusters = 4
kmeans = KMeans(n_clusters=optimal_clusters, random_state=42, n_init=10)
cluster_data['Cluster'] = kmeans.fit_predict(scaled_data)

# Add the cluster labels to the original dataframe
orders_df['Cluster'] = cluster_data['Cluster']

# --------------------------------------------------
# 2. Map Clusters to Descriptive Profitability Labels
# --------------------------------------------------
# Calculate total profit per cluster and sort descending (highest profit first)
cluster_profits = orders_df.groupby('Cluster')['Profit'].sum().sort_values(ascending=False)
sorted_clusters = list(cluster_profits.index)

# Create mapping from original cluster number to a descriptive label
cluster_label_map = {}
n = len(sorted_clusters)
for i, cl in enumerate(sorted_clusters):
    if i == 0:
        label = "Most Profitable"
    elif i == n - 1:
        label = "Least Profitable"
    elif i == 1:
        label = "2nd Most Profitable"
    elif i == 2:
        label = "3rd Most Profitable"
    else:
        label = f"{i+1}th Most Profitable"
    cluster_label_map[cl] = label

# Add new descriptive labels to our dataframes
cluster_data['Cluster_Label'] = cluster_data['Cluster'].map(cluster_label_map)
orders_df['Cluster_Label'] = orders_df['Cluster'].map(cluster_label_map)
ordered_labels = ["Most Profitable", "2nd Most Profitable", "3rd Most Profitable", "Least Profitable"]
cluster_data['Cluster_Label'] = pd.Categorical(cluster_data['Cluster_Label'], categories=ordered_labels, ordered=True)
orders_df['Cluster_Label'] = pd.Categorical(orders_df['Cluster_Label'], categories=ordered_labels, ordered=True)

# --------------------------------------------------
# 3. Visualize Clusters (Scatter Plot with Descriptive Legend)
# --------------------------------------------------
plt.figure(figsize=(10, 6))
custom_palette = {
    "Most Profitable": "green",
    "2nd Most Profitable": "yellow",
    "3rd Most Profitable": "orange",
    "Least Profitable": "red"
}
sns.scatterplot(
    data=cluster_data, 
    x='Sales', 
    y='Profit', 
    hue='Cluster_Label', 
    palette=custom_palette, 
    alpha=0.7
)
plt.xlabel('Sales')
plt.ylabel('Profit')
plt.title('Cluster Analysis of Sales vs Profit')
plt.legend(title="Profitability Rank")
plt.savefig('static/clusterwise_correlation/cluster_analysis.png')
plt.close()

# --------------------------------------------------
# 4. Cluster-wise Analysis: Heatmap, Scatter Plots with Regression, and Category Analysis
# --------------------------------------------------
print("Performing cluster-wise correlation analysis and scatter plot regression...")

# Compute total profit for each cluster and sort in descending order
cluster_profitability = orders_df.groupby("Cluster")["Profit"].sum().sort_values(ascending=False)
sorted_clusters = cluster_profitability.index.tolist()

# List of variable pairs for scatter plots
pairs = [
    ('Sales', 'Profit'),
]

# Iterate over clusters in order of profitability ranking
for i, cl in enumerate(sorted_clusters, start=1):
    # Subset the dataframe for the current cluster
    cluster_df = orders_df[orders_df['Cluster'] == cl]
    
    # --------------------------
    # Cluster-wise Correlation Heatmap
    # --------------------------
    correlation_matrix_cluster = cluster_df[['Sales', 'Profit']].corr()
    
    plt.figure(figsize=(8, 5))
    sns.heatmap(correlation_matrix_cluster, annot=True, cmap="coolwarm", linewidths=0.5)
    plt.title(f"Sales vs. Profit Correlation Heatmap of the {cluster_label_map[cl]} Cluster")
    plt.savefig(f'static/clusterwise_correlation/correlation_heatmap_cluster_{i}.png')
    plt.close()
    print("-" * 100)
    
    # --------------------------
    # Scatter Plots with Linear Regression for Each Pair
    # --------------------------
    for x_col, y_col in pairs:
        plt.figure(figsize=(8, 5))
        # Using regplot to plot scatter and regression line
        sns.regplot(x=x_col, y=y_col, data=cluster_df, scatter_kws={'alpha':0.7}, line_kws={'color':'blue'})
        
        # Compute regression coefficients using numpy's polyfit
        x = cluster_df[x_col]
        y = cluster_df[y_col]
        slope, intercept = np.polyfit(x, y, 1)
        
        plt.title(f"{x_col} vs. {y_col} for {cluster_label_map[cl]} Cluster\n" f"Regression Line: y = {slope:.2f}x + {intercept:.2f}")
        plt.xlabel(x_col)
        plt.ylabel(y_col)
        plt.savefig(f'static/clusterwise_correlation/liner_regression_cluster_{i}.png')
        plt.close()
    
    print("-" * 100)
    
    # --------------------------
    # Cluster-based Category Analysis
    # --------------------------
    print(f"Cluster: {cluster_label_map[cl]}")
    # Aggregate total Profit and Sales by Category in this cluster
    category_summary = cluster_df.groupby('Category').agg({'Profit': 'sum', 'Sales': 'sum'}).reset_index()
    
    # Identify most/least profitable categories based on total profit
    most_profitable_category = category_summary.loc[category_summary['Profit'].idxmax()]
    least_profitable_category = category_summary.loc[category_summary['Profit'].idxmin()]
    
    # Identify most/least sold categories based on total sales
    most_selled_category = category_summary.loc[category_summary['Sales'].idxmax()]
    least_selled_category = category_summary.loc[category_summary['Sales'].idxmin()]
    
    # For each identified category, find a representative product within that cluster.
    rep_most_profitable_product = cluster_df[cluster_df['Category'] == most_profitable_category['Category']].loc[cluster_df[cluster_df['Category'] == most_profitable_category['Category']]['Profit'].idxmax()]
    rep_least_profitable_product = cluster_df[cluster_df['Category'] == least_profitable_category['Category']].loc[cluster_df[cluster_df['Category'] == least_profitable_category['Category']]['Profit'].idxmin()]
    
    rep_most_selled_product = cluster_df[cluster_df['Category'] == most_selled_category['Category']].loc[cluster_df[cluster_df['Category'] == most_selled_category['Category']]['Sales'].idxmax()]
    rep_least_selled_product = cluster_df[cluster_df['Category'] == least_selled_category['Category']].loc[cluster_df[cluster_df['Category'] == least_selled_category['Category']]['Sales'].idxmin()]
    
    # Print the results for this cluster
    print(f"Most Profitable Category..: {most_profitable_category['Category']} (Total Profit: {most_profitable_category['Profit']})")
    print(f"  Representative Product..: {rep_most_profitable_product['Product Name']} " f"(Profit: {rep_most_profitable_product['Profit']}, Location: {rep_most_profitable_product['City']}, {rep_most_profitable_product['State']})")
    
    print(f"Least Profitable Category.: {least_profitable_category['Category']} (Total Profit: {least_profitable_category['Profit']})")
    print(f"  Representative Product..: {rep_least_profitable_product['Product Name']} " f"(Profit: {rep_least_profitable_product['Profit']}, Location: {rep_least_profitable_product['City']}, {rep_least_profitable_product['State']})")
    
    print(f"Most Sold Category........: {most_selled_category['Category']} (Total Sales: {most_selled_category['Sales']})")
    print(f"  Representative Product..: {rep_most_selled_product['Product Name']} " f"(Sales: {rep_most_selled_product['Sales']}, Location: {rep_most_selled_product['City']}, {rep_most_selled_product['State']})")
    
    print(f"Least Sold Category.......: {least_selled_category['Category']} (Total Sales: {least_selled_category['Sales']})")
    print(f"  Representative Product..: {rep_least_selled_product['Product Name']} " f"(Sales: {rep_least_selled_product['Sales']}, Location: {rep_least_selled_product['City']}, {rep_least_selled_product['State']})")
    
    print("-" * 100)

# ---------------------------
# 5. Prepare Data for Geographic Maps (Separate for Profit and Sales)
# ---------------------------

# Define coordinates for major Indian cities (add or modify as needed)
city_coords = {
    "Mumbai": (19.0760, 72.8777),
    "Delhi": (28.7041, 77.1025),
    "Bengaluru": (12.9716, 77.5946),
    "Hyderabad": (17.3850, 78.4867),
    "Chennai": (13.0827, 80.2707),
    "Kolkata": (22.5726, 88.3639),
    "Surat": (21.1702, 72.8311),
    "Pune": (18.5204, 73.8567),
    "Kanpur": (26.4499, 80.3319),
    "Goa": (15.4909, 73.8278),
    "Nashik": (19.9975, 73.7898)
}

def get_coords(city):
    return city_coords.get(city, (22.5937, 78.9629))

# Function to jitter coordinates if markers overlap.
def jitter_coords(coords, count, jitter_amount=0.001):
    # Increase latitude and decrease longitude slightly based on count
    return (coords[0] + jitter_amount * count, coords[1] - jitter_amount * count)

# Create two separate lists of marker info: one for profit and one for sales.
profit_markers = []
sales_markers = []

# Iterate over clusters (using sorted_clusters, which is ordered by profitability)
for cl in sorted_clusters:
    # Subset for current cluster
    cluster_df = orders_df[orders_df['Cluster'] == cl]
    
    # Aggregate total Profit and Sales by Category in this cluster
    category_summary = cluster_df.groupby('Category').agg({'Profit': 'sum', 'Sales': 'sum'}).reset_index()
    
    # For Profit: Identify the categories with highest and lowest total profit.
    most_profitable_category = category_summary.loc[category_summary['Profit'].idxmax()]
    least_profitable_category = category_summary.loc[category_summary['Profit'].idxmin()]
    
    # For Sales: Identify the categories with highest and lowest total sales.
    most_selled_category = category_summary.loc[category_summary['Sales'].idxmax()]
    least_selled_category = category_summary.loc[category_summary['Sales'].idxmin()]
    
    # For each identified category, select a representative product within that cluster.
    rep_most_profitable_product = cluster_df[cluster_df['Category'] == most_profitable_category['Category']].loc[cluster_df[cluster_df['Category'] == most_profitable_category['Category']]['Profit'].idxmax()]
    rep_least_profitable_product = cluster_df[cluster_df['Category'] == least_profitable_category['Category']].loc[cluster_df[cluster_df['Category'] == least_profitable_category['Category']]['Profit'].idxmin()]
    
    rep_most_selled_product = cluster_df[cluster_df['Category'] == most_selled_category['Category']].loc[cluster_df[cluster_df['Category'] == most_selled_category['Category']]['Sales'].idxmax()]
    rep_least_selled_product = cluster_df[cluster_df['Category'] == least_selled_category['Category']].loc[cluster_df[cluster_df['Category'] == least_selled_category['Category']]['Sales'].idxmin()]
    
    # Append profit markers (green for most profitable, red for least profitable)
    profit_markers.append({
        'label': 'Most Profitable',
        'color': 'green',
        'product_name': rep_most_profitable_product['Product Name'],
        'profit': rep_most_profitable_product['Profit'],
        'city': rep_most_profitable_product['City'],
        'state': rep_most_profitable_product['State']
    })
    profit_markers.append({
        'label': 'Least Profitable',
        'color': 'red',
        'product_name': rep_least_profitable_product['Product Name'],
        'profit': rep_least_profitable_product['Profit'],
        'city': rep_least_profitable_product['City'],
        'state': rep_least_profitable_product['State']
    })
    
    # Append sales markers (green for most sold, red for least sold)
    sales_markers.append({
        'label': 'Most Sold',
        'color': 'green',
        'product_name': rep_most_selled_product['Product Name'],
        'sales': rep_most_selled_product['Sales'],
        'city': rep_most_selled_product['City'],
        'state': rep_most_selled_product['State']
    })
    sales_markers.append({
        'label': 'Least Sold',
        'color': 'red',
        'product_name': rep_least_selled_product['Product Name'],
        'sales': rep_least_selled_product['Sales'],
        'city': rep_least_selled_product['City'],
        'state': rep_least_selled_product['State']
    })

# ---------------------------
# 6. Create Two Separate Interactive Maps with Marker Clustering & Jitter
# ---------------------------

# For profit markers, maintain a dictionary to count markers per base coordinate.
profit_coords_count = {}

profit_map = folium.Map(location=[22.5937, 78.9629], zoom_start=5, tiles="CartoDB positron")
profit_cluster = MarkerCluster().add_to(profit_map)

for marker in profit_markers:
    base_coords = get_coords(marker['city'])
    # Get current count for these base coordinates and then update count.
    count = profit_coords_count.get(base_coords, 0)
    if count > 0:
        new_coords = jitter_coords(base_coords, count)
    else:
        new_coords = base_coords
    profit_coords_count[base_coords] = count + 1
    
    tooltip = (
        f"<b>{marker['label']}</b><br>"
        f"Product: {marker['product_name']}<br>"
        f"City: {marker['city']}, {marker['state']}<br>"
        f"Profit: {marker['profit']}"
    )
    folium.CircleMarker(
        location=new_coords,
        radius=6,
        color=marker['color'],
        fill=True,
        fill_color=marker['color'],
        tooltip=tooltip
    ).add_to(profit_cluster)

# Save and display the profit map
profit_map.save("static/maps/profit_map.html")

# For sales markers, maintain a separate dictionary to count markers per base coordinate.
sales_coords_count = {}

sales_map = folium.Map(location=[22.5937, 78.9629], zoom_start=5, tiles="CartoDB positron")
sales_cluster = MarkerCluster().add_to(sales_map)

for marker in sales_markers:
    base_coords = get_coords(marker['city'])
    count = sales_coords_count.get(base_coords, 0)
    if count > 0:
        new_coords = jitter_coords(base_coords, count)
    else:
        new_coords = base_coords
    sales_coords_count[base_coords] = count + 1
    
    tooltip = (
        f"<b>{marker['label']}</b><br>"
        f"Product: {marker['product_name']}<br>"
        f"City: {marker['city']}, {marker['state']}<br>"
        f"Sales: {marker['sales']}"
    )
    folium.CircleMarker(
        location=new_coords,
        radius=6,
        color=marker['color'],
        fill=True,
        fill_color=marker['color'],
        tooltip=tooltip
    ).add_to(sales_cluster)

# Save and display the sales map
sales_map.save("static/maps/sales_map.html")

print("Geographic Distribution of Most and Least Profitable Products: ")
display(profit_map)
print("-" * 100)
print("Geographic Distribution of Most and Least Sold Products: ")
display(sales_map)
