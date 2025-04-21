import os
import re
import matplotlib
import matplotlib.pyplot as plt
import plotly.graph_objects as go
import pandas as pd
import pickle
import scipy.cluster.hierarchy as sch
import seaborn as sns
import numpy as np
import folium
import warnings
import logging
from google.oauth2 import id_token
from google.auth.transport import requests
matplotlib.use('Agg')
from prophet import Prophet
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from statsmodels.tsa.statespace.sarimax import SARIMAX
from flask import Flask, request, jsonify, send_file
from sklearn.cluster import KMeans
from sklearn.linear_model import LinearRegression
from sklearn.preprocessing import StandardScaler
from folium.plugins import MarkerCluster
from IPython.display import display

warnings.filterwarnings("ignore")

# Suppress Pandas SettingWithCopyWarning
warnings.simplefilter("ignore", category=pd.errors.SettingWithCopyWarning)

# Suppress cmdstanpy warnings (only show errors)
logging.getLogger("cmdstanpy").setLevel(logging.ERROR)

app = Flask(__name__)

CLIENT_ID = "450726384001-l075m6t166ijfc1mb5ufhnppklni8su0.apps.googleusercontent.com"

lr_folder = os.path.join('static', 'linear_regression')
os.makedirs(lr_folder, exist_ok=True)

# Functions for Linear Regression forecast route {START}
def overall_aggregated_view(data):
    cat_sub_stats = data.groupby(['Category', 'Sub-Category']).agg({
        'Sales': 'sum',
        'Profit': 'sum',
        'Quantity': 'sum',
        'Discount': 'mean',
        'Order ID': 'count'
    }).rename(columns={'Order ID': 'Order Count'}).reset_index()
    
    cat_sub_stats['Profit Margin (%)'] = (cat_sub_stats['Profit'] / cat_sub_stats['Sales']) * 100
    
    # Save overall aggregated plot using FacetGrid inside static/linear_regression
    overall_plot_path = os.path.join(lr_folder, 'overall_view.png')
    g = sns.FacetGrid(cat_sub_stats, col="Category", sharex=False, sharey=False, height=4)
    g.map_dataframe(sns.barplot, x='Sub-Category', y='Sales', palette="coolwarm")
    g.set_titles("{col_name} Category")
    for ax in g.axes.flatten():
        ax.tick_params(labelrotation=45)
    plt.tight_layout()
    plt.savefig(overall_plot_path)
    plt.close()
    
    return {
        'aggregated_metrics': cat_sub_stats.to_dict(orient='records'),
        'overall_view_image': overall_plot_path
    }

def forecast_sales_profit_file(data, filter_col, filter_val, future_years_count=5):
    """
    Forecasts Sales and Profit for a given Category/Sub-Category and saves plots.
    Returns a dict with historical data, forecast data, and image paths.
    """
    filtered_data = data[data[filter_col] == filter_val].copy()
    if filtered_data.empty:
        return None
    # Aggregate yearly Sales and Profit
    yearly_data = filtered_data.groupby('Year').agg({'Sales': 'sum', 'Profit': 'sum'}).reset_index()
    
    # Build linear regression models for Sales and Profit
    X = yearly_data[['Year']]
    y_sales = yearly_data['Sales']
    y_profit = yearly_data['Profit']
    
    model_sales = LinearRegression()
    model_sales.fit(X, y_sales)
    model_profit = LinearRegression()
    model_profit.fit(X, y_profit)
    
    # Forecast for future years
    last_year = yearly_data['Year'].max()
    future_years = np.arange(last_year + 1, last_year + 1 + future_years_count).reshape(-1, 1)
    predicted_sales = model_sales.predict(future_years)
    predicted_profit = model_profit.predict(future_years)
    
    future_df = pd.DataFrame({
        'Year': future_years.flatten(),
        'Predicted Sales': predicted_sales,
        'Predicted Profit': predicted_profit
    })
    
    # Save Sales forecast plot
    sales_plot_path = os.path.join(lr_folder, f'sales_forecast_{filter_val}.png')
    plt.figure(figsize=(10, 5))
    plt.plot(yearly_data['Year'], yearly_data['Sales'], marker='o', label='Historical Sales')
    plt.plot(future_df['Year'], future_df['Predicted Sales'], marker='o', linestyle='--', label='Predicted Sales')
    plt.xlabel('Year')
    plt.ylabel('Sales')
    plt.title(f'Sales Forecast for {filter_col}: {filter_val}')
    plt.legend()
    plt.tight_layout()
    plt.savefig(sales_plot_path)
    plt.close()
    
    # Save Profit forecast plot
    profit_plot_path = os.path.join(lr_folder, f'profit_forecast_{filter_val}.png')
    plt.figure(figsize=(10, 5))
    plt.plot(yearly_data['Year'], yearly_data['Profit'], marker='o', label='Historical Profit')
    plt.plot(future_df['Year'], future_df['Predicted Profit'], marker='o', linestyle='--', label='Predicted Profit')
    plt.xlabel('Year')
    plt.ylabel('Profit')
    plt.title(f'Profit Forecast for {filter_col}: {filter_val}')
    plt.legend()
    plt.tight_layout()
    plt.savefig(profit_plot_path)
    plt.close()
    
    return {
        'historical_data': yearly_data.to_dict(orient='records'),
        'forecast_data': future_df.to_dict(orient='records'),
        'sales_forecast_image': sales_plot_path,
        'profit_forecast_image': profit_plot_path
    }

def recommend_products_data(data, filter_col, filter_val, top_n=5):
    """
    Aggregates product performance for a given Category/Sub-Category and returns the top recommended products.
    """
    if 'Product Name' not in data.columns:
        return None
    filtered_data = data[data[filter_col] == filter_val].copy()
    if filtered_data.empty:
        return None
    # Build the aggregation dictionary.
    # If the DataFrame has a 'Product ID' column, include it.
    agg_dict = {
        'Sales': 'sum',
        'Profit': 'sum'
    }
    if 'Product ID' in filtered_data.columns:
        agg_dict['Product ID'] = 'first'  # assumes that each product name maps to a unique product ID

    product_perf = filtered_data.groupby('Product Name').agg(agg_dict).reset_index()
    product_perf['Profit Margin (%)'] = np.where(
        product_perf['Sales'] != 0,
        (product_perf['Profit'] / product_perf['Sales']) * 100,
        0
    )
    recommended_products = product_perf.sort_values(by=['Profit', 'Profit Margin (%)'], ascending=False)
    top_recommendations = recommended_products.head(top_n)
    return top_recommendations.to_dict(orient='records')

