# Table Tennis Tournament System

A web-based system for managing table tennis tournaments, including player registration, match scheduling, bracket generation, and reporting.

## Features
- Player registration and management
- Tournament bracket generation
- Match scheduling and results
- User authentication (with Google OAuth)
- Admin and organizer panels
- Reports and statistics

## Getting Started

### Prerequisites
- PHP 7.x or higher
- MySQL
- Web server (e.g., Apache, XAMPP)

### Installation
1. Clone the repository:
   ```sh
   git clone https://github.com/Kenttyt/table-tennis-tournament-system.git
   ```
2. Copy `config/google.local.php.example` to `config/google.local.php` and fill in your Google OAuth credentials.
3. Import the database from the `sql/` directory.
4. Configure your web server to serve the project directory.

### Usage
- Access the system via your browser (e.g., http://localhost/table-tennis-system/)
- Register as a player or log in as an admin/organizer
- Manage tournaments, matches, and players



## License
MIT
