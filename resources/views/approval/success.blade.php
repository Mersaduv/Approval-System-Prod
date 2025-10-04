<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Action Completed - Request #{{ $request->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <!-- Success Icon -->
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <!-- Success Message -->
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Action Completed Successfully</h1>
            <p class="text-gray-600 mb-6">Your action has been processed for Request #{{ $request->id }}</p>

            <!-- Request Summary -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                <h3 class="font-semibold text-gray-900 mb-2">Request Summary</h3>
                <div class="space-y-1 text-sm text-gray-600">
                    <p><strong>Item:</strong> {{ $request->item }}</p>
                    <p><strong>Amount:</strong> {{ number_format($request->amount, 2) }} AFN</p>
                    <p><strong>Employee:</strong> {{ $request->employee->full_name }}</p>
                    <p><strong>Department:</strong> {{ $request->employee->department->name }}</p>
                    <p><strong>Status:</strong>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($request->status === 'Pending') bg-yellow-100 text-yellow-800
                            @elseif($request->status === 'Approved') bg-green-100 text-green-800
                            @elseif($request->status === 'Rejected') bg-red-100 text-red-800
                            @else bg-gray-100 text-gray-800 @endif">
                            {{ $request->status }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="text-sm text-gray-600 mb-6">
                <p>The request will now continue through the approval workflow.</p>
                <p>All relevant parties have been notified of your action.</p>
            </div>

            <!-- Close Button -->
            <button onclick="window.close()"
                class="bg-primary hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                Close Window
            </button>
        </div>
    </div>
</body>
</html>
