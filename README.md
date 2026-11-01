# Smart Fire and Rescue Response System
**Town of Sudar Emergency Services &bull; Computer-Aided Dispatch (CAD)**

A full-stack, database-connected emergency management and response system built for the fictional town of **Sudar**, utilizing **PHP 8.2**, **MySQL (InnoDB)**, **Java 25**, and modern **Vanilla HTML5/CSS3/JavaScript**.

---

## 1. System Overview & Architecture

The **Smart Fire and Rescue Response System** provides an automated, end-to-end emergency management workflow:
1. **Intake & Sequential IDs**: Incident reporting without manual IDs; IDs are generated sequentially (`1, 2, 3...`) via MySQL `AUTO_INCREMENT`.
2. **Geographical Support**: Real-world GPS geolocation ("Use My Current Location"), reverse geocoding fallback, and interactive Leaflet OpenStreetMap alongside the simulated 14-node Sudar road network.
3. **Automated Severity Assessment**: A 10-question assessment form evaluated by a transparent rules-based scoring engine (`SeverityScorer.php`), categorizing emergencies into `Low`, `Medium`, `High`, or `Critical`.
4. **Multiple Equipment Checkboxes**: Selection of 7 standardized equipment types with normalized relational storage and unit inventory compatibility verification.
5. **Priority-Based Dispatch**: Automatic assignment matching highest-priority incidents, unit type/equipment compatibility, and Dijkstra shortest travel time, locked via atomic MySQL transactions.
6. **Email Notifications**: Real SMTP email notifications sent to authorized duty officers via an RFC 5321 socket client / PHPMailer with status tracking, audit logs, and retry capabilities.
7. **Dijkstra Shortest Route Planner**: Dual implementation in PHP and Java with dynamic road closure/reopening support and non-negative travel times.
8. **Incident Lifecycle & Auth**: Multi-stage mission status (`Pending` ➔ `Dispatched` ➔ `In Progress` ➔ `Completed`), session-based officer authentication, unit release to `Available`, and automatic re-evaluation of waiting incidents.

---

## 2. Database Schema (`smart_fire_rescue`)

The system utilizes 11 normalized InnoDB tables with foreign key constraints, indexes, and transactional integrity:

| Table | Purpose | Primary Key | Key Foreign Keys & Indexes |
| :--- | :--- | :--- | :--- |
| `locations` | Sudar landmark locations & coordinates | `location_id` | `VARCHAR(50)` |
| `stations` | Emergency fire & rescue stations | `station_id` | `location_id` ➔ `locations` |
| `equipment_types` | Master catalog of certified emergency equipment | `code` | `VARCHAR(50)` |
| `response_units` | Emergency response fleet (Engines, Vans) | `unit_id` | `station_id` ➔ `stations` |
| `unit_equipment` | Equipment inventory assigned to each unit | `id` (Auto) | `unit_id` ➔ `response_units`, `equipment_code` ➔ `equipment_types` |
| `roads` | Weighted adjacency graph connecting locations | `road_id` (Auto) | `from_location_id`, `to_location_id` ➔ `locations`, `status` |
| `incidents` | Emergency incidents with assessment & triage | `incident_id` (Auto) | `location_id` ➔ `locations`, `status`, `severity_tier` |
| `incident_equipment` | Mandatory equipment needed per incident | `id` (Auto) | `incident_id` ➔ `incidents`, `equipment_code` ➔ `equipment_types` |
| `dispatch_records` | Dispatch assignments, routes, travel time | `dispatch_id` (Auto) | `incident_id` ➔ `incidents`, `unit_id` ➔ `response_units` |
| `users` | Authorized personnel, dispatchers & chiefs | `user_id` (Auto) | `username` (Unique), `role` |
| `email_logs` | Audit trail of notification delivery attempts | `log_id` (Auto) | `dispatch_id` ➔ `dispatch_records`, `status` |

---

## 3. Technology Stack

- **Backend**: Native Object-Oriented PHP 8.2 with PDO prepared statements and atomic transactions.
- **Database**: MySQL 8.x (InnoDB engine, foreign keys, `utf8mb4`).
- **Java Routing Engine**: OpenJDK 25 (`RoadNetwork.java`, `RoutePlanner.java`, `RoutePlannerCli.java`, `RoutePlannerTest.java`).
- **Frontend**: Vanilla HTML5, CSS3 Custom Properties (Emergency Dark Theme), Leaflet.js 1.9.4 for OpenStreetMap.
- **Email Delivery**: RFC 5321 Direct SMTP Socket Client with STARTTLS, AUTH LOGIN, MIME HTML formatting, and local file fallback.
- **Web Server**: Apache 2.4 / XAMPP on Windows.

---

## 4. Setup & Installation Instructions

### Step 1: Place Files in XAMPP htdocs
Clone or extract the repository to:
```
C:\xampp\htdocs\smart-fire-rescue
```

### Step 2: Start Apache and MySQL
Open the **XAMPP Control Panel** and click **Start** next to:
- **Apache** (Port 80 / 443)
- **MySQL** (Port 3306)

### Step 3: Database Import via phpMyAdmin or Migration Script
**Option A: Automated Migration (Recommended)**
Open PowerShell or Command Prompt:
```powershell
cd C:\xampp\htdocs\smart-fire-rescue
C:\xampp\php\php.exe migrate.php
```