def forecast_product_sales_profit_file(data, sub_category, product_name, future_years_count=5):
    """
    Forecasts Sales and Profit for a specific product under a given Sub-Category and saves plots.
    Returns a dict with forecast details and image paths.
    """
    filtered_data = data[(data['Sub-Category'] == sub_category) & (data['Product Name'] == product_name)].copy()
    if filtered_data.empty:
        return None
    yearly_data = filtered_data.groupby('Year').agg({'Sales': 'sum', 'Profit': 'sum'}).reset_index()
    
    X = yearly_data[['Year']]
    y_sales = yearly_data['Sales']
    y_profit = yearly_data['Profit']
    
    model_sales = LinearRegression()
    model_sales.fit(X, y_sales)
    model_profit = LinearRegression()
    model_profit.fit(X, y_profit)
    
    last_year = yearly_data['Year'].max()
    future_years = np.arange(last_year + 1, last_year + 1 + future_years_count).reshape(-1, 1)
    predicted_sales = model_sales.predict(future_years)
    predicted_profit = model_profit.predict(future_years)
    
    future_df = pd.DataFrame({
        'Year': future_years.flatten(),
        'Predicted Sales': predicted_sales,
        'Predicted Profit': predicted_profit
    })
    
    # Sanitize product name for filename: replace spaces with underscores and remove non-alphanumeric characters
    sanitized_product_name = re.sub(r'[^A-Za-z0-9_]', '', product_name.replace(' ', '_'))
    
    # Save product Sales forecast plot
    product_sales_plot_path = os.path.join(lr_folder, f'sales_forecast_{sanitized_product_name}.png')
    plt.figure(figsize=(10, 5))
    plt.plot(yearly_data['Year'], yearly_data['Sales'], marker='o', label='Historical Sales')
    plt.plot(future_df['Year'], future_df['Predicted Sales'], marker='o', linestyle='--', label='Predicted Sales')
    plt.xlabel('Year')
    plt.ylabel('Sales')
    plt.title(f'Sales Forecast for Product: {product_name}')
    plt.legend()
    plt.tight_layout()
    plt.savefig(product_sales_plot_path)
    plt.close()
    
    # Save product Profit forecast plot
    product_profit_plot_path = os.path.join(lr_folder, f'profit_forecast_{sanitized_product_name}.png')
    plt.figure(figsize=(10, 5))
    plt.plot(yearly_data['Year'], yearly_data['Profit'], marker='o', label='Historical Profit')
    plt.plot(future_df['Year'], future_df['Predicted Profit'], marker='o', linestyle='--', label='Predicted Profit')
    plt.xlabel('Year')
    plt.ylabel('Profit')
    plt.title(f'Profit Forecast for Product: {product_name}')
    plt.legend()
    plt.tight_layout()
    plt.savefig(product_profit_plot_path)
    plt.close()
    
    return {
        'historical_data': yearly_data.to_dict(orient='records'),
        'forecast_data': future_df.to_dict(orient='records'),
        'sales_forecast_image': product_sales_plot_path,
        'profit_forecast_image': product_profit_plot_path
    }

# Functions for Linear Regression forecast route {END}

# Functions for SARIMAX forecast route {START}
# -------------------------------
# 1. Forecasting Function (SARIMAX)
# -------------------------------
def forecast_metric(data, metric, forecast_periods=12, order=(1,1,1), seasonal_order=(1,1,1,12)):
    """
    Resamples the data monthly, fits a SARIMAX model, and forecasts
    future periods for the given metric.
    Returns: historical time series, forecast series, and confidence intervals.
    """
    ts = data.resample('M', on='Order Date')[metric].sum()
    
    model = SARIMAX(ts, order=order, seasonal_order=seasonal_order, enforce_stationarity=False, enforce_invertibility=False)
    model_fit = model.fit(disp=False)
    
    forecast_obj = model_fit.get_forecast(steps=forecast_periods)
    forecast_index = pd.date_range(start=ts.index[-1] + pd.offsets.MonthBegin(1), periods=forecast_periods, freq='M')
    forecast_series = pd.Series(forecast_obj.predicted_mean.values, index=forecast_index)
    
    conf_int = forecast_obj.conf_int()
    conf_int.index = forecast_index
    
    return ts, forecast_series, conf_int

# -------------------------------
# 2. Forecast SARIMAX for a Given Filter
# -------------------------------
def forecast_sarimax_file(data, filter_col, filter_val, forecast_periods=12):
    """
    Filters the data based on the specified column and value,
    then forecasts Sales and Profit using SARIMAX.
    Saves forecast plots to disk and returns forecast data.
    """
    filtered_data = data[data[filter_col] == filter_val].copy()
    if filtered_data.empty:
        return None

    # Forecast Sales and Profit
    ts_sales, forecast_sales, conf_sales = forecast_metric(filtered_data, 'Sales', forecast_periods)
    ts_profit, forecast_profit, conf_profit = forecast_metric(filtered_data, 'Profit', forecast_periods)
    
    # Ensure folder exists to save plots
    plot_folder = "static/SARIMAX"
    os.makedirs(plot_folder, exist_ok=True)
    
    # Sanitize product name for filename: replace spaces with underscores and remove non-alphanumeric characters
    sanitized_filter_val = re.sub(r'[^A-Za-z0-9_]', '', filter_val.replace(' ', '_'))
    
    # ---------------------------
    # Save Sales Historical+Forecast Plot
    # ---------------------------
    sales_plot_path = os.path.join(plot_folder, f'sales_forecast_{sanitized_filter_val}.png')
    plt.figure(figsize=(12,6))
    plt.plot(ts_sales, label='Historical Sales', marker='o')
    plt.plot(forecast_sales, label='Forecast Sales', color='red', marker='o')
    plt.fill_between(conf_sales.index, conf_sales.iloc[:,0], conf_sales.iloc[:,1], color='pink', alpha=0.3, label='Confidence Interval')
    plt.title(f'{filter_val} - Sales Forecast')
    plt.xlabel('Date')
    plt.ylabel('Sales')
    plt.legend()
    plt.tight_layout()
    plt.savefig(sales_plot_path)
    plt.close()

    # ---------------------------
    # Save Profit Historical+Forecast Plot
    # ---------------------------
    profit_plot_path = os.path.join(plot_folder, f'profit_forecast_{sanitized_filter_val}.png')
    plt.figure(figsize=(12,6))
    plt.plot(ts_profit, label='Historical Profit', marker='o')
    plt.plot(forecast_profit, label='Forecast Profit', color='red', marker='o')
    plt.fill_between(conf_profit.index, conf_profit.iloc[:,0], conf_profit.iloc[:,1], color='pink', alpha=0.3, label='Confidence Interval')
    plt.title(f'{filter_val} - Profit Forecast')
    plt.xlabel('Date')
    plt.ylabel('Profit')
    plt.legend()
    plt.tight_layout()
    plt.savefig(profit_plot_path)
    plt.close()
    
    # ---------------------------
    # Plot Forecast-Only for Sales
    # ---------------------------
    sales_forecast_only_path = os.path.join(plot_folder, f'sales_forecast_only_{sanitized_filter_val}.png')
    plt.figure(figsize=(12,6))
    plt.plot(forecast_sales.index, forecast_sales.values, label='Forecast Sales', color='red', marker='o')
    plt.fill_between(conf_sales.index, conf_sales.iloc[:,0], conf_sales.iloc[:,1],
                     color='pink', alpha=0.3, label='Confidence Interval')
    months = [date.strftime('%b-%Y') for date in forecast_sales.index]
    plt.xticks(forecast_sales.index, months, rotation=45)
    plt.title(f'{filter_val} - Sales Forecast (Future Only)')
    plt.xlabel('Month')
    plt.ylabel('Sales')
    plt.legend()
    plt.tight_layout()
    plt.savefig(sales_forecast_only_path)
    plt.close()
    
    # ---------------------------
    # Plot Forecast-Only for Profit
    # ---------------------------
    profit_forecast_only_path = os.path.join(plot_folder, f'profit_forecast_only_{sanitized_filter_val}.png')
    plt.figure(figsize=(12,6))
    plt.plot(forecast_profit.index, forecast_profit.values, label='Forecast Profit', color='red', marker='o')
    plt.fill_between(conf_profit.index, conf_profit.iloc[:,0], conf_profit.iloc[:,1],
                     color='pink', alpha=0.3, label='Confidence Interval')
    months = [date.strftime('%b-%Y') for date in forecast_profit.index]
    plt.xticks(forecast_profit.index, months, rotation=45)
    plt.title(f'{filter_val} - Profit Forecast (Future Only)')
    plt.xlabel('Month')
    plt.ylabel('Profit')
    plt.legend()
    plt.tight_layout()
    plt.savefig(profit_forecast_only_path)
    plt.close()

    # Format historical and forecast data for Sales
    historical_sales = ts_sales.reset_index().to_dict(orient='records')
    forecast_sales_data = pd.DataFrame({
         'Date': forecast_sales.index,
         'Forecast Sales': forecast_sales.values,
         'Lower CI': conf_sales.iloc[:,0].values,
         'Upper CI': conf_sales.iloc[:,1].values
    }).to_dict(orient='records')

    # Format historical and forecast data for Profit
    historical_profit = ts_profit.reset_index().to_dict(orient='records')
    forecast_profit_data = pd.DataFrame({
         'Date': forecast_profit.index,
         'Forecast Profit': forecast_profit.values,
         'Lower CI': conf_profit.iloc[:,0].values,
         'Upper CI': conf_profit.iloc[:,1].values
    }).to_dict(orient='records')

    return {
         'sales': {
             'historical': historical_sales,
             'forecast': forecast_sales_data,
             'forecast_image': sales_plot_path,
             'forecast_only_image': sales_forecast_only_path
         },
         'profit': {
             'historical': historical_profit,
             'forecast': forecast_profit_data,
             'forecast_image': profit_plot_path,
             'forecast_only_image': profit_forecast_only_path
         }
    }

