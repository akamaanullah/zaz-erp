CREATE INDEX idx_usage_farm_status ON stock_usages (farm_id, status);
CREATE INDEX idx_expenses_farm_status ON expenses (farm_id, status);
CREATE INDEX idx_sales_farm_status ON sales (farm_id, status);
CREATE INDEX idx_advances_farm_status ON advances (farm_id, status);
