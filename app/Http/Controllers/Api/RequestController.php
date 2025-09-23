<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkflowService;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RequestController extends Controller
{
    protected $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Display a listing of requests
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = RequestModel::with(['employee', 'employee.department', 'procurement', 'notifications', 'verifiedBy', 'auditLogs.user']);

        // Check if user wants to see all requests (for managers, admins, and procurement users)
        $tab = $request->get('tab', 'my-requests');
        $showAllRequests = $tab === 'all-requests' && ($user->role->name === 'admin' || $user->role->name === 'manager' || $user->role->name === 'procurement');

        // Filter based on user role
        switch ($user->role->name) {
            case 'employee':
                $query->where('employee_id', $user->id);
                break;
            case 'manager':
                if ($showAllRequests) {
                    // Show all requests that have been assigned to this manager (past or present)
                    $query->where(function($q) use ($user) {
                        // Show requests from their own department
                        $q->whereHas('employee', function($empQuery) use ($user) {
                            $empQuery->where('department_id', $user->department_id);
                        })
                        // OR show requests assigned to their department in workflow steps (any status)
                        ->orWhere(function($workflowQuery) use ($user) {
                            $workflowQuery->whereExists(function($subQuery) use ($user) {
                                $subQuery->select(DB::raw(1))
                                    ->from('workflow_steps')
                                    ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                    ->where('workflow_step_assignments.assignable_type', 'App\\Models\\Department')
                                    ->where('workflow_step_assignments.assignable_id', $user->department_id)
                                    ->where('workflow_steps.is_active', true);
                            });
                        })
                        // OR show requests assigned to them personally in workflow steps (any status)
                        ->orWhere(function($personalQuery) use ($user) {
                            $personalQuery->whereExists(function($subQuery) use ($user) {
                                $subQuery->select(DB::raw(1))
                                    ->from('workflow_steps')
                                    ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                    ->where('workflow_steps.is_active', true)
                                    ->where(function($assignmentQuery) use ($user) {
                                        $assignmentQuery->where(function($userQuery) use ($user) {
                                            $userQuery->where('workflow_step_assignments.assignable_type', 'App\\Models\\User')
                                                ->where('workflow_step_assignments.assignable_id', $user->id);
                                        })
                                        ->orWhere(function($financeQuery) use ($user) {
                                            $financeQuery->where('workflow_step_assignments.assignable_type', 'App\\Models\\FinanceAssignment')
                                                ->whereExists(function($financeSubQuery) use ($user) {
                                                    $financeSubQuery->select(DB::raw(1))
                                                        ->from('finance_assignments')
                                                        ->whereColumn('finance_assignments.id', 'workflow_step_assignments.assignable_id')
                                                        ->where('finance_assignments.user_id', $user->id)
                                                        ->where('finance_assignments.is_active', true);
                                                });
                                        });
                                    });
                            });
                        });
                    });
                } else {
                    // For managers in 'my-requests' tab, show requests they can approve (including delegated ones)
                    $query->where(function($q) use ($user) {
                        // Show requests from their own department
                        $q->whereHas('employee', function($empQuery) use ($user) {
                            $empQuery->where('department_id', $user->department_id);
                        })
                        // OR show requests assigned to their department in workflow steps
                        ->orWhere(function($workflowQuery) use ($user) {
                            $workflowQuery->whereExists(function($subQuery) use ($user) {
                                $subQuery->select(DB::raw(1))
                                    ->from('workflow_steps')
                                    ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                    ->where('workflow_step_assignments.assignable_type', 'App\\Models\\Department')
                                    ->where('workflow_step_assignments.assignable_id', $user->department_id)
                                    ->where('workflow_steps.is_active', true);
                            });
                        })
                        // OR show requests assigned to them personally in workflow steps
                        ->orWhere(function($personalQuery) use ($user) {
                            $personalQuery->whereIn('status', ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered'])
                                ->whereExists(function($subQuery) use ($user) {
                                    $subQuery->select(DB::raw(1))
                                        ->from('workflow_steps')
                                        ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                        ->where('workflow_steps.is_active', true)
                                        ->where(function($assignmentQuery) use ($user) {
                                            $assignmentQuery->where(function($userQuery) use ($user) {
                                                $userQuery->where('workflow_step_assignments.assignable_type', 'App\\Models\\User')
                                                    ->where('workflow_step_assignments.assignable_id', $user->id);
                                            })
                                            ->orWhere(function($roleQuery) use ($user) {
                                                $roleQuery->where('workflow_step_assignments.assignable_type', 'App\\Models\\Role')
                                                    ->where('workflow_step_assignments.assignable_id', $user->role_id);
                                            })
                                            ->orWhere(function($financeQuery) use ($user) {
                                                $financeQuery->where('workflow_step_assignments.assignable_type', 'App\\Models\\FinanceAssignment')
                                                    ->whereExists(function($financeSubQuery) use ($user) {
                                                        $financeSubQuery->select(DB::raw(1))
                                                            ->from('finance_assignments')
                                                            ->whereColumn('finance_assignments.id', 'workflow_step_assignments.assignable_id')
                                                            ->where('finance_assignments.user_id', $user->id)
                                                            ->where('finance_assignments.is_active', true);
                                                    });
                                            });
                                        });
                                });
                        });
                    });

                    // Check if this is a Finance user (has FinanceAssignment)
                    $isFinanceUser = \App\Models\FinanceAssignment::where('user_id', $user->id)
                        ->where('is_active', true)
                        ->exists();

                    if ($isFinanceUser) {
                        // Finance users should ONLY see requests that are currently at their assigned step
                        $query->where(function($q) use ($user) {
                            $q->whereIn('status', ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered'])
                                ->whereExists(function($subQuery) use ($user) {
                                    $subQuery->select(DB::raw(1))
                                        ->from('workflow_steps')
                                        ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                        ->where('workflow_steps.is_active', true)
                                        ->where(function($assignmentQuery) use ($user) {
                                            $assignmentQuery->where(function($userQuery) use ($user) {
                                                $userQuery->where('workflow_step_assignments.assignable_type', 'App\\Models\\User')
                                                    ->where('workflow_step_assignments.assignable_id', $user->id);
                                            })
                                            ->orWhere(function($financeQuery) use ($user) {
                                                $financeQuery->where('workflow_step_assignments.assignable_type', 'App\\Models\\FinanceAssignment')
                                                    ->whereExists(function($financeSubQuery) use ($user) {
                                                        $financeSubQuery->select(DB::raw(1))
                                                            ->from('finance_assignments')
                                                            ->whereColumn('finance_assignments.id', 'workflow_step_assignments.assignable_id')
                                                            ->where('finance_assignments.user_id', $user->id)
                                                            ->where('finance_assignments.is_active', true);
                                                    });
                                            });
                                        })
                                        // Only show requests that are currently at this step (not completed yet)
                                        ->whereNotExists(function($completedQuery) {
                                            $completedQuery->select(DB::raw(1))
                                                ->from('audit_logs')
                                                ->whereColumn('audit_logs.request_id', 'requests.id')
                                                ->where('audit_logs.action', 'Step completed')
                                                ->where('audit_logs.notes', 'like', '%Finance Approval%');
                                        })
                                        // And ensure the step has been started
                                        ->whereExists(function($startedQuery) {
                                            $startedQuery->select(DB::raw(1))
                                                ->from('audit_logs')
                                                ->whereColumn('audit_logs.request_id', 'requests.id')
                                                ->where('audit_logs.action', 'Workflow Step Started')
                                                ->where('audit_logs.notes', 'like', '%Finance Approval%');
                                        });
                                });
                        });
                    } else {
                        // Regular managers see requests from their department and assigned to them
                        $query->where(function($q) use ($user) {
                            // Show requests from their own department
                            $q->whereHas('employee', function($empQuery) use ($user) {
                                $empQuery->where('department_id', $user->department_id);
                            })
                            // OR show requests assigned to their department in workflow steps
                            ->orWhere(function($workflowQuery) use ($user) {
                                $workflowQuery->whereIn('status', ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered'])
                                    ->whereExists(function($subQuery) use ($user) {
                                        $subQuery->select(DB::raw(1))
                                            ->from('workflow_steps')
                                            ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                            ->where('workflow_step_assignments.assignable_type', 'App\\Models\\Department')
                                            ->where('workflow_step_assignments.assignable_id', $user->department_id)
                                            ->where('workflow_steps.is_active', true);
                                    });
                            })
                            // OR show requests assigned to them personally in workflow steps
                            ->orWhere(function($personalQuery) use ($user) {
                                $personalQuery->whereIn('status', ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered'])
                                    ->whereExists(function($subQuery) use ($user) {
                                        $subQuery->select(DB::raw(1))
                                            ->from('workflow_steps')
                                            ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                            ->where('workflow_steps.is_active', true)
                                            ->where(function($assignmentQuery) use ($user) {
                                                $assignmentQuery->where(function($userQuery) use ($user) {
                                                    $userQuery->where('workflow_step_assignments.assignable_type', 'App\\Models\\User')
                                                        ->where('workflow_step_assignments.assignable_id', $user->id);
                                                })
                                                ->orWhere(function($financeQuery) use ($user) {
                                                    $financeQuery->where('workflow_step_assignments.assignable_type', 'App\\Models\\FinanceAssignment')
                                                        ->whereExists(function($financeSubQuery) use ($user) {
                                                            $financeSubQuery->select(DB::raw(1))
                                                                ->from('finance_assignments')
                                                                ->whereColumn('finance_assignments.id', 'workflow_step_assignments.assignable_id')
                                                                ->where('finance_assignments.user_id', $user->id)
                                                                ->where('finance_assignments.is_active', true);
                                                        });
                                                });
                                            });
                                    });
                            });
                        });
                    }
                }
                break;
            case 'procurement':
                if ($showAllRequests) {
                    // Show all requests that are in procurement workflow
                    // Procurement users should see ALL requests in procurement workflow, not just assigned ones
                    $query->whereIn('status', [
                        'Pending Procurement Verification',
                        'Pending Approval',
                        'Approved',
                        'Rejected',
                        'Pending Procurement',
                        'Ordered',
                        'Delivered',
                        'Cancelled'
                    ]);
                } else {
                    // Show only requests created by the current user
                    $query->where('employee_id', $user->id);
                }
                break;
            case 'admin':
                if (!$showAllRequests) {
                    // Admin can see all requests by default, but if they want "My Requests", show only their own
                    $query->where('employee_id', $user->id);
                }
                // If showAllRequests is true, admin sees everything (no additional filtering)
                break;
            default:
                // For other roles (like Finance), check if they are assigned to any workflow steps
                if ($this->isUserAssignedToAnyWorkflowStep($user)) {
                    $query->where(function($q) use ($user) {
                        // Show requests that are assigned to this user's department in workflow steps
                        $q->whereHas('employee', function($empQuery) use ($user) {
                            $empQuery->where('department_id', $user->department_id);
                        })
                        ->orWhere(function($workflowQuery) use ($user) {
                            // Show requests that are in workflow steps assigned to this user's department
                            $workflowQuery->whereIn('status', ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered'])
                                ->whereExists(function($subQuery) use ($user) {
                                    $subQuery->select(DB::raw(1))
                                        ->from('workflow_steps')
                                        ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                        ->where('workflow_step_assignments.assignable_type', 'App\\\\Models\\\\Department')
                                        ->where('workflow_step_assignments.assignable_id', $user->department_id)
                                        ->where('workflow_steps.is_active', true);
                                });
                        });
                    });
                } else {
                    // If user is not assigned to any workflow step, only show their own requests
                    $query->where('employee_id', $user->id);
                }
                break;
        }

        // Add delegation-based filtering for all users
        $this->addDelegationFilter($query, $user);

        // Apply filters
        if ($request->has('status')) {
            if ($request->status === 'Delayed') {
                // For Delayed status, filter by audit logs using raw query
                $query->whereExists(function($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('audit_logs')
                        ->whereColumn('audit_logs.request_id', 'requests.id')
                        ->where('audit_logs.action', 'Delayed');
                });
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->has('department_id')) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Store a newly created request
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'item' => 'required|string|max:255',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $requestData = $request->only(['item', 'description', 'amount']);
            $newRequest = $this->workflowService->submitRequest($requestData, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Request submitted successfully',
                'data' => $newRequest->load(['employee', 'employee.department'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified request
     */
    public function show(string $id): JsonResponse
    {
        $request = RequestModel::with([
            'employee',
            'employee.department',
            'procurement',
            'notifications.receiver',
            'auditLogs.user',
            'verifiedBy',
            'billPrintedBy'
        ])->findOrFail($id);

        // Check if user can view this request
        if (!$this->canViewRequest($request, Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this request'
            ], 403);
        }

        // Add approval workflow information
        $request->approval_workflow = $this->getApprovalWorkflowInfo($request);

        // Add delegation information if user is acting on behalf of someone
        $request->delegation_info = $this->getDelegationInfo($request, Auth::user());

        return response()->json([
            'success' => true,
            'data' => $request
        ]);
    }

    /**
     * Approve a request
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->workflowService->approveRequest(
                $id,
                Auth::id(),
                $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Request approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a request
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->workflowService->rejectRequest(
                $id,
                Auth::id(),
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Request rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delay a request for later review
     */
    public function delay(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'delay_date' => 'required|date|after_or_equal:today',
            'delay_reason' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $requestModel = RequestModel::findOrFail($id);
            $user = Auth::user();

            // Check if user can delay this request (only Finance assignment)
            if ($user->id !== 28) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Finance users can delay requests'
                ], 403);
            }

            // Check if request is in correct status for delay
            if (!in_array($requestModel->status, ['Pending', 'Pending Approval'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request is not in a valid status for delay'
                ], 400);
            }

            // Use WorkflowService to delay the workflow step
            $workflowService = app(\App\Services\WorkflowService::class);
            $workflowService->delayWorkflowStep(
                $requestModel->id,
                $user->id,
                $request->input('delay_date'),
                $request->input('delay_reason')
            );

            // Also log the legacy delay action for backward compatibility
            AuditLog::create([
                'user_id' => $user->id,
                'request_id' => $requestModel->id,
                'action' => 'Delayed',
                'notes' => "Request delayed until {$request->input('delay_date')}" .
                          ($request->input('delay_reason') ? " - {$request->input('delay_reason')}" : "")
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Request delayed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delay request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process bill printing for a request
     */
    public function billPrinting(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bill_amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $requestModel = RequestModel::findOrFail($id);
            $user = Auth::user();

            // Check if user is authorized to process bill printing
            if (!$this->canProcessBillPrinting($requestModel, $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to process bill printing for this request'
                ], 403);
            }

            // Update request with bill printing information
            $billNumber = $this->generateBillNumber();
            $requestModel->update([
                'bill_number' => $billNumber,
                'bill_amount' => $request->input('bill_amount'),
                'bill_printed_at' => now(),
                'bill_printed_by' => $user->id,
                'bill_status' => 'printed'
            ]);

            // Log the bill printing action
            AuditLog::create([
                'user_id' => $user->id,
                'request_id' => $requestModel->id,
                'action' => 'Bill Printed',
                'details' => "Bill printed with number: {$billNumber}, Amount: {$request->input('bill_amount')}"
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bill printed successfully',
                'data' => [
                    'bill_number' => $request->input('bill_number'),
                    'bill_amount' => $request->input('bill_amount'),
                    'bill_printed_at' => $requestModel->bill_printed_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bill printing',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finance approval with bill printing
     */
    public function financeApproveWithBill(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
            'bill_amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $requestModel = RequestModel::findOrFail($id);
            $user = Auth::user();

            // Check if user is authorized (Finance user)
            if (!$this->canProcessBillPrinting($requestModel, $user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to process this request'
                ], 403);
            }

            DB::beginTransaction();

            // Generate automatic bill number
            $billNumber = $this->generateBillNumber();

            // First, process bill printing
            $requestModel->update([
                'bill_number' => $billNumber,
                'bill_amount' => $request->input('bill_amount'),
                'bill_printed_at' => now(),
                'bill_printed_by' => $user->id,
                'bill_status' => 'printed'
            ]);

            // Log the bill printing action
            AuditLog::create([
                'user_id' => $user->id,
                'request_id' => $requestModel->id,
                'action' => 'Bill Printed',
                'details' => "Bill printed with number: {$billNumber}, Amount: {$requestModel->amount}"
            ]);

            // Then, approve the request
            $this->workflowService->approveRequest(
                $id,
                $user->id,
                $request->input('notes') . " (Bill printed: {$billNumber})"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request approved and bill printed successfully',
                'data' => [
                    'bill_number' => $billNumber,
                    'bill_amount' => $requestModel->amount,
                    'bill_printed_at' => $requestModel->fresh()->bill_printed_at
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve request and print bill',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function generateBillNumber()
    {
        $year = date('Y');
        $month = date('m');

        // Get the last bill number for this year and month
        $lastBill = RequestModel::whereNotNull('bill_number')
            ->where('bill_number', 'like', "BILL-{$year}{$month}-%")
            ->orderBy('bill_number', 'desc')
            ->first();

        if ($lastBill) {
            // Extract the number part and increment
            $parts = explode('-', $lastBill->bill_number);
            $lastNumber = (int) end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return "BILL-{$year}{$month}-" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Check if user can process bill printing
     */
    private function canProcessBillPrinting(RequestModel $request, User $user): bool
    {
        // Check if user is from Finance department and assigned to this request
        if ($user->department && $user->department->name === 'Finance') {
            // Check if this user is assigned to the current workflow step
            $currentStep = $this->getCurrentWorkflowStep($request);
            if ($currentStep) {
                $assignments = $currentStep->assignments;
                foreach ($assignments as $assignment) {
                    // Check for direct user assignment
                    if ($assignment->assignable_type === 'App\\Models\\User' &&
                        $assignment->assignable_id == $user->id) {
                        return true;
                    }

                    // Check for FinanceAssignment assignment
                    if ($assignment->assignable_type === 'App\\Models\\FinanceAssignment') {
                        $financeAssignment = \App\Models\FinanceAssignment::find($assignment->assignable_id);
                        if ($financeAssignment && $financeAssignment->user_id == $user->id && $financeAssignment->is_active) {
                            return true;
                        }
                    }
                }
            }
        }

        // Check if user is from Procurement department and assigned to this request
        if ($user->department && $user->department->name === 'Procurement') {
            // Check if this user is assigned to the current workflow step
            $currentStep = $this->getCurrentWorkflowStep($request);
            if ($currentStep) {
                $assignments = $currentStep->assignments;
                foreach ($assignments as $assignment) {
                    // Check for direct user assignment
                    if ($assignment->assignable_type === 'App\\Models\\User' &&
                        $assignment->assignable_id == $user->id) {
                        return true;
                    }

                    // Check for role assignment
                    if ($assignment->assignable_type === 'App\\Models\\Role' &&
                        $assignment->assignable_id == $user->role_id) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get current workflow step for a request
     */
    private function getCurrentWorkflowStep(RequestModel $request)
    {
        // Get the current pending step for this request
        $workflowSteps = \App\Models\WorkflowStep::getStepsForRequest($request);

        foreach ($workflowSteps as $step) {
            // Check if this step is currently pending
            if ($this->isStepPending($request, $step, $workflowSteps->search(function($s) use ($step) {
                return $s->id === $step->id;
            }))) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Update procurement status (legacy method for admin)
     */
    public function updateProcurement(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Ordered,Delivered,Failed',
            'final_cost' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user is admin (only admin can update procurement status in our simplified system)
        if (Auth::user()->role->name !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can update procurement status'
            ], 403);
        }

        try {
            $this->workflowService->updateProcurementStatus(
                $id,
                $request->input('status'),
                $request->input('final_cost')
            );

            return response()->json([
                'success' => true,
                'message' => 'Procurement status updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update procurement status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process procurement approval (for procurement users)
     */
    public function processProcurement(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Pending Procurement,Ordered,Delivered,Cancelled',
            'final_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user can process procurement
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user || !$user->canProcessProcurement()) {
            return response()->json([
                'success' => false,
                'message' => 'Only procurement team members can process procurement requests'
            ], 403);
        }

        try {
            $this->workflowService->processProcurementApproval(
                $id,
                Auth::id(),
                $request->input('status'),
                $request->input('final_cost'),
                $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Procurement request processed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process procurement request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get procurement requests (all requests that need procurement processing)
     */
    public function pendingProcurement(Request $request): JsonResponse
    {
        try {
            // Check if user can process procurement
            /** @var \App\Models\User $user */
            $user = Auth::user();
            if (!$user || !$user->canProcessProcurement()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only procurement team members can view procurement requests'
                ], 403);
            }

            // Check if user wants to see all requests or just their own
            $tab = $request->get('tab', 'all-requests');
            $showAllRequests = $tab === 'all-requests';

            // Get all requests that are in procurement workflow
            // This includes: Pending Procurement Verification, Pending Approval, Approved, Rejected, Pending Procurement, Ordered, Delivered, Cancelled
            $query = RequestModel::whereIn('status', [
                'Pending Procurement Verification',
                'Pending Approval',
                'Approved',
                'Rejected',
                'Pending Procurement',
                'Ordered',
                'Delivered',
                'Cancelled'
            ])
            ->with(['employee.department', 'procurement', 'verifiedBy', 'auditLogs.user']);

            // Filter based on tab
            if ($tab === 'my-requests') {
                // Show only requests created by the current user
                $query->where('employee_id', $user->id);
            } else {
                // Show all requests (all-requests tab)
                // For procurement users, this means all requests that are in procurement workflow
                // No additional filtering needed as the status filter above already handles this
            }

            // Apply status filter if provided
            if ($request->has('status') && $request->status !== 'all') {
                if ($request->status === 'Delayed') {
                    // For Delayed status, filter by audit logs using raw query
                    $query->whereExists(function($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('audit_logs')
                            ->whereColumn('audit_logs.request_id', 'requests.id')
                            ->where('audit_logs.action', 'Delayed');
                    });
                } else {
                    $query->where('status', $request->status);
                }
            }

            $requests = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $requests
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch procurement requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get approved requests ready for procurement
     */
    public function approvedRequests(Request $request): JsonResponse
    {
        try {
            // Check if user can process procurement
            /** @var \App\Models\User $user */
            $user = Auth::user();
            if (!$user || !$user->canProcessProcurement()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only procurement team members can view approved requests'
                ], 403);
            }

            // Get all approved requests (ready for procurement)
            $query = RequestModel::where('status', 'Approved')
                ->with(['employee.department', 'procurement', 'verifiedBy']);

            $requests = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $requests
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch approved requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get requests pending procurement verification
     */
    public function pendingProcurementVerification(): JsonResponse
    {
        try {
            // Check if user can process procurement verification
            /** @var \App\Models\User $user */
            $user = Auth::user();
            if (!$user || !$user->canProcessProcurement()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only procurement team members can view verification requests'
                ], 403);
            }

            // Get all requests pending procurement verification
            $requests = RequestModel::where('procurement_status', 'Pending Verification')
                ->with(['employee.department', 'verifiedBy'])
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $requests
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch verification requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process procurement verification
     */
    public function processProcurementVerification(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Verified,Not Available,Rejected',
            'final_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user can process procurement verification
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user || !$user->canProcessProcurement()) {
            return response()->json([
                'success' => false,
                'message' => 'Only procurement team members can process verification requests'
            ], 403);
        }

        try {
            $this->workflowService->processProcurementVerification(
                $id,
                Auth::id(),
                $request->input('status'),
                $request->input('final_price'),
                $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Procurement verification processed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process procurement verification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get requests pending approval for current user
     */
    public function pendingApprovals(): JsonResponse
    {
        $user = Auth::user();

        $query = RequestModel::with(['employee', 'employee.department'])
            ->where('status', 'Pending');

        // Get requests where user is directly assigned
        switch ($user->role->name) {
            case 'manager':
                $query->whereHas('employee', function($empQuery) use ($user) {
                    $empQuery->where('department_id', $user->department_id);
                });
                break;
            case 'admin':
                // Can see all pending requests
                break;
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'No pending approvals for your role'
                ], 403);
        }

        $requests = $query->orderBy('created_at', 'asc')->get();

        // Filter requests based on delegation
        $filteredRequests = $requests->filter(function($request) use ($user) {
            // Check if user can approve this request (including delegations)
            return $this->workflowService->canUserApproveRequest($request, $user);
        });

        // Add delegation info to each request
        foreach ($filteredRequests as $request) {
            $request->delegation_info = $this->getDelegationInfo($request, $user);
        }

        return response()->json([
            'success' => true,
            'data' => $filteredRequests->values()
        ]);
    }

    /**
     * Rollback a cancelled request
     */
    public function rollbackRequest(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user can process procurement
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user || !$user->canProcessProcurement()) {
            return response()->json([
                'success' => false,
                'message' => 'Only procurement team members can rollback requests'
            ], 403);
        }

        try {
            $this->workflowService->rollbackCancelledRequest(
                $id,
                Auth::id(),
                $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Request restored successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get audit logs for a request (only important logs)
     */
    public function auditLogs(string $id): JsonResponse
    {
        $request = RequestModel::findOrFail($id);

        // Check if user can view this request
        if (!$this->canViewRequest($request, Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this request'
            ], 403);
        }

        // Get all workflow-related audit logs
        $auditLogs = $request->auditLogs()
            ->with('user')
            ->whereIn('action', [
                'Workflow Step Completed',
                'Workflow Step Rejected',
                'Workflow Step Delayed',
                'Workflow Step Cancelled',
                'Step Forwarded',
                'Submitted',
                'All Approvals Complete'
            ])
            ->orderBy('created_at', 'asc') // Show in chronological order
            ->get();

        return response()->json([
            'success' => true,
            'data' => $auditLogs
        ]);
    }

    /**
     * Get approval workflow information for a request
     */
    private function getApprovalWorkflowInfo(RequestModel $request): array
    {
        $employee = $request->employee;
        $department = $employee->department;
        $amount = $request->amount;

        // Check if request is rejected or cancelled
        if ($request->status === 'Rejected' || $request->status === 'Cancelled') {
            return $this->getRejectedWorkflowInfo($request);
        }

        // Procurement users follow the same standard workflow as other users

        // Use dynamic workflow steps from WorkflowStep model
        $workflowSteps = \App\Models\WorkflowStep::getStepsForRequest($request);

        if ($workflowSteps->isEmpty()) {
            // Fallback to old system if no workflow steps are configured
            return $this->getLegacyWorkflowInfo($request);
        }

        return $this->getDynamicWorkflowStepsInfo($request, $workflowSteps);
    }

    /**
     * Get workflow info using dynamic workflow steps
     */
    private function getDynamicWorkflowStepsInfo(RequestModel $request, $workflowSteps): array
    {
        $steps = [];
        $currentStep = 0;
        $totalSteps = $workflowSteps->count();
        $waitingFor = null;
        $nextApprover = null;

        // Check if the request creator is admin
        $isAdminRequest = $request->employee && $request->employee->role && $request->employee->role->name === 'admin';

        foreach ($workflowSteps as $index => $step) {
            // Skip manager assignment steps for admin requests
            if ($isAdminRequest && $this->isManagerAssignmentStep($step)) {
                continue;
            }

            $stepStatus = $this->getStepStatus($request, $step, $index);
            $stepInfo = [
                'id' => $step->id,
                'name' => $step->name,
                'role' => $this->getStepRoleName($step),
                'status' => $stepStatus,
                'description' => $step->description ?: $this->getStepDescription($step, $stepStatus),
                'approver' => $this->getStepApprover($step),
                'order' => $step->order_index,
                'step_type' => $step->step_type,
                'is_active' => $step->is_active,
                'timeout_hours' => $step->timeout_hours,
                'assignments' => $step->assignments->map(function($assignment) {
                    return [
                        'id' => $assignment->id,
                        'assignment_type' => $this->getAssignmentType($assignment),
                        'assignable_name' => $this->getAssignableName($assignment),
                        'is_required' => $assignment->is_required
                    ];
                })
            ];

            $steps[] = $stepInfo;

            // Determine current step and waiting status
            if ($stepStatus === 'completed') {
                $currentStep = $index + 1;
            } elseif ($stepStatus === 'cancelled' || $stepStatus === 'rejected') {
                // For cancelled or rejected steps, don't update currentStep
                // The workflow is stopped at this point
            } elseif ($stepStatus === 'pending' && !$waitingFor) {
                $waitingFor = $stepInfo['role'];
                $nextApprover = $stepInfo['approver'];
                // Don't update currentStep here - it should remain 0 until completed
            } elseif ($stepStatus === 'waiting' && !$waitingFor) {
                // If no pending step found, the next waiting step is the current one
                $waitingFor = $stepInfo['role'];
                $nextApprover = $stepInfo['approver'];
                // Don't update currentStep here - it should remain 0 until completed
            }
        }

        // Check if current user can approve this request
        $canApprove = $this->canApproveRequest($request, Auth::user());

        return [
            'is_sequential' => true,
            'current_step' => $currentStep,
            'total_steps' => $totalSteps,
            'steps' => $steps,
            'next_approver' => $nextApprover,
            'waiting_for' => $waitingFor,
            'workflow_type' => 'dynamic',
            'can_approve' => $canApprove,
            'can_approve_message' => $canApprove ? 'Waiting for your turn to approve' : 'Waiting for ' . ($waitingFor ?? 'approval')
        ];
    }

    /**
     * Get step status based on request and step
     */
    private function getStepStatus(RequestModel $request, $step, $index): string
    {
        // Check if step is cancelled
        if ($this->isStepCancelled($request, $step)) {
            return 'cancelled';
        }

        // Check if step is rejected
        if ($this->isStepRejected($request, $step)) {
            return 'rejected';
        }

        // Check if step is completed
        if ($this->isStepCompleted($request, $step)) {
            return 'completed';
        }

        // Check if step is currently pending
        if ($this->isStepPending($request, $step, $index)) {
            return 'pending';
        }

        // For steps that are not yet active, show as waiting
        return 'waiting';
    }

    /**
     * Check if step is completed
     */
    private function isStepCompleted(RequestModel $request, $step): bool
    {
        // Implementation depends on your business logic
        // This is a simplified version
        switch ($step->step_type) {
            case 'approval':
                return $this->isApprovalStepCompleted($request, $step);
            case 'verification':
                return $this->isVerificationStepCompleted($request, $step);
            case 'notification':
                return $this->isNotificationStepCompleted($request, $step);
            default:
                return false;
        }
    }

    /**
     * Check if step is cancelled
     */
    private function isStepCancelled(RequestModel $request, $step): bool
    {
        // Check if step is specifically cancelled in audit logs
        $stepCancelled = AuditLog::where('request_id', $request->id)
            ->where('action', 'Workflow Step Cancelled')
            ->where('notes', 'like', '%' . $step->name . '%')
            ->exists();

        if ($stepCancelled) {
            return true;
        }

        // Check for procurement step cancellation
        if ($step->step_type === 'approval' && $step->name === 'Procurement order') {
            // For procurement order step, check if request status is Cancelled
            if ($request->status === 'Cancelled') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if step is rejected
     */
    private function isStepRejected(RequestModel $request, $step): bool
    {
        // Check if step is specifically rejected in audit logs
        $stepRejected = AuditLog::where('request_id', $request->id)
            ->where('action', 'Workflow Step Rejected')
            ->where('notes', 'like', '%' . $step->name . '%')
            ->exists();

        if ($stepRejected) {
            return true;
        }

        // Check if step is specifically cancelled in audit logs
        $stepCancelled = AuditLog::where('request_id', $request->id)
            ->where('action', 'Workflow Step Cancelled')
            ->where('notes', 'like', '%' . $step->name . '%')
            ->exists();

        if ($stepCancelled) {
            return true;
        }

        // Check for verification step rejection
        if ($step->step_type === 'verification') {
            // For procurement verification, check if it was rejected
            if ($request->procurement_status === 'Rejected' || $request->procurement_status === 'Not Available') {
                return true;
            }
        }

        // If request is rejected, check if this step was the one that caused the rejection
        if ($request->status === 'Rejected') {
            // Check if this step was the current pending step when rejected
            $rejectionLog = AuditLog::where('request_id', $request->id)
                ->where('action', 'Workflow Step Rejected')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($rejectionLog && $rejectionLog->notes && strpos($rejectionLog->notes, $step->name) !== false) {
                return true;
            }

            // Only return true if this specific step was rejected, not all steps
            return false;
        }

        return false;
    }

    /**
     * Check if step is currently pending
     */
    private function isStepPending(RequestModel $request, $step, $index): bool
    {
        // If step is already completed, rejected, or cancelled, it's not pending
        if ($this->isStepCompleted($request, $step) || $this->isStepRejected($request, $step) || $this->isStepCancelled($request, $step)) {
            return false;
        }

        // Check if all previous steps are completed
        $previousStepsCompleted = true;
        // We need to get the actual workflow steps to check previous ones
        $workflowSteps = \App\Models\WorkflowStep::getStepsForRequest($request);

        for ($i = 0; $i < $index; $i++) {
            $previousStep = $workflowSteps[$i] ?? null;
            if ($previousStep && !$this->isStepCompleted($request, $previousStep)) {
                $previousStepsCompleted = false;
                break;
            }
        }

        return $previousStepsCompleted;
    }

    /**
     * Get step role name for display
     */
    private function getStepRoleName($step): string
    {
        return $step->name;
    }

    /**
     * Get step description
     */
    private function getStepDescription($step, $status): string
    {
        if ($step->description) {
            return $step->description;
        }

        // Return status-specific descriptions
        switch ($status) {
            case 'cancelled':
                return 'Step cancelled';
            case 'rejected':
                return 'Step rejected';
            case 'completed':
                return 'Step completed';
            case 'pending':
                return 'Waiting for approval';
            case 'waiting':
                return 'Waiting for previous steps';
            default:
                break;
        }

        switch ($step->step_type) {
            case 'approval':
                return 'Approval required';
            case 'verification':
                return 'Verification required';
            case 'notification':
                return 'Notification sent';
            default:
                return 'Step ' . ($step->order_index + 1);
        }
    }

    /**
     * Get step approver name
     */
    private function getStepApprover($step): string
    {
        $assignments = $step->assignments;
        if ($assignments->isEmpty()) {
            return 'Not assigned';
        }

        $assignableNames = $assignments->map(function($assignment) {
            return $this->getAssignableName($assignment);
        })->toArray();

        return implode(', ', $assignableNames);
    }

    /**
     * Get assignment type from assignable relationship
     */
    private function getAssignmentType($assignment): string
    {
        switch ($assignment->assignable_type) {
            case 'App\\Models\\User':
                return 'user';
            case 'App\\Models\\Role':
                $role = \App\Models\Role::find($assignment->assignable_id);
                return $role ? $role->name : 'admin';
            case 'App\\Models\\Department':
                $department = \App\Models\Department::find($assignment->assignable_id);
                return $department ? $department->name : 'department';
            case 'App\\Models\\FinanceAssignment':
                return 'finance';
            default:
                return 'unknown';
        }
    }

    /**
     * Get assignable name for display
     */
    private function getAssignableName($assignment): string
    {
        switch ($assignment->assignable_type) {
            case 'App\\Models\\User':
                $user = \App\Models\User::find($assignment->assignable_id);
                return $user ? $user->full_name : 'Unknown User';
            case 'App\\Models\\Role':
                $role = \App\Models\Role::find($assignment->assignable_id);
                return $role ? $role->name : 'Unknown Role';
            case 'App\\Models\\Department':
                $department = \App\Models\Department::find($assignment->assignable_id);
                return $department ? $department->name : 'Unknown Department';
            case 'App\\Models\\FinanceAssignment':
                $financeAssignment = \App\Models\FinanceAssignment::with('user')->find($assignment->assignable_id);
                return $financeAssignment && $financeAssignment->user ? $financeAssignment->user->full_name : 'Finance User';
            default:
                return 'Unknown';
        }
    }

    /**
     * Check if approval step is completed
     */
    private function isApprovalStepCompleted(RequestModel $request, $step): bool
    {
        // Use WorkflowService to check step completion with proper required assignments logic
        $workflowService = app(\App\Services\WorkflowService::class);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($workflowService);
        $method = $reflection->getMethod('isStepCompleted');
        $method->setAccessible(true);

        return $method->invoke($workflowService, $request, $step);
    }

    /**
     * Check if verification step is completed
     */
    private function isVerificationStepCompleted(RequestModel $request, $step): bool
    {
        // Check if procurement verification is completed
        return $request->procurement_status === 'Verified';
    }

    /**
     * Check if notification step is completed
     */
    private function isNotificationStepCompleted(RequestModel $request, $step): bool
    {
        // Notifications are considered completed when sent
        return true; // This might need more complex logic based on your requirements
    }

    /**
     * Fallback to legacy workflow system
     */
    private function getLegacyWorkflowInfo(RequestModel $request): array
    {
        $employee = $request->employee;
        $department = $employee->department;
        $amount = $request->amount;

        // Get approval rules for this department
        $rules = \App\Models\ApprovalRule::where('department_id', $department->id)
            ->where('min_amount', '<=', $amount)
            ->where('max_amount', '>=', $amount)
            ->orderBy('order')
            ->get();

        $workflowInfo = [
            'is_sequential' => $rules->count() > 1,
            'current_step' => 1,
            'total_steps' => $rules->count(),
            'steps' => [],
            'next_approver' => null,
            'waiting_for' => null,
            'workflow_type' => 'legacy'
        ];

        if ($rules->isEmpty()) {
            // Use default workflow
            $workflowInfo = $this->getDefaultWorkflowInfo($request);
        } else {
            // Use dynamic rules
            $workflowInfo = $this->getDynamicWorkflowInfo($request, $rules);
        }

        // Add Procurement Verification step if needed
        $workflowInfo = $this->addProcurementVerificationStep($request, $workflowInfo);

        return $workflowInfo;
    }

    /**
     * Add Procurement Verification step to workflow
     */
    private function addProcurementVerificationStep(RequestModel $request, array $workflowInfo): array
    {
        // Only add procurement verification step for non-procurement users
        if ($request->employee->isProcurement()) {
            return $workflowInfo;
        }

        // Check if procurement verification is needed
        $needsProcurementVerification = in_array($request->status, [
            'Pending Procurement Verification',
            'Pending Approval',
            'Approved',
            'Pending Procurement',
            'Ordered',
            'Delivered',
            'Cancelled'
        ]);

        if (!$needsProcurementVerification) {
            return $workflowInfo;
        }

        // Determine procurement verification status
        $procurementStatus = 'waiting';
        if ($request->procurement_status === 'Verified') {
            $procurementStatus = 'completed';
        } elseif ($request->procurement_status === 'Not Available' || $request->procurement_status === 'Rejected') {
            $procurementStatus = 'rejected';
        } elseif ($request->status === 'Pending Procurement Verification') {
            $procurementStatus = 'pending';
        }

        // Add procurement verification step at the beginning
        $procurementStep = [
            'role' => 'Procurement',
            'status' => $procurementStatus,
            'description' => 'Procurement verification required',
            'approver' => 'Procurement Team',
            'order' => 0
        ];

        // Insert at the beginning of steps
        array_unshift($workflowInfo['steps'], $procurementStep);

        // Update total steps
        $workflowInfo['total_steps'] = count($workflowInfo['steps']);

        // Update current step and waiting status
        if ($procurementStatus === 'pending') {
            $workflowInfo['waiting_for'] = 'Procurement';
            $workflowInfo['next_approver'] = 'Procurement Team';
            $workflowInfo['current_step'] = 0;
        } elseif ($procurementStatus === 'completed') {
            // Move to next step if procurement is completed
            $workflowInfo['current_step'] = 1;
            // Find next pending step (skip the procurement step at index 0)
            foreach ($workflowInfo['steps'] as $index => $step) {
                if ($step['status'] === 'pending' && $index > 0) {
                    $workflowInfo['waiting_for'] = $step['role'];
                    $workflowInfo['next_approver'] = $step['approver'];
                    $workflowInfo['current_step'] = $index;
                    break;
                }
            }
        } elseif ($procurementStatus === 'rejected') {
            $workflowInfo['waiting_for'] = null;
            $workflowInfo['next_approver'] = null;
            $workflowInfo['current_step'] = 0;
        }

        return $workflowInfo;
    }

    /**
     * Get default workflow information
     */
    private function getDefaultWorkflowInfo(RequestModel $request): array
    {
        $employee = $request->employee;
        $amount = $request->amount;

        // Get thresholds from settings
        $autoApprovalThreshold = \App\Models\SystemSetting::get('auto_approval_threshold', 1000);
        $managerOnlyThreshold = \App\Models\SystemSetting::get('manager_only_threshold', 2000);
        $ceoThreshold = \App\Models\SystemSetting::get('ceo_approval_threshold', 5000);

        $workflowInfo = [
            'is_sequential' => false,
            'current_step' => 1,
            'total_steps' => 1,
            'steps' => [],
            'next_approver' => null,
            'waiting_for' => null
        ];

        // Auto-approval: no additional approvals needed
        if ($amount <= $autoApprovalThreshold) {
            $workflowInfo['steps'] = [
                ['role' => 'Auto-Approval', 'status' => 'completed', 'description' => 'Request auto-approved based on amount threshold']
            ];
            return $workflowInfo;
        }

        // Manager-only approval
        if ($amount <= $managerOnlyThreshold) {
            $manager = $this->getDepartmentManager($employee->department_id);
            $hasManagerApproval = $this->hasManagerApproval($request);

            $workflowInfo['steps'] = [
                [
                    'role' => 'Manager',
                    'status' => $hasManagerApproval ? 'completed' : 'pending',
                    'description' => 'Manager approval required',
                    'approver' => $manager ? $manager->full_name : 'No manager assigned'
                ]
            ];

            if (!$hasManagerApproval) {
                $workflowInfo['waiting_for'] = 'Manager';
                $workflowInfo['next_approver'] = $manager ? $manager->full_name : 'No manager assigned';
            }

            return $workflowInfo;
        }

        // Manager + CEO approval
        $manager = $this->getDepartmentManager($employee->department_id);
        $admin = $this->getAdmin();
        $hasManagerApproval = $this->hasManagerApproval($request);
        $hasAdminApproval = $this->hasAdminApproval($request);

        $workflowInfo['is_sequential'] = true;
        $workflowInfo['total_steps'] = 2;

        $steps = [
            [
                'role' => 'Manager',
                'status' => $hasManagerApproval ? 'completed' : 'pending',
                'description' => 'Manager approval required',
                'approver' => $manager ? $manager->full_name : 'No manager assigned'
            ]
        ];

        if ($amount >= $ceoThreshold) {
            $steps[] = [
                'role' => 'Admin/CEO',
                'status' => $hasAdminApproval ? 'completed' : ($hasManagerApproval ? 'pending' : 'waiting'),
                'description' => 'CEO approval required for high-value request',
                'approver' => $admin ? $admin->full_name : 'No admin assigned'
            ];
            $workflowInfo['total_steps'] = 2;
        }

        $workflowInfo['steps'] = $steps;

        // Calculate current step based on completed approvals
        $completedSteps = 0;
        if ($hasManagerApproval) $completedSteps++;
        if ($amount >= $ceoThreshold && $hasAdminApproval) $completedSteps++;

        // Show the actual completed steps, not the next step
        $workflowInfo['current_step'] = $completedSteps;

        // Determine waiting status
        if (!$hasManagerApproval) {
            $workflowInfo['waiting_for'] = 'Manager';
            $workflowInfo['next_approver'] = $manager ? $manager->full_name : 'No manager assigned';
        } elseif ($amount >= $ceoThreshold && !$hasAdminApproval) {
            $workflowInfo['waiting_for'] = 'Admin/CEO';
            $workflowInfo['next_approver'] = $admin ? $admin->full_name : 'No admin assigned';
        }

        return $workflowInfo;
    }

    /**
     * Get dynamic workflow information
     */
    private function getDynamicWorkflowInfo(RequestModel $request, $rules): array
    {
        $employee = $request->employee;
        $workflowInfo = [
            'is_sequential' => $rules->count() > 1,
            'current_step' => 1,
            'total_steps' => $rules->count(),
            'steps' => [],
            'next_approver' => null,
            'waiting_for' => null
        ];

        $steps = [];
        $completedSteps = 0;
        $waitingFor = null;
        $nextApprover = null;

        foreach ($rules as $index => $rule) {
            $approver = $this->getApproverByRole($rule->approver_role, $employee->department_id);
            $hasApproval = $this->hasApprovalFromRole($request, $rule->approver_role, $employee->department_id);

            $stepStatus = 'waiting';
            if ($hasApproval) {
                $stepStatus = 'completed';
                $completedSteps++;
            } elseif ($index === 0 || $this->hasPreviousApprovals($request, $rules, $index)) {
                $stepStatus = 'pending';
                if (!$waitingFor) {
                    $waitingFor = $rule->approver_role;
                    $nextApprover = $approver ? $approver->full_name : 'No approver assigned';
                }
            }

            $steps[] = [
                'role' => $rule->approver_role,
                'status' => $stepStatus,
                'description' => "Approval required from {$rule->approver_role}",
                'approver' => $approver ? $approver->full_name : 'No approver assigned',
                'order' => $rule->order
            ];
        }

        $workflowInfo['steps'] = $steps;

        // Show the actual completed steps, not the next step
        $workflowInfo['current_step'] = $completedSteps;

        $workflowInfo['waiting_for'] = $waitingFor;
        $workflowInfo['next_approver'] = $nextApprover;

        return $workflowInfo;
    }

    /**
     * Get procurement workflow information - only Admin/CEO approval required
     */
    private function getProcurementWorkflowInfo(RequestModel $request): array
    {
        $employee = $request->employee;
        $amount = $request->amount;

        // Get thresholds from settings
        $autoApprovalThreshold = \App\Models\SystemSetting::get('auto_approval_threshold', 1000);

        // Check if Admin approval is needed
        $needsAdminApproval = $amount > $autoApprovalThreshold;
        $hasAdminApproval = $this->hasAdminApproval($request);

        // Get Admin user for display
        $admin = $this->getApproverByRole('Admin', 0);

        if (!$needsAdminApproval) {
            // Auto-approved - no steps needed
            $steps = [];
            $currentStep = 0;
            $waitingFor = null;
        } else {
            // Admin approval required
            $stepStatus = $hasAdminApproval ? 'completed' : 'pending';
            $steps = [
                [
                    'role' => 'Admin',
                    'status' => $stepStatus,
                    'description' => 'Admin/CEO approval required for procurement request',
                    'approver' => $admin ? $admin->full_name : 'No admin assigned',
                    'order' => 1
                ]
            ];
            $currentStep = $hasAdminApproval ? 1 : 0;
            $waitingFor = $hasAdminApproval ? null : 'Admin';
        }

        return [
            'is_sequential' => false,
            'current_step' => $currentStep,
            'total_steps' => $needsAdminApproval ? 1 : 0,
            'steps' => $steps,
            'next_approver' => $needsAdminApproval && !$hasAdminApproval ? ($admin ? $admin->full_name : 'No admin assigned') : null,
            'waiting_for' => $waitingFor
        ];
    }

    /**
     * Get rejected workflow information - shows rejection status
     */
    private function getRejectedWorkflowInfo(RequestModel $request): array
    {
        // Get the rejection or cancellation details from audit logs
        $rejectionLog = \App\Models\AuditLog::where('request_id', $request->id)
            ->whereIn('action', ['Workflow Step Rejected', 'Workflow Step Cancelled'])
            ->with('user')
            ->first();

        $rejectedBy = $rejectionLog ? $rejectionLog->user->full_name : 'Unknown';
        $rejectionReason = $rejectionLog ? $rejectionLog->notes : 'No reason provided';

        // Use dynamic workflow steps if available
        $workflowSteps = \App\Models\WorkflowStep::getStepsForRequest($request);

        if (!$workflowSteps->isEmpty()) {
            // Use dynamic workflow steps
            $steps = [];
            $currentStep = 0;
            $totalSteps = $workflowSteps->count();

            foreach ($workflowSteps as $index => $step) {
                $stepStatus = $this->getStepStatus($request, $step, $index);
                $stepInfo = [
                    'id' => $step->id,
                    'name' => $step->name,
                    'role' => $this->getStepRoleName($step),
                    'status' => $stepStatus,
                    'description' => $step->description ?: $this->getStepDescription($step, $stepStatus),
                    'approver' => $this->getStepApprover($step),
                    'order' => $step->order_index,
                    'step_type' => $step->step_type,
                    'is_active' => $step->is_active,
                    'timeout_hours' => $step->timeout_hours,
                ];

                $steps[] = $stepInfo;

                // Count completed steps
                if ($stepStatus === 'completed') {
                    $currentStep = $index + 1;
                }
            }

            return [
                'is_sequential' => true,
                'current_step' => $currentStep,
                'total_steps' => $totalSteps,
                'steps' => $steps,
                'next_approver' => null,
                'waiting_for' => null,
                'workflow_type' => 'dynamic',
                'rejection_info' => [
                    'rejected_by' => $rejectedBy,
                    'rejection_reason' => $rejectionReason,
                    'rejected_at' => $rejectionLog ? $rejectionLog->created_at : null,
                    'is_cancelled' => $request->status === 'Cancelled'
                ]
            ];
        }

        // Fallback to legacy system
        $employee = $request->employee;
        $department = $employee->department;
        $amount = $request->amount;

        // Get approval rules for this department
        $rules = \App\Models\ApprovalRule::where('department_id', $department->id)
            ->where('min_amount', '<=', $amount)
            ->where('max_amount', '>=', $amount)
            ->orderBy('order')
            ->get();

        $steps = [];
        $completedSteps = 0;

        if ($rules->isEmpty()) {
            // Use default workflow steps
            $autoApprovalThreshold = \App\Models\SystemSetting::get('auto_approval_threshold', 1000);
            $managerOnlyThreshold = \App\Models\SystemSetting::get('manager_only_threshold', 2000);
            $ceoThreshold = \App\Models\SystemSetting::get('ceo_approval_threshold', 5000);

            if ($amount > $autoApprovalThreshold) {
                if ($amount <= $managerOnlyThreshold) {
                    // Manager approval only - show as rejected since request was rejected
                    $steps[] = [
                        'role' => 'Manager',
                        'status' => 'rejected',
                        'description' => 'Manager approval rejected',
                        'approver' => $rejectedBy,
                        'order' => 1
                    ];
                    $completedSteps = 0;
                } else {
                    // Manager + CEO approval - show as rejected since request was rejected
                    $steps[] = [
                        'role' => 'Manager',
                        'status' => 'rejected',
                        'description' => 'Manager approval rejected',
                        'approver' => $rejectedBy,
                        'order' => 1
                    ];

                    $steps[] = [
                        'role' => 'Admin',
                        'status' => 'rejected',
                        'description' => 'Admin/CEO approval rejected',
                        'approver' => $rejectedBy,
                        'order' => 2
                    ];

                    $completedSteps = 0;
                }
            }
        } else {
            // Use dynamic rules - show all as rejected since request was rejected
            foreach ($rules as $index => $rule) {
                $steps[] = [
                    'role' => $rule->approver_role,
                    'status' => 'rejected',
                    'description' => "{$rule->approver_role} approval rejected",
                    'approver' => $rejectedBy,
                    'order' => $rule->order
                ];
            }
            $completedSteps = 0;
        }

        return [
            'is_sequential' => $rules->count() > 1,
            'current_step' => $completedSteps,
            'total_steps' => count($steps),
            'steps' => $steps,
            'next_approver' => null,
            'waiting_for' => null,
            'workflow_type' => 'legacy',
            'rejection_info' => [
                'rejected_by' => $rejectedBy,
                'rejection_reason' => $rejectionReason,
                'rejected_at' => $rejectionLog ? $rejectionLog->created_at : null
            ]
        ];
    }

    /**
     * Check if previous approvals exist for dynamic workflow
     */
    private function hasPreviousApprovals(RequestModel $request, $rules, $currentIndex): bool
    {
        for ($i = 0; $i < $currentIndex; $i++) {
            $rule = $rules[$i];
            if (!$this->hasApprovalFromRole($request, $rule->approver_role, $request->employee->department_id)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Helper methods
     */
    private function getDepartmentManager(int $departmentId): ?\App\Models\User
    {
        return \App\Models\User::where('department_id', $departmentId)
            ->whereHas('role', function($query) {
                $query->where('name', 'manager');
            })
            ->first();
    }

    private function getAdmin(): ?\App\Models\User
    {
        return \App\Models\User::whereHas('role', function($query) {
            $query->where('name', 'admin');
        })->first();
    }

    private function getApproverByRole(string $role, int $departmentId): ?\App\Models\User
    {
        if ($role === 'Manager' || $role === 'manager') {
            return \App\Models\User::whereHas('role', function($query) {
                $query->where('name', 'manager');
            })
            ->where('department_id', $departmentId)
            ->first();
        } elseif ($role === 'Admin' || $role === 'admin') {
            return \App\Models\User::whereHas('role', function($query) {
                $query->where('name', 'admin');
            })->first();
        }
        return null;
    }

    private function hasManagerApproval(RequestModel $request): bool
    {
        return \App\Models\AuditLog::where('request_id', $request->id)
            ->where('action', 'Approved')
            ->whereHas('user', function($query) use ($request) {
                $query->whereHas('role', function($roleQuery) {
                    $roleQuery->where('name', 'manager');
                })
                ->where('department_id', $request->employee->department_id);
            })
            ->exists();
    }

    private function hasAdminApproval(RequestModel $request): bool
    {
        return \App\Models\AuditLog::where('request_id', $request->id)
            ->where('action', 'Approved')
            ->whereHas('user', function($query) {
                $query->whereHas('role', function($roleQuery) {
                    $roleQuery->where('name', 'admin');
                });
            })
            ->exists();
    }

    private function hasApprovalFromRole(RequestModel $request, string $role, int $departmentId): bool
    {
        $query = \App\Models\AuditLog::where('request_id', $request->id)
            ->where('action', 'Approved')
            ->whereHas('user', function($query) use ($role, $departmentId) {
                $query->whereHas('role', function($roleQuery) use ($role) {
                    if ($role === 'Manager' || $role === 'manager') {
                        $roleQuery->where('name', 'manager');
                    } elseif ($role === 'Admin' || $role === 'admin') {
                        $roleQuery->where('name', 'admin');
                    } else {
                        $roleQuery->where('name', $role);
                    }
                });

                if ($role === 'Manager' || $role === 'manager') {
                    $query->where('department_id', $departmentId);
                }
            });

        return $query->exists();
    }

    /**
     * Check if user can view a request
     */
    private function canViewRequest(RequestModel $request, $user): bool
    {
        // Admin can see everything
        if ($user->role->name === 'admin') {
            return true;
        }

        // Every user can see their own requests in any status
        if ($request->employee_id === $user->id) {
            return true;
        }

        // Check if user has delegation access to this request
        if ($this->hasDelegationAccess($request, $user)) {
            return true;
        }

        switch ($user->role->name) {
            case 'employee':
                return $request->employee_id === $user->id;
            case 'manager':
                // Check if this is a Finance user (has FinanceAssignment)
                $isFinanceUser = \App\Models\FinanceAssignment::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->exists();

                if ($isFinanceUser) {
                    // Finance users can view requests assigned to them via FinanceAssignment
                    if (in_array($request->status, ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered', 'Rejected'])) {
                        return $this->isRequestAssignedToUserPersonally($request, $user);
                    }
                    return false;
                } else {
                    // Regular managers can view requests from their department OR requests assigned to them in workflow steps
                    if ($request->employee->department_id === $user->department_id) {
                        return true;
                    }
                    // Check if request is assigned to them personally in workflow steps
                    if (in_array($request->status, ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered', 'Rejected'])) {
                        return $this->isRequestAssignedToUserPersonally($request, $user) ||
                               $this->isRequestAssignedToUserDepartment($request, $user);
                    }
                    return false;
                }
            case 'procurement':
                // Check if request is assigned to procurement in workflow steps
                if (in_array($request->status, [
                    'Pending Procurement Verification',
                    'Pending Approval',
                    'Approved',
                    'Pending Procurement',
                    'Ordered',
                    'Delivered',
                    'Cancelled',
                    'Rejected'
                ])) {
                    return $this->isRequestAssignedToProcurement($request, $user);
                }
                return false;
            default:
                // For other roles (like Finance), check if they are assigned to any workflow steps
                if ($this->isUserAssignedToAnyWorkflowStep($user)) {
                    // Finance users can view requests that are assigned to their department in workflow steps
                    return in_array($request->status, ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered', 'Rejected']) &&
                           $this->isRequestAssignedToUserDepartment($request, $user);
                }
                // If user is not assigned to any workflow step, only show their own requests
                return $request->employee_id === $user->id;
        }
    }

    /**
     * Check if user can approve the current request
     */
    private function canApproveRequest(RequestModel $request, $user): bool
    {
        // Get current workflow steps
        $workflowSteps = \App\Models\WorkflowStep::getStepsForRequest($request);

        // Find the first pending step (current step in sequence)
        foreach ($workflowSteps as $index => $step) {
            $stepStatus = $this->getStepStatus($request, $step, $index);

            // If this step is pending, check if user can approve it
            if ($stepStatus === 'pending') {
                return $this->canUserApproveStep($step, $user);
            }
        }

        return false;
    }

    /**
     * Check if user can approve a specific step
     */
    private function canUserApproveStep($step, $user): bool
    {
        $assignments = $step->assignments;

        if ($assignments->isEmpty()) {
            return false;
        }

        foreach ($assignments as $assignment) {
            if ($this->isUserAssignedToStep($assignment, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is assigned to a specific step assignment
     */
    private function isUserAssignedToStep($assignment, $user): bool
    {
        // If user is null, return false
        if (!$user) {
            return false;
        }

        switch ($assignment->assignable_type) {
            case 'App\\Models\\User':
                return $assignment->assignable_id == $user->id;
            case 'App\\Models\\Role':
                return $assignment->assignable_id == $user->role_id;
            case 'App\\Models\\Department':
                return $assignment->assignable_id == $user->department_id;
            case 'App\\Models\\FinanceAssignment':
                return \App\Models\FinanceAssignment::where('id', $assignment->assignable_id)
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->exists();
            default:
                return false;
        }
    }

    /**
     * Check if user has delegation access to a request
     */
    private function hasDelegationAccess(RequestModel $request, User $user): bool
    {
        // Get active delegations for this user
        $activeDelegations = \App\Models\Delegation::where('delegate_id', $user->id)
            ->where('is_active', true)
            ->where(function($delegationTimeQuery) {
                $delegationTimeQuery->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function($delegationExpiryQuery) {
                $delegationExpiryQuery->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            // Removed delegation_type filter since it's no longer used
            ->with('delegator')
            ->get();

        if ($activeDelegations->isEmpty()) {
            return false;
        }

        foreach ($activeDelegations as $delegation) {
            // Check if request is from the delegator's department
            if ($request->employee->department_id !== $delegation->delegator->department_id) {
                continue;
            }

            // Check if request was created after delegation starts
            if ($request->created_at < ($delegation->starts_at ?: $delegation->created_at)) {
                continue;
            }

            // Check if request is in the delegated workflow step
            if ($this->isRequestInDelegatedWorkflowStep($request, $delegation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if request is in the delegated workflow step
     */
    private function isRequestInDelegatedWorkflowStep(RequestModel $request, \App\Models\Delegation $delegation): bool
    {
        // Get all workflow steps for this request
        $workflowSteps = \App\Models\WorkflowStep::getStepsForRequest($request);

        // Find the delegated step
        $delegatedStep = null;
        foreach ($workflowSteps as $step) {
            if ($step->id === $delegation->workflow_step_id) {
                $delegatedStep = $step;
                break;
            }
        }

        if (!$delegatedStep) {
            return false;
        }

        // Check if the delegator would normally be assigned to this step
        $assignments = $delegatedStep->assignments;
        foreach ($assignments as $assignment) {
            if ($this->isUserAssignedToStep($assignment, $delegation->delegator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if step is assigned to user
     */
    private function isStepForUser($step, $user): bool
    {
        $assignments = $step->assignments;

        foreach ($assignments as $assignment) {
            if ($this->isUserAssignedToStep($assignment, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is assigned to any workflow step
     */
    private function isUserAssignedToAnyWorkflowStep($user): bool
    {
        return \App\Models\WorkflowStepAssignment::where(function($query) use ($user) {
            $query->where('assignable_type', 'App\\\\Models\\\\User')
                  ->where('assignable_id', $user->id)
                  ->orWhere(function($roleQuery) use ($user) {
                      $roleQuery->where('assignable_type', 'App\\\\Models\\\\Role')
                                ->where('assignable_id', $user->role_id);
                  })
                  ->orWhere(function($deptQuery) use ($user) {
                      $deptQuery->where('assignable_type', 'App\\\\Models\\\\Department')
                                ->where('assignable_id', $user->department_id);
                  });
        })->exists();
    }

    /**
     * Check if a request is assigned to user personally in workflow steps
     */
    private function isRequestAssignedToUserPersonally(RequestModel $request, $user): bool
    {
        // Check direct user assignment
        $userAssignment = \App\Models\WorkflowStepAssignment::where('assignable_type', 'App\\Models\\User')
            ->where('assignable_id', $user->id)
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->exists();

        if ($userAssignment) {
            return true;
        }

        // Check FinanceAssignment
        $financeAssignment = \App\Models\WorkflowStepAssignment::where('assignable_type', 'App\\Models\\FinanceAssignment')
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->whereExists(function($query) use ($user) {
                $query->select(DB::raw(1))
                    ->from('finance_assignments')
                    ->whereColumn('finance_assignments.id', 'workflow_step_assignments.assignable_id')
                    ->where('finance_assignments.user_id', $user->id)
                    ->where('finance_assignments.is_active', true);
            })
            ->exists();

        return $financeAssignment;
    }

    /**
     * Check if a request is assigned to procurement in workflow steps
     */
    private function isRequestAssignedToProcurement(RequestModel $request, $user): bool
    {
        // Check if user is personally assigned to any workflow step
        $personalAssignment = \App\Models\WorkflowStepAssignment::where('assignable_type', 'App\\Models\\User')
            ->where('assignable_id', $user->id)
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->exists();

        if ($personalAssignment) {
            return true;
        }

        // Check if user's department is assigned to any workflow step
        $departmentAssignment = \App\Models\WorkflowStepAssignment::where('assignable_type', 'App\\Models\\Department')
            ->where('assignable_id', $user->department_id)
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->exists();

        if ($departmentAssignment) {
            return true;
        }

        // Check if user's role is assigned to any workflow step
        $roleAssignment = \App\Models\WorkflowStepAssignment::where('assignable_type', 'App\\Models\\Role')
            ->where('assignable_id', $user->role_id)
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->exists();

        return $roleAssignment;
    }

    /**
     * Check if a request is assigned to user's department in workflow steps
     */
    private function isRequestAssignedToUserDepartment(RequestModel $request, $user): bool
    {
        // Get all workflow steps assigned to user's department
        $assignments = \App\Models\WorkflowStepAssignment::where('assignable_type', 'App\\\\Models\\\\Department')
            ->where('assignable_id', $user->department_id)
            ->whereHas('workflowStep', function($query) {
                $query->where('is_active', true);
            })
            ->get();

        if ($assignments->isEmpty()) {
            return false;
        }

        // Check if any of the assigned steps are applicable to this request
        foreach ($assignments as $assignment) {
            $step = $assignment->workflowStep;
            if (!$step) {
                continue;
            }

            // Check if step conditions are met for this request
            if ($this->isStepApplicableToRequest($step, $request)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a workflow step is applicable to a request
     */
    private function isStepApplicableToRequest($step, RequestModel $request): bool
    {
        // If no conditions, always applicable
        if (empty($step->conditions)) {
            return true;
        }

        // Check each condition
        foreach ($step->conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;

            if (!$field || !$operator) {
                continue;
            }

            $requestValue = data_get($request, $field);

            switch ($operator) {
                case '>':
                    if (!($requestValue > $value)) {
                        return false;
                    }
                    break;
                case '>=':
                    if (!($requestValue >= $value)) {
                        return false;
                    }
                    break;
                case '<':
                    if (!($requestValue < $value)) {
                        return false;
                    }
                    break;
                case '<=':
                    if (!($requestValue <= $value)) {
                        return false;
                    }
                    break;
                case '=':
                case '==':
                    if (!($requestValue == $value)) {
                        return false;
                    }
                    break;
                case '!=':
                    if (!($requestValue != $value)) {
                        return false;
                    }
                    break;
                case 'in':
                    if (!in_array($requestValue, (array)$value)) {
                        return false;
                    }
                    break;
                case 'not_in':
                    if (in_array($requestValue, (array)$value)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Check if a step has manager assignments
     */
    private function isManagerAssignmentStep($step): bool
    {
        return $step->assignments->some(function($assignment) {
            // Check if assignment is for manager role
            if ($assignment->assignable_type === 'App\\Models\\Role') {
                $role = \App\Models\Role::find($assignment->assignable_id);
                return $role && $role->name === 'manager';
            }

            // Check if assignment is for manager department
            if ($assignment->assignable_type === 'App\\Models\\Department') {
                $department = \App\Models\Department::find($assignment->assignable_id);
                return $department && $department->name === 'Management'; // Assuming management department name
            }

            // Check if assignment is for a specific manager user
            if ($assignment->assignable_type === 'App\\Models\\User') {
                $user = \App\Models\User::find($assignment->assignable_id);
                return $user && $user->role && $user->role->name === 'manager';
            }

            return false;
        });
    }

    /**
     * Get delegation information for a user acting on behalf of another user
     */
    private function getDelegationInfo(RequestModel $request, User $user): ?array
    {
        // Check if user is acting on behalf of someone for this specific request
        $delegation = \App\Models\Delegation::where('delegate_id', $user->id)
            ->where('is_active', true)
            ->where(function ($query) use ($request) {
                $query->whereNull('department_id')
                    ->orWhere('department_id', $request->employee->department_id);
            })
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            // Removed delegation_type filter since it's no longer used
            ->with('delegator')
            ->first();

        if (!$delegation) {
            return null;
        }

        return [
            'original_approver' => $delegation->delegator->full_name,
            'reason' => $delegation->reason,
            'expires_at' => $delegation->expires_at,
            'delegation_id' => $delegation->id
        ];
    }

    /**
     * Add delegation-based filtering to the query
     */
    private function addDelegationFilter($query, User $user)
    {
        // Get active delegations for this user
        $activeDelegations = \App\Models\Delegation::where('delegate_id', $user->id)
            ->where('is_active', true)
            ->where(function($delegationTimeQuery) {
                $delegationTimeQuery->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function($delegationExpiryQuery) {
                $delegationExpiryQuery->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            // Removed delegation_type filter since it's no longer used
            ->with('delegator')
            ->get();

        if ($activeDelegations->isNotEmpty()) {
            // Add requests that are delegated to this user
            $query->orWhere(function($delegationQuery) use ($user, $activeDelegations) {
                $delegationQuery->whereIn('status', ['Pending Approval', 'Approved', 'Pending Procurement', 'Ordered', 'Delivered'])
                    ->where(function($delegationSubQuery) use ($activeDelegations) {
                        foreach ($activeDelegations as $delegation) {
                            $delegationSubQuery->orWhere(function($specificDelegationQuery) use ($delegation) {
                                // Only show requests from the delegator's department
                                $specificDelegationQuery->whereHas('employee', function($empQuery) use ($delegation) {
                                    $empQuery->where('department_id', $delegation->delegator->department_id);
                                })
                                // AND that are created AFTER the delegation starts
                                ->where('created_at', '>=', $delegation->starts_at ?: $delegation->created_at)
                                // AND that are in the specific workflow step
                                ->whereExists(function($workflowQuery) use ($delegation) {
                                    $workflowQuery->select(DB::raw(1))
                                        ->from('workflow_steps')
                                        ->join('workflow_step_assignments', 'workflow_steps.id', '=', 'workflow_step_assignments.workflow_step_id')
                                        ->where('workflow_steps.id', $delegation->workflow_step_id)
                                        ->where('workflow_steps.is_active', true)
                                        ->where(function($assignmentQuery) use ($delegation) {
                                            $assignmentQuery->where(function($userQuery) use ($delegation) {
                                                $userQuery->where('workflow_step_assignments.assignable_type', 'App\\Models\\User')
                                                    ->where('workflow_step_assignments.assignable_id', $delegation->delegator_id);
                                            })
                                            ->orWhere(function($roleQuery) use ($delegation) {
                                                $roleQuery->where('workflow_step_assignments.assignable_type', 'App\\Models\\Role')
                                                    ->where('workflow_step_assignments.assignable_id', $delegation->delegator->role_id);
                                            })
                                            ->orWhere(function($departmentQuery) use ($delegation) {
                                                $departmentQuery->where('workflow_step_assignments.assignable_type', 'App\\Models\\Department')
                                                    ->where('workflow_step_assignments.assignable_id', $delegation->delegator->department_id);
                                            });
                                        });
                                });
                            });
                        }
                    });
            });
        }
    }
}
