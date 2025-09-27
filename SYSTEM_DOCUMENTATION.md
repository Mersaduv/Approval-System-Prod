# Approval Workflow Management System

A comprehensive Laravel-based approval workflow management system with modern React frontend, designed for managing approval processes in organizations.

## 🚀 Features

### Core Functionality
- **Request Management**: Submit, track, and manage approval requests
- **Dynamic Approval Rules**: Configurable approval workflows based on amount, department, and role
- **Secure Token-based Approvals**: JWT-like tokens for secure approval links
- **Email Notifications**: Automated email notifications for all stakeholders
- **Real-time Notifications**: In-app notification system
- **Comprehensive Reporting**: Detailed analytics and reporting dashboard
- **Audit Trail**: Complete audit logging for compliance and tracking

### User Roles
- **Employee**: Submit requests, view own requests
- **Manager**: Approve department requests, manage team
- **Sales Manager**: Approve purchase-related requests
- **CEO**: Approve high-value requests (>5,000 AFN)
- **Procurement**: Process approved requests, manage deliveries
- **Admin**: Full system access and management

### Approval Workflow
1. **Employee submits request** with item details and amount
2. **System determines approval path** based on dynamic rules
3. **Managers receive email notifications** with secure approval links
4. **Approvers review and act** via secure approval portal
5. **System processes workflow** and notifies all stakeholders
6. **Procurement handles fulfillment** and delivery tracking

## 🛠️ Technical Stack

### Backend
- **Laravel 10**: PHP framework
- **MySQL**: Database
- **Laravel Sanctum**: API authentication
- **Laravel Mail**: Email notifications
- **Laravel Inertia**: SPA integration

### Frontend
- **React 18**: UI framework
- **Inertia.js**: SPA routing
- **Tailwind CSS**: Styling
- **Axios**: HTTP client

### Key Services
- **WorkflowService**: Core workflow logic
- **NotificationService**: Notification management
- **ReportingService**: Analytics and reporting
- **ApprovalPortalController**: Secure approval handling

## 📁 Project Structure

```
app/
├── Http/Controllers/
│   ├── Api/                    # API controllers
│   ├── ApprovalPortalController.php
├── Mail/                       # Email templates
├── Models/                     # Eloquent models
├── Services/                   # Business logic services
└── ...

resources/
├── js/
│   ├── Layouts/               # React layouts
│   └── Pages/                 # React pages
└── views/
    └── emails/                # Email templates

database/
├── migrations/                # Database migrations
└── seeders/                  # Database seeders
```

## 🚀 Installation & Setup

### Prerequisites
- PHP 8.1+
- Composer
- Node.js 16+
- MySQL 8.0+
- Laravel Valet or similar development environment

### Installation Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd approval-workflow-system
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Database configuration**
   Update `.env` with your database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=approval_sys
   DB_USERNAME=root
   DB_PASSWORD=
   ```

6. **Run migrations and seeders**
   ```bash
   php artisan migrate
   php artisan db:seed --class=ExampleDataSeeder
   ```

7. **Build frontend assets**
   ```bash
   npm run build
   # or for development:
   npm run dev
   ```

8. **Start the development server**
   ```bash
   php artisan serve
   ```

## 📊 Database Schema

### Core Tables
- **users**: User accounts and roles
- **departments**: Organizational departments
- **requests**: Approval requests
- **approval_rules**: Dynamic approval rules
- **approval_tokens**: Secure approval tokens
- **notifications**: In-app notifications
- **audit_logs**: System audit trail
- **procurements**: Procurement tracking

### Key Relationships
- Users belong to Departments
- Requests belong to Users (employees)
- Approval Rules belong to Departments
- Audit Logs track all system actions
- Notifications link to Requests and Users

## 🔧 Configuration

### Approval Rules
Configure approval workflows in the `approval_rules` table:
- **Department-based rules**: Different rules per department
- **Amount thresholds**: Automatic routing based on request amount
- **Role-based approvers**: Manager, Sales Manager, CEO, etc.
- **Order priority**: Sequential approval steps

### Email Configuration
Update mail settings in `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

### Notification Settings
- **Email notifications**: Automatic for all approval actions
- **In-app notifications**: Real-time updates
- **Token expiration**: 48 hours default
- **Usage limits**: Single-use tokens by default

## 🎯 Usage Examples

### Submitting a Request
1. Navigate to "New Request"
2. Fill in item details, description, and amount
3. Select department and priority
4. Submit request
5. Receive confirmation and tracking information

### Approving a Request
1. Receive email notification with secure link
2. Click approval link (expires in 48 hours)
3. Review request details in approval portal
4. Choose action: Approve, Reject, or Forward
5. Add notes if needed
6. Submit decision

   - System activity logs

## 🔒 Security Features

### Token Security
- **Cryptographically secure tokens**: 64-character random tokens
- **Expiration handling**: Automatic token expiration
- **Usage limits**: Single-use or limited-use tokens
- **Role validation**: Tokens tied to specific user roles

### Data Protection
- **Audit logging**: Complete action tracking
- **IP address logging**: Security monitoring
- **User agent tracking**: Device identification
- **Input validation**: Comprehensive data validation

### Access Control
- **Role-based permissions**: Granular access control
- **Department isolation**: Users only see relevant data
- **API authentication**: Laravel Sanctum integration
- **CSRF protection**: Built-in Laravel protection

## 📈 Reporting & Analytics

### Dashboard Metrics
- Total requests and amounts
- Approval rates and processing times
- Department-wise statistics
- User activity reports
- System performance metrics

### Export Capabilities
- **CSV exports**: Request and audit data
- **Date range filtering**: Flexible reporting periods
- **Custom reports**: Department and user-specific reports
- **Real-time updates**: Live dashboard metrics

## 🧪 Testing & Demo

### Example Data
The system includes comprehensive example data:
- 8 departments with realistic structure
- 10 users across different roles
- Sample requests in various states
- Complete audit trail examples
- Notification history

### Workflow Demo
Interactive demonstration showing:
- Complete approval workflow
- Real-time step progression
- Notification simulation
- Audit log generation
- Status updates

## 🚀 Deployment

### Production Considerations
1. **Environment variables**: Secure configuration
2. **Database optimization**: Indexes and queries
3. **Email service**: Reliable SMTP provider
4. **File storage**: Secure file handling
5. **Backup strategy**: Regular database backups
6. **Monitoring**: Application and server monitoring

### Performance Optimization
- **Database indexing**: Optimized queries
- **Caching**: Laravel caching strategies
- **Asset optimization**: Minified frontend assets
- **Queue processing**: Background job processing

## 🤝 Contributing

### Development Guidelines
1. Follow PSR-12 coding standards
2. Write comprehensive tests
3. Document new features
4. Update database migrations
5. Maintain backward compatibility

### Code Structure
- **Services**: Business logic separation
- **Controllers**: Thin controllers, fat services
- **Models**: Eloquent relationships
- **Frontend**: Component-based architecture

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

For support and questions:
- Check the documentation
- Review the example data
- Test with the workflow demo
- Contact the development team

## 🔄 Version History

### v1.0.0 (Current)
- Complete approval workflow system
- Modern React frontend
- Comprehensive reporting
- Security features
- Example data and demos

---

**Built with ❤️ using Laravel and React**


php artisan config:clear ; php artisan cache:clear ; php artisan route:clear