# Functions for SARIMAX forecast route {END}

# Functions for PROPHET forecast route {START}
def compute_metrics(actual, predicted):
    mae = mean_absolute_error(actual, predicted)
    rmse = np.sqrt(mean_squared_error(actual, predicted))
    r2 = r2_score(actual, predicted)
    return mae, rmse, r2

def forecast_prophet_file(df, filter_col, user_value):
    # Filter the data based on the provided column and value
    filtered_df = df[df[filter_col] == user_value]
    if filtered_df.empty:
        return None

    # Sort and aggregate data monthly (using month end frequency)
    filtered_df.sort_values('Order Date', inplace=True)
    monthly_agg = filtered_df.resample('ME', on='Order Date').agg({'Sales': 'sum', 'Profit': 'sum'}).reset_index()
    
    # Prepare data for Sales and Profit forecasts
    monthly_sales = monthly_agg[['Order Date', 'Sales']].rename(columns={'Order Date': 'ds', 'Sales': 'y'})
    monthly_profit = monthly_agg[['Order Date', 'Profit']].rename(columns={'Order Date': 'ds', 'Profit': 'y'})
    
    forecast_periods = 12
    if len(monthly_sales) < 2 * forecast_periods:
        print("Warning: Not enough data for a robust 12-month train/test split. Proceeding anyway...")
    
    #---------------
    # Sales Forecast
    #---------------
    # Train/Test Split
    train_sales = monthly_sales.iloc[:-forecast_periods]
    test_sales = monthly_sales.iloc[-forecast_periods:]
    
    # Train model on Sales
    model_sales = Prophet(yearly_seasonality=True, daily_seasonality=True, weekly_seasonality=False)
    model_sales.fit(train_sales)
    future_sales = model_sales.make_future_dataframe(periods=forecast_periods, freq='ME')
    forecast_sales = model_sales.predict(future_sales)
    
    # Evaluation on train/test splits
    train_sales_forecast = forecast_sales[forecast_sales['ds'].isin(train_sales['ds'])]
    test_sales_forecast = forecast_sales[forecast_sales['ds'].isin(test_sales['ds'])]
    
    sales_train_actual = train_sales.set_index('ds')['y']
    sales_train_pred = train_sales_forecast.set_index('ds')['yhat']
    sales_test_actual = test_sales.set_index('ds')['y']
    sales_test_pred = test_sales_forecast.set_index('ds')['yhat']
    
    mae_sales_train, rmse_sales_train, r2_sales_train = compute_metrics(sales_train_actual, sales_train_pred)
    mae_sales_test, rmse_sales_test, r2_sales_test = compute_metrics(sales_test_actual, sales_test_pred)
    
    # Final model using entire Sales data (for plotting)
    model_sales_full = Prophet(yearly_seasonality=True, daily_seasonality=False, weekly_seasonality=False)
    model_sales_full.fit(monthly_sales)
    future_sales_full = model_sales_full.make_future_dataframe(periods=forecast_periods, freq='ME')
    forecast_sales_full = model_sales_full.predict(future_sales_full)
    
    last_date_sales = monthly_sales['ds'].max()
    historical_sales = monthly_sales.copy()
    future_sales_forecast = forecast_sales_full[forecast_sales_full['ds'] > last_date_sales]
    
    # Build Sales Plotly Figure
    fig_sales = go.Figure()
    fig_sales.add_trace(go.Scatter(
        x=historical_sales['ds'],
        y=historical_sales['y'],
        mode='lines+markers',
        line=dict(color='blue', width=2),
        marker=dict(color='blue', size=6),
        name='Historical Sales'
    ))
    fig_sales.add_trace(go.Scatter(
        x=future_sales_forecast['ds'],
        y=future_sales_forecast['yhat'],
        mode='lines+markers',
        line=dict(color='red', width=2),
        marker=dict(color='red', size=6),
        name='Forecast Sales'
    ))
    ci_x_sales = pd.concat([future_sales_forecast['ds'], future_sales_forecast['ds'][::-1]])
    ci_y_sales = pd.concat([future_sales_forecast['yhat_upper'], future_sales_forecast['yhat_lower'][::-1]])
    fig_sales.add_trace(go.Scatter(
        x=ci_x_sales,
        y=ci_y_sales,
        fill='toself',
        fillcolor='rgba(255, 0, 0, 0.2)',
        line=dict(color='rgba(255, 0, 0, 0)'),
        hoverinfo='skip',
        name='Sales Confidence Interval'
    ))
    fig_sales.update_layout(
        xaxis=dict(tickformat='%b %Y', showgrid=True, gridcolor='white'),
        yaxis=dict(title="Sales", showgrid=True, gridcolor='white'),
        title={
            'text': f"{user_value} - Sales Forecast",
            'y': 0.97,
            'x': 0.5,
            'xanchor': 'center',
            'yanchor': 'top'
        },
        legend={'orientation': 'h', 'y': 1.07, 'x': 0.5, 'xanchor': 'center'},
        xaxis_title='Date',
        hovermode='x unified',
        template='plotly_white',
        width=1200,
        height=700,
        plot_bgcolor='#F0F8FF'
    )
    plot_html_sales = fig_sales.to_html(full_html=False)
    
    # Prepare table data for Sales
    historical_sales_df = monthly_sales.rename(columns={"ds": "Order Date", "y": "Sales"})
    historical_sales_table = historical_sales_df.to_dict(orient="records")
    forecast_sales_df = future_sales_forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']].copy()
    forecast_sales_df.rename(columns={
        "ds": "Date", 
        "yhat": "Forecast Sales", 
        "yhat_lower": "Lower CI", 
        "yhat_upper": "Upper CI"
    }, inplace=True)
    forecast_sales_table = forecast_sales_df.to_dict(orient="records")
    
    #----------------
    # Profit Forecast 
    #----------------
    # Train/Test Split
    train_profit = monthly_profit.iloc[:-forecast_periods]
    test_profit = monthly_profit.iloc[-forecast_periods:]
    
    # Train model on Profit
    model_profit = Prophet(yearly_seasonality=True, daily_seasonality=False, weekly_seasonality=False)
    model_profit.fit(train_profit)
    future_profit = model_profit.make_future_dataframe(periods=forecast_periods, freq='ME')
    forecast_profit = model_profit.predict(future_profit)
    
    # Evaluation on train/test splits
    train_profit_forecast = forecast_profit[forecast_profit['ds'].isin(train_profit['ds'])]
    test_profit_forecast = forecast_profit[forecast_profit['ds'].isin(test_profit['ds'])]
    
    profit_train_actual = train_profit.set_index('ds')['y']
    profit_train_pred = train_profit_forecast.set_index('ds')['yhat']
    profit_test_actual = test_profit.set_index('ds')['y']
    profit_test_pred = test_profit_forecast.set_index('ds')['yhat']
    
    mae_profit_train, rmse_profit_train, r2_profit_train = compute_metrics(profit_train_actual, profit_train_pred)
    mae_profit_test, rmse_profit_test, r2_profit_test = compute_metrics(profit_test_actual, profit_test_pred)
    
    # Final model using entire Profit data (for plotting)
    model_profit_full = Prophet(yearly_seasonality=True, daily_seasonality=False, weekly_seasonality=False)
    model_profit_full.fit(monthly_profit)
    future_profit_full = model_profit_full.make_future_dataframe(periods=forecast_periods, freq='ME')
    forecast_profit_full = model_profit_full.predict(future_profit_full)
    
    last_date_profit = monthly_profit['ds'].max()
    historical_profit = monthly_profit.copy()
    future_profit_forecast = forecast_profit_full[forecast_profit_full['ds'] > last_date_profit]
    
    # Build Profit Plotly Figure
    fig_profit = go.Figure()
    fig_profit.add_trace(go.Scatter(
        x=historical_profit['ds'],
        y=historical_profit['y'],
        mode='lines+markers',
        line=dict(color='blue', width=2),
        marker=dict(color='blue', size=6),
        name='Historical Profit'
    ))
    fig_profit.add_trace(go.Scatter(
        x=future_profit_forecast['ds'],
        y=future_profit_forecast['yhat'],
        mode='lines+markers',
        line=dict(color='red', width=2),
        marker=dict(color='red', size=6),
        name='Forecast Profit'
    ))
    ci_x_profit = pd.concat([future_profit_forecast['ds'], future_profit_forecast['ds'][::-1]])
    ci_y_profit = pd.concat([future_profit_forecast['yhat_upper'], future_profit_forecast['yhat_lower'][::-1]])
    fig_profit.add_trace(go.Scatter(
        x=ci_x_profit,
        y=ci_y_profit,
        fill='toself',
        fillcolor='rgba(255, 0, 0, 0.2)',
        line=dict(color='rgba(255, 0, 0, 0)'),
        hoverinfo='skip',
        name='Profit Confidence Interval'
    ))
    fig_profit.update_layout(
        xaxis=dict(tickformat='%b %Y', showgrid=True, gridcolor='white'),
        yaxis=dict(title="Profit", showgrid=True, gridcolor='white'),
        title={
            'text': f"{user_value} - Profit Forecast",
            'y': 0.97,
            'x': 0.5,
            'xanchor': 'center',
            'yanchor': 'top'
        },
        legend={'orientation': 'h', 'y': 1.07, 'x': 0.5, 'xanchor': 'center'},
        hovermode='x unified',
        template='plotly_white',
        width=1200,
        height=700,
        plot_bgcolor='#F0F8FF'
    )
    plot_html_profit = fig_profit.to_html(full_html=False)
    
    # Prepare table data for Profit
    historical_profit_df = monthly_profit.rename(columns={"ds": "Order Date", "y": "Profit"})
    historical_profit_table = historical_profit_df.to_dict(orient="records")
    forecast_profit_df = future_profit_forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']].copy()
    forecast_profit_df.rename(columns={
        "ds": "Date", 
        "yhat": "Forecast Profit", 
        "yhat_lower": "Lower CI", 
        "yhat_upper": "Upper CI"
    }, inplace=True)
    forecast_profit_table = forecast_profit_df.to_dict(orient="records")
    
    # Combine results for Sales and Profit
    result = {
        'train_metrics': {
            'sales': {'MAE': mae_sales_train, 'RMSE': rmse_sales_train, 'R2': r2_sales_train},
            'profit': {'MAE': mae_profit_train, 'RMSE': rmse_profit_train, 'R2': r2_profit_train}
        },
        'test_metrics': {
            'sales': {'MAE': mae_sales_test, 'RMSE': rmse_sales_test, 'R2': r2_sales_test},
            'profit': {'MAE': mae_profit_test, 'RMSE': rmse_profit_test, 'R2': r2_profit_test}
        },
        'plot_html': {
            'sales': plot_html_sales,
            'profit': plot_html_profit
        },
        'historical': {
            'sales': historical_sales_table,
            'profit': historical_profit_table
        },
        'forecast': {
            'sales': forecast_sales_table,
            'profit': forecast_profit_table
        }
    }
    
    return result
