# STRYDEX Sport Shoes Store Platform

An advanced, cloud-inspired e-commerce platform and business intelligence ecosystem tailored for high-performance sports footwear. **STRYDEX** merges traditional PHP/MySQL transactional paradigms with next-generation client-side tools, featuring an interactive 3D Custom Product Customizer, comprehensive multi-tier user dashboards, and real-time generative AI sub-systems powered by the Google Gemini API.


## 🚀 Key Pillars & Features

### 1. Immersive User Experience (Storefront & Catalog)

* **Dynamic Cinematic Hero Section:** Smooth background video sequencing switching automatically upon completion, backed by a micro-typewriter title engine looping high-conversion branding phrases.
* **Intelligent Virtual Inventory System:** Context-aware product details that map multi-variate sizes and colors from single master rows. Tailored specifically to support unique custom material selections for advanced 3D models while safely managing unified stock limits.
* **Asynchronous Floating Mini-Cart:** Implements state tracking across pages, updating price summaries and quantities seamlessly on structural client interactions without requiring standard page refreshes.

### 2. Generative AI Cognitive Engine (Gemini API Integration)

* **AI Foot Sizer & Smart Recommendations:** Generates unique session-bound encryption tokens paired with QR codes, enabling real-time multi-device capture pipelines. Downscales and handles binary canvas transfers smoothly to prevent common server memory limits. Uses specialized visual analytics to map foot shapes and render anatomical line coordinates directly onto a dynamic overlay `<canvas>` block.
* **AI Triple-View Wear Assessment:** Analyzes user-uploaded front, left, and right profile photos of old footwear to calculate wear patterns across soles and midsoles, providing automated safety degradation insights.
* **Semantic Prompt-to-Texture 3D Customizer:** Allows customers to type creative themes (e.g., *“Cyberpunk 2077”* or *“Volcanic Lava”*), transforming natural language text descriptions directly into fully colored, material-mapped 3D assets inside the virtual layout.

### 3. High-Fidelity 3D Product Customizer

* **WebGL PBR Material Mapping:** Built on a `<model-viewer>` architecture, allowing real-time injection of high-resolution micro-surface Normal Maps (Leather, Canvas, Satin, Jersey) directly into distinct GLTF node meshes.
* **Undo/Redo State Stack Manager:** Keeps track of color and material configurations via a specialized JavaScript array stack history, allowing users to move backward or forward through design edits effortlessly.
* **Canvas Share-Poster Engine:** Combines the active WebGL buffer snapshot matrix with dynamic graphical layers onto a local 2D HTML5 canvas, outputting high-resolution downloadable marketing cards instantly.

### 4. Enterprise-Grade Admin Command & Control

* **Financial Growth Visualizers:** Track and display Month-over-Month (MoM) revenue changes automatically using context-aware growth metrics that adjust indicators intuitively for positive performance versus pending bottlenecks.
* **AI Hot-Product Demand Forecasting:** Feeds complex product sales matrices directly into Gemini analytical nodes to generate automated trend forecasts and rank upcoming best-sellers.
* **AI Market Gap & Concept Innovator:** Evaluates underperforming categories and inventory imbalances to propose new product configurations. Automates the generation of missing features and maps multi-angle concept renders dynamically via model streams.
* **Automated Low-Stock Alert System:** Continuously monitors inventory depth during checkout transactions, instantly triggering real-time warning notifications whenever product combinations fall below a 5-unit threshold.

---

## 🛠️ Technology Stack & Ecosystem

| Layer | Technology / Library | Description |
| --- | --- | --- |
| **Backend Core** | PHP 8.x (OOP & Procedural Mix) | Main application engine, processing secure API routing and transactional session parameters. |
| **Database** | MySQL (InnoDb Engine) | Structured data management, indexed relationships, foreign key cascade protections. |
| **Client Interface** | HTML5, CSS3, JavaScript (ES6+) | Core architecture for UI/UX interaction flows and state handling. |
| **Styling Framework** | Bootstrap 5.3 & Bootstrap Icons | Layout structure, adaptive component grids, fluid responsive mechanics. |
| **AI Processing** | Google Gemini API (2.5 Flash / Lite) | Natural language text evaluation, structured JSON parsing, intelligent forecasting. |
| **3D Graphics** | Google `<model-viewer>` / Three.js | In-browser PBR WebGL model interaction and dynamic texturing pipelines. |
| **Communication** | PHPMailer | Secure registration workflows backed by robust TLS SMTP Gmail handling. |
| **UI Components** | SweetAlert2 & Flatpickr | Smooth popups, diagnostic load animations, and calendar range pickers. |

