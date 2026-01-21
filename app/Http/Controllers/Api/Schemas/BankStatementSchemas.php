<?php

namespace App\Http\Controllers\Api\Schemas;

use OpenApi\Attributes as OA;

/**
 * OpenAPI Schema definitions for Bank Statement Analyzer API
 */

#[OA\Schema(
    schema: 'ErrorResponse',
    title: 'Error Response',
    description: 'Standard error response',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'string', example: 'Error message here'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string')
            ),
            nullable: true
        )
    ]
)]

#[OA\Schema(
    schema: 'Pagination',
    title: 'Pagination',
    description: 'Pagination metadata',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 10),
        new OA\Property(property: 'per_page', type: 'integer', example: 20),
        new OA\Property(property: 'total', type: 'integer', example: 200)
    ]
)]

#[OA\Schema(
    schema: 'Transaction',
    title: 'Transaction',
    description: 'A bank transaction',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'transaction_date', type: 'string', format: 'date', example: '2024-01-15'),
        new OA\Property(property: 'description', type: 'string', example: 'DIRECT DEPOSIT - PAYROLL'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 5000.00),
        new OA\Property(property: 'type', type: 'string', enum: ['credit', 'debit'], example: 'credit'),
        new OA\Property(property: 'original_type', type: 'string', enum: ['credit', 'debit'], example: 'credit'),
        new OA\Property(property: 'was_corrected', type: 'boolean', example: false),
        new OA\Property(property: 'confidence', type: 'number', format: 'float', example: 1.0),
        new OA\Property(property: 'confidence_label', type: 'string', enum: ['high', 'medium', 'low'], example: 'high'),
        new OA\Property(property: 'is_mca_payment', type: 'boolean', example: false, nullable: true),
        new OA\Property(property: 'mca_lender', type: 'string', example: 'OnDeck Capital', nullable: true)
    ]
)]

#[OA\Schema(
    schema: 'TransactionSummary',
    title: 'Transaction Summary',
    description: 'Summary of transactions',
    properties: [
        new OA\Property(property: 'total_transactions', type: 'integer', example: 150),
        new OA\Property(property: 'credit_count', type: 'integer', example: 50),
        new OA\Property(property: 'debit_count', type: 'integer', example: 100),
        new OA\Property(property: 'total_credits', type: 'number', format: 'float', example: 75000.00),
        new OA\Property(property: 'total_debits', type: 'number', format: 'float', example: 60000.00),
        new OA\Property(property: 'net_flow', type: 'number', format: 'float', example: 15000.00)
    ]
)]

#[OA\Schema(
    schema: 'SessionSummary',
    title: 'Session Summary',
    description: 'Summary of an analysis session',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'session_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'filename', type: 'string', example: 'bank_statement_jan_2024.pdf'),
        new OA\Property(property: 'pages', type: 'integer', example: 5),
        new OA\Property(property: 'total_transactions', type: 'integer', example: 150),
        new OA\Property(property: 'total_credits', type: 'number', format: 'float', example: 75000.00),
        new OA\Property(property: 'total_debits', type: 'number', format: 'float', example: 60000.00),
        new OA\Property(property: 'net_flow', type: 'number', format: 'float', example: 15000.00),
        new OA\Property(property: 'analysis_type', type: 'string', example: 'openai'),
        new OA\Property(property: 'model_used', type: 'string', example: 'gpt-4o'),
        new OA\Property(property: 'api_cost', type: 'number', format: 'float', example: 0.15),
        new OA\Property(property: 'created_at', type: 'string', format: 'datetime', example: '2024-01-15T10:30:00Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'datetime', example: '2024-01-15T10:30:00Z')
    ]
)]

#[OA\Schema(
    schema: 'SessionDetail',
    title: 'Session Detail',
    description: 'Detailed information about an analysis session',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/SessionSummary'),
        new OA\Schema(
            properties: [
                new OA\Property(property: 'user_id', type: 'integer', example: 1, nullable: true),
                new OA\Property(property: 'high_confidence_count', type: 'integer', example: 140),
                new OA\Property(property: 'medium_confidence_count', type: 'integer', example: 8),
                new OA\Property(property: 'low_confidence_count', type: 'integer', example: 2)
            ]
        )
    ]
)]

#[OA\Schema(
    schema: 'SessionSummaryDetail',
    title: 'Session Summary Detail',
    description: 'Detailed summary for a session',
    properties: [
        new OA\Property(property: 'session_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'filename', type: 'string'),
        new OA\Property(property: 'analyzed_at', type: 'string', format: 'datetime'),
        new OA\Property(property: 'total_transactions', type: 'integer'),
        new OA\Property(property: 'credit_count', type: 'integer'),
        new OA\Property(property: 'debit_count', type: 'integer'),
        new OA\Property(property: 'total_credits', type: 'number', format: 'float'),
        new OA\Property(property: 'total_debits', type: 'number', format: 'float'),
        new OA\Property(property: 'net_flow', type: 'number', format: 'float'),
        new OA\Property(property: 'api_cost', type: 'number', format: 'float'),
        new OA\Property(property: 'model_used', type: 'string'),
        new OA\Property(property: 'pages', type: 'integer')
    ]
)]

