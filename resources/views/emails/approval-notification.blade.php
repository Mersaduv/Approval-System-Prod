<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Approval Required - Request #{{ $request->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .request-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .action-buttons {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            color: white;
        }
        .btn-approve {
            background-color: #28a745;
        }
        .btn-reject {
            background-color: #dc3545;
        }
        .btn-forward {
            background-color: #007bff;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .footer {
            font-size: 12px;
            color: #6c757d;
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Approval Required</h1>
        <p>Hello {{ $approver->full_name }},</p>
        <p>You have a pending approval request that requires your attention.</p>
    </div>

    <div class="content">
        <h2>Request Details</h2>
        <div class="request-details">
            <p><strong>Request ID:</strong> #{{ $request->id }}</p>
            <p><strong>Employee:</strong> {{ $request->employee->full_name }}</p>
            <p><strong>Department:</strong> {{ $request->employee->department->name }}</p>
            <p><strong>Item:</strong> {{ $request->item }}</p>
            <p><strong>Description:</strong> {{ $request->description }}</p>
            <p><strong>Amount:</strong> {{ number_format($request->amount, 2) }} AFN</p>
            <p><strong>Status:</strong> {{ $request->status }}</p>
            <p><strong>Submitted:</strong> {{ $request->created_at->format('M d, Y H:i') }}</p>
        </div>

        <div class="warning">
            <strong>⚠️ Important:</strong> This approval link will expire on {{ $expiresAt }} and can only be used once.
        </div>

        <div class="action-buttons">
            <a href="{{ $approvalUrl }}?action=approve" class="btn btn-approve">✓ Approve Request</a>
            <a href="{{ $approvalUrl }}?action=reject" class="btn btn-reject">✗ Reject Request</a>
            <a href="{{ $approvalUrl }}?action=forward" class="btn btn-forward">↗ Forward Request</a>
        </div>

        <p style="text-align: center; margin-top: 20px;">
            <a href="{{ $approvalUrl }}" style="color: #007bff; text-decoration: none;">
                Or click here to view the full approval portal
            </a>
        </p>
    </div>

    <div class="footer">
        <p>This is an automated message from the Approval Workflow System.</p>
        <p>If you did not expect this email, please contact your system administrator.</p>
        <p>Token expires: {{ $expiresAt }}</p>
    </div>
</body>
</html>