**Option B: phpMyAdmin Import**
1. Navigate to `http://localhost/phpmyadmin/`.
2. Click **Databases** ➔ Create database named `smart_fire_rescue` with collation `utf8mb4_unicode_ci`.
3. Select `smart_fire_rescue` from the sidebar.
4. Click the **Import** tab.
5. Choose file `C:\xampp\htdocs\smart-fire-rescue\database.sql` and click **Import**.

### Step 4: Verify Database Connection (`db.php`)
Inspect `db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'smart_fire_rescue');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Step 5: Configure SMTP Email (`smtp_config.php`)
Copy `smtp_config.example.php` to `smtp_config.php` and configure:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_AUTH', true);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_SECURE', 'tls');
define('SMTP_FROM_EMAIL', 'cad@sudar.gov.in');
define('SMTP_FROM_NAME', 'Sudar Emergency Dispatch');
define('OFFICER_NOTIFY_EMAIL', 'duty-officer@sudar.gov.in');
define('SMTP_LOG_FALLBACK', true);
```
*Note: If no live SMTP server is configured, `SMTP_LOG_FALLBACK` is enabled by default. Notifications will be logged to `storage/emails/email_dispatch_*.html` with complete delivery simulation.*

---

## 5. User Roles & Credentials

The database is pre-seeded with 3 authorized accounts (passwords hashed with `PASSWORD_BCRYPT`):

| Username | Password | Role | Permissions |
| :--- | :--- | :--- | :--- |
| `officer` | `Officer@2026` | `Officer` | Mark In-Progress, Complete missions, release units |
| `admin` | `Admin@2026` | `Admin` | Full administrative control, severity triage overrides, road controls |
| `dispatcher` | `Dispatch@2026` | `Dispatcher` | Incident intake, automatic and manual dispatching |

---

## 6. Testing & Verification

### Running the Full PHP Test Suite
Execute the 14-point test suite in PowerShell:
```powershell
cd C:\xampp\htdocs\smart-fire-rescue
C:\xampp\php\php.exe test_suite.php
```
All 14 automated tests validate:
1. Sequential Incident IDs (`1, 2, 3...`)
2. GPS coordinate and geocoded address persistence
3. Rules-based severity triage and score calculation
4. Multiple equipment capability requirements
5. Priority dispatch and double-assignment locking
6. Dijkstra shortest-path calculation
7. Alternate route routing on road closure
8. Road reopening path restoration
9. Unreachable destination detection
10. Email dispatch notification generation
11. Safe email retry mechanism
12. Mission completion and unit availability release
13. Authorized severity score override
14. MySQL database consistency across models

### Running the Java Routing Tests
Verify Dijkstra's algorithm in Java:
```powershell
cd C:\xampp\htdocs\smart-fire-rescue
javac RoutePlannerTest.java
java RoutePlannerTest
```
All 4 Java test cases will execute:
- Test 1: Normal route (`StationA -> Road1 -> Hospital`, 7 mins)
- Test 2: Alternate route after closing Road1-Hospital (`StationA -> Road2 -> Factory -> Road4 -> TechPark -> Hospital`, 20 mins)
- Test 3: Unreachable destination detection (`-1 mins`, empty path)
- Test 4: Road reopening restoration (`StationA -> Road1 -> Hospital`, 7 mins)

### Running Java CLI Routing directly:
```powershell
java RoutePlannerCli StationA Hospital
# Output: {"success":true,"origin":"StationA","destination":"Hospital","path":["StationA","Road1","Hospital"],"travel_time_minutes":7}
```

---

## 7. Web Application Navigation

| Page | URL | Description |
| :--- | :--- | :--- |
| **Dashboard** | `http://localhost/smart-fire-rescue/dashboard.php` | Real-time statistics, active units, and recent incidents |
| **Report Incident** | `http://localhost/smart-fire-rescue/report-incident.php` | GPS location, Leaflet map, assessment questions, equipment checkboxes |
| **Incident Management** | `http://localhost/smart-fire-rescue/incidents.php` | Tabular list of incidents, statuses, triage scores, and filters |
| **Incident Details** | `http://localhost/smart-fire-rescue/incident-details.php?id=1` | Live route map, triage breakdown, override controls, and mission completion |
| **Response Units** | `http://localhost/smart-fire-rescue/units.php` | Unit fleet status, station assignments, and onboard equipment inventories |
| **Dispatch History** | `http://localhost/smart-fire-rescue/records.php` | Complete audit trail of assignments, travel times, and notification delivery |
| **Road Network Manager** | `http://localhost/smart-fire-rescue/roads.php` | Interactive road closure/reopening controls and Dijkstra route test simulator |
| **Login / Logout** | `http://localhost/smart-fire-rescue/login.php` | Session-based authentication with 1-click role demo logins |
| **REST API Gateway** | `http://localhost/smart-fire-rescue/api.php` | JSON endpoints consumed by static HTML pages and external clients |

---

## 8. Disclaimer
This software is an academic simulation prototype designed for educational demonstration in emergency management systems. It is not connected to real municipal fire departments, police CAD systems, or live 911/112 emergency networks.
