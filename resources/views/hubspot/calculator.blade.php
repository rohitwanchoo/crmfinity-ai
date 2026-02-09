<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCA Offer Calculator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; }
    </style>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">MCA Offer Calculator</h1>

            @if($offer)
                {{-- Display existing offer --}}
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h2 class="text-lg font-semibold text-blue-800 mb-3">Offer Details</h2>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Funded Amount:</span>
                            <span class="font-semibold text-gray-900">${{ number_format($offer->advance_amount, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Factor Rate:</span>
                            <span class="font-semibold text-gray-900">{{ number_format($offer->factor_rate, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Total Payback:</span>
                            <span class="font-semibold text-gray-900">${{ number_format($offer->advance_amount * $offer->factor_rate, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Term:</span>
                            <span class="font-semibold text-gray-900">{{ $offer->term_months }} months</span>
                        </div>
                        @php
                            $totalPayback = $offer->advance_amount * $offer->factor_rate;
                            $monthlyPayment = $offer->term_months > 0 ? $totalPayback / $offer->term_months : 0;
                            $weeklyPayment = $monthlyPayment / 4.33;
                            $dailyPayment = $monthlyPayment / 21.67;
                        @endphp
                        <div>
                            <span class="text-gray-600">Monthly Payment:</span>
                            <span class="font-semibold text-blue-600">${{ number_format($monthlyPayment, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Weekly Payment:</span>
                            <span class="font-semibold text-green-600">${{ number_format($weeklyPayment, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">Daily Payment:</span>
                            <span class="font-semibold text-purple-600">${{ number_format($dailyPayment, 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600">True Revenue:</span>
                            <span class="font-semibold text-gray-900">${{ number_format($offer->true_revenue_monthly, 2) }}/mo</span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Calculator Form --}}
            <form id="calculator-form" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">True Revenue (Monthly)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input type="number" id="true-revenue" name="true_revenue"
                                value="{{ $offer ? $offer->true_revenue_monthly : '' }}"
                                class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="0.00" step="100" onchange="calculate()">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Existing MCA Payment</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input type="number" id="existing-payment" name="existing_payment"
                                value="{{ $offer ? $offer->existing_mca_payment : 0 }}"
                                class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="0.00" step="100" onchange="calculate()">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Withhold %</label>
                        <input type="number" id="withhold-percent" name="withhold_percent"
                            value="{{ $offer ? $offer->withhold_percent : 20 }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            min="5" max="25" step="1" onchange="calculate()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Factor Rate</label>
                        <input type="number" id="factor-rate" name="factor_rate"
                            value="{{ $offer ? $offer->factor_rate : 1.30 }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            min="1.10" max="1.60" step="0.01" onchange="calculate()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Term (Months)</label>
                        <select id="term-months" name="term_months"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            onchange="calculate()">
                            @for($i = 1; $i <= 24; $i++)
                                <option value="{{ $i }}" {{ ($offer && $offer->term_months == $i) || (!$offer && $i == 9) ? 'selected' : '' }}>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
            </form>

            {{-- Results --}}
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Calculated Offer</h3>

                <div class="space-y-3">
                    <div class="flex justify-between items-center p-3 bg-purple-100 rounded-lg">
                        <span class="text-purple-700 font-medium">Funded Amount</span>
                        <span class="text-2xl font-bold text-purple-600" id="result-funded">$0.00</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex justify-between items-center p-2 bg-white border rounded">
                            <span class="text-gray-600 text-sm">Total Payback</span>
                            <span class="font-semibold text-gray-900" id="result-payback">$0.00</span>
                        </div>
                        <div class="flex justify-between items-center p-2 bg-white border rounded">
                            <span class="text-gray-600 text-sm">Cap Amount</span>
                            <span class="font-semibold text-gray-900" id="result-cap">$0.00</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="flex flex-col items-center p-3 bg-blue-50 rounded-lg">
                            <span class="text-blue-600 text-xs uppercase font-medium">Monthly</span>
                            <span class="text-lg font-bold text-blue-700" id="result-monthly">$0.00</span>
                        </div>
                        <div class="flex flex-col items-center p-3 bg-green-50 rounded-lg">
                            <span class="text-green-600 text-xs uppercase font-medium">Weekly</span>
                            <span class="text-lg font-bold text-green-700" id="result-weekly">$0.00</span>
                        </div>
                        <div class="flex flex-col items-center p-3 bg-purple-50 rounded-lg">
                            <span class="text-purple-600 text-xs uppercase font-medium">Daily</span>
                            <span class="text-lg font-bold text-purple-700" id="result-daily">$0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function formatCurrency(value) {
            return '$' + value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function calculate() {
            const trueRevenue = parseFloat(document.getElementById('true-revenue').value) || 0;
            const existingPayment = parseFloat(document.getElementById('existing-payment').value) || 0;
            const withholdPercent = parseFloat(document.getElementById('withhold-percent').value) || 20;
            const factorRate = parseFloat(document.getElementById('factor-rate').value) || 1.30;
            const termMonths = parseInt(document.getElementById('term-months').value) || 9;

            // Calculate cap amount and new payment available
            const capAmount = trueRevenue * (withholdPercent / 100);
            const newPaymentAvailable = Math.max(0, capAmount - existingPayment);

            // Calculate funded amount based on withhold constraint
            const fundedAmount = (newPaymentAvailable * termMonths) / factorRate;

            // Calculate offer details
            const totalPayback = fundedAmount * factorRate;
            const monthlyPayment = termMonths > 0 ? totalPayback / termMonths : 0;
            const weeklyPayment = monthlyPayment / 4.33;
            const dailyPayment = monthlyPayment / 21.67;

            // Update UI
            document.getElementById('result-funded').textContent = formatCurrency(fundedAmount);
            document.getElementById('result-payback').textContent = formatCurrency(totalPayback);
            document.getElementById('result-cap').textContent = formatCurrency(capAmount);
            document.getElementById('result-monthly').textContent = formatCurrency(monthlyPayment);
            document.getElementById('result-weekly').textContent = formatCurrency(weeklyPayment);
            document.getElementById('result-daily').textContent = formatCurrency(dailyPayment);
        }

        // Calculate on page load
        document.addEventListener('DOMContentLoaded', calculate);
    </script>
</body>
</html>
