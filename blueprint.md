# Inventory Blueprint

## Plant Lab Inventory System — Complete Architectural Reference

> **Version:** 1.0 | **Date:** March 2026
> **Scope:** Inventory Module only (excludes Research and Business modules)
> **Purpose:** Single source of truth for rebuilding the Inventory system from scratch to production.

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Domain Model](#2-domain-model)
3. [Database Design](#3-database-design)
4. [Project Architecture](#4-project-architecture)
5. [API Design](#5-api-design)
6. [Business Logic Layer](#6-business-logic-layer)
7. [Inventory Workflows](#7-inventory-workflows)
8. [Data Integrity Rules](#8-data-integrity-rules)
9. [Security Architecture](#9-security-architecture)
10. [Performance Strategy](#10-performance-strategy)
11. [Event System](#11-event-system)
12. [Testing Strategy](#12-testing-strategy)
13. [Production Readiness](#13-production-readiness)
14. [Implementation Roadmap](#14-implementation-roadmap)

---

# 1. System Overview

## 1.1 Purpose

The Plant Lab Inventory System manages the complete lifecycle of biological and laboratory resources in a research plant laboratory. It tracks:

- **Plant Specimens**: Species taxonomy, cultivar varieties, individual samples, and seed/seedling stock quantities.
- **Chemicals**: Reagents, solvents, and hazardous materials across named batches with full expiry tracking.
- **Equipment**: Laboratory instruments from acquisition through maintenance and borrowing.
- **Borrow Events**: Request → Approval → Return workflow for any borrowable resource.
- **Audit Trail**: Every mutation to every inventory item is stamped as an immutable Transaction record.

## 1.2 High-Level Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    API Layer (Laravel)                    │
│  JWT Auth ──► Route Guards ──► Controllers ──► Resources │
└──────────────────────────┬──────────────────────────────┘
                           │ Validated Request Data
┌──────────────────────────▼──────────────────────────────┐
│                   Service Layer                           │
│  BorrowService │ StockService │ ChemicalUsageService     │
│  InventoryCrudService │ DashboardService                 │
│                 TransactionService (shared)               │
└──────────────────────────┬──────────────────────────────┘
                           │ ORM / DB Transactions
┌──────────────────────────▼──────────────────────────────┐
│           Domain Models (Eloquent)                        │
│  PlantSpecies │ PlantVariety │ PlantSample │ PlantStock   │
│  Chemical │ ChemicalBatch │ ChemicalUsageLog              │
│  Equipment │ MaintenanceRecord                            │
│  BorrowRecord │ Transaction │ LocationHistory             │
│  Achievement │ UserDocument                              │
└──────────────────────────┬──────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────┐
│                    PostgreSQL Database                    │
│   Soft Deletes │ Check Constraints │ Partial Indexes     │
│   Row Locking (SELECT FOR UPDATE)                        │
└─────────────────────────────────────────────────────────┘
```

## 1.3 Design Philosophy

| Principle                         | Implementation                                                                                    |
| --------------------------------- | ------------------------------------------------------------------------------------------------- |
| **Single Responsibility**         | Each service owns one domain concern                                                              |
| **Polymorphism over Duplication** | `transactions` and `borrow_records` work across all inventory types via `MorphTo`                 |
| **Immutable Audit Log**           | Transactions are append-only; `TransactionService` is the sole writer                             |
| **Guard at the Model Layer**      | DB CHECK constraints prevent negative quantities even if application code is bypassed             |
| **Soft Deletes Everywhere**       | No row is ever hard-deleted from inventory tables                                                 |
| **Permission-Based Access**       | Spatie `laravel-permission` with an `api` guard; every `authorize()` call uses a named permission |
| **Cache Aggregates**              | Dashboard counts/alerts are cached so the home page never hits the DB under load                  |

---

# 2. Domain Model

## 2.1 PlantSpecies

**Purpose:** The root taxonomic record. Represents a plant species such as _Solanum lycopersicum_ (tomato). Everything in the plant sub-domain belongs to a species.

| Attribute            | Type            | Description                                                   |
| -------------------- | --------------- | ------------------------------------------------------------- |
| `id`                 | bigint PK       | Auto-increment                                                |
| `common_name`        | string          | Human-readable name (e.g. "Tomato")                           |
| `khmer_name`         | string?         | Khmer language name                                           |
| `scientific_name`    | string UNIQUE\* | Binomial nomenclature, unique among non-deleted rows          |
| `family`             | string?         | Botanical family (e.g. "Solanaceae")                          |
| `growth_type`        | enum            | `annual` / `perennial` / `biennial`                           |
| `native_region`      | string?         | Geographic origin                                             |
| `propagation_method` | string?         | Free-text (see `PropagationMethod` enum for suggested values) |
| `description`        | text?           | Notes                                                         |
| `image_url`          | string?         | Remote URL                                                    |
| `image_path`         | string?         | Local storage path                                            |
| `deleted_at`         | timestamp?      | Soft delete                                                   |

**Relationships:** `hasMany` PlantVariety, PlantSample, PlantStock

**Constraints:** `scientific_name` is unique among non-deleted rows (partial unique index).

**Lifecycle:** Created → Updated → Soft-deleted. Cannot delete if varieties/samples exist (RESTRICT FK).

---

## 2.2 PlantVariety

**Purpose:** A cultivar or genetic variant within a species (e.g. "Cherry Tomato" under _Solanum lycopersicum_).

| Attribute                  | Type            | Description                                   |
| -------------------------- | --------------- | --------------------------------------------- |
| `id`                       | bigint PK       |                                               |
| `plant_species_id`         | bigint FK       | References `plant_species` RESTRICT on delete |
| `name`                     | string          | Variety common name                           |
| `variety_code`             | string UNIQUE\* | Short code, unique among non-deleted          |
| `description`              | text?           |                                               |
| `image_url` / `image_path` | string?         |                                               |
| `deleted_at`               | timestamp?      |                                               |

**Relationships:** `belongsTo` PlantSpecies; `hasMany` PlantSample, PlantStock

---

## 2.3 PlantSample

**Purpose:** An individual plant specimen brought into the lab. Tracks provenance (who brought it, from where, when).

| Attribute                  | Type            | Description                        |
| -------------------------- | --------------- | ---------------------------------- |
| `id`                       | bigint PK       |                                    |
| `plant_species_id`         | bigint FK       | RESTRICT on delete                 |
| `plant_variety_id`         | bigint FK?      | SET NULL on delete                 |
| `contributor_id`           | bigint FK?      | User who brought it                |
| `sample_name`              | string          | Display name                       |
| `sample_code`              | string UNIQUE\* | Unique among non-deleted           |
| `owner_name`               | string?         | External owner name                |
| `department`               | string?         | Lab department                     |
| `origin_location`          | string?         | Geographic or institutional origin |
| `brought_at`               | date?           | Date arrived in lab                |
| `lab_location`             | enum?           | `lab_a` / `lab_b` / `lab_c`        |
| `status`                   | enum            | `active` / `inactive` / `archived` |
| `quantity`                 | integer ≥ 0     | Count of specimens                 |
| `description`              | text?           |                                    |
| `image_url` / `image_path` | string?         |                                    |
| `deleted_at`               | timestamp?      |                                    |

**Relationships:** `belongsTo` PlantSpecies, PlantVariety, User (contributor); `hasMany` PlantStock

**Lifecycle states:** `active → inactive → archived` (any direction allowed via update)

---

## 2.4 PlantStock

**Purpose:** Tracks the countable, borrowable/consumable quantity of plants derived from a sample. The _reserve mechanism_ prevents over-commitment.

| Attribute           | Type                         | Description                               |
| ------------------- | ---------------------------- | ----------------------------------------- |
| `id`                | bigint PK                    |                                           |
| `plant_species_id`  | bigint FK                    | RESTRICT on delete                        |
| `plant_variety_id`  | bigint FK?                   | SET NULL on delete                        |
| `plant_sample_id`   | bigint FK?                   | SET NULL on delete                        |
| `quantity`          | unsigned int ≥ 0             | Total physical units                      |
| `reserved_quantity` | unsigned int ≥ 0, ≤ quantity | Reserved but not yet consumed             |
| `status`            | enum                         | `available` / `reserved` / `out_of_stock` |
| `deleted_at`        | timestamp?                   |                                           |

**Computed:** `available_quantity = quantity − reserved_quantity`.

**DB Constraints:**

- `quantity >= 0` (CHECK)
- `reserved_quantity >= 0` (CHECK)
- `reserved_quantity <= quantity` (CHECK)
- `status IN ('available','reserved','out_of_stock')` (CHECK)

---

## 2.5 Chemical

**Purpose:** Master record for a chemical reagent. Tracks total stock in aggregate; batch-level detail is in ChemicalBatch.

| Attribute                  | Type             | Description                                                    |
| -------------------------- | ---------------- | -------------------------------------------------------------- |
| `id`                       | bigint PK        |                                                                |
| `common_name`              | string           | Name (e.g. "Ethanol")                                          |
| `chemical_code`            | string UNIQUE\*  | Lab code, unique among non-deleted                             |
| `category`                 | enum             | `acid` / `base` / `solvent` / `oxidizer` / `reducer` / `other` |
| `quantity`                 | unsigned int ≥ 0 | Aggregate stock in default unit                                |
| `storage_location`         | string?          | Cabinet, room                                                  |
| `expiry_date`              | date?            | Earliest expiry (informational; batch level is authoritative)  |
| `danger_level`             | enum             | `low` / `medium` / `high`                                      |
| `safety_measures`          | text?            | PPE and handling instructions                                  |
| `description`              | text?            |                                                                |
| `image_url` / `image_path` | string?          |                                                                |
| `deleted_at`               | timestamp?       |                                                                |

**Scopes:** `available()`, `lowStock($threshold)`, `expiringSoon($days)`, `expired()`, `search($term)`

---

## 2.6 ChemicalBatch

**Purpose:** One delivery/lot of a chemical. Enables FIFO stock management and granular expiry tracking.

| Attribute          | Type             | Description                            |
| ------------------ | ---------------- | -------------------------------------- |
| `id`               | bigint PK        |                                        |
| `chemical_id`      | bigint FK        | CASCADE on delete                      |
| `batch_number`     | string           | Unique per chemical (composite unique) |
| `quantity`         | unsigned int ≥ 0 | Remaining units in this batch          |
| `unit`             | string           | `ml`, `g`, `L`, etc.                   |
| `expiry_date`      | date?            |                                        |
| `supplier_name`    | string?          |                                        |
| `supplier_contact` | string?          |                                        |
| `received_at`      | date?            |                                        |
| `cost_per_unit`    | decimal(10,2)?   |                                        |
| `notes`            | text?            |                                        |
| `deleted_at`       | timestamp?       |                                        |

**Computed:** `remaining_quantity = quantity − SUM(usage_logs.quantity_used)`

---

## 2.7 ChemicalUsageLog

**Purpose:** Immutable record of a chemical consumption event. Decrements both the batch and the parent chemical quantity.

| Attribute           | Type          | Description                        |
| ------------------- | ------------- | ---------------------------------- |
| `id`                | bigint PK     |                                    |
| `chemical_id`       | bigint FK     | CASCADE on delete                  |
| `chemical_batch_id` | bigint FK?    | SET NULL on delete                 |
| `user_id`           | bigint FK     | RESTRICT on delete                 |
| `quantity_used`     | decimal(10,2) | Amount consumed                    |
| `unit`              | string        | Unit of measure                    |
| `purpose`           | string        | Why it was used                    |
| `experiment_name`   | string?       | Optional experiment label          |
| `experiment_id`     | bigint FK?    | Link to Research module experiment |
| `used_at`           | datetime      | When it was used                   |
| `notes`             | text?         |                                    |
| `deleted_at`        | timestamp?    |                                    |

---

## 2.8 Equipment

**Purpose:** Individual piece of laboratory equipment. Status-driven lifecycle.

| Attribute                  | Type            | Description                                                               |
| -------------------------- | --------------- | ------------------------------------------------------------------------- |
| `id`                       | bigint PK       |                                                                           |
| `equipment_name`           | string          |                                                                           |
| `equipment_code`           | string UNIQUE\* |                                                                           |
| `category`                 | enum            | `microscope` / `centrifuge` / `incubator` / `spectrophotometer` / `other` |
| `status`                   | enum            | `available` / `borrowed` / `in_use` / `under_maintenance`                 |
| `condition`                | enum            | `good` / `normal` / `broken`                                              |
| `location`                 | string?         | Physical location                                                         |
| `manufacturer`             | string?         |                                                                           |
| `model_name`               | string?         |                                                                           |
| `serial_number`            | string UNIQUE\* |                                                                           |
| `purchase_date`            | date?           |                                                                           |
| `purchase_price`           | decimal(10,2)?  |                                                                           |
| `description`              | text?           |                                                                           |
| `image_url` / `image_path` | string?         |                                                                           |
| `deleted_at`               | timestamp?      |                                                                           |

**Computed:** `is_borrowable = (status === AVAILABLE && condition !== BROKEN)`

**DB Constraints:**

- `status IN (...)` (CHECK)
- `condition IN (...)` (CHECK)

---

## 2.9 MaintenanceRecord

**Purpose:** Log a past or scheduled maintenance event for a piece of equipment.

| Attribute            | Type           | Description                                                |
| -------------------- | -------------- | ---------------------------------------------------------- |
| `id`                 | bigint PK      |                                                            |
| `equipment_id`       | bigint FK      | CASCADE on delete                                          |
| `performed_by`       | bigint FK?     | User, SET NULL on delete                                   |
| `maintenance_type`   | enum           | `preventive` / `corrective` / `calibration` / `inspection` |
| `description`        | text           | What was done                                              |
| `technician_name`    | string?        | External technician                                        |
| `technician_contact` | string?        |                                                            |
| `cost`               | decimal(10,2)? |                                                            |
| `started_at`         | date           |                                                            |
| `completed_at`       | date?          | Null = in progress                                         |
| `next_service_date`  | date?          | Scheduled follow-up                                        |
| `notes`              | text?          |                                                            |
| `deleted_at`         | timestamp?     |                                                            |

**Scopes:** `upcoming($days)`, `overdue()`, `ofType($type)`

---

## 2.10 BorrowRecord

**Purpose:** Polymorphic record tracking the borrow lifecycle for any inventory item. One table covers Equipment, Chemical, and PlantSample.

| Attribute         | Type         | Description                                                  |
| ----------------- | ------------ | ------------------------------------------------------------ |
| `id`              | bigint PK    |                                                              |
| `user_id`         | bigint FK    | Who borrows; RESTRICT on delete                              |
| `borrowable_type` | string       | Morph type: `equipment`, `chemical`, `plant_sample`          |
| `borrowable_id`   | bigint       | PK of the borrowed item                                      |
| `quantity`        | unsigned int |                                                              |
| `status`          | enum         | `pending` / `borrowed` / `returned` / `overdue` / `rejected` |
| `borrowed_at`     | datetime     | When borrow started                                          |
| `due_at`          | datetime?    | Return deadline                                              |
| `returned_at`     | datetime?    | Actual return datetime                                       |
| `reviewed_by`     | bigint FK?   | Approver                                                     |
| `reviewed_at`     | datetime?    |                                                              |
| `rejected_reason` | string?      |                                                              |
| `notes`           | text?        |                                                              |

**State Machine:**

```
PENDING ─► BORROWED ─► RETURNED
        └─► REJECTED       │
                      ◄────┘ (OVERDUE is an automatic flag,
                               still transitions to RETURNED)
```

**DB Constraints:** `status IN ('pending','borrowed','returned','overdue','rejected')` (CHECK)

---

## 2.11 Transaction

**Purpose:** Append-only audit log. Every mutation to any inventory item creates one row here. Polymorphic — one table covers everything.

| Attribute              | Type          | Description                                                                           |
| ---------------------- | ------------- | ------------------------------------------------------------------------------------- |
| `id`                   | bigint PK     |                                                                                       |
| `user_id`              | bigint FK     | Actor; RESTRICT on delete                                                             |
| `transactionable_type` | string        | Morph type of item                                                                    |
| `transactionable_id`   | bigint        | PK of item                                                                            |
| `action`               | enum          | `added` / `updated` / `consumed` / `borrowed` / `returned` / `harvested` / `disposed` |
| `quantity`             | decimal(8,2)? | For volume-based actions                                                              |
| `note`                 | string?       | Human-readable context                                                                |

**Design principle:** Written by `TransactionService` only. Never edited or deleted.

---

## 2.12 LocationHistory

**Purpose:** Tracks physical movement of inventory items (equipment, samples) for compliance and traceability.

| Attribute        | Type       | Description              |
| ---------------- | ---------- | ------------------------ |
| `id`             | bigint PK  |                          |
| `trackable_type` | string     | Morph type               |
| `trackable_id`   | bigint     | Item PK                  |
| `from_location`  | string?    | Previous location        |
| `to_location`    | string     | New location             |
| `moved_by`       | bigint FK? | User, SET NULL on delete |
| `reason`         | text?      | Why it was moved         |
| `created_at`     | timestamp  |                          |

---

## 2.13 Achievement

**Purpose:** Gamification record. Defines criteria for earning a badge (e.g. "Contributed 10 samples").

| Attribute        | Type         | Description                           |
| ---------------- | ------------ | ------------------------------------- |
| `id`             | bigint PK    |                                       |
| `name`           | string       | Badge name                            |
| `description`    | text?        |                                       |
| `criteria_type`  | string       | e.g. `samples_count`, `borrows_count` |
| `criteria_value` | unsigned int | Threshold                             |
| `icon`           | string?      | Icon identifier                       |

**Pivot table:** `user_achievements` (`user_id`, `achievement_id`, `earned_at`) with unique constraint on `(user_id, achievement_id)`.

---

## 2.14 UserDocument

**Purpose:** File attachments (PDF, images, certificates) attached to a user profile.

| Attribute     | Type         | Description                                |
| ------------- | ------------ | ------------------------------------------ |
| `id`          | bigint PK    |                                            |
| `user_id`     | bigint FK    | CASCADE on delete                          |
| `title`       | string       |                                            |
| `file_path`   | string       | Storage-relative path                      |
| `file_type`   | string(20)   | `pdf`, `doc`, `image`, `certificate`       |
| `file_size`   | unsigned int | Bytes                                      |
| `description` | text?        |                                            |
| `deleted_at`  | timestamp?   | File is deleted from disk on `forceDelete` |

---

# 3. Database Design

## 3.1 Full Schema

### `plant_species`

```sql
CREATE TABLE plant_species (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    common_name        VARCHAR(255) NOT NULL,
    khmer_name         VARCHAR(255),
    scientific_name    VARCHAR(255) NOT NULL,
    family             VARCHAR(255),
    growth_type        ENUM('annual','perennial','biennial'),
    native_region      VARCHAR(255),
    propagation_method VARCHAR(255),
    description        TEXT,
    image_url          VARCHAR(255),
    image_path         VARCHAR(255),
    created_at         TIMESTAMP,
    updated_at         TIMESTAMP,
    deleted_at         TIMESTAMP,
    INDEX(scientific_name)
);
-- Partial unique index (soft-delete aware, PostgreSQL):
-- CREATE UNIQUE INDEX idx_scientific_name_unique ON plant_species(scientific_name) WHERE deleted_at IS NULL;
```

### `plant_varieties`

```sql
CREATE TABLE plant_varieties (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plant_species_id BIGINT UNSIGNED NOT NULL,
    name             VARCHAR(255) NOT NULL,
    variety_code     VARCHAR(255) NOT NULL,
    description      TEXT,
    image_url        VARCHAR(255),
    image_path       VARCHAR(255),
    created_at       TIMESTAMP,
    updated_at       TIMESTAMP,
    deleted_at       TIMESTAMP,
    FOREIGN KEY (plant_species_id) REFERENCES plant_species(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX(plant_species_id),
    INDEX(variety_code)
);
-- CREATE UNIQUE INDEX idx_variety_code_unique ON plant_varieties(variety_code) WHERE deleted_at IS NULL;
```

### `plant_samples`

```sql
CREATE TABLE plant_samples (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plant_species_id BIGINT UNSIGNED NOT NULL,
    plant_variety_id BIGINT UNSIGNED,
    contributor_id   BIGINT UNSIGNED,
    sample_name      VARCHAR(255) NOT NULL,
    sample_code      VARCHAR(100) NOT NULL,
    owner_name       VARCHAR(255),
    department       VARCHAR(255),
    origin_location  VARCHAR(255),
    brought_at       DATE,
    lab_location     ENUM('lab_a','lab_b','lab_c'),
    status           ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    quantity         INT NOT NULL DEFAULT 0,
    description      TEXT,
    image_url        VARCHAR(255),
    image_path       VARCHAR(255),
    created_at       TIMESTAMP,
    updated_at       TIMESTAMP,
    deleted_at       TIMESTAMP,
    FOREIGN KEY (plant_species_id) REFERENCES plant_species(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (plant_variety_id) REFERENCES plant_varieties(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (contributor_id)   REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX(plant_species_id, plant_variety_id, status),
    INDEX(sample_code),
    INDEX(department),
    INDEX(lab_location),
    CONSTRAINT chk_sample_status CHECK (status IN ('active','inactive','archived'))
);
-- CREATE UNIQUE INDEX idx_sample_code_unique ON plant_samples(sample_code) WHERE deleted_at IS NULL;
```

### `plant_stocks`

```sql
CREATE TABLE plant_stocks (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plant_species_id BIGINT UNSIGNED NOT NULL,
    plant_variety_id BIGINT UNSIGNED,
    plant_sample_id  BIGINT UNSIGNED,
    quantity         INT UNSIGNED NOT NULL DEFAULT 0,
    reserved_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    status           ENUM('available','reserved','out_of_stock') NOT NULL DEFAULT 'available',
    created_at       TIMESTAMP,
    updated_at       TIMESTAMP,
    deleted_at       TIMESTAMP,
    FOREIGN KEY (plant_species_id) REFERENCES plant_species(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (plant_variety_id) REFERENCES plant_varieties(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (plant_sample_id)  REFERENCES plant_samples(id)   ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX(plant_species_id, plant_variety_id, plant_sample_id, status),
    CONSTRAINT chk_stock_qty_non_negative      CHECK (quantity >= 0),
    CONSTRAINT chk_stock_reserved_non_negative CHECK (reserved_quantity >= 0),
    CONSTRAINT chk_stock_reserved_lte_quantity CHECK (reserved_quantity <= quantity),
    CONSTRAINT chk_stock_status                CHECK (status IN ('available','reserved','out_of_stock'))
);
```

### `chemicals`

```sql
CREATE TABLE chemicals (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    common_name      VARCHAR(255) NOT NULL,
    chemical_code    VARCHAR(100),
    category         ENUM('acid','base','solvent','oxidizer','reducer','other') NOT NULL DEFAULT 'other',
    quantity         INT UNSIGNED NOT NULL DEFAULT 0,
    storage_location VARCHAR(255),
    expiry_date      DATE,
    danger_level     ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    safety_measures  TEXT,
    description      TEXT,
    image_url        VARCHAR(255),
    image_path       VARCHAR(255),
    created_at       TIMESTAMP,
    updated_at       TIMESTAMP,
    deleted_at       TIMESTAMP,
    INDEX(chemical_code),
    INDEX(common_name),
    INDEX(category, expiry_date, danger_level),
    CONSTRAINT chk_chemical_qty_non_negative CHECK (quantity >= 0)
);
-- CREATE UNIQUE INDEX idx_chemical_code_unique ON chemicals(chemical_code) WHERE deleted_at IS NULL;
```

### `chemical_batches`

```sql
CREATE TABLE chemical_batches (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chemical_id      BIGINT UNSIGNED NOT NULL,
    batch_number     VARCHAR(100) NOT NULL,
    quantity         INT UNSIGNED NOT NULL DEFAULT 0,
    unit             VARCHAR(20) NOT NULL DEFAULT 'ml',
    expiry_date      DATE,
    supplier_name    VARCHAR(255),
    supplier_contact VARCHAR(255),
    received_at      DATE,
    cost_per_unit    DECIMAL(10,2),
    notes            TEXT,
    created_at       TIMESTAMP,
    updated_at       TIMESTAMP,
    deleted_at       TIMESTAMP,
    FOREIGN KEY (chemical_id) REFERENCES chemicals(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY (chemical_id, batch_number),
    INDEX(chemical_id, expiry_date)
);
```

### `chemical_usage_logs`

```sql
CREATE TABLE chemical_usage_logs (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chemical_id       BIGINT UNSIGNED NOT NULL,
    chemical_batch_id BIGINT UNSIGNED,
    user_id           BIGINT UNSIGNED NOT NULL,
    quantity_used     DECIMAL(10,2) NOT NULL,
    unit              VARCHAR(20) NOT NULL DEFAULT 'ml',
    purpose           VARCHAR(255) NOT NULL,
    experiment_name   VARCHAR(255),
    experiment_id     BIGINT UNSIGNED,
    used_at           DATETIME NOT NULL,
    notes             TEXT,
    created_at        TIMESTAMP,
    updated_at        TIMESTAMP,
    deleted_at        TIMESTAMP,
    FOREIGN KEY (chemical_id)       REFERENCES chemicals(id)       ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (chemical_batch_id) REFERENCES chemical_batches(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (user_id)           REFERENCES users(id)            ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX(chemical_id, used_at),
    INDEX(user_id, used_at)
);
```

### `equipment`

```sql
CREATE TABLE equipment (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_name   VARCHAR(255) NOT NULL,
    equipment_code   VARCHAR(100),
    category         ENUM('microscope','centrifuge','incubator','spectrophotometer','other') NOT NULL DEFAULT 'other',
    status           ENUM('available','borrowed','in_use','under_maintenance') NOT NULL DEFAULT 'available',
    condition        ENUM('good','normal','broken') NOT NULL DEFAULT 'good',
    location         VARCHAR(255),
    manufacturer     VARCHAR(255),
    model_name       VARCHAR(255),
    serial_number    VARCHAR(255),
    purchase_date    DATE,
    purchase_price   DECIMAL(10,2),
    description      TEXT,
    image_url        VARCHAR(255),
    image_path       VARCHAR(255),
    created_at       TIMESTAMP,
    updated_at       TIMESTAMP,
    deleted_at       TIMESTAMP,
    INDEX(equipment_code),
    INDEX(equipment_name),
    INDEX(serial_number),
    INDEX(category, status, condition),
    CONSTRAINT chk_equipment_status    CHECK (status IN ('available','borrowed','in_use','under_maintenance')),
    CONSTRAINT chk_equipment_condition CHECK (condition IN ('good','normal','broken'))
);
-- CREATE UNIQUE INDEX idx_equipment_code_unique ON equipment(equipment_code)   WHERE deleted_at IS NULL;
-- CREATE UNIQUE INDEX idx_serial_number_unique  ON equipment(serial_number)    WHERE deleted_at IS NULL;
```

### `maintenance_records`

```sql
CREATE TABLE maintenance_records (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_id     BIGINT UNSIGNED NOT NULL,
    performed_by     BIGINT UNSIGNED,
    maintenance_type VARCHAR(20) NOT NULL DEFAULT 'preventive',
    description      TEXT NOT NULL,
    technician_name  VARCHAR(255),
    technician_contact VARCHAR(255),
    cost             DECIMAL(10,2),
    started_at       DATE NOT NULL,
    completed_at     DATE,
    next_service_date DATE,
    notes            TEXT,
    created_at       TIMESTAMP,
    updated_at       TIMESTAMP,
    deleted_at       TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id)     ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX(equipment_id, maintenance_type),
    INDEX(next_service_date)
);
```

### `borrow_records`

```sql
CREATE TABLE borrow_records (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    borrowable_type VARCHAR(255) NOT NULL,
    borrowable_id   BIGINT UNSIGNED NOT NULL,
    quantity        INT UNSIGNED NOT NULL DEFAULT 1,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',
    borrowed_at     DATETIME NOT NULL,
    due_at          DATETIME,
    returned_at     DATETIME,
    reviewed_by     BIGINT UNSIGNED,
    reviewed_at     DATETIME,
    rejected_reason TEXT,
    notes           TEXT,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX(borrowable_type, borrowable_id),
    INDEX(status, due_at),
    INDEX(borrowed_at),
    CONSTRAINT chk_borrow_status CHECK (status IN ('pending','borrowed','returned','overdue','rejected'))
);
```

### `transactions`

```sql
CREATE TABLE transactions (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id               BIGINT UNSIGNED NOT NULL,
    transactionable_type  VARCHAR(255) NOT NULL,
    transactionable_id    BIGINT UNSIGNED NOT NULL,
    action                ENUM('added','updated','consumed','borrowed','returned','harvested','disposed') NOT NULL,
    quantity              DECIMAL(8,2),
    note                  VARCHAR(255),
    created_at            TIMESTAMP,
    updated_at            TIMESTAMP,
    deleted_at            TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX(transactionable_type, transactionable_id),
    INDEX(action, created_at)
);
```

### `location_histories`

```sql
CREATE TABLE location_histories (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trackable_type VARCHAR(255) NOT NULL,
    trackable_id   BIGINT UNSIGNED NOT NULL,
    from_location  VARCHAR(255),
    to_location    VARCHAR(255) NOT NULL,
    moved_by       BIGINT UNSIGNED,
    reason         TEXT,
    created_at     TIMESTAMP,
    updated_at     TIMESTAMP,
    FOREIGN KEY (moved_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX(trackable_type, trackable_id, created_at)
);
```

### `achievements` & `user_achievements`

```sql
CREATE TABLE achievements (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(255) NOT NULL,
    description    TEXT,
    criteria_type  VARCHAR(255) NOT NULL,
    criteria_value INT UNSIGNED NOT NULL DEFAULT 1,
    icon           VARCHAR(255),
    created_at     TIMESTAMP,
    updated_at     TIMESTAMP
);

CREATE TABLE user_achievements (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        BIGINT UNSIGNED NOT NULL,
    achievement_id BIGINT UNSIGNED NOT NULL,
    earned_at      DATETIME NOT NULL,
    created_at     TIMESTAMP,
    updated_at     TIMESTAMP,
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY (user_id, achievement_id)
);
```

### `user_documents`

```sql
CREATE TABLE user_documents (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    title       VARCHAR(255) NOT NULL,
    file_path   VARCHAR(255) NOT NULL,
    file_type   VARCHAR(20) NOT NULL,
    file_size   INT UNSIGNED NOT NULL,
    description TEXT,
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP,
    deleted_at  TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX(user_id, file_type)
);
```

## 3.2 ERD Explanation

```
users
 ├── plant_samples (contributor_id)         [1 user → many samples]
 ├── borrow_records (user_id)               [1 user → many borrows]
 ├── transactions (user_id)                 [1 user → many transactions]
 ├── chemical_usage_logs (user_id)          [1 user → many usage logs]
 ├── maintenance_records (performed_by)     [1 user → many maintenance records]
 ├── user_achievements (pivot)              [M:M with achievements]
 └── user_documents (user_id)              [1 user → many documents]

plant_species
 ├── plant_varieties (plant_species_id)     [1 species → many varieties]
 ├── plant_samples   (plant_species_id)     [1 species → many samples]
 └── plant_stocks    (plant_species_id)     [1 species → many stocks]

plant_varieties
 ├── plant_samples (plant_variety_id)       [1 variety → many samples]
 └── plant_stocks  (plant_variety_id)       [1 variety → many stocks]

plant_samples
 └── plant_stocks (plant_sample_id)         [1 sample → many stocks]

chemicals
 ├── chemical_batches    (chemical_id)      [1 chemical → many batches]
 └── chemical_usage_logs (chemical_id)      [1 chemical → many usage logs]

chemical_batches
 └── chemical_usage_logs (chemical_batch_id)[1 batch → many usage logs]

equipment
 └── maintenance_records (equipment_id)     [1 equipment → many maintenance]

-- Polymorphic (borrow_records.borrowable → equipment | chemical | plant_sample)
-- Polymorphic (transactions.transactionable → any inventory model)
-- Polymorphic (location_histories.trackable → equipment | plant_sample)
```

---

# 4. Project Architecture

## 4.1 Directory Structure

```
app/
├── Concerns/                         # Shared traits
│   ├── ApiResponse.php               # Standardized JSON helpers
│   ├── HasActivityLogging.php        # Spatie activity log trait
│   ├── HasImageUpload.php            # Image storage helpers
│   ├── HasTransactions.php           # MorphMany transactions relation
│   ├── ManagesBorrowableStock.php    # Stock dec/inc helpers for BorrowService
│   └── EscapesSearchTerm.php        # LIKE query escaping
│
├── Enums/                            # PHP 8.1 backed enums
│   ├── BorrowStatus.php
│   ├── ChemicalCategory.php
│   ├── DangerLevel.php
│   ├── EquipmentCategory.php
│   ├── EquipmentCondition.php
│   ├── EquipmentStatus.php
│   ├── LabLocation.php
│   ├── MaintenanceType.php
│   ├── PlantGrowthType.php
│   ├── SampleStatus.php
│   ├── StockStatus.php
│   ├── TransactionAction.php
│   └── UserRole.php
│
├── Exceptions/
│   └── InsufficientStockException.php
│
├── Modules/
│   ├── Core/
│   │   └── Models/
│   │       └── User.php              # Auth user model
│   │
│   └── Inventory/
│       ├── Controllers/              # HTTP layer only (no business logic)
│       │   ├── AchievementController.php
│       │   ├── BorrowRecordController.php
│       │   ├── ChemicalBatchController.php
│       │   ├── ChemicalController.php
│       │   ├── ChemicalUsageController.php
│       │   ├── DashboardController.php
│       │   ├── EquipmentController.php
│       │   ├── MaintenanceRecordController.php
│       │   ├── PlantSampleController.php
│       │   ├── PlantSpeciesController.php
│       │   ├── PlantStockController.php
│       │   ├── PlantVarietyController.php
│       │   ├── ProfileController.php
│       │   ├── ReportController.php
│       │   ├── TransactionController.php
│       │   └── UserDocumentController.php
│       │
│       ├── Models/                   # Eloquent models
│       │   ├── Achievement.php
│       │   ├── BorrowRecord.php
│       │   ├── Chemical.php
│       │   ├── ChemicalBatch.php
│       │   ├── ChemicalUsageLog.php
│       │   ├── Equipment.php
│       │   ├── LocationHistory.php
│       │   ├── MaintenanceRecord.php
│       │   ├── PlantSample.php
│       │   ├── PlantSpecies.php
│       │   ├── PlantStock.php
│       │   ├── PlantVariety.php
│       │   ├── Transaction.php
│       │   └── UserDocument.php
│       │
│       ├── Policies/                 # Authorization policies
│       │   ├── BorrowRecordPolicy.php
│       │   ├── ChemicalBatchPolicy.php
│       │   ├── ChemicalPolicy.php
│       │   ├── ChemicalUsageLogPolicy.php
│       │   ├── EquipmentPolicy.php
│       │   ├── MaintenanceRecordPolicy.php
│       │   ├── PlantSamplePolicy.php
│       │   ├── PlantSpeciesPolicy.php
│       │   ├── PlantStockPolicy.php
│       │   ├── PlantVarietyPolicy.php
│       │   └── TransactionPolicy.php
│       │
│       ├── Requests/                 # Form requests (validation + auth)
│       │   ├── Borrow/
│       │   ├── Chemical/
│       │   ├── Equipment/
│       │   ├── Maintenance/
│       │   ├── Profile/
│       │   ├── Sample/
│       │   ├── Species/
│       │   ├── Stock/
│       │   ├── UserDocument/
│       │   └── Variety/
│       │
│       ├── Resources/                # API response transformers
│       │   ├── BorrowRecordResource.php
│       │   ├── ChemicalBatchResource.php
│       │   ├── ChemicalResource.php
│       │   ├── ChemicalUsageLogResource.php
│       │   ├── EquipmentResource.php
│       │   ├── MaintenanceRecordResource.php
│       │   ├── PlantSampleResource.php
│       │   ├── PlantSpeciesResource.php
│       │   ├── PlantStockResource.php
│       │   ├── PlantVarietyResource.php
│       │   ├── TransactionResource.php
│       │   ├── UserDocumentResource.php
│       │   └── UserResource.php
│       │
│       ├── Routes/
│       │   └── api.php               # All inventory routes
│       │
│       └── Services/
│           ├── AchievementService.php
│           ├── BorrowService.php
│           ├── ChemicalUsageService.php
│           ├── DashboardService.php
│           ├── InventoryCrudService.php
│           ├── ProfileService.php
│           ├── StockService.php
│           ├── TransactionService.php
│           ├── UserDocumentService.php
│           └── Reports/
│               ├── BorrowedItemsReportQuery.php
│               ├── ChemicalUsageReportQuery.php
│               ├── ExpiredItemsReportQuery.php
│               ├── InventoryReportQuery.php
│               ├── ReportCsvHelper.php
│               └── UserActivityReportQuery.php
```

## 4.2 Layer Responsibilities

### Controllers

- **Single responsibility**: parse HTTP input, authorize, call service, return resource.
- **No raw SQL**. Never contains `DB::` calls (except where explicitly unavoidable and documented).
- **No business logic**. If it needs more than 5 lines of PHP, move it to a service.

### Form Requests

- **Double duty**: authorization (via `authorize()`) AND validation (via `rules()`).
- Authorization calls `$this->user()->hasPermissionTo(...)` using Spatie Permission.
- Validation uses `Rule::enum()` for enum types — never raw string arrays.

### Models

- Declare `$fillable`, `casts()`, relationships, and scopes.
- Scopes (`scopeAvailable`, `scopeLowStock`, etc.) are the DRY alternative to scattered `where()` chains.
- Computed attributes (e.g. `getAvailableQuantityAttribute()`) live on the model.

### Services

| Service                | Responsibility                                                                                 |
| ---------------------- | ---------------------------------------------------------------------------------------------- |
| `TransactionService`   | Write one Transaction row. Used by all other services. Never call directly from controllers.   |
| `InventoryCrudService` | Generic create/update/delete with automatic transaction logging. Use for all standard CRUD.    |
| `StockService`         | Atomic stock mutations: `consume()`, `reserve()`, `release()`, `syncStatus()`.                 |
| `BorrowService`        | Full borrow lifecycle: `requestBorrow()`, `approveBorrow()`, `rejectBorrow()`, `returnItem()`. |
| `ChemicalUsageService` | Log usage, decrement chemical + batch quantity atomically.                                     |
| `DashboardService`     | All aggregate queries, each cached.                                                            |
| `AchievementService`   | Check criteria and award achievements.                                                         |
| `ProfileService`       | User profile data aggregation.                                                                 |
| `Report/*`             | Read-only query objects for each report type.                                                  |

### Policies

- Map Spatie permission names to policy methods.
- Convention: `<resource>.<action>` e.g. `chemicals.create`, `borrows.approve`.
- Self-ownership rule: users can always `view` their own borrow records, regardless of permission.

### Resources (API Resources)

- Transform model → JSON array for every API response.
- Always explicit: list every field — no `$this->resource->toArray()`.
- Relationships are conditionally loaded: `$this->whenLoaded('species', ...)`.

---

# 5. API Design

## 5.1 Route Structure

All routes are prefixed with `/api/` and protected by `middleware('auth:api')`.
Authentication is JWT via `php-open-source-saver/jwt-auth` with the `api` guard.

## 5.2 Endpoint Catalogue

### Dashboard

| Method | URL              | Permission    | Description                                        |
| ------ | ---------------- | ------------- | -------------------------------------------------- |
| GET    | `/api/dashboard` | authenticated | Counts, alerts, recent activity, status breakdowns |

**Response:**

```json
{
  "counts": {
    "plant_species": 42,
    "plant_varieties": 150,
    "plant_samples": 320,
    "plant_stocks": 315,
    "chemicals": 85,
    "chemical_batches": 200,
    "equipment": 45,
    "users": 28,
    "active_borrows": 12,
    "total_borrows": 198
  },
  "alerts": {
    "expiring_chemicals": 3,
    "expired_chemicals": 1,
    "overdue_borrows": 2,
    "pending_borrows": 5,
    "overdue_maintenance": 1,
    "low_stock_chemicals": 7
  },
  "recent_activity": [ ... ],
  "status_breakdown": { ... }
}
```

---

### Plant Species

| Method | URL                       | Permission      | Description                  |
| ------ | ------------------------- | --------------- | ---------------------------- |
| GET    | `/api/plant-species`      | `plants.view`   | List (paginated, filterable) |
| POST   | `/api/plant-species`      | `plants.create` | Create                       |
| GET    | `/api/plant-species/{id}` | `plants.view`   | Show                         |
| PUT    | `/api/plant-species/{id}` | `plants.edit`   | Update                       |
| DELETE | `/api/plant-species/{id}` | `plants.delete` | Soft delete                  |

**Query Parameters (index):** `search`, `family`, `growth_type`

**Store Payload:**

```json
{
    "common_name": "Tomato",
    "khmer_name": "ប៉េងប៉ោះ",
    "scientific_name": "Solanum lycopersicum",
    "family": "Solanaceae",
    "growth_type": "annual",
    "native_region": "South America",
    "propagation_method": "seed",
    "description": "...",
    "image": "<file upload>"
}
```

**Validation Rules:**

- `common_name`: required, string, max 255
- `scientific_name`: required, unique among non-deleted, max 255
- `growth_type`: required, must be `annual|perennial|biennial`
- `image`: optional, mimes: jpg/jpeg/png/webp, max 2MB

---

### Plant Varieties

| Method | URL                         | Permission      | Description |
| ------ | --------------------------- | --------------- | ----------- |
| GET    | `/api/plant-varieties`      | `plants.view`   | List        |
| POST   | `/api/plant-varieties`      | `plants.create` | Create      |
| GET    | `/api/plant-varieties/{id}` | `plants.view`   | Show        |
| PUT    | `/api/plant-varieties/{id}` | `plants.edit`   | Update      |
| DELETE | `/api/plant-varieties/{id}` | `plants.delete` | Soft delete |

**Store Payload:**

```json
{
    "plant_species_id": 1,
    "name": "Cherry Tomato",
    "variety_code": "CHR-001",
    "description": "..."
}
```

**Validation Rules:**

- `plant_species_id`: required, exists in `plant_species`
- `variety_code`: required, unique among non-deleted, max 100

---

### Plant Samples

| Method | URL                       | Permission      | Description                                          |
| ------ | ------------------------- | --------------- | ---------------------------------------------------- |
| GET    | `/api/plant-samples`      | `plants.view`   | List (search, filter by status/species/lab_location) |
| POST   | `/api/plant-samples`      | `plants.create` | Create                                               |
| GET    | `/api/plant-samples/{id}` | `plants.view`   | Show                                                 |
| PUT    | `/api/plant-samples/{id}` | `plants.edit`   | Update                                               |
| DELETE | `/api/plant-samples/{id}` | `plants.delete` | Soft delete                                          |

**Store Payload:**

```json
{
    "sample_name": "Cherry T. Batch A",
    "sample_code": "CTA-001",
    "plant_species_id": 1,
    "plant_variety_id": 2,
    "owner_name": "Dr. Sok",
    "department": "Plant Biology",
    "origin_location": "Siem Reap",
    "brought_at": "2026-01-15",
    "lab_location": "lab_a",
    "status": "active",
    "quantity": 50,
    "description": "..."
}
```

---

### Plant Stocks

| Method | URL                      | Permission      | Description                         |
| ------ | ------------------------ | --------------- | ----------------------------------- |
| GET    | `/api/plant-stocks`      | `plants.view`   | List (filter by species_id, status) |
| POST   | `/api/plant-stocks`      | `plants.create` | Create                              |
| GET    | `/api/plant-stocks/{id}` | `plants.view`   | Show                                |
| PUT    | `/api/plant-stocks/{id}` | `plants.edit`   | Update                              |
| DELETE | `/api/plant-stocks/{id}` | `plants.delete` | Soft delete                         |

**Store Payload:**

```json
{
    "plant_species_id": 1,
    "plant_variety_id": 2,
    "plant_sample_id": 5,
    "quantity": 100,
    "reserved_quantity": 0,
    "status": "available"
}
```

---

### Chemicals

| Method | URL                   | Permission         | Description                                                                     |
| ------ | --------------------- | ------------------ | ------------------------------------------------------------------------------- |
| GET    | `/api/chemicals`      | `chemicals.view`   | List (search, category, available_only, expired_only, low_stock, expiring_soon) |
| POST   | `/api/chemicals`      | `chemicals.create` | Create                                                                          |
| GET    | `/api/chemicals/{id}` | `chemicals.view`   | Show                                                                            |
| PUT    | `/api/chemicals/{id}` | `chemicals.edit`   | Update                                                                          |
| DELETE | `/api/chemicals/{id}` | `chemicals.delete` | Soft delete                                                                     |

**Store Payload:**

```json
{
    "common_name": "Ethanol",
    "chemical_code": "ETH-001",
    "category": "solvent",
    "quantity": 500,
    "storage_location": "Cabinet A-3",
    "expiry_date": "2027-06-01",
    "danger_level": "medium",
    "safety_measures": "Wear gloves and goggles. No open flames.",
    "description": "..."
}
```

---

### Chemical Batches

| Method | URL                          | Permission                | Description |
| ------ | ---------------------------- | ------------------------- | ----------- |
| GET    | `/api/chemical-batches`      | `chemical_batches.view`   | List        |
| POST   | `/api/chemical-batches`      | `chemical_batches.create` | Create      |
| GET    | `/api/chemical-batches/{id}` | `chemical_batches.view`   | Show        |
| PUT    | `/api/chemical-batches/{id}` | `chemical_batches.edit`   | Update      |
| DELETE | `/api/chemical-batches/{id}` | `chemical_batches.delete` | Soft delete |

**Store Payload:**

```json
{
    "chemical_id": 5,
    "batch_number": "LOT-2026-001",
    "quantity": 200,
    "unit": "ml",
    "expiry_date": "2027-03-01",
    "supplier_name": "Sigma-Aldrich",
    "supplier_contact": "orders@sigma.com",
    "received_at": "2026-01-10",
    "cost_per_unit": 0.5,
    "notes": "..."
}
```

---

### Chemical Usage Logs

| Method | URL                             | Permission              | Description               |
| ------ | ------------------------------- | ----------------------- | ------------------------- |
| GET    | `/api/chemical-usage-logs`      | `chemical_usage.view`   | List                      |
| POST   | `/api/chemical-usage-logs`      | `chemical_usage.create` | Create (decrements stock) |
| GET    | `/api/chemical-usage-logs/{id}` | `chemical_usage.view`   | Show                      |

**Store Payload:**

```json
{
    "chemical_id": 5,
    "chemical_batch_id": 12,
    "quantity_used": 25.5,
    "unit": "ml",
    "purpose": "DNA extraction",
    "experiment_name": "EXP-2026-042",
    "used_at": "2026-03-12T10:30:00",
    "notes": "..."
}
```

---

### Equipment

| Method | URL                   | Permission         | Description                                |
| ------ | --------------------- | ------------------ | ------------------------------------------ |
| GET    | `/api/equipment`      | `equipment.view`   | List (search, status, category, condition) |
| POST   | `/api/equipment`      | `equipment.create` | Create                                     |
| GET    | `/api/equipment/{id}` | `equipment.view`   | Show                                       |
| PUT    | `/api/equipment/{id}` | `equipment.edit`   | Update                                     |
| DELETE | `/api/equipment/{id}` | `equipment.delete` | Soft delete                                |

**Store Payload:**

```json
{
    "equipment_name": "Olympus BX53 Microscope",
    "equipment_code": "MIC-001",
    "category": "microscope",
    "status": "available",
    "condition": "good",
    "location": "Lab A - Bench 3",
    "manufacturer": "Olympus",
    "serial_number": "OL-2024-5523",
    "purchase_date": "2024-03-15",
    "purchase_price": 15000.0
}
```

---

### Maintenance Records

| Method | URL                             | Permission         | Description |
| ------ | ------------------------------- | ------------------ | ----------- |
| GET    | `/api/maintenance-records`      | `equipment.view`   | List        |
| POST   | `/api/maintenance-records`      | `equipment.create` | Create      |
| GET    | `/api/maintenance-records/{id}` | `equipment.view`   | Show        |
| PUT    | `/api/maintenance-records/{id}` | `equipment.edit`   | Update      |
| DELETE | `/api/maintenance-records/{id}` | `equipment.delete` | Soft delete |

---

### Borrow Records

| Method | URL                                | Permission                     | Description                                                    |
| ------ | ---------------------------------- | ------------------------------ | -------------------------------------------------------------- |
| GET    | `/api/borrow-records`              | `borrows.view`                 | List (filter type, user_id, status, active_only, overdue_only) |
| POST   | `/api/borrow-records`              | `borrows.create`               | Create (direct borrow if `borrows.approve`, else PENDING)      |
| GET    | `/api/borrow-records/{id}`         | `borrows.view` (or own record) | Show                                                           |
| GET    | `/api/borrow-records/overdue`      | `borrows.view`                 | Overdue list                                                   |
| GET    | `/api/borrow-records/pending`      | `borrows.view`                 | Pending list                                                   |
| POST   | `/api/borrow-records/{id}/return`  | `borrows.return`               | Return item                                                    |
| POST   | `/api/borrow-records/{id}/approve` | `borrows.approve`              | Approve pending                                                |
| POST   | `/api/borrow-records/{id}/reject`  | `borrows.approve`              | Reject pending                                                 |

**Store Payload:**

```json
{
    "user_id": 12,
    "borrowable_type": "equipment",
    "borrowable_id": 5,
    "quantity": 1,
    "due_at": "2026-04-01T17:00:00",
    "notes": "For student project"
}
```

**Reject Payload:**

```json
{
    "rejected_reason": "Item is under scheduled maintenance this week."
}
```

---

### Transactions

| Method | URL                      | Permission          | Description                |
| ------ | ------------------------ | ------------------- | -------------------------- |
| GET    | `/api/transactions`      | `transactions.view` | List (read-only audit log) |
| GET    | `/api/transactions/{id}` | `transactions.view` | Show                       |

---

### Achievements

| Method | URL                                      | Permission            | Description      |
| ------ | ---------------------------------------- | --------------------- | ---------------- |
| GET    | `/api/achievements`                      | authenticated         | List             |
| POST   | `/api/achievements`                      | `achievements.manage` | Create           |
| GET    | `/api/achievements/{id}`                 | authenticated         | Show             |
| PUT    | `/api/achievements/{id}`                 | `achievements.manage` | Update           |
| DELETE | `/api/achievements/{id}`                 | `achievements.manage` | Delete           |
| POST   | `/api/achievements/{id}/assign/{userId}` | `achievements.manage` | Award to user    |
| DELETE | `/api/achievements/{id}/revoke/{userId}` | `achievements.manage` | Revoke from user |

---

### User Documents

| Method | URL                                 | Permission    | Description                       |
| ------ | ----------------------------------- | ------------- | --------------------------------- |
| GET    | `/api/user-documents`               | authenticated | List (own docs or all if manager) |
| POST   | `/api/user-documents`               | authenticated | Upload                            |
| GET    | `/api/user-documents/{id}`          | authenticated | Show metadata                     |
| DELETE | `/api/user-documents/{id}`          | authenticated | Soft delete + file cleanup        |
| GET    | `/api/user-documents/{id}/download` | authenticated | Download file                     |

---

### Profile

| Method | URL                          | Permission    | Description                 |
| ------ | ---------------------------- | ------------- | --------------------------- |
| GET    | `/api/profile`               | authenticated | Own profile                 |
| PUT    | `/api/profile`               | authenticated | Update profile              |
| GET    | `/api/profile/contributions` | authenticated | Samples contributed by user |
| GET    | `/api/profile/achievements`  | authenticated | Earned achievements         |
| GET    | `/api/profile/activity`      | authenticated | User's transaction history  |

---

### Reports

| Method | URL                           | Permission     | Description                   |
| ------ | ----------------------------- | -------------- | ----------------------------- |
| GET    | `/api/reports/inventory`      | `reports.view` | Full inventory summary        |
| GET    | `/api/reports/chemical-usage` | `reports.view` | Usage stats with date filters |
| GET    | `/api/reports/expired-items`  | `reports.view` | All expired chemicals/batches |
| GET    | `/api/reports/borrowed-items` | `reports.view` | Currently borrowed items      |
| GET    | `/api/reports/user-activity`  | `reports.view` | Per-user transaction counts   |
| GET    | `/api/reports/{type}/export`  | `reports.view` | Export as CSV                 |

**Common Response Envelope (all list endpoints):**

```json
{
  "data": [...],
  "links": { "first": "...", "next": "...", "prev": "..." },
  "meta": { "current_page": 1, "total": 250, "per_page": 15 }
}
```

---

# 6. Business Logic Layer

## 6.1 TransactionService

**The only writer of Transaction rows.** All other services depend on it.

```php
public function log(
    Model $item,
    User $user,
    TransactionAction $action,
    ?float $quantity = null,
    ?string $note = null,
): Transaction
```

- Called automatically by `InventoryCrudService`, `BorrowService`, `ChemicalUsageService`, `StockService`.
- Uses `$item->transactions()->create(...)` so the polymorphic relation is set correctly.

## 6.2 InventoryCrudService

Generic wrapper eliminating the repetitive `DB::transaction + TransactionService::log` pattern.

```php
create(string $modelClass, array $data, User $user, ?string $note, ?Model $logTarget): Model
update(Model $instance, array $data, User $user, ?string $note, ?Model $logTarget): Model
delete(Model $instance, User $user, ?string $note, ?Model $logTarget): void
```

**Key behaviour:**

- `create` wraps in `DB::transaction`, calls `$modelClass::create($data)`, then calls `TransactionService::log` with `TransactionAction::ADDED`.
- `update` wraps in `DB::transaction`, calls `$instance->update($data)`, logs `UPDATED`.
- `delete` logs `DISPOSED` first, then calls `$instance->delete()` (soft delete). Logging before delete ensures the transaction record points to the still-existing model.
- `$logTarget` override: used when `ChemicalBatch` wants its Transaction to point to the parent `Chemical`.

## 6.3 StockService

Guards `PlantStock` quantity against going negative.

```php
consume(PlantStock $stock, int $quantity): PlantStock   // throws InsufficientStockException
reserve(PlantStock $stock, int $quantity): PlantStock   // throws InsufficientStockException
release(PlantStock $stock, int $quantity): PlantStock   // safe — can never over-release
```

**`syncStatus()` algorithm:**

```
if quantity <= 0         → status = OUT_OF_STOCK
if reserved >= quantity  → status = RESERVED
else                     → status = AVAILABLE
```

All three public methods wrap everything in `DB::transaction`.

## 6.4 BorrowService

Full state machine for borrows.

```php
borrow(item, user, quantity, dueAt, notes): BorrowRecord        // Direct BORROWED
requestBorrow(item, user, quantity, dueAt, notes): BorrowRecord // Creates PENDING
approveBorrow(record, approver, notes): BorrowRecord            // PENDING → BORROWED
rejectBorrow(record, rejector, reason): BorrowRecord            // PENDING → REJECTED
returnItem(record, notes): BorrowRecord                         // BORROWED → RETURNED
```

**Before any borrow action:**

- `assertBorrowable($item, $quantity)` — validates the item supports borrowing and sufficient stock exists.
- For equipment: checks `status === AVAILABLE && condition !== BROKEN`.
- For Plant/Chemical: checks `available_quantity >= quantity`.

**Stock effects:**

- `decrementStock`: Equipment → set status `BORROWED`; Chemical/PlantStock → decrement quantity.
- `incrementStock`: Equipment → set status `AVAILABLE`; Chemical/PlantStock → increment quantity.

**Notifications:**

- On `requestBorrow`: all users with role `admin` or `lab-manager` are notified (`BorrowRequestNotification`).
- On `approveBorrow`/`rejectBorrow`: the borrowing user is notified.

## 6.5 ChemicalUsageService

```php
create(array $data, int $userId): ChemicalUsageLog
```

Within a single `DB::transaction`:

1. Create the `ChemicalUsageLog` record.
2. Decrement `chemical.quantity` by `ceil(quantity_used)`.
3. If `chemical_batch_id` provided, decrement `chemical_batch.quantity` as well.
4. Log a `CONSUMED` transaction against the parent chemical.

## 6.6 DashboardService

All methods cache their results:

- `getCounts()` → 60-second cache key `dashboard:counts`
- `getAlerts()` → 30-second cache key `dashboard:alerts`
- `getRecentActivity()` → 15-second cache key `dashboard:recent_activity`
- `getStatusBreakdown()` → 60-second cache key `dashboard:status_breakdown`

**Cache bust strategy:** The cache is NOT automatically invalidated on mutations. Short TTLs (15-60 seconds) are sufficient for a lab context. For higher consistency requirements, add `Cache::forget('dashboard:counts')` to the event listeners on stock mutations.

---

# 7. Inventory Workflows

## 7.1 Add New Plant Species

```
Actor: Lab Manager / Admin

1. POST /api/plant-species
   Payload: { common_name, scientific_name, growth_type, ... }

2. StorePlantSpeciesRequest::authorize()
   → user must have permission 'plants.create'

3. StorePlantSpeciesRequest::rules()
   → scientific_name unique among non-deleted rows

4. InventoryCrudService::create()
   → DB::transaction {
       PlantSpecies::create($data)
       TransactionService::log(species, user, ADDED)
     }

5. Spatie activity_log entry created (HasActivityLogging trait)

Result: New PlantSpecies row, Transaction row, ActivityLog row
```

---

## 7.2 Receive Chemical Stock (Add Batch)

```
Actor: Lab Manager / Admin

1. POST /api/chemical-batches
   Payload: { chemical_id, batch_number, quantity, unit, expiry_date, supplier_name, ... }

2. StoreChemicalBatchRequest::authorize()
   → 'chemical_batches.create'

3. Validate batch_number unique per chemical_id (ignoring soft-deleted)

4. InventoryCrudService::create(
     modelClass: ChemicalBatch::class,
     data: $validated,
     user: $auth,
     logTarget: $batch->chemical   // ← audit log points to parent Chemical
   )
   → DB::transaction {
       ChemicalBatch::create($data)
       TransactionService::log(chemical, user, ADDED, quantity)
     }

5. NOTE: The parent Chemical.quantity is NOT auto-updated from batch creation.
   It must be updated separately via PUT /api/chemicals/{id} or a dedicated
   restock endpoint. The Chemical.quantity field represents the aggregated
   current total; batches represent individual lots.

Result: New ChemicalBatch, Transaction logged against parent Chemical
```

---

## 7.3 Chemical Usage (Consumption Workflow)

```
Actor: Researcher / Student

1. POST /api/chemical-usage-logs
   Payload: { chemical_id, chemical_batch_id, quantity_used, unit, purpose, used_at }

2. StoreChemicalUsageLogRequest::authorize()
   → 'chemical_usage.create'

3. ChemicalUsageService::create($data, $userId)
   → DB::transaction {
       ChemicalUsageLog::create($data + [user_id])
       chemical->decrement('quantity', ceil(quantity_used))
       if chemical_batch_id:
         batch->decrement('quantity', ceil(quantity_used))
       TransactionService::log(chemical, user, CONSUMED, quantity_used, "Used for: {purpose}")
     }

Constraints enforced by DB CHECK: chemical.quantity >= 0
If the decrement would violate this, the transaction rolls back with a DB exception.

Result: ChemicalUsageLog row, Chemical.quantity decremented, ChemicalBatch.quantity decremented, Transaction logged
```

---

## 7.4 Equipment Borrow Workflow (Two Paths)

### Path A: Direct Borrow (privileged user — has `borrows.approve`)

```
1. POST /api/borrow-records
   Payload: { borrowable_type: "equipment", borrowable_id, user_id, quantity: 1, due_at }

2. BorrowRecordController::store()
   → resolves Equipment via morph map
   → currentUser has borrows.approve → calls BorrowService::borrow()

3. BorrowService::borrow()
   → assertBorrowable(equipment, 1):
       check equipment.status === AVAILABLE
       check equipment.condition !== BROKEN
   → DB::transaction {
       equipment.status = BORROWED (decrementStock)
       BorrowRecord::create([..., status: BORROWED])
       TransactionService::log(equipment, user, BORROWED)
     }

Result: Equipment.status = borrowed, BorrowRecord, Transaction
```

### Path B: Borrow Request (regular user — no `borrows.approve`)

```
1. POST /api/borrow-records  (same route)
   → calls BorrowService::requestBorrow()
   → DB::transaction {
       BorrowRecord::create([..., status: PENDING])
       Notify all admins/lab-managers
     }
   → Equipment.status unchanged

2. Lab Manager sees pending list: GET /api/borrow-records/pending

3. Approve: POST /api/borrow-records/{id}/approve
   → BorrowService::approveBorrow()
   → DB::transaction {
       assertBorrowable(equipment, quantity)  // re-check availability
       decrementStock(equipment, 1)           // equipment.status = BORROWED
       record.update([status: BORROWED, reviewed_by, reviewed_at])
       TransactionService::log(equipment, user, BORROWED, note: "Approved by ...")
       Notify borrower (approved)
     }

   OR Reject: POST /api/borrow-records/{id}/reject
   → BorrowService::rejectBorrow()
   → DB::transaction {
       record.update([status: REJECTED, rejected_reason])
       Notify borrower (rejected)
     }
```

---

## 7.5 Item Return Workflow

```
1. POST /api/borrow-records/{id}/return
   Payload: { notes?: "Returned in good condition" }

2. ReturnBorrowRecordRequest::authorize()
   → 'borrows.return'

3. BorrowService::returnItem($record, $notes)
   → if record.is_returned: return as-is (idempotent)
   → DB::transaction {
       record.update([status: RETURNED, returned_at: now()])
       incrementStock($item, record.quantity)  // equipment → AVAILABLE; chemical/stock → +N
       TransactionService::log($item, record.user, RETURNED)
     }

Result: BorrowRecord.status = returned, stock restored, Transaction logged
```

---

## 7.6 Plant Stock Reserve & Consume

```
Reserve (e.g. allocate for experiment):
1. StockService::reserve($stock, $quantity)
   → check available_quantity >= quantity (throws InsufficientStockException)
   → DB::transaction {
       stock.reserved_quantity += quantity
       syncStatus(stock)
     }

Consume (e.g. use in propagation):
1. StockService::consume($stock, $quantity)
   → check available_quantity >= quantity
   → DB::transaction {
       stock.quantity -= quantity
       syncStatus(stock)
     }

Release (cancel reservation):
1. StockService::release($stock, $quantity)
   → release = min(quantity, stock.reserved_quantity)
   → DB::transaction {
       stock.reserved_quantity -= release
       syncStatus(stock)
     }
```

---

## 7.7 Inventory Audit (Low Stock / Expiry Detection)

```
This is a read-side workflow, not a mutation.

GET /api/dashboard
→ DashboardService::getAlerts() (cached 30 sec)
  → ChemicalBatch::expiringSoon(30).count()
  → ChemicalBatch::expired().count()
  → BorrowRecord::overdue().count()
  → BorrowRecord::where(status, PENDING).count()
  → MaintenanceRecord::overdue().count()
  → Chemical::lowStock(10).count()

GET /api/reports/expired-items
→ ExpiredItemsReportQuery: all expired ChemicalBatches + Chemical records

GET /api/chemicals?low_stock=true
→ Chemical::lowStock($threshold) scope applied
```

---

## 7.8 Equipment Maintenance Workflow

```
1. Equipment breaks or scheduled service is due.

2. PUT /api/equipment/{id}
   Payload: { status: "under_maintenance" }
   → InventoryCrudService::update(equipment, data, user)
   → DB::transaction {
       equipment.status = under_maintenance
       TransactionService::log(equipment, user, UPDATED)
     }

3. POST /api/maintenance-records
   Payload: { equipment_id, maintenance_type, description, started_at, next_service_date }
   → InventoryCrudService::create(MaintenanceRecord, data, user, logTarget: equipment)

4. Once maintenance complete:
   PUT /api/maintenance-records/{id}   { completed_at: "2026-03-20" }
   PUT /api/equipment/{id}             { status: "available" }
```

---

# 8. Data Integrity Rules

## 8.1 Stock Consistency

| Rule                                     | Enforcement Level                                      |
| ---------------------------------------- | ------------------------------------------------------ |
| `quantity >= 0`                          | DB CHECK constraint                                    |
| `reserved_quantity >= 0`                 | DB CHECK constraint                                    |
| `reserved_quantity <= quantity`          | DB CHECK constraint + Form Request `lte:quantity` rule |
| Status auto-synced after quantity change | Application (StockService::syncStatus)                 |
| Chemical quantity >= 0                   | DB CHECK constraint                                    |

## 8.2 Transaction Boundaries

Every mutating operation that touches more than one table is wrapped in `DB::transaction(function() {...})`:

- `InventoryCrudService::create/update/delete`
- `BorrowService::borrow/requestBorrow/approveBorrow/rejectBorrow/returnItem`
- `ChemicalUsageService::create`
- `StockService::consume/reserve/release`

If any step throws, the entire transaction rolls back — no partial state is committed.

## 8.3 Concurrency Protection

For high-concurrency stock operations, use **pessimistic row locking**:

```php
// In StockService::consume() — add lockForUpdate():
$stock = PlantStock::where('id', $stockId)->lockForUpdate()->first();
```

This prevents two concurrent requests from both seeing the same `quantity` and both decrementing, which would otherwise produce a race condition that CHECK constraints alone cannot prevent (they only reject final state, not concurrent reads).

For PostgreSQL the full pattern is:

```php
DB::transaction(function() use ($stockId, $quantity) {
    $stock = PlantStock::lockForUpdate()->findOrFail($stockId);
    if ($stock->available_quantity < $quantity) {
        throw new InsufficientStockException(...);
    }
    $stock->decrement('quantity', $quantity);
    $this->syncStatus($stock);
});
```

## 8.4 Soft Delete Integrity

- All inventory models use `SoftDeletes`.
- Unique indexes are _partial_ (PostgreSQL `WHERE deleted_at IS NULL`) so a soft-deleted `variety_code` can be reused.
- FK `RESTRICT` (not `CASCADE`) on parent → child links prevents accidentally orphaning child records.

## 8.5 Audit Log Immutability

- The `transactions` table has no `UPDATE` or `DELETE` operations exposed through the application.
- `TransactionController` only provides `index` and `show`.
- Production policy: the database user used by the application should have `INSERT`/`SELECT` only on the `transactions` table.

---

# 9. Security Architecture

## 9.1 Authentication

- **Driver**: JWT (`php-open-source-saver/jwt-auth`) with the `api` guard.
- **Guard**: All routes wrapped in `middleware('auth:api')`.
- **Token lifetime**: Configurable in `config/jwt.php` (`ttl`, `refresh_ttl`).
- **Stateless**: No session cookies in the API path.

## 9.2 Authorization Model

Spatie `laravel-permission` (v6+) with the `api` guard.

### Permission Registry (Inventory Module)

| Permission                | Roles that hold it by default           |
| ------------------------- | --------------------------------------- |
| `plants.view`             | admin, lab-manager, researcher, student |
| `plants.create`           | admin, lab-manager, researcher          |
| `plants.edit`             | admin, lab-manager                      |
| `plants.delete`           | admin                                   |
| `chemicals.view`          | admin, lab-manager, researcher, student |
| `chemicals.create`        | admin, lab-manager                      |
| `chemicals.edit`          | admin, lab-manager                      |
| `chemicals.delete`        | admin                                   |
| `chemical_batches.view`   | admin, lab-manager, researcher          |
| `chemical_batches.create` | admin, lab-manager                      |
| `chemical_batches.edit`   | admin, lab-manager                      |
| `chemical_batches.delete` | admin                                   |
| `chemical_usage.view`     | admin, lab-manager, researcher          |
| `chemical_usage.create`   | admin, lab-manager, researcher          |
| `equipment.view`          | admin, lab-manager, researcher, student |
| `equipment.create`        | admin, lab-manager                      |
| `equipment.edit`          | admin, lab-manager                      |
| `equipment.delete`        | admin                                   |
| `borrows.view`            | admin, lab-manager, researcher, student |
| `borrows.create`          | admin, lab-manager, researcher, student |
| `borrows.return`          | admin, lab-manager                      |
| `borrows.approve`         | admin, lab-manager                      |
| `transactions.view`       | admin, lab-manager                      |
| `reports.view`            | admin, lab-manager                      |
| `achievements.manage`     | admin                                   |

### Authorization Flow

```
HTTP Request
  └─► auth:api middleware (validates JWT → resolves User)
        └─► FormRequest::authorize()
              └─► $user->hasPermissionTo('resource.action', 'api')
                    OR Policy method called from Controller::authorize()
```

### Self-Ownership Rule

`BorrowRecordPolicy::view()` grants access if `$user->id === $borrowRecord->user_id`, even without `borrows.view` permission. This pattern applies to user documents and profile as well.

## 9.3 Input Validation

- All input goes through `FormRequest` classes before reaching any controller logic.
- Enum validation uses `Rule::enum(SomeEnum::class)` — never raw string comparisons.
- Image uploads validated with `HasImageValidation` trait: allowed mimes (jpg, jpeg, png, webp), max 2 MB.
- Code fields (e.g. `chemical_code`, `sample_code`) validated for uniqueness using `Rule::unique()->whereNull('deleted_at')`.

## 9.4 File Upload Security

- Images stored via Laravel's `Storage` disk (configurable to `public` or S3).
- Old image deleted from disk when a model is updated with a new image (`ImageUploadService`).
- `UserDocument`: file deleted from storage on `forceDelete` event (Eloquent model observer pattern in `booted()`).
- Never serve raw user-uploaded files without extension validation.

---

# 10. Performance Strategy

## 10.1 Database Indexing

### Applied Indexes

| Table                 | Index                                                                                | Purpose                     |
| --------------------- | ------------------------------------------------------------------------------------ | --------------------------- |
| `plant_species`       | `scientific_name`                                                                    | Search/lookup               |
| `plant_varieties`     | `plant_species_id`, `variety_code`                                                   | FK join, code lookup        |
| `plant_samples`       | `(plant_species_id, plant_variety_id, status)`, `department`, `lab_location`         | Multi-filter queries        |
| `plant_stocks`        | `(plant_species_id, plant_variety_id, plant_sample_id, status)`                      | Multi-filter                |
| `chemicals`           | `chemical_code`, `common_name`, `(category, expiry_date, danger_level)`              | Search, expiry filter       |
| `equipment`           | `equipment_code`, `equipment_name`, `serial_number`, `(category, status, condition)` | Search, status filter       |
| `chemical_batches`    | `(chemical_id, expiry_date)`                                                         | Expiry scanning             |
| `chemical_usage_logs` | `(chemical_id, used_at)`, `(user_id, used_at)`                                       | Usage reports               |
| `borrow_records`      | `(status, due_at)`, `borrowed_at`, `(borrowable_type, borrowable_id)`                | Overdue queries, morph join |
| `transactions`        | `(transactionable_type, transactionable_id)`, `(action, created_at)`                 | Audit history               |
| `maintenance_records` | `(equipment_id, maintenance_type)`, `next_service_date`                              | Overdue service             |
| `location_histories`  | `(trackable_type, trackable_id, created_at)`                                         | Item movement history       |
| `user_documents`      | `(user_id, file_type)`                                                               | User file filtering         |
| `users`               | `role`                                                                               | Role-based filtering        |

### Partial Unique Indexes (PostgreSQL)

Applied on code fields to enforce uniqueness only on non-deleted rows:

```sql
CREATE UNIQUE INDEX idx_variety_code_unique  ON plant_varieties(variety_code)  WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX idx_sample_code_unique   ON plant_samples(sample_code)     WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX idx_chemical_code_unique ON chemicals(chemical_code)       WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX idx_equipment_code_unique ON equipment(equipment_code)     WHERE deleted_at IS NULL;
```

## 10.2 Caching

- **Dashboard aggregates** cached using `Cache::remember()`:
    - Counts: 60 seconds
    - Alerts: 30 seconds
    - Recent activity: 15 seconds
    - Status breakdown: 60 seconds
- **Cache driver**: Redis recommended for production (configure `CACHE_DRIVER=redis`).
- **Cache keys**: namespaced as `dashboard:*` to allow targeted invalidation.

## 10.3 Eager Loading

Controllers eager-load relationships before returning Resources:

```php
BorrowRecord::with(['user', 'borrowable'])->...
PlantStock::with(['species', 'variety', 'sample'])->...
```

This avoids N+1 queries in collection responses.

## 10.4 Pagination

All list endpoints paginate. Default page sizes:

- Most resources: 10 items per page
- Borrow records and transactions: 15 items per page

## 10.5 Query Optimization

- Scopes (`scopeAvailable()`, `scopeLowStock()`, etc.) are used everywhere — never inline conditions.
- Search uses the `EscapesSearchTerm` trait's `escapeLike()` to prevent LIKE injection and allow proper index use.
- Report queries use `selectRaw` + `groupBy` for aggregations rather than loading collections into PHP.

---

# 11. Event System

## 11.1 Activity Logging

**Spatie laravel-activitylog** is used via the `HasActivityLogging` trait, applied to all models that participate in audit trails:

- `BorrowRecord`, `Chemical`, `ChemicalBatch`, `Equipment`, `MaintenanceRecord`, `PlantSample`, `PlantSpecies`, `PlantStock`, `PlantVariety`

Logged operations: `created`, `updated`, `deleted` (soft).

Stored in the `activity_log` table with `causer` (the authenticated user) and `subject` (the model).

## 11.2 Notifications

`BorrowRequestNotification` is sent in three situations:

| Trigger                      | Recipients                    | Message                                      |
| ---------------------------- | ----------------------------- | -------------------------------------------- |
| New borrow request (PENDING) | All admin + lab-manager users | "New borrow request from {user}"             |
| Borrow approved              | The requesting user           | "Your borrow request was approved"           |
| Borrow rejected              | The requesting user           | "Your borrow request was rejected: {reason}" |

Notifications use Laravel's notification system. Implement the `via()` method to choose delivery channels (database, email, etc.).

## 11.3 Recommended Events to Add

For a fully event-driven system, add these events (not yet in codebase but architecturally correct):

| Event                         | Trigger                                | Suggested Listener                                      |
| ----------------------------- | -------------------------------------- | ------------------------------------------------------- |
| `StockLevelChanged`           | StockService::consume/reserve          | Check low-stock threshold, fire `LowStockDetectedEvent` |
| `LowStockDetected`            | When `available_quantity <= threshold` | Notify lab manager                                      |
| `ChemicalExpiringSoon`        | Scheduled command                      | Notify lab manager via email                            |
| `EquipmentOverdueMaintenance` | Scheduled command                      | Notify maintenance staff                                |
| `BorrowOverdue`               | Scheduled command                      | Notify borrower and manager                             |

**Suggested console commands (Laravel Scheduler):**

```php
// In Console/Kernel.php (or routes/console.php)
Schedule::command('inventory:check-expiry')->daily();
Schedule::command('inventory:check-overdue-borrows')->hourly();
Schedule::command('inventory:check-maintenance-due')->weekly();
```

---

# 12. Testing Strategy

## 12.1 Test Framework

The project uses **Pest PHP** (v3/v4) with:

- `Tests\TestCase` extending `Illuminate\Foundation\Testing\TestCase`
- `RefreshDatabase` trait on feature tests
- **Factories** for all inventory models

## 12.2 Unit Tests (Services)

### StockService

```php
// tests/Unit/StockServiceTest.php
it('consumes stock correctly', function () {
    $stock = PlantStock::factory()->create(['quantity' => 100, 'reserved_quantity' => 0]);
    $service = app(StockService::class);
    $result = $service->consume($stock, 30);
    expect($result->quantity)->toBe(70);
    expect($result->status->value)->toBe('available');
});

it('throws InsufficientStockException when consuming more than available', function () {
    $stock = PlantStock::factory()->create(['quantity' => 10, 'reserved_quantity' => 5]);
    $service = app(StockService::class);
    expect(fn() => $service->consume($stock, 8))->toThrow(InsufficientStockException::class);
});

it('syncs status to out_of_stock when quantity reaches 0', function () {
    $stock = PlantStock::factory()->create(['quantity' => 5, 'reserved_quantity' => 0]);
    $service = app(StockService::class);
    $service->consume($stock, 5);
    expect($stock->fresh()->status->value)->toBe('out_of_stock');
});

it('releases reserved stock correctly', function () {
    $stock = PlantStock::factory()->create(['quantity' => 50, 'reserved_quantity' => 20]);
    $service = app(StockService::class);
    $service->release($stock, 10);
    expect($stock->fresh()->reserved_quantity)->toBe(10);
});
```

### BorrowService

```php
it('creates a PENDING borrow record when user lacks approve permission', function () {
    $user = User::factory()->create();
    $equipment = Equipment::factory()->available()->create();
    $service = app(BorrowService::class);
    $record = $service->requestBorrow($equipment, $user, 1);
    expect($record->status->value)->toBe('pending');
    expect($equipment->fresh()->status->value)->toBe('available'); // No stock change
});

it('changes equipment status to borrowed on direct borrow', function () {
    $user = User::factory()->create();
    $equipment = Equipment::factory()->available()->create();
    $service = app(BorrowService::class);
    $record = $service->borrow($equipment, $user, 1);
    expect($record->status->value)->toBe('borrowed');
    expect($equipment->fresh()->status->value)->toBe('borrowed');
});

it('restores equipment to available on return', function () {
    $user = User::factory()->create();
    $equipment = Equipment::factory()->borrowed()->create();
    $record = BorrowRecord::factory()->create([
        'borrowable_type' => 'equipment',
        'borrowable_id' => $equipment->id,
        'status' => 'borrowed',
        'user_id' => $user->id,
        'quantity' => 1,
    ]);
    $service = app(BorrowService::class);
    $service->returnItem($record);
    expect($equipment->fresh()->status->value)->toBe('available');
    expect($record->fresh()->returned_at)->not->toBeNull();
});
```

## 12.3 Feature Tests (HTTP)

### Chemicals

```php
// tests/Feature/ChemicalApiTest.php
it('lists chemicals with pagination', function () {
    $user = User::factory()->withPermission('chemicals.view')->create();
    Chemical::factory()->count(25)->create();
    actingAsApiUser($user)
        ->getJson('/api/chemicals')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links'])
        ->assertJsonCount(10, 'data');
});

it('creates a chemical and logs a transaction', function () {
    $user = User::factory()->withPermission('chemicals.create')->create();
    $payload = Chemical::factory()->make()->toArray();
    actingAsApiUser($user)
        ->postJson('/api/chemicals', $payload)
        ->assertCreated()
        ->assertJsonPath('data.common_name', $payload['common_name']);
    expect(Transaction::where('action', 'added')->count())->toBe(1);
});

it('prevents creating a chemical without permission', function () {
    $user = User::factory()->student()->create(); // no chemicals.create
    actingAsApiUser($user)
        ->postJson('/api/chemicals', Chemical::factory()->make()->toArray())
        ->assertForbidden();
});
```

### Borrow Records

```php
it('full borrow → return cycle', function () {
    $manager = User::factory()->labManager()->create();
    $student = User::factory()->student()->create();
    $equipment = Equipment::factory()->available()->create();

    // Manager borrows directly
    actingAsApiUser($manager)
        ->postJson('/api/borrow-records', [
            'user_id' => $student->id,
            'borrowable_type' => 'equipment',
            'borrowable_id' => $equipment->id,
            'quantity' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'borrowed');

    expect($equipment->fresh()->status->value)->toBe('borrowed');

    $recordId = BorrowRecord::first()->id;

    // Manager returns it
    actingAsApiUser($manager)
        ->postJson("/api/borrow-records/{$recordId}/return")
        ->assertOk()
        ->assertJsonPath('data.status', 'returned');

    expect($equipment->fresh()->status->value)->toBe('available');
});

it('student can request, cannot approve', function () {
    $student = User::factory()->student()->create();
    $equipment = Equipment::factory()->available()->create();

    actingAsApiUser($student)
        ->postJson('/api/borrow-records', [
            'user_id' => $student->id,
            'borrowable_type' => 'equipment',
            'borrowable_id' => $equipment->id,
            'quantity' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');

    $recordId = BorrowRecord::first()->id;

    actingAsApiUser($student)
        ->postJson("/api/borrow-records/{$recordId}/approve")
        ->assertForbidden();
});
```

## 12.4 Integration Tests

Test cross-service scenarios:

```php
it('chemical usage decrements both chemical and batch quantity atomically', function () {
    $chemical = Chemical::factory()->create(['quantity' => 100]);
    $batch = ChemicalBatch::factory()->for($chemical)->create(['quantity' => 50]);
    $user = User::factory()->withPermission('chemical_usage.create')->create();

    actingAsApiUser($user)->postJson('/api/chemical-usage-logs', [
        'chemical_id' => $chemical->id,
        'chemical_batch_id' => $batch->id,
        'quantity_used' => 20,
        'unit' => 'ml',
        'purpose' => 'Test',
        'used_at' => now()->toDateTimeString(),
    ])->assertCreated();

    expect($chemical->fresh()->quantity)->toBe(80);
    expect($batch->fresh()->quantity)->toBe(30);
    expect(Transaction::where('action', 'consumed')->count())->toBe(1);
});
```

## 12.5 Test Helpers

Create a custom `actingAsApiUser()` helper using JWT:

```php
// tests/Pest.php
function actingAsApiUser(User $user): TestCase
{
    $token = auth('api')->login($user);
    return test()->withHeaders(['Authorization' => "Bearer {$token}"]);
}
```

---

# 13. Production Readiness

## 13.1 Environment Configuration

Required `.env` variables:

```dotenv
APP_NAME="Plant Lab Inventory"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://inventory.yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=plant_inventory
DB_USERNAME=plant_user
DB_PASSWORD=your_secure_password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# JWT
JWT_SECRET=your_jwt_secret
JWT_TTL=60
JWT_REFRESH_TTL=20160

# File Storage
FILESYSTEM_DISK=s3        # or 'public' for local
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=plant-lab-inventory

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

## 13.2 Logging

Laravel logging is configured in `config/logging.php`.

Recommended production stack:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],   // errors also go to Slack
    ],
    'daily' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/laravel.log'),
        'level'  => env('LOG_LEVEL', 'warning'),
        'days'   => 30,
    ],
],
```

**Spatie ActivityLog** provides an additional domain-specific audit trail stored in the database.

## 13.3 Monitoring

Recommended tools:

- **Laravel Telescope** (development): Detailed request, query, and log inspection.
- **Laravel Pulse** (production): Real-time performance dashboard.
- **Sentry** or **Bugsnag**: Exception tracking and alerting.
- **Uptime Robot** or **Better Uptime**: Endpoint availability monitoring.

## 13.4 Database Backups

With PostgreSQL:

```bash
# Daily backup
pg_dump plant_inventory | gzip > /backups/plant_inventory_$(date +%F).sql.gz

# Restore
gunzip -c /backups/plant_inventory_2026-03-12.sql.gz | psql plant_inventory
```

For automated cloud backups, use `spatie/laravel-backup`:

```bash
php artisan backup:run
```

Configure S3 as the backup destination.

## 13.5 Scheduled Commands

```php
// routes/console.php
Schedule::command('activitylog:clean')->weekly();      // Clean old activity logs
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('02:00');
Schedule::command('queue:restart')->daily()->at('03:00');
```

## 13.6 Queue Workers

All notifications are queued. Run workers:

```bash
php artisan queue:work redis --queue=notifications,default --sleep=3 --tries=3
```

In production, use **Supervisor** to keep queue workers alive:

```ini
[program:laravel-worker]
command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3
numprocs=2
autostart=true
autorestart=true
```

## 13.7 Scaling Strategy

| Layer           | Strategy                                                                                       |
| --------------- | ---------------------------------------------------------------------------------------------- |
| API (Stateless) | Horizontal scaling behind load balancer (NGINX / AWS ALB). JWT auth makes sessions irrelevant. |
| Database        | Read replicas for report queries. Write to primary. Configure `DB_READ_HOST`.                  |
| Cache           | Redis Cluster or ElastiCache.                                                                  |
| File Storage    | S3 (or compatible) — never store uploads on application servers.                               |
| Queue           | Redis with multiple workers. Scale workers independently.                                      |

---

# 14. Implementation Roadmap

Follow these steps strictly in order. Each step builds on the previous.

---

## Step 1 — Bootstrap the Laravel Project

```bash
composer create-project laravel/laravel plant-lab-inventory
cd plant-lab-inventory

# Install dependencies
composer require php-open-source-saver/jwt-auth
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
composer require spatie/laravel-backup

# Publish config files
php artisan vendor:publish --provider="PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"
```

Configure `config/auth.php` to use JWT driver for the `api` guard.

---

## Step 2 — Create the Module Directory Structure

```bash
mkdir -p app/Modules/Inventory/{Controllers,Models,Policies,Requests,Resources,Routes,Services/Reports}
mkdir -p app/Modules/Core/Models
mkdir -p app/Concerns
mkdir -p app/Enums
mkdir -p app/Exceptions
```

---

## Step 3 — Implement Enums

Create all enums in `app/Enums/`:

- `BorrowStatus` (with `allowedTransitions()`, `canTransitionTo()`, `isTerminal()`)
- `ChemicalCategory`
- `DangerLevel`
- `EquipmentCategory`
- `EquipmentCondition`
- `EquipmentStatus`
- `LabLocation`
- `MaintenanceType`
- `PlantGrowthType`
- `SampleStatus`
- `StockStatus`
- `TransactionAction`
- `UserRole`

---

## Step 4 — Create All Migrations

Run migrations in this order:

1. `users` table (with `role`, soft deletes, `email_verified_at`)
2. `plant_species`
3. `plant_varieties` (FK → plant_species)
4. `plant_samples` (FK → plant_species, plant_varieties, users)
5. `plant_stocks` (FK → plant_species, plant_varieties, plant_samples)
6. `chemicals`
7. `chemical_batches` (FK → chemicals)
8. `chemical_usage_logs` (FK → chemicals, chemical_batches, users)
9. `equipment`
10. `maintenance_records` (FK → equipment, users)
11. `transactions` (morphs)
12. `borrow_records` (morphs, FK → users)
13. `achievements` + `user_achievements` (FK → users, achievements)
14. `user_documents` (FK → users)
15. `location_histories` (morphs, FK → users)
16. Permission tables (from Spatie)
17. Activity log tables (from Spatie)
18. Performance indexes migration
19. CHECK constraints + partial unique indexes migration

```bash
php artisan migrate
```

---

## Step 5 — Implement Shared Concerns (Traits)

Create in `app/Concerns/`:

- `HasTransactions`: adds `morphMany(Transaction::class, 'transactionable')` relationship.
- `HasActivityLogging`: `use \Spatie\Activitylog\Traits\LogsActivity;` with configured `$logName`, `getActivitylogOptions()`.
- `HasImageUpload`: `static function imageFolder(): string`, `getImageUrlAttribute(): ?string`.
- `EscapesSearchTerm`: `protected function escapeLike(string $term): string`.
- `ManagesBorrowableStock`: `decrementStock(Model $item, int $qty)`, `incrementStock(Model $item, int $qty)`, `assertBorrowable(Model $item, int $qty)`.

---

## Step 6 — Implement Models

Create all Eloquent models in `app/Modules/Inventory/Models/` referencing Section 2 of this blueprint for `$fillable`, `casts()`, relationships, and scopes.

Create `app/Modules/Core/Models/User.php` extending `Authenticatable` with:

- JWT `JWTSubject` interface implementation
- Spatie `HasRoles` trait
- relationships to `achievements`, `borrowRecords`, `transactions`, `documents`

---

## Step 7 — Create Factories and Seeders

Create factories in `database/factories/` for all 14 models.
Create seeders:

- `UserSeeder` — admin, lab-manager, researcher, student
- `RolePermissionSeeder` — define roles and assign permissions from the table in Section 9.2
- `PlantSpeciesSeeder` — sample species data
- `ChemicalSeeder` — common lab chemicals
- `EquipmentSeeder` — common lab equipment

---

## Step 8 — Implement Services

Implement in this order (dependencies are clear):

1. `TransactionService` (no dependencies)
2. `StockService` (depends on TransactionService indirectly; standalone)
3. `InventoryCrudService` (depends on TransactionService)
4. `ChemicalUsageService` (depends on TransactionService)
5. `BorrowService` (depends on TransactionService; uses ManagesBorrowableStock)
6. `DashboardService` (no service dependencies)
7. `AchievementService`
8. `ProfileService`
9. `Reports/*` (read-only query objects)

---

## Step 9 — Implement Form Requests

For each resource, create `Store{Resource}Request` and `Update{Resource}Request` in the appropriate `Requests/` subdirectory.

Follow the pattern in Section 5 validation rules exactly.

---

## Step 10 — Implement API Resources

Create all Resource classes in `app/Modules/Inventory/Resources/`. Each must:

- List every returned field explicitly.
- Use `$this->whenLoaded('relationship', ...)` for nested resources.
- Return enum values as `->value` (never the enum object).

---

## Step 11 — Implement Policies

Create `app/Modules/Inventory/Policies/` classes using the permission names in Section 9.2.

Register all policies in `AuthServiceProvider` (or `AppServiceProvider`):

```php
Gate::policy(Chemical::class, ChemicalPolicy::class);
Gate::policy(Equipment::class, EquipmentPolicy::class);
// ...
```

---

## Step 12 — Implement Controllers

Create controllers in `app/Modules/Inventory/Controllers/`.

Every controller method must:

1. Call `$this->authorize()`.
2. Type-hint the `FormRequest` (not plain `Request`) for write operations.
3. Delegate to services — no inline business logic.
4. Return `AnonymousResourceCollection` for lists, `JsonResource` for single items, `JsonResponse` for deletions.

---

## Step 13 — Register Routes

Create `app/Modules/Inventory/Routes/api.php` with the full route map from Section 5.

Register it in `bootstrap/app.php` or `RouteServiceProvider`:

```php
Route::prefix('api')
    ->middleware('api')
    ->group(base_path('app/Modules/Inventory/Routes/api.php'));
```

---

## Step 14 — Implement Notifications

Create `app/Notifications/BorrowRequestNotification.php`:

```php
class BorrowRequestNotification extends Notification implements ShouldQueue
{
    public function __construct(
        public BorrowRecord $record,
        public string $eventType, // 'requested' | 'approved' | 'rejected'
    ) {}

    public function via($notifiable): array { return ['database']; }

    public function toArray($notifiable): array { /* ... */ }
}
```

---

## Step 15 — Write Tests

Write tests in the order: Unit tests first, then Feature tests.

Use the examples from Section 12 as starting templates.

Run the test suite:

```bash
php artisan test --parallel
```

Target: **complete passing tests for all critical paths** before proceeding.

---

## Step 16 — Production Hardening

1. **Set `APP_DEBUG=false`** in production `.env`.
2. **Run `php artisan config:cache`** and `php artisan route:cache`.
3. **Add DB CHECK constraints**: apply the migration in Section 3.
4. **Add partial unique indexes**: apply the migration in Section 3.
5. **Configure rate limiting** on the API routes in `app/Http/Middleware/` or via `Route::middleware('throttle:60,1')`.
6. **Set up CORS** via `config/cors.php` — restrict `allowed_origins` to known frontend domains.
7. **Configure file storage** for S3 and set `FILESYSTEM_DISK=s3`.
8. **Set up queue workers** with Supervisor.
9. **Set up scheduled commands** with cron: `* * * * * php /var/www/html/artisan schedule:run`.
10. **Configure logging** to daily or remote log aggregation.
11. **Run `php artisan db:seed`** to seed roles, permissions, and initial data.

---

## Step 17 — Deploy

```bash
# On production server
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Use CI/CD (GitHub Actions or similar) to automate this sequence after tests pass.

---

# Appendix A: Permission Registration Seeder

```php
// database/seeders/RolePermissionSeeder.php
public function run(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $permissions = [
        'plants.view', 'plants.create', 'plants.edit', 'plants.delete',
        'chemicals.view', 'chemicals.create', 'chemicals.edit', 'chemicals.delete',
        'chemical_batches.view', 'chemical_batches.create', 'chemical_batches.edit', 'chemical_batches.delete',
        'chemical_usage.view', 'chemical_usage.create',
        'equipment.view', 'equipment.create', 'equipment.edit', 'equipment.delete',
        'borrows.view', 'borrows.create', 'borrows.return', 'borrows.approve',
        'transactions.view',
        'reports.view',
        'achievements.manage',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
    }

    $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
    $admin->syncPermissions($permissions); // admin gets all

    $manager = Role::firstOrCreate(['name' => 'lab-manager', 'guard_name' => 'api']);
    $manager->syncPermissions([
        'plants.view', 'plants.create', 'plants.edit',
        'chemicals.view', 'chemicals.create', 'chemicals.edit',
        'chemical_batches.view', 'chemical_batches.create', 'chemical_batches.edit',
        'chemical_usage.view', 'chemical_usage.create',
        'equipment.view', 'equipment.create', 'equipment.edit',
        'borrows.view', 'borrows.create', 'borrows.return', 'borrows.approve',
        'transactions.view', 'reports.view',
    ]);

    $researcher = Role::firstOrCreate(['name' => 'researcher', 'guard_name' => 'api']);
    $researcher->syncPermissions([
        'plants.view', 'plants.create',
        'chemicals.view',
        'chemical_batches.view',
        'chemical_usage.view', 'chemical_usage.create',
        'equipment.view',
        'borrows.view', 'borrows.create',
    ]);

    $student = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api']);
    $student->syncPermissions([
        'plants.view',
        'chemicals.view',
        'equipment.view',
        'borrows.view', 'borrows.create',
    ]);
}
```

---

# Appendix B: InsufficientStockException

```php
// app/Exceptions/InsufficientStockException.php
class InsufficientStockException extends \RuntimeException
{
    public function __construct(
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(
            "Insufficient stock. Requested: {$requested}, Available: {$available}."
        );
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'requested' => $this->requested,
            'available' => $this->available,
        ], 422);
    }
}
```

---

# Appendix C: Morph Map Registration

Register morph aliases to keep `borrowable_type` and `transactionable_type` values short and stable:

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Database\Eloquent\Relations\Relation;

public function boot(): void
{
    Relation::morphMap([
        'equipment'    => \App\Modules\Inventory\Models\Equipment::class,
        'chemical'     => \App\Modules\Inventory\Models\Chemical::class,
        'plant_sample' => \App\Modules\Inventory\Models\PlantSample::class,
        'plant_stock'  => \App\Modules\Inventory\Models\PlantStock::class,
        'plant_species' => \App\Modules\Inventory\Models\PlantSpecies::class,
        'plant_variety' => \App\Modules\Inventory\Models\PlantVariety::class,
    ]);
}
```

---

_End of Inventory Blueprint._
_This document was auto-generated through full codebase reverse-engineering on March 12, 2026._
