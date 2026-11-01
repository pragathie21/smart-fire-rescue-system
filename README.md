# 🚒 Smart Fire and Rescue Response System

### Town of Sudar Emergency Services | Computer-Aided Dispatch (CAD)

A full-stack, database-connected emergency management and response system developed for the fictional town of Sudar. The system automates emergency incident reporting, severity assessment, priority-based dispatching, equipment compatibility verification, and shortest-route calculation.

**Built with PHP 8.2, MySQL 8.x, Java 25, HTML5, CSS3 and JavaScript.**

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![Java](https://img.shields.io/badge/Java-25-ED8B00?logo=openjdk&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?logo=javascript&logoColor=black)
![Status](https://img.shields.io/badge/Status-Academic%20Project-success)

---

## 📌 1. Project Overview

The **Smart Fire and Rescue Response System** is a Computer-Aided Dispatch (CAD) application designed to improve emergency response operations through intelligent automation.

Traditional emergency response systems may experience delays due to manual incident prioritization, unsuitable unit assignments, equipment mismatches, and inefficient route selection.

This system addresses these challenges by integrating automated incident assessment, priority-based dispatching, equipment compatibility checking, and Dijkstra's Shortest Path Algorithm.

The application uses a simulated 14-node road network representing the fictional town of Sudar, alongside real-world GPS location detection and interactive maps.

### 🎯 Project Objectives

- Automate emergency incident registration.
- Generate sequential incident IDs automatically.
- Identify emergency severity using a rules-based assessment engine.
- Prioritize incidents according to urgency.
- Automatically assign suitable available emergency units.
- Verify equipment compatibility before dispatch.
- Calculate the shortest travel-time route.
- Support dynamic road closures and alternative route calculation.
- Notify authorized duty officers through email.
- Maintain incident, dispatch, and notification records.

---

## ✨ 2. Key Features

### 2.1 Incident Reporting and Location Detection

- Automatic sequential incident IDs using MySQL `AUTO_INCREMENT`.
- GPS-based location detection using the browser's geolocation API.
- Reverse geocoding fallback for location identification.
- Interactive Leaflet maps using OpenStreetMap.
- Simulated 14-node Sudar road network.
- Incident location and assessment data persistence.

### 2.2 Automated Severity Assessment

The system uses a 10-question assessment form to evaluate emergency conditions.

The `SeverityScorer.php` engine calculates a transparent rules-based severity score and classifies incidents into four levels:

| Severity | Priority |
|---|---|
| Critical | Highest |
| High | High |
| Medium | Moderate |
| Low | Normal |

Administrators can also perform authorized severity overrides.

### 2.3 Equipment Compatibility Management

The application supports seven standardized emergency equipment types.

- Multiple equipment selection using checkboxes.
- Normalized relational database storage.
- Unit equipment inventory management.
- Equipment compatibility verification.
- Suitable response unit identification.

### 2.4 Automatic Priority-Based Dispatch

The dispatch engine automatically:

1. Identifies pending incidents.
2. Evaluates incident priority.
3. Identifies available response units.
4. Checks unit type and equipment compatibility.
5. Calculates travel time using the shortest available route.
6. Assigns a suitable response unit.
7. Stores dispatch information using atomic MySQL transactions.

The system also supports manual dispatching by authorized dispatchers.

### 2.5 Dijkstra Shortest Route Planner

The project implements Dijkstra's Shortest Path Algorithm in both PHP and Java.

Features include:

- Weighted road network.
- Travel-time-based shortest path calculation.
- Dynamic road closure and reopening.
- Alternative route selection.
- Unreachable destination detection.
- Route restoration after road reopening.

### 2.6 Email Notification System

The notification system supports:

- SMTP-based email delivery.
- STARTTLS secure connection.
- SMTP authentication.
- HTML-formatted email notifications.
- Authorized duty officer notifications.
- Notification status tracking.
- Email audit logs.
- Retry functionality.
- Local email file fallback for development.

### 2.7 Incident Lifecycle Management

The system supports the following incident lifecycle:

`Pending → Dispatched → In Progress → Completed`

Additional functionality:

- Track incident progress.
- Maintain dispatch history.
- Release response units after mission completion.
- Update unit availability.
- Automatically re-evaluate waiting incidents.

### 2.8 User Authentication and Role Management

Three user roles are supported:

| Role | Permissions |
|---|---|
| Officer | Update mission progress, complete missions, release units |
| Admin | Full administrative control, severity overrides, road management |
| Dispatcher | Incident intake, automatic and manual dispatching |

Authentication uses PHP sessions and hashed passwords.

---

## 🏗️ 3. System Architecture and Workflow

The system combines a PHP backend, MySQL database, Java routing implementation, and a Vanilla JavaScript frontend.

### Emergency Response Workflow

```text
        Incident Reporting
                |
                v
       GPS Location Detection
                |
                v
      10-Question Assessment
                |
                v
      Severity Scoring Engine
                |
                v
       Priority Classification
                |
                v
      Equipment Compatibility
                |
                v
       Available Unit Selection
                |
                v
       Automatic Unit Dispatch
                |
                v
       Dijkstra Route Planning
                |
                v
       Email Notifications
                |
                v
       Incident Status Tracking
                |
                v
        Mission Completion
                |
                v
       Unit Availability Update
                |
                v
       Pending Incident Recheck
```

### Main Architectural Components

| Component | Responsibility |
|---|---|
| Frontend | Incident forms, dashboards, maps and management pages |
| PHP Backend | Business logic, authentication and dispatch processing |
| MySQL | Persistent storage and transaction management |
| Java Routing Engine | Dijkstra algorithm implementation |
| Email Service | SMTP notifications and delivery logs |
| Leaflet / OpenStreetMap | Interactive geographical visualization |

---

## 🗄️ 4. Database Schema

**Database Name:** `smart_fire_rescue`

The system uses 11 normalized MySQL InnoDB tables with foreign key constraints, indexes, and transactional integrity.

| Table | Purpose |
|---|---|
| `locations` | Sudar landmarks and geographical coordinates |
| `stations` | Emergency fire and rescue stations |
| `equipment_types` | Standardized emergency equipment catalog |
| `response_units` | Fire engines and rescue vans |
| `unit_equipment` | Equipment inventories assigned to units |
| `roads` | Weighted road network connections |
| `incidents` | Incident reports and severity assessments |
| `incident_equipment` | Equipment requirements for incidents |
| `dispatch_records` | Unit assignments, routes and travel times |
| `users` | Authorized officers, admins and dispatchers |
| `email_logs` | Notification delivery attempts and audit records |

The database uses:

- InnoDB storage engine.
- Foreign key constraints.
- Auto-increment primary keys.
- Indexed columns.
- PDO prepared statements.
- Atomic transactions.

---

## 🛠️ 5. Technology Stack

| Technology | Version / Tool | Purpose |
|---|---|---|
| PHP | 8.2 | Backend development |
| Java | OpenJDK 25 | Routing algorithm |
| MySQL | 8.x | Database management |
| HTML5 | Modern HTML | Web structure |
| CSS3 | Custom Properties | Emergency-themed interface |
| JavaScript | Vanilla JS | Frontend interactions |
| Leaflet.js | 1.9.4 | Interactive maps |
| OpenStreetMap | Map data | Geographical visualization |
| Apache | 2.4 | Web server |
| XAMPP | Local environment | Development and testing |
| SMTP | STARTTLS | Email notifications |

### Important Algorithms and Concepts

- Dijkstra's Shortest Path Algorithm.
- Priority-based incident scheduling.
- Rules-based severity scoring.
- Equipment compatibility matching.
- Database transactions and concurrency control.
- Object-Oriented Programming.
- Relational database normalization.

---

## 📂 6. Project Structure

```text
smart-fire-rescue/
│
├── actions/
│   ├── auto-dispatch.php
│   ├── close-road.php
│   ├── complete-mission.php
│   ├── dispatch-incident.php
│   ├── override-severity.php
│   ├── reopen-road.php
│   ├── retry-email.php
│   ├── save-incident.php
│   └── update-incident-status.php
│
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
│
├── classes/
│   ├── DispatchService.php
│   ├── EmailNotificationService.php
│   ├── Incident.php
│   ├── ResponseUnit.php
│   ├── RoadNetwork.php
│   ├── RoutePlanner.php
│   └── SeverityScorer.php
│
├── includes/
│   ├── auth.php
│   ├── footer.php
│   ├── functions.php
│   ├── header.php
│   └── sidebar.php
│
├── Main.java
├── DispatchRecord.java
├── DispatchService.java
├── FireEngine.java
├── FireStation.java
├── Incident.java
├── IncidentManager.java
├── IncidentPriorityPolicy.java
├── RescueVan.java
├── ResponseUnit.java
├── RoadNetwork.java
├── RoutePlanner.java
├── RoutePlannerCli.java
├── RoutePlannerTest.java
│
├── api.php
├── config.php
├── db.php
├── database.sql
├── dashboard.php
├── report-incident.php
├── incidents.php
├── incident-details.php
├── units.php
├── roads.php
├── dispatch.php
├── records.php
├── login.php
├── logout.php
├── migrate.php
├── test_suite.php
├── README.md
└── .gitignore
```

---

## ⚙️ 7. Installation and Setup

### Prerequisites

Install the following software:

- XAMPP with PHP 8.2.
- MySQL 8.x or compatible MySQL/MariaDB installation.
- Java JDK 25.
- Web browser.
- Git.

### Step 1: Clone the Repository

Open PowerShell or VS Code Terminal:

```bash
git clone https://github.com/pragathie21/smart-fire-rescue-system.git
```

### Step 2: Move Project into XAMPP

Place the project folder inside:

```text
C:\xampp\htdocs\smart-fire-rescue
```

### Step 3: Start Apache and MySQL

Open XAMPP Control Panel.

Start:

- Apache (Port 80 / 443)
- MySQL (Port 3306)

### Step 4: Create the Database

**Option A: Automated Migration (Recommended)**

Open PowerShell:

```powershell
cd C:\xampp\htdocs\smart-fire-rescue

C:\xampp\php\php.exe migrate.php
```

**Option B: phpMyAdmin Import**

1. Open `http://localhost/phpmyadmin/`.
2. Create a database named `smart_fire_rescue`.
3. Select `utf8mb4_unicode_ci` collation.
4. Select the database.
5. Open the Import tab.
6. Choose `database.sql`.
7. Click Import.

### Step 5: Configure Database Connection

Inspect `db.php` and configure the database connection:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'smart_fire_rescue');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Adjust credentials according to your local MySQL configuration.

### Step 6: Configure SMTP Email (Optional)

Copy:

```text
smtp_config.example.php
```

Rename the copy to:

```text
smtp_config.php
```

Configure your SMTP settings:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_AUTH', true);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_SECURE', 'tls');
define('SMTP_FROM_EMAIL', 'your-sender@gmail.com');
define('SMTP_FROM_NAME', 'Sudar Emergency Dispatch');
define('OFFICER_NOTIFY_EMAIL', 'your-officer-email@example.com');
define('SMTP_LOG_FALLBACK', true);
```

**Security:** Never upload real SMTP passwords, app passwords, or private credentials to GitHub.

When live SMTP is not configured, the local fallback can generate email HTML logs under:

```text
storage/emails/
```

### Step 7: Run the Application

Open:

```text
http://localhost/smart-fire-rescue/
```

Dashboard:

```text
http://localhost/smart-fire-rescue/dashboard.php
```

---

## 🧪 8. Testing and Verification

The project includes a PHP test suite and Java routing tests.

### PHP Automated Test Suite

Execute:

```powershell
cd C:\xampp\htdocs\smart-fire-rescue

