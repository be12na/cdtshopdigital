# Cepat Shop cPanel Deployment Guide

## 1. Directory Structure

For security reasons, Laravel application files should not be publicly accessible in the `public_html` directory.

Follow this structure on your cPanel:
```text
/home/yourusername/
├── public_html/          <-- The content of your project's `public/` folder goes here
└── cepatshop_app/        <-- ALL other project folders/files go here (app, config, routes, vendor, storage, .env, etc.)
```

## 2. Deploying Files
1. Zip the entire application on your computer.
2. Upload the zip file using cPanel File Manager to `~/cepatshop_app/` and extract it.
3. Move ONLY the contents of the `public/` directory from `~/cepatshop_app/public/*` into `~/public_html/`.

## 3. Path Configuration
Use the provided `cpanel_index.php` template.
1. Rename `cpanel_index.php` (now in your `public_html`) to `index.php`, replacing the original one.
2. Open `public_html/index.php` and verify `$appPath` points to your app folder name (`../cepatshop_app`).

## 4. Database Setup
1. In cPanel, go to "MySQL Databases" and create a database and a user.
2. Add the user to the database with **All Privileges**.
3. Edit the `.env` file in `~/cepatshop_app/` and update the connection details. Usually `DB_HOST` remains `127.0.0.1` or `localhost`.
   ```env
   DB_DATABASE=your_cpanel_db_name
   DB_USERNAME=your_cpanel_db_user
   DB_PASSWORD=your_cpanel_password
   ```

## 5. File Permissions
If you have Terminal access in cPanel:
1. Open Terminal.
2. Navigate to your app directory: `cd ~/cepatshop_app`
3. Run the included script: `bash cpanel_permissions.sh`

Alternatively, using File Manager, ensure:
- `storage/` and all its subfolders/files are `775`
- `bootstrap/cache/` is `775`
- Other files are `644` and folders `755`

## 6. PHP Version
Ensure your domain in **cPanel -> MultiPHP Manager** is configured to use PHP **8.1, 8.2, or 8.3**.
