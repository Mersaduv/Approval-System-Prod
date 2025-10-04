import React from "react";

const AuditTrailGraph = ({ auditLogs, formatDate }) => {
    if (!auditLogs || auditLogs.length === 0) {
        return (
            <div className="text-center py-8">
                <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg
                        className="w-8 h-8 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                        />
                    </svg>
                </div>
                <p className="text-sm text-gray-500">No audit logs available</p>
            </div>
        );
    }

    // Calculate progress percentage
    const totalSteps = auditLogs.filter(
        (log) => log.action === "Step Forwarded" || log.action === "Submitted"
    ).length;
    const completedSteps = auditLogs.filter(
        (log) =>
            log.action === "Workflow Step Completed" ||
            log.action === "All Approvals Complete"
    ).length;

    return (
        <div>
            <div className="relative">
                {/* Vertical Line - Centered on circles */}
                <div className="absolute left-[7px] top-2 bottom-0 w-0.5 bg-gray-200"></div>

                {auditLogs.map((log, index) => {
                    const isCompleted =
                        log.action === "Workflow Step Completed" ||
                        log.action === "All Approvals Complete";
                    const isRejected = log.action === "Workflow Step Rejected";
                    const isCancelled = log.action === "Workflow Step Cancelled";
                    const isDelayed = log.action === "Workflow Step Delayed";
                    const isForwarded = log.action === "Step Forwarded";
                    const isStarted = log.action === "Submitted";
                    const isLast = index === auditLogs.length - 1;

                    return (
                        <div
                            key={index}
                            className="relative flex items-start mb-4 last:mb-0"
                        >
                            {/* Circle - Ultra Minimal */}
                            <div className="relative z-10 flex-shrink-0">
                                <div
                                    className={`w-4 h-4 rounded-full ${
                                        isCompleted
                                            ? "bg-green-500"
                                            : isRejected
                                            ? "bg-red-500"
                                            : isCancelled
                                            ? "bg-orange-500"
                                            : isDelayed
                                            ? "bg-yellow-500"
                                            : isForwarded
                                            ? "bg-blue-500"
                                            : isStarted
                                            ? "bg-blue-500"
                                            : "bg-gray-400"
                                    }`}
                                ></div>
                            </div>

                            {/* Content - Minimal */}
                            <div className="ml-2 flex-1 min-w-0">
                                <div
                                    className={`p-3 rounded-md ${
                                        isCompleted
                                            ? "bg-green-50 border-l-2 border-green-400"
                                            : isRejected
                                            ? "bg-red-50 border-l-2 border-red-400"
                                            : isCancelled
                                            ? "bg-orange-50 border-l-2 border-orange-400"
                                            : isDelayed
                                            ? "bg-yellow-50 border-l-2 border-yellow-400"
                                            : isStarted
                                            ? "bg-blue-50 border-l-2 border-blue-400"
                                            : "bg-gray-50 border-l-2 border-gray-400"
                                    }`}
                                >
                                    <div className="flex items-center justify-between mb-1">
                                        <h4
                                            className={`text-sm font-medium ${
                                                isCompleted
                                                    ? "text-green-800"
                                                    : isRejected
                                                    ? "text-red-800"
                                                    : isCancelled
                                                    ? "text-orange-800"
                                                    : isDelayed
                                                    ? "text-yellow-800"
                                                    : isStarted
                                                    ? "text-blue-800"
                                                    : "text-gray-800"
                                            }`}
                                        >
                                            {log.action ===
                                            "Workflow Step Started"
                                                ? "Step Started"
                                                : log.action ===
                                                  "Workflow Step Completed"
                                                ? "Step Completed"
                                                : log.action ===
                                                  "Workflow Step Rejected"
                                                ? "Step Rejected"
                                                : log.action ===
                                                  "Workflow Step Cancelled"
                                                ? "Step Cancelled"
                                                : log.action ===
                                                  "Workflow Step Delayed"
                                                ? "Step Delayed"
                                                : log.action === "Submitted"
                                                ? "Request Submitted"
                                                : log.action ===
                                                  "All Approvals Complete"
                                                ? "All Steps Completed"
                                                : log.action}
                                        </h4>
                                        <span className="text-xs text-gray-500">
                                            {formatDate(log.created_at)}
                                        </span>
                                    </div>
                                    {/* Show user name (except for Step Forwarded) */}
                                    {log.action !== "Step Forwarded" && (
                                        <div className="flex items-center mb-1">
                                            <span className="text-sm text-gray-600">
                                                {log.user?.name ||
                                                    log.user?.full_name}
                                            </span>
                                        </div>
                                    )}

                                    {log.notes && (
                                        <p className="text-xs text-gray-500 mt-1">
                                            {log.notes}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Connecting Line (except for last item) */}
                            {!isLast && (
                                <div className="absolute left-1.5 top-6 w-0.5 h-2 bg-gray-200"></div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

export default AuditTrailGraph;
