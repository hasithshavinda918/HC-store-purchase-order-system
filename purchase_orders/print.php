<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db_connection.php';

$po_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$po_id) {
    $_SESSION['error'] = "Purchase order ID is required.";
    header('Location: index.php');
    exit;
}

// Get purchase order details
$stmt = $conn->prepare("
    SELECT po.*, s.name as supplier_name, s.contact_person, s.email, s.phone, 
           s.address, s.city, s.postal_code, s.country,
           u.username as created_by_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN users u ON po.created_by = u.id
    WHERE po.id = ?
");
$stmt->bind_param("i", $po_id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();

if (!$po) {
    $_SESSION['error'] = "Purchase order not found.";
    header('Location: index.php');
    exit;
}

// Get purchase order items
$items_stmt = $conn->prepare("
    SELECT poi.*, p.name as product_name, p.sku, c.name as category_name
    FROM purchase_order_items poi
    LEFT JOIN products p ON poi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE poi.po_id = ?
    ORDER BY p.name
");
$items_stmt->bind_param("i", $po_id);
$items_stmt->execute();
$items = $items_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - <?= htmlspecialchars($po['po_number']) ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; font-size: 12px; }
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.4;
            color: #333;
            margin: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }
        
        .document-title {
            font-size: 18px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .po-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .po-info, .supplier-info {
            width: 48%;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: bold;
            background-color: #f3f4f6;
            padding: 8px 12px;
            margin-bottom: 10px;
            border-left: 4px solid #2563eb;
        }
        
        .info-row {
            margin-bottom: 8px;
        }
        
        .label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            text-align: left;
        }
        
        .items-table th {
            background-color: #f9fafb;
            font-weight: bold;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .total-row {
            background-color: #f3f4f6;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #d1d5db;
            font-size: 11px;
            color: #6b7280;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-draft { background-color: #f3f4f6; color: #374151; }
        .status-sent { background-color: #dbeafe; color: #1e40af; }
        .status-confirmed { background-color: #fef3c7; color: #92400e; }
        .status-partially_received { background-color: #fed7aa; color: #c2410c; }
        .status-received { background-color: #dcfce7; color: #166534; }
        .status-cancelled { background-color: #fecaca; color: #dc2626; }
        
        .print-buttons {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            margin: 0 5px;
            background-color: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn:hover {
            background-color: #1d4ed8;
        }
        
        .btn-secondary {
            background-color: #6b7280;
        }
        
        .btn-secondary:hover {
            background-color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="no-print print-buttons">
        <a href="javascript:window.print()" class="btn">🖨️ Print</a>
        <a href="view.php?id=<?= $po_id ?>" class="btn btn-secondary">← Back to PO</a>
        <a href="index.php" class="btn btn-secondary">← All Orders</a>
    </div>

    <div class="header">
        <div class="company-name">HC Store</div>
        <div>Inventory Management System</div>
        <div class="document-title">PURCHASE ORDER</div>
    </div>

    <div class="po-details">
        <div class="po-info">
            <div class="section-title">Order Information</div>
            <div class="info-row">
                <span class="label">PO Number:</span>
                <strong><?= htmlspecialchars($po['po_number']) ?></strong>
            </div>
            <div class="info-row">
                <span class="label">Status:</span>
                <span class="status-badge status-<?= $po['status'] ?>">
                    <?= ucfirst(str_replace('_', ' ', $po['status'])) ?>
                </span>
            </div>
            <div class="info-row">
                <span class="label">Order Date:</span>
                <?= date('F d, Y', strtotime($po['order_date'])) ?>
            </div>
            <?php if ($po['expected_delivery']): ?>
            <div class="info-row">
                <span class="label">Expected:</span>
                <?= date('F d, Y', strtotime($po['expected_delivery'])) ?>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="label">Created By:</span>
                <?= htmlspecialchars($po['created_by_name']) ?>
            </div>
            <div class="info-row">
                <span class="label">Created:</span>
                <?= date('F d, Y g:i A', strtotime($po['created_at'])) ?>
            </div>
        </div>

        <div class="supplier-info">
            <div class="section-title">Supplier Information</div>
            <div class="info-row">
                <strong><?= htmlspecialchars($po['supplier_name']) ?></strong>
            </div>
            <?php if ($po['contact_person']): ?>
            <div class="info-row">
                Attn: <?= htmlspecialchars($po['contact_person']) ?>
            </div>
            <?php endif; ?>
            <?php if ($po['address']): ?>
            <div class="info-row">
                <?= nl2br(htmlspecialchars($po['address'])) ?>
            </div>
            <?php endif; ?>
            <?php if ($po['city']): ?>
            <div class="info-row">
                <?= htmlspecialchars($po['city']) ?>
                <?= $po['postal_code'] ? ' ' . htmlspecialchars($po['postal_code']) : '' ?>
            </div>
            <?php endif; ?>
            <?php if ($po['country'] && $po['country'] !== 'Sri Lanka'): ?>
            <div class="info-row">
                <?= htmlspecialchars($po['country']) ?>
            </div>
            <?php endif; ?>
            <?php if ($po['phone']): ?>
            <div class="info-row">
                📞 <?= htmlspecialchars($po['phone']) ?>
            </div>
            <?php endif; ?>
            <?php if ($po['email']): ?>
            <div class="info-row">
                📧 <?= htmlspecialchars($po['email']) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($po['notes']): ?>
    <div style="margin-bottom: 20px;">
        <div class="section-title">Special Instructions / Notes</div>
        <div style="padding: 10px; background-color: #fef3c7; border-left: 4px solid #f59e0b;">
            <?= nl2br(htmlspecialchars($po['notes'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="section-title">Order Items</div>
    
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>Product Description</th>
                <th style="width: 80px;" class="text-center">Qty</th>
                <th style="width: 100px;" class="text-right">Unit Cost</th>
                <th style="width: 100px;" class="text-right">Total</th>
                <?php if (in_array($po['status'], ['partially_received', 'received'])): ?>
                <th style="width: 80px;" class="text-center">Received</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php 
            $item_number = 1;
            $total_items = 0;
            $total_quantity = 0;
            $total_amount = 0;
            $total_received = 0;
            ?>
            <?php while ($item = $items->fetch_assoc()): 
                $total_items++;
                $total_quantity += $item['quantity'];
                $total_amount += $item['total_cost'];
                $total_received += $item['received_quantity'];
            ?>
            <tr>
                <td class="text-center"><?= $item_number++ ?></td>
                <td>
                    <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                    <?php if ($item['sku']): ?>
                    <br><small>SKU: <?= htmlspecialchars($item['sku']) ?></small>
                    <?php endif; ?>
                    <?php if ($item['category_name']): ?>
                    <br><small><em><?= htmlspecialchars($item['category_name']) ?></em></small>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?= number_format($item['quantity']) ?></td>
                <td class="text-right">LKR <?= number_format($item['unit_cost'], 2) ?></td>
                <td class="text-right">LKR <?= number_format($item['total_cost'], 2) ?></td>
                <?php if (in_array($po['status'], ['partially_received', 'received'])): ?>
                <td class="text-center">
                    <?= number_format($item['received_quantity']) ?>
                    <?php if ($item['received_quantity'] > 0): ?>
                    <br><small style="color: <?= $item['received_quantity'] >= $item['quantity'] ? '#166534' : '#c2410c' ?>;">
                        <?= round(($item['received_quantity'] / $item['quantity']) * 100) ?>%
                    </small>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="text-right">
                    <strong>TOTAL (<?= $total_items ?> items)</strong>
                </td>
                <td class="text-center"><strong><?= number_format($total_quantity) ?></strong></td>
                <td class="text-right">-</td>
                <td class="text-right"><strong>LKR <?= number_format($total_amount, 2) ?></strong></td>
                <?php if (in_array($po['status'], ['partially_received', 'received'])): ?>
                <td class="text-center">
                    <strong><?= number_format($total_received) ?></strong>
                    <?php if ($total_received > 0): ?>
                    <br><small style="color: <?= $total_received >= $total_quantity ? '#166534' : '#c2410c' ?>;">
                        <?= round(($total_received / $total_quantity) * 100) ?>%
                    </small>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 30px;">
        <div class="section-title">Total Amount</div>
        <div style="font-size: 18px; font-weight: bold; color: #2563eb; text-align: right;">
            LKR <?= number_format($total_amount, 2) ?>
        </div>
        <div style="text-align: right; font-size: 12px; color: #6b7280;">
            (<?= ucwords(numberToWords($total_amount)) ?> Rupees Only)
        </div>
    </div>

    <?php if ($po['status'] === 'received' && $po['received_date']): ?>
    <div style="margin-top: 20px; padding: 15px; background-color: #dcfce7; border-left: 4px solid #166534;">
        <strong>✅ ORDER COMPLETED</strong><br>
        All items were received on <?= date('F d, Y g:i A', strtotime($po['received_date'])) ?>
        <?php if ($po['received_by']): ?>
        <br>Received by: <?= htmlspecialchars($po['received_by']) ?>
        <?php endif; ?>
    </div>
    <?php elseif ($po['status'] === 'partially_received'): ?>
    <div style="margin-top: 20px; padding: 15px; background-color: #fed7aa; border-left: 4px solid #c2410c;">
        <strong>📦 PARTIALLY RECEIVED</strong><br>
        <?= number_format($total_received) ?> of <?= number_format($total_quantity) ?> items received 
        (<?= round(($total_received / $total_quantity) * 100) ?>% complete)
        <br>Remaining <?= number_format($total_quantity - $total_received) ?> items pending delivery
    </div>
    <?php endif; ?>

    <div class="footer">
        <div style="display: flex; justify-content: space-between;">
            <div>
                <strong>HC Store Inventory Management System</strong><br>
                Generated on <?= date('F d, Y g:i A') ?><br>
                PO ID: <?= $po_id ?> | Status: <?= ucfirst(str_replace('_', ' ', $po['status'])) ?>
            </div>
            <div style="text-align: right;">
                <strong>Authorized Signature</strong><br><br>
                ________________________<br>
                <?= htmlspecialchars($po['created_by_name']) ?><br>
                <small>Purchase Order Created By</small>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px; font-size: 10px; color: #9ca3af;">
            This is a computer-generated document and does not require a physical signature unless specified by company policy.
        </div>
    </div>

    <script>
        // Auto-print when page loads (only if print=1 in URL)
        if (new URLSearchParams(window.location.search).get('print') === '1') {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>

<?php
// Simple function to convert numbers to words (for amounts)
function numberToWords($number) {
    $number = intval($number);
    
    if ($number === 0) return 'zero';
    
    $ones = array(
        0 => '', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
        5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
        10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen',
        14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen',
        18 => 'eighteen', 19 => 'nineteen'
    );
    
    $tens = array(
        0 => '', 2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
        6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'
    );
    
    if ($number < 20) {
        return $ones[$number];
    } elseif ($number < 100) {
        return $tens[intval($number / 10)] . ($number % 10 ? ' ' . $ones[$number % 10] : '');
    } elseif ($number < 1000) {
        return $ones[intval($number / 100)] . ' hundred' . ($number % 100 ? ' ' . numberToWords($number % 100) : '');
    } elseif ($number < 1000000) {
        return numberToWords(intval($number / 1000)) . ' thousand' . ($number % 1000 ? ' ' . numberToWords($number % 1000) : '');
    }
    
    return number_format($number);
}
?>
