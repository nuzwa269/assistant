-- CoachPro AI Assistant — MySQL Schema (reference copy)
-- The actual installation is done via dbDelta() in class-coachpro-activator.php
-- This file is provided for documentation / manual inspection.

-- AI Models
CREATE TABLE IF NOT EXISTS `{prefix}coachpro_ai_models` (
    id VARCHAR(100) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    provider VARCHAR(100) NOT NULL,
    provider_type ENUM('openai_compatible','anthropic','lovable') NOT NULL DEFAULT 'openai_compatible',
    category ENUM('text','image','reasoning') NOT NULL DEFAULT 'text',
    credits_cost INT NOT NULL DEFAULT 1,
    min_plan ENUM('free','basic','pro') NOT NULL DEFAULT 'free',
    api_key_secret_name VARCHAR(100) DEFAULT NULL,
    api_base_url VARCHAR(500) DEFAULT NULL,
    api_model_name VARCHAR(200) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- Assistants
CREATE TABLE IF NOT EXISTS `{prefix}coachpro_assistants` (
    id CHAR(36) NOT NULL,
    owner_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    system_prompt LONGTEXT NOT NULL,
    icon VARCHAR(100) NOT NULL DEFAULT 'Bot',
    category VARCHAR(100) DEFAULT NULL,
    is_prebuilt TINYINT(1) NOT NULL DEFAULT 0,
    default_model_id VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_owner (owner_id),
    KEY idx_prebuilt (is_prebuilt)
);

-- User Active Assistants
CREATE TABLE IF NOT EXISTS `{prefix}coachpro_user_active_assistants` (
    id CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assistant_id CHAR(36) NOT NULL,
    activated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_ua (user_id, assistant_id),
    KEY idx_user (user_id)
);

-- Projects
CREATE TABLE IF NOT EXISTS `{prefix}coachpro_projects` (
    id CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id)
);

-- Conversations
CREATE TABLE IF NOT EXISTS `{prefix}coachpro_conversations` (
    id CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    project_id CHAR(36) NOT NULL,
    assistant_id CHAR(36) NOT NULL,
    title VARCHAR(500) NOT NULL DEFAULT 'New conversation',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project (project_id),
    KEY idx_user (user_id)
);

-- Messages
CREATE TABLE IF NOT EXISTS `{prefix}coachpro_messages` (
    id CHAR(36) NOT NULL,
    conversation_id CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('user','assistant','system') NOT NULL,
    content LONGTEXT NOT NULL,
    model_id VARCHAR(100) DEFAULT NULL,
    credits_used INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conv (conversation_id),
    KEY idx_user (user_id)
);

-- Conversation Summaries
CREATE TABLE IF NOT EXISTS `{prefix}coachpro_conv_summaries` (
    id CHAR(36) NOT NULL,
    conversation_id CHAR(36) NOT NULL,
    summary LONGTEXT DEFAULT NULL,
    durable_facts LONGTEXT DEFAULT NULL,
    summarized_up_to_message_id CHAR(36) DEFAULT NULL,
    message_count_at_summary INT NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_conv (conversation_id)
);

-- Saved Responses
CREATE TABLE IF NOT EXISTS `{prefix}coachpro_saved_responses` (
    id CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    message_id CHAR(36) NOT NULL,
    project_id CHAR(36) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_um (user_id, message_id),
    KEY idx_user (user_id)
);

-- Plans
CREATE TABLE IF NOT EXISTS `{prefix}coachpro_plans` (
    id VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    price_pkr INT NOT NULL DEFAULT 0,
    monthly_credits INT NOT NULL DEFAULT 0,
    max_projects INT DEFAULT NULL,
    max_custom_assistants INT DEFAULT NULL,
    max_saved_responses INT DEFAULT NULL,
    features LONGTEXT DEFAULT NULL,
    is_popular TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- Credit Packs
CREATE TABLE IF NOT EXISTS `{prefix}coachpro_credit_packs` (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    credits INT NOT NULL,
    price_pkr INT NOT NULL,
    is_popular TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- Payments
CREATE TABLE IF NOT EXISTS `{prefix}coachpro_payments` (
    id CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    kind ENUM('subscription','credit_pack') NOT NULL,
    plan_id VARCHAR(50) DEFAULT NULL,
    pack_id CHAR(36) DEFAULT NULL,
    amount_pkr INT NOT NULL,
    method ENUM('jazzcash','easypaisa','bank_transfer','whatsapp') NOT NULL,
    sender_name VARCHAR(255) DEFAULT NULL,
    sender_phone VARCHAR(50) DEFAULT NULL,
    reference_no VARCHAR(255) DEFAULT NULL,
    proof_url TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    admin_notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    KEY idx_status (status)
);

-- Transactions
CREATE TABLE IF NOT EXISTS `{prefix}coachpro_transactions` (
    id CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    amount INT NOT NULL,
    kind ENUM('signup_bonus','subscription_grant','pack_purchase','message_deduct','admin_adjust','refund') NOT NULL,
    balance_after INT NOT NULL,
    reference_id CHAR(36) DEFAULT NULL,
    model_id VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_date (user_id, created_at)
);
