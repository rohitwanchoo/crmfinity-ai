<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>File Control Sheet</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #2563eb;
        }
        .header h1 {
            font-size: 22px;
            color: #1e40af;
            margin-bottom: 5px;
        }
        .header .subtitle {
            font-size: 12px;
            color: #64748b;
        }
        .meta-info {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            background: #f8fafc;
            padding: 10px;
            border-radius: 4px;
        }
        .meta-row {
            display: table-row;
        }
        .meta-label {
            display: table-cell;
            font-weight: bold;
            padding: 3px 10px 3px 0;
            width: 150px;
            color: #475569;
        }
        .meta-value {
            display: table-cell;
            padding: 3px 0;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .summary-table th,
        .summary-table td {
            padding: 8px 12px;
            text-align: left;
            border: 1px solid #e2e8f0;
        }
        .summary-table th {
            background: #f1f5f9;
            font-weight: bold;
            color: #334155;
        }
        .summary-table .amount {
            text-align: right;
            font-family: 'DejaVu Sans Mono', monospace;
        }
        .summary-table .highlight {
            background: #dbeafe;
            font-weight: bold;
        }
        .summary-table .true-revenue {
            background: #dcfce7;
            font-weight: bold;
            color: #166534;
        }
        .summary-table .excluded {
            background: #fef2f2;
            color: #991b1b;
        }
        .statements-list {
            margin-bottom: 15px;
        }
        .statement-item {
            background: #f8fafc;
            padding: 8px 12px;
            margin-bottom: 5px;
            border-left: 3px solid #2563eb;
        }
        .statement-item .filename {
            font-weight: bold;
            color: #1e40af;
        }
        .statement-item .details {
            font-size: 10px;
            color: #64748b;
            margin-top: 3px;
        }
        .excluded-items {
            font-size: 10px;
        }
        .excluded-items table {
            width: 100%;
            border-collapse: collapse;
        }
        .excluded-items th,
        .excluded-items td {
            padding: 5px 8px;
            text-align: left;
            border: 1px solid #e2e8f0;
        }
        .excluded-items th {
            background: #fef2f2;
            font-weight: bold;
        }
        .excluded-items .desc {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
        .ratio-box {
            display: inline-block;
            padding: 5px 15px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            font-weight: bold;
            border-radius: 4px;
            margin: 10px 0;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>FILE CONTROL SHEET</h1>
        <div class="subtitle">SmartMCA Bank Statement Analysis Report</div>
    </div>

    <div class="meta-info">
        <div class="meta-row">
            <span class="meta-label">Report ID:</span>
            <span class="meta-value">{{ $report_id }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Generated:</span>
            <span class="meta-value">{{ $generated_at }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Analyst:</span>
            <span class="meta-value">{{ $analyst_name }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Statements Analyzed:</span>
            <span class="meta-value">{{ $statement_count }}</span>
        </div>
    </div>

    <div class="section">
        <div class="section-title">REVENUE SUMMARY</div>
        <table class="summary-table">
            <tr>
                <th>Description</th>
                <th class="amount">Amount</th>
                <th class="amount">Count</th>
            </tr>
            <tr>
                <td>Total Credits (All Deposits)</td>
                <td class="amount">${{ number_format($total_credits, 2) }}</td>
                <td class="amount">{{ $total_credit_count }}</td>
            </tr>
            <tr class="excluded">
                <td>Less: Excluded Items (Transfers, Loans, Refunds)</td>
                <td class="amount">(${{ number_format($excluded_amount, 2) }})</td>
                <td class="amount">{{ $excluded_count }}</td>
            </tr>
            <tr class="true-revenue">
                <td>TRUE REVENUE (Net Business Deposits)</td>
                <td class="amount">${{ number_format($true_revenue, 2) }}</td>
                <td class="amount">{{ $revenue_deposits }}</td>
            </tr>
        </table>

        <div style="text-align: center;">
            <div class="ratio-box">
                Revenue Ratio: {{ $revenue_ratio }}%
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">STATEMENTS ANALYZED</div>
        <div class="statements-list">
            @foreach($statements as $stmt)
            <div class="statement-item">
                <div class="filename">{{ $stmt['filename'] }}</div>
                <div class="details">
                    Bank: {{ $stmt['bank_name'] ?? 'N/A' }} |
                    Period: {{ $stmt['date_range'] ?? 'N/A' }} |
                    Credits: ${{ number_format($stmt['credits'] ?? 0, 2) }} |
                    Debits: ${{ number_format($stmt['debits'] ?? 0, 2) }}
                </div>
            </div>
            @endforeach
        </div>
    </div>

    @if(count($excluded_items) > 0)
    <div class="section">
        <div class="section-title">EXCLUDED ITEMS (Non-Revenue Deposits)</div>
        <div class="excluded-items">
            <table>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th class="amount">Amount</th>
                </tr>
                @foreach($excluded_items as $item)
                <tr>
                    <td>{{ $item['date'] }}</td>
                    <td class="desc">{{ \Illuminate\Support\Str::limit($item['description'], 60) }}</td>
                    <td class="amount">${{ number_format($item['amount'], 2) }}</td>
                </tr>
                @endforeach
            </table>
        </div>
    </div>
    @endif

    <div class="footer">
        <p>This report was generated by SmartMCA Bank Statement Analysis System</p>
        <p>Report ID: {{ $report_id }} | Generated: {{ $generated_at }}</p>
        <p>The information contained in this report is based on automated analysis of uploaded bank statements.</p>
    </div>
</body>
</html>
