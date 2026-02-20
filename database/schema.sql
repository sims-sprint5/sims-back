-- ============================================================================
-- SIMS Backend - Multi-Schema Multi-Tenancy SQL Schema
-- ============================================================================
-- 
-- Public schema: tablas compartidas entre todos los tenants.
-- Tenant schema: tablas aisladas por tenant.
--
-- Requerimientos: PostgreSQL 15+, PostGIS 3.3+
-- ============================================================================

-- ============================
-- EXTENSIONES
-- ============================
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "postgis";

-- ============================
-- SCHEMA PUBLIC - TABLAS COMPARTIDAS
-- ============================

-- Planes
CREATE TABLE public.plans (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    billing_interval VARCHAR(50) CHECK (billing_interval IN ('monthly','yearly')),
    max_users INTEGER,
    max_vehicles INTEGER,
    metadata JSONB,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Features
CREATE TABLE public.features (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    category VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Pivot plan_features
CREATE TABLE public.plan_features (
    id BIGSERIAL PRIMARY KEY,
    plan_id BIGINT REFERENCES public.plans(id) ON DELETE CASCADE,
    feature_id BIGINT REFERENCES public.features(id) ON DELETE CASCADE,
    tier VARCHAR(50) CHECK (tier IN ('basic','full','premium')),
    limit_value INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(plan_id, feature_id)
);

-- Tenants
CREATE TABLE public.tenants (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    plan_id BIGINT REFERENCES public.plans(id),
    status VARCHAR(50) DEFAULT 'active' CHECK (status IN ('active','suspended','cancelled','trial')),
    trial_ends_at TIMESTAMP,
    subscribed_at TIMESTAMP,
    cancelled_at TIMESTAMP,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Domains por tenant
CREATE TABLE public.domains (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID REFERENCES public.tenants(id) ON DELETE CASCADE,
    domain VARCHAR(255) UNIQUE NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Global admins
CREATE TABLE public.global_admins (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    email_verified_at TIMESTAMP,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tenant invoices
CREATE TABLE public.tenant_invoices (
    id BIGSERIAL PRIMARY KEY,
    tenant_id UUID REFERENCES public.tenants(id) ON DELETE CASCADE,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending','paid','failed','refunded')),
    due_date DATE NOT NULL,
    paid_at TIMESTAMP,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================
-- SCHEMA TENANT_X - TABLAS DE NEGOCIO POR TENANT
-- ============================
-- Nota: crear un schema por tenant, por ejemplo tenant_1, tenant_2, etc.

-- Ejemplo:
-- CREATE SCHEMA tenant_1;
-- SET search_path TO tenant_1;

-- Users
CREATE TABLE tenant_x.users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role VARCHAR(50) DEFAULT 'client' CHECK (role IN ('tenant_admin','manager','client')),
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

-- Vehicles
CREATE TABLE tenant_x.vehicles (
    id BIGSERIAL PRIMARY KEY,
    license_plate VARCHAR(20) UNIQUE NOT NULL,
    vin VARCHAR(17),
    brand VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL CHECK (type IN ('scooter','bike','car','van')),
    status VARCHAR(50) DEFAULT 'available' CHECK (status IN ('available','in_use','maintenance','retired')),
    year INTEGER,
    color VARCHAR(50),
    battery_level INTEGER CHECK (battery_level>=0 AND battery_level<=100),
    range_km INTEGER,
    price_per_minute DECIMAL(6,2),
    price_per_hour DECIMAL(8,2),
    location GEOGRAPHY(POINT,4326),
    metadata JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

-- Geofences
CREATE TABLE tenant_x.geofences (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL CHECK (type IN ('perimeter','zone','restricted','parking')),
    description TEXT,
    color VARCHAR(7) DEFAULT '#3B82F6',
    is_active BOOLEAN DEFAULT TRUE,
    polygon GEOGRAPHY(POLYGON,4326) NOT NULL,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bookings
CREATE TABLE tenant_x.bookings (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES tenant_x.users(id) ON DELETE CASCADE,
    vehicle_id BIGINT REFERENCES tenant_x.vehicles(id) ON DELETE CASCADE,
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending','confirmed','active','completed','cancelled')),
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP,
    actual_start_at TIMESTAMP,
    actual_end_at TIMESTAMP,
    pickup_location GEOGRAPHY(POINT,4326),
    dropoff_location GEOGRAPHY(POINT,4326),
    distance_km DECIMAL(8,2),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

-- Payments
CREATE TABLE tenant_x.payments (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE DEFAULT uuid_generate_v4(),
    user_id BIGINT REFERENCES tenant_x.users(id) ON DELETE CASCADE,
    booking_id BIGINT REFERENCES tenant_x.bookings(id) ON DELETE SET NULL,
    gateway VARCHAR(50) CHECK (gateway IN ('stripe','paypal','square')),
    gateway_txn_id VARCHAR(255),
    gateway_secondary_id VARCHAR(255),
    gateway_response JSONB,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    status VARCHAR(50) DEFAULT 'pending',
    refunded_amount DECIMAL(10,2) DEFAULT 0,
    paid_at TIMESTAMP,
    refunded_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Support tickets
CREATE TABLE tenant_x.support_tickets (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES tenant_x.users(id) ON DELETE CASCADE,
    assigned_to BIGINT REFERENCES tenant_x.users(id) ON DELETE SET NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'open' CHECK (status IN ('open','in_progress','resolved','closed')),
    priority VARCHAR(50) DEFAULT 'medium' CHECK (priority IN ('low','medium','high','urgent')),
    category VARCHAR(50) CHECK (category IN ('billing','technical','vehicle','general')),
    resolved_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Maintenance
CREATE TABLE tenant_x.maintenance (
    id BIGSERIAL PRIMARY KEY,
    vehicle_id BIGINT REFERENCES tenant_x.vehicles(id) ON DELETE CASCADE,
    reported_by BIGINT REFERENCES tenant_x.users(id) ON DELETE SET NULL,
    type VARCHAR(50) NOT NULL CHECK (type IN ('scheduled','repair','inspection','cleaning')),
    description TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending','in_progress','completed','cancelled')),
    scheduled_at TIMESTAMP,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    cost DECIMAL(10,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Incidences
CREATE TABLE tenant_x.incidences (
    id BIGSERIAL PRIMARY KEY,
    reported_by BIGINT REFERENCES tenant_x.users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL CHECK (type IN ('accident','damage','theft','battery','other')),
    description TEXT NOT NULL,
    severity VARCHAR(50) NOT NULL CHECK (severity IN ('low','medium','high','critical')),
    status VARCHAR(50) DEFAULT 'reported' CHECK (status IN ('reported','investigating','resolved','closed')),
    resolved_at TIMESTAMP,
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- API keys
CREATE TABLE tenant_x.api_keys (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES tenant_x.users(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    key VARCHAR(255) NOT NULL,
    prefix VARCHAR(8) NOT NULL,
    scopes JSONB DEFAULT '["*"]',
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tenant branding
CREATE TABLE tenant_x.tenant_branding (
    id BIGSERIAL PRIMARY KEY,
    logo_url VARCHAR(500),
    favicon_url VARCHAR(500),
    primary_color VARCHAR(7) DEFAULT '#3B82F6',
    secondary_color VARCHAR(7) DEFAULT '#10B981',
    accent_color VARCHAR(7),
    custom_css TEXT,
    settings JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vehicle locations
CREATE TABLE tenant_x.vehicle_locations (
    id BIGSERIAL PRIMARY KEY,
    vehicle_id BIGINT REFERENCES tenant_x.vehicles(id) ON DELETE CASCADE,
    location GEOGRAPHY(POINT,4326) NOT NULL,
    battery_level INTEGER CHECK (battery_level>=0 AND battery_level<=100),
    speed DECIMAL(6,2),
    heading DECIMAL(5,2),
    recorded_at TIMESTAMP NOT NULL
);

-- Audit logs
CREATE TABLE tenant_x.audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES tenant_x.users(id) ON DELETE SET NULL,
    action VARCHAR(255) NOT NULL,
    model_type VARCHAR(255),
    model_id BIGINT,
    old_values JSONB,
    new_values JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Payment webhook events
CREATE TABLE tenant_x.payment_webhook_events (
    id BIGSERIAL PRIMARY KEY,
    gateway VARCHAR(50) NOT NULL,
    event_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSONB,
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending','processed','failed')),
    processed_at TIMESTAMP,
    error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(event_id)
);

-- ============================================
-- ÍNDICES SUGERIDOS PARA TENANT
-- ============================================
CREATE INDEX idx_users_email ON tenant_x.users(email);
CREATE INDEX idx_vehicles_license ON tenant_x.vehicles(license_plate);
CREATE INDEX idx_bookings_user ON tenant_x.bookings(user_id);
CREATE INDEX idx_bookings_vehicle ON tenant_x.bookings(vehicle_id);
CREATE INDEX idx_maintenance_vehicle ON tenant_x.maintenance(vehicle_id);
CREATE INDEX idx_api_keys_key ON tenant_x.api_keys(key);
CREATE INDEX idx_vehicle_locations_recorded_at ON tenant_x.vehicle_locations(recorded_at DESC);
CREATE INDEX idx_payment_webhook_events_status ON tenant_x.payment_webhook_events(status);

-- ============================================================================
-- FIN DEL SCHEMA
-- ============================================================================
