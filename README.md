# PHP Timetable Management System

A PHP-based system for managing university course timetables. The application allows administrators to create and publish timetables, while professors and students can view their respective schedules.

## Project Structure

The project has been reorganized into a more structured directory layout:

```
/src
  /admin          - Admin-related files
  /api            - API endpoints for AJAX requests
  /assets         - Static files (CSS, JS, images)
    /css          - CSS stylesheets
    /js           - JavaScript files
    /images       - Image files
  /core           - Core system files
  /includes       - Shared includes (e.g., db.php)
  /models         - Data models
  /timetable_data - Stored timetable JSON files
  /utils          - Utility scripts
  /views          - User interface files
  index.php       - Main entry point
```

## Directory Purposes

- **admin**: Contains administrative interfaces and functionality
- **api**: Endpoints for AJAX requests (e.g., save_timetable.php, get_timetable.php)
- **assets**: Static files like CSS, JavaScript, and images
- **core**: Core system files, including database schema
- **includes**: Shared files like the database connection
- **models**: Data models and business logic
- **timetable_data**: JSON files storing timetable data
- **utils**: Utility scripts for maintenance and special operations
- **views**: User interface files for different user roles

## Getting Started

1. Set up a PHP environment with MySQL support
2. Import the database schema from `src/core/timetable_schema.sql`
3. Configure the database connection in `src/includes/db.php`
4. Access the application through your web server

## User Roles

- **Administrators**: Can create, edit, and publish timetables
- **Professors**: Can view their teaching schedule
- **Students**: Can view the timetable for their assigned group

## Database Structure

The database includes these main tables:

- `users`: User accounts and authentication
- `years`: Academic years
- `groups`: Student groups within each year
- `subjects`: Academic subjects
- `professors`: Professor information

## Notes About Reorganization

The application previously had a flat structure with files in the root directory. It has been reorganized into a more maintainable directory structure with proper separation of concerns. An .htaccess file has been added to maintain compatibility with any direct links to old file locations.

## Features

- Interactive timetable interface with drag-and-drop functionality
- Year and group selection to manage multiple timetables
- Add, edit, and delete classes in real-time
- Save and publish timetables
- Responsive design that works on desktop and mobile devices

## Setup Instructions

1. Ensure you have PHP installed on your server (version 7.0 or higher recommended)
2. Upload all files to your web server
3. Make sure the `timetable_data` directory is writable by the web server:
   ```
   chmod 755 timetable_data
   ```
4. Access the system via your web browser: `http://yourserver.com/table.php`

## File Structure

- `table.php` - The main timetable interface
- `save_timetable.php` - Handles saving timetable data
- `publish_timetable.php` - Handles publishing timetable data
- `get_timetable.php` - Retrieves timetable data for a specific year/group
- `timetable_data/` - Directory where timetable data is stored

## Usage

1. Select a year and group to view/edit the corresponding timetable
2. Click the "+" button in any cell to add a new class
3. Fill in the class details (subject, professor, room)
4. Click "Save" to save the class to the timetable
5. Use the "Save Timetable" button to persist changes to the server
6. Use the "Publish Timetable" button to make the timetable publicly available

## Data Storage

Timetable data is stored in JSON format in the `timetable_data` directory:

- Regular saved timetables: `timetable_[YEAR]_[GROUP].json`
- Published timetables: `timetable_[YEAR]_[GROUP]_published.json`

If the server storage fails, data is automatically saved to the browser's localStorage as a fallback.

## Browser Compatibility

This system works on all modern browsers including:

- Chrome
- Firefox
- Safari
- Edge

## Customization

You can easily customize the system by:

- Modifying the PHP arrays in `table.php` to change available years, groups, subjects, professors, or rooms
- Editing the CSS in the `<style>` section to change the appearance
- Adding additional fields to the class form as needed
