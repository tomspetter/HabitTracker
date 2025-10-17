# Habit Tracker 2025

A beautiful, minimalist habit tracker with a year-at-a-glance view. Track up to 6 daily habits with color-coded visualization inspired by GitHub's contribution graph.

> **Note:** This is a work in progress and currently a proof of concept. Feel free to use and modify as needed!

## Features

- **Year-at-a-Glance View**: GitHub-style contribution graph showing your entire year of habits
- **6 Color-Coded Habits**: Track up to 6 different habits with rainbow colors (red, orange, yellow, green, blue, purple)
- **Simple Password Protection**: Basic login screen to keep your data private
- **Data Import/Export**: CSV export and import for backing up your habit data
- **Local Storage**: All data saved in your browser's localStorage
- **Clean Dark Theme**: GitHub-inspired dark interface for comfortable viewing
- **Quick Daily Tracking**: Check off today's habits with one click

## Getting Started

1. Download or clone this repository
2. Open `index.html` in your web browser
3. Default password is `mypassword123` (change this in the code at line 70)
4. Start tracking your habits!

## Usage

- **Add a Habit**: Click "Add Habit" button (max 6 habits)
- **Rename a Habit**: Click on the habit name to edit it
- **Mark Today**: Use the checkbox next to each habit name
- **Mark Any Day**: Click on any day cell in the calendar grid
- **Remove a Habit**: Click "Remove Habit" button, then click the trash icon
- **Export Data**: Click the download icon to save your data as CSV
- **Import Data**: Click the upload icon to restore from a CSV backup

## Planned Improvements

This is a proof of concept, and several features are planned for future releases:

- **Security**: Implement proper password hashing (currently stored in plain text)
- **Statistics**: Add streak counters, completion percentages, and analytics
- **Mobile Responsiveness**: Optimize layout for smaller screens
- **Accessibility**: Add ARIA labels and improved keyboard navigation
- **Data Backup**: Cloud sync options and automatic backups

## Technology Stack

- React 18 (via CDN)
- Tailwind CSS (via CDN)
- PapaParse (for CSV handling)
- Pure JavaScript (ES6+)

## License

MIT License - Free to use, modify, and distribute.

## Contributing

This is a personal project and proof of concept. Feel free to fork and modify for your own needs!

## Security Note

⚠️ **Important**: The current password implementation is NOT secure. The password is stored in plain text in the code. This is only suitable for personal, local use. Do not use this for sensitive data or deploy publicly without implementing proper authentication.