# Functions for PROPHET forecast route {END}

# Functions for PROPHET with region forecast route {START}
def forecast_prophet_with_region_file(df, region_type, region_value, level, level_value):
    # ----------- Data Filtering -----------
    # Filter by region
    if region_type == "city":
        region_filtered_df = df[df["City"] == region_value]
    elif region_type == "state":
        region_filtered_df = df[df["State"] == region_value]
    else:
        return {"error": f"Invalid region type: {region_type}"}
    
    # Further filter by prediction level
    if level == "product":
        filtered_df = region_filtered_df[region_filtered_df["Product Name"] == level_value]
    elif level == "category":
        filtered_df = region_filtered_df[region_filtered_df["Category"] == level_value]
    elif level == "sub-category":
        filtered_df = region_filtered_df[region_filtered_df["Sub-Category"] == level_value]
    else:
        return {"error": f"Invalid prediction level: {level}"}

    if filtered_df.empty:
        return {"error": f"No sales or profit data found for {region_type} '{region_value}' and {level} '{level_value}'."}

    try:
        # ----------- Aggregation -----------
        filtered_df.sort_values('Order Date', inplace=True)
        # Aggregate monthly using month-end frequency
        monthly_agg = filtered_df.resample('ME', on='Order Date').agg({'Sales': 'sum', 'Profit': 'sum'}).reset_index()
        
        # Prepare dataframes for Prophet:
        monthly_sales = monthly_agg[['Order Date', 'Sales']].rename(columns={'Order Date': 'ds', 'Sales': 'y'})
        monthly_profit = monthly_agg[['Order Date', 'Profit']].rename(columns={'Order Date': 'ds', 'Profit': 'y'})
        
        forecast_periods = 12
        if len(monthly_sales) < 2 * forecast_periods:
            print("Warning: Not enough data for a robust 12-month train/test split. Proceeding anyway...")

        # ----------- Sales Forecasting -----------
        train_sales = monthly_sales.iloc[:-forecast_periods]
        test_sales = monthly_sales.iloc[-forecast_periods:]
        
        model_sales = Prophet(yearly_seasonality=True, daily_seasonality=False, weekly_seasonality=False)
        model_sales.fit(train_sales)
        future_sales = model_sales.make_future_dataframe(periods=forecast_periods, freq='ME')
        forecast_sales = model_sales.predict(future_sales)
        
        train_sales_forecast = forecast_sales[forecast_sales['ds'].isin(train_sales['ds'])]
        test_sales_forecast = forecast_sales[forecast_sales['ds'].isin(test_sales['ds'])]
        
        sales_train_actual = train_sales.set_index('ds')['y']
        sales_train_pred = train_sales_forecast.set_index('ds')['yhat']
        sales_test_actual = test_sales.set_index('ds')['y']
        sales_test_pred = test_sales_forecast.set_index('ds')['yhat']
        
        mae_sales_train, rmse_sales_train, r2_sales_train = compute_metrics(sales_train_actual, sales_train_pred)
        mae_sales_test, rmse_sales_test, r2_sales_test = compute_metrics(sales_test_actual, sales_test_pred)
        
        # Final Sales Forecast (using entire data)
        model_sales_full = Prophet(yearly_seasonality=True, daily_seasonality=False, weekly_seasonality=False)
        model_sales_full.fit(monthly_sales)
        future_sales_full = model_sales_full.make_future_dataframe(periods=forecast_periods, freq='ME')
        forecast_sales_full = model_sales_full.predict(future_sales_full)
        
        last_date_sales = monthly_sales['ds'].max()
        historical_sales = monthly_sales.copy()
        future_sales_forecast = forecast_sales_full[forecast_sales_full['ds'] > last_date_sales]
        
        # Build Sales Plotly Figure
        fig_sales = go.Figure()
        fig_sales.add_trace(go.Scatter(
            x=historical_sales['ds'],
            y=historical_sales['y'],
            mode='lines+markers',
            line=dict(color='blue', width=2),
            marker=dict(color='blue', size=6),
            name='Historical Sales'
        ))
        fig_sales.add_trace(go.Scatter(
            x=future_sales_forecast['ds'],
            y=future_sales_forecast['yhat'],
            mode='lines+markers',
            line=dict(color='red', width=2),
            marker=dict(color='red', size=6),
            name='Forecast Sales'
        ))
        ci_x_sales = pd.concat([future_sales_forecast['ds'], future_sales_forecast['ds'][::-1]])
        ci_y_sales = pd.concat([future_sales_forecast['yhat_upper'], future_sales_forecast['yhat_lower'][::-1]])
        fig_sales.add_trace(go.Scatter(
            x=ci_x_sales,
            y=ci_y_sales,
            fill='toself',
            fillcolor='rgba(255, 0, 0, 0.2)',
            line=dict(color='rgba(255, 0, 0, 0)'),
            hoverinfo='skip',
            name='Sales Confidence Interval'
        ))
        fig_sales.update_layout(
            xaxis=dict(tickformat='%b %Y', showgrid=True, gridcolor='white'),
            yaxis=dict(title="Sales", showgrid=True, gridcolor='white'),
            title={
                'text': f"{region_value} ({region_type.title()}) - {level_value} {level.title()} Sales Forecast",
                'y': 0.97,
                'x': 0.5,
                'xanchor': 'center',
                'yanchor': 'top'
            },
            legend={
                'orientation': 'h',
                'y': 1.07,
                'x': 0.5,
                'xanchor': 'center'
            },
            plot_bgcolor='#F0F8FF',
            hovermode='x unified',
            template='plotly_white',
            width=1200,
            height=700,
        )
        plot_html_sales = fig_sales.to_html(full_html=False)
        
        # Prepare table data for Sales
        historical_sales_df = monthly_sales.rename(columns={"ds": "Order Date", "y": "Sales"})
        historical_sales_table = historical_sales_df.to_dict(orient="records")
        forecast_sales_df = future_sales_forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']].copy()
        forecast_sales_df.rename(columns={
            "ds": "Date", 
            "yhat": "Forecast Sales", 
            "yhat_lower": "Lower CI", 
            "yhat_upper": "Upper CI"
        }, inplace=True)
        forecast_sales_table = forecast_sales_df.to_dict(orient="records")
        
        sales_metrics = {
            'train': {
                'MAE': mae_sales_train,
                'RMSE': rmse_sales_train,
                'R2': r2_sales_train
            },
            'test': {
                'MAE': mae_sales_test,
                'RMSE': rmse_sales_test,
                'R2': r2_sales_test
            }
        }
        
        # ----------- Profit Forecasting -----------
        train_profit = monthly_profit.iloc[:-forecast_periods]
        test_profit = monthly_profit.iloc[-forecast_periods:]
        
        model_profit = Prophet(yearly_seasonality=True, daily_seasonality=False, weekly_seasonality=False)
        model_profit.fit(train_profit)
        future_profit = model_profit.make_future_dataframe(periods=forecast_periods, freq='ME')
        forecast_profit = model_profit.predict(future_profit)
        
        train_profit_forecast = forecast_profit[forecast_profit['ds'].isin(train_profit['ds'])]
        test_profit_forecast = forecast_profit[forecast_profit['ds'].isin(test_profit['ds'])]
        
        profit_train_actual = train_profit.set_index('ds')['y']
        profit_train_pred = train_profit_forecast.set_index('ds')['yhat']
        profit_test_actual = test_profit.set_index('ds')['y']
        profit_test_pred = test_profit_forecast.set_index('ds')['yhat']
        
        mae_profit_train, rmse_profit_train, r2_profit_train = compute_metrics(profit_train_actual, profit_train_pred)
        mae_profit_test, rmse_profit_test, r2_profit_test = compute_metrics(profit_test_actual, profit_test_pred)
        
        # Final Profit Forecast (using entire data)
        model_profit_full = Prophet(yearly_seasonality=True, daily_seasonality=False, weekly_seasonality=False)
        model_profit_full.fit(monthly_profit)
        future_profit_full = model_profit_full.make_future_dataframe(periods=forecast_periods, freq='ME')
        forecast_profit_full = model_profit_full.predict(future_profit_full)
        
        last_date_profit = monthly_profit['ds'].max()
        historical_profit = monthly_profit.copy()
        future_profit_forecast = forecast_profit_full[forecast_profit_full['ds'] > last_date_profit]
        
        # Build Profit Plotly Figure
        fig_profit = go.Figure()
        fig_profit.add_trace(go.Scatter(
            x=historical_profit['ds'],
            y=historical_profit['y'],
            mode='lines+markers',
            line=dict(color='blue', width=2),
            marker=dict(color='blue', size=6),
            name='Historical Profit'
        ))
        fig_profit.add_trace(go.Scatter(
            x=future_profit_forecast['ds'],
            y=future_profit_forecast['yhat'],
            mode='lines+markers',
            line=dict(color='red', width=2),
            marker=dict(color='red', size=6),
            name='Forecast Profit'
        ))
        ci_x_profit = pd.concat([future_profit_forecast['ds'], future_profit_forecast['ds'][::-1]])
        ci_y_profit = pd.concat([future_profit_forecast['yhat_upper'], future_profit_forecast['yhat_lower'][::-1]])
        fig_profit.add_trace(go.Scatter(
            x=ci_x_profit,
            y=ci_y_profit,
            fill='toself',
            fillcolor='rgba(255, 0, 0, 0.2)',
            line=dict(color='rgba(255, 0, 0, 0)'),
            hoverinfo='skip',
            name='Profit Confidence Interval'
        ))
        fig_profit.update_layout(
            xaxis=dict(tickformat='%b %Y', showgrid=True, gridcolor='white'),
            yaxis=dict(title="Profit", showgrid=True, gridcolor='white'),
            title={
                'text': f"{region_value} ({region_type.title()}) - {level_value} {level.title()} Profit Forecast",
                'y': 0.97,
                'x': 0.5,
                'xanchor': 'center',
                'yanchor': 'top'
            },
            legend={
                'orientation': 'h',
                'y': 1.07,
                'x': 0.5,
                'xanchor': 'center'
            },
            plot_bgcolor='#F0F8FF',
            hovermode='x unified',
            template='plotly_white',
            width=1200,
            height=700,
        )
        plot_html_profit = fig_profit.to_html(full_html=False)
        
        # Prepare table data for Profit
        historical_profit_df = monthly_profit.rename(columns={"ds": "Order Date", "y": "Profit"})
        historical_profit_table = historical_profit_df.to_dict(orient="records")
        forecast_profit_df = future_profit_forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']].copy()
        forecast_profit_df.rename(columns={
            "ds": "Date", 
            "yhat": "Forecast Profit", 
            "yhat_lower": "Lower CI", 
            "yhat_upper": "Upper CI"
        }, inplace=True)
        forecast_profit_table = forecast_profit_df.to_dict(orient="records")
        
        profit_metrics = {
            'train': {
                'MAE': mae_profit_train,
                'RMSE': rmse_profit_train,
                'R2': r2_profit_train
            },
            'test': {
                'MAE': mae_profit_test,
                'RMSE': rmse_profit_test,
                'R2': r2_profit_test
            }
        }
        
        # Combine results for sales and profit into one response
        result = {
            'sales': {
                'metrics': sales_metrics,
                'plot_html': plot_html_sales,
                'historical': historical_sales_table,
                'forecast': forecast_sales_table
            },
            'profit': {
                'metrics': profit_metrics,
                'plot_html': plot_html_profit,
                'historical': historical_profit_table,
                'forecast': forecast_profit_table
            }
        }
        return result
    
    except ValueError as e:
        return {"error": "Oops! Not enough data for forecasting."}