C:\xampp\php\php.exe test_suite.php
```

The 14-point test suite validates:

1. Sequential incident ID generation.
2. GPS coordinate and geocoded address persistence.
3. Rules-based severity assessment.
4. Multiple equipment requirements.
5. Priority-based dispatch and duplicate-assignment locking.
6. Dijkstra shortest-path calculation.
7. Alternative route selection after road closure.
8. Route restoration after road reopening.
9. Unreachable destination detection.
10. Email dispatch notification generation.
11. Safe email retry mechanism.
12. Mission completion and unit availability release.
13. Authorized severity score override.
14. Database consistency across models.

### Java Routing Tests

Compile:

```bash
javac RoutePlannerTest.java
```

Execute:

```bash
java RoutePlannerTest
```

The four test cases cover:

| Test | Scenario | Expected Result |
|---|---|---|
| Test 1 | Normal route | 7 minutes |
| Test 2 | Alternative route after road closure | 20 minutes |
| Test 3 | Unreachable destination | -1 minutes |
| Test 4 | Route after road reopening | 7 minutes |

### Java CLI Routing

Run:

```bash
java RoutePlannerCli StationA Hospital
```

Expected JSON output:

```json
{
  "success": true,
  "origin": "StationA",
  "destination": "Hospital",
  "path": ["StationA", "Road1", "Hospital"],
  "travel_time_minutes": 7
}
```

---

## 🌐 9. Web Application Navigation

| Module | Local URL | Description |
|---|---|---|
| Dashboard | `/dashboard.php` | Emergency statistics and active units |
| Report Incident | `/report-incident.php` | GPS, map and emergency assessment |
| Incident Management | `/incidents.php` | Incident records and priority |
| Incident Details | `/incident-details.php?id=1` | Route, severity and mission controls |
| Response Units | `/units.php` | Unit availability and equipment |
| Dispatch History | `/records.php` | Dispatch and notification audit records |
| Road Network | `/roads.php` | Road closures and route testing |
| Login | `/login.php` | Officer and administrator authentication |
| REST API | `/api.php` | JSON endpoints |

All URLs above are relative to:

```text
http://localhost/smart-fire-rescue
```

---

## 🔮 10. Future Enhancements

- Real-time emergency vehicle GPS tracking.
- Live traffic-based route optimization.
- Mobile application for emergency incident reporting.
- AI-based emergency severity prediction.
- Advanced emergency resource allocation.
- Integration with real emergency service infrastructure.
- Real-time notifications and monitoring dashboards.

---

## ⚠️ 11. Disclaimer

This software is an academic simulation prototype developed for educational and demonstration purposes.

It uses a fictional town named Sudar and is not connected to real municipal fire departments, police Computer-Aided Dispatch systems, or live 911/112 emergency networks.

The system should not be used for actual emergency response operations.

---

## 👩‍💻 12. Project Information

**Project:** Smart Fire and Rescue Response System

**Organization:** Town of Sudar Emergency Services

**Domain:** Emergency Management | Web Development | Data Structures and Algorithms

**Architecture:** Full-Stack Web Application with Java Routing Engine

**GitHub Repository:**

https://github.com/pragathie21/smart-fire-rescue-system

---

⭐ If you find this project interesting, consider giving the repository a star!

### Faster Decisions. Smarter Dispatch. Safer Communities. 🚒
## Project Screenshots

### Home Page
![Home Page](screenshots/Screenshot%202026-10-29%20221528.png)

### Incident Report
![Incident Report](screenshots/Screenshot%202026-10-29%2022137.png)

### Dashboard
![Dashboard](screenshots/Screenshot%202026-10-29%20222408.png)