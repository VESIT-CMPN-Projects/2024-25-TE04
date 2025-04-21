import pandas as pd
import numpy as np
import warnings
import logging
from prophet import Prophet
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
import plotly.graph_objects as go

# Suppress Pandas SettingWithCopyWarning
warnings.simplefilter("ignore", category=pd.errors.SettingWithCopyWarning)

# Suppress cmdstanpy INFO messages (only show ERROR and above)
logging.getLogger("cmdstanpy").setLevel(logging.ERROR)

def compute_metrics(actual, predicted):
    mae = mean_absolute_error(actual, predicted)
    rmse = np.sqrt(mean_squared_error(actual, predicted))
    r2 = r2_score(actual, predicted)
    return mae, rmse, r2

# -------------------------------------------------
# 1. Load the Data
# -------------------------------------------------
file_path = "Assets/Dataset/Superstore 3.0.xlsx"  # Adjust if needed
df = pd.read_excel(file_path, sheet_name="Orders", parse_dates=['Order Date'])

# -------------------------------------------------
# 2. Prompt User for Forecasting Level
# -------------------------------------------------
valid_levels = ["product", "category", "sub-category"]
level = input("Enter level for forecasting (product, category, sub-category): ").strip().lower()
if level not in valid_levels:
    raise ValueError(f"Invalid level '{level}'. Please choose from {valid_levels}.")

value = input(f"Enter the {level} name: ").strip()

# Filter data
if level == "product":
    filtered_df = df[df["Product Name"] == value]
elif level == "category":
    filtered_df = df[df["Category"] == value]
elif level == "sub-category":
    filtered_df = df[df["Sub-Category"] == value]

if filtered_df.empty:
    print(f"No data found for {level} '{value}'. Exiting.")
    exit()

# -------------------------------------------------
# 3. Prepare Monthly Aggregation
# -------------------------------------------------
filtered_df.sort_values('Order Date', inplace=True)
monthly_sales = filtered_df.resample('ME', on='Order Date')['Sales'].sum().reset_index()
monthly_sales.columns = ['ds', 'y']  # Prophet requires 'ds' and 'y'

# -------------------------------------------------
# 4. OPTIONAL: Train/Test Split for Evaluation
# -------------------------------------------------
forecast_periods = 12  # Last 12 months as test
if len(monthly_sales) < 2 * forecast_periods:
    print("Warning: Not enough data to do a robust 12-month train/test split. Proceeding anyway...")

train = monthly_sales.iloc[:-forecast_periods]
test = monthly_sales.iloc[-forecast_periods:]

# -------------------------------------------------
# 5. Model for Train/Test Metrics
# -------------------------------------------------
model_train = Prophet(
    yearly_seasonality=True,
    daily_seasonality=True,
    weekly_seasonality=False
)

model_train.fit(train)

# Create future dataframe to cover the entire historical + test range
# i.e. from earliest train date to last test date
future_train = model_train.make_future_dataframe(periods=forecast_periods, freq='ME')
forecast_train = model_train.predict(future_train)

# Extract fitted values for train & test sets
train_forecast = forecast_train[forecast_train['ds'].isin(train['ds'])]
test_forecast = forecast_train[forecast_train['ds'].isin(test['ds'])]

# Align indexes for metric calculations
train_actual = train.set_index('ds')['y']
train_pred = train_forecast.set_index('ds')['yhat']

test_actual = test.set_index('ds')['y']
test_pred = test_forecast.set_index('ds')['yhat']

# Compute metrics
mae_train, rmse_train, r2_train = compute_metrics(train_actual, train_pred)
mae_test, rmse_test, r2_test = compute_metrics(test_actual, test_pred)

# Print out the metrics
print(f"\nProphet Model (Train/Test Split) for {level.title()} '{value}'")
print("Train Metrics:")
print(f"MAE:  {mae_train:.2f}")
print(f"RMSE: {rmse_train:.2f}")
print(f"R2:   {r2_train:.4f}")

print("\nTest Metrics:")
print(f"MAE:  {mae_test:.2f}")
print(f"RMSE: {rmse_test:.2f}")
print(f"R2:   {r2_test:.4f}")

# -------------------------------------------------
# 6. Final Model on Entire Data (for Plot)
# -------------------------------------------------
model_full = Prophet(
    yearly_seasonality=True,
    daily_seasonality=False,
    weekly_seasonality=False
)

model_full.fit(monthly_sales)

# Forecast 12 months into the future
future_full = model_full.make_future_dataframe(periods=forecast_periods, freq='ME')
forecast_full = model_full.predict(future_full)

# -------------------------------------------------
# 7. Separate Historical vs. Future Forecast
# -------------------------------------------------
last_historical_date = monthly_sales['ds'].max()

# Historical (all actual data)
historical = monthly_sales.copy()

# Future forecast (beyond last actual date)
future_forecast = forecast_full[forecast_full['ds'] > last_historical_date]

# -------------------------------------------------
# 8. Build the Interactive Plotly Figure
# -------------------------------------------------
fig = go.Figure()

# (A) Historical Sales (Blue)
fig.add_trace(go.Scatter(
    x=historical['ds'],
    y=historical['y'],
    mode='lines+markers',
    line=dict(color='blue', width=2),
    marker=dict(color='blue', size=6),
    name='Historical Sales'
))

# (B) Forecast Sales (Red)
fig.add_trace(go.Scatter(
    x=future_forecast['ds'],
    y=future_forecast['yhat'],
    mode='lines+markers',
    line=dict(color='red', width=2),
    marker=dict(color='red', size=6),
    name='Forecast Sales'
))

# (C) Confidence Interval (Shaded in Red)
ci_x = pd.concat([future_forecast['ds'], future_forecast['ds'][::-1]])
ci_y = pd.concat([future_forecast['yhat_upper'], future_forecast['yhat_lower'][::-1]])

fig.add_trace(go.Scatter(
    x=ci_x,
    y=ci_y,
    fill='toself',
    fillcolor='rgba(255, 0, 0, 0.2)',  # Semi-transparent red
    line=dict(color='rgba(255, 0, 0, 0)'),
    hoverinfo='skip',
    name='Confidence Interval'
))

# -------------------------------------------------
# 9. Figure Layout
# -------------------------------------------------
fig.update_layout(
    xaxis=dict(
        tickformat='%b %Y',
        showgrid=True, gridcolor='white'
    ),
    yaxis=dict(
        showgrid=True, gridcolor='white',
        tick0=0,
        dtick=5000
    ),
    title=f"{value} - Sales Forecast",
    plot_bgcolor='#F0F8FF',
    xaxis_title='Date',
    yaxis_title='Sales',
    hovermode='x unified',
    template='plotly_white',
)

# Show the figure
fig.show()