# Functions for PROPHET with region forecast route {END}

# Load the pre-trained KMeans model and scaler
with open('models_pkl/kmeans_model.pkl', 'rb') as model_file:
    kmeans = pickle.load(model_file)

with open('models_pkl/scaler_kmeans.pkl', 'rb') as scaler_file:
    scaler = pickle.load(scaler_file)

# Load the pre-trained Hierarchical Clustering model and scaler
with open('models_pkl/hierarchical_model.pkl', 'rb') as model_file:
    hierarchical_model = pickle.load(model_file)

with open('models_pkl/scaler_hierarchical.pkl', 'rb') as scaler_file:
    scaler = pickle.load(scaler_file)

@app.route('/predict_kmeans', methods=['POST'])
def predict_kmeans():
    if 'file' not in request.files:
        return jsonify({'error': 'No file part'}), 400

    file = request.files['file']
    
    # Load the dataset from the uploaded file
    dataset = pd.read_excel(file)
    
    # Select the relevant features
    features = dataset[['Sales', 'Profit', 'Quantity', 'Discount']]
    
    # Scale the features
    scaled_features = scaler.transform(features)
    
    # Predict clusters
    clusters = kmeans.predict(scaled_features)
    
    # Add cluster assignment to the dataset
    dataset['Cluster'] = clusters
    
    # Identify the most profitable cluster
    cluster_profit = dataset.groupby('Cluster')['Profit'].mean()
    most_profitable_cluster = cluster_profit.idxmax()

    # Get all products from the most profitable cluster
    profitable_products = dataset[dataset['Cluster'] == most_profitable_cluster]

    # Sort products by profit in descending order
    profitable_products = profitable_products.sort_values(by='Profit', ascending=False)

    # Remove duplicate products, keeping the one with the highest profit
    profitable_products = profitable_products.drop_duplicates(subset='Product ID', keep='first')

    # Select the top 3 profitable products
    top_3_products = profitable_products.head(3)
    
    # Handle missing image URLs by assigning a default image URL
    default_image_url = "https://via.placeholder.com/150"
    dataset['Product Image'] = dataset['Product Image'].fillna(default_image_url)
        
    # Save the Elbow plot image
    static_folder = 'static'
    elbow_img_path = os.path.join(static_folder, 'elbow_plot_kmeans.png')
    if not os.path.exists(elbow_img_path):
        # Generate and save the Elbow graph
        wcss = []
        for i in range(1, 11):
            kmeans_elbow = KMeans(n_clusters=i, init='k-means++', max_iter=300, n_init=10, random_state=42)
            kmeans_elbow.fit(scaled_features)
            wcss.append(kmeans_elbow.inertia_)
        
        plt.figure(figsize=(10, 6))
        plt.plot(range(1, 11), wcss, marker='o')
        plt.title('Elbow Method for Optimal Number of Clusters')
        plt.xlabel('Number of Clusters')
        plt.ylabel('WCSS')
        plt.savefig(elbow_img_path)
        plt.close()
    
    # Save the second image (Clusters visualization)    
    cluster_img_path = os.path.join(static_folder, 'cluster_plot_kmeans.png')
    colors = ['purple', 'orange', 'green', 'blue', 'brown']
    
    plt.figure(figsize=(10, 6))
    for cluster in range(5):  # Assuming 5 clusters
        cluster_data = dataset[dataset['Cluster'] == cluster]
        plt.scatter(cluster_data['Sales'], cluster_data['Profit'], 
                    c=colors[cluster], label=f'Cluster {cluster}')
    
    # Plot the centroids
    plt.scatter(kmeans.cluster_centers_[:, 0], kmeans.cluster_centers_[:, 1], 
                s=100, c='red', label='Centroids')

    plt.title('Clusters of Sales and Profit')
    plt.xlabel('Sales')
    plt.ylabel('Profit')
    plt.legend()
    plt.savefig(cluster_img_path)
    plt.close()
    
    # Return the result along with the image paths
    return jsonify({
        'data': dataset.to_dict(orient='records'),
        'elbow_image': elbow_img_path,
        'cluster_image': cluster_img_path,
        'top_3_products': top_3_products[['Product ID', 'Product Name', 'Sales', 'Quantity', 'Discount', 'Profit', 'Product Image']].to_dict(orient='records')
    }), 200

