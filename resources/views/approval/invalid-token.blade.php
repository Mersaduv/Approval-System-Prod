<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Approval Link</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <!-- Error Icon -->
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>

            <!-- Error Message -->
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Invalid Approval Link</h1>
            <p class="text-gray-600 mb-6">{{ $message }}</p>

            <!-- Help Text -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <svg class="w-5 h-5 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <div class="text-sm text-yellow-800">
                        <strong>Possible reasons:</strong>
                        <ul class="mt-2 list-disc list-inside text-left">
                            <li>The approval link has expired (links expire after 48 hours)</li>
                            <li>The link has already been used</li>
                            <li>The link is invalid or corrupted</li>
                            <li>The request has already been processed</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-2">
                <p class="text-sm text-gray-600 mb-4">What would you like to do?</p>
                <button onclick="window.close()"
                    class="block w-full bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 mb-2">
                    Close Window
                </button>
                <p class="text-xs text-gray-500">
                    If you believe this is an error, please contact your system administrator.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
