<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Approval Required</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 30px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background-color: #007bff;
            color: white !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #0056b3;
            color: white !important;
        }
        .btn:visited {
            color: white !important;
        }
        .btn:active {
            color: white !important;
        }
    </style>
</head>
<body>
    <div class="content">
        <h1>Approval Required</h1>
        <p>Hello {{ $approver->full_name }},</p>
        <p>You have a pending approval request that requires your attention.</p>

        <a href="{{ $approvalUrl }}" class="btn" style="background-color: #007bff; color: white !important; text-decoration: none; padding: 15px 30px; border-radius: 5px; font-weight: bold; display: inline-block; margin: 20px 0;">Access Approval Portal</a>
    </div>
</body>
</html>