@app.route('/predict_hierarchical', methods=['POST'])
def predict_hierarchical():
    if 'file' not in request.files:
        return jsonify({'error': 'No file part'}), 400

    file = request.files['file']

    # Load the dataset from the uploaded file
    dataset = pd.read_excel(file)

    # Select the relevant features
    features = dataset[['Sales', 'Profit', 'Quantity', 'Discount']]

    # Scale the features
    scaled_features = scaler.transform(features)

    # Generate and save the dendrogram plot
    dendrogram_img_path = 'static/dendrogram_plot.png'
    plt.figure(figsize=(10, 6))
    sch.dendrogram(sch.linkage(scaled_features, method='ward'))
    plt.title('Dendrogram for Optimal Number of Clusters')
    plt.xlabel('Data Points')
    plt.ylabel('Euclidean Distance')
    plt.savefig(dendrogram_img_path)
    plt.close()

    # Predict clusters using the pre-trained hierarchical model
    optimal_clusters = 5  # Based on the dendrogram analysis
    clusters = hierarchical_model.fit_predict(scaled_features)
    
    # Add cluster assignment to the dataset
    dataset['Cluster'] = clusters
    
    # Identify the most profitable cluster
    cluster_profit = dataset.groupby('Cluster')['Profit'].mean()
    most_profitable_cluster = cluster_profit.idxmax()

    # Get all products from the most profitable cluster
    profitable_products = dataset[dataset['Cluster'] == most_profitable_cluster]

    # Sort products by profit in descending order
    profitable_products = profitable_products.sort_values(by='Profit', ascending=False)

    # Remove duplicate products, keeping the one with the highest profit
    profitable_products = profitable_products.drop_duplicates(subset='Product ID', keep='first')

    # Select the top 3 profitable products
    top_3_products = profitable_products.head(3)
    
    # Handle missing image URLs by assigning a default image URL
    default_image_url = "https://via.placeholder.com/150"
    dataset['Product Image'] = dataset['Product Image'].fillna(default_image_url)

    # Save the second image (Cluster visualization using Sales and Profit)
    cluster_img_path = 'static/cluster_plot_hierarchical.png'
    colors = ['purple', 'orange', 'green', 'blue', 'brown']
    plt.figure(figsize=(10, 6))

    for cluster in range(optimal_clusters):
        cluster_data = dataset[dataset['Cluster'] == cluster]
        plt.scatter(cluster_data['Sales'], cluster_data['Profit'], 
                    c=colors[cluster], label=f'Cluster {cluster}')

    plt.title('Clusters of Sales and Profit')
    plt.xlabel('Sales')
    plt.ylabel('Profit')
    plt.legend()
    plt.savefig(cluster_img_path)
    plt.close()

    # Return the result along with the image paths
    return jsonify({
        'data': dataset.to_dict(orient='records'),
        'dendrogram_image': dendrogram_img_path,
        'cluster_image': cluster_img_path,
        'top_3_products': top_3_products[['Product ID', 'Product Name', 'Sales', 'Quantity', 'Discount', 'Profit', 'Product Image']].to_dict(orient='records')        
    }), 200

