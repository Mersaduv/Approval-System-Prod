<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Portal - Request #{{ $request->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        success: '#10b981',
                        danger: '#ef4444',
                        warning: '#f59e0b'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Approval Portal</h1>
                    <p class="text-gray-600">Request #{{ $request->id }} - {{ $request->item }}</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Approver</div>
                    <div class="font-semibold">{{ $approver->full_name }}</div>
                    <div class="text-sm text-gray-500">{{ $approver->role }}</div>
                </div>
            </div>
        </div>

        <!-- Request Details -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Request Details
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900 border-b border-gray-200 pb-2">Basic Information</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Request ID</label>
                        <p class="mt-1 text-sm text-gray-900 font-mono bg-gray-50 px-2 py-1 rounded">#{{ $request->id }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Employee</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $request->employee->full_name }}</p>
                        <p class="text-xs text-gray-500">{{ $request->employee->email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Department</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $request->employee->department->name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Role</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $request->employee->role->name ?? 'N/A' }}</p>
                    </div>
                </div>

                <!-- Request Information -->
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900 border-b border-gray-200 pb-2">Request Information</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Item</label>
                        <p class="mt-1 text-sm text-gray-900 font-medium">{{ $request->item }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Amount</label>
                        <p class="mt-1 text-lg font-bold text-primary">{{ number_format($request->amount, 2) }} AFN</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                            @if($request->status === 'Pending') bg-yellow-100 text-yellow-800
                            @elseif($request->status === 'Approved') bg-green-100 text-green-800
                            @elseif($request->status === 'Rejected') bg-red-100 text-red-800
                            @elseif($request->status === 'Pending Approval') bg-blue-100 text-blue-800
                            @elseif($request->status === 'Pending Procurement') bg-purple-100 text-purple-800
                            @else bg-gray-100 text-gray-800 @endif">
                            {{ $request->status }}
                        </span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Submitted</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $request->created_at->format('M d, Y H:i') }}</p>
                        <p class="text-xs text-gray-500">{{ $request->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ $request->description ?: 'No description provided' }}</p>
                </div>
            </div>

            <!-- Procurement Information (if available) -->
            @if($request->procurement_status)
            <div class="mt-6">
                <h3 class="text-lg font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">Procurement Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Procurement Status</label>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($request->procurement_status === 'Verified') bg-green-100 text-green-800
                            @elseif($request->procurement_status === 'Rejected') bg-red-100 text-red-800
                            @elseif($request->procurement_status === 'Not Available') bg-yellow-100 text-yellow-800
                            @else bg-gray-100 text-gray-800 @endif">
                            {{ $request->procurement_status }}
                        </span>
                    </div>
                    @if($request->final_price)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Final Price</label>
                        <p class="mt-1 text-sm text-gray-900 font-semibold">{{ number_format($request->final_price, 2) }} AFN</p>
                    </div>
                    @endif
                    @if($request->procurement_notes)
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Procurement Notes</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $request->procurement_notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Bill Information (if available) -->
            @if($request->bill_number)
            <div class="mt-6">
                <h3 class="text-lg font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">Bill Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bill Number</label>
                        <p class="mt-1 text-sm text-gray-900 font-mono bg-gray-50 px-2 py-1 rounded">{{ $request->bill_number }}</p>
                    </div>
                    @if($request->bill_amount)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bill Amount</label>
                        <p class="mt-1 text-sm text-gray-900 font-semibold">{{ number_format($request->bill_amount, 2) }} AFN</p>
                    </div>
                    @endif
                    @if($request->bill_printed_at)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bill Printed</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $request->bill_printed_at->format('M d, Y H:i') }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Workflow Progress -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                Workflow Progress
            </h2>
            <div class="flow-root">
                <ul class="-mb-8">
                    <!-- Step 1: Request Submitted -->
                    <li>
                        <div class="relative pb-8">
                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                            <div class="relative flex space-x-3">
                                <div>
                                    <span class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center ring-8 ring-white">
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                    <div>
                                        <p class="text-sm text-gray-500">Request submitted by <span class="font-medium text-gray-900">{{ $request->employee->full_name }}</span></p>
                                        <p class="text-xs text-gray-500">{{ $request->created_at->format('M d, Y H:i') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>

                    <!-- Step 2: Procurement Verification -->
                    <li>
                        <div class="relative pb-8">
                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                            <div class="relative flex space-x-3">
                                <div>
                                    @if($request->procurement_status === 'Verified')
                                        <span class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center ring-8 ring-white">
                                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                        </span>
                                    @elseif($request->procurement_status === 'Rejected' || $request->procurement_status === 'Not Available')
                                        <span class="h-8 w-8 rounded-full bg-red-500 flex items-center justify-center ring-8 ring-white">
                                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                            </svg>
                                        </span>
                                    @else
                                        <span class="h-8 w-8 rounded-full bg-yellow-500 flex items-center justify-center ring-8 ring-white">
                                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                    <div>
                                        <p class="text-sm text-gray-500">Procurement verification</p>
                                        @if($request->procurement_status)
                                            <p class="text-xs text-gray-500">Status: {{ $request->procurement_status }}</p>
                                        @else
                                            <p class="text-xs text-gray-500">Pending verification</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>

                    <!-- Step 3: Approval Required (Current Step) -->
                    <li>
                        <div class="relative">
                            <div class="relative flex space-x-3">
                                <div>
                                    <span class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white">
                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                    <div>
                                        <p class="text-sm text-gray-500">Approval required from <span class="font-medium text-gray-900">{{ $approver->full_name }}</span></p>
                                        <p class="text-xs text-gray-500">Your approval is needed to proceed</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Approval Actions -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Take Action
            </h2>

            <!-- Notes Section -->
            <div class="mb-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                    Notes (Optional)
                    <span class="text-gray-500 text-xs">Add any comments or feedback about this request</span>
                </label>
                <textarea id="notes" name="notes" rows="4"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"
                    placeholder="Add any notes or comments about this request..."></textarea>
            </div>

            <!-- Action Buttons -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button onclick="processAction('approve')"
                    class="bg-success hover:bg-green-700 text-white font-bold py-4 px-6 rounded-lg transition duration-200 flex items-center justify-center shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div class="text-left">
                        <div class="text-lg">Approve Request</div>
                        <div class="text-sm opacity-90">Approve this request</div>
                    </div>
                </button>

                <button onclick="processAction('reject')"
                    class="bg-danger hover:bg-red-700 text-white font-bold py-4 px-6 rounded-lg transition duration-200 flex items-center justify-center shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <div class="text-left">
                        <div class="text-lg">Reject Request</div>
                        <div class="text-sm opacity-90">Reject this request</div>
                    </div>
                </button>

                <button onclick="processAction('forward')"
                    class="bg-warning hover:bg-yellow-600 text-white font-bold py-4 px-6 rounded-lg transition duration-200 flex items-center justify-center shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    <div class="text-left">
                        <div class="text-lg">Forward Request</div>
                        <div class="text-sm opacity-90">Forward to another approver</div>
                    </div>
                </button>
            </div>

            <!-- Security Information -->
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-md">
                <div class="flex">
                    <svg class="w-5 h-5 text-blue-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                    </svg>
                    <div class="text-sm text-blue-800">
                        <strong>Security:</strong> This approval link is secure and expires on {{ $approvalToken->expires_at->format('M d, Y H:i') }}.
                        It can only be used once and is tied to your role as {{ $approver->role }}.
                    </div>
                </div>
            </div>

            <!-- Warning -->
            <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                <div class="flex">
                    <svg class="w-5 h-5 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <div class="text-sm text-yellow-800">
                        <strong>Important:</strong> This action cannot be undone. The request will be processed immediately.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 flex items-center">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Processing...
        </div>
    </div>

    <script>
        function processAction(action) {
            if (!confirm(`Are you sure you want to ${action} this request?`)) {
                return;
            }

            showLoading();

            const formData = new FormData();
            formData.append('action', action);
            formData.append('notes', document.getElementById('notes').value);
            formData.append('_token', '{{ csrf_token() }}');

            fetch('/approval/{{ $token }}/process', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    // Show success message with better UX
                    const successDiv = document.createElement('div');
                    successDiv.className = 'fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50';
                    successDiv.innerHTML = `
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            ${data.message}
                        </div>
                    `;
                    document.body.appendChild(successDiv);
                    setTimeout(() => {
                        successDiv.remove();
                        window.location.href = data.redirect;
                    }, 2000);
                } else {
                    // Show error message with better UX
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50';
                    errorDiv.innerHTML = `
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            Error: ${data.message}
                        </div>
                    `;
                    document.body.appendChild(errorDiv);
                    setTimeout(() => errorDiv.remove(), 5000);
                }
            })
            .catch(error => {
                hideLoading();
                // Show error message with better UX
                const errorDiv = document.createElement('div');
                errorDiv.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50';
                errorDiv.innerHTML = `
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        An error occurred. Please try again.
                    </div>
                `;
                document.body.appendChild(errorDiv);
                setTimeout(() => errorDiv.remove(), 5000);
                console.error('Error:', error);
            });
        }

        function showLoading() {
            document.getElementById('loadingOverlay').classList.remove('hidden');
            document.getElementById('loadingOverlay').classList.add('flex');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.add('hidden');
            document.getElementById('loadingOverlay').classList.remove('flex');
        }
    </script>
</body>
</html>
