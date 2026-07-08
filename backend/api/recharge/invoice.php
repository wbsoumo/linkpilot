<?php
// backend/api/recharge/invoice.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../jwt_helper.php';

// Auth checks
$user = JWTHelper::requireAuth();
$userId = $user['id'];
$db = Database::getConnection();

$txId = (int)($_GET['id'] ?? 0);
if (!$txId) {
    die("Invalid invoice request.");
}

try {
    // Fetch transaction details
    $stmt = $db->prepare("
        SELECT t.id, t.credits, t.amount, t.payment_id, t.created_at, u.name as user_name, u.email as user_email, u.phone_number
        FROM email_credit_transactions t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND t.user_id = ? AND t.type = 'recharge' AND t.status = 'success'
    ");
    $stmt->execute([$txId, $userId]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tx) {
        die("Invoice not found or unauthorized access.");
    }

    $invoiceNumber = "INV-" . date('Y', strtotime($tx['created_at'])) . "-" . str_pad($tx['id'], 6, '0', STR_PAD_LEFT);
    $invoiceDate = date('d M, Y h:i A', strtotime($tx['created_at']));
    $paymentId = $tx['payment_id'] ?: 'Direct Top-up';
    $amount = (float)$tx['amount'];
    $credits = (int)$tx['credits'];
    $rate = $credits > 0 ? ($amount / $credits) : 0.20;

} catch (Exception $e) {
    die("Error loading invoice: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo htmlspecialchars($invoiceNumber); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }
        body {
            background-color: #f8fafc;
            color: #1e293b;
            padding: 40px 20px;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            padding: 45px;
            position: relative;
            overflow: hidden;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            border-b: 1px solid #f1f5f9;
            padding-bottom: 30px;
        }
        .brand-section img {
            height: 40px;
            margin-bottom: 12px;
            display: block;
        }
        .brand-name {
            font-size: 22px;
            font-weight: 800;
            color: #4f46e5;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .brand-tagline {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
        }
        .invoice-title-section {
            text-align: right;
        }
        .invoice-title {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .invoice-meta {
            font-size: 12px;
            color: #64748b;
            margin-top: 8px;
            line-height: 1.6;
        }
        .invoice-meta strong {
            color: #334155;
        }
        .details-grid {
            display: grid;
            grid-cols-2;
            display: flex;
            justify-content: space-between;
            gap: 40px;
            margin-bottom: 40px;
        }
        .details-block {
            flex: 1;
        }
        .details-block h4 {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            color: #94a3b8;
            letter-spacing: 1px;
            margin-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 6px;
        }
        .details-block p {
            font-size: 13px;
            color: #475569;
            line-height: 1.6;
        }
        .details-block strong {
            color: #0f172a;
            font-size: 14px;
        }
        .table-container {
            margin-bottom: 40px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .invoice-table th {
            background-color: #f8fafc;
            padding: 14px 18px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            color: #475569;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        .invoice-table td {
            padding: 18px;
            font-size: 13px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            line-height: 1.5;
        }
        .invoice-table td.qty, .invoice-table th.qty {
            text-align: center;
        }
        .invoice-table td.amount, .invoice-table th.amount {
            text-align: right;
            font-weight: 600;
        }
        .summary-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-top: 20px;
            border-top: 2px solid #f1f5f9;
        }
        .payment-info {
            max-width: 400px;
        }
        .payment-info h5 {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            color: #94a3b8;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .payment-info p {
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
        }
        .payment-info strong {
            color: #475569;
        }
        .totals-block {
            width: 250px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #475569;
            margin-bottom: 10px;
        }
        .totals-row.grand-total {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            border-top: 1px solid #f1f5f9;
            padding-top: 10px;
            margin-top: 10px;
        }
        .paid-stamp {
            position: absolute;
            top: 140px;
            right: 45px;
            border: 4px double #10b981;
            color: #10b981;
            font-size: 20px;
            font-weight: 800;
            padding: 8px 25px;
            border-radius: 8px;
            transform: rotate(-10deg);
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.85;
            pointer-events: none;
        }
        .action-bar {
            max-width: 800px;
            margin: 25px auto 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: #4f46e5;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
        }
        .action-btn:hover {
            background-color: #4338ca;
            transform: translateY(-1px);
        }
        .action-btn.secondary {
            background-color: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            box-shadow: none;
        }
        .action-btn.secondary:hover {
            background-color: #f8fafc;
            color: #1e293b;
        }
        
        @media print {
            body {
                background-color: #ffffff;
                padding: 0;
            }
            .invoice-container {
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .action-bar {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="invoice-container">
        <!-- Paid Watermark Stamp -->
        <div class="paid-stamp">PAID</div>

        <!-- Header -->
        <div class="invoice-header">
            <div class="brand-section">
                <div class="brand-name">
                    <img src="/dashboard/assets/img/logo.png" alt="LinkPilot CRM" onerror="this.style.display='none'">
                    <span>LinkPilot CRM</span>
                </div>
                <div class="brand-tagline">AI-Powered Email Scraper & Outreach Hub</div>
            </div>
            
            <div class="invoice-title-section">
                <div class="invoice-title">Tax Invoice</div>
                <div class="invoice-meta">
                    Invoice No: <strong><?php echo htmlspecialchars($invoiceNumber); ?></strong><br>
                    Date: <strong><?php echo htmlspecialchars($invoiceDate); ?></strong>
                </div>
            </div>
        </div>

        <!-- Billing Details -->
        <div class="details-grid">
            <div class="details-block">
                <h4>Seller Details</h4>
                <p>
                    <strong>LinkPilot CRM Ltd.</strong><br>
                    Email: billing@linkpilot.work<br>
                    Support: support@linkpilot.work<br>
                    Website: https://linkpilot.work<br>
                    Kolkata, West Bengal, India
                </p>
            </div>
            
            <div class="details-block">
                <h4>Buyer / Billed To</h4>
                <p>
                    <strong><?php echo htmlspecialchars($tx['user_name']); ?></strong><br>
                    Email: <?php echo htmlspecialchars($tx['user_email']); ?><br>
                    <?php if (!empty($tx['phone_number'])): ?>
                        Phone: <?php echo htmlspecialchars($tx['phone_number']); ?><br>
                    <?php endif; ?>
                    Customer ID: LP-USR-<?php echo str_pad($userId, 5, '0', STR_PAD_LEFT); ?>
                </p>
            </div>
        </div>

        <!-- Line Item Table -->
        <div class="table-container">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="qty">Credits / Tokens</th>
                        <th class="qty">Unit Rate</th>
                        <th class="amount">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>LinkPilot AI Top-up Credits</strong><br>
                            <span style="font-size: 11px; color: #64748b;">Tokens used for automatic AI replies and verified LinkedIn scraping lookup pipelines.</span>
                        </td>
                        <td class="qty"><?php echo number_format($credits); ?></td>
                        <td class="qty">₹<?php echo number_format($rate, 2); ?></td>
                        <td class="amount">₹<?php echo number_format($amount, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="summary-section">
            <div class="payment-info">
                <h5>Payment Verification Information</h5>
                <p>
                    Method: <strong>Razorpay Online Payment</strong><br>
                    Transaction ID: <strong><?php echo htmlspecialchars($paymentId); ?></strong><br>
                    Status: <strong>Captured & Successful</strong><br>
                    <span style="font-size: 11px; color: #94a3b8; display: block; margin-top: 6px;">This is an electronically generated document. No signature is required.</span>
                </p>
            </div>
            
            <div class="totals-block">
                <div class="totals-row">
                    <span>Subtotal:</span>
                    <span>₹<?php echo number_format($amount, 2); ?></span>
                </div>
                <div class="totals-row">
                    <span>Taxes & GST (0%):</span>
                    <span>₹0.00</span>
                </div>
                <div class="totals-row grand-total">
                    <span>Amount Paid:</span>
                    <span>₹<?php echo number_format($amount, 2); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Printable Action Buttons -->
    <div class="action-bar">
        <button onclick="window.close()" class="action-btn secondary">
            ← Close Window
        </button>
        <button onclick="window.print()" class="action-btn">
            Print / Save PDF
        </button>
    </div>

    <script>
        // Auto trigger browser print window
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 300);
        }
    </script>
</body>
</html>
