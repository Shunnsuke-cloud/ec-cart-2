-- Create coupons table
CREATE TABLE IF NOT EXISTS coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL UNIQUE,
    type ENUM('fixed','percent') NOT NULL COMMENT 'fixed: 固定額割引, percent: 割合割引',
    value DECIMAL(10,2) NOT NULL COMMENT 'fixedなら金額(円)、percentなら100分率で指定(例:10は10%)',
    usage_limit INT DEFAULT NULL COMMENT '合計使用上限 (NULL は無制限)',
    used_count INT DEFAULT 0 COMMENT '現在の使用回数',
    min_order_amount INT DEFAULT NULL COMMENT '適用最小注文額（円）。NULLで制約なし',
    starts_at DATETIME DEFAULT NULL,
    ends_at DATETIME DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'active' COMMENT 'active, disabled, expired',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_starts_ends (starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
