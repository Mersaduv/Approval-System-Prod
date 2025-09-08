# Authentication System Guide

## Overview
The Approval Workflow System now includes a complete authentication system with login/logout functionality.

## Features Implemented

### 1. Login Page
- **URL**: `/login`
- **Features**:
  - Email and password authentication
  - Remember me functionality
  - Password visibility toggle
  - English language interface
  - Form validation
  - Loading states

### 2. Logout Functionality
- **URL**: `/logout` (POST)
- **Features**:
  - Session invalidation
  - CSRF token regeneration
  - Redirect to login page
  - Available in both sidebar and header dropdown

### 3. User Management
- Admin users can create new users through the API
- Users are created with proper roles and permissions
- Password hashing is handled automatically

## Default Users Created

The system creates three default users for testing:

### Admin User
- **Email**: admin@company.com
- **Password**: password123
- **Name**: System Administrator
- **Role**: Admin
- **Permissions**: All permissions (*)

### Employee User
- **Email**: employee@company.com
- **Password**: password123
- **Name**: Test Employee
- **Role**: Employee
- **Permissions**: submit_requests, view_all_requests

### Manager User
- **Email**: manager@company.com
- **Password**: password123
- **Name**: Department Manager
- **Role**: Manager
- **Permissions**: submit_requests, approve_requests, view_all_requests, manage_team

## Database Configuration

The system is configured to use MySQL with the following settings:
- **Host**: 127.0.0.1
- **Port**: 3306
- **Database**: approval_sys
- **Username**: root
- **Password**: (empty)

## How to Test

1. **Start the server**:
   ```bash
   php artisan serve
   ```

2. **Access the login page**:
   - Go to `http://localhost:8000/login`
   - You'll be redirected here if not authenticated

3. **Login with admin credentials**:
   - Email: admin@company.com
   - Password: password123

4. **Test logout**:
   - Click the logout button in the sidebar or header dropdown
   - You'll be redirected to the login page

5. **Test user creation**:
   - Login as admin
   - Go to Users page
   - Create new users through the interface

## Security Features

- **Password Hashing**: All passwords are hashed using Laravel's Hash facade
- **CSRF Protection**: All forms include CSRF tokens
- **Session Management**: Proper session handling with regeneration
- **Input Validation**: All inputs are validated on both client and server side
- **Role-based Access**: Different users have different permissions

## API Endpoints

### Authentication
- `GET /login` - Show login form
- `POST /login` - Process login
- `POST /logout` - Process logout
- `GET /user` - Get authenticated user data

### User Management (Admin only)
- `GET /api/admin/users` - List users
- `POST /api/admin/users` - Create user
- `GET /api/admin/users/{id}` - Show user
- `PUT /api/admin/users/{id}` - Update user
- `DELETE /api/admin/users/{id}` - Delete user

## Troubleshooting

### Common Issues

1. **"Route not found" errors**:
   - Make sure to run `php artisan route:clear`
   - Check that routes are properly defined

2. **Database connection errors**:
   - Verify MySQL is running
   - Check database credentials in `.env`
   - Run `php artisan migrate` to create tables

3. **Login not working**:
   - Check if users exist in database
   - Verify password is correct
   - Check Laravel logs for errors

4. **Assets not loading**:
   - Run `npm run build` to compile assets
   - Check if public/build directory exists

### Logs
Check the Laravel logs at `storage/logs/laravel.log` for any errors.

## Next Steps

1. **Email Verification**: Add email verification for new users
2. **Password Reset**: Implement password reset functionality
3. **Two-Factor Authentication**: Add 2FA for enhanced security
4. **Audit Logging**: Track all authentication events
5. **Session Management**: Add session timeout and concurrent session limits