@app.route('/clusterwise_correlation', methods=['POST'])
def clusterwise_correlation():
    # Check if a file is provided
    try:
        if 'file' not in request.files:
            return jsonify({'error': 'No file provided'}), 400

        file = request.files['file']

        # ---------------------------
        # 1. Load Data and Clustering
        # ---------------------------
        orders_df = pd.read_excel(file, sheet_name="Orders")

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
        scatter_plot_path = 'static/clusterwise_correlation/cluster_analysis.png'
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
        plt.savefig(scatter_plot_path)
        plt.close()

        # --------------------------------------------------
        # 4. Cluster-wise Analysis: Heatmap, Scatter Plots with Regression, and Category Analysis
        # --------------------------------------------------

        # Compute total profit for each cluster and sort in descending order
        cluster_profitability = orders_df.groupby("Cluster")["Profit"].sum().sort_values(ascending=False)
        sorted_clusters = cluster_profitability.index.tolist()

        # List of variable pairs for scatter plots
        pairs = [
            ('Sales', 'Profit'),
        ]
        
        heatmap_paths = []
        regression_paths = []
        
        cluster_details = []

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
            heatmap_path = f'static/clusterwise_correlation/correlation_heatmap_cluster_{i}.png'
            plt.savefig(heatmap_path)
            plt.close()
            heatmap_paths.append(heatmap_path)
            
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
                regression_path = f'static/clusterwise_correlation/liner_regression_cluster_{i}.png'
                plt.savefig(regression_path)
                plt.close()
                regression_paths.append(regression_path)
                        
            # --------------------------
            # Cluster-based Category Analysis
            # --------------------------
            # Aggregate total Profit and Sales by Category in this cluster
            category_summary = cluster_df.groupby('Sub-Category').agg({'Profit': 'sum', 'Sales': 'sum'}).reset_index()
            
            # Identify most/least profitable categories based on total profit
            most_profitable_category = category_summary.loc[category_summary['Profit'].idxmax()]
            least_profitable_category = category_summary.loc[category_summary['Profit'].idxmin()]
            
            # Identify most/least sold categories based on total sales
            most_selled_category = category_summary.loc[category_summary['Sales'].idxmax()]
            least_selled_category = category_summary.loc[category_summary['Sales'].idxmin()]
            
            # For each identified category, find a representative product within that cluster.
            # Most Profitable Product
            rep_most_profitable_product = (
                cluster_df[cluster_df['Sub-Category'] == most_profitable_category['Sub-Category']]
                .sort_values('Profit', ascending=False)
                .iloc[0]
                .replace({np.nan: None})
                .to_dict()
            )

            # Least Profitable Product
            rep_least_profitable_product = (
                cluster_df[cluster_df['Sub-Category'] == least_profitable_category['Sub-Category']]
                .sort_values('Profit', ascending=True)
                .iloc[0]
                .replace({np.nan: None})
                .to_dict()
            )

            # Most Sold Product
            rep_most_selled_product = (
                cluster_df[cluster_df['Sub-Category'] == most_selled_category['Sub-Category']]
                .sort_values('Sales', ascending=False)
                .iloc[0]
                .replace({np.nan: None})
                .to_dict()
            )

            # Least Sold Product
            rep_least_selled_product = (
                cluster_df[cluster_df['Sub-Category'] == least_selled_category['Sub-Category']]
                .sort_values('Sales', ascending=True)
                .iloc[0]
                .replace({np.nan: None})
                .to_dict()
            )
            
            # Append a dictionary for this cluster to the cluster_details list
            cluster_details.append({
                'cluster_label': cluster_label_map[cl],
                'most_profitable_category': most_profitable_category.to_dict(),
                'rep_most_profitable_product': rep_most_profitable_product,
                'least_profitable_category': least_profitable_category.to_dict(),
                'rep_least_profitable_product': rep_least_profitable_product,
                'most_sold_category': most_selled_category.to_dict(),
                'rep_most_sold_product': rep_most_selled_product,
                'least_sold_category': least_selled_category.to_dict(),
                'rep_least_sold_product': rep_least_selled_product
            })
            
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
        for rank, cl in enumerate(sorted_clusters, start=1):
            # Subset for current cluster
            cluster_df = orders_df[orders_df['Cluster'] == cl]
            
            # Aggregate total Profit and Sales by Category in this cluster
            category_summary = cluster_df.groupby('Sub-Category').agg({'Profit': 'sum', 'Sales': 'sum'}).reset_index()
            
            # For Profit: Identify the categories with highest and lowest total profit.
            most_profitable_category = category_summary.loc[category_summary['Profit'].idxmax()]
            least_profitable_category = category_summary.loc[category_summary['Profit'].idxmin()]
            
            # For Sales: Identify the categories with highest and lowest total sales.
            most_selled_category = category_summary.loc[category_summary['Sales'].idxmax()]
            least_selled_category = category_summary.loc[category_summary['Sales'].idxmin()]
            
            # For each identified category, select a representative product within that cluster.
            rep_most_profitable_product = cluster_df[cluster_df['Sub-Category'] == most_profitable_category['Sub-Category']].loc[cluster_df[cluster_df['Sub-Category'] == most_profitable_category['Sub-Category']]['Profit'].idxmax()]
            rep_least_profitable_product = cluster_df[cluster_df['Sub-Category'] == least_profitable_category['Sub-Category']].loc[cluster_df[cluster_df['Sub-Category'] == least_profitable_category['Sub-Category']]['Profit'].idxmin()]
            
            rep_most_selled_product = cluster_df[cluster_df['Sub-Category'] == most_selled_category['Sub-Category']].loc[cluster_df[cluster_df['Sub-Category'] == most_selled_category['Sub-Category']]['Sales'].idxmax()]
            rep_least_selled_product = cluster_df[cluster_df['Sub-Category'] == least_selled_category['Sub-Category']].loc[cluster_df[cluster_df['Sub-Category'] == least_selled_category['Sub-Category']]['Sales'].idxmin()]
            
            # Append profit markers (green for most profitable, red for least profitable)
            profit_markers.append({
                'label': 'Most Profitable',
                'color': 'green',
                'product_name': rep_most_profitable_product['Product Name'],
                'profit': rep_most_profitable_product['Profit'],
                'city': rep_most_profitable_product['City'],
                'state': rep_most_profitable_product['State'],
                'Cluster': rank
            })
            profit_markers.append({
                'label': 'Least Profitable',
                'color': 'red',
                'product_name': rep_least_profitable_product['Product Name'],
                'profit': rep_least_profitable_product['Profit'],
                'city': rep_least_profitable_product['City'],
                'state': rep_least_profitable_product['State'],
                'Cluster': rank
            })
            
            # Append sales markers (green for most sold, red for least sold)
            sales_markers.append({
                'label': 'Most Sold',
                'color': 'green',
                'product_name': rep_most_selled_product['Product Name'],
                'sales': rep_most_selled_product['Sales'],
                'city': rep_most_selled_product['City'],
                'state': rep_most_selled_product['State'],
                'Cluster': rank
            })
            sales_markers.append({
                'label': 'Least Sold',
                'color': 'red',
                'product_name': rep_least_selled_product['Product Name'],
                'sales': rep_least_selled_product['Sales'],
                'city': rep_least_selled_product['City'],
                'state': rep_least_selled_product['State'],
                'Cluster': rank
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
                f"Profit: {marker['profit']}<br>"
                f"Cluster: {marker['Cluster']}" 
            )
            folium.CircleMarker(
                location=new_coords,
                radius=6,
                color=marker['color'],
                fill=True,
                fill_color=marker['color'],
                tooltip=tooltip
            ).add_to(profit_cluster)

        # Save the profit map
        profit_map_path = 'static/maps/profit_map.html'
        profit_map.save(profit_map_path)

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
                f"Sales: {marker['sales']}<br>"
                f"Cluster: {marker['Cluster']}"
            )
            folium.CircleMarker(
                location=new_coords,
                radius=6,
                color=marker['color'],
                fill=True,
                fill_color=marker['color'],
                tooltip=tooltip
            ).add_to(sales_cluster)

        # Save the sales map
        sales_map_path = 'static/maps/sales_map.html'
        sales_map.save(sales_map_path)
        
        result = {
            'data': orders_df.where(pd.notnull(orders_df), None).to_dict(orient='records'), # Replacing NaN with None
            'scatter_plot': scatter_plot_path,
            'heatmaps': heatmap_paths,
            'regression_plots': regression_paths,
            'profit_map': profit_map_path,
            'sales_map': sales_map_path,
            'cluster_details': cluster_details
        }
        return jsonify(result), 200
    
    except Exception as e:
        import traceback
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500
    
