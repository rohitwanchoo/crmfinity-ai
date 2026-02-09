<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Application File Control Sheet</title>
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
        .header .business-name {
            font-size: 16px;
            color: #1e40af;
            font-weight: bold;
            margin-top: 8px;
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
        .summary-table .debits {
            background: #fef2f2;
            color: #991b1b;
        }
        .statements-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .statements-table th,
        .statements-table td {
            padding: 6px 10px;
            text-align: left;
            border: 1px solid #e2e8f0;
        }
        .statements-table th {
            background: #f1f5f9;
            font-weight: bold;
        }
        .statements-table .amount {
            text-align: right;
            font-family: 'DejaVu Sans Mono', monospace;
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
        .big-number {
            font-size: 28px;
            font-weight: bold;
            color: #166534;
            text-align: center;
            padding: 15px;
            background: #dcfce7;
            border-radius: 8px;
            margin: 15px 0;
        }
        .big-number .label {
            font-size: 12px;
            color: #475569;
            font-weight: normal;
        }
        .uw-section {
            margin-bottom: 20px;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
        }
        .uw-section.high { border-color: #22c55e; background: #f0fdf4; }
        .uw-section.medium { border-color: #f59e0b; background: #fffbeb; }
        .uw-section.low { border-color: #ef4444; background: #fef2f2; }
        .uw-header {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .uw-score-box {
            display: table-cell;
            width: 100px;
            text-align: center;
            vertical-align: middle;
        }
        .uw-score-circle {
            display: inline-block;
            width: 70px;
            height: 70px;
            line-height: 70px;
            border-radius: 50%;
            font-size: 28px;
            font-weight: bold;
            color: white;
        }
        .uw-score-circle.high { background: #22c55e; }
        .uw-score-circle.medium { background: #f59e0b; }
        .uw-score-circle.low { background: #ef4444; }
        .uw-details {
            display: table-cell;
            vertical-align: middle;
            padding-left: 15px;
        }
        .uw-decision {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .uw-decision.high { color: #166534; }
        .uw-decision.medium { color: #92400e; }
        .uw-decision.low { color: #991b1b; }
        .uw-components {
            display: table;
            width: 100%;
            margin-top: 10px;
            font-size: 10px;
        }
        .uw-component {
            display: table-cell;
            text-align: center;
            padding: 5px;
        }
        .uw-component-score {
            font-size: 14px;
            font-weight: bold;
        }
        .uw-component-label {
            color: #64748b;
            font-size: 9px;
        }
        .uw-flags {
            margin-top: 10px;
            padding: 8px;
            background: #f8fafc;
            border-radius: 4px;
            font-size: 9px;
        }
        .uw-flag {
            padding: 2px 0;
            color: #475569;
        }
        .uw-flag.critical { color: #991b1b; }
        .uw-flag.high { color: #c2410c; }
    </style>
</head>
<body>
    <div class="header">
        <h1>FILE CONTROL SHEET</h1>
        <div class="subtitle">Bank Statement Analysis Report</div>
        <div class="business-name">{{ $business_name }}</div>
    </div>

    <div class="meta-info">
        <div class="meta-row">
            <span class="meta-label">Application ID:</span>
            <span class="meta-value">#{{ $application_id }}</span>
        </div>
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

    @if($underwriting_score)
    @php
        $uwLevel = $underwriting_score >= 75 ? 'high' : ($underwriting_score >= 45 ? 'medium' : 'low');
        $decisionLabels = [
            'APPROVE' => 'APPROVE',
            'CONDITIONAL_APPROVE' => 'CONDITIONAL APPROVE',
            'REVIEW' => 'MANUAL REVIEW',
            'HIGH_RISK' => 'HIGH RISK',
            'DECLINE' => 'DECLINE',
        ];
        $componentNames = [
            'true_revenue' => 'Revenue',
            'cash_flow' => 'Cash Flow',
            'balance_quality' => 'Balance',
            'transaction_patterns' => 'Patterns',
            'risk_indicators' => 'Risk',
        ];
    @endphp
    <div class="uw-section {{ $uwLevel }}">
        <div class="section-title" style="border-bottom: none; margin-bottom: 10px;">UNDERWRITING DECISION SCORE</div>
        <div class="uw-header">
            <div class="uw-score-box">
                <div class="uw-score-circle {{ $uwLevel }}">{{ $underwriting_score }}</div>
            </div>
            <div class="uw-details">
                <div class="uw-decision {{ $uwLevel }}">
                    {{ $decisionLabels[$underwriting_decision] ?? $underwriting_decision }}
                </div>
                <div style="font-size: 11px; color: #64748b;">
                    @if($uwLevel == 'high')
                        Strong financial indicators support approval
                    @elseif($uwLevel == 'medium')
                        Acceptable risk profile - review recommended
                    @else
                        High risk indicators detected
                    @endif
                </div>
            </div>
        </div>

        @if($underwriting_details && isset($underwriting_details['component_scores']))
        <div class="uw-components">
            @foreach($underwriting_details['component_scores'] as $key => $score)
            <div class="uw-component">
                <div class="uw-component-score" style="color: {{ $score >= 60 ? '#166534' : ($score >= 40 ? '#92400e' : '#991b1b') }}">{{ $score }}</div>
                <div class="uw-component-label">{{ $componentNames[$key] ?? $key }}</div>
            </div>
            @endforeach
        </div>
        @endif

        @if($underwriting_details && isset($underwriting_details['flags']) && count($underwriting_details['flags']) > 0)
        <div class="uw-flags">
            <strong>Key Findings:</strong>
            @foreach(array_slice($underwriting_details['flags'], 0, 4) as $flag)
            <div class="uw-flag {{ $flag['severity'] ?? 'low' }}">
                - {{ $flag['message'] }}
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    <div class="big-number">
        <div class="label">TRUE MONTHLY REVENUE</div>
        ${{ number_format($true_revenue, 2) }}
    </div>

    <div class="section">
        <div class="section-title">FINANCIAL SUMMARY</div>
        <table class="summary-table">
            <tr>
                <th>Description</th>
                <th class="amount">Amount</th>
            </tr>
            <tr class="highlight">
                <td>Total Credits (All Deposits)</td>
                <td class="amount">${{ number_format($total_credits, 2) }}</td>
            </tr>
            <tr class="debits">
                <td>Total Debits (All Withdrawals)</td>
                <td class="amount">${{ number_format($total_debits, 2) }}</td>
            </tr>
            <tr class="true-revenue">
                <td>TRUE REVENUE (Net Business Deposits)</td>
                <td class="amount">${{ number_format($true_revenue, 2) }}</td>
            </tr>
            <tr>
                <td>Net Cash Flow</td>
                <td class="amount">${{ number_format($total_credits - $total_debits, 2) }}</td>
            </tr>
        </table>

        <div style="text-align: center;">
            <div class="ratio-box">
                Revenue Ratio: {{ $revenue_ratio }}%
            </div>
        </div>
        <p style="text-align: center; font-size: 10px; color: #64748b; margin-top: 5px;">
            (Percentage of total deposits that represent actual business revenue)
        </p>
    </div>

    <div class="section">
        <div class="section-title">STATEMENT BREAKDOWN</div>
        <table class="statements-table">
            <tr>
                <th>Statement</th>
                <th>Period</th>
                <th class="amount">Credits</th>
                <th class="amount">Debits</th>
                <th class="amount">True Revenue</th>
                <th class="amount">Txns</th>
            </tr>
            @foreach($statements as $stmt)
            <tr>
                <td>{{ \Illuminate\Support\Str::limit($stmt['filename'], 30) }}</td>
                <td>{{ $stmt['statement_period'] }}</td>
                <td class="amount">${{ number_format($stmt['credits'] ?? 0, 2) }}</td>
                <td class="amount">${{ number_format($stmt['debits'] ?? 0, 2) }}</td>
                <td class="amount" style="color: #166534; font-weight: bold;">${{ number_format($stmt['true_revenue'] ?? 0, 2) }}</td>
                <td class="amount">{{ $stmt['transactions'] ?? 0 }}</td>
            </tr>
            @endforeach
            <tr style="background: #f1f5f9; font-weight: bold;">
                <td colspan="2">TOTALS</td>
                <td class="amount">${{ number_format($total_credits, 2) }}</td>
                <td class="amount">${{ number_format($total_debits, 2) }}</td>
                <td class="amount" style="color: #166534;">${{ number_format($true_revenue, 2) }}</td>
                <td class="amount">-</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">NOTES</div>
        <ul style="padding-left: 20px; font-size: 10px; color: #64748b;">
            <li>True Revenue excludes: transfers, loans, MCA advances, refunds, reversals, and interest payments</li>
            <li>Revenue Ratio indicates the quality of deposits - higher is better</li>
            <li>All amounts are based on automated transaction analysis</li>
        </ul>
    </div>

    <div class="footer">
        <p>This report was generated by CRMfinity Bank Statement Analysis System</p>
        <p>Application #{{ $application_id }} | Report ID: {{ $report_id }} | Generated: {{ $generated_at }}</p>
        <p>The information contained in this report is based on automated analysis of uploaded bank statements.</p>
    </div>
</body>
</html>
