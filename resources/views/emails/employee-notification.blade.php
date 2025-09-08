<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ucfirst($status) }} - Request #{{ $request->id }}</title>
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
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-delivered {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .footer {
            font-size: 12px;
            color: #6c757d;
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .action-required {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .reason-box {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Request {{ ucfirst($status) }}</h1>
        <p>Hello {{ $employee->full_name }},</p>
        <p>{{ $statusMessage }}</p>
    </div>

    <div class="content">
        <h2>Request Details</h2>
        <div class="request-details">
            <p><strong>Request ID:</strong> #{{ $request->id }}</p>
            <p><strong>Item:</strong> {{ $request->item }}</p>
            <p><strong>Description:</strong> {{ $request->description }}</p>
            <p><strong>Amount:</strong> {{ number_format($request->amount, 2) }} AFN</p>
            <p><strong>Status:</strong>
                <span class="status-badge status-{{ $status }}">
                    {{ ucfirst($status) }}
                </span>
            </p>
            <p><strong>Submitted:</strong> {{ $request->created_at->format('M d, Y H:i') }}</p>
            @if($request->updated_at != $request->created_at)
                <p><strong>Last Updated:</strong> {{ $request->updated_at->format('M d, Y H:i') }}</p>
            @endif
        </div>

        @if($reason && $status === 'rejected')
            <div class="reason-box">
                <h3>Rejection Reason:</h3>
                <p>{{ $reason }}</p>
            </div>
        @endif

        <div class="action-required">
            <h3>Next Steps:</h3>
            <p>{{ $actionRequired }}</p>
        </div>

        @if($status === 'delivered')
            <div style="text-align: center; margin: 30px 0;">
                <p style="font-size: 16px; color: #28a745;">
                    <strong>✓ Your request has been successfully delivered!</strong>
                </p>
            </div>
        @endif
    </div>

    <div class="footer">
        <p>This is an automated message from the Approval Workflow System.</p>
        <p>If you have any questions, please contact your department manager or the system administrator.</p>
        <p>Request ID: #{{ $request->id }} | {{ now()->format('M d, Y H:i') }}</p>
    </div>
</body>
</html>