#[OA\Schema(
    schema: 'AnalysisResult',
    title: 'Analysis Result',
    description: 'Result of analyzing a single bank statement',
    properties: [
        new OA\Property(property: 'filename', type: 'string', example: 'bank_statement_jan_2024.pdf'),
        new OA\Property(property: 'session_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'error', type: 'string', nullable: true),
        new OA\Property(
            property: 'summary',
            type: 'object',
            properties: [
                new OA\Property(property: 'total_transactions', type: 'integer', example: 150),
                new OA\Property(property: 'credit_count', type: 'integer', example: 50),
                new OA\Property(property: 'debit_count', type: 'integer', example: 100),
                new OA\Property(property: 'credit_total', type: 'number', format: 'float', example: 75000.00),
                new OA\Property(property: 'debit_total', type: 'number', format: 'float', example: 60000.00),
                new OA\Property(property: 'net_balance', type: 'number', format: 'float', example: 15000.00)
            ]
        ),
        new OA\Property(
            property: 'api_cost',
            type: 'object',
            properties: [
                new OA\Property(property: 'total_cost', type: 'number', format: 'float', example: 0.15),
                new OA\Property(property: 'total_tokens', type: 'integer', example: 5000),
                new OA\Property(property: 'input_tokens', type: 'integer', example: 4000),
                new OA\Property(property: 'output_tokens', type: 'integer', example: 1000)
            ]
        ),
        new OA\Property(property: 'transaction_count', type: 'integer', example: 150),
        new OA\Property(property: 'monthly_data', ref: '#/components/schemas/MonthlyBreakdown'),
        new OA\Property(property: 'mca_analysis', ref: '#/components/schemas/McaAnalysis')
    ]
)]

#[OA\Schema(
    schema: 'MonthlyData',
    title: 'Monthly Data',
    description: 'Data for a single month',
    properties: [
        new OA\Property(property: 'month_key', type: 'string', example: '2024-01'),
        new OA\Property(property: 'month_name', type: 'string', example: 'January 2024'),
        new OA\Property(property: 'deposits', type: 'number', format: 'float', example: 25000.00),
        new OA\Property(property: 'deposit_count', type: 'integer', example: 15),
        new OA\Property(property: 'adjustments', type: 'number', format: 'float', example: 3000.00),
        new OA\Property(property: 'adjustment_count', type: 'integer', example: 3),
        new OA\Property(property: 'true_revenue', type: 'number', format: 'float', example: 22000.00),
        new OA\Property(property: 'debits', type: 'number', format: 'float', example: 18000.00),
        new OA\Property(property: 'debit_count', type: 'integer', example: 45),
        new OA\Property(property: 'days_in_month', type: 'integer', example: 31),
        new OA\Property(property: 'average_daily', type: 'number', format: 'float', example: 709.68)
    ]
)]

#[OA\Schema(
    schema: 'MonthlyBreakdown',
    title: 'Monthly Breakdown',
    description: 'Monthly breakdown of transactions with totals and averages',
    properties: [
        new OA\Property(
            property: 'months',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/MonthlyData')
        ),
        new OA\Property(
            property: 'totals',
            type: 'object',
            properties: [
                new OA\Property(property: 'deposits', type: 'number', format: 'float', example: 75000.00),
                new OA\Property(property: 'adjustments', type: 'number', format: 'float', example: 9000.00),
                new OA\Property(property: 'true_revenue', type: 'number', format: 'float', example: 66000.00),
                new OA\Property(property: 'debits', type: 'number', format: 'float', example: 54000.00)
            ]
        ),
        new OA\Property(
            property: 'averages',
            type: 'object',
            properties: [
                new OA\Property(property: 'deposits', type: 'number', format: 'float', example: 25000.00),
                new OA\Property(property: 'adjustments', type: 'number', format: 'float', example: 3000.00),
                new OA\Property(property: 'true_revenue', type: 'number', format: 'float', example: 22000.00),
                new OA\Property(property: 'debits', type: 'number', format: 'float', example: 18000.00)
            ]
        ),
        new OA\Property(property: 'month_count', type: 'integer', example: 3)
    ]
)]

#[OA\Schema(
    schema: 'McaLender',
    title: 'MCA Lender',
    description: 'An MCA lender',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'ondeck'),
        new OA\Property(property: 'name', type: 'string', example: 'OnDeck Capital')
    ]
)]

#[OA\Schema(
    schema: 'McaLenderAnalysis',
    title: 'MCA Lender Analysis',
    description: 'Analysis of payments to a single MCA lender',
    properties: [
        new OA\Property(property: 'lender_id', type: 'string', example: 'ondeck'),
        new OA\Property(property: 'lender_name', type: 'string', example: 'OnDeck Capital'),
        new OA\Property(property: 'payment_count', type: 'integer', example: 22),
        new OA\Property(property: 'total_amount', type: 'number', format: 'float', example: 11000.00),
        new OA\Property(property: 'average_payment', type: 'number', format: 'float', example: 500.00),
        new OA\Property(property: 'frequency', type: 'string', enum: ['daily', 'weekly', 'bi_weekly', 'monthly', 'irregular'], example: 'daily'),
        new OA\Property(property: 'frequency_label', type: 'string', example: 'Daily')
    ]
)]

#[OA\Schema(
    schema: 'McaAnalysis',
    title: 'MCA Analysis',
    description: 'Complete MCA payment analysis',
    properties: [
        new OA\Property(property: 'total_mca_count', type: 'integer', example: 2, description: 'Number of different MCA lenders detected'),
        new OA\Property(property: 'total_mca_payments', type: 'integer', example: 44, description: 'Total number of MCA payments'),
        new OA\Property(property: 'total_mca_amount', type: 'number', format: 'float', example: 22000.00, description: 'Total amount of all MCA payments'),
        new OA\Property(
            property: 'lenders',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/McaLenderAnalysis')
        )
    ]
)]

class BankStatementSchemas
{
    // This class exists only to hold the OpenAPI schema attributes
}
