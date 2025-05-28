# PHP Timetable Management System

A PHP-based system for managing university course timetables. The application allows administrators to create and publish timetables, while professors and students can view their respective schedules.

## Project Structure

The project has the following directory layout:

```
/src
  /admin          - Admin dashboard and management interface
  /api            - API endpoints for AJAX requests
  /assets         - Static files (CSS, JS, images)
    /css          - CSS stylesheets
    /js           - JavaScript files
    /images       - Image files (including logos and icons)
  /includes       - Shared includes (e.g., db.php for database connection)
  /views          - User interface files for different roles
  index.php       - Main entry point (redirects to login)
```

## Directory Purposes

- **admin**: Contains the administrative dashboard with timetable management features
- **api**: Endpoints for AJAX requests (e.g., save_timetable.php, get_timetable.php, publish_timetable.php)
- **assets**: Static files like CSS, JavaScript, and images including the SupNum logo
- **includes**: Shared files like the database connection (db.php)
- **views**: User interface files for different user roles including:
  - login.php - Authentication system
  - admin_timetable.php - Main timetable management interface
  - timetable_view.php - Timetable display for students and professors
  - professor.php - Professor selection interface for admins
  - notifications.php - System for managing class cancellations and reschedules

## Getting Started

1. Set up a PHP environment with MySQL support
2. Import the database schema
3. Configure the database connection in `src/includes/db.php`
4. Access the application through your web server

## User Roles

- **Administrators**: Can create, edit, and publish timetables
- **Professors**: Can view their teaching schedule
- **Students**: Can view the timetable for their assigned group

## Database Structure

The database includes these main tables:

- `users`: User accounts with email, password (hashed), and role (admin, professor, student)
- `years`: Academic years
- `groups`: Student groups within each year
- `subjects`: Academic subjects
- `timetables`: Timetable entries with fields for:
  - year_id and group_id
  - day and time_slot
  - subject_id and professor_id
  - room
  - is_published status
  - is_canceled and is_reschedule flags for class status

## Features

- Interactive timetable interface with drag-and-drop functionality
- Year and group selection to manage multiple timetables
- Add, edit, and delete classes in real-time
- Save and publish timetables
- Class cancellation and rescheduling system
- Role-based access control (admin, professor, student)
- Responsive design that works on desktop and mobile devices
- Authentication system with password hashing

## Setup Instructions

1. Ensure you have PHP installed on your server (version 7.0 or higher recommended)
2. Upload all files to your web server
3. Create a MySQL database named 'PI'
4. Configure the database connection in `src/includes/db.php`
5. Access the system via your web browser

## File Structure

- `src/views/login.php` - Authentication system
- `src/views/admin_timetable.php` - The main timetable interface for administrators
- `src/views/timetable_view.php` - The timetable view for students and professors
- `src/views/professor.php` - Professor selection interface for admins
- `src/api/save_timetable.php` - Handles saving timetable data to the database
- `src/api/publish_timetable.php` - Handles publishing timetable data
- `src/api/get_timetable.php` - Retrieves timetable data for a specific year/group

## Usage

1. Log in with your credentials (email and password)
2. For administrators:
   - Select a year and group to view/edit the corresponding timetable
   - Click the "+" button in any cell to add a new class
   - Fill in the class details (subject, professor, room)
   - Use the "Save Timetable" button to persist changes
   - Use the "Publish Timetable" button to make the timetable publicly available
3. For professors:
   - View your teaching schedule automatically
4. For students:
   - View the timetable for your assigned group automatically

## Data Storage

Timetable data is stored in the MySQL database in the `timetables` table with fields for year_id, group_id, day, time_slot, subject_id, professor_id, room, and flags for published status, cancellations, and reschedules.

## Browser Compatibility

This system works on all modern browsers including:

- Chrome
- Firefox
- Safari
- Edge

## Customization

You can easily customize the system by:

- Modifying the database content to change available years, groups, subjects, professors, or rooms
- Editing the CSS to change the appearance
- Adding additional fields to the class form as needed
