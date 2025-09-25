import { Head, Link, router } from "@inertiajs/react";
import AppLayout from "../Layouts/AppLayout";
import { useState, useEffect } from "react";
import axios from "axios";
import AlertModal from "../Components/AlertModal";
import AuditTrailGraph from "../Components/AuditTrailGraph";

export default function RequestView({ auth, requestId, source = "requests", approvalToken = null }) {
    const [request, setRequest] = useState(null);
    const [loading, setLoading] = useState(true);
    const [showActionModal, setShowActionModal] = useState(false);
    const [actionType, setActionType] = useState("");
    const [actionNotes, setActionNotes] = useState("");
    const [actionData, setActionData] = useState({
        status: "",
        final_cost: "",
        notes: "",
    });
    const [billPrintingData, setBillPrintingData] = useState({
        bill_amount: "",
    });
    const [actionLoading, setActionLoading] = useState(false);
    const [auditLogs, setAuditLogs] = useState([]);
    const [verificationModal, setVerificationModal] = useState(false);
    const [verificationData, setVerificationData] = useState({
        status: "",
        final_price: "",
        notes: "",
    });
    const [showAlert, setShowAlert] = useState(false);
    const [alertMessage, setAlertMessage] = useState("");
    const [alertType, setAlertType] = useState("info");
    const [showDelayModal, setShowDelayModal] = useState(false);
    const [delayData, setDelayData] = useState({
        delay_date: "",
        delay_reason: "",
    });

    // Approval Portal Mode
    const isApprovalPortal = source === "approval" && approvalToken;

    const showAlertMessage = (message, type = "info") => {
        setAlertMessage(message);
        setAlertType(type);
        setShowAlert(true);
    };

    const formatNumber = (num) => {
        if (!num) return "0";
        const parsed = parseFloat(num);
        if (isNaN(parsed)) return "0";
        // Remove .00 if it's a whole number
        return parsed % 1 === 0 ? parsed.toString() : parsed.toFixed(2);
    };

    // Initialize billPrintingData with request amount when component mounts
    useEffect(() => {
        if (request && request.amount && !billPrintingData.bill_amount) {
            setBillPrintingData((prev) => ({
                ...prev,
                bill_amount: request.amount.toString(),
            }));
        }
    }, [request, billPrintingData.bill_amount]);

    // Helper function to check if bill amount is valid
    const isBillAmountValid = () => {
        if (!billPrintingData.bill_amount) return false;
        const amount = parseFloat(billPrintingData.bill_amount);
        return !isNaN(amount) && amount > 0;
    };

    const handlePrint = () => {
        // Check if bill is available for printing
        if (request && request.bill_number) {
            handlePrintBill();
        } else {
            // Print regular request
            window.print();
        }
    };

    const handlePrintBill = () => {
        if (!request || !request.bill_number) {
            showAlertMessage("No bill information available to print", "error");
            return;
        }

        // Create a new window for printing
        const printWindow = window.open("", "_blank");

        // Generate the bill HTML content
        const billContent = generateBillHTML(request);

        printWindow.document.write(billContent);
        printWindow.document.close();

        // Wait for content to load then print
        printWindow.onload = () => {
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        };
    };

    const generateBillHTML = (request) => {
        const currentDate = new Date().toLocaleDateString("en-US", {
            year: "numeric",
            month: "long",
            day: "numeric",
        });

        const printedDate = request.bill_printed_at
            ? new Date(request.bill_printed_at).toLocaleDateString("en-US", {
                  year: "numeric",
                  month: "long",
                  day: "numeric",
                  hour: "2-digit",
                  minute: "2-digit",
              })
            : currentDate;

        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 7); // 7 days from now
        const dueDateFormatted = dueDate.toLocaleDateString("en-US", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
        });

        return `
<!DOCTYPE html>
<html>
<head>
    <title>Invoice - ${request.bill_number}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: white;
            color: #333;
        }
        .invoice-container {
            max-width: 100%;
            width: 100%;
            margin: 0;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            font-size: 11px;
            min-height: 100vh;
            max-height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 15px 20px;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            min-height: 80px;
            flex-shrink: 0;
        }
        .company-info {
            flex: 1;
        }
        .company-logo {
            margin-bottom: 8px;
        }
        .logo-image {
            width: 200px;
            object-fit: contain;
        }
        .company-name {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 3px;
            color: white;
        }
        .company-name-ar {
            font-size: 12px;
            opacity: 0.9;
        }
        .tagline {
            font-size: 10px;
            opacity: 0.8;
            margin-top: 5px;
        }
        .invoice-banner {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            text-align: center;
            min-width: 180px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .bill-number-container {
            margin-bottom: 8px;
        }
        .bill-label {
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
            margin-bottom: 4px;
        }
        .bill-number {
            font-size: 18px;
            font-weight: bold;
            color: #60a5fa;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
            font-family: 'Courier New', monospace;
        }
        .document-type {
            font-size: 12px;
            font-weight: 500;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .invoice-details {
            display: flex;
            padding: 15px 20px;
            gap: 30px;
            background: #f8fafc;
            min-height: 120px;
            flex-shrink: 0;
        }
        .invoice-to {
            flex: 1;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .invoice-info {
            background: #f8fafc;
            padding: 10px;
            border-radius: 6px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            padding: 2px 0;
        }
        .info-label {
            font-weight: 600;
            color: #374151;
            font-size: 10px;
        }
        .info-value {
            color: #6b7280;
            font-size: 10px;
        }
        .bank-details {
            flex: 1;
        }
        .bank-info {
            background: #f0f9ff;
            padding: 10px;
            border-radius: 6px;
            border-left: 3px solid #0ea5e9;
        }
        .service-table {
            margin: 15px 20px;
            border-collapse: collapse;
            width: calc(100% - 40px);
            min-height: 80px;
            flex-shrink: 0;
        }
        .service-table th {
            background: #1e40af;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: 600;
            font-size: 10px;
        }
        .service-table td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
        }
        .service-table tr:nth-child(even) {
            background: #f9fafb;
        }
        .amount-section {
            margin: 15px 20px;
            text-align: center;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #0ea5e9;
            min-height: 60px;
            margin-top: auto;
        }
        .amount-label {
            font-size: 12px;
            color: #0c4a6e;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .amount-value {
            font-size: 24px;
            font-weight: bold;
            color: #0c4a6e;
        }
        .footer-section {
            display: flex;
            padding: 15px 20px;
            gap: 30px;
            min-height: 120px;
            margin-top: auto;
        }
        .signature-section {
            margin: 15px 20px;
            display: flex;
            justify-content: center;
            padding: 20px 0;
            border-top: 2px solid #e5e7eb;
            min-height: 150px;
            margin-top: auto;
        }
        .signature-box {
            width: 300px;
            text-align: center;
        }
        .signature-line {
            border-bottom: 2px solid #374151;
            height: 50px;
            margin-bottom: 10px;
        }
        .signature-label {
            font-size: 10px;
            color: #374151;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .signature-name {
            font-size: 12px;
            color: #1e40af;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .signature-date {
            font-size: 9px;
            color: #6b7280;
            font-style: italic;
        }
        .stamp {
            background: #0ea5e9;
            color: white;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            margin-top: 5px;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
                font-size: 10px;
                line-height: 1.2;
            }
            .invoice-container {
                box-shadow: none;
                max-width: 100%;
                width: 100%;
                margin: 0;
                padding: 0;
                min-height: 100vh;
                max-height: 100vh;
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }
            .header {
                padding: 10px 15px;
                page-break-inside: avoid;
                min-height: 60px;
                flex-shrink: 0;
            }
            .logo-image {
                width: 180px;
                object-fit: contain;
            }
            .invoice-banner {
                padding: 12px 16px;
                min-width: 160px;
            }
            .bill-number {
                font-size: 16px;
            }
            .document-type {
                font-size: 11px;
            }
            .invoice-details {
                padding: 10px 15px;
                page-break-inside: avoid;
                min-height: 80px;
                flex-shrink: 0;
            }
            .service-table {
                margin: 10px 15px;
                page-break-inside: avoid;
                min-height: 60px;
                flex-shrink: 0;
            }
            .amount-section {
                margin: 10px 15px;
                padding: 10px;
                page-break-inside: avoid;
                min-height: 40px;
                margin-top: auto;
            }
            .footer-section {
                padding: 10px 15px;
                page-break-inside: avoid;
                min-height: 80px;
                margin-top: auto;
            }
            .signature-section {
                margin: 10px 15px;
                padding: 15px 0;
                page-break-inside: avoid;
                min-height: 120px;
                margin-top: auto;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <div class="company-logo">
                    <img src="/images/logo.png" alt="Company Logo" class="logo-image" />
                </div>
                <div class="company-name">Ariyabod</div>
                <div class="tagline">Trusted Speed, Proven Quality, Future Ready!</div>
            </div>
            <div class="invoice-banner">
                <div class="bill-number-container">
                    <div class="bill-label">BILL NUMBER</div>
                    <div class="bill-number">${request.bill_number}</div>
                </div>
                <div class="document-type">Print Request</div>
            </div>
        </div>

        <!-- Invoice Details -->
        <div class="invoice-details">
            <div class="invoice-to">
                <div class="section-title">Request Details:</div>
                <div class="invoice-info">
                    <div class="info-row">
                        <span class="info-label">Request ID:</span>
                        <span class="info-value">#${request.id}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Employee:</span>
                        <span class="info-value">${
                            request.employee?.full_name || "N/A"
                        }</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Department:</span>
                        <span class="info-value">${
                            request.employee?.department?.name || "N/A"
                        }</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Created Date:</span>
                        <span class="info-value">${new Date(
                            request.created_at
                        ).toLocaleDateString("en-US", {
                            year: "numeric",
                            month: "long",
                            day: "numeric",
                        })}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Print Date:</span>
                        <span class="info-value">${printedDate}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Due Date:</span>
                        <span class="info-value">${dueDateFormatted}</span>
                    </div>
                    ${request.audit_logs ? (() => {
                        const financeApprovalLog = request.audit_logs
                            .filter(log =>
                                log.action === 'Approved' &&
                                log.notes &&
                                (log.notes.includes('finance Approval') || log.notes.includes('Finance Approval'))
                            )
                            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))[0];

                        if (financeApprovalLog && financeApprovalLog.notes) {
                            // Remove "Bill printed:" and "Step:" from the notes
                            let cleanNotes = financeApprovalLog.notes
                                .replace(/Bill printed:\s*/g, '')
                                .replace(/Step:\s*\d+/g, '')
                                .trim();

                            // Remove any extra spaces or newlines
                            cleanNotes = cleanNotes.replace(/\s+/g, ' ').trim();

                            return cleanNotes ? `
                            <div class="info-row">
                                <span class="info-label">Bill Information:</span>
                                <span class="info-value">${cleanNotes}</span>
                            </div>
                            ` : '';
                        }
                        return '';
                    })() : ''}
                </div>
            </div>
        </div>

        <!-- Request Details Table -->
        <table class="service-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Description</th>
                    <th>Request Date</th>
                    <th>Status</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>${request.item}</td>
                    <td>${request.description}</td>
                    <td>${new Date(request.created_at).toLocaleDateString(
                        "en-US",
                        {
                            year: "numeric",
                            month: "2-digit",
                            day: "2-digit",
                        }
                    )}</td>
                    <td>${request.status}</td>
                    <td>${formatNumber(request.amount)} AFN</td>
                </tr>
            </tbody>
        </table>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-label">Approved By (Signature & Stamp):</div>
                <div class="signature-name">${
                    request.bill_printed_by?.full_name || "Finance Approval Pending"
                }</div>
            </div>
        </div>

        <!-- Footer (Signature & Stamp)-->

    </div>
</body>
</html>
        `;
    };

    useEffect(() => {
        fetchRequestDetails();
    }, [requestId]);

    const fetchRequestDetails = async () => {
        try {
            setLoading(true);

            // Add debugging
            console.log("Fetching request details for ID:", requestId);
            console.log("Current user:", auth.user);
            console.log("Auth user role:", auth.user?.role?.name);

            // Try to get CSRF token first
            try {
                await axios.get("/sanctum/csrf-cookie");
                console.log("CSRF cookie set successfully");
            } catch (csrfError) {
                console.warn("CSRF cookie setting failed:", csrfError);
            }

            const response = await axios.get(`/api/requests/${requestId}`, {
                withCredentials: true,
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
            });

            console.log("API Response:", response.data);

            if (response.data.success) {
                setRequest(response.data.data);
                fetchAuditLogs();
            } else {
                throw new Error(
                    response.data.message || "Failed to fetch request details"
                );
            }
        } catch (error) {
            console.error("Error fetching request details:", error);
            console.error("Error response:", error.response);
            console.error("Error status:", error.response?.status);
            console.error("Error data:", error.response?.data);

            if (error.response?.status === 403) {
                console.error("Access denied - checking user permissions");
                console.error("User role:", auth.user?.role?.name);
                console.error("Request ID:", requestId);

                showAlertMessage(
                    "You are not authorized to view this request. Please check your permissions.",
                    "error"
                );
                // Redirect based on source
                if (source === "procurement") {
                    router.visit("/procurement");
                } else {
                    router.visit("/requests");
                }
            } else if (error.response?.status === 401) {
                showAlertMessage("Please login again", "error");
                router.visit("/login");
            } else {
                showAlertMessage(
                    "Error loading request details: " +
                        (error.response?.data?.message || error.message),
                    "error"
                );
            }
        } finally {
            setLoading(false);
        }
    };

    const fetchAuditLogs = async () => {
        try {
            const response = await axios.get(
                `/api/requests/${requestId}/audit-logs`
            );
            if (response.data.success) {
                setAuditLogs(response.data.data);
            }
        } catch (error) {
            console.error("Error fetching audit logs:", error);
        }
    };

    const handleVerification = (action) => {
        setVerificationData({
            status: action,
            final_price: "",
            notes: "",
        });
        setVerificationModal(true);
    };

    const handleVerificationSubmit = async () => {
        if (!verificationData.notes.trim()) {
            showAlertMessage(
                "Please provide notes for the verification",
                "warning"
            );
            return;
        }

        if (
            verificationData.status === "Verified" &&
            !verificationData.final_price
        ) {
            showAlertMessage(
                "Please provide final price for verification",
                "warning"
            );
            return;
        }

        try {
            setActionLoading(true);

            const response = await axios.post(
                `/api/requests/${requestId}/verify`,
                {
                status: verificationData.status,
                    final_price: verificationData.final_price
                        ? parseFloat(verificationData.final_price)
                        : null,
                    notes: verificationData.notes,
                }
            );

            if (response.data.success) {
                setVerificationModal(false);
                fetchRequestDetails(); // Refresh the request details
            } else {
                throw new Error(
                    response.data.message || "Failed to submit verification"
                );
            }
        } catch (error) {
            console.error("Error submitting verification:", error);
            showAlertMessage(
                "Error submitting verification: " +
                    (error.response?.data?.message || error.message),
                "error"
            );
        } finally {
            setActionLoading(false);
        }
    };

    // Approval Portal Actions
    const handleApprovalAction = async (action) => {
        if (!approvalToken) return;

        setActionLoading(true);
        try {
            const response = await axios.post(`/approval/${approvalToken}/process`, {
                action: action,
                notes: actionNotes,
                _token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            });

            if (response.data.success) {
                showAlertMessage(response.data.message, "success");
                setTimeout(() => {
                    window.location.href = `/approval/${approvalToken}/success`;
                }, 2000);
            } else {
                showAlertMessage(response.data.message, "error");
            }
        } catch (error) {
            showAlertMessage("An error occurred while processing your request", "error");
        } finally {
            setActionLoading(false);
        }
    };

    const handleAction = (action, request = null) => {
        setActionType(action);
        setActionNotes("");

        // Initialize action data based on action type
        if (action === "procurement") {
            let status = "Cancelled";
            if (request?.status === "Approved") {
                status = "Pending Procurement";
            } else if (request?.status === "Pending Procurement") {
                status = "Ordered";
            }

            setActionData({
                status: status,
                final_cost: "",
                notes: "",
            });
        } else if (action === "order") {
            setActionData({
                status: "Ordered",
                final_cost: "",
                notes: "",
            });
            setActionType("procurement");
        } else if (action === "cancel") {
            setActionData({
                status: "Cancelled",
                final_cost: "",
                notes: "",
            });
            setActionType("procurement"); // Change to procurement instead of reject
        } else if (action === "deliver") {
            setActionData({
                status: "Delivered",
                final_cost: "",
                notes: "",
            });
            setActionType("procurement");
        } else if (action === "rollback") {
            setActionData({
                status: "Pending Procurement",
                final_cost: "",
                notes: "",
            });
            setActionType("procurement");
        } else if (action === "reject") {
            setActionData({
                status: "Cancelled", // Set status to Cancelled for reject action
                final_cost: "",
                notes: "",
            });
        } else {
            setActionData({
                status: "",
                final_cost: "",
                notes: "",
            });
        }

        setShowActionModal(true);
    };

    const handleDelay = () => {
        // Get the latest delay data from audit logs
        let latestDelayDate = "";
        let latestDelayReason = "";

        if (request && request.audit_logs) {
            const delayedLogs = request.audit_logs.filter(log => log.action === "Delayed");
            if (delayedLogs.length > 0) {
                // Get the most recent delay log
                const latestDelay = delayedLogs[delayedLogs.length - 1];

                // Parse the delay date from notes (format: "Request delayed until YYYY-MM-DD")
                const dateMatch = latestDelay.notes.match(/Request delayed until (\d{4}-\d{2}-\d{2})/);
                if (dateMatch) {
                    latestDelayDate = dateMatch[1];
                }

                // Parse the delay reason from notes (format: "Request delayed until YYYY-MM-DD - reason")
                const reasonMatch = latestDelay.notes.match(/Request delayed until \d{4}-\d{2}-\d{2} - (.+)$/);
                if (reasonMatch) {
                    latestDelayReason = reasonMatch[1].trim();
                }
            }
        }

        setDelayData({
            delay_date: latestDelayDate,
            delay_reason: latestDelayReason,
        });
        setShowDelayModal(true);
    };

    const submitDelay = async () => {
        if (!request || !delayData.delay_date) {
            return;
        }

        try {
            setActionLoading(true);

            const response = await axios.post(`/api/requests/${request.id}/delay`, {
                delay_date: delayData.delay_date,
                delay_reason: delayData.delay_reason,
            });

            if (response.data.success) {
                setShowDelayModal(false);
                // Refresh request data
                fetchRequestDetails();
            }
        } catch (error) {
            console.error("Error delaying request:", error);
        } finally {
            setActionLoading(false);
        }
    };

    const submitAction = async () => {
        if (!request || !actionType) return;

        try {
            setActionLoading(true);
            let endpoint = "";
            let data = {};

            if (actionType === "approve") {
                // Check if this is a Finance-specific step
                const currentStep = request.approval_workflow?.steps?.find(step => step.status === 'pending');
                const isFinanceStep = currentStep?.step_category === 'finance';

                if ((auth.user?.id === 28 || request.delegation_info) && isFinanceStep) {
                    // For Finance users or delegated users in Finance-specific steps, first print bill then approve
                    endpoint = `/api/requests/${request.id}/finance-approve-with-bill`;
                    data = {
                        notes: actionNotes,
                        bill_amount: billPrintingData.bill_amount,
                    };
                } else {
                    // For all other cases, use regular approve
                    endpoint = `/api/requests/${request.id}/approve`;
                    data = { notes: actionNotes };
                }
            } else if (actionType === "reject") {
                endpoint = `/api/requests/${request.id}/reject`;
                data = { reason: actionNotes };
            } else if (actionType === "procurement") {
                // Check if this is a rollback action
                if (
                    request.status === "Cancelled" &&
                    actionData.status === "Pending Procurement"
                ) {
                    endpoint = `/api/requests/${request.id}/rollback`;
                    data = {
                        notes: actionData.notes,
                    };
                } else {
                    endpoint = `/api/requests/${request.id}/process-procurement`;
                    data = {
                        status: actionData.status,
                        final_cost: actionData.final_cost,
                        notes: actionData.notes,
                    };
                }
            } else if (actionType === "bill_printing") {
                endpoint = `/api/requests/${request.id}/bill-printing`;
                data = {
                    bill_amount: billPrintingData.bill_amount,
                };
            }

            const response = await axios.post(endpoint, data);

            if (response.data.success) {
                setShowActionModal(false);
                setActionType("");
                setActionNotes("");
                setActionData({ status: "", final_cost: "", notes: "" });
                setBillPrintingData({ bill_notes: "" });
                fetchRequestDetails(); // Refresh the request data

                // Show success message
                const successMessage = response.data.message || "Action completed successfully!";
                showAlertMessage(successMessage, "success");
            } else {
                // Show error message if success is false
                showAlertMessage(
                    response.data.message || "Action failed. Please try again.",
                    "error"
                );
            }
        } catch (error) {
            console.error("Error performing action:", error);
            showAlertMessage(
                "Error performing action: " +
                    (error.response?.data?.message || error.message),
                "error"
            );
        } finally {
            setActionLoading(false);
        }
    };





    // New simplified action detection methods
    const hasUserAlreadyApprovedOrRejected = () => {
        const user = auth.user;
        if (!user || !request || !auditLogs || !request.approval_workflow) return false;

        // Check if user has already approved or rejected ANY step they were assigned to
        const assignedSteps = request.approval_workflow.steps?.filter(step => {
            if (!step.assignments || step.assignments.length === 0) return false;

            return step.assignments.some(assignment => {
                if (assignment.assignment_type === "user") {
                    return assignment.assignable_name === user.full_name;
                } else if (assignment.assignment_type === "role") {
                    return assignment.assignable_name === user.role?.name;
                } else if (assignment.assignment_type === "department") {
                    return assignment.assignable_name === user.department?.name;
                } else if (assignment.assignment_type === "App\\Models\\FinanceAssignment" || assignment.assignment_type === "finance") {
                    // For FinanceAssignment, check if user is the finance user
                    return user.role?.name === "manager" && user.department?.name === "Finance";
                }
                return false;
            });
        }) || [];

        // Check if user has approved/rejected any of their assigned steps
        for (const step of assignedSteps) {
            const hasApprovedThisStep = auditLogs.some(
                (log) =>
                log.user_id === user.id &&
                    (log.action === "Approved" ||
                     log.action === "Rejected" ||
                     log.action === "Workflow Step Completed" ||
                     log.action === "Workflow Step Rejected") &&
                log.notes &&
                (log.notes.includes(`Step: ${step.name}`) ||
                 log.notes.includes(`${step.name} -`) ||
                 log.notes.includes(`${step.name} approved`) ||
                 log.notes.includes(`${step.name} rejected`))
            );

            if (hasApprovedThisStep) {
                return true;
            }
        }

        return false;
    };

    // Helper function to check if a workflow step is completed
    const isWorkflowStepCompleted = (stepName) => {
        if (!request?.audit_logs) return false;

        return request.audit_logs.some(log =>
            log.action === 'Workflow Step Completed' &&
            log.notes &&
            log.notes.includes(stepName)
        );
    };

    const getAvailableActions = () => {
        const user = auth.user;
        if (!user || !request) return [];

        const actions = [];

        // Check if user has already approved/rejected
        if (hasUserAlreadyApprovedOrRejected()) {
            return actions;
        }

        // Procurement Verification Actions
        if (user.role?.name === "procurement" &&
            request.status === "Pending Procurement Verification" &&
            request.procurement_status === "Pending Verification") {
            actions.push(
                { type: "verify", label: "Verify", color: "green", icon: "✓" },
                { type: "reject", label: "Reject", color: "red", icon: "✗" }
            );
        }

        // Manager/Admin Approval Actions (Non-Finance)
        if (user.role?.name === "manager" &&
            user.department?.name !== "Finance" &&
            (request.status === "Pending" || request.status === "Pending Approval") &&
            request.approval_workflow?.can_approve) {
            actions.push(
                { type: "approve", label: "Approve Request", color: "green", icon: "✓" },
                { type: "reject", label: "Reject Request", color: "red", icon: "✗" }
            );
        }

        // Admin Approval Actions
        if (user.role?.name === "admin" &&
            (request.status === "Pending" || request.status === "Pending Approval") &&
            request.approval_workflow?.can_approve) {
            actions.push(
                { type: "approve", label: "Approve Request", color: "green", icon: "✓" },
                { type: "reject", label: "Reject Request", color: "red", icon: "✗" }
            );
        }

        // Finance Manager Approval Actions - Based on step category
        if (user.role?.name === "manager" &&
            user.department?.name === "Finance" &&
            request.status === "Pending Approval" &&
            request.approval_workflow?.can_approve) {

            // Get current step category from workflow steps
            const currentStep = request.approval_workflow?.steps?.find(step => step.status === 'pending');
            const stepCategory = currentStep?.step_category;

            if (stepCategory === 'manager') {
                // Manager Approval step - only approve and reject, no delay or bill printing
                actions.push(
                    { type: "approve", label: "Approve Request", color: "green", icon: "✓" },
                    { type: "reject", label: "Reject Request", color: "red", icon: "✗" }
                );
            } else if (stepCategory === 'finance') {
                // Finance Approval step - can approve, reject, delay, and print bill
                actions.push(
                    { type: "finance-approve", label: "Approve Request", color: "green", icon: "✓" },
                    { type: "reject", label: "Reject Request", color: "red", icon: "✗" },
                    { type: "delay", label: "Delay Request", color: "yellow", icon: "⏰" },
                );
            }
        }

        // Procurement Order Actions
        if (user.role?.name === "procurement" &&
            request.approval_workflow?.waiting_for === "Procurement order" &&
            request.approval_workflow?.can_approve) {
            actions.push(
                { type: "order", label: "Order", color: "green", icon: "📦" },
                { type: "cancel", label: "Cancel", color: "red", icon: "❌" }
            );
        }

        // Procurement Actions for Ordered status
        if (user.role?.name === "procurement" && request.status === "Ordered") {
            actions.push(
                { type: "deliver", label: "Deliver", color: "purple", icon: "🚚" }
            );
        }

        // Procurement Actions for Cancelled status
        if (user.role?.name === "procurement" && request.status === "Cancelled") {
            actions.push(
                { type: "rollback", label: "Restore Request", color: "orange", icon: "🔄" }
            );
        }

        // Delegation Actions - If user has delegation access, they can perform the same actions as the original approver
        if (request.delegation_info && request.delegation_info.original_approver) {
            // Get delegation step information from delegation_info
            const delegationStep = request.delegation_info.delegation_step;

            if (delegationStep) {
                // Determine actions based on the delegated workflow step
                if (delegationStep.step_type === 'verification') {
                    // Verification actions for delegated users
                    actions.push(
                        { type: "verify", label: "Verify (Delegated)", color: "green", icon: "✓" },
                        { type: "reject", label: "Reject (Delegated)", color: "red", icon: "✗" }
                    );
                } else if (delegationStep.step_type === 'approval' && delegationStep.step_category === 'procurement') {
                    // Procurement order actions for delegated users
                    if (request.status === "Approved" || request.status === "Pending Procurement") {
                        actions.push(
                            { type: "order", label: "Order (Delegated)", color: "green", icon: "📦" },
                            { type: "cancel", label: "Cancel (Delegated)", color: "red", icon: "❌" }
                        );
                    } else if (request.status === "Ordered") {
                        actions.push(
                            { type: "deliver", label: "Deliver (Delegated)", color: "purple", icon: "🚚" }
                        );
                    } else if (request.status === "Pending Approval") {
                        // For Pending Approval status, show order actions for procurement delegation
                        actions.push(
                            { type: "order", label: "Order (Delegated)", color: "green", icon: "📦" },
                            { type: "cancel", label: "Cancel (Delegated)", color: "red", icon: "❌" }
                        );
                    }
                } else if (delegationStep.step_type === 'approval' && delegationStep.step_category === 'finance') {
                    // Finance Approval step - can approve, reject, delay, and print bill
                    // Only show actions if the Finance Approval step is not completed yet
                    if (!isWorkflowStepCompleted('Finance Approval')) {
                        actions.push(
                            { type: "approve", label: "Approve Request (Delegated)", color: "green", icon: "✓" },
                            { type: "reject", label: "Reject Request (Delegated)", color: "red", icon: "✗" },
                            { type: "delay", label: "Delay Request (Delegated)", color: "yellow", icon: "⏰" }
                        );
                    }
                } else if (delegationStep.step_type === 'approval') {
                    // Other approval steps - only approve and reject
                    // Only show actions if the delegated step is not completed yet
                    if (!isWorkflowStepCompleted(delegationStep.name)) {
                        actions.push(
                            { type: "approve", label: "Approve Request (Delegated)", color: "green", icon: "✓" },
                            { type: "reject", label: "Reject Request (Delegated)", color: "red", icon: "✗" }
                        );
                    }
                }
            } else {
                // Fallback to status-based logic if delegation step info is not available
                if (request.status === "Pending Procurement Verification") {
                    // Verification actions for delegated users
                    actions.push(
                        { type: "verify", label: "Verify (Delegated)", color: "green", icon: "✓" },
                        { type: "reject", label: "Reject (Delegated)", color: "red", icon: "✗" }
                    );
                } else if (request.status === "Approved" || request.status === "Pending Procurement") {
                    // Procurement order actions for delegated users
                    actions.push(
                        { type: "order", label: "Order (Delegated)", color: "green", icon: "📦" },
                        { type: "cancel", label: "Cancel (Delegated)", color: "red", icon: "❌" }
                    );
                } else if (request.status === "Ordered") {
                    // Delivery actions for delegated users
                    actions.push(
                        { type: "deliver", label: "Deliver (Delegated)", color: "purple", icon: "🚚" }
                    );
                } else if ((request.status === "Pending" || request.status === "Pending Approval") &&
                           request.approval_workflow?.can_approve) {
                    // Get current step category from workflow steps
                    const currentStep = request.approval_workflow?.steps?.find(step => step.status === 'pending');
                    const stepCategory = currentStep?.step_category;

                    if (stepCategory === 'finance') {
                        // Finance Approval step - can approve, reject, delay, and print bill
                        actions.push(
                            { type: "approve", label: "Approve Request (Delegated)", color: "green", icon: "✓" },
                            { type: "reject", label: "Reject Request (Delegated)", color: "red", icon: "✗" },
                            { type: "delay", label: "Delay Request (Delegated)", color: "yellow", icon: "⏰" }
                        );
                    } else {
                        // Other steps - only approve and reject
                        actions.push(
                            { type: "approve", label: "Approve Request (Delegated)", color: "green", icon: "✓" },
                            { type: "reject", label: "Reject Request (Delegated)", color: "red", icon: "✗" }
                        );
                    }
                }
            }
        }

        return actions;
    };

    const handleActionClick = (actionType) => {
        switch (actionType) {
            case "verify":
                handleVerification("Verified");
                break;
            case "reject":
                if (request.status === "Pending Procurement Verification") {
                    handleVerification("Not Available");
                } else {
                    handleAction("reject");
                }
                break;
            case "approve":
                handleAction("approve");
                break;
            case "finance-approve":
                handleAction("approve");
                break;
            case "delay":
                handleDelay();
                break;
            case "order":
                handleAction("order", request);
                break;
            case "cancel":
                handleAction("cancel", request);
                break;
            case "deliver":
                handleAction("deliver", request);
                break;
            case "rollback":
                handleAction("rollback", request);
                break;
            default:
                console.warn("Unknown action type:", actionType);
        }
    };


    const canViewRequest = () => {
        const user = auth.user;
        if (!user || !request) return false;

        // Admin can see everything
        if (user.role?.name === "admin") return true;

        // Every user can see their own requests in any status
        if (request.employee_id === user.id) {
            return true;
        }

        // Manager can see requests from their department OR requests assigned to them in workflow steps
        if (user.role?.name === "manager") {
            // Check if request is from their department
            if (request.employee?.department_id === user.department_id) {
                return true;
            }

            // Check if request is assigned to them personally in workflow steps
            // This will be handled by the backend API, so we'll allow it for now
            // and let the backend determine if the user can view it
            return [
                "Pending Approval",
                "Approved",
                "Pending Procurement",
                "Ordered",
                "Delivered",
                "Cancelled",
                "Rejected",
            ].includes(request.status);
        }

        // Procurement can see requests assigned to them in workflow steps
        if (user.role?.name === "procurement") {
            return [
                "Pending Procurement Verification",
                "Pending Approval",
                "Approved",
                "Pending Procurement",
                "Ordered",
                "Delivered",
                "Cancelled",
                "Rejected",
            ].includes(request.status);
        }

        // For other roles, check if they are assigned to workflow steps
        // This will be handled by the backend API
        return [
            "Pending Procurement Verification",
            "Pending Approval",
            "Approved",
            "Pending Procurement",
            "Ordered",
            "Delivered",
            "Cancelled",
            "Rejected",
        ].includes(request.status);
    };

    const getStatusColor = (status, request = null) => {
        // Check if request is delayed
        if (request && isRequestDelayed(request)) {
            return "bg-orange-100 text-orange-800";
        }

        // If status is Approved but no procurement record exists, use Pending Procurement color
        if (status === "Approved" && request && !request.procurement) {
            return "bg-blue-100 text-blue-800";
        }

        switch (status.toLowerCase()) {
            case "pending":
                return "bg-yellow-100 text-yellow-800";
            case "approved":
                return "bg-green-100 text-green-800";
            case "rejected":
                return "bg-red-100 text-red-800";
            case "pending procurement verification":
                return "bg-orange-100 text-orange-800";
            case "pending approval":
                return "bg-blue-100 text-blue-800";
            case "pending procurement":
                return "bg-blue-100 text-blue-800";
            case "ordered":
                return "bg-purple-100 text-purple-800";
            case "delivered":
                return "bg-green-100 text-green-800";
            case "cancelled":
                return "bg-orange-100 text-orange-800";
            default:
                return "bg-gray-100 text-gray-800";
        }
    };

    const isRequestDelayed = (request) => {
        if (!request || !request.audit_logs) return false;

        // Check if there's a "Delayed" action in audit logs
        const hasDelayedAction = request.audit_logs.some(log => log.action === "Delayed");

        if (!hasDelayedAction) return false;

        // Check if Finance Approval step has been completed after the delay
        // If Finance Approval step is approved, rejected, or completed, the request is no longer delayed
        const financeApprovalCompleted = request.audit_logs.some(log =>
            (log.action === "Step completed" || log.action === "Approved" || log.action === "Rejected" || log.action === "Workflow Step Rejected") &&
            log.notes &&
            log.notes.includes("Finance Approval")
        );

        // If Finance Approval step is completed, the request is no longer delayed
        return !financeApprovalCompleted;
    };

    const getStatusDisplayText = (status, approvalWorkflow, request = null) => {
        // Check if request is cancelled
        if (status === "Cancelled") {
            return "Cancelled";
        }

        // Check if request is delayed
        if (request && isRequestDelayed(request)) {
            return "Delayed (Waiting for Finance Approval)";
        }

        // If we have workflow information, use it to determine the display text
        if (approvalWorkflow?.waiting_for && status !== "Cancelled") {
            return `Pending (Waiting for ${approvalWorkflow.waiting_for})`;
        }

        // Fallback to status-based display
        if (status === "Pending" && approvalWorkflow?.waiting_for) {
            return `Pending (Waiting for ${approvalWorkflow.waiting_for})`;
        }
        if (status === "Pending Procurement Verification") {
            return "Pending Procurement";
        }
        if (status === "Pending Approval") {
            return "Pending Approval";
        }
        // If status is Approved but no procurement record exists, show as Pending Procurement
        if (status === "Approved" && request && !request.procurement) {
            return "Pending Procurement";
        }
        return status;
    };

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleString();
    };

    if (loading) {
        return (
            <AppLayout title="Request Details" auth={auth}>
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                </div>
            </AppLayout>
        );
    }

    if (!request) {
        return (
            <AppLayout title="Request Not Found" auth={auth}>
                <div className="text-center py-12">
                    <div className="text-gray-400 text-6xl mb-4">❌</div>
                    <h3 className="text-lg font-medium text-gray-900 mb-2">
                        Request not found
                    </h3>
                    <p className="text-gray-500 mb-4">
                        The request you're looking for doesn't exist or you
                        don't have permission to view it.
                    </p>
                    <Link
                        href={
                            auth.user?.role?.name === "procurement"
                                ? "/procurement"
                                : "/requests"
                        }
                        className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <svg
                            className="w-4 h-4 mr-2"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M15 19l-7-7 7-7"
                            />
                        </svg>
                        Back
                    </Link>
                </div>
            </AppLayout>
        );
    }

    // Check if user can view this request
    if (!canViewRequest()) {
        return (
            <AppLayout title="Access Denied" auth={auth}>
                <div className="text-center py-12">
                    <div className="text-gray-400 text-6xl mb-4">🚫</div>
                    <h3 className="text-lg font-medium text-gray-900 mb-2">
                        Access Denied
                    </h3>
                    <p className="text-gray-500 mb-4">
                        You don't have permission to view this request.
                    </p>
                    <Link
                        href={
                            source === "procurement"
                                ? "/procurement"
                                : "/requests"
                        }
                        className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <svg
                            className="w-4 h-4 mr-2"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M15 19l-7-7 7-7"
                            />
                        </svg>
                        Back
                    </Link>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title={`Request #${request.id}`} auth={auth}>
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <Link
                            href={
                                source === "procurement"
                                    ? "/procurement"
                                    : "/requests"
                            }
                            className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <svg
                                className="w-4 h-4 mr-2"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                            Back
                        </Link>
                        <div>
                            <div className="flex items-center space-x-3">
                                <h1 className="text-2xl font-bold text-gray-900">
                                    Request #{request.id}
                                </h1>
                                {source === "procurement" && (
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        Procurement View
                                    </span>
                                )}
                            </div>
                            <p className="text-gray-600">
                                Submitted by {request.employee?.full_name}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center space-x-3">
                        <span
                            className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getStatusColor(
                                request.status,
                                request
                            )}`}
                        >
                            {getStatusDisplayText(
                                request.status,
                                request.approval_workflow,
                                request
                            )}
                        </span>
                    </div>
                </div>

                {/* Request Details */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Information */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Basic Details */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Request Details
                            </h3>
                            <dl className="grid grid-cols-1 gap-4">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">
                                        Item
                                    </dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {request.item}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">
                                        Description
                                    </dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {request.description}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">
                                        Amount
                                    </dt>
                                     <dd className="mt-1 text-sm text-gray-900 font-semibold">
                                         {formatNumber(request.amount)} AFN
                                     </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">
                                        Status
                                    </dt>
                                    <dd className="mt-1">
                                        <span
                                            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(
                                                request.status,
                                                request
                                            )}`}
                                        >
                                            {getStatusDisplayText(
                                                request.status,
                                                request.approval_workflow,
                                                request
                                            )}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">
                                        Created
                                    </dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {formatDate(request.created_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">
                                        Last Updated
                                    </dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {formatDate(request.updated_at)}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        {/* Delegation Information */}
                        {request.delegation_info && (
                            <div className="bg-blue-50 border border-blue-200 shadow-sm rounded-lg p-6">
                                <h3 className="text-lg font-medium text-blue-900 mb-4 flex items-center">
                                    <span className="mr-2">🔄</span>
                                    Delegation Information
                                </h3>
                                <div className="bg-blue-100 p-4 rounded-lg">
                                    <p className="text-sm text-blue-800">
                                        <strong>Acting on behalf of:</strong> {request.delegation_info.original_approver}
                                    </p>
                                    <p className="text-sm text-blue-800 mt-1">
                                        <strong>Delegation reason:</strong> {request.delegation_info.reason}
                                    </p>
                                    <p className="text-sm text-blue-800 mt-1">
                                        <strong>Valid until:</strong> {formatDate(request.delegation_info.expires_at)}
                                    </p>
                                </div>
                            </div>
                        )}

                        {/* Employee Information */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Employee Information
                            </h3>
                            <dl className="grid grid-cols-1 gap-4">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">
                                        Name
                                    </dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {request.employee?.full_name}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">
                                        Email
                                    </dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {request.employee?.email}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">
                                        Department
                                    </dt>
                                    <dd className="mt-1 text-sm text-gray-900">
                                        {request.employee?.department?.name}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        {/* Procurement Information */}
                        {auth.user?.role?.name === "procurement" &&
                            request.procurement && (
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                    <h3 className="text-lg font-medium text-gray-900 mb-4">
                                        Procurement Information
                                    </h3>
                                <dl className="grid grid-cols-1 gap-4">
                                    <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Procurement Status
                                            </dt>
                                        <dd className="mt-1">
                                                <span
                                                    className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(
                                                        request.procurement
                                                            .status
                                                    )}`}
                                                >
                                                {request.procurement.status}
                                            </span>
                                        </dd>
                                    </div>
                                    {request.procurement.final_cost && (
                                        <div>
                                                <dt className="text-sm font-medium text-gray-500">
                                                    Final Cost
                                                </dt>
                                                 <dd className="mt-1 text-sm text-gray-900 font-semibold">
                                                     {formatNumber(
                                                         request.procurement
                                                             .final_cost
                                                     )} AFN
                                                 </dd>
                                        </div>
                                    )}
                                    <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Procurement Started
                                            </dt>
                                            <dd className="mt-1 text-sm text-gray-900">
                                                {formatDate(
                                                    request.procurement
                                                        .created_at
                                                )}
                                            </dd>
                                    </div>
                                    <div>
                                            <dt className="text-sm font-medium text-gray-500">
                                                Last Updated
                                            </dt>
                                            <dd className="mt-1 text-sm text-gray-900">
                                                {formatDate(
                                                    request.procurement
                                                        .updated_at
                                                )}
                                            </dd>
                                    </div>
                                </dl>
                            </div>
                        )}

                        {/* Audit Trail - Graph Style */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-6">
                                Audit Trail
                            </h3>
                            <AuditTrailGraph auditLogs={auditLogs} formatDate={formatDate} />
                        </div>
                    </div>

                    {/* Actions Sidebar */}
                    <div className="space-y-6">
                        {/* Approval Workflow Status */}
                        {request.approval_workflow && (
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    Approval Workflow
                                </h3>
                                <div className="space-y-4">
                                    {/* Workflow Progress */}
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-gray-600">
                                            Progress
                                        </span>
                                        <span className="font-medium">
                                            {
                                                request.approval_workflow
                                                    .current_step
                                            }{" "}
                                            of{" "}
                                            {
                                                request.approval_workflow
                                                    .total_steps
                                            }
                                        </span>
                                    </div>

                                    {/* Progress Bar */}
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            className={`h-2 rounded-full transition-all duration-300 ${
                                                request.approval_workflow
                                                    .current_step ===
                                                request.approval_workflow
                                                    .total_steps
                                                    ? "bg-green-600"
                                                    : "bg-blue-600"
                                            }`}
                                            style={{
                                                width: `${
                                                    (request.approval_workflow
                                                        .current_step /
                                                        request
                                                            .approval_workflow
                                                            .total_steps) *
                                                    100
                                                }%`,
                                            }}
                                        ></div>
                                    </div>

                                    {/* Workflow Steps */}
                                    <div className="space-y-3">
                                        {request.approval_workflow.steps.map(
                                            (step, index) => (
                                                <div
                                                    key={step.id || index}
                                                    className="flex items-center space-x-3"
                                                >
                                                    <div
                                                        className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium ${
                                                            step.status ===
                                                            "completed"
                                                                ? "bg-green-100 text-green-800"
                                                                : step.status ===
                                                                  "pending"
                                                                ? "bg-blue-100 text-blue-800"
                                                                : step.status ===
                                                                  "rejected"
                                                                ? "bg-red-100 text-red-800"
                                                                : step.status ===
                                                                  "cancelled"
                                                                ? "bg-orange-100 text-orange-800"
                                                                : "bg-gray-100 text-gray-500"
                                                        }`}
                                                    >
                                                        {step.status ===
                                                        "completed"
                                                            ? "✓"
                                                            : step.status ===
                                                              "rejected"
                                                            ? "✗"
                                                            : step.status ===
                                                              "cancelled"
                                                            ? "✕"
                                                            : step.order !==
                                                              undefined
                                                            ? step.order + 1
                                                            : index + 1}
                                                </div>
                                                <div className="flex-1">
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-2">
                                                                <span
                                                                    className={`text-sm font-medium ${
                                                                        step.status ===
                                                                        "completed"
                                                                            ? "text-green-800"
                                                                            : step.status ===
                                                                              "pending"
                                                                            ? "text-blue-800"
                                                                            : step.status ===
                                                                              "rejected"
                                                                            ? "text-red-800"
                                                                            : step.status ===
                                                                              "cancelled"
                                                                            ? "text-orange-800"
                                                                            : "text-gray-500"
                                                                    }`}
                                                                >
                                                                    {step.name ||
                                                                        step.role}
                                                            </span>
                                                            {step.step_type && (
                                                                    <span
                                                                        className={`text-xs px-2 py-1 rounded-full ${
                                                                            step.step_type ===
                                                                            "approval"
                                                                                ? "bg-blue-100 text-blue-800"
                                                                                : step.step_type ===
                                                                                  "verification"
                                                                                ? "bg-yellow-100 text-yellow-800"
                                                                                : step.step_type ===
                                                                                  "notification"
                                                                                ? "bg-purple-100 text-purple-800"
                                                                                : "bg-gray-100 text-gray-600"
                                                                        }`}
                                                                    >
                                                                        {step.step_type ===
                                                                        "approval"
                                                                            ? "Approval"
                                                                            : step.step_type ===
                                                                              "verification"
                                                                            ? "Verification"
                                                                            : step.step_type ===
                                                                              "notification"
                                                                            ? "Notification"
                                                                            : step.step_type}
                                                                </span>
                                                            )}
                                                        </div>
                                                            <span
                                                                className={`text-xs px-2 py-1 rounded-full ${
                                                                    step.status ===
                                                                    "completed"
                                                                        ? "bg-green-100 text-green-800"
                                                                        : step.status ===
                                                                          "pending"
                                                                        ? "bg-blue-100 text-blue-800"
                                                                        : step.status ===
                                                                          "rejected"
                                                                        ? "bg-red-100 text-red-800"
                                                                        : step.status ===
                                                                          "cancelled"
                                                                        ? "bg-orange-100 text-orange-800"
                                                                        : "bg-gray-100 text-gray-500"
                                                                }`}
                                                            >
                                                                {step.status ===
                                                                "completed"
                                                                    ? "Completed"
                                                                    : step.status ===
                                                                      "pending"
                                                                    ? "Pending"
                                                                    : step.status ===
                                                                      "rejected"
                                                                    ? "Rejected"
                                                                    : step.status ===
                                                                      "cancelled"
                                                                    ? "Cancelled"
                                                                    : "Waiting"}
                                                        </span>
                                                    </div>
                                                        <p className="text-xs text-gray-500 mt-1">
                                                            {step.description}
                                                        </p>
                                                    <div className="flex items-center gap-4 mt-1">
                                                            {step.approver &&
                                                                step.approver !==
                                                                    "Not assigned" && (
                                                                    <p className="text-xs text-gray-400">
                                                                        Approver:{" "}
                                                                        {
                                                                            step.approver
                                                                        }
                                                                    </p>
                                                        )}
                                                        {step.timeout_hours && (
                                                                <p className="text-xs text-gray-400">
                                                                    Timeout:{" "}
                                                                    {
                                                                        step.timeout_hours
                                                                    }
                                                                    h
                                                                </p>
                                                        )}
                                                        {/* {step.assignments && step.assignments.length > 0 && (
                                                            <div className="flex flex-wrap gap-1">
                                                                {step.assignments.map((assignment, idx) => (
                                                                    <span
                                                                        key={idx}
                                                                        className={`text-xs px-2 py-1 rounded-full ${
                                                                            assignment.is_required
                                                                                ? 'bg-red-100 text-red-700 border border-red-200'
                                                                                : 'bg-gray-100 text-gray-600'
                                                                        }`}
                                                                    >
                                                                        {assignment.assignable_name}
                                                                        {assignment.is_required && (
                                                                            <span className="ml-1 font-semibold">*</span>
                                                                        )}
                                                                    </span>
                                                                ))}
                                                            </div>
                                                        )} */}
                                                    </div>
                                                </div>
                                            </div>
                                            )
                                        )}
                                    </div>

                                    {/* Waiting Status */}
                                    {request.approval_workflow.waiting_for && (
                                        <div
                                            className={`border rounded-lg p-4 ${
                                                request.approval_workflow
                                                    .can_approve
                                                    ? "bg-green-50 border-green-200"
                                                    : "bg-yellow-50 border-yellow-200"
                                            }`}
                                        >
                                            <div className="flex items-center">
                                                <div className="flex-shrink-0">
                                                    <svg
                                                        className={`h-5 w-5 ${
                                                            request
                                                                .approval_workflow
                                                                .can_approve
                                                                ? "text-green-400"
                                                                : "text-yellow-400"
                                                        }`}
                                                        fill="currentColor"
                                                        viewBox="0 0 20 20"
                                                    >
                                                        {request
                                                            .approval_workflow
                                                            .can_approve ? (
                                                            <path
                                                                fillRule="evenodd"
                                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                                clipRule="evenodd"
                                                            />
                                                        ) : (
                                                            <path
                                                                fillRule="evenodd"
                                                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                                clipRule="evenodd"
                                                            />
                                                        )}
                                                    </svg>
                                                </div>
                                                <div className="ml-3">
                                                    <p
                                                        className={`text-sm font-medium ${
                                                            request
                                                                .approval_workflow
                                                                .can_approve
                                                                ? "text-green-800"
                                                                : "text-yellow-800"
                                                        }`}
                                                    >
                                                        {request
                                                            .approval_workflow
                                                            .can_approve_message ||
                                                         `⏳ Waiting for ${request.approval_workflow.waiting_for} approval`}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Rejection Info */}
                                    {request.approval_workflow
                                        .rejection_info && (
                                        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                                            <div className="flex items-center">
                                                <div className="flex-shrink-0">
                                                    <svg
                                                        className="h-5 w-5 text-red-400"
                                                        fill="currentColor"
                                                        viewBox="0 0 20 20"
                                                    >
                                                        <path
                                                            fillRule="evenodd"
                                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                            clipRule="evenodd"
                                                        />
                                                    </svg>
                                                </div>
                                                <div className="ml-3">
                                                    <p className={`text-sm font-medium ${request.status === "Cancelled" ? "text-orange-800" : "text-red-800"}`}>
                                                        {request.status === "Cancelled" ? "❌ Request cancelled by" : "❌ Request rejected by"}{" "}
                                                        {
                                                            request
                                                                .approval_workflow
                                                                .rejection_info
                                                                .rejected_by
                                                        }
                                                    </p>
                                                    <p className={`text-sm mt-1 ${request.status === "Cancelled" ? "text-orange-700" : "text-red-700"}`}>
                                                        {request.status === "Cancelled" ? "Reason:" : "Reason:"}{" "}
                                                        {
                                                            request
                                                                .approval_workflow
                                                                .rejection_info
                                                                .rejection_reason
                                                        }
                                                    </p>
                                                    {request.approval_workflow
                                                        .rejection_info
                                                        .rejected_at && (
                                                        <p className={`text-xs mt-1 ${request.status === "Cancelled" ? "text-orange-600" : "text-red-600"}`}>
                                                            {request.status === "Cancelled" ? "Cancelled at:" : "Rejected at:"}{" "}
                                                            {new Date(
                                                                request.approval_workflow.rejection_info.rejected_at
                                                            ).toLocaleString()}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

        {/* Approval Portal Actions */}
        {isApprovalPortal && request.status === "Pending Approval" && (
            <div className="bg-white shadow-sm rounded-lg p-6 mb-6">
                <div className="border-l-4 border-blue-500 bg-blue-50 p-4 mb-6">
                    <div className="flex">
                        <div className="flex-shrink-0">
                            <svg className="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <div className="ml-3">
                            <h3 className="text-sm font-medium text-blue-800">
                                Approval Required
                            </h3>
                            <div className="mt-2 text-sm text-blue-700">
                                <p>Your approval is required for this request. Please review the details and take action.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="mb-6">
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Notes (Optional)
                    </label>
                    <textarea
                        value={actionNotes}
                        onChange={(e) => setActionNotes(e.target.value)}
                        rows={3}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Add any notes or comments about this request..."
                    />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button
                        onClick={() => handleApprovalAction('approve')}
                        disabled={actionLoading}
                        className="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200 flex items-center justify-center shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        {actionLoading ? 'Processing...' : 'Approve Request'}
                    </button>

                    <button
                        onClick={() => handleApprovalAction('reject')}
                        disabled={actionLoading}
                        className="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200 flex items-center justify-center shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        {actionLoading ? 'Processing...' : 'Reject Request'}
                    </button>

                    <button
                        onClick={() => handleApprovalAction('forward')}
                        disabled={actionLoading}
                        className="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200 flex items-center justify-center shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                        {actionLoading ? 'Processing...' : 'Forward Request'}
                    </button>
                </div>

                <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                    <div className="flex">
                        <svg className="w-5 h-5 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                        </svg>
                        <div className="text-sm text-yellow-800">
                            <strong>Important:</strong> This action cannot be undone. The request will be processed immediately.
                        </div>
                    </div>
                </div>
            </div>
        )}

        {/* New Simplified Actions */}
        {[
            "Pending",
            "Pending Approval",
            "Pending Procurement Verification",
            "Ordered",
            "Cancelled",
        ].includes(request.status) && !isApprovalPortal && (
                            <div className="bg-white shadow-sm rounded-lg p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    Actions
                                </h3>

                                {(() => {
                                    const availableActions = getAvailableActions();

                                    if (availableActions.length === 0) {
                                        // Show appropriate message based on status
                                        if (hasUserAlreadyApprovedOrRejected()) {
                                            return (
                                                <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                                                    <div className="flex items-center">
                                                        <div className="flex-shrink-0">
                                                            <svg className="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                                            </svg>
                                                        </div>
                                                        <div className="ml-3">
                                                            <p className="text-sm font-medium text-green-800">
                                                                ✅ You have already approved this request
                                                            </p>
                                                            <p className="text-sm text-green-700 mt-1">
                                                                Waiting for other approvers to complete the workflow
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        } else {
                                            return (
                                                <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                                    <div className="flex items-center">
                                                        <div className="flex-shrink-0">
                                                            <svg className="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                                            </svg>
                                                        </div>
                                                        <div className="ml-3">
                                                            <p className="text-sm font-medium text-gray-800">
                                                                Waiting for your turn to approve
                                                            </p>
                                                            <p className="text-sm text-gray-700 mt-1">
                                                                {request.approval_workflow?.waiting_for
                                                                    ? `This request is currently waiting for ${request.approval_workflow.waiting_for} approval`
                                                                    : "This request is waiting for previous approvers in the workflow to complete their review"}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        }
                                    }

                                    return (
                                        <div className="space-y-3">
                                            {availableActions.map((action, index) => (
                                                <button
                                                    key={index}
                                                    onClick={() => handleActionClick(action.type)}
                                                    className={`w-full px-4 py-2 rounded-md font-medium text-center flex items-center justify-center gap-2 ${
                                                        action.color === 'green'
                                                            ? 'bg-green-600 hover:bg-green-700 text-white'
                                                            : action.color === 'red'
                                                            ? 'bg-red-600 hover:bg-red-700 text-white'
                                                            : action.color === 'yellow'
                                                            ? 'border-2 border-yellow-600 hover:bg-yellow-50 text-gray-600'
                                                            : 'bg-blue-600 hover:bg-blue-700 text-white'
                                                    }`}
                                                >
                                                    <span>{action.icon}</span>
                                                    <span>{action.label}</span>
                                                </button>
                                            ))}
                                        </div>
                                    );
                                })()}

                                {request.bill_number && (
                                    <div className="space-y-3 mt-4">
                                        <button
                                            onClick={handlePrint}
                                            className="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-md font-medium text-lg flex items-center justify-center gap-2"
                                            title="Print professional bill with all details"
                                        >
                                            🖨️ Print Request
                                        </button>
                                    </div>
                                )}
                            </div>
                        )}




                        {/* Quick Actions */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Quick Actions
                            </h3>
                            <div className="space-y-2">
                                <button
                                    onClick={() => {
                                        const url = window.location.href;
                                        navigator.clipboard.writeText(url);
                                        // URL copied to clipboard - no need to show message
                                    }}
                                    className="w-full text-left text-sm text-blue-600 hover:text-blue-800"
                                >
                                    📋 Copy Link
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Action Modal */}
                {showActionModal && (
                    <div className="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
                        <div className="relative w-full max-w-2xl bg-white rounded-lg shadow-xl">
                            <div className="p-6">
                                <div className="flex items-center justify-center w-12 h-12 mx-auto bg-blue-100 rounded-full mb-4">
                                    <svg
                                        className="w-6 h-6 text-blue-600"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>
                                <div className="text-center mb-6">
                                    <h3 className="text-lg font-medium text-gray-900">
                                        {actionType === "approve"
                                            ? "Approve Request"
                                            : actionType === "reject"
                                            ? "Reject Request"
                                            : actionType === "bill_printing"
                                            ? "Finance (Bill Printing)"
                                            : actionType === "procurement"
                                            ? request.status === "Cancelled" &&
                                              actionData.status ===
                                                  "Pending Procurement"
                                                ? "Restore Request"
                                                : actionData.status ===
                                                  "Pending Procurement"
                                                ? "Start Procurement Process"
                                                : actionData.status ===
                                                  "Ordered"
                                                ? "Mark as Ordered"
                                                : actionData.status ===
                                                  "Delivered"
                                                ? "Mark as Delivered"
                                                : actionData.status ===
                                                  "Cancelled"
                                                ? "Cancel Request"
                                                : "Procurement Action"
                                            : "Action"}
                                    </h3>
                                </div>
                                <div className="space-y-4">
                                    {actionType === "procurement" ? (
                                            <div className="space-y-4">
                                                <p className="text-sm text-gray-500 text-center">
                                                {request.status ===
                                                    "Cancelled" &&
                                                actionData.status ===
                                                    "Pending Procurement"
                                                    ? "Restore this cancelled request to pending procurement status"
                                                    : actionData.status ===
                                                      "Pending Procurement"
                                                    ? "Start procurement process for this request"
                                                    : actionData.status ===
                                                      "Ordered"
                                                    ? "Mark this request as ordered"
                                                    : actionData.status ===
                                                      "Delivered"
                                                    ? "Mark this request as delivered"
                                                    : actionData.status ===
                                                      "Cancelled"
                                                    ? "Cancel this request"
                                                    : "Process procurement action"}
                                                </p>
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                                        Notes
                                                    </label>
                                                    <textarea
                                                        value={actionData.notes}
                                                    onChange={(e) =>
                                                        setActionData(
                                                            (prev) => ({
                                                                ...prev,
                                                                notes: e.target
                                                                    .value,
                                                            })
                                                        )
                                                    }
                                                        rows={3}
                                                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                        placeholder="Add any notes about this action..."
                                                    />
                                                </div>
                                            </div>
                                    ) : actionType === "bill_printing" ? (
                                        <div className="space-y-4">
                                            <p className="text-sm text-gray-500 text-center">
                                                Process bill printing for this
                                                request. Fill in the bill
                                                details below.
                                            </p>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                                    Bill Amount
                                                </label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={
                                                        billPrintingData.bill_amount ||
                                                        request.amount
                                                    }
                                                    onChange={(e) =>
                                                        setBillPrintingData(
                                                            (prev) => ({
                                                                ...prev,
                                                                bill_amount:
                                                                    e.target
                                                                        .value,
                                                            })
                                                        )
                                                    }
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                    placeholder="Enter bill amount"
                                                />
                                                         <p className="text-xs text-gray-500 mt-1">
                                                             Default: {formatNumber(
                                                                 request.amount
                                                             )} AFN (from request)
                                                         </p>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                                    Bill Notes
                                                </label>
                                                <textarea
                                                    value={
                                                        billPrintingData.bill_notes
                                                    }
                                                    onChange={(e) =>
                                                        setBillPrintingData(
                                                            (prev) => ({
                                                                ...prev,
                                                                bill_notes:
                                                                    e.target
                                                                        .value,
                                                            })
                                                        )
                                                    }
                                                    rows={3}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                    placeholder="Add any notes about the bill..."
                                                />
                                            </div>
                                        </div>
                                    ) : actionType === "approve" &&
                                      (auth.user?.id === 28 || request.delegation_info) &&
                                      request.approval_workflow?.steps?.find(step => step.status === 'pending')?.step_category === 'finance' ? (
                                        <div className="space-y-4">
                                            <p className="text-sm text-gray-500 text-center">
                                                Finance Approval with Bill
                                                Printing. Please fill in the
                                                bill details below before
                                                approving.
                                            </p>

                                            {/* Bill Printing Section */}
                                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                                <h4 className="text-sm font-medium text-blue-900 mb-3">
                                                    Bill Printing Information
                                                </h4>
                                                <div className="space-y-3">
                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                                            Bill Amount
                                                        </label>
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            value={
                                                                billPrintingData.bill_amount ||
                                                                request.amount
                                                            }
                                                            onChange={(e) =>
                                                                setBillPrintingData(
                                                                    (prev) => ({
                                                                        ...prev,
                                                                        bill_amount:
                                                                            e
                                                                                .target
                                                                                .value,
                                                                    })
                                                                )
                                                            }
                                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                            placeholder="Enter bill amount"
                                                        />
                                                         <p className="text-xs text-gray-500 mt-1">
                                                             Default: {formatNumber(
                                                                 request.amount
                                                             )} AFN (from request)
                                                         </p>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Approval Notes */}
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                                    Approval Notes (optional)
                                                </label>
                                                <textarea
                                                    value={actionNotes}
                                                    onChange={(e) =>
                                                        setActionNotes(
                                                            e.target.value
                                                        )
                                                    }
                                                    rows={3}
                                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                    placeholder="Add any approval notes..."
                                                />
                                            </div>
                                        </div>
                                        ) : (
                                            <>
                                                <p className="text-sm text-gray-500 text-center">
                                                {actionType === "approve"
                                                    ? "Are you sure you want to approve this request?"
                                                    : "Are you sure you want to reject this request?"}
                                                </p>
                                                <div className="mt-4">
                                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                                    {actionType === "approve"
                                                        ? "Notes (optional)"
                                                        : "Reason (required)"}
                                                    </label>
                                                    <textarea
                                                        value={actionNotes}
                                                    onChange={(e) =>
                                                        setActionNotes(
                                                            e.target.value
                                                        )
                                                    }
                                                        rows={3}
                                                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                    placeholder={
                                                        actionType === "approve"
                                                            ? "Add any notes..."
                                                            : "Please provide a reason for rejection..."
                                                    }
                                                    required={
                                                        actionType === "reject"
                                                    }
                                                    />
                                                </div>
                                            </>
                                        )}
                                </div>
                                <div className="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                                    <button
                                        onClick={() =>
                                            setShowActionModal(false)
                                        }
                                        className="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        onClick={submitAction}
                                        disabled={
                                            actionLoading ||
                                            (actionType === "reject" &&
                                                !actionNotes.trim()) ||
                                            (actionType === "procurement" &&
                                                !actionData.status) ||
                                            (actionType === "approve" &&
                                                auth.user?.id === 28 &&
                                                !isBillAmountValid())
                                        }
                                        className={`px-4 py-2 text-white text-base font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 ${
                                            actionType === "approve"
                                                ? "bg-green-600 hover:bg-green-700 focus:ring-green-300"
                                                : actionType === "reject"
                                                ? "bg-red-600 hover:bg-red-700 focus:ring-red-300"
                                                : "bg-blue-600 hover:bg-blue-700 focus:ring-blue-300"
                                        } ${
                                            actionLoading
                                                ? "opacity-50 cursor-not-allowed"
                                                : ""
                                        }`}
                                    >
                                        {actionLoading
                                            ? "Processing..."
                                            : actionType === "approve"
                                            ? auth.user?.id === 28
                                                ? "Approve"
                                                : "Approve"
                                            : actionType === "reject"
                                            ? "Reject"
                                            : actionType === "bill_printing"
                                            ? "Print Bill"
                                            : "Process"}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Delay Modal */}
                {showDelayModal && (
                    <div className="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
                        <div className="relative w-full max-w-2xl bg-white rounded-lg shadow-xl">
                            <div className="p-6">
                                <div className="flex items-center justify-center w-12 h-12 mx-auto bg-yellow-100 rounded-full mb-4">
                                    <svg
                                        className="w-6 h-6 text-yellow-600"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>
                                <div className="text-center mb-6">
                                    <h3 className="text-lg font-medium text-gray-900">
                                        Delay Request
                                    </h3>
                                    <p className="text-sm text-gray-500 mt-2">
                                        Select a date to delay this request for later review
                                    </p>
                                </div>
                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Delay Date *
                                        </label>
                                        <input
                                            type="date"
                                            value={delayData.delay_date}
                                            onChange={(e) =>
                                                setDelayData({
                                                    ...delayData,
                                                    delay_date: e.target.value,
                                                })
                                            }
                                            min={new Date().toISOString().split('T')[0]}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Reason for Delay
                                        </label>
                                        <textarea
                                            value={delayData.delay_reason}
                                            onChange={(e) =>
                                                setDelayData({
                                                    ...delayData,
                                                    delay_reason: e.target.value,
                                                })
                                            }
                                            rows={3}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                            placeholder="Optional: Explain why this request is being delayed..."
                                        />
                                    </div>
                                </div>
                                <div className="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                                    <button
                                        onClick={() => setShowDelayModal(false)}
                                        className="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        onClick={submitDelay}
                                        disabled={actionLoading || !delayData.delay_date}
                                        className={`px-4 py-2 text-white text-base font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-yellow-300 ${
                                            actionLoading || !delayData.delay_date
                                                ? "bg-gray-400 cursor-not-allowed"
                                                : "bg-yellow-600 hover:bg-yellow-700"
                                        }`}
                                    >
                                        {actionLoading ? "Processing..." : "Delay Request"}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Verification Modal */}
                {verificationModal && (
                    <div className="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
                        <div className="relative w-full max-w-2xl bg-white rounded-lg shadow-xl">
                            <div className="p-6">
                                <div className="flex items-center justify-center w-12 h-12 mx-auto bg-blue-100 rounded-full mb-4">
                                    <svg
                                        className="w-6 h-6 text-blue-600"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>
                                <div className="text-center mb-6">
                                    <h3 className="text-lg font-medium text-gray-900">
                                        {verificationData.status === "Verified"
                                            ? "Verify Request"
                                            : "Reject Request"}
                                    </h3>
                                    <p className="text-sm text-gray-500 mt-2">
                                        {verificationData.status === "Verified"
                                            ? "Please provide the final price and verification notes"
                                            : "Please provide the reason for rejection"}
                                    </p>
                                </div>

                                <div className="space-y-4">
                                    {/* Status Display */}
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Verification Status
                                        </label>
                                        <div
                                            className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${
                                                verificationData.status ===
                                                "Verified"
                                                    ? "bg-green-100 text-green-800"
                                                    : "bg-red-100 text-red-800"
                                            }`}
                                        >
                                            {verificationData.status ===
                                            "Verified"
                                                ? "✓ Verified"
                                                : "✗ Rejected"}
                                        </div>
                                    </div>

                                    {/* Final Price (only for Verified) */}
                                    {verificationData.status === "Verified" && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Final Amount (AFN){" "}
                                                <span className="text-red-500">
                                                    *
                                                </span>
                                            </label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={
                                                    verificationData.final_price
                                                }
                                                onChange={(e) =>
                                                    setVerificationData({
                                                    ...verificationData,
                                                        final_price:
                                                            e.target.value,
                                                    })
                                                }
                                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                placeholder="Enter final amount"
                                                required
                                            />
                                        </div>
                                    )}

                                    {/* Notes */}
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            {verificationData.status ===
                                            "Verified"
                                                ? "Verification Notes"
                                                : "Rejection Reason"}{" "}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </label>
                                        <textarea
                                            value={verificationData.notes}
                                            onChange={(e) =>
                                                setVerificationData({
                                                ...verificationData,
                                                    notes: e.target.value,
                                                })
                                            }
                                            rows={3}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder={
                                                verificationData.status ===
                                                "Verified"
                                                    ? "Enter verification notes..."
                                                    : "Enter rejection reason..."
                                            }
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                                    <button
                                        onClick={() =>
                                            setVerificationModal(false)
                                        }
                                        className="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        onClick={handleVerificationSubmit}
                                        disabled={
                                            actionLoading ||
                                            !verificationData.notes.trim() ||
                                            (verificationData.status ===
                                                "Verified" &&
                                                !verificationData.final_price)
                                        }
                                        className={`px-4 py-2 text-white text-base font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 ${
                                            verificationData.status ===
                                            "Verified"
                                                ? "bg-green-600 hover:bg-green-700 focus:ring-green-300"
                                                : "bg-red-600 hover:bg-red-700 focus:ring-red-300"
                                        } ${
                                            actionLoading
                                                ? "opacity-50 cursor-not-allowed"
                                                : ""
                                        }`}
                                    >
                                        {actionLoading
                                            ? "Processing..."
                                            : verificationData.status ===
                                              "Verified"
                                            ? "Submit Verification"
                                            : "Submit Rejection"}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Alert Modal */}
            <AlertModal
                isOpen={showAlert}
                onClose={() => setShowAlert(false)}
                title={
                    alertType === "success"
                        ? "Success"
                        : alertType === "error"
                        ? "Error"
                        : alertType === "warning"
                        ? "Warning"
                        : "Information"
                }
                message={alertMessage}
                type={alertType}
                buttonText="OK"
                autoClose={alertType === "success"}
                autoCloseDelay={3000}
            />
        </AppLayout>
    );
}
