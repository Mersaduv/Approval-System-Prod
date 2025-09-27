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
        .workflow-progress {
            background-color: #e3f2fd;
            border: 1px solid #bbdefb;
            color: #1565c0;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .progress-bar {
            background-color: #e0e0e0;
            border-radius: 10px;
            height: 20px;
            margin: 10px 0;
            overflow: hidden;
        }
        .progress-fill {
            background-color: #4caf50;
            height: 100%;
            transition: width 0.3s ease;
        }
        .step-list {
            margin: 15px 0;
        }
        .step-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            border-left: 4px solid #ddd;
        }
        .step-completed {
            background-color: #d4edda;
            border-left-color: #28a745;
        }
        .step-current {
            background-color: #fff3cd;
            border-left-color: #ffc107;
        }
        .step-pending {
            background-color: #f8f9fa;
            border-left-color: #6c757d;
        }
        .performer-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Request {{ ucfirst($status) }}</h1>
        <p>Hello {{ $employee ? $employee->full_name : 'User' }},</p>
        <p>{{ $statusMessage }}</p>
    </div>

    <div class="content">
        <h2>Request Details</h2>
        <div class="request-details">
            <p><strong>Request ID:</strong> #{{ $request->id }}</p>
            <p><strong>Item:</strong> {{ $request->item }}</p>
            <p><strong>Description:</strong> {{ $request->description }}</p>
            <p><strong>Amount:</strong> {{ number_format($request->amount ?? 0, 2) }} AFN</p>
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

        @if($status === 'workflow_update')
            <div class="workflow-progress">
                <h3>Workflow Progress</h3>

                @if($workflowInfo)
                    @if($workflowInfo['workflow_progress'])
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: {{ $workflowInfo['workflow_progress']['percentage'] ?? 0 }}%"></div>
                        </div>
                        <p><strong>Progress:</strong> {{ $workflowInfo['workflow_progress']['completed'] }} of {{ $workflowInfo['workflow_progress']['total'] }} steps completed ({{ $workflowInfo['workflow_progress']['percentage'] }}%)</p>
                    @endif

                    @if($workflowInfo['performer'])
                    <div class="performer-info">
                        <h4>Action Performed By:</h4>
                        @if($workflowInfo['notes'])
                            <p><strong>Notes:</strong> {{ $workflowInfo['notes'] }}</p>
                        @endif
                    </div>
                @endif

                @if($workflowInfo['completed_steps'] && count($workflowInfo['completed_steps']) > 0)
                    <h4>Completed Steps:</h4>
                    <div class="step-list">
                        @foreach($workflowInfo['completed_steps'] as $step)
                            <div class="step-item step-completed">
                                <strong>✓ {{ $step['name'] }}</strong>
                                @if($step['description'])
                                    <br><small>{{ $step['description'] }}</small>
                                @endif
                                <br><small>Completed: {{ $step['completed_at'] }}</small>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($workflowInfo['pending_steps'] && count($workflowInfo['pending_steps']) > 0)
                    <h4>Remaining Steps:</h4>
                    <div class="step-list">
                        @foreach($workflowInfo['pending_steps'] as $step)
                            <div class="step-item {{ $step['is_current'] ? 'step-current' : 'step-pending' }}">
                                <strong>{{ $step['is_current'] ? '→ ' : '○ ' }}{{ $step['name'] }}</strong>
                                @if($step['description'])
                                    <br><small>{{ $step['description'] }}</small>
                                @endif
                                @if($step['is_current'])
                                    <br><small><em>Currently in progress</em></small>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($workflowInfo['next_step'])
                    <div style="background-color: #e8f5e8; padding: 15px; border-radius: 5px; margin: 15px 0;">
                        <h4>Next Step:</h4>
                        <p><strong>{{ $workflowInfo['next_step']['name'] }}</strong></p>
                        @if($workflowInfo['next_step']['description'])
                            <p>{{ $workflowInfo['next_step']['description'] }}</p>
                        @endif
                    </div>
                @endif
                    @else
                        <p>Workflow information is not available at this time.</p>
                    @endif
            </div>
        @endif

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
