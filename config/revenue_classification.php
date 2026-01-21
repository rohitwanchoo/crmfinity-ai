<?php

return [
    /*
    |--------------------------------------------------------------------------
    | True Revenue Classification Rules
    |--------------------------------------------------------------------------
    |
    | Deterministic rules for classifying bank statement transactions.
    | Order matters - exclude patterns are checked before revenue patterns.
    |
    */

    // Average business days per month (used for daily revenue calculations)
    'business_days_per_month' => env('BUSINESS_DAYS_PER_MONTH', 21.67),

    // Maximum total withhold percentage (industry standard)
    'max_withhold_percentage' => env('MAX_WITHHOLD_PERCENTAGE', 0.20),

    /*
    |--------------------------------------------------------------------------
    | ALWAYS INCLUDE AS REVENUE (pattern => description)
    |--------------------------------------------------------------------------
    |
    | These patterns identify legitimate business revenue that should be
    | included in True Revenue calculations.
    |
    */
    'revenue_patterns' => [
        // Card processor settlements
        '/SQUARE\s*(INC|DEPOSIT|TRANSFER|PAYOUT)/i' => 'Square card settlement',
        '/STRIPE\s*(TRANSFER|PAYOUT|DEPOSIT)/i' => 'Stripe settlement',
        '/SHOPIFY\s*(PAYOUT|DEPOSIT|TRANSFER)/i' => 'Shopify payout',
        '/PAYPAL\s*(TRANSFER|DEPOSIT|INST\s*XFER)(?!.*WORKING\s*CAPITAL)/i' => 'PayPal settlement',
        '/CLOVER\s*(DEPOSIT|PAYOUT|TRANSFER)/i' => 'Clover settlement',
        '/TOAST\s*(DEPOSIT|PAYOUT)/i' => 'Toast settlement',
        '/HEARTLAND\s*(DEPOSIT|MERCH)/i' => 'Heartland settlement',
        '/WORLDPAY|VANTIV|FIRST\s*DATA/i' => 'Card processor settlement',
        '/ELAVON|MONERIS|AUTHORIZE\.?NET/i' => 'Card processor settlement',
        '/BRAINTREE\s*(DEPOSIT|PAYOUT)/i' => 'Braintree settlement',
        '/ADYEN\s*(DEPOSIT|PAYOUT)/i' => 'Adyen settlement',
        '/MERCHANT\s*SERV.*DEPOSIT/i' => 'Merchant services deposit',
        '/CREDIT\s*CARD\s*DEPOSIT/i' => 'Credit card deposit',
        '/POS\s*DEPOSIT/i' => 'POS deposit',

        // Marketplace payouts
        '/AMAZON\s*(SETTLEMENT|PAYOUT|TRANSFER)(?!.*LENDING)/i' => 'Amazon marketplace payout',
        '/EBAY\s*(MANAGED\s*PAYMENTS?|PAYOUT)/i' => 'eBay marketplace payout',
        '/ETSY\s*(DEPOSIT|PAYOUT)/i' => 'Etsy marketplace payout',
        '/WALMART\s*MARKETPLACE/i' => 'Walmart marketplace payout',
        '/DOORDASH\s*(DEPOSIT|PAYOUT|TRANSFER)/i' => 'DoorDash payout',
        '/UBER\s*EATS?\s*(DEPOSIT|PAYOUT)/i' => 'Uber Eats payout',
        '/GRUBHUB\s*(DEPOSIT|PAYOUT)/i' => 'Grubhub payout',
        '/POSTMATES\s*(DEPOSIT|PAYOUT)/i' => 'Postmates payout',

        // Zelle FROM (receiving money from customers)
        '/ZELLE\s*(FROM|CREDIT\s*FROM|REC\'?D?\s*FROM)/i' => 'Zelle payment received',
        '/ZELLE\s*PAYMENT\s*FROM/i' => 'Zelle payment received',

        // ACH customer payments (excluding loans/advances)
        '/ACH\s*CREDIT\s*(?!.*LOAN|.*ADVANCE|.*CAPITAL|.*FUNDING)/i' => 'ACH customer payment',

        // Customer deposits
        '/DEPOSIT\s*(CASH|CHECK|MOBILE|ATM)/i' => 'Customer deposit',
        '/REMOTE\s*DEPOSIT/i' => 'Mobile check deposit',
        '/MOBILE\s*CHECK\s*DEP/i' => 'Mobile check deposit',

        // Invoice/customer payments
        '/INVOICE\s*PAYMENT/i' => 'Invoice payment',
        '/CLIENT\s*PAYMENT/i' => 'Client payment',
        '/CUSTOMER\s*PAYMENT/i' => 'Customer payment',
    ],

    /*
    |--------------------------------------------------------------------------
    | ALWAYS EXCLUDE FROM REVENUE (pattern => description)
    |--------------------------------------------------------------------------
    |
    | These patterns identify non-revenue items that must be excluded from
    | True Revenue calculations.
    |
    */
    'exclude_patterns' => [
        // =====================================================================
        // INTERBANK TRANSFERS
        // =====================================================================
        '/TRANSFER\s+(FROM|FRM)\s+(CHK|CHECK|SAV|SAVINGS|\*+\d{4})/i' => 'Internal account transfer',
        '/TRANSFER\s+(TO|INTO)\s+(CHK|CHECK|SAV|SAVINGS|\*+\d{4})/i' => 'Internal account transfer',
        '/INTERNAL\s*TRANSFER/i' => 'Internal transfer',
        '/ACCOUNT\s*TRANSFER/i' => 'Account transfer',
        '/MOVE\s*MONEY/i' => 'Internal money movement',
        '/FUNDS\s*TRANSFER/i' => 'Funds transfer',
        '/ONLINE\s*TRANSFER\s*(FROM|TO)/i' => 'Online banking transfer',
        '/XFER\s*(FROM|TO)/i' => 'Transfer between accounts',
        '/WIRE\s*TRANSFER\s*(?!.*CUSTOMER|.*CLIENT|.*INVOICE)/i' => 'Wire transfer',
        '/BETWEEN\s*ACCOUNTS/i' => 'Between accounts transfer',

        // =====================================================================
        // MCA / LOAN PROCEEDS (Comprehensive list of funders)
        // =====================================================================
        // Major MCA Funders
        '/ONDECK|ON\s*DECK/i' => 'MCA funder - OnDeck',
        '/KABBAGE/i' => 'MCA funder - Kabbage',
        '/FUNDBOX/i' => 'MCA funder - Fundbox',
        '/BLUEVINE|BLUE\s*VINE/i' => 'MCA funder - BlueVine',
        '/CREDIBLY/i' => 'MCA funder - Credibly',
        '/KAPITUS/i' => 'MCA funder - Kapitus',
        '/RAPID\s*FINANCE/i' => 'MCA funder - Rapid Finance',
        '/CAN\s*CAPITAL/i' => 'MCA funder - CAN Capital',
        '/NATIONAL\s*FUNDING/i' => 'MCA funder - National Funding',
        '/BIZFI|BIZ2CREDIT/i' => 'MCA funder - BizFi/Biz2Credit',
        '/LENDIO/i' => 'MCA funder - Lendio',
        '/FUNDERA/i' => 'MCA funder - Fundera',

        // Platform Capital Programs
        '/SQUARE\s*CAPITAL|SQ\s*CAPITAL/i' => 'MCA funder - Square Capital',
        '/PAYPAL\s*WORKING\s*CAPITAL/i' => 'MCA funder - PayPal Working Capital',
        '/AMAZON\s*LENDING/i' => 'MCA funder - Amazon Lending',
        '/SHOPIFY\s*CAPITAL/i' => 'MCA funder - Shopify Capital',
        '/STRIPE\s*CAPITAL/i' => 'MCA funder - Stripe Capital',

        // Additional MCA Funders
        '/CLEARCO|CLEARBANC/i' => 'MCA funder - Clearco',
        '/LIBERTAS/i' => 'MCA funder - Libertas',
        '/FORWARD\s*FINANCING/i' => 'MCA funder - Forward Financing',
        '/FORA\s*FINANCIAL/i' => 'MCA funder - Fora Financial',
        '/RELIANT\s*FUNDING/i' => 'MCA funder - Reliant Funding',
        '/HEADWAY\s*CAPITAL/i' => 'MCA funder - Headway Capital',
        '/BEHALF/i' => 'MCA funder - Behalf',
        '/GREENBOX\s*CAPITAL/i' => 'MCA funder - Greenbox Capital',
        '/KALAMATA\s*CAPITAL/i' => 'MCA funder - Kalamata Capital',
        '/MULLIGAN\s*FUNDING/i' => 'MCA funder - Mulligan Funding',
        '/UNITED\s*CAPITAL\s*SOURCE/i' => 'MCA funder - United Capital Source',

        // Generic MCA/Loan patterns
        '/MERCHANT\s*CASH\s*ADVANCE/i' => 'MCA funding',
        '/MCA\s*(FUNDING|ADVANCE|DEPOSIT)/i' => 'MCA funding',
        '/BUSINESS\s*(LOAN|ADVANCE|FUNDING)/i' => 'Business loan/advance',
        '/LOAN\s*(PROCEED|DEPOSIT|DISBURS)/i' => 'Loan proceeds',
        '/WORKING\s*CAPITAL\s*(ADVANCE|FUNDING)/i' => 'Working capital advance',
        '/REVENUE\s*BASED\s*FINANCING/i' => 'Revenue based financing',
        '/LINE\s*OF\s*CREDIT|LOC\s*(ADVANCE|DRAW)/i' => 'Line of credit advance',
        '/CREDIT\s*LINE\s*(ADVANCE|DRAW)/i' => 'Credit line advance',
        '/TERM\s*LOAN/i' => 'Term loan',
        '/EQUIPMENT\s*(LOAN|FINANCING|LEASE)/i' => 'Equipment financing',

        // SBA Loans
        '/SBA\s*(LOAN|EIDL|PPP)/i' => 'SBA loan',
        '/EIDL\s*(ADVANCE|LOAN)/i' => 'EIDL loan',
        '/PPP\s*(LOAN|FORGIVE)/i' => 'PPP loan',

        // =====================================================================
        // OWNER INJECTIONS / CAPITAL CONTRIBUTIONS
        // =====================================================================
        '/OWNER\s*(CONTRIBUTION|DEPOSIT|LOAN|INVESTMENT)/i' => 'Owner capital injection',
        '/SHAREHOLDER\s*(CONTRIBUTION|LOAN|DEPOSIT)/i' => 'Shareholder contribution',
        '/CAPITAL\s*CONTRIBUTION/i' => 'Capital contribution',
        '/MEMBER\s*(CONTRIBUTION|DEPOSIT|LOAN)/i' => 'Member contribution',
        '/PARTNER\s*(CONTRIBUTION|DEPOSIT)/i' => 'Partner contribution',
        '/PERSONAL\s*(DEPOSIT|TRANSFER|FUNDS)/i' => 'Personal funds transfer',
        '/INVESTMENT\s*FROM\s*OWNER/i' => 'Owner investment',
        '/EQUITY\s*(CONTRIBUTION|INJECTION)/i' => 'Equity injection',

        // =====================================================================
        // TAX REFUNDS
        // =====================================================================
        '/IRS\s*TREAS/i' => 'IRS/Treasury payment',
        '/TREASURY\s*(DEPT|310)/i' => 'Treasury department',
        '/TAX\s*REFUND/i' => 'Tax refund',
        '/STATE\s*TAX\s*REF/i' => 'State tax refund',
        '/FRANCHISE\s*TAX\s*REF/i' => 'Franchise tax refund',
        '/SALES\s*TAX\s*REF/i' => 'Sales tax refund',

        // =====================================================================
        // REVERSALS, REFUNDS, AND ADJUSTMENTS
        // =====================================================================
        '/CHARGEBACK\s*REVERSAL/i' => 'Chargeback reversal',
        '/DISPUTE\s*CREDIT/i' => 'Dispute credit',
        '/PROVISIONAL\s*CREDIT/i' => 'Provisional credit',
        '/FEE\s*REVERSAL/i' => 'Fee reversal',
        '/FEE\s*REFUND/i' => 'Fee refund',
        '/NSF\s*FEE\s*REV/i' => 'NSF fee reversal',
        '/OD\s*FEE\s*REV/i' => 'Overdraft fee reversal',
        '/OVERDRAFT\s*FEE\s*REV/i' => 'Overdraft fee reversal',
        '/ADJUSTMENT\s*CREDIT/i' => 'Account adjustment',
        '/CORRECTION\s*CREDIT/i' => 'Account correction',
        '/ERROR\s*CORRECTION/i' => 'Error correction',
        '/REFUND\s*(CREDIT|FROM)/i' => 'Refund credit',
        '/RETURN\s*ITEM\s*CREDIT/i' => 'Return item credit',

        // =====================================================================
        // INTEREST AND BANK CREDITS
        // =====================================================================
        '/INTEREST\s*(PAYMENT|CREDIT|EARNED|PAID)/i' => 'Interest earned',
        '/DIVIDEND\s*(PAYMENT|CREDIT)/i' => 'Dividend',
        '/CASH\s*BACK/i' => 'Cashback reward',
        '/CASHBACK\s*REWARD/i' => 'Cashback reward',
        '/REWARD\s*(CREDIT|REDEMPTION)/i' => 'Reward credit',
        '/REBATE\s*(CREDIT|PAYMENT)/i' => 'Rebate',
        '/BONUS\s*CREDIT(?!.*PAYROLL)/i' => 'Bonus credit',
        '/PROMOTIONAL\s*CREDIT/i' => 'Promotional credit',
        '/SIGN\s*UP\s*BONUS/i' => 'Sign up bonus',
        '/REFERRAL\s*BONUS/i' => 'Referral bonus',

        // =====================================================================
        // INSURANCE PROCEEDS
        // =====================================================================
        '/INSURANCE\s*(CLAIM|PROCEED|PAYMENT|SETTLEMENT)/i' => 'Insurance proceeds',
        '/CLAIM\s*PAYMENT/i' => 'Insurance claim payment',
        '/SETTLEMENT\s*PAYMENT(?!.*CARD|.*MERCHANT)/i' => 'Settlement payment',

        // =====================================================================
        // MISC EXCLUSIONS
        // =====================================================================
        '/VENMO\s*(FROM|TRANSFER).*PERSONAL/i' => 'Personal Venmo transfer',
        '/CASHAPP\s*(FROM|TRANSFER).*PERSONAL/i' => 'Personal Cash App transfer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Industry-Specific Revenue Patterns
    |--------------------------------------------------------------------------
    */
    'industry_patterns' => [
        'restaurant' => [
            '/DOORDASH|UBER\s*EATS|GRUBHUB|POSTMATES/i' => 'Food delivery payout',
            '/YELP\s*RESERV|OPENTABLE/i' => 'Reservation platform',
            '/CAVIAR\s*(DEPOSIT|PAYOUT)/i' => 'Caviar payout',
            '/SEAMLESS\s*(DEPOSIT|PAYOUT)/i' => 'Seamless payout',
        ],
        'retail' => [
            '/POS\s*DEPOSIT|REGISTER\s*DEPOSIT/i' => 'POS deposit',
            '/INVENTORY\s*SALE/i' => 'Inventory sale',
        ],
        'professional_services' => [
            '/INVOICE\s*PAYMENT|CLIENT\s*PAYMENT/i' => 'Client payment',
            '/RETAINER\s*PAYMENT/i' => 'Retainer payment',
            '/CONSULTING\s*FEE/i' => 'Consulting fee',
        ],
        'healthcare' => [
            '/INSURANCE\s*REIMBURSE/i' => 'Insurance reimbursement',
            '/MEDICARE|MEDICAID/i' => 'Government healthcare payment',
            '/PATIENT\s*PAYMENT/i' => 'Patient payment',
        ],
        'construction' => [
            '/PROGRESS\s*PAYMENT/i' => 'Progress payment',
            '/CONTRACT\s*PAYMENT/i' => 'Contract payment',
            '/DRAW\s*REQUEST(?!.*CREDIT\s*LINE)/i' => 'Construction draw',
        ],
        'ecommerce' => [
            '/SHOPIFY\s*(PAYOUT|DEPOSIT)/i' => 'Shopify payout',
            '/WOOCOMMERCE/i' => 'WooCommerce payout',
            '/BIGCOMMERCE/i' => 'BigCommerce payout',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Heuristic Thresholds
    |--------------------------------------------------------------------------
    */
    'heuristics' => [
        // Large deposits above this amount require manual review
        'large_deposit_threshold' => 50000,

        // Round number deposits (exact thousands) are flagged for review
        'round_number_threshold' => 1000,

        // Deposits this close to common loan amounts are suspicious
        'suspicious_loan_amounts' => [5000, 10000, 15000, 20000, 25000, 30000, 50000, 75000, 100000],

        // Default confidence for unmatched transactions
        'default_confidence' => 0.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Known MCA Funders (for reference and detection)
    |--------------------------------------------------------------------------
    */
    'known_funders' => [
        'ondeck',
        'kabbage',
        'fundbox',
        'bluevine',
        'credibly',
        'kapitus',
        'rapid finance',
        'can capital',
        'national funding',
        'bizfi',
        'biz2credit',
        'lendio',
        'fundera',
        'square capital',
        'paypal working capital',
        'amazon lending',
        'shopify capital',
        'stripe capital',
        'clearco',
        'clearbanc',
        'libertas',
        'forward financing',
        'fora financial',
        'reliant funding',
        'headway capital',
        'behalf',
        'greenbox capital',
        'mulligan funding',
        'united capital source',
    ],
];