@app.route('/linear_regression_forecast', methods=['POST'])
def linear_regression_forecast():
    # Check for file in the request
    if 'file' not in request.files:
        return jsonify({'error': 'No file part in the request'}), 400

    file = request.files['file']
    if file.filename == '':
        return jsonify({'error': 'No file selected'}), 400

    try:
        # Load Excel file from the uploaded file
        df = pd.read_excel(file)
    except Exception as e:
        return jsonify({'error': f'Failed to read Excel file: {str(e)}'}), 400

    # Create the 'Year' column from 'Order Date' if present
    if 'Order Date' in df.columns:
        df['Order Date'] = pd.to_datetime(df['Order Date'], errors='coerce')
        df['Year'] = df['Order Date'].dt.year
    else:
        return jsonify({'error': "Excel file must contain an 'Order Date' column"}), 400

    # Generate overall aggregated view and graph
    overall_view = overall_aggregated_view(df)
    
    # Get the sub-category from the form data
    sub_category = request.form.get('sub_category')
    if not sub_category:
        return jsonify({'error': 'sub_category not provided'}), 400

    # Forecast for the given sub-category
    sub_category_forecast = forecast_sales_profit_file(df, 'Sub-Category', sub_category)
    if sub_category_forecast is None:
        return jsonify({'error': f'No data found for Sub-Category: {sub_category}'}), 400

    # Get recommended products for the sub-category
    recommended_products = recommend_products_data(df, 'Sub-Category', sub_category)

    result = {
        'overall_view': overall_view,
        'sub_category_forecast': sub_category_forecast,
        'recommended_products': recommended_products
    }

    # Optionally, if a product name is provided, forecast that product as well
    product_name = request.form.get('product_name')
    if product_name:
        product_forecast = forecast_product_sales_profit_file(df, sub_category, product_name)
        if product_forecast:
            result['product_forecast'] = product_forecast
        else:
            result['product_forecast'] = f'No data found for Product: {product_name} in Sub-Category: {sub_category}'

    return jsonify(result), 200

@app.route('/sarimax_forecast', methods=['POST'])
def sarimax_forecast():
    # Check if file is in the request
    if 'file' not in request.files:
        return jsonify({'error': 'No file part in the request'}), 400

    file = request.files['file']
    if file.filename == '':
        return jsonify({'error': 'No file selected'}), 400

    # Try loading the Excel file and ensure the Order Date column is parsed
    try:
        df = pd.read_excel(file, parse_dates=['Order Date'])
    except Exception as e:
        return jsonify({'error': f'Failed to read Excel file: {str(e)}'}), 400

    if 'Order Date' not in df.columns:
        return jsonify({'error': "Excel file must contain an 'Order Date' column"}), 400

    # Sort data by Order Date
    df.sort_values('Order Date', inplace=True)

    # Get prediction type and value from form data
    prediction_type = request.form.get('prediction_type')
    user_value = request.form.get('user_value')
    
    if not prediction_type or not user_value:
        return jsonify({'error': 'prediction_type and user_value must be provided'}), 400
    
    prediction_type = prediction_type.strip().lower()
    user_value = user_value.strip()
    
    # Determine the filter column based on prediction type
    if prediction_type == 'product':
        filter_col = 'Product Name'
    elif prediction_type in ['sub-category', 'subcategory']:
        filter_col = 'Sub-Category'
    elif prediction_type == 'category':
        filter_col = 'Category'
    else:
        return jsonify({'error': "Invalid prediction type. Use 'product', 'sub-category', or 'category'."}), 400

    # Generate forecasts using the SARIMAX model
    forecast_result = forecast_sarimax_file(df, filter_col, user_value)
    if forecast_result is None:
        return jsonify({'error': f"'{user_value}' not found in the dataset for {prediction_type} level."}), 400

    return jsonify(forecast_result), 200

@app.route('/prophet_forecast', methods=['POST'])
def prophet_forecast():
    # Check if file is included in the request
    if 'file' not in request.files:
        return jsonify({'error': 'No file part in the request'}), 400

    file = request.files['file']
    if file.filename == '':
        return jsonify({'error': 'No file selected'}), 400

    # Attempt to load the Excel file (ensuring 'Order Date' is parsed)
    try:
        df = pd.read_excel(file, parse_dates=['Order Date'])
    except Exception as e:
        return jsonify({'error': f'Failed to read Excel file: {str(e)}'}), 400

    if 'Order Date' not in df.columns:
        return jsonify({'error': "Excel file must contain an 'Order Date' column"}), 400

    # Sort data by Order Date
    df.sort_values('Order Date', inplace=True)

    # Get prediction type and value from the form data
    prediction_type = request.form.get('prediction_type')
    user_value = request.form.get('user_value')
    
    if not prediction_type or not user_value:
        return jsonify({'error': 'prediction_type and user_value must be provided'}), 400
    
    prediction_type = prediction_type.strip().lower()
    user_value = user_value.strip()
    
    # Determine the filter column based on prediction type
    if prediction_type == 'product':
        filter_col = 'Product Name'
    elif prediction_type in ['sub-category', 'subcategory']:
        filter_col = 'Sub-Category'
    elif prediction_type == 'category':
        filter_col = 'Category'
    else:
        return jsonify({'error': "Invalid prediction type. Use 'product', 'sub-category', or 'category'."}), 400

    # Generate forecast using the Prophet model
    forecast_result = forecast_prophet_file(df, filter_col, user_value)
    if forecast_result is None:
        return jsonify({'error': f"'{user_value}' not found in the dataset for {prediction_type} level."}), 400

    return jsonify(forecast_result), 200

@app.route('/prophet_forecast_with_region', methods=['POST'])
def prophet_forecast_with_region():
    # Check if file is included in the request
    if 'file' not in request.files:
        return jsonify({'error': 'No file part in the request'}), 400

    file = request.files['file']
    if file.filename == '':
        return jsonify({'error': 'No file selected'}), 400

    # Attempt to load the Excel file (ensuring 'Order Date' is parsed)
    try:
        df = pd.read_excel(file, parse_dates=['Order Date'])
    except Exception as e:
        return jsonify({'error': f'Failed to read Excel file: {str(e)}'}), 400

    if 'Order Date' not in df.columns:
        return jsonify({'error': "Excel file must contain an 'Order Date' column"}), 400

    df.sort_values('Order Date', inplace=True)
    
    # Get region and filtering details from the form data
    region_type = request.form.get('region_type')
    region_value = request.form.get('region_value')
    level = request.form.get('prediction_level')
    level_value = request.form.get('level_value')
    
    if not region_type or not region_value or not level or not level_value:
        return jsonify({'error': 'region_type, region_value, prediction_level, and level_value must be provided'}), 400
    
    region_type = region_type.strip().lower()
    region_value = region_value.strip()
    level = level.strip().lower()
    level_value = level_value.strip()
    
    if region_type not in ["city", "state"]:
        return jsonify({'error': "Invalid region type. Use 'city' or 'state'."}), 400
    if level not in ["product", "category", "sub-category"]:
        return jsonify({'error': "Invalid prediction level. Use 'product', 'category', or 'sub-category'."}), 400

    forecast_result = forecast_prophet_with_region_file(df, region_type, region_value, level, level_value)
    if forecast_result is None:
        return jsonify({'error': f"No data found for {region_type} '{region_value}' and {level} '{level_value}'."}), 400

    return jsonify(forecast_result), 200

if __name__ == '__main__':
    app.run(debug=True)