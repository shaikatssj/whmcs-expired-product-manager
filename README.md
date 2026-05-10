Expired Products Manager Pro for WHMCS
Expired Products Manager Pro is a comprehensive admin-side management tool for WHMCS. It transforms the way hosting providers track and handle expiring services by providing real-time analytics, visual data representation, and powerful bulk actions within a modern, responsive interface.

🚀 Key Features
Advanced Analytics Dashboard:

Visual KPIs for Total Tracked, Expiring Soon, Recently Expired, and Suspended services.

Revenue at Risk: Instantly see the monetary value of services nearing expiration.

Interactive Visualizations:

Doughnut Chart: Breakdown of expiration categories.

Bar Chart: 30-day expiration forecast timeline.

Dynamic UI:

Dark & Light Mode: Toggle themes directly from the module configuration.

Glassmorphism Design: Modern UI built with Tailwind-inspired CSS and Inter fonts.

Bulk Management:

One-Click Reminders: Send "Service Overdue" emails to selected clients via the WHMCS Local API.

Batch Termination: Safely terminate multiple long-expired services at once.

Utility Tools:

Real-time Filtering: Search by client, domain, or status without reloading the page.

Data Export: Export your filtered views to CSV or generate a clean PDF/Print report.

Instant Sorting: Clickable headers to sort by amount, due date, or days remaining.

🛠 Installation
Upload Files:
Create a folder named expiredproducts in your WHMCS directory:
/modules/addons/expiredproducts/

Add Module File:
Upload the expiredproducts.php file into that folder.

Activate:

Log in to your WHMCS Admin Area.

Navigate to System Settings > Addon Modules.

Find Expired Products Manager, click Activate, and then Configure.

Permissions:
Ensure you grant "Full Administrator" (or your specific role) access in the module configuration.

⚙️ Configuration
Within the WHMCS Addon configuration page, you can customize:

Expiry Warning Days: Define the threshold for "Expiring Soon" (default is 7 days).

Theme: Switch between a professional light theme or a sleek dark mode.

Logo: Provide a custom URL for your branding within the module dashboard.

Pagination: Control how many items are displayed per page.

📂 Technical Details
Database: Utilizes the WHMCS Capsule (Eloquent) manager for secure, optimized queries.

API integration: Uses localAPI for SendEmail and ModuleTerminate functions to ensure standard WHMCS logging and hook execution.

Frontend: Built with vanilla JavaScript and Chart.js for zero-dependency performance.

🤝 Contribution & Support
Developed by Hostinoz.com.

If you encounter bugs or have feature requests, please open an issue in this repository.

Note: This module requires WHMCS v8.0 or higher and PHP 7.4/8.x for optimal performance.

📄 License
This project is licensed under the MIT License - feel free to use and modify it for your own WHMCS installations.