---

## 📂 System Architecture & Modules

```php
STRYDEX_PROJECT_ROOT/
│
├── includes/
│   ├── db_connection.php      // Central MySQLi database connection instance
│   ├── material_configs.php   // Cost matrix maps for micro-surface normal map textures
│   ├── header.php             // Global application navigation bar & session state management
│   └── footer.php             // Application footer layout
│
├── Module A/                  // Identity Access Management (IAM) & Profiles
│   ├── login.php              // Administrative & customer entry handler
│   ├── register.php           // Multi-tier user signup with PHPMailer SMTP integration
│   ├── verify_otp.php         // Time-boxed security token validator
│   ├── user_dashboard.php     // Profile control panel with soft-deleted multi-address book
│   └── send_otp.php           // Security token factory code generator
│
├── Module B/                  // Transaction Engine & E-Commerce Operations
│   ├── catalogue.php          // Global product inventory display with brand filters
│   ├── product_details.php    // High-performance canvas hosting AI Sizer and AI Wear modules
│   ├── custom_builder.php     // WebGL 3D configuration dashboard with Undo/Redo state engine
│   ├── cart.php               // Core shopping cart logic with live stock validation
│   ├── checkout.php           // Single-page billing, voucher application, and payment gateway router
│   ├── wallet.php             // Digital ledger tracking top-ups and deductions
│   └── gemini_handler.php     // API gateway for AI features (sizing, wear reports, texturing)
│
└── admin/                     // Corporate Business Intelligence Suite
    ├── admin_dashboard.php    // Command grid combining analytic charts with AI predictive modules
    ├── admin_sidebar.php      // Role-filtered navigation panel with auto-capping unread badges
    └── admin_notifications.php// Notification center tracking system warnings and low-stock alerts

```

---

## 🔒 Security Hardening

The platform implements strict security layers to maintain data privacy and systemic stability:

* **Prepared Statements Protection:** Database input vectors use strict MySQLi parameter binding to protect against SQL injection vulnerabilities.
* **Cryptographic Verification Layers:** Critical actions like password modifications are locked behind OTP-verified states, preventing cross-site session high-jacking.
* **Anti-Tainted Canvas Sandbox Boundaries:** Generative canvas operations explicitly declare `crossOrigin = 'anonymous'` flags to maintain a secure memory context.
* **Address Book Data Isolation:** Built with a specialized soft-deletion strategy (`Is_Deleted = 1`), allowing users to clean up their active dashboards while keeping historical strings intact for continuous invoice audit compliance.

---

## 💻 Installation & Environment Setup

Follow these steps to deploy the project locally in an isolated XAMPP development environment:

### Prerequisites

* XAMPP or WAMP server with PHP 8.0 or higher.
* A valid Google Gemini API Key.
* A Gmail account configured for App Passwords (if using email registration notifications).

### Step 1: Clone and Position the Directory

Clone the repository and place the project root folder inside your local server environment directory:

```bash
# For Windows XAMPP installations
C:\xampp\htdocs\strydex\

```

### Step 2: Initialize the Database Schema

1. Open XAMPP Control Panel and start the **Apache** and **MySQL** services.
2. Navigate to your web browser and open `http://localhost/phpmyadmin/`.
3. Create a new database named `strydex_db`.
4. Import the provided structural dump database file (e.g., `strydex_db.sql`) to generate all tables, indexed relations, and initial product entries.

### Step 3: Configure Project Environment Files

Create a local environment file within the system administration directory to establish your API access parameters:

```env
# Path: includes/Tung_Gemini_API.env
GEMINI_API_KEY=YOUR_ACTUAL_GOOGLE_GEMINI_API_KEY_HERE
MOBILE_DEVICE_IP=localhost

```

Configure your mail server preferences inside the root structure to enable registration verification:

```php
// Path: includes/mail_config.php
define('SMTP_EMAIL', 'your-system-email@gmail.com');
define('SMTP_PASS', 'your-16-character-gmail-app-password');

```

### Step 4: Run the Application

Open your web browser and head to the local address to explore the storefront:

```bash
http://localhost/strydex/index.php

```

To access the corporate analytical control panel directly, navigate to:

```bash
http://localhost/strydex/admin/admin_dashboard.php

```

---

## 📄 License & Terms

This software ecosystem is built for educational, portfolio, and commercial evaluation purposes. All product imagery, brand trademarks, and 3D assets remain the legal property of their respective creators and official brand distributors.