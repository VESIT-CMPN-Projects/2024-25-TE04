import pandas as pd
import matplotlib.pyplot as plt
from statsmodels.tsa.statespace.sarimax import SARIMAX
import warnings
warnings.filterwarnings("ignore")

# ======================
# 1. Data Loading
# ======================
df = pd.read_excel('Assets/Dataset/Superstore 3.0.xlsx', parse_dates=['Order Date'])
df.sort_values('Order Date', inplace=True)

# ======================
# 2. Forecast Function
# ======================
def forecast_metric(data, metric, forecast_periods=12, order=(1,1,1), seasonal_order=(1,1,1,12)):
    """
    1) Resamples the data monthly,
    2) Fits a SARIMAX model,
    3) Forecasts future_periods months.
    Returns: historical_ts, forecast_series, confidence_intervals
    """
    # Aggregate data to monthly totals
    ts = data.resample('M', on='Order Date')[metric].sum()
    
    # Fit SARIMAX (tune order/seasonal_order as needed)
    model = SARIMAX(ts, order=order, seasonal_order=seasonal_order, enforce_stationarity=False, enforce_invertibility=False)
    model_fit = model.fit(disp=False)
    
    # Forecast the next forecast_periods months
    forecast_obj = model_fit.get_forecast(steps=forecast_periods)
    forecast_index = pd.date_range(start=ts.index[-1] + pd.offsets.MonthBegin(1), periods=forecast_periods, freq='M')
    forecast_series = pd.Series(forecast_obj.predicted_mean.values, index=forecast_index)
    
    # Confidence intervals
    conf_int = forecast_obj.conf_int()
    conf_int.index = forecast_index
    
    return ts, forecast_series, conf_int

# ======================
# 3. Filtering the Data
# ======================
product_name = "Hon Deluxe Fabric Upholstered Stacking Chairs, Rounded Back"
sub_category_name = "Bookcases"
category_name = "Furniture"

df_product = df[df["Product Name"] == product_name]
df_subcat  = df[df["Sub-Category"] == sub_category_name]
df_cat     = df[df["Category"] == category_name]

# ======================
# 4. Generating Forecasts
# ======================
# Product
ts_prod_sales, forecast_prod_sales, conf_prod_sales = forecast_metric(df_product, 'Sales')
ts_prod_profit, forecast_prod_profit, conf_prod_profit = forecast_metric(df_product, 'Profit')

# Sub-Category
ts_subcat_sales, forecast_subcat_sales, conf_subcat_sales = forecast_metric(df_subcat, 'Sales')
ts_subcat_profit, forecast_subcat_profit, conf_subcat_profit = forecast_metric(df_subcat, 'Profit')

# Category
ts_cat_sales, forecast_cat_sales, conf_cat_sales = forecast_metric(df_cat, 'Sales')
ts_cat_profit, forecast_cat_profit, conf_cat_profit = forecast_metric(df_cat, 'Profit')

# ======================
# 5. Plotting Functions
# ======================
def plot_historical_and_forecast(ts, forecast_series, conf_int, title):
    """
    Plots the historical time series + the forecast + confidence interval.
    """
    plt.figure(figsize=(12,6))
    plt.plot(ts, label='Historical', marker='o')
    plt.plot(forecast_series, label='Forecast', color='red', marker='o')
    plt.fill_between(conf_int.index, conf_int.iloc[:,0], conf_int.iloc[:,1], color='pink', alpha=0.3, label='Confidence Interval')
    plt.title(title)
    plt.xlabel('Date')
    plt.ylabel('Value')
    plt.legend()
    plt.show()

def plot_forecast_only(forecast_series, conf_int, title, ylabel='Value'):
    """
    Plots ONLY the future forecast (12 months) and its confidence interval.
    The x-axis will be labeled with the month-year (e.g., Jan-2018, Feb-2018, etc.).
    """
    plt.figure(figsize=(12,6))
    plt.plot(forecast_series.index, forecast_series.values, label='Forecast', color='red', marker='o')
    plt.fill_between(conf_int.index, conf_int.iloc[:,0], conf_int.iloc[:,1], color='pink', alpha=0.3, label='Confidence Interval')
    
    # Format the x-axis ticks as Month-Year
    months = [date.strftime('%b-%Y') for date in forecast_series.index]
    plt.xticks(forecast_series.index, months, rotation=45)
    
    plt.title(title)
    plt.xlabel('Month')
    plt.ylabel(ylabel)
    plt.legend()
    plt.tight_layout()
    plt.show()

# ======================
# 6. Plot Historical + Future
# ======================
# Product-level
plot_historical_and_forecast(ts_prod_sales, forecast_prod_sales, conf_prod_sales, f'{product_name} - Sales Forecast for 2018')
plot_historical_and_forecast(ts_prod_profit, forecast_prod_profit, conf_prod_profit, f'{product_name} - Profit Forecast for 2018')

# Sub-category-level
plot_historical_and_forecast(ts_subcat_sales, forecast_subcat_sales, conf_subcat_sales, f'{sub_category_name} - Sales Forecast for 2018')
plot_historical_and_forecast(ts_subcat_profit, forecast_subcat_profit, conf_subcat_profit, f'{sub_category_name} - Profit Forecast for 2018')

# Category-level
plot_historical_and_forecast(ts_cat_sales, forecast_cat_sales, conf_cat_sales, f'{category_name} - Sales Forecast for 2018')
plot_historical_and_forecast(ts_cat_profit, forecast_cat_profit, conf_cat_profit, f'{category_name} - Profit Forecast for 2018')

# ======================
# 7. Plot Future-Only (Optional)
# ======================
# Product-level (Future Only)
plot_forecast_only(forecast_prod_sales, conf_prod_sales, f'{product_name} - Sales Forecast (Future Only)', 'Sales')
plot_forecast_only(forecast_prod_profit, conf_prod_profit, f'{product_name} - Profit Forecast (Future Only)', 'Profit')

# Sub-category-level (Future Only)
plot_forecast_only(forecast_subcat_sales, conf_subcat_sales, f'{sub_category_name} - Sales Forecast (Future Only)', 'Sales')
plot_forecast_only(forecast_subcat_profit, conf_subcat_profit, f'{sub_category_name} - Profit Forecast (Future Only)', 'Profit')

# Category-level (Future Only)
plot_forecast_only(forecast_cat_sales, conf_cat_sales, f'{category_name} - Sales Forecast (Future Only)', 'Sales')
plot_forecast_only(forecast_cat_profit, conf_cat_profit, f'{category_name} - Profit Forecast (Future Only)', 'Profit')