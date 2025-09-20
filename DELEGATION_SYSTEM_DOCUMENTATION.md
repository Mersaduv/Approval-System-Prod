# Delegation System Documentation

## Overview

The Delegation System is a comprehensive feature that allows users to delegate their approval responsibilities to other users in the Approval Workflow System. This system ensures business continuity when approvers are unavailable while maintaining proper audit trails and security.

## Features

### Core Functionality
- **Delegation Management**: Create, update, and delete delegations
- **Time-based Delegations**: Set start and end dates for delegations
- **Department-specific Delegations**: Limit delegations to specific departments
- **Workflow Step Delegations**: Delegate specific workflow steps or all steps
- **Delegation Types**: Support for approval, notification, and all types
- **Audit Trail**: Complete logging of delegation activities
- **Permission Control**: Control whether delegates can further delegate

### User Interface
- **Delegation Management Page**: Complete CRUD interface for delegations
- **Navigation Integration**: Accessible from main navigation for managers and admins
- **Request View Integration**: Shows delegation information when acting on behalf of others
- **Statistics Dashboard**: Overview of delegation metrics

## Database Schema

### Delegations Table
```sql
CREATE TABLE delegations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delegator_id BIGINT UNSIGNED NOT NULL,
    delegate_id BIGINT UNSIGNED NOT NULL,
    workflow_step_id BIGINT UNSIGNED NULL,
    department_id BIGINT UNSIGNED NULL,
    delegation_type VARCHAR(255) NOT NULL DEFAULT 'approval',
    reason TEXT NULL,
    starts_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    can_delegate_further TINYINT(1) NOT NULL DEFAULT 0,
    permissions JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (delegator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (delegate_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (workflow_step_id) REFERENCES workflow_steps(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);
```

## API Endpoints

### Delegation Management
- `GET /api/delegations` - List all delegations for user
- `GET /api/delegations/my` - List delegations created by user
- `GET /api/delegations/received` - List delegations received by user
- `POST /api/delegations` - Create new delegation
- `PUT /api/delegations/{id}` - Update delegation
- `DELETE /api/delegations/{id}` - Delete delegation

### Supporting Endpoints
- `GET /api/delegations/available-users` - Get users available for delegation
- `GET /api/delegations/workflow-steps` - Get workflow steps for delegation
- `GET /api/delegations/departments` - Get departments for delegation
- `GET /api/delegations/stats` - Get delegation statistics

## Usage Examples

### Creating a Delegation
```javascript
const delegationData = {
    delegate_id: 5,
    workflow_step_id: 2,
    department_id: 3,
    delegation_type: 'approval',
    reason: 'Manager is on vacation',
    starts_at: '2024-01-01',
    expires_at: '2024-01-15',
    can_delegate_further: false
};

const response = await axios.post('/api/delegations', delegationData);
```

### Checking Effective Approvers
```php
// In WorkflowService
$effectiveApprovers = $this->getEffectiveApprovers($request, $step);
```

## Integration Points

### Workflow Service Integration
The delegation system is integrated into the `WorkflowService` through the `getEffectiveApprovers()` method, which:
1. Gets original approvers from workflow step assignments
2. Checks for active delegations for each approver
3. Replaces original approvers with their delegates when applicable
4. Logs delegation usage in audit trail

### Request Controller Integration
The `RequestController` includes delegation information in request details through the `getDelegationInfo()` method, which:
1. Checks if the current user is acting on behalf of someone
2. Returns delegation details if applicable
3. Displays delegation information in the UI

## Security Features

### Access Control
- Only managers and admins can create delegations
- Users can only manage their own delegations
- Delegation permissions are validated before creation

### Validation
- Prevents self-delegation
- Validates delegation periods (start < end)
- Checks for conflicting delegations
- Validates user roles and permissions

### Audit Trail
- All delegation activities are logged
- Includes IP address and user agent
- Tracks delegation usage in request processing

## User Interface Components

### DelegationManagement Component
- **Location**: `resources/js/Components/DelegationManagement.jsx`
- **Features**:
  - Tabbed interface (My Delegations, Received Delegations, All Delegations)
  - Filter by status (Active, Expired, Inactive)
  - Create/Edit/Delete delegations
  - Statistics dashboard
  - Responsive design

### RequestView Integration
- **Location**: `resources/js/Pages/RequestView.jsx`
- **Features**:
  - Shows delegation information when acting on behalf of others
  - Displays original approver, reason, and expiration
  - Visual indicators for delegated actions

## Configuration

### Navigation
Delegation management is accessible from the main navigation for:
- **Managers**: Can manage their own delegations
- **Admins**: Can manage all delegations

### Permissions
- **Create Delegations**: Managers and Admins
- **View Delegations**: All users can view their own
- **Manage Delegations**: Only the creator can modify/delete

## Testing

The system includes comprehensive testing through:
- Unit tests for model methods
- Integration tests for API endpoints
- UI component testing
- End-to-end workflow testing

## Best Practices

### Delegation Management
1. **Set Clear Expiration Dates**: Always set end dates for delegations
2. **Provide Clear Reasons**: Document why delegation is needed
3. **Regular Review**: Periodically review active delegations
4. **Minimal Scope**: Delegate only what's necessary

### Security
1. **Trusted Delegates**: Only delegate to trusted users
2. **Time Limits**: Use short-term delegations when possible
3. **Monitor Usage**: Regularly check delegation usage logs
4. **Revoke When Done**: Deactivate delegations when no longer needed

## Troubleshooting

### Common Issues
1. **Delegation Not Working**: Check if delegation is active and within date range
2. **User Not Available**: Ensure user has appropriate role (manager/admin)
3. **Permission Denied**: Verify user has delegation permissions
4. **UI Not Loading**: Check browser console for JavaScript errors

### Debug Information
- Check audit logs for delegation activities
- Verify database constraints and foreign keys
- Test API endpoints directly
- Check user roles and permissions

## Future Enhancements

### Planned Features
1. **Bulk Delegation**: Delegate multiple workflow steps at once
2. **Templates**: Pre-defined delegation templates
3. **Notifications**: Email notifications for delegation events
4. **Approval Chains**: Multi-level delegation chains
5. **Reporting**: Advanced delegation analytics

### Integration Opportunities
1. **Calendar Integration**: Sync with vacation calendars
2. **Mobile App**: Mobile delegation management
3. **Workflow Designer**: Visual delegation configuration
4. **API Webhooks**: External system integration

## Support

For technical support or questions about the delegation system:
1. Check this documentation
2. Review audit logs
3. Test with sample data
4. Contact the development team

---

**Last Updated**: September 20, 2025
**Version**: 1.0.0
**Status**: Production Ready